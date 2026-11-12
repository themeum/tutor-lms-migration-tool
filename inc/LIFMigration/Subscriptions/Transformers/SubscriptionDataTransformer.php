<?php
/**
 * Transform Lifter recurring order into Tutor subscription row data.
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
 * SubscriptionDataTransformer class.
 *
 * @since 2.6.0
 */
class SubscriptionDataTransformer implements DataTransformer {

	/**
	 * Transform a Lifter recurring order into Tutor subscription fields.
	 *
	 * Plan and order IDs are remapped during migrate().
	 *
	 * @since 2.6.0
	 *
	 * @param mixed $llms_order_id Lifter order ID.
	 *
	 * @return array
	 */
	public function transform( $llms_order_id ) {
		$llms_order_id = (int) $llms_order_id;
		$post          = get_post( $llms_order_id );

		if ( ! $post || ! Helper::is_recurring_course_order( $llms_order_id ) ) {
			return array();
		}

		$user_id   = (int) get_post_meta( $llms_order_id, '_llms_user_id', true );
		$course_id = Helper::get_order_course_id( $llms_order_id );
		$plan_id   = (int) get_post_meta( $llms_order_id, '_llms_plan_id', true );

		if ( $user_id < 1 ) {
			$user_id = (int) $post->post_author;
		}

		$start_gmt = Helper::to_gmt_datetime( $post->post_date_gmt ? $post->post_date_gmt : $post->post_date );
		if ( empty( $start_gmt ) ) {
			$start_gmt = gmdate( 'Y-m-d H:i:s' );
		}

		$next_payment = Helper::to_gmt_datetime( (string) get_post_meta( $llms_order_id, '_llms_date_next_payment', true ) );
		$access_exp   = Helper::to_gmt_datetime( (string) get_post_meta( $llms_order_id, '_llms_date_access_expires', true ) );
		$trial_end    = Helper::to_gmt_datetime( (string) get_post_meta( $llms_order_id, '_llms_date_trial_end', true ) );
		$has_trial    = '' !== $trial_end || (bool) get_post_meta( $llms_order_id, '_llms_trial_offer', true );

		if ( empty( $next_payment ) ) {
			$next_payment = $access_exp ? $access_exp : $start_gmt;
		}

		$end_gmt = $access_exp ? $access_exp : $next_payment;

		return array(
			'user_id'               => $user_id,
			'plan_id'               => $plan_id, // Remapped via access-plan map.
			'first_order_id'        => $llms_order_id,
			'active_order_id'       => $llms_order_id,
			'status'                => Helper::map_subscription_status( (string) $post->post_status ),
			'auto_renew'            => in_array( (string) $post->post_status, array( 'llms-active', 'llms-on-hold', 'llms-pending' ), true ) ? 1 : 0,
			'is_trial_enabled'      => $has_trial && ! empty( $trial_end ) && strtotime( $trial_end ) > time() ? 1 : 0,
			'is_trial_used'         => $has_trial ? 1 : 0,
			'trial_end_date_gmt'    => $has_trial ? ( $trial_end ? $trial_end : null ) : null,
			'start_date_gmt'        => $start_gmt,
			'end_date_gmt'          => $end_gmt,
			'next_payment_date_gmt' => $next_payment,
			'created_at_gmt'        => $start_gmt,
			'updated_at_gmt'        => Helper::to_gmt_datetime( $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified ),
			'note'                  => __( 'Subscription migrated from LifterLMS', 'tutor-lms-migration-tool' ),
			'llms_order_id'         => $llms_order_id,
			'llms_course_id'        => $course_id,
			'llms_plan_id'          => $plan_id,
		);
	}
}
