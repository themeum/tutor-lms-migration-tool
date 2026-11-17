<?php
/**
 * Sensei LMS Course Enrollments Migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates student course enrollments from Sensei LMS to Tutor LMS.
 */
class Enrollments {

	/**
	 * Default batch size.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Migrate enrollments in batches across all courses.
	 *
	 * @param int $offset Offset number.
	 * @param int $limit  Batch limit.
	 * @return array{migrated: int, remaining: int, has_more: bool}
	 */
	public function migrate_batch( int $offset = 0, int $limit = self::BATCH_SIZE ): array {
		global $wpdb;

		$enrollments = $this->get_unmigrated_enrollments( $offset, $limit );
		$migrated    = 0;

		foreach ( $enrollments as $record ) {
			$user_id     = (int) $record->user_id;
			$course_id   = (int) $record->course_id;
			$enrolled_at = ! empty( $record->enrolled_at ) ? $record->enrolled_at : current_time( 'mysql' );

			if ( $user_id <= 0 || $course_id <= 0 ) {
				continue;
			}

			// Validate user exists.
			if ( ! get_userdata( $user_id ) ) {
				continue;
			}

			$enrolled = $this->enroll_student( $course_id, $user_id, $enrolled_at );
			if ( $enrolled ) {
				++$migrated;
			}
		}

		$remaining = $this->get_total_unmigrated_enrollments_count( $offset + $limit );

		return array(
			'migrated'  => $migrated,
			'remaining' => $remaining,
			'has_more'  => $remaining > 0,
		);
	}

	/**
	 * Migrate all enrollments for a single course.
	 *
	 * @param int $course_id Course ID.
	 * @return int Number of enrolled students.
	 */
	public function migrate_course_enrollments( int $course_id ): int {
		if ( $course_id <= 0 ) {
			return 0;
		}

		$records  = $this->get_course_enrollment_records( $course_id );
		$migrated = 0;

		foreach ( $records as $record ) {
			$user_id     = (int) $record->user_id;
			$enrolled_at = ! empty( $record->enrolled_at ) ? $record->enrolled_at : current_time( 'mysql' );

			if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
				continue;
			}

			if ( $this->enroll_student( $course_id, $user_id, $enrolled_at ) ) {
				++$migrated;
			}
		}

		return $migrated;
	}

	/**
	 * Enroll student in Tutor course if not already enrolled.
	 *
	 * @param int    $course_id   Course ID.
	 * @param int    $user_id     Student User ID.
	 * @param string $enrolled_at MySQL timestamp.
	 * @return bool True if newly enrolled or already enrolled, false on error.
	 */
	public function enroll_student( int $course_id, int $user_id, string $enrolled_at = '' ): bool {
		global $wpdb;

		// Check database directly to prevent duplicate rows without polluting TutorCache.
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tutor_enrolled' AND post_author = %d AND post_parent = %d AND post_status = 'completed' LIMIT 1",
				$user_id,
				$course_id
			)
		);

		if ( $existing_id ) {
			return true;
		}

		$enrolled_date = ! empty( $enrolled_at ) ? $enrolled_at : current_time( 'mysql' );

		/* translators: %s: Course title */
		$enroll_title = sprintf( __( 'Course Enrolled - %s', 'tutor-lms-migration-tool' ), get_the_title( $course_id ) );

		$enroll_id = wp_insert_post(
			array(
				'post_type'   => 'tutor_enrolled',
				'post_title'  => $enroll_title,
				'post_status' => 'completed',
				'post_author' => $user_id,
				'post_parent' => $course_id,
				'post_date'   => $enrolled_date,
			)
		);

		if ( $enroll_id && ! is_wp_error( $enroll_id ) ) {
			clean_post_cache( $enroll_id );
			update_post_meta( $enroll_id, '_tutor_enrolled_by', 'sensei_migration' );
			update_user_meta( $user_id, '_is_tutor_student', $enrolled_date );

			// Invalidate runtime cache if set previously.
			if ( class_exists( '\Tutor\Cache\TutorCache' ) ) {
				\Tutor\Cache\TutorCache::set( "tutor_is_enrolled_{$course_id}_{$user_id}_1", false );
				\Tutor\Cache\TutorCache::set( "tutor_is_enrolled_{$course_id}_{$user_id}_", false );
			}

			return true;
		}

		return false;
	}

	/**
	 * Retrieve unmigrated enrollment records from Sensei dual sources (tables + comments).
	 *
	 * @param int $offset Query offset.
	 * @param int $limit  Query limit.
	 * @return array
	 */
	private function get_unmigrated_enrollments( int $offset = 0, int $limit = self::BATCH_SIZE ): array {
		global $wpdb;

		// 1. Comments source (primary Sensei storage)
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, comment_post_ID AS course_id, comment_date AS enrolled_at
				FROM {$wpdb->comments}
				WHERE comment_type = 'sensei_course_status'
				AND user_id > 0
				ORDER BY comment_ID ASC
				LIMIT %d, %d",
				$offset,
				$limit
			)
		);

		if ( ! empty( $results ) ) {
			return $results;
		}

		// 2. Custom table fallback if sensei_course_enrolment table exists.
		$enrolment_table = $wpdb->prefix . 'sensei_course_enrolment';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $enrolment_table ) ) === $enrolment_table ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$table_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, course_id, created_at AS enrolled_at
					FROM {$enrolment_table}
					WHERE user_id > 0
					ORDER BY id ASC
					LIMIT %d, %d",
					$offset,
					$limit
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			if ( ! empty( $table_results ) ) {
				return $table_results;
			}
		}

		return array();
	}

	/**
	 * Get total remaining unmigrated enrollments count.
	 *
	 * @param int $processed Count already processed in past batches.
	 * @return int
	 */
	private function get_total_unmigrated_enrollments_count( int $processed = 0 ): int {
		global $wpdb;

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_type = 'sensei_course_status' AND user_id > 0"
		);

		return max( 0, $total - $processed );
	}

	/**
	 * Get enrollment records for a specific course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private function get_course_enrollment_records( int $course_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, comment_post_ID AS course_id, comment_date AS enrolled_at
				FROM {$wpdb->comments}
				WHERE comment_type = 'sensei_course_status'
				AND comment_post_ID = %d
				AND user_id > 0",
				$course_id
			)
		);
	}
}
