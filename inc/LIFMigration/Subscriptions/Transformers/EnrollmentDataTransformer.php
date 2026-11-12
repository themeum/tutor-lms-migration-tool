<?php
/**
 * Transform Lifter subscription enrollments for Tutor linking.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * EnrollmentDataTransformer class.
 *
 * @since 2.6.0
 */
class EnrollmentDataTransformer implements DataTransformer {

	/**
	 * Find Tutor enrollments for the subscription user + course.
	 *
	 * @since 2.6.0
	 *
	 * @param mixed $llms_order_id Lifter order ID.
	 *
	 * @return array List of enrollment post objects.
	 */
	public function transform( $llms_order_id ) {
		global $wpdb;

		$llms_order_id = (int) $llms_order_id;
		$post          = get_post( $llms_order_id );

		if ( ! $post ) {
			return array();
		}

		$course_id = Helper::get_order_course_id( $llms_order_id );
		$user_id   = (int) get_post_meta( $llms_order_id, '_llms_user_id', true );
		if ( $user_id < 1 ) {
			$user_id = (int) $post->post_author;
		}

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
