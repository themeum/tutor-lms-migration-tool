<?php
/**
 * Learndash Orders to Tutor Orders migration class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Order;

use Themeum\TutorLMSMigrationTool\Interfaces\Order;
use TUTOR\Earnings;
use Tutor\Ecommerce\OrderController;
use Tutor\Models\OrderActivitiesModel;
use Tutor\Models\OrderModel;

/**
 * TutorOrder class to migrate orders from learndash.
 */
class TutorOrder implements Order {

	/**
	 * Tutor OrderController class instance.
	 *
	 * @var OrderController
	 */
	private $order_controller;

	/**
	 * Tutor Earnings class instance.
	 *
	 * @var Earnings
	 */
	private $earnings;

	/**
	 * Tutor OrderActivitiesModel class instance.
	 *
	 * @var OrderActivitiesModel
	 */
	private $order_activities_model;

	/**
	 * Tutor order class constructor.
	 */
	public function __construct() {
		$this->order_controller       = new OrderController( false );
		$this->earnings               = Earnings::get_instance();
		$this->order_activities_model = new OrderActivitiesModel();
	}

	/**
	 * Migrate learndash orders to tutor monetization order.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_Post|null $order the learndash order post.
	 * @param integer       $course_id the course id of the order.
	 *
	 * @throws \Throwable
	 *
	 * @return void
	 */
	public function migrate( $order, $course_id ) {
		$course_data = get_post_meta( $course_id, '_sfwd-courses', true );

		$args = $this->get_payment_payloads( $order );

		$item = array(
			'item_id'        => $course_id,
			'regular_price'  => $course_data['sfwd-courses_course_price'],
			'sale_price'     => null,
			'discount_price' => null,
		);

		try {
			$order_id = $this->order_controller->create_order( $order->post_author, $item, OrderModel::PAYMENT_PAID, OrderModel::TYPE_SINGLE_ORDER, null, $args );

			$order_meta = (object) array(
				'order_id'   => $order_id,
				'meta_key'   => OrderActivitiesModel::META_KEY_HISTORY,
				'meta_value' => __( 'Order migrated from learndash', 'tutor-lms-migration-tool' ),
			);

			$this->order_activities_model->add_order_meta( $order_meta );

			$this->earnings->prepare_order_earnings( $order_id );
			$this->earnings->remove_before_store_earnings();
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Get payment arguments based on the payment method.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_Post $order the learndash order.
	 *
	 * @return array
	 */
	private function get_payment_payloads( $order ) {

		$payment_data = get_post_meta( $order->ID );
		$payment_data = array_map( fn( $val ) => $val[0], $payment_data );

		$args = array();

		if ( isset( $payment_data['ld_payment_processor'] ) && 'paypal_ipn' === $payment_data['ld_payment_processor'] ) {
			$gateway_transaction = isset( $payment_data['gateway_transaction'] ) ? maybe_unserialize( $payment_data['gateway_transaction'] ) : array();
			$event               = $gateway_transaction['event'] ?? array();
			$args                = array(
				'payment_method'   => 'paypal',
				'transaction_id'   => $event['txn_id'] ?? $order->ID,
				'payment_payloads' => $payment_data['gateway_transaction'] ?? array(),
			);
		}

		if ( isset( $payment_data['ld_payment_processor'] ) && 'razorpay' === $payment_data['ld_payment_processor'] ) {
			$gateway_transaction = isset( $payment_data['gateway_transaction'] ) ? maybe_unserialize( $payment_data['gateway_transaction'] ) : array();
			$event               = $gateway_transaction['event'] ?? array();
			$payload             = $event['payload'] ?? array();
			$payment             = $payload['payment'] ?? array();
			$entity              = $payment['entity'] ?? array();
			$transaction_id      = $entity['id'] ?? $order->ID;
			$args                = array(
				'payment_method'   => 'razorpay',
				'transaction_id'   => $transaction_id,
				'payment_payloads' => $payment_data['gateway_transaction'] ?? array(),
			);
		}

		if ( isset( $payment_data['ld_payment_processor'] ) && 'stripe_connect' === $payment_data['ld_payment_processor'] ) {
			$gateway_transaction = isset( $payment_data['gateway_transaction'] ) ? maybe_unserialize( $payment_data['gateway_transaction'] ) : array();
			$args                = array(
				'payment_method'   => 'stripe',
				'transaction_id'   => $gateway_transaction['id'] ?? $order->ID,
				'payment_payloads' => $payment_data['gateway_transaction'] ?? array(),
			);
		}

		if ( isset( $payment_data['action'] ) && 'ld_stripe_init_checkout' === $payment_data['action'] ) {
			$args = array(
				'payment_method' => 'stripe',
				'transaction_id' => $order->ID,
			);
		}

		return $args;
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
