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

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates LearnPress lesson completion progress to Tutor LMS.
 *
 * Reads completed `lp_lesson` rows from `learnpress_user_items` and writes
 * Tutor user meta `_tutor_completed_lesson_id_{lesson_id}` with a timestamp.
 *
 * @since 2.5.0
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
	 * LearnPress completed status.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const STATUS_COMPLETED = 'completed';

	/**
	 * LearnPress course ref type on lesson user items.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const REF_TYPE_COURSE = 'lp_course';

	/**
	 * Migrate LearnPress lesson progress to Tutor LMS for a course.
	 *
	 * @since 2.5.0
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
	 * Fetch completed LearnPress lesson rows for a course.
	 *
	 * @since 2.5.0
	 *
	 * @param int $course_id Course ID (stored as ref_id on lesson items).
	 * @param int $user_id   Optional user ID to scope results.
	 *
	 * @return array<int, object>
	 */
	private function get_completed_lessons( int $course_id, int $user_id = 0 ): array {
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
					self::LP_LESSON,
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
					self::LP_LESSON,
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
		foreach ( array( 'end_time', 'start_time' ) as $field ) {
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
