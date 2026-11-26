<?php
/**
 * Sensei LMS Student Progress Migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration;

use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates student lesson completion and quiz attempt progress from Sensei to Tutor LMS.
 */
class StudentProgress implements StudentProgressInterface {

	/**
	 * Migrate student progress for a course or user.
	 *
	 * @since 2.3.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID (optional, 0 for all students of course).
	 * @return int Total progress items migrated.
	 */
	public function migrate( int $course_id, int $user_id = 0 ): int {
		if ( $course_id <= 0 ) {
			return 0;
		}

		$lessons_migrated  = $this->migrate_lesson_completions( $course_id, $user_id );
		$attempts_migrated = $this->migrate_quiz_attempts( $course_id, $user_id );
		$this->migrate_course_completions( $course_id, $user_id );

		return $lessons_migrated + $attempts_migrated;
	}

	/**
	 * Mark course completion in Tutor LMS for every student who finished the course in Sensei.
	 *
	 * Checks the HPPS progress table first; falls back to wp_comments for default storage.
	 * Creates a Tutor 'course_completed' comment only when one does not already exist.
	 *
	 * @since 2.3.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Optional student user ID. 0 = all students.
	 * @return void
	 */
	private function migrate_course_completions( int $course_id, int $user_id = 0 ): void {
		global $wpdb;

		$user_clause = $user_id > 0 ? $wpdb->prepare( ' AND user_id = %d', $user_id ) : '';

		$completions = array();

		// Try HPPS custom table first.
		$progress_table = $wpdb->prefix . 'sensei_lms_progress';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $progress_table ) ) === $progress_table ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$completions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id,
						COALESCE(completed_at, updated_at, created_at) AS completed_at
					FROM {$progress_table}
					WHERE type = 'course'
					AND post_id = %d
					AND status = 'complete'
					AND user_id > 0" . $user_clause,
					$course_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Fall back to Sensei comment storage when HPPS is not active.
		if ( empty( $completions ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$completions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, comment_date AS completed_at
					FROM {$wpdb->comments}
					WHERE comment_post_ID = %d
					AND comment_type = 'sensei_course_status'
					AND comment_approved = 'complete'
					AND user_id > 0" . $user_clause,
					$course_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( empty( $completions ) ) {
			return;
		}

		foreach ( $completions as $row ) {
			$uid  = (int) $row->user_id;
			$date = ! empty( $row->completed_at ) ? $row->completed_at : current_time( 'mysql' );

			if ( $uid <= 0 ) {
				continue;
			}

			// Skip if Tutor already has a course_completed record for this user+course.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments}
					WHERE comment_post_ID = %d
					AND user_id = %d
					AND comment_type = 'course_completed'
					AND comment_agent = 'TutorLMSPlugin'
					LIMIT 1",
					$course_id,
					$uid
				)
			);

			if ( $exists ) {
				continue;
			}

			// Generate a unique hash as Tutor LMS does in CourseModel::mark_course_as_completed().
			$hash = substr( md5( $date . $course_id . $uid . wp_generate_password( 8 ) ), 0, 16 );

			$wpdb->insert(
				$wpdb->comments,
				array(
					'comment_post_ID'  => $course_id,
					'comment_author'   => $uid,
					'comment_date'     => $date,
					'comment_date_gmt' => get_gmt_from_date( $date ),
					'comment_content'  => $hash,
					'comment_approved' => 'approved',
					'comment_agent'    => 'TutorLMSPlugin',
					'comment_type'     => 'course_completed',
					'user_id'          => $uid,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
			);
		}
	}

	/**
	 * Migrate lesson completions for a course from Sensei 4.x progress table.
	 *
	 * @since 2.3.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID (optional).
	 * @return int Number of completed lesson records created.
	 */
	public function migrate_lesson_completions( int $course_id, int $user_id = 0 ): int {
		global $wpdb;

		$lessons = get_posts(
			array(
				'post_type'      => array( 'lesson', tutor()->lesson_post_type ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_tutor_course_id_for_lesson',
						'value' => $course_id,
					),
				),
			)
		);

		if ( empty( $lessons ) ) {
			return 0;
		}

		$migrated_count = 0;
		$user_clause    = $user_id > 0 ? $wpdb->prepare( ' AND user_id = %d', $user_id ) : '';
		$format_ids     = implode( ',', array_map( 'intval', $lessons ) );

		$completions = array();

		$progress_table = $wpdb->prefix . 'sensei_lms_progress';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $progress_table ) ) === $progress_table ) {
			// HPPS: custom progress table.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$completions = $wpdb->get_results(
				"SELECT post_id AS lesson_id, user_id, COALESCE(completed_at, updated_at, created_at) AS completed_at
				FROM {$progress_table}
				WHERE type = 'lesson'
				AND post_id IN ({$format_ids})
				AND status = 'complete'
				AND user_id > 0" . $user_clause
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		}

		// Fallback: Sensei default comment-based lesson completion storage.
		if ( empty( $completions ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$completions = $wpdb->get_results(
				"SELECT comment_post_ID AS lesson_id, user_id, comment_date AS completed_at
				FROM {$wpdb->comments}
				WHERE comment_type = 'sensei_lesson_status'
				AND comment_approved = 'complete'
				AND comment_post_ID IN ({$format_ids})
				AND user_id > 0" . $user_clause
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! empty( $completions ) ) {
			foreach ( $completions as $row ) {
				$lid   = (int) $row->lesson_id;
				$uid   = (int) $row->user_id;
				$cdate = ! empty( $row->completed_at ) ? $row->completed_at : current_time( 'mysql' );

				if ( $this->mark_lesson_complete( $lid, $course_id, $uid, $cdate ) ) {
					++$migrated_count;
				}
			}
		}

		return $migrated_count;
	}

	/**
	 * Mark a lesson completed in Tutor LMS for a student.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $lesson_id    Lesson ID.
	 * @param int    $course_id    Course ID.
	 * @param int    $user_id      Student User ID.
	 * @param string $completed_at Completion timestamp.
	 * @return bool True if newly marked complete, false if already complete or on failure.
	 */
	public function mark_lesson_complete( int $lesson_id, int $course_id, int $user_id, string $completed_at = '' ): bool {
		global $wpdb;

		if ( $lesson_id <= 0 || $user_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Check if already marked complete via comment record.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments}
				WHERE comment_post_ID = %d
				AND user_id = %d
				AND comment_type = 'lesson_completed'
				LIMIT 1",
				$lesson_id,
				$user_id
			)
		);

		$time = ! empty( $completed_at ) ? strtotime( $completed_at ) : time();
		$date = ! empty( $completed_at ) ? $completed_at : current_time( 'mysql' );

		// Always keep user meta in sync.
		update_user_meta( $user_id, '_tutor_completed_lesson_id_' . $lesson_id, $time );

		if ( $exists ) {
			return true;
		}

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $lesson_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_date'         => $date,
				'comment_date_gmt'     => get_gmt_from_date( $date ),
				'comment_content'      => 'completed',
				'comment_approved'     => 1,
				'comment_type'         => 'lesson_completed',
				'user_id'              => $user_id,
			)
		);

		if ( $comment_id ) {
			clean_comment_cache( $comment_id );
			update_comment_meta( $comment_id, 'course_id', $course_id );
			return true;
		}

		// wp_insert_comment() failed — user meta was updated but comment record was not created.
		return false;
	}

	/**
	 * Migrate quiz attempts from Sensei 4.x submissions table to Tutor LMS.
	 *
	 * Each Sensei submission row is migrated as a separate Tutor attempt, preserving
	 * full attempt history. The Sensei submission ID is stored in attempt_info to
	 * guarantee per-submission idempotency on re-runs.
	 *
	 * @since 2.3.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID (optional).
	 * @return int Number of quiz attempts migrated.
	 */
	public function migrate_quiz_attempts( int $course_id, int $user_id = 0 ): int {
		global $wpdb;

		$submissions_table    = $wpdb->prefix . 'sensei_lms_quiz_submissions';
		$answers_table        = $wpdb->prefix . 'sensei_lms_quiz_submission_answers';
		$use_hpps             = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $submissions_table ) ) === $submissions_table;
		$answers_table_exists = $use_hpps && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $answers_table ) ) === $answers_table;

		$quizzes = get_posts(
			array(
				'post_type'      => array( 'quiz', tutor()->quiz_post_type ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_tutor_course_id_for_quiz',
						'value' => $course_id,
					),
				),
			)
		);

		if ( empty( $quizzes ) ) {
			return 0;
		}

		$migrated_count = 0;
		$user_clause    = $user_id > 0 ? $wpdb->prepare( ' AND user_id = %d', $user_id ) : '';
		$format_ids     = implode( ',', array_map( 'intval', $quizzes ) );

		$attempts = array();

		if ( $use_hpps ) {
			// HPPS: each submissions row = one attempt, preserving full attempt history.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$attempts = $wpdb->get_results(
				"SELECT id AS submission_id, quiz_id, user_id, final_grade, created_at AS attempt_date
				FROM {$submissions_table}
				WHERE quiz_id IN ({$format_ids})
				AND user_id > 0" . $user_clause . '
				ORDER BY id ASC'
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		}

		// Fallback: Sensei default comment-based quiz status storage.
		// Each comment_type='sensei_quiz_status' row = one completed quiz attempt.
		// Grade is stored in commentmeta with key 'grade' as a percentage.
		if ( empty( $attempts ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$comment_rows = $wpdb->get_results(
				"SELECT c.comment_ID AS submission_id, c.comment_post_ID AS quiz_id,
					c.user_id, c.comment_date AS attempt_date
				FROM {$wpdb->comments} c
				WHERE c.comment_type = 'sensei_quiz_status'
				AND c.comment_post_ID IN ({$format_ids})
				AND c.user_id > 0" . $user_clause . '
				ORDER BY c.comment_ID ASC'
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $comment_rows as $cr ) {
				$grade_pct         = (float) get_comment_meta( (int) $cr->submission_id, 'grade', true );
				$cr->final_grade   = $grade_pct;
				$cr->submission_id = 0; // No HPPS submission — no per-answer data available.
				$attempts[]        = $cr;
			}
		}

		if ( ! empty( $attempts ) ) {
			// Build question maps per quiz once to avoid per-answer SQL LIKE lookups.
			$question_maps = array();

			foreach ( $attempts as $row ) {
				$qid                  = (int) $row->quiz_id;
				$uid                  = (int) $row->user_id;
				$sensei_submission_id = (int) $row->submission_id;
				$attempt_date         = ! empty( $row->attempt_date ) ? $row->attempt_date : current_time( 'mysql' );
				$grade                = null !== $row->final_grade ? (float) $row->final_grade : 0.0;

				// Count how many answers Sensei actually recorded for this submission.
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$actual_answered = ( $sensei_submission_id > 0 && $answers_table_exists ) ? (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$answers_table} WHERE submission_id = %d",
						$sensei_submission_id
					)
				) : 0;
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				$attempt_id = $this->record_quiz_attempt( $qid, $course_id, $uid, $attempt_date, $grade, $sensei_submission_id, $actual_answered );
				$is_new     = $attempt_id > 0;

				// When the attempt already existed (interrupted migration), look up its ID
				// so we can still migrate any answers that were not yet written.
				if ( ! $is_new ) {
					$attempt_id = $this->find_existing_attempt( $qid, $uid, $sensei_submission_id );
				}

				if ( $attempt_id > 0 ) {
					// Build the question map once per quiz ID to avoid repeated DB lookups.
					if ( ! isset( $question_maps[ $qid ] ) ) {
						$question_maps[ $qid ] = $this->build_question_map( $qid );
					}
					$this->migrate_submission_answers( $attempt_id, $sensei_submission_id, $qid, $uid, $question_maps[ $qid ] );
				}

				if ( $is_new ) {
					++$migrated_count;
				}
			}
		}

		return $migrated_count;
	}

	/**
	 * Record a single quiz attempt into tutor_quiz_attempts table.
	 *
	 * Uses the Sensei submission ID stored in attempt_info for per-submission
	 * idempotency, allowing multiple attempts per user per quiz.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $quiz_id              Tutor quiz ID.
	 * @param int    $course_id            Course ID.
	 * @param int    $user_id              Student User ID.
	 * @param string $attempt_date         Date string.
	 * @param float  $grade                Grade percentage from Sensei submission (0–100).
	 * @param int    $sensei_submission_id Sensei submission primary key for idempotency.
	 * @return int Inserted attempt_id on success, 0 if already exists or on failure.
	 */
	public function record_quiz_attempt( int $quiz_id, int $course_id, int $user_id, string $attempt_date = '', float $grade = 0.0, int $sensei_submission_id = 0, int $actual_answered = -1 ): int {
		global $wpdb;

		// Per-submission idempotency: check if this exact Sensei submission was already imported.
		if ( $sensei_submission_id > 0 ) {
			$marker   = $wpdb->esc_like( '"_sensei_submission_id";i:' . $sensei_submission_id . ';' );
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT attempt_id FROM {$wpdb->prefix}tutor_quiz_attempts
					WHERE quiz_id = %d AND user_id = %d AND attempt_info LIKE %s
					LIMIT 1",
					$quiz_id,
					$user_id,
					'%' . $marker . '%'
				)
			);
		} else {
			// Fallback: deduplicate by date when no submission ID is available.
			$date     = ! empty( $attempt_date ) ? $attempt_date : current_time( 'mysql' );
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT attempt_id FROM {$wpdb->prefix}tutor_quiz_attempts
					WHERE quiz_id = %d AND user_id = %d AND course_id = %d AND attempt_started_at = %s
					LIMIT 1",
					$quiz_id,
					$user_id,
					$course_id,
					$date
				)
			);
		}

		if ( $existing ) {
			return 0;
		}

		// Calculate total questions and marks for this quiz.
		$questions_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(question_id) AS total_q, COALESCE(SUM(question_mark), 0) AS total_m
				FROM {$wpdb->prefix}tutor_quiz_questions
				WHERE quiz_id = %d",
				$quiz_id
			)
		);

		$total_questions = ! empty( $questions_data ) ? (int) $questions_data->total_q : 0;
		$total_marks     = ! empty( $questions_data ) ? (float) $questions_data->total_m : 0.00;

		// Passmark threshold from quiz options.
		$passmark = 80;
		$opts     = get_post_meta( $quiz_id, 'tutor_quiz_option', true );
		if ( ! empty( $opts['passing_grade'] ) ) {
			$passmark = (int) $opts['passing_grade'];
		}

		// Convert Sensei percentage grade to earned marks.
		$earned_marks = ( $grade > 0 && $total_marks > 0 ) ? ( $grade / 100 * $total_marks ) : (float) $grade;
		$result       = ( $grade >= $passmark ) ? 'pass' : 'fail';
		$date         = ! empty( $attempt_date ) ? $attempt_date : current_time( 'mysql' );

		$attempt_info = array( '_sensei_submission_id' => $sensei_submission_id );

		// Use the actual answer count when provided; fall back to total_questions.
		$answered_count = $actual_answered >= 0 ? $actual_answered : $total_questions;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'tutor_quiz_attempts',
			array(
				'course_id'                => $course_id,
				'quiz_id'                  => $quiz_id,
				'user_id'                  => $user_id,
				'total_questions'          => $total_questions,
				'total_answered_questions' => $answered_count,
				'total_marks'              => $total_marks > 0 ? $total_marks : 100.00,
				'earned_marks'             => $earned_marks,
				'attempt_info'             => maybe_serialize( $attempt_info ),
				'attempt_status'           => 'attempt_ended',
				'attempt_ip'               => '',
				'attempt_started_at'       => $date,
				'attempt_ended_at'         => $date,
				'is_manually_reviewed'     => 0,
				'result'                   => $result,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Look up an existing Tutor attempt by Sensei submission ID or by quiz/user/date.
	 *
	 * Used when record_quiz_attempt() finds an existing row (returns 0) so that
	 * migrate_submission_answers() can still run against the correct attempt_id.
	 *
	 * @param int $quiz_id              Tutor quiz ID.
	 * @param int $user_id              Student user ID.
	 * @param int $sensei_submission_id Sensei submission primary key.
	 * @return int Attempt ID, or 0 if not found.
	 */
	private function find_existing_attempt( int $quiz_id, int $user_id, int $sensei_submission_id ): int {
		global $wpdb;

		if ( $sensei_submission_id > 0 ) {
			$marker = $wpdb->esc_like( '"_sensei_submission_id";i:' . $sensei_submission_id . ';' );
			$id     = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT attempt_id FROM {$wpdb->prefix}tutor_quiz_attempts
					WHERE quiz_id = %d AND user_id = %d AND attempt_info LIKE %s
					LIMIT 1",
					$quiz_id,
					$user_id,
					'%' . $marker . '%'
				)
			);
			if ( $id ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/**
	 * Build a map of Sensei question post ID → Tutor question row for a given quiz.
	 *
	 * Uses PHP-side deserialization of question_settings to avoid the esc_like/prepare
	 * double-escaping issue that causes SQL LIKE to miss the "_sensei_question_id" key.
	 *
	 * @param int $quiz_id Tutor quiz post ID.
	 * @return array<int, object> Map of sensei_question_id => stdClass{question_id, question_mark, question_type}.
	 */
	private function build_question_map( int $quiz_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT question_id, question_mark, question_type, question_settings
				FROM {$wpdb->prefix}tutor_quiz_questions
				WHERE quiz_id = %d",
				$quiz_id
			)
		);

		$map = array();
		foreach ( $rows as $row ) {
			$settings = maybe_unserialize( $row->question_settings );
			if ( ! is_array( $settings ) ) {
				continue;
			}
			$sensei_id = isset( $settings['_sensei_question_id'] ) ? (int) $settings['_sensei_question_id'] : 0;
			if ( $sensei_id > 0 ) {
				$map[ $sensei_id ] = $row;
			}
		}

		return $map;
	}

	/**
	 * Migrate per-answer data for a quiz attempt from Sensei to Tutor LMS.
	 *
	 * Reads from sensei_lms_quiz_submission_answers (Sensei 4.x) and inserts
	 * into tutor_quiz_attempt_answers. Requires that questions were migrated
	 * with _sensei_question_id stored in question_settings.
	 *
	 * @since 2.3.0
	 *
	 * @param int   $attempt_id           Tutor attempt ID (tutor_quiz_attempts.attempt_id).
	 * @param int   $sensei_submission_id Sensei submission ID.
	 * @param int   $quiz_id              Tutor quiz post ID.
	 * @param int   $user_id              Student user ID.
	 * @param array $question_map         Pre-built map from build_question_map(); omit to auto-build.
	 * @return void
	 */
	private function migrate_submission_answers( int $attempt_id, int $sensei_submission_id, int $quiz_id, int $user_id, array $question_map = array() ): void {
		global $wpdb;

		if ( $attempt_id <= 0 || $sensei_submission_id <= 0 ) {
			return;
		}

		$answers_table = $wpdb->prefix . 'sensei_lms_quiz_submission_answers';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $answers_table ) ) !== $answers_table ) {
			return;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$answers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT question_id, value, points
				FROM {$answers_table}
				WHERE submission_id = %d",
				$sensei_submission_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $answers ) ) {
			return;
		}

		// Build question map on first use (passed in by caller to avoid per-submission rebuilds).
		if ( empty( $question_map ) ) {
			$question_map = $this->build_question_map( $quiz_id );
		}

		// Question types requiring manual review (is_correct deferred to 0 pending review).
		$manual_review_types = array( 'short_answer', 'open_ended' );

		foreach ( $answers as $row ) {
			$sensei_question_id = (int) $row->question_id;
			$tutor_question     = $question_map[ $sensei_question_id ] ?? null;

			if ( ! $tutor_question ) {
				continue;
			}

			$question_mark = (float) $tutor_question->question_mark;
			$given_answer  = ! empty( $row->value ) ? (string) $row->value : '';
			$achieved_mark = null !== $row->points ? (float) $row->points : 0.0;

			// Manual-review types: set is_correct = 0 (unreviewed); auto-graded types use points.
			$is_correct = in_array( $tutor_question->question_type, $manual_review_types, true ) ? 0 : ( $achieved_mark > 0 ? 1 : 0 );

			// Skip if this answer row already exists (re-run safety).
			$already = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}tutor_quiz_attempt_answers
					WHERE quiz_attempt_id = %d AND question_id = %d
					LIMIT 1",
					$attempt_id,
					(int) $tutor_question->question_id
				)
			);

			if ( $already ) {
				continue;
			}

			$wpdb->insert(
				$wpdb->prefix . 'tutor_quiz_attempt_answers',
				array(
					'user_id'         => $user_id,
					'quiz_id'         => $quiz_id,
					'question_id'     => (int) $tutor_question->question_id,
					'quiz_attempt_id' => $attempt_id,
					'given_answer'    => $given_answer,
					'question_mark'   => $question_mark,
					'achieved_mark'   => $achieved_mark,
					'minus_mark'      => 0,
					'is_correct'      => $is_correct,
				),
				array( '%d', '%d', '%d', '%d', '%s', '%f', '%f', '%d', '%d' )
			);
		}
	}
}
