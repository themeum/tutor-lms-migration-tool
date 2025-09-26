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

use Tutor\Models\OrderModel;
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
}
