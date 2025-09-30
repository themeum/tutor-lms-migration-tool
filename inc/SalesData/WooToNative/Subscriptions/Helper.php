<?php
/**
 * Helper class for WC subscription migration to native.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions;

use TutorPro\Subscription\Models\SubscriptionModel;

/**
 * Class Helper
 *
 * @since 2.4.0
 */
class Helper {
	/**
	 * Get all WC plans that are linked to Tutor
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public static function get_wc_plans() {
		$products = tutor_utils()->get_linked_product_ids();
		$args     = array(
			'limit'   => -1,
			'type'    => array( 'subscription', 'variable-subscription', 'subscription_variation' ),
			'include' => $products,
		);

		return wc_get_products( $args );
	}

	/**
	 * Get WC plan ids
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public static function get_wc_plan_ids() {
		return array_map( fn( $plan) => $plan->get_id(), self::get_wc_plans() );
	}

	/**
	 * Get WC product id by subscription
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Subscription $subscription subscription object.
	 *
	 * @return int
	 */
	public static function get_wc_product_id_by_subscription( $subscription ) {
		$product_ids = array_map( fn( $item ) => $item->get_product_id(), $subscription->get_items() );
		$product_id  = ! empty( $product_ids ) ? reset( $product_ids ) : null;

		return $product_id;
	}

	/**
	 * Get tutor subscription status by wc subscription status.
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Subscription $wc_subscription wc subscription object.
	 *
	 * @return string
	 */
	public static function get_subscription_status( $wc_subscription ) {
		$status = $wc_subscription->get_status();
		$map    = array(
			'pending'   => SubscriptionModel::STATUS_PENDING,
			'on-hold'   => SubscriptionModel::STATUS_HOLD,
			'active'    => SubscriptionModel::STATUS_ACTIVE,
			'cancelled' => SubscriptionModel::STATUS_CANCELLED,
			'expired'   => SubscriptionModel::STATUS_EXPIRED,
		);

		return $map[ $status ] ?? SubscriptionModel::STATUS_PENDING;
	}
}
