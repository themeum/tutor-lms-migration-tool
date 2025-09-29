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

defined( 'ABSPATH' ) || exit;

/**
 * Customer data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Customers implements MigrationTemplate {

	/**
	 * Tutor Orders Meta table
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	protected $tutor_customers_table = 'tutor_customers';

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
	public $customer_billing_data = array();

	/**
	 * User_orders description]
	 *
	 * @var object
	 */
	public $current_customer = array();

	/**
	 * Get items from source
	 *
	 * @since 2.4.0
	 *
	 * @param int $limit  Number of items to fetch from source.
	 * @param int $offset Number of items to skip from source.
	 *
	 * @return array
	 * @throws \Throwable Return throws.
	 */
	public function get_items( int $limit = 5, int $offset = 1 ): array {
		return $this->get_woocommerce_customers( $limit, $offset );
	}

	/**
	 * Total customer count
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_total_items_count(): int {
		$this->total_customer_count = $this->get_woocommerce_customers_count();
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
	 * @throws \Throwable Return throws.
	 */
	public function extract( $customer = null ): self {
		try {
			$this->current_customer = (object) $customer;
			return $this;
		} catch ( \Throwable $th ) {
			throw $th;
		}
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
	 * @throws \Throwable Return throws.
	 */
	public function get_woocommerce_customers( $limit = 5, $offset = 1 ) {
		try {
			$args      = array(
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
			$user_map  = array();

			foreach ( $orders as $order_id ) {
				$order   = wc_get_order( $order_id );
				$user_id = $order->get_user_id();
				if ( ! in_array( $user_id, $user_map, true ) ) {
					$customers[] = array(
						'id'    => $user_id,
						'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
						'email' => $order->get_billing_email(),
					);
					$user_map[]  = $user_id;
				}
			}

			return $customers;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Get total WooCommerce customers count from orders
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 * @throws \Throwable Return throws.
	 */
	public function get_woocommerce_customers_count() {
		global $wpdb;
		try {
			$is_hpos_enabled = false;

			if ( class_exists( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class ) ) {
				$controller      = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
				$is_hpos_enabled = $controller->custom_orders_table_usage_is_enabled();
			}

			if ( $is_hpos_enabled ) {
				// HPOS query.
				$query = "
				SELECT DISTINCT c.customer_id, c.user_id, c.email, c.first_name, c.last_name
				FROM {$wpdb->prefix}wc_customer_lookup AS c
				INNER JOIN {$wpdb->prefix}wc_order_stats AS s 
					ON c.customer_id = s.customer_id
				INNER JOIN {$wpdb->prefix}wc_orders_meta AS om 
					ON om.order_id = s.order_id
				WHERE om.meta_key = %s
			";
			} else {
				// Legacy postmeta query.
				$query = "
				SELECT DISTINCT c.customer_id, c.user_id, c.email, c.first_name, c.last_name
				FROM {$wpdb->prefix}wc_customer_lookup AS c
				INNER JOIN {$wpdb->prefix}wc_order_stats AS s 
					ON c.customer_id = s.customer_id
				INNER JOIN {$wpdb->postmeta} AS pm 
					ON pm.post_id = s.order_id
				WHERE pm.meta_key = %s
			";
			}

			$customers = $wpdb->get_results( $wpdb->prepare( $query, '_is_tutor_order_for_course' ) ); //phpcs:ignore
			return count( $customers );
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Transform the customer data to tutor native customer
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
			if ( empty( $this->current_customer ) ) {
				throw new Exception( 'Customer data not found!' );
			}

			$customer_info               = array(
				'user_id'            => $this->current_customer->get_id(),
				'billing_first_name' => $this->current_customer->get_billing_first_name(),
				'billing_last_name ' => $this->current_customer->get_billing_last_name(),
				'billing_email'      => $this->current_customer->get_billing_email(),
				'billing_phone'      => $this->current_customer->get_billing_phone(),
				'billing_zip_code'   => $this->current_customer->get_billing_postcode(),
				'billing_address'    => $this->current_customer->get_billing_address_1(),
				'billing_country'    => $this->current_customer->get_billing_country(),
				'billing_state'      => $this->current_customer->get_billing_state(),
				'billing_city'       => $this->current_customer->get_billing_city(),
			);
			$this->customer_billing_data = $customer_info;
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
			if ( empty( $this->customer_billing_data ) ) {
				throw new Exception( 'Customer data not found!' );
			}
			global $wpdb;
			$is_customer_exist = QueryHelper::get_row(
				$this->tutor_customers_table,
				array( 'user_id' => $this->customer_billing_data['user_id'] ),
				'id'
			);
			if ( ! empty( $is_customer_exist ) ) {
				// Update existing customer billing info.
				$order_meta_inserted = QueryHelper::update(
					$wpdb->prefix . $this->tutor_customers_table,
					$this->customer_billing_data,
					array(
						'id'      => $is_customer_exist->id,
						'user_id' => $this->customer_billing_data['user_id'],
					)
				);
				if ( ! $order_meta_inserted ) {
					throw new Exception( 'Customer migration failed!' );
				}
				return true;
			}
			// Insert data in tutor_customers table.
			$order_meta_inserted = QueryHelper::insert( $wpdb->prefix . $this->tutor_customers_table, $this->customer_billing_data );
			if ( ! $order_meta_inserted ) {
				throw new Exception( 'Customer migration failed!' );
			}
			return true;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}
