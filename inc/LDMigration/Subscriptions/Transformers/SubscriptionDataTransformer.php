<?php
/**
 * Transform LearnDash subscription transaction to Tutor subscription data.
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
 * SubscriptionDataTransformer class.
 *
 * @since 2.5.0
 */
class SubscriptionDataTransformer implements DataTransformer {

	/**
	 * Transform an LD subscription transaction into Tutor subscription fields.
	 *
	 * Plan and order IDs are remapped during migrate().
	 *
	 * @since 2.5.0
	 *
	 * @param mixed $transaction_id LD subscription transaction ID.
	 *
	 * @return array
	 */
	public function transform( $transaction_id ) {
		$transaction_id = (int) $transaction_id;
		$post           = get_post( $transaction_id );

		if ( ! $post || ! Helper::is_subscription_transaction( $transaction_id ) ) {
			return array();
		}

		$course_id          = Helper::get_transaction_course_id( $transaction_id );
		$parent_id          = (int) $post->post_parent;
		$product_status     = (string) get_post_meta( $transaction_id, 'product_status', true );
		$next_payment       = get_post_meta( $transaction_id, 'next_payment_date', true );
		$has_trial          = (bool) get_post_meta( $transaction_id, 'has_trial', true )
			|| (bool) get_post_meta( $transaction_id, 'has_free_trial', true )
			|| 'trial' === strtolower( $product_status );
		$start_date_gmt     = Helper::to_gmt_datetime( $post->post_date_gmt ? $post->post_date_gmt : $post->post_date );
		$next_payment_gmt   = Helper::to_gmt_datetime( $next_payment );
		$expired_date       = Helper::to_gmt_datetime( get_post_meta( $transaction_id, 'expired_date', true ) );

		if ( empty( $next_payment_gmt ) ) {
			$next_payment_gmt = $expired_date ? $expired_date : $start_date_gmt;
		}

		$charges      = $this->get_charge_ids( $transaction_id );
		$active_order = ! empty( $charges ) ? (int) end( $charges ) : ( $parent_id ? $parent_id : $transaction_id );
		$first_order  = $parent_id ? $parent_id : $transaction_id;

		$is_trial_used = 0;
		if ( $has_trial && 'trial' !== strtolower( $product_status ) ) {
			$is_trial_used = 1;
		}

		return array(
			'user_id'               => (int) $post->post_author,
			'plan_id'               => $course_id, // Remapped to Tutor plan via course→plan map.
			'first_order_id'        => $first_order,
			'active_order_id'       => $active_order,
			'status'                => Helper::map_subscription_status( $product_status ? $product_status : 'active' ),
			'auto_renew'            => 1,
			'is_trial_enabled'      => $has_trial && ! $is_trial_used ? 1 : 0,
			'is_trial_used'         => $is_trial_used,
			'trial_end_date_gmt'    => $has_trial ? $next_payment_gmt : null,
			'start_date_gmt'        => $start_date_gmt,
			'end_date_gmt'          => $expired_date ? $expired_date : $next_payment_gmt,
			'next_payment_date_gmt' => $next_payment_gmt,
			'created_at_gmt'        => $start_date_gmt,
			'updated_at_gmt'        => Helper::to_gmt_datetime( $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified ),
			'note'                  => __( 'Subscription migrated from LearnDash', 'tutor-lms-migration-tool' ),
			'ld_transaction_id'     => $transaction_id,
			'ld_course_id'          => $course_id,
		);
	}

	/**
	 * Get renewal charge transaction IDs for a subscription line item.
	 *
	 * @since 2.5.0
	 *
	 * @param int $subscription_id Subscription transaction ID.
	 *
	 * @return int[]
	 */
	private function get_charge_ids( int $subscription_id ): array {
		$children = get_children(
			array(
				'post_parent' => $subscription_id,
				'post_type'   => 'sfwd-transactions',
				'post_status' => 'any',
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
				'fields'      => 'ids',
			)
		);

		return array_map( 'intval', $children ? $children : array() );
	}
}
