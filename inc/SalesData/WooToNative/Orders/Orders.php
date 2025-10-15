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
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Helper;
use Themeum\TutorLMSMigrationTool\SalesDataTypes;
use Tutor\Helpers\QueryHelper;
use Tutor\Models\OrderModel;
use WC_Customer;
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
	 * WC_Order customer id
	 *
	 * @since 2.4.0
	 *
	 * @var int
	 */
	private $order_customer_id;

	/**
	 * WC_Order course id
	 *
	 * @since 2.4.0
	 *
	 * @var int
	 */
	private $order_course_id;

	/**
	 * Transformed Order data to migrate
	 *
	 * @since 2.4.0
	 *
	 * @var array
	 */
	private $transformed_order_data;

	/**
	 * Transformed Order meta data to migrate
	 *
	 * @since 2.4.0
	 *
	 * @var array
	 */
	private $transformed_order_meta_data;

	/**
	 * Transformed Order item data to migrate
	 *
	 * @since 2.4.0
	 *
	 * @var array
	 */
	private $transformed_order_item_data;

	/**
	 * Tutor order model
	 *
	 * @since 2.4.0
	 *
	 * @var OrderModel
	 */
	private $tutor_order_model;

	/**
	 * Resolve props
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		$this->tutor_order_model = new OrderModel();
	}

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
				'type'       => array(
					'shop_order',
					'shop_order_refund',
				),
				'orderby'    => 'date',
				'order'      => 'ASC',
				'return'     => 'objects',
				'meta_query' => array(
					array(
						'key'     => '_is_tutor_order_for_course',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return $orders->orders;
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
				'type'       => array(
					'shop_order',
					'shop_order_refund',
				),
				'limit'      => -1,
				'meta_query' => array(
					array(
						'key'     => '_is_tutor_order_for_course',
						'compare' => 'EXISTS',
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
		$order                   = is_int( $order ) ? wc_get_order( $order ) : $order;
		$this->wc_order          = $order;
		$this->order_customer_id = $order->get_customer_id();

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
		$this->transform_order_item_data( $this->wc_order );
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
		$tax_type   = get_option( 'woocommerce_prices_include_tax' ) === 'yes' ? 'inclusive' : 'exclusive';
		$tax_amount = $order->get_total_tax();
		$tax_rate   = 0;
		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			$tax_rate = $tax_item->get_rate_percent();
		}
		$order_status = ! $order->get_user_id() ? OrderModel::ORDER_CANCELLED : Helper::get_order_status( $order );
		$wc_coupon    = $order->get_coupons();
		$coupon       = ! empty( $wc_coupon ) ? reset( $wc_coupon ) : null;

		$data = array(
			'parent_id'        => $order->get_parent_id(),
			'transaction_id'   => $order->get_transaction_id(),
			'user_id'          => $order->get_user_id() ?? 0,
			'order_type'       => 'single_order',
			'order_status'     => $order_status,
			'payment_status'   => Helper::get_payment_status( $order ),
			'subtotal_price'   => $order->get_subtotal(),
			'pre_tax_price'    => $order->get_subtotal(),
			'tax_type'         => $tax_type,
			'tax_rate'         => $tax_rate,
			'tax_amount'       => $tax_amount,
			'total_price'      => $order->get_total(),
			'net_payment'      => $order->get_total() - $order->get_total_refunded(),
			'coupon_code'      => implode( ',', $order->get_coupon_codes() ),
			'coupon_amount'    => $coupon ? $order->get_discount_total() : 0.00,
			'discount_type'    => $coupon ? Helper::get_coupon_discount_type( new \WC_Coupon( $coupon->get_code() ) ) : null,
			'discount_amount'  => ! $coupon ? $order->get_discount_total() : 0.00,
			'discount_reason'  => '',
			'fees'             => $order->get_total_fees(),
			'earnings'         => ( $order->get_total() - $order->get_total_refunded() ) - $order->get_total_fees(),
			'refund_amount'    => $order->get_total_refunded(),
			'payment_method'   => $order->get_payment_method(),
			'payment_payloads' => wp_json_encode( $order->get_data() ),
			'note'             => $order->get_customer_note(),
			'created_at_gmt'   => gmdate( 'Y-m-d H:i:s', strtotime( $order->get_date_created() ) ),
			'created_by'       => $order->get_user_id() ?? 0,
			'updated_at_gmt'   => gmdate( 'Y-m-d H:i:s', strtotime( $order->get_date_modified() ) ),
			'updated_by'       => $order->get_user_id() ?? 0,
		);

		$this->transformed_order_data = Helper::filter_subscription_order_item( $data, $order );
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

		$this->transformed_order_meta_data = array(
			$this->tutor_order_model::META_KEY_BILLING_ADDRESS => wp_json_encode( $billing_data ),
		);

		return $this;
	}

	/**
	 * Transform order item data to native data structure
	 *
	 * @param WC_Order $order WC_Order object.
	 *
	 * @return void
	 */
	public function transform_order_item_data( WC_Order $order ) {
		$transformed_items = array();
		$items             = $order->get_items();

		foreach ( $items as $item ) {
			$product = $item->get_product();

			if ( ! $product ) {
				continue;
			}

			// Check if subscription item
			if ( $product && Helper::check_wc_subscription_product( $product ) ) {
				continue;
			}

			$course = tutor_utils()->product_belongs_with_course( $product->get_id() );
			if ( ! $course ) {
				continue;
			}

			$this->order_course_id = $course->post_id;

			$regular_price  = $product->get_regular_price();
			$sale_price     = $product->get_sale_price();
			$discount_price = null;

			if ( $sale_price > 0 && $sale_price < $regular_price ) {
				$discount_price = $regular_price - $sale_price;
			}

			$transformed_items[] = array(
				'item_id'        => $course->post_id,
				'regular_price'  => wc_format_decimal( $regular_price ),
				'sale_price'     => wc_format_decimal( $sale_price ),
				'discount_price' => wc_format_decimal( $discount_price ),
			);
		}

		$this->transformed_order_item_data = $transformed_items;
	}

	/**
	 * Migrate the order, store in database
	 *
	 * @since 2.4.0
	 *
	 * @throws \Throwable If fails to create order.
	 *
	 * @return bool true|false
	 */
	public function migrate(): bool {
		if ( ! tutor_utils()->count( $this->transformed_order_data ) ) {
			return true;
		}
		try {
			$order_id = $this->tutor_order_model->create_order( $this->transformed_order_data );

			// Update enrollment map.
			$enrollment = tutor_utils()->get_enrolled_data( $this->order_course_id, $this->order_customer_id );
			if ( $enrollment ) {
				update_post_meta( $enrollment->ID, '_tutor_enrolled_by_order_id', $order_id );
			}

			// Store meta.
			$meta_data = $this->prepare_order_meta( $order_id );
			if ( $meta_data ) {
				QueryHelper::insert_multiple_rows( 'tutor_ordermeta', $meta_data, false, false );
			}

			// Store order items.
			$order_items = $this->prepare_order_items( $order_id );
			if ( $order_items ) {
				QueryHelper::insert_multiple_rows( 'tutor_order_items', $order_items, false, false );
			}

			// Migrate customer.
			$this->migrate_customer();

			// Migrate earnings.
			$order_id_map = (object) array(
				'old_order_id' => $this->wc_order->get_id(),
				'new_order_id' => $order_id,
			);

			$this->migrate_earnings( $order_id_map );
		} catch ( \Throwable $th ) {
			throw $th;
		}

		return true;
	}

	/**
	 * Prepare order meta data to store in database
	 *
	 * @since 2.4.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array 2 dimensional array of meta data.
	 */
	private function prepare_order_meta( int $order_id ) {
		$order_meta = array();
		foreach ( $this->transformed_order_meta_data as $key => $value ) {
			$order_meta[] = array(
				'order_id'       => $order_id,
				'meta_key'       => $key,
				'meta_value'     => $value,
				'created_at_gmt' => current_time( 'mysql', 1 ),
				'created_by'     => $this->order_customer_id,
			);
		}

		return $order_meta;
	}

	/**
	 * Prepare order items data to store in database
	 *
	 * @since 2.4.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array 2 dimensional array of meta data.
	 */
	private function prepare_order_items( int $order_id ) {
		$items = array();
		foreach ( $this->transformed_order_item_data as $key => $value ) {
			$value['order_id'] = $order_id;
			$items[]           = $value;
		}

		return $items;
	}

	/**
	 * Get item id
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $item Item that we want to get id from. Item property may
	 * vary based on the source & extraction logics.
	 *
	 * @return int
	 */
	public function get_item_id( $item ): int {
		return (int) $item->get_id();
	}

	/**
	 * Migrate earnings
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $order_id_map Map of old & new order id.
	 *
	 * @return void
	 */
	private function migrate_earnings( $order_id_map ) {
		$earning = new Earnings();
		$earning->migrate( $order_id_map );
	}

	/**
	 * Migrate customer
	 *
	 * @since 2.4.0
	 *
	 * @throws \Throwable If error occurs while migrating customer.
	 *
	 * @return void
	 */
	private function migrate_customer() {
		try {
			$wc_customer  = new WC_Customer( $this->order_customer_id );
			$customer_obj = tlmt_get_sales_data_object( SalesDataTypes::CUSTOMERS, MigrationTypes::WC_TO_NATIVE );
			$customer_obj->extract( $wc_customer )->transform()->migrate();
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}
