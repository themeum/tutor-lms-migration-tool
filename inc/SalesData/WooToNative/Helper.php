<?php
/**
 * Helper class to contain helper methods
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative;

use Tutor\Models\CouponModel;
use Tutor\Models\OrderModel;
use TutorPro\Subscription\Models\SubscriptionModel;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Helper class to contain helper methods
 *
 * @since 2.4.0
 */
class Helper {

	/**
	 * Get transformed order status from WooCommerce order.
	 *
	 * This method will transform wc order status to native order status
	 *
	 * @since 2.4.0
	 *
	 * @param object $wc_order WC_Order.
	 *
	 * @return string Tutor native order status.
	 */
	public static function get_order_status( $wc_order ) {
		$order_status = $wc_order->get_status();
		$map          = array(
			'pending'    => OrderModel::ORDER_INCOMPLETE,
			'on-hold'    => OrderModel::ORDER_INCOMPLETE,
			'processing' => OrderModel::ORDER_INCOMPLETE,
			'completed'  => OrderModel::ORDER_COMPLETED,
			'cancelled'  => OrderModel::ORDER_CANCELLED,
			'failed'     => OrderModel::ORDER_CANCELLED,
			'refunded'   => OrderModel::ORDER_CANCELLED,
			'trash'      => OrderModel::ORDER_TRASH,
		);

		return $map[ $order_status ] ?? OrderModel::ORDER_INCOMPLETE;
	}

	/**
	 * Get transformed payment status from WooCommerce order.
	 *
	 * This method will transform wc payment status to native payment status
	 *
	 * @since 2.4.0
	 *
	 * @param Object $order WooCommerce order object.
	 * @return string Tutor native payment status.
	 */
	public static function get_payment_status( $order ) {
		if ( $order->is_paid() ) {
			// Check for refund edge cases.
			if ( $order->get_total_refunded() > 0 ) {
				return ( $order->get_total_refunded() >= $order->get_total() )
					? OrderModel::PAYMENT_REFUNDED
					: OrderModel::PAYMENT_PARTIALLY_REFUNDED;
			}

			return OrderModel::PAYMENT_PAID;
		}

		$wc_status = $order->get_status();

		$map = array(
			'pending'   => OrderModel::PAYMENT_UNPAID,
			'on-hold'   => OrderModel::PAYMENT_UNPAID,
			'failed'    => OrderModel::PAYMENT_FAILED,
			'cancelled' => OrderModel::PAYMENT_FAILED,
			'refunded'  => OrderModel::PAYMENT_REFUNDED,
		);

		return $map[ $wc_status ] ?? OrderModel::PAYMENT_UNPAID;
	}

	/**
	 * Get transformed coupon status from WooCommerce coupon.
	 *
	 * This method will transform wc coupon status to native coupon status
	 *
	 * @since 2.4.0
	 *
	 * @param object $wc_coupon WC_Coupon.
	 *
	 * @return string Tutor native coupon status.
	 */
	public static function get_coupon_status( $wc_coupon ): string {

		$wc_coupon_status      = $wc_coupon->get_status();
		$wc_coupon_expire_date = $wc_coupon->get_date_expires();

		if ( ! empty( $wc_coupon_expire_date ) && $wc_coupon_expire_date->getTimestamp() < time() ) {
			return CouponModel::STATUS_EXPIRED;
		}

		$map = array(
			'publish' => CouponModel::STATUS_ACTIVE,
			'future'  => CouponModel::STATUS_ACTIVE,
			'trash'   => CouponModel::STATUS_TRASH,
			'private' => CouponModel::STATUS_INACTIVE,
			'pending' => CouponModel::STATUS_INACTIVE,
			'draft'   => CouponModel::STATUS_INACTIVE,
		);

		return $map[ $wc_coupon_status ] ?? CouponModel::STATUS_INACTIVE;
	}

	/**
	 * Get the tutor discount type for a given WooCommerce coupon.
	 *
	 * @since 2.4.0
	 *
	 * @param \WC_Coupon $wc_coupon WooCommerce coupon object.
	 * @return string Tutor discount type for WooCommerce Coupon.
	 */
	public static function get_coupon_discount_type( $wc_coupon ): string {

		$wc_discount_type = $wc_coupon->get_discount_type();

		$map = array(
			'percent'       => CouponModel::DISCOUNT_TYPE_PERCENTAGE,
			'fixed_cart'    => CouponModel::DISCOUNT_TYPE_FLAT,
			'fixed_product' => CouponModel::DISCOUNT_TYPE_FLAT,
		);

		return $map[ $wc_discount_type ] ?? CouponModel::DISCOUNT_TYPE_PERCENTAGE;
	}

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
