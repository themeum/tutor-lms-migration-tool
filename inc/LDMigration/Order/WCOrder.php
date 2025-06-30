<?php
/**
 * Learndash Orders to WC Orders migration class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Order;

use Automattic\WooCommerce\Enums\OrderStatus;
use Themeum\TutorLMSMigrationTool\Interfaces\Order;

class WCOrder implements Order {

	/**
	 * Migrate learndash orders to tutor WC orders.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_Post|null $order the order object.
	 * @param int           $course_id the course id.
	 *
	 * @throws \Throwable
	 *
	 * @return void
	 */
	public function migrate( $order, $course_id ) {

		$gateway      = get_post_meta( $order->ID, 'ld_payment_processor', true );
		$pricing_info = maybe_unserialize( get_post_meta( $order->ID, 'pricing_info', true ) );
		$customer     = maybe_unserialize( get_post_meta( $order->ID, 'user', true ) );
		$product_id   = get_post_meta( $course_id, '_tutor_course_product_id', true );
		$product      = get_post( $product_id );

		$wc_order = new \WC_Order();

		$wc_order->set_created_via( 'admin' );
		$wc_order->set_customer_id( $order->post_author );
		$wc_order->set_currency( $pricing_info['currency'] ?? 'USD' );
		$wc_order->set_payment_method( $gateway );
		$wc_order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
		$wc_order->set_total( $pricing_info['price'] );
		$wc_order->add_meta_data( 'is_vat_exempt', 'no', true );
		$wc_order->set_billing_email( $customer['user_email'] );
		$wc_order->set_transaction_id( $this->get_transaction_data( $order->ID, $gateway )['transaction_id'] );
		$wc_order->set_payment_method_title( $this->get_transaction_data( $order->ID, $gateway )['payment_method_title'] );
		$wc_order->set_status( OrderStatus::COMPLETED );

		$wc_order_item = new \WC_Order_Item_Product();

		$wc_order_item->set_props(
			array(
				'quantity' => 1,
				'subtotal' => $pricing_info['price'],
				'total'    => $pricing_info['price'],
			)
		);

		if ( $product ) {
			$wc_order_item->set_props(
				array(
					'name'         => $product->post_title,
					'product_id'   => $product_id,
					'variation_id' => 0,
				)
			);
		}

		$wc_order_item->set_backorder_meta();

		$wc_order->add_item( $wc_order_item );

		$order_id = $wc_order->save();

		if ( ! $order_id ) {
			throw new \Exception( __( 'WooCommerce order creation failed', 'tutor-lms-migration-tool' ) );
		}
	}

	/**
	 * Get transaction data from gateway.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $order_id the order id.
	 * @param string $gateway the payment gateway.
	 *
	 * @return array
	 */
	private function get_transaction_data( $order_id, $gateway ) {
		$gateway_transaction = maybe_unserialize( get_post_meta( $order_id, 'gateway_transaction', true ) ?? array() );

		if ( $gateway_transaction ) {
			if ( 'paypal_ipn' === $gateway ) {
				$event = $gateway_transaction['event'] ?? array();
				return array(
					'transaction_id'       => $event['txn_id'] ?? '',
					'payment_method_title' => 'Paypal',
				);
			}

			if ( 'razorpay' === $gateway ) {
				$event          = $gateway_transaction['event'] ?? array();
				$payload        = $event['payload'] ?? array();
				$payment        = $payload['payment'] ?? array();
				$entity         = $payment['entity'] ?? array();
				$transaction_id = $entity['id'] ?? '';

				return array(
					'transaction_id'       => $transaction_id,
					'payment_method_title' => 'Razor Pay',
				);

			}

			if ( 'stripe_connect' === $gateway ) {

				return array(
					'transaction_id'       => $gateway_transaction['id'] ?? '',
					'payment_method_title' => 'Stripe Connect',
				);
			}
		}
	}

	/**
	 * Remove learndash orders after migration.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function remove_orders() {
		global $wpdb;
		$wpdb->query(
			"DELETE wp_posts, wp_postmeta
            FROM wp_posts
            INNER JOIN wp_postmeta ON wp_posts.ID = wp_postmeta.post_id
            WHERE post_type = 'sfwd-transactions'"
		);
	}
}
