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
use Exception;
use Themeum\TutorLMSMigrationTool\SalesData\MigrationHandler;
use Tutor\Models\OrderModel;
use TutorPro\Subscription\Models\SubscriptionModel;
use Tutor\Helpers\QueryHelper;

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
	 * @param object $status the wc order status.
	 *
	 * @return string Tutor native order status.
	 */
	public static function get_order_status( $status ) {
		$map = array(
			'pending'       => OrderModel::ORDER_INCOMPLETE,
			'wc-pending'    => OrderModel::ORDER_INCOMPLETE,
			'on-hold'       => OrderModel::ORDER_INCOMPLETE,
			'wc-on-hold'    => OrderModel::ORDER_INCOMPLETE,
			'processing'    => OrderModel::ORDER_INCOMPLETE,
			'wc-processing' => OrderModel::ORDER_INCOMPLETE,
			'wc-completed'  => OrderModel::ORDER_COMPLETED,
			'completed'     => OrderModel::ORDER_COMPLETED,
			'complete'      => OrderModel::ORDER_COMPLETED,
			'cancelled'     => OrderModel::ORDER_CANCELLED,
			'wc-cancelled'  => OrderModel::ORDER_CANCELLED,
			'failed'        => OrderModel::ORDER_CANCELLED,
			'wc-failed'     => OrderModel::ORDER_CANCELLED,
			'refunded'      => OrderModel::ORDER_CANCELLED,
			'wc-refunded'   => OrderModel::ORDER_CANCELLED,
			'trash'         => OrderModel::ORDER_TRASH,
			'wc-trash'      => OrderModel::ORDER_TRASH,
		);

		return $map[ $status ] ?? OrderModel::ORDER_INCOMPLETE;
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
	 *
	 * @throws Exception If the coupon type is not supported by tutor.
	 *
	 * @return string|null Tutor discount type for WooCommerce Coupon.
	 */
	public static function get_coupon_discount_type( $wc_coupon ): ?string {

		$wc_discount_type = $wc_coupon->get_discount_type();

		$map = array(
			'percent'       => CouponModel::DISCOUNT_TYPE_PERCENTAGE,
			'fixed_cart'    => CouponModel::DISCOUNT_TYPE_FLAT,
			'fixed_product' => CouponModel::DISCOUNT_TYPE_FLAT,
		);

		return $map[ $wc_discount_type ] ?? null;
	}

	/**
	 * Filter Orders and Order Count from subscriptions.
	 *
	 * @since 2.4.0
	 *
	 * @param array   $orders the array of orders.
	 * @param boolean $return_count whether to return count.
	 *
	 * @return array|int
	 */
	public static function filter_order_subscription_count( $orders = array(), $return_count = false ) {
		if ( count( $orders ) ) {
			foreach ( $orders as $key => $order ) {
				$order = is_int( $order ) ? wc_get_order( $order ) : $order;
				$items = $order->get_items();

				$subscriptions = 0;
				if ( count( $items ) ) {
					foreach ( $items as $item ) {
						if ( self::check_wc_subscription_product(
							$item->get_product()
						) ) {
							++$subscriptions;
						} else {
							continue;
						}
					}

					if ( $subscriptions === count( $items ) ) {
						unset( $orders[ $key ] );
					}
				}
			}
		}

		return $return_count ? count( $orders ) : $orders;
	}


	/**
	 * Filter tutor order to removed subscription items.
	 *
	 * @since 2.4.0
	 *
	 * @param array     $tutor_order_data the tutor order data array.
	 * @param \WC_Order $order the WC_Order object.
	 *
	 * @return array
	 */
	public static function filter_subscription_order_item( $tutor_order_data, $order ) {
		$items         = $order->get_items();
		$subscriptions = MigrationHandler::is_active_wc_subscription() ? wcs_get_subscriptions_for_order( $order ) : array();

		// If the total items and subscription items are equal then don't create order.
		if ( count( $items ) === count( $subscriptions ) ) {
			return array();
		}

		$removed_items = array();

		/**
		 * WC order item.
		 *
		 * @var WC_Order_Item $item order item.
		 */
		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( ! self::check_wc_subscription_product( $product ) ) {
				continue;
			}

			// Remove subscription item from order and recalculate total.
			$order->remove_item( $item->get_id() );
			$removed_items[] = $item;
		}

		// If no item removed, that means no subscription items.
		if ( ! count( $removed_items ) ) {
			return $tutor_order_data;
		}

		if ( count( $removed_items ) === count( $items ) ) {
			return array();
		}

		$order->calculate_totals();

		// Update prices for tutor orders after recalculation.
		$tutor_order_data['total_price']     = $order->get_total();
		$tutor_order_data['subtotal_price']  = $order->get_subtotal();
		$tutor_order_data['pre_tax_price']   = $order->get_subtotal();
		$tutor_order_data['tax_amount']      = $order->get_total_tax();
		$tutor_order_data['net_payment']     = $order->get_total() - $order->get_total_refunded();
		$tutor_order_data['coupon_amount']   = $order->get_discount_total();
		$tutor_order_data['discount_amount'] = $order->get_discount_total();
		$tutor_order_data['fees']            = $order->get_total_fees();
		$tutor_order_data['earnings']        = ( $order->get_total() - $order->get_total_refunded() ) - $order->get_total_fees();
		$tutor_order_data['refund_amount']   = $order->get_total_refunded();

		// Prevent creating multiple earnings when adding new order item.
		remove_all_actions( 'woocommerce_new_order_item' );

		// Add back the subscription item to handle it by subscription class.
		foreach ( $removed_items as $item ) {
			$order->add_item( $item );
			$order->save();
		}

		return $tutor_order_data;
	}

	/**
	 * Check if wc subscription product type.
	 *
	 * @since 2.4.0
	 *
	 * @param \WC_Product $product the wc product.
	 *
	 * @return bool
	 */
	public static function check_wc_subscription_product( $product ) {
		return in_array( $product->get_type(), self::get_wc_subscription_types() );
	}


	/**
	 * Get wc subscription types.
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public static function get_wc_subscription_types() {
		return array( 'subscription', 'variable-subscription', 'subscription_variation' );
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
			'type'    => self::get_wc_subscription_types(),
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
		return array_map( fn( $plan ) => $plan->get_id(), self::get_wc_plans() );
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

	/**
	 * Generate progress message
	 *
	 * @since 2.4.0
	 *
	 * @param array $options_value Options value.
	 *
	 * @return string
	 */
	private static function generate_progress_message( array $options_value ): string {
		$message_parts = array();

		foreach ( $options_value['requirements'] as $key => $requirement ) {
			if ( isset( $requirement['succeed'] ) && is_array( $requirement['succeed'] ) ) {
				$succeed_count = count( $requirement['succeed'] );

				$capitalized_key = ucfirst( strtolower( $key ) );

				if ( $succeed_count > 0 ) {
					$message_parts[] = "{$capitalized_key} ({$succeed_count})";
				}
			}
		}

		if ( empty( $message_parts ) ) {
			return __( 'No data migrated', 'tutor-lms-migration-tool' );
		}

		return implode( ', ', $message_parts );
	}

	/**
	 * Get WC migration history
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public static function get_wc_migration_history(): array {
		global $wpdb;
		$data = array();

		$fetch = QueryHelper::get_all(
			$wpdb->options,
			array(
				'option_name LIKE %s AND option_value NOT LIKE %s' => array(
					'RAW',
					array(
						'tutor_migration_%',
						'%"job_progress";i:0%',
					),
				),
			),
			'option_id',
			10
		);

		if ( ! $fetch ) {
			return $data;
		}

		foreach ( $fetch as $item ) {
			if ( ! isset( $item->option_name ) || ! isset( $item->option_value ) ) {
				continue;
			}

			$options_value = json_decode( $item->option_value, true );

			if ( ! is_array( $options_value ) ) {
				continue;
			}

			$title = self::generate_progress_message( $options_value );

			$converted_item = array(
				'status'     => $options_value['status'] ?? '',
				'id'         => (int) ( $item->option_id ?? 0 ),
				'started_at' => ! empty( $options_value['started_at'] ) ? tutor_i18n_get_formated_date( $options_value['started_at'] ) : '',
				'title'      => $title,
			);

			$data[] = $converted_item;
		}

		return $data;
	}
}
