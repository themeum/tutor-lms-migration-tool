<?php
/**
 * LearnPress student progress migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LPMigration;

use Tutor\Models\QuizModel;
use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates LearnPress lesson and quiz progress to Tutor LMS.
 *
 * Lesson completions become `_tutor_completed_lesson_id_{lesson_id}` user meta.
 * Completed quizzes become `tutor_quiz_attempts` rows so they count toward
 * Tutor course progress (which includes lessons + quizzes).
 *
 * @since 2.5.0
 * @since 2.5.0 Migrate completed LearnPress quizzes into tutor_quiz_attempts.
 */
class StudentProgress implements StudentProgressInterface {

	/**
	 * LearnPress lesson item type in user items.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const LP_LESSON = 'lp_lesson';

	/**
	 * LearnPress quiz item type in user items.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const LP_QUIZ = 'lp_quiz';

	/**
	 * LearnPress completed status.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const STATUS_COMPLETED = 'completed';

	/**
	 * LearnPress course ref type on curriculum user items.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const REF_TYPE_COURSE = 'lp_course';

	/**
	 * Migrate LearnPress lesson and quiz progress to Tutor LMS for a course.
	 *
	 * @since 2.5.0
	 * @since 2.5.0 Also migrates completed quizzes for course progress totals.
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
	 * Migrate completed LearnPress lessons to Tutor lesson-complete user meta.
	 *
	 * @since 2.5.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Optional user ID to scope migration.
	 *
	 * @return void
	 */
	private function migrate_lessons( int $course_id, int $user_id = 0 ) {
		$progress_rows = $this->get_completed_items( $course_id, self::LP_LESSON, $user_id );
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
	 * Migrate completed LearnPress quizzes to Tutor quiz attempts.
	 *
	 * Tutor course progress counts distinct quizzes with a non-started attempt.
	 * Full per-question answer history is not reconstructed here.
	 *
	 * @since 2.5.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   Optional user ID to scope migration.
	 *
	 * @return void
	 */
	private function migrate_quizzes( int $course_id, int $user_id = 0 ) {
		$progress_rows = $this->get_completed_quizzes( $course_id, $user_id );
		if ( empty( $progress_rows ) ) {
			return;
		}

		$quiz_post_type = tutor()->quiz_post_type;

		foreach ( $progress_rows as $row ) {
			$progress_user_id = (int) ( $row->user_id ?? 0 );
			$quiz_id          = (int) ( $row->item_id ?? 0 );

			if ( $progress_user_id <= 0 || $quiz_id <= 0 ) {
				continue;
			}

			$quiz = get_post( $quiz_id );
			if ( ! $quiz || $quiz_post_type !== $quiz->post_type ) {
				continue;
			}

			// Already migrated (or student retook in Tutor) — skip to avoid duplicates.
			if ( tutor_utils()->has_attempted_quiz( $progress_user_id, $quiz_id ) ) {
				continue;
			}

			$this->insert_quiz_attempt( $course_id, $quiz_id, $progress_user_id, $row );
		}
	}

	/**
	 * Insert a Tutor quiz attempt from a LearnPress completed quiz row.
	 *
	 * @since 2.5.0
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $quiz_id   Quiz ID.
	 * @param int    $user_id   Student user ID.
	 * @param object $row       LearnPress user item (+ optional result JSON).
	 *
	 * @return void
	 */
	private function insert_quiz_attempt( int $course_id, int $quiz_id, int $user_id, object $row ) {
		global $wpdb;

		$lp_result  = $this->parse_lp_quiz_result( $row->result ?? null );
		$graduation = (string) ( $row->graduation ?? '' );

		$result = ( 'passed' === $graduation || ! empty( $lp_result['pass'] ) )
			? QuizModel::RESULT_PASS
			: QuizModel::RESULT_FAIL;

		$total_marks  = isset( $lp_result['mark'] ) ? (float) $lp_result['mark'] : 0;
		$earned_marks = isset( $lp_result['user_mark'] ) ? (float) $lp_result['user_mark'] : 0;

		// Prefer absolute marks; fall back to percentage of total when only result % exists.
		if ( $earned_marks <= 0 && $total_marks > 0 && isset( $lp_result['result'] ) ) {
			$earned_marks = ( $total_marks * (float) $lp_result['result'] ) / 100;
		}

		$total_questions = isset( $lp_result['question_count'] ) ? (int) $lp_result['question_count'] : 0;
		$answered        = isset( $lp_result['question_answered'] ) ? (int) $lp_result['question_answered'] : $total_questions;

		$started_ts = $this->resolve_timestamp_field( $row, array( 'start_time', 'end_time' ) );
		$ended_ts   = $this->resolve_timestamp_field( $row, array( 'end_time', 'start_time' ) );

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
				'total_marks'              => $total_marks,
				'earned_marks'             => $earned_marks,
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
	 * Fetch completed LearnPress quiz rows (with optional result payload).
	 *
	 * @since 2.5.0
	 *
	 * @param int $course_id Course ID (stored as ref_id on quiz items).
	 * @param int $user_id   Optional user ID to scope results.
	 *
	 * @return array<int, object>
	 */
	private function get_completed_quizzes( int $course_id, int $user_id = 0 ): array {
		global $wpdb;

		$items_table   = $wpdb->prefix . 'learnpress_user_items';
		$results_table = $wpdb->prefix . 'learnpress_user_item_results';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $user_id > 0 ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ui.user_id, ui.item_id, ui.end_time, ui.start_time, ui.graduation, uir.result
					FROM {$items_table} ui
					LEFT JOIN {$results_table} uir ON uir.user_item_id = ui.user_item_id
					WHERE ui.ref_id = %d
						AND ui.ref_type = %s
						AND ui.item_type = %s
						AND ui.status = %s
						AND ui.user_id = %d
					ORDER BY ui.user_item_id DESC",
					$course_id,
					self::REF_TYPE_COURSE,
					self::LP_QUIZ,
					self::STATUS_COMPLETED,
					$user_id
				)
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ui.user_id, ui.item_id, ui.end_time, ui.start_time, ui.graduation, uir.result
					FROM {$items_table} ui
					LEFT JOIN {$results_table} uir ON uir.user_item_id = ui.user_item_id
					WHERE ui.ref_id = %d
						AND ui.ref_type = %s
						AND ui.item_type = %s
						AND ui.status = %s
					ORDER BY ui.user_item_id DESC",
					$course_id,
					self::REF_TYPE_COURSE,
					self::LP_QUIZ,
					self::STATUS_COMPLETED
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
	 * Fetch completed LearnPress curriculum item rows for a course.
	 *
	 * @since 2.5.0
	 * @since 2.5.0 Generalized for lesson/quiz item types.
	 *
	 * @param int    $course_id Course ID (stored as ref_id on items).
	 * @param string $item_type LearnPress item type (`lp_lesson`, etc.).
	 * @param int    $user_id   Optional user ID to scope results.
	 *
	 * @return array<int, object>
	 */
	private function get_completed_items( int $course_id, string $item_type, int $user_id = 0 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_user_items';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $user_id > 0 ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, item_id, end_time, start_time
					FROM {$table}
					WHERE ref_id = %d
						AND ref_type = %s
						AND item_type = %s
						AND status = %s
						AND user_id = %d",
					$course_id,
					self::REF_TYPE_COURSE,
					$item_type,
					self::STATUS_COMPLETED,
					$user_id
				)
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, item_id, end_time, start_time
					FROM {$table}
					WHERE ref_id = %d
						AND ref_type = %s
						AND item_type = %s
						AND status = %s",
					$course_id,
					self::REF_TYPE_COURSE,
					$item_type,
					self::STATUS_COMPLETED
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
	 * Decode LearnPress quiz result JSON into an array.
	 *
	 * @since 2.5.0
	 *
	 * @param mixed $result Raw result column value.
	 *
	 * @return array<string, mixed>
	 */
	private function parse_lp_quiz_result( $result ): array {
		if ( empty( $result ) ) {
			return array();
		}

		if ( is_array( $result ) ) {
			return $result;
		}

		$decoded = json_decode( (string) $result, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Resolve a Unix timestamp for Tutor lesson completion meta.
	 *
	 * Prefer LearnPress `end_time`, fall back to `start_time`, then current tutor time.
	 *
	 * @since 2.5.0
	 *
	 * @param object $row LearnPress user item row.
	 *
	 * @return int
	 */
	private function resolve_completion_timestamp( object $row ): int {
		return $this->resolve_timestamp_field( $row, array( 'end_time', 'start_time' ) );
	}

	/**
	 * Resolve a Unix timestamp from ordered datetime fields on a row.
	 *
	 * @since 2.5.0
	 *
	 * @param object        $row    LearnPress user item row.
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
