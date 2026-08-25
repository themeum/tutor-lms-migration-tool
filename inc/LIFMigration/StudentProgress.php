<?php
/**
 * LifterLMS student progress migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration;

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates LifterLMS lesson completions to Tutor LMS.
 *
 * Lifter stores completions in `lifterlms_user_postmeta` as `_is_complete = yes`
 * on the lesson post ID. After course migration those IDs remain Tutor lessons.
 *
 * @since 2.5.0
 */
class StudentProgress implements StudentProgressInterface {

	/**
	 * Migrate LifterLMS lesson progress to Tutor LMS for a course.
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

		$this->migrate_lessons( $course_id, $user_id );
	}

	/**
	 * Migrate completed LifterLMS lessons to Tutor lesson-complete user meta.
	 *
	 * @since 2.5.0
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
	 * Fetch completed LifterLMS lesson rows for a course.
	 *
	 * Uses `_tutor_course_id_for_lesson` set during course migration so progress
	 * stays scoped to the Tutor course even after CPT conversion.
	 *
	 * @since 2.5.0
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
	 * Resolve a Unix timestamp for Tutor lesson completion meta.
	 *
	 * Prefer Lifter `updated_date`, then current tutor time.
	 *
	 * @since 2.5.0
	 *
	 * @param object $row Lifter user postmeta row.
	 *
	 * @return int
	 */
	private function resolve_completion_timestamp( object $row ): int {
		$value = $row->updated_date ?? null;
		if ( ! empty( $value ) && '0000-00-00 00:00:00' !== $value ) {
			$timestamp = strtotime( (string) $value );
			if ( false !== $timestamp && $timestamp > 0 ) {
				return (int) $timestamp;
			}
		}

		return (int) tutor_time();
	}
}
