<?php
/**
 * Concrete class to handle order data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Orders;

use AllowDynamicProperties;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Helper;
use WC_Order;
use WC_Order_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Order data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Orders implements MigrationTemplate {

	/**
	 * WC_Order
	 *
	 * @since 2.4.0
	 *
	 * @var WC_Order
	 */
	private $wc_order;

	/**
	 * Transformed Order data to migrate
	 *
	 * @since 2.4.0
	 *
	 * @var array
	 */
	private $order_data;

	/**
	 * Transformed Order meta data to migrate
	 *
	 * @since 2.4.0
	 *
	 * @var array
	 */
	private $order_meta_data;


	/**
	 * Get items from source
	 *
	 * @since 2.4.0
	 *
	 * @param int $limit  Number of items to fetch from source.
	 * @param int $offset Number of items to skip from source.
	 *
	 * @return array
	 */
	public function get_items( int $limit = 5, int $offset = 0 ): array {
		return $this->get_orders( $limit, $offset );
	}

	/**
	 * Total items count from source
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_total_items_count(): int {
		return $this->get_total_orders_count();
	}

	/**
	 * Fetch WooCommerce orders excluding trashed ones.
	 *
	 * @since 2.4.0
	 *
	 * @param int $limit  Number of orders to fetch per page.
	 * @param int $offset Number of orders to skip.
	 *
	 * @return array Array of orders.
	 */
	public function get_orders( $limit = 10, $offset = 0 ) {
		$orders = wc_get_orders(
			array(
				'paginate'   => true,
				'limit'      => $limit,
				'offset'     => $offset,
				'status'     => $this->get_order_statuses(),
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'objects',
				'meta_query' => array(
					array(
						'key'     => '_is_tutor_order_for_course',
						'compare' => 'EXISTS',
					),
					array(
						'key'   => '_tutor_order_type',
						'value' => 'single_order',
					),
				),
			)
		);

		return $orders;
	}

	/**
	 * Get total number of orders
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_total_orders_count() {
		$total_query = new WC_Order_Query(
			array(
				'status'     => $this->get_order_statuses(),
				'return'     => 'ids',
				'limit'      => -1,
				'meta_query' => array(
					array(
						'key'     => '_is_tutor_order_for_course',
						'compare' => 'EXISTS',
					),
					array(
						'key'   => '_tutor_order_type',
						'value' => 'single_order',
					),
				),
			)
		);

		return count( $total_query->get_orders() );
	}

	/**
	 * Get order statuses
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	private function get_order_statuses() {
		$statuses = array_keys( wc_get_order_statuses() );
		$statuses = array_filter(
			$statuses,
			function ( $status ) {
				return 'wc-trash' !== $status;
			}
		);

		return $statuses;
	}

	/**
	 * Extract order data
	 *
	 * @since 2.4.0
	 *
	 * @param int|object $order Order id or object.
	 *
	 * @return MigrationTemplate
	 */
	public function extract( $order ): MigrationTemplate {
		$order          = is_int( $order ) ? wc_get_order( $order ) : $order;
		$this->wc_order = $order;

		return $this;
	}

	/**
	 * Transform the order data to native order
	 *
	 * @since 2.4.0
	 *
	 * @return MigrationTemplate
	 */
	public function transform(): MigrationTemplate {
		$this->transform_order_data( $this->wc_order );
		$this->transform_order_meta_data( $this->wc_order );

		return $this;
	}

	/**
	 * Transform wc order data to native data structure
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Order $order WC_Order object.
	 *
	 * @return void
	 */
	public function transform_order_data( WC_Order $order ) {
		$data = array(
			'parent_id'        => $order->get_parent_id(),
			'transaction_id'   => $order->get_transaction_id(),
			'user_id'          => $order->get_user_id(),
			'order_type'       => 'single_order',
			'order_status'     => Helper::get_order_status( $order ),
			'payment_status'   => Helper::get_order_status( $order ),
			'subtotal_price'   => $order->get_subtotal(),
			'pre_tax_price'    => $order->get_subtotal(),
			'tax_type'         => 'VAT',
			'tax_rate'         => '',
			'tax_amount'       => $order->get_total_tax(),
			'total_price'      => $order->get_total(),
			'net_payment'      => $order->get_total() - $order->get_total_refunded(),
			'coupon_code'      => implode( ',', $order->get_coupon_codes() ),
			'coupon_amount'    => $order->get_discount_total(),
			'discount_type'    => null,
			'discount_amount'  => $order->get_discount_total(),
			'discount_reason'  => '',
			'fees'             => $order->get_total_fees(),
			'earnings'         => ( $order->get_total() - $order->get_total_refunded() ) - $order->get_total_fees(),
			'refund_amount'    => $order->get_total_refunded(),
			'payment_method'   => $order->get_payment_method(),
			'payment_payloads' => wp_json_encode( $order->get_data() ),
			'note'             => $order->get_customer_note(),
			'created_at_gmt'   => gmdate( 'Y-m-d H:i:s', strtotime( $order->get_date_created() ) ),
			'created_by'       => $order->get_user_id(),
			'updated_at_gmt'   => gmdate( 'Y-m-d H:i:s', strtotime( $order->get_date_modified() ) ),
			'updated_by'       => $order->get_user_id(),
		);

		$this->order_data = $data;
	}

	/**
	 * Transform WC order billing meta data to native data structure.
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Order $order WC_Order object.
	 *
	 * @return array Native formatted billing data.
	 */
	public function transform_order_meta_data( WC_Order $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array();
		}

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

		$this->order_meta_data = $billing_data;

		return $this;
	}


	/**
	 * Migrate the order, store in database
	 *
	 * @since 2.4.0
	 *
	 * @return bool true|false
	 */
	public function migrate(): bool {
		return true;
	}
}
