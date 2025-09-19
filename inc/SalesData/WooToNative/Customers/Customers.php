<?php
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
	protected $tutor_order_meta_table = 'tutor_ordermeta';

	/**
	 * Extract customer data
	 *
	 * @since 2.4.0
	 *
	 * @param int|object $customer_id Customer id or object.
	 *
	 * @return mixed
	 */
	public function extract( $order ) {
		$this->customer_data = $this->get_woocommerce_customers_orders_data($order);
		return $this;
	}

	/**
	 * Get WooCommerce customers from orders
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_woocommerce_customers_orders_data( $order ) {
		$user_id = $order->get_user_id();
		$user    = $user_id ? get_userdata( $user_id ) : null;

		$customer_info = array(
			'order_id'       => $order->get_id(),
			'order_date'     => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'status'         => $order->get_status(),
			'total'          => $order->get_total(),
			'payment_method' => $order->get_payment_method(),
			'billing'        => $order->get_address( 'billing' ),
			'shipping'       => $order->get_address( 'shipping' ),
			'user'           => $user ? array(
				'id'    => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
			) : null,
		);
		return $customer_info;
	}

	/**
	 * Transform the customer data to native customer
	 *
	 * @since 2.4.0
	 *
	 * @return self
	 */
	public function transform(): self {
		try {
			$woo_customer_data   = $this->customer_data;
			if ( empty( $woo_customer_data ) ) {
				throw new Exception( 'Customer data not found!' );
			}
			$tutor_customer      = ( new BillingModel() )->get_info( $woo_customer_data['user']['id'] );
			$customer_meta_value = array(
				'id'                 => $tutor_customer->id,
				'user_id'            => $woo_customer_data['user']['id'] ?? '',
				'billing_first_name' => $woo_customer_data['billing']['first_name'] ?? '',
				'billing_last_name'  => $woo_customer_data['billing']['last_name'] ?? '',
				'billing_email'      => $woo_customer_data['billing']['email'] ?? '',
				'billing_phone'      => $woo_customer_data['billing']['phone'] ?? '',
				'billing_zip_code'   => $woo_customer_data['billing']['postcode'] ?? '',
				'billing_address'    => $woo_customer_data['billing']['address_1'] ?? '',
				'billing_country'    => $woo_customer_data['billing']['country'] ?? '',
				'billing_state'      => $woo_customer_data['billing']['state'] ?? '',
				'billing_city'       => $woo_customer_data['billing']['city'] ?? '',
			);

			$data = array(
				'order_id'       => $woo_customer_data['order_id'],
				'meta_key'       => 'billing_address',
				'meta_value'     => json_encode( $customer_meta_value ),
				'created_at_gmt' => $woo_customer_data['order_date'],
			);
			$this->customer_data = $data;
			return $this;
		} catch (\Throwable $th) {
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
			$woo_customer_data = $this->customers;
			if ( empty( $woo_customer_data ) ) {
				throw new Exception( 'Customer data not found!' );
			}

			global $wpdb;
			// $data_insert_format  = array( '%d', '%s', '%s', '%s' );
			$order_meta_inserted = QueryHelper::insert( $wpdb->prefix . $this->tutor_order_meta_table, $data_to_insert );
			// $order_meta_inserted = $wpdb->insert( $wpdb->prefix . $this->tutor_order_meta_table, $data_to_insert, $data_insert_format );
			if(!$order_meta_inserted) {
				throw new Exception( 'Customer migration failed!' );
			} 
			return true;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}
