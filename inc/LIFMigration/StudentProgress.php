<?php
/**
 * LifterLMS student progress migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration;

use Tutor\Models\QuizModel;
use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates LifterLMS lesson completions and quiz attempts to Tutor LMS.
 *
 * Lifter stores lesson completions in `lifterlms_user_postmeta` as `_is_complete = yes`
 * on the lesson post ID. Quiz attempts live in `lifterlms_quiz_attempts`. After course
 * migration those IDs remain Tutor lessons/quizzes.
 *
 * @since 2.6.0
 * @since 2.6.0 Migrate completed Lifter quiz attempts into tutor_quiz_attempts.
 */
class StudentProgress implements StudentProgressInterface {

	/**
	 * Lifter attempt statuses that count as completed for Tutor progress.
	 *
	 * @since 2.6.0
	 *
	 * @var string[]
	 */
	const COMPLETED_ATTEMPT_STATUSES = array( 'pass', 'fail' );

	/**
	 * Migrate LifterLMS lesson progress and quiz attempts to Tutor LMS for a course.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id The course ID.
	 * @param int $user_id   Optional user ID. When > 0, only that student's progress is migrated.
	 *
	 * @return void
	 */
	public function migrate( int $course_id, int $user_id = 0 ) {
		if ( $course_id <= 0 || ! is_object( get_post( $course_id ) ) ) {
			return;
		}

		$this->migrate_lessons( $course_id, $user_id );
		$this->migrate_quizzes( $course_id, $user_id );
	}

	/**
	 * Migrate completed LifterLMS lessons to Tutor lesson-complete user meta.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Optional user ID to scope migration.
	 *
	 * @return void
	 */
	private function migrate_lessons( int $course_id, int $user_id = 0 ) {
		$progress_rows = $this->get_completed_lessons( $course_id, $user_id );
		if ( empty( $progress_rows ) ) {
			return;
		}

		$lesson_post_type = tutor()->lesson_post_type;

		foreach ( $progress_rows as $row ) {
			$progress_user_id = (int) ( $row->user_id ?? 0 );
			$lesson_id        = (int) ( $row->item_id ?? 0 );

			if ( $progress_user_id <= 0 || $lesson_id <= 0 ) {
				continue;
			}

			$lesson = get_post( $lesson_id );
			if ( ! $lesson || $lesson_post_type !== $lesson->post_type ) {
				continue;
			}

			$completed = $this->resolve_completion_timestamp( $row );
			update_user_meta( $progress_user_id, "_tutor_completed_lesson_id_{$lesson_id}", $completed );
		}
	}

	/**
	 * Migrate completed LifterLMS quiz attempts to Tutor quiz attempts.
	 *
	 * Tutor course progress counts distinct quizzes with a non-started attempt.
	 * Full per-question answer history is not reconstructed here.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Optional user ID to scope migration.
	 *
	 * @return void
	 */
	private function migrate_quizzes( int $course_id, int $user_id = 0 ) {
		$attempt_rows = $this->get_completed_quiz_attempts( $course_id, $user_id );
		if ( empty( $attempt_rows ) ) {
			return;
		}

		$quiz_post_type = tutor()->quiz_post_type;
		$seen           = array();

		foreach ( $attempt_rows as $row ) {
			$progress_user_id = (int) ( $row->user_id ?? 0 );
			$quiz_id          = (int) ( $row->item_id ?? 0 );

			if ( $progress_user_id <= 0 || $quiz_id <= 0 ) {
				continue;
			}

			$dedupe_key = $progress_user_id . ':' . $quiz_id;
			if ( isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}
			$seen[ $dedupe_key ] = true;

			$quiz = get_post( $quiz_id );
			if ( ! $quiz || $quiz_post_type !== $quiz->post_type ) {
				continue;
			}

			if ( tutor_utils()->has_attempted_quiz( $progress_user_id, $quiz_id ) ) {
				continue;
			}

			$this->insert_quiz_attempt( $course_id, $quiz_id, $progress_user_id, $row );
		}
	}

	/**
	 * Insert a Tutor quiz attempt from a Lifter quiz attempt row.
	 *
	 * @since 2.6.0
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $quiz_id   Quiz ID.
	 * @param int    $user_id   Student user ID.
	 * @param object $row       Lifter quiz attempt row.
	 *
	 * @return void
	 */
	private function insert_quiz_attempt( int $course_id, int $quiz_id, int $user_id, object $row ) {
		global $wpdb;

		$status = strtolower( (string) ( $row->status ?? '' ) );
		$result = ( 'pass' === $status ) ? QuizModel::RESULT_PASS : QuizModel::RESULT_FAIL;

		$marks           = $this->resolve_attempt_marks( $quiz_id, $row );
		$total_questions = (int) ( $marks['total_questions'] ?? 0 );
		$answered        = (int) ( $marks['answered'] ?? $total_questions );

		$started_ts = $this->resolve_timestamp_field( $row, array( 'start_date', 'end_date' ) );
		$ended_ts   = $this->resolve_timestamp_field( $row, array( 'end_date', 'start_date' ) );

		$attempt_info = get_post_meta( $quiz_id, 'tutor_quiz_option', true );
		if ( ! is_string( $attempt_info ) ) {
			$attempt_info = maybe_serialize( $attempt_info );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'tutor_quiz_attempts',
			array(
				'course_id'                => $course_id,
				'quiz_id'                  => $quiz_id,
				'user_id'                  => $user_id,
				'total_questions'          => $total_questions,
				'total_answered_questions' => $answered,
				'total_marks'              => (float) $marks['total_marks'],
				'earned_marks'             => (float) $marks['earned_marks'],
				'attempt_info'             => $attempt_info ? $attempt_info : '',
				'attempt_status'           => QuizModel::ATTEMPT_ENDED,
				'attempt_started_at'       => wp_date( 'Y-m-d H:i:s', $started_ts ),
				'attempt_ended_at'         => wp_date( 'Y-m-d H:i:s', $ended_ts ),
				'result'                   => $result,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $wpdb->last_error );
		}
	}

	/**
	 * Resolve total/earned marks from a Lifter attempt row.
	 *
	 * Prefer points embedded in the attempt `questions` payload; fall back to
	 * grade percentage applied against Tutor quiz question marks.
	 *
	 * @since 2.6.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param object $row     Lifter attempt row.
	 *
	 * @return array{total_marks: float, earned_marks: float, total_questions: int, answered: int}
	 */
	private function resolve_attempt_marks( int $quiz_id, object $row ): array {
		$questions = maybe_unserialize( $row->questions ?? '' );
		if ( is_array( $questions ) && ! empty( $questions ) ) {
			$total_marks     = 0.0;
			$earned_marks    = 0.0;
			$total_questions = 0;
			$answered        = 0;

			foreach ( $questions as $question ) {
				if ( ! is_array( $question ) ) {
					continue;
				}

				$points = isset( $question['points'] ) ? (float) $question['points'] : 0.0;
				if ( $points <= 0 ) {
					continue;
				}

				++$total_questions;
				$total_marks += $points;
				$earned_marks += isset( $question['earned'] ) ? (float) $question['earned'] : 0.0;

				if ( isset( $question['answer'] ) || isset( $question['correct'] ) ) {
					++$answered;
				}
			}

			if ( $total_questions > 0 ) {
				return array(
					'total_marks'     => $total_marks,
					'earned_marks'    => $earned_marks,
					'total_questions' => $total_questions,
					'answered'        => $answered > 0 ? $answered : $total_questions,
				);
			}
		}

		global $wpdb;
		$tutor_totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(question_id) AS question_count, COALESCE(SUM(question_mark), 0) AS total_marks
				FROM {$wpdb->prefix}tutor_quiz_questions
				WHERE quiz_id = %d",
				$quiz_id
			)
		);

		$total_questions = $tutor_totals ? (int) $tutor_totals->question_count : 0;
		$total_marks     = $tutor_totals ? (float) $tutor_totals->total_marks : 0.0;
		$grade           = isset( $row->grade ) ? (float) $row->grade : 0.0;
		$earned_marks    = ( $total_marks > 0 && $grade > 0 ) ? ( $total_marks * $grade ) / 100 : 0.0;

		return array(
			'total_marks'     => $total_marks,
			'earned_marks'    => $earned_marks,
			'total_questions' => $total_questions,
			'answered'        => $total_questions,
		);
	}

	/**
	 * Fetch completed LifterLMS lesson rows for a course.
	 *
	 * Uses `_tutor_course_id_for_lesson` set during course migration so progress
	 * stays scoped to the Tutor course even after CPT conversion.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Optional user ID to scope results.
	 *
	 * @return array<int, object>
	 */
	private function get_completed_lessons( int $course_id, int $user_id = 0 ): array {
		global $wpdb;

		$table            = $wpdb->prefix . 'lifterlms_user_postmeta';
		$lesson_post_type = tutor()->lesson_post_type;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $user_id > 0 ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT upm.user_id, upm.post_id AS item_id, MAX(upm.updated_date) AS updated_date
					FROM {$table} upm
					INNER JOIN {$wpdb->posts} p ON p.ID = upm.post_id
					INNER JOIN {$wpdb->postmeta} cm
						ON cm.post_id = p.ID
						AND cm.meta_key = '_tutor_course_id_for_lesson'
						AND cm.meta_value = %s
					WHERE upm.meta_key = '_is_complete'
						AND upm.meta_value = 'yes'
						AND upm.user_id = %d
						AND p.post_type = %s
					GROUP BY upm.user_id, upm.post_id",
					(string) $course_id,
					$user_id,
					$lesson_post_type
				)
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT upm.user_id, upm.post_id AS item_id, MAX(upm.updated_date) AS updated_date
					FROM {$table} upm
					INNER JOIN {$wpdb->posts} p ON p.ID = upm.post_id
					INNER JOIN {$wpdb->postmeta} cm
						ON cm.post_id = p.ID
						AND cm.meta_key = '_tutor_course_id_for_lesson'
						AND cm.meta_value = %s
					WHERE upm.meta_key = '_is_complete'
						AND upm.meta_value = 'yes'
						AND upm.user_id > 0
						AND p.post_type = %s
					GROUP BY upm.user_id, upm.post_id",
					(string) $course_id,
					$lesson_post_type
				)
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $wpdb->last_error ) {
			ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $wpdb->last_error );
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Fetch completed LifterLMS quiz attempt rows for a course.
	 *
	 * Quizzes receive `_tutor_course_id_for_lesson` during course migration
	 * (same meta key as lessons), so course scoping stays consistent.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Optional user ID to scope results.
	 *
	 * @return array<int, object>
	 */
	private function get_completed_quiz_attempts( int $course_id, int $user_id = 0 ): array {
		global $wpdb;

		$table          = $wpdb->prefix . 'lifterlms_quiz_attempts';
		$quiz_post_type = tutor()->quiz_post_type;
		$status_in      = implode( ',', array_fill( 0, count( self::COMPLETED_ATTEMPT_STATUSES ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		if ( $user_id > 0 ) {
			$params  = array_merge(
				array( (string) $course_id ),
				self::COMPLETED_ATTEMPT_STATUSES,
				array( $user_id, $quiz_post_type )
			);
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT qa.student_id AS user_id, qa.quiz_id AS item_id, qa.grade, qa.status,
						qa.start_date, qa.end_date, qa.questions, qa.id
					FROM {$table} qa
					INNER JOIN {$wpdb->posts} p ON p.ID = qa.quiz_id
					INNER JOIN {$wpdb->postmeta} cm
						ON cm.post_id = p.ID
						AND cm.meta_key = '_tutor_course_id_for_lesson'
						AND cm.meta_value = %s
					WHERE qa.status IN ({$status_in})
						AND qa.student_id = %d
						AND p.post_type = %s
					ORDER BY qa.id DESC",
					$params
				)
			);
		} else {
			$params  = array_merge(
				array( (string) $course_id ),
				self::COMPLETED_ATTEMPT_STATUSES,
				array( $quiz_post_type )
			);
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT qa.student_id AS user_id, qa.quiz_id AS item_id, qa.grade, qa.status,
						qa.start_date, qa.end_date, qa.questions, qa.id
					FROM {$table} qa
					INNER JOIN {$wpdb->posts} p ON p.ID = qa.quiz_id
					INNER JOIN {$wpdb->postmeta} cm
						ON cm.post_id = p.ID
						AND cm.meta_key = '_tutor_course_id_for_lesson'
						AND cm.meta_value = %s
					WHERE qa.status IN ({$status_in})
						AND qa.student_id > 0
						AND p.post_type = %s
					ORDER BY qa.id DESC",
					$params
				)
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( $wpdb->last_error ) {
			ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $wpdb->last_error );
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Resolve a Unix timestamp for Tutor lesson completion meta.
	 *
	 * Prefer Lifter `updated_date`, then current tutor time.
	 *
	 * @since 2.6.0
	 *
	 * @param object $row Lifter user postmeta row.
	 *
	 * @return int
	 */
	private function resolve_completion_timestamp( object $row ): int {
		return $this->resolve_timestamp_field( $row, array( 'updated_date' ) );
	}

	/**
	 * Resolve a Unix timestamp from ordered datetime fields on a row.
	 *
	 * @since 2.6.0
	 *
	 * @param object        $row    Source row.
	 * @param array<string> $fields Field names in preference order.
	 *
	 * @return int
	 */
	private function resolve_timestamp_field( object $row, array $fields ): int {
		foreach ( $fields as $field ) {
			$value = $row->{$field} ?? null;
			if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
				continue;
			}

			$timestamp = strtotime( (string) $value );
			if ( false !== $timestamp && $timestamp > 0 ) {
				return (int) $timestamp;
			}
		}

		return (int) tutor_time();
	}
}
