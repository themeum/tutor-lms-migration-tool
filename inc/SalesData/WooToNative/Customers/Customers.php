<?php //phpcs:ignore
/**
 * Concrete class to handle customer data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Customers;

use AllowDynamicProperties;
use Exception;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;
use Tutor\Helpers\QueryHelper;
use Tutor\Models\BillingModel;

defined( 'ABSPATH' ) || exit;

/**
 * Customer data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Customers implements MigrationTemplate {
	/**
	 * Customer data
	 *
	 * @since 2.4.0
	 *
	 * @var array
	 */
	public $customer_data = array();

	/**
	 * $woo_customers description
	 *
	 * @var array
	 */
	public $woo_customers = array();

	/**
	 * Total customer count
	 *
	 * @since 2.4.0
	 *
	 * @var int
	 */
	public $total_customer_count;

	/**
	 * Customers
	 *
	 * @since 2.4.0
	 *
	 * @var array
	 */
	public $customer_meta_data = array();

	/**
	 * Tutor Orders table
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	protected $tutor_order_table = 'tutor_orders';

	/**
	 * Tutor Orders Meta table
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	protected $tutor_customers_table = 'tutor_customers';

	/**
	 * User_orders description]
	 *
	 * @var object
	 */
	public $user_orders = array();

	/**
	 * Customer construction
	 *
	 * @return  void
	 */
	public function __construct() {
		$this->woo_customers = $this->get_woocommerce_customers( -1 );
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
	public function get_items( int $limit = 5, int $offset = 1 ): array {
		return $this->get_woocommerce_customers( $limit, $offset );
	}

	/**
	 * Total items count from source
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_total_items_count(): int {
		return $this->total_customer_count;
	}

	/**
	 * Extract customer data
	 *
	 * @since 2.4.0
	 *
	 * @param object $customer customer order object.
	 *
	 * @return self
	 */
	public function extract( $customer = null ): self {
		$args = array(
			'customer_id' => $customer->id,
			'limit'       => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		);

		$orders            = wc_get_orders( $args );
		$this->user_orders = $orders[0];
		return $this;
	}

	/**
	 * Get WooCommerce customers from orders
	 *
	 * @since 2.4.0
	 *
	 * @param int $limit  Number of items to fetch from source.
	 * @param int $offset Number of items to skip from source.
	 *
	 * @return array
	 */
	public function get_woocommerce_customers( $limit = 5, $offset = 1 ) {
		$args = array(
			'status'     => array( 'completed', 'processing', 'on-hold' ),
			'limit'      => $limit,
			'paged'      => $offset,
			'return'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => '_is_tutor_order_for_course',
					'compare' => 'EXISTS',
				),
			),
		);

		$orders    = wc_get_orders( $args );
		$customers = array();

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );

			$customers[] = array(
				'id'    => $order->get_user_id(),
				'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'email' => $order->get_billing_email(),
			);
		}

		// Remove duplicates.
		$customers = array_unique( $customers, SORT_REGULAR );

		$this->woo_customers        = $customers;
		$this->total_customer_count = count( $customers );

		return $customers;
	}

	/**
	 * Get total WooCommerce customers count from orders
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_woocommerce_customers_count() {
		$args = array(
			'status'     => array( 'completed', 'processing', 'on-hold' ),
			'limit'      => -1,
			'return'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => '_is_tutor_order_for_course',
					'compare' => 'EXISTS',
				),
			),
		);

		$orders    = wc_get_orders( $args );
		$customers = array();

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );

			$customers[] = array(
				'id'    => $order->get_user_id(),
				'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'email' => $order->get_billing_email(),
			);
		}

		// Remove duplicates.
		$customers = array_unique( $customers, SORT_REGULAR );

		return count( $customers );
	}

	/**
	 * Transform the customer data to native customer
	 *
	 * @since 2.4.0
	 *
	 * @return self
	 *
	 * @throws \Throwable If transformation fails.
	 * @throws Exception If customer data not found.
	 */
	public function transform(): self {
		try {
			$order = $this->user_orders;
			if ( empty( $order ) ) {
				throw new Exception( 'Customer data not found!' );
			}

			$user_id = $order->get_user_id();
			$user    = $user_id ? get_userdata( $user_id ) : null;

			$customer_meta_value = array(
				'user_id'            => $user->ID,
				'billing_first_name' => $order->get_address( 'billing' )['first_name'] ?? '',
				'billing_last_name'  => $order->get_address( 'billing' )['last_name'] ?? '',
				'billing_email'      => $user->user_email ?? '',
				'billing_phone'      => $order->get_address( 'billing' )['phone'] ?? '',
				'billing_zip_code'   => $order->get_address( 'billing' )['postcode'] ?? '',
				'billing_address'    => $order->get_address( 'billing' )['address_1'] ?? '',
				'billing_country'    => $order->get_address( 'billing' )['country'] ?? '',
				'billing_state'      => $order->get_address( 'billing' )['state'] ?? '',
				'billing_city'       => $order->get_address( 'billing' )['city'] ?? '',
			);

			$this->customer_meta_data = $customer_meta_value;
			return $this;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Migrate the customer, store in database
	 *
	 * @since 2.4.0
	 *
	 * @return bool true|false|Exception
	 * @throws \Throwable If migration fails.
	 * @throws Exception If customer data not found.
	 */
	public function migrate(): bool {
		try {
			if ( empty( $this->customer_meta_data ) ) {
				throw new Exception( 'Customer data not found!' );
			}

			global $wpdb;
			$order_meta_inserted = QueryHelper::insert( $wpdb->prefix . $this->tutor_customers_table, $this->customer_meta_data );
			if ( ! $order_meta_inserted ) {
				throw new Exception( 'Customer migration failed!' );
			}
			return true;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}
