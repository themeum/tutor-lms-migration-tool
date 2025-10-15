<?php
/**
 * Subscription order data transformer.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;
use Themeum\TutorLMSMigrationTool\MigrationMapper;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Helper;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Subscriptions;
use Tutor\Models\OrderModel;
use TutorPro\Subscription\Models\PlanModel;

/**
 * Class OrderDataTransformer
 *
 * @since 2.4.0
 */
class OrderDataTransformer implements DataTransformer {
	/**
	 * Transform subscriptions orders data from woo to native
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Subscription $subscription subscription object.
	 *
	 * @return array
	 */
	public function transform( $subscription ) {
		$plan_model = new PlanModel();
		$mapper     = new MigrationMapper( Subscriptions::MAP_KEY );

		$tax_type          = get_option( 'woocommerce_prices_include_tax' ) === 'yes' ? 'inclusive' : 'exclusive';
		$order_ids         = $subscription->get_related_orders( 'ids', array( 'parent', 'renewal' ) );
		$order_ids         = array_reverse( $order_ids ); // To get orders like subscription, renewal, renewal so on.
		$orders            = array_map( 'wc_get_order', $order_ids );
		$wc_plan_id        = Helper::get_wc_product_id_by_subscription( $subscription );
		$removed_items     = array();
		$completed_item_id = 0;

		/**
		 * Order
		 *
		 * @var WC_Order $order
		 */
		$order_data = array();
		$plans_map  = $mapper->get_map_by_key( 'plans' );
		foreach ( $orders as $order ) {
			$order_type = wcs_order_contains_renewal( $order ) ? OrderModel::TYPE_RENEWAL : OrderModel::TYPE_SUBSCRIPTION;
			$parent_id  = 0;

			$tutor_plan_id = $plans_map[ $wc_plan_id ];
			$plan          = $plan_model->get_plan( $tutor_plan_id );

			$items = array(
				array(
					'item_id'       => $plan->id,
					'regular_price' => $plan->regular_price,
					'sale_price'    => $plan->sale_price > 0 ? $plan->sale_price : null,
				),
			);

			// Keep only subscription items and recalculate.
			if ( OrderModel::TYPE_SUBSCRIPTION === $order_type ) {
				$wc_order_items = $order->get_items();

				foreach ( $wc_order_items as $key => $item ) {
					$product = $item->get_product();
					if ( $wc_plan_id === $product->get_id() ) {
						$completed_item_id = $item->get_id();
						continue;
					}

					$order->remove_item( $item->get_id() );
					$removed_items[] = $item;
				}

				$order->calculate_totals();
			}

			$tax_amount = $order->get_total_tax();
			$total      = $order->get_total();
			$refunded   = $order->get_total_refunded();
			$fees       = $order->get_total_fees();
			$discount   = $order->get_total_discount();
			$earnings   = $total - ( $refunded + $fees );

			$user_id        = $order->get_user_id();
			$created_at_gmt = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );
			$updated_at_gmt = $order->get_date_modified() ? $order->get_date_modified()->date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );

			$tax_rate = 0;
			foreach ( $order->get_items( 'tax' ) as $tax_item ) {
				$tax_rate = $tax_item->get_rate_percent();
			}

			$order_data[] = array(
				'wc_order_id'      => $order->get_id(),
				'order_type'       => $order_type,
				'parent_id'        => $parent_id,
				'transaction_id'   => $order->get_transaction_id(),
				'user_id'          => $user_id,
				'order_status'     => Helper::get_order_status( $order->get_status() ),
				'payment_status'   => Helper::get_payment_status( $order ),
				'subtotal_price'   => $order->get_subtotal(),
				'pre_tax_price'    => $order->get_subtotal(),

				'tax_type'         => $tax_type,
				'tax_rate'         => $tax_rate,
				'tax_amount'       => $tax_amount,

				'total_price'      => $total,
				'net_payment'      => $total - $refunded,

				'coupon_code'      => implode( ',', $order->get_coupon_codes() ),
				'coupon_amount'    => $discount,
				'discount_amount'  => $discount,
				'discount_type'    => '',
				'discount_reason'  => '',

				'fees'             => $fees,
				'refund_amount'    => $refunded,
				'earnings'         => $earnings,

				'payment_method'   => $order->get_payment_method(),
				'payment_payloads' => '',
				'note'             => $order->get_customer_note(),

				'created_by'       => $user_id,
				'updated_by'       => $user_id,
				'created_at_gmt'   => $created_at_gmt,
				'updated_at_gmt'   => $updated_at_gmt,

				'items'            => $items,
				'meta_data'        => $this->prepare_meta_data( $order, $plan ),
			);

			if ( count( $removed_items ) ) {
				// Remove the current processed subscription item.
				$order->remove_item( $completed_item_id );

				// Prevent creating multiple earnings when adding new order item.
				remove_all_actions( 'woocommerce_new_order_item' );

				// Keep the items that are subscription based after it was removed.
				foreach ( $removed_items as $removed_item ) {
					$product = $removed_item->get_product();
					if ( Helper::check_wc_subscription_product( $product ) ) {
						$order->add_item( $removed_item );
					}
				}

				$order->save();
			}
		}

		return $order_data;
	}

	/**
	 * Prepare meta item.
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Order $order wc order object.
	 * @param string   $meta_key meta key.
	 * @param mixed    $meta_value meta value.
	 *
	 * @return array
	 */
	private function prepare_meta_item( $order, $meta_key, $meta_value ) {
		$created_at_gmt = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );
		$updated_at_gmt = $order->get_date_modified() ? $order->get_date_modified()->date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );

		return array(
			'meta_key'       => $meta_key,
			'meta_value'     => $meta_value,
			'created_at_gmt' => $created_at_gmt,
			'updated_at_gmt' => $updated_at_gmt,
			'created_by'     => $order->get_customer_id(),
			'updated_by'     => $order->get_customer_id(),
		);
	}

	/**
	 * Prepare meta data for order
	 *
	 * @param WC_Order $order wc order object.
	 * @param object   $tutor_plan tutor subscription plan.
	 *
	 * @return array
	 */
	public function prepare_meta_data( $order, $tutor_plan ) {
		$billing_data = array(
			'id'                 => $order->get_user_id(),
			'user_id'            => $order->get_user_id(),
			'billing_first_name' => $order->get_billing_first_name(),
			'billing_last_name'  => $order->get_billing_last_name(),
			'billing_email'      => $order->get_billing_email(),
			'billing_phone'      => $order->get_billing_phone(),
			'billing_zip_code'   => $order->get_billing_postcode(),
			'billing_address'    => $order->get_billing_address_1(),
			'billing_country'    => $order->get_billing_country(),
			'billing_state'      => $order->get_billing_state(),
			'billing_city'       => $order->get_billing_city(),
		);

		$meta_data = array(
			$this->prepare_meta_item( $order, OrderModel::META_KEY_BILLING_ADDRESS, wp_json_encode( $billing_data, JSON_UNESCAPED_UNICODE ) ),
			$this->prepare_meta_item( $order, OrderModel::META_PLAN_INFO, maybe_serialize( $tutor_plan ) ),
		);

		$order_type = wcs_order_contains_renewal( $order ) ? OrderModel::TYPE_RENEWAL : OrderModel::TYPE_SUBSCRIPTION;
		if ( OrderModel::TYPE_SUBSCRIPTION === $order_type ) {
			if ( $tutor_plan->enrollment_fee > 0 ) {
				$meta_data[] = $this->prepare_meta_item( $order, OrderModel::META_ENROLLMENT_FEE, $tutor_plan->enrollment_fee );
			}
			if ( $tutor_plan->trial_value > 0 ) {
				$meta_data[] = $this->prepare_meta_item( $order, OrderModel::META_IS_PLAN_TRIAL_ORDER, true );
			}
		}

		return $meta_data;
	}
}
