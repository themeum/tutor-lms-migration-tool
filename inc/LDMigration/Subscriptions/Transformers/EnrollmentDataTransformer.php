<?php
/**
 * Transform LearnDash subscription enrollments for Tutor linking.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * EnrollmentDataTransformer class.
 *
 * @since 2.5.0
 */
class EnrollmentDataTransformer implements DataTransformer {

	/**
	 * Find Tutor enrollments for the subscription user + course.
	 *
	 * Uses a direct query because WP_Query strips unregistered statuses like
	 * `completed` / `cancel` when mixed with `publish` / `private`.
	 *
	 * @since 2.5.0
	 *
	 * @param mixed $transaction_id LD subscription transaction ID.
	 *
	 * @return array List of enrollment post objects.
	 */
	public function transform( $transaction_id ) {
		global $wpdb;

		$transaction_id = (int) $transaction_id;
		$post           = get_post( $transaction_id );

		if ( ! $post ) {
			return array();
		}

		$course_id = Helper::get_transaction_course_id( $transaction_id );
		$user_id   = (int) $post->post_author;

		if ( ! $course_id || ! $user_id ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$enrollments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_parent = %d
				AND post_author = %d
				AND post_status IN ( 'completed', 'cancel', 'publish', 'private' )",
				'tutor_enrolled',
				$course_id,
				$user_id
			)
		);

		return $enrollments ? $enrollments : array();
	}
}
