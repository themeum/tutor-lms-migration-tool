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

		return $lessons_migrated + $attempts_migrated;
	}

	/**
	 * Migrate lesson completions for a course.
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

		// Primary source: Sensei comments-based lesson progress
		$format_ids  = implode( ',', array_map( 'intval', $lessons ) );
		$completions = $wpdb->get_results(
			"SELECT comment_ID, comment_post_ID AS lesson_id, user_id, comment_date AS completed_at
			FROM {$wpdb->comments}
			WHERE comment_type = 'sensei_lesson_status'
			AND comment_post_ID IN ({$format_ids})
			AND comment_approved IN ('complete', 'passed', '1', 'approved')
			AND user_id > 0" . $user_clause
		);

		if ( ! empty( $completions ) ) {
			foreach ( $completions as $row ) {
				$lid   = (int) $row->lesson_id;
				$uid   = (int) $row->user_id;
				$cdate = ! empty( $row->completed_at ) ? $row->completed_at : current_time( 'mysql' );

				if ( $this->mark_lesson_complete( $lid, $course_id, $uid, $cdate ) ) {
					$migrated_count++;
				}
			}
		}

		return $migrated_count;
	}

	/**
	 * Mark a lesson completed in Tutor LMS for a student.
	 *
	 * @param int    $lesson_id     Lesson ID.
	 * @param int    $course_id     Course ID.
	 * @param int    $user_id       Student User ID.
	 * @param string $completed_at  Completion timestamp.
	 * @return bool True if inserted, false if already complete or on failure.
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

		// Check if already completed
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

		// Mark lesson complete in Tutor LMS user meta
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

		return true;
	}

	/**
	 * Migrate quiz attempts from Sensei to Tutor LMS.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID (optional).
	 * @return int Number of quiz attempts migrated.
	 */
	public function migrate_quiz_attempts( int $course_id, int $user_id = 0 ): int {
		global $wpdb;

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

		// Query Sensei quiz status comments
		$attempts = $wpdb->get_results(
			"SELECT comment_ID, comment_post_ID AS quiz_id, user_id, comment_date AS attempt_date
			FROM {$wpdb->comments}
			WHERE comment_type IN ('sensei_quiz_status', 'sensei_lesson_status')
			AND comment_post_ID IN ({$format_ids})
			AND user_id > 0" . $user_clause
		);

		if ( ! empty( $attempts ) ) {
			foreach ( $attempts as $row ) {
				$qid          = (int) $row->quiz_id;
				$uid          = (int) $row->user_id;
				$attempt_date = ! empty( $row->attempt_date ) ? $row->attempt_date : current_time( 'mysql' );

				if ( $this->record_quiz_attempt( $qid, $course_id, $uid, $attempt_date, (int) $row->comment_ID ) ) {
					$migrated_count++;
				}
			}
		}

		return $migrated_count;
	}

	/**
	 * Record a single quiz attempt into tutor_quiz_attempts table.
	 *
	 * @param int    $quiz_id      Tutor quiz ID.
	 * @param int    $course_id    Course ID.
	 * @param int    $user_id      Student User ID.
	 * @param string $attempt_date Date string.
	 * @param int    $comment_id   Sensei source comment ID (optional).
	 * @return bool
	 */
	public function record_quiz_attempt( int $quiz_id, int $course_id, int $user_id, string $attempt_date = '', int $comment_id = 0 ): bool {
		global $wpdb;

		// Calculate total questions and marks for this quiz
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

		$grade = 0.00;
		if ( $comment_id > 0 ) {
			$raw_grade = get_comment_meta( $comment_id, 'grade', true );
			if ( '' === $raw_grade ) {
				$raw_grade = get_comment_meta( $comment_id, 'quiz_grade', true );
			}
			if ( is_numeric( $raw_grade ) ) {
				$grade = (float) $raw_grade;
			}
		}

		// Passmark threshold
		$passmark = 80;
		$opts     = get_post_meta( $quiz_id, 'tutor_quiz_option', true );
		if ( ! empty( $opts['passing_grade'] ) ) {
			$passmark = (int) $opts['passing_grade'];
		}

		$earned_marks = ( $grade > 0 && $total_marks > 0 ) ? ( $grade / 100 * $total_marks ) : (float) $grade;
		$result       = ( $grade >= $passmark ) ? 'pass' : 'fail';
		$date         = ! empty( $attempt_date ) ? $attempt_date : current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'tutor_quiz_attempts',
			array(
				'course_id'                 => $course_id,
				'quiz_id'                   => $quiz_id,
				'user_id'                   => $user_id,
				'total_questions'           => $total_questions,
				'total_answered_questions'  => $total_questions,
				'total_marks'               => $total_marks > 0 ? $total_marks : 100.00,
				'earned_marks'              => $earned_marks,
				'attempt_info'              => maybe_serialize( array() ),
				'attempt_status'            => 'attempt_ended',
				'attempt_ip'                => '',
				'attempt_started_at'        => $date,
				'attempt_ended_at'          => $date,
				'is_manually_reviewed'      => 0,
				'result'                    => $result,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (bool) $inserted;
	}
}
