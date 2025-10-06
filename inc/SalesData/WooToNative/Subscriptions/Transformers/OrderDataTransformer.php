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
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Helper;
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

		$tax_type   = get_option( 'woocommerce_prices_include_tax' ) === 'yes' ? 'inclusive' : 'exclusive';
		$order_ids  = $subscription->get_related_orders( 'ids', array( 'parent', 'renewal' ) );
		$order_ids  = array_reverse( $order_ids ); // To get orders like subscription, renewal, renewal so on.
		$orders     = array_map( 'wc_get_order', $order_ids );
		$wc_plan_id = Helper::get_wc_product_id_by_subscription( $subscription );

		/**
		 * Order
		 *
		 * @var WC_Order $order
		 */
		$order_data = array();
		$plans_map  = $mapper->get_map_by_key( 'plans' );
		foreach ( $orders as $order ) {
			$order_type     = wcs_order_contains_renewal( $order ) ? OrderModel::TYPE_RENEWAL : OrderModel::TYPE_SUBSCRIPTION;
			$parent_id      = 0;
			$payment_status = 'completed' === $order->get_status() ? OrderModel::PAYMENT_PAID : OrderModel::PAYMENT_UNPAID;
			$tax_amount     = $order->get_total_tax();

			$tutor_plan_id = $plans_map[ $wc_plan_id ];
			$plan          = $plan_model->get_plan( $tutor_plan_id );

			$items = array(
				array(
					'item_id'       => $plan->id,
					'regular_price' => $plan->regular_price,
					'sale_price'    => $plan->sale_price > 0 ? $plan->sale_price : null,
				),
			);

			$total    = $order->get_total();
			$refunded = $order->get_total_refunded();
			$fees     = $order->get_total_fees();
			$discount = $order->get_total_discount();
			$earnings = $total - ( $refunded + $fees );

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
				'order_status'     => $order->get_status(),
				'payment_status'   => $payment_status,
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
				'meta_data'        => $this->prepare_meta_data( $order ),
			);
		}

		return $order_data;
	}

	/**
	 * Prepare meta data for order
	 *
	 * @param WC_Order $order wc order object..
	 *
	 * @return array
	 */
	public function prepare_meta_data( $order ) {
		$billing_data = array(
			'id'                 => $order->get_customer_id(),
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

		$created_at_gmt = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );
		$updated_at_gmt = $order->get_date_modified() ? $order->get_date_modified()->date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );

		$meta_data = array(
			array(
				'meta_key'       => OrderModel::META_KEY_BILLING_ADDRESS,
				'meta_value'     => wp_json_encode( $billing_data, JSON_UNESCAPED_UNICODE ),
				'created_at_gmt' => $created_at_gmt,
				'updated_at_gmt' => $updated_at_gmt,
				'created_by'     => $order->get_customer_id(),
				'updated_by'     => $order->get_customer_id(),
			),
		);

		return $meta_data;
	}
}
