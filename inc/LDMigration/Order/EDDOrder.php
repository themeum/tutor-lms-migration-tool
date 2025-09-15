<?php
/**
 * Learndash Orders to EDD Orders migration class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Order;

use Themeum\TutorLMSMigrationTool\Interfaces\Order;

class EDDOrder implements Order {

	/**
	 * Migrate learndash orders to tutor EDD orders.
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

		$order_args = $this->prepare_edd_order_args( $order );

		// Insert EDD order.
		$order_id = edd_add_order( $order_args );

		if ( ! $order_id ) {
			throw new \Exception( __( 'Could not create edd order', 'tutor-lms-migration-tool' ) );
		}

		$customer = $this->maybe_create_edd_customer( $order->post_author, $order_args['email'] ?? '' );

		// Attach order to EDD customer.
		$customer->attach_payment( $order_id );

		edd_update_order( $order_id, array( 'customer_id' => $customer->id ) );

		$download_id = get_post_meta( $course_id, '_tutor_course_product_id', true );

		// Insert order item.
		if ( $download_id ) {
			$order_item_args = $this->prepare_edd_order_item_args( $download_id, $order_id );

			$order_item_id = edd_add_order_item( $order_item_args );

			if ( ! $order_item_id ) {
				throw new \Exception( __( 'Could not create edd order item', 'tutor-lms-migration-tool' ) );
			}
		}

		// Enroll customer to tutor course.
		$has_any_enrolled = tutor_utils()->has_any_enrolled( $course_id, $order->post_author );
		if ( ! $has_any_enrolled ) {
			tutor_utils()->do_enroll( $course_id, $order_id, $order->post_author );
		}
	}

	/**
	 * Prepare order item args for creating edd order item.
	 *
	 * @since 2.3.0
	 *
	 * @param int $download_id the product id.
	 * @param int $order_id the order id.
	 *
	 * @return array
	 */
	private function prepare_edd_order_item_args( $download_id, $order_id ) {
		$product = get_post( $download_id );
		$amount  = floatval( get_post_meta( $download_id, 'edd_price', true ) );

		$order_item_args = array(
			'order_id'     => $order_id,
			'product_id'   => $download_id,
			'product_name' => $product->post_title,
			'type'         => $product->post_type,
			'status'       => 'complete',
			'quantity'     => 1,
			'amount'       => $amount,
			'subtotal'     => $amount,
			'total'        => $amount,
			'rate'         => 1.00,
		);

		return $order_item_args;
	}

	/**
	 * Prepare EDD Order args for insertion.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_Post|null $order the learndash order.
	 *
	 * @return array
	 */
	private function prepare_edd_order_args( $order ) {

		$gateway      = get_post_meta( $order->ID, 'ld_payment_processor', true );
		$test_mode    = (int) get_post_meta( $order->ID, 'is_test_mode', true );
		$pricing_info = maybe_unserialize( get_post_meta( $order->ID, 'pricing_info', true ) );
		$customer     = maybe_unserialize( get_post_meta( $order->ID, 'user', true ) );

		$order_args = array(
			'status'   => 'complete',
			'type'     => 'sale',
			'user_id'  => $order->post_author,
			'email'    => $customer['user_email'] ?? '',
			'gateway'  => $gateway ?? 'manual',
			'mode'     => $test_mode ? 'test' : 'live',
			'currency' => $pricing_info['currency'] ?? 'USD',
			'subtotal' => floatval( $pricing_info['price'] ) ?? 0.00,
			'discount' => floatval( $pricing_info['discount'] ) ?? 0.00,
			'total'    => floatval( $pricing_info['price'] ) ?? 0.00,
			'rate'     => 1.00,
		);

		return $order_args;
	}

	/**
	 * Create or updates existing edd customer.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $user_id the user id.
	 * @param string $email the user email.
	 *
	 * @return \EDD_Customer
	 */
	private function maybe_create_edd_customer( $user_id, $email ) {

		$customer = new \EDD_Customer( $user_id, true );

		if ( ! empty( $customer->id ) && $email !== $customer->email ) {
			$customer->add_email( $email );
		}

		if ( empty( $customer->id ) ) {
			$customer = new \EDD_Customer( $email );
		}

		if ( empty( $customer->id ) ) {
			$first_name = get_user_meta( $user_id, 'first_name', true );
			$last_name  = get_user_meta( $user_id, 'last_name', true );

			$name = '';

			if ( empty( $first_name ) && empty( $last_name ) ) {
				$name = $email;
			} else {
				$name = $first_name . ' ' . $last_name;
			}

			$customer_args = array(
				'user_id' => $user_id,
				'name'    => $name,
				'email'   => $email,
			);

			$customer_id = edd_add_customer( $customer_args );

			if ( $customer_id ) {
				$customer = new \EDD_Customer( $user_id, true );
			}
		}

		return $customer;
	}

	/**
	 * Remove learndash orders after migration.
	 *
	 * @since 2.3.0
	 *
	 * @param int $order_id the order id of the order to remove.
	 *
	 * @return void
	 */
	public function remove_orders( $order_id ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE wp_posts, wp_postmeta
				FROM wp_posts
				INNER JOIN wp_postmeta ON wp_posts.ID = wp_postmeta.post_id
				WHERE ID = '%d' ",
				$order_id
			)
		);
	}
}
