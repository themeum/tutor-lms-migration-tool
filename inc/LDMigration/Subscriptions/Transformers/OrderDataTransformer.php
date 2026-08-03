<?php
/**
 * Transform LearnDash subscription-related transactions into Tutor orders.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Helper;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Subscriptions;
use Themeum\TutorLMSMigrationTool\MigrationMapper;
use Tutor\Models\OrderModel;

defined( 'ABSPATH' ) || exit;

/**
 * OrderDataTransformer class.
 *
 * @since 2.5.0
 */
class OrderDataTransformer implements DataTransformer {

	/**
	 * Transform parent + renewal charge transactions for a subscription.
	 *
	 * @since 2.5.0
	 *
	 * @param mixed $transaction_id LD subscription transaction ID.
	 *
	 * @return array
	 */
	public function transform( $transaction_id ) {
		$transaction_id = (int) $transaction_id;
		$post           = get_post( $transaction_id );

		if ( ! $post || ! Helper::is_subscription_transaction( $transaction_id ) ) {
			return array();
		}

		$mapper        = new MigrationMapper( Subscriptions::MAP_KEY );
		$plans_map     = $mapper->get_map_by_key( Subscriptions::PLANS );
		$course_id     = Helper::get_transaction_course_id( $transaction_id );
		$tutor_plan_id = $plans_map[ $course_id ] ?? 0;

		if ( ! $tutor_plan_id ) {
			return array();
		}

		global $wpdb;
		$plan = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tutor_subscription_plans WHERE id = %d",
				$tutor_plan_id
			)
		);
		if ( ! $plan ) {
			return array();
		}

		$parent_id = (int) $post->post_parent;
		$order_ids = array();

		if ( $parent_id ) {
			$order_ids[] = $parent_id;
		} else {
			$order_ids[] = $transaction_id;
		}

		$charges = get_children(
			array(
				'post_parent' => $transaction_id,
				'post_type'   => 'sfwd-transactions',
				'post_status' => 'any',
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
				'fields'      => 'ids',
			)
		);

		if ( ! empty( $charges ) ) {
			$order_ids = array_merge( $order_ids, array_map( 'intval', $charges ) );
		}

		$order_ids  = array_values( array_unique( $order_ids ) );
		$order_data = array();
		$is_first   = true;

		foreach ( $order_ids as $ld_order_id ) {
			$ld_order = get_post( $ld_order_id );
			if ( ! $ld_order ) {
				continue;
			}

			$price          = $this->get_transaction_price( $ld_order_id, $plan );
			$payment_args   = $this->get_payment_payloads( $ld_order_id );
			$created_at_gmt = Helper::to_gmt_datetime( $ld_order->post_date_gmt ? $ld_order->post_date_gmt : $ld_order->post_date );
			$updated_at_gmt = Helper::to_gmt_datetime( $ld_order->post_modified_gmt ? $ld_order->post_modified_gmt : $ld_order->post_modified );
			$user_id        = (int) $ld_order->post_author;
			$order_type     = $is_first ? OrderModel::TYPE_SUBSCRIPTION : OrderModel::TYPE_RENEWAL;

			$order_data[] = array(
				'ld_order_id'      => $ld_order_id,
				'order_type'       => $order_type,
				'parent_id'        => 0,
				'transaction_id'   => $payment_args['transaction_id'] ?? (string) $ld_order_id,
				'user_id'          => $user_id,
				'order_status'     => OrderModel::ORDER_COMPLETED,
				'payment_status'   => OrderModel::PAYMENT_PAID,
				'subtotal_price'   => $price,
				'pre_tax_price'    => $price,
				'tax_type'         => null,
				'tax_rate'         => 0,
				'tax_amount'       => 0,
				'total_price'      => $price,
				'net_payment'      => $price,
				'coupon_code'      => null,
				'coupon_amount'    => 0,
				'discount_amount'  => 0,
				'discount_type'    => null,
				'discount_reason'  => '',
				'fees'             => 0,
				'refund_amount'    => 0,
				'earnings'         => $price,
				'payment_method'   => $payment_args['payment_method'] ?? '',
				'payment_payloads' => isset( $payment_args['payment_payloads'] ) ? maybe_serialize( $payment_args['payment_payloads'] ) : '',
				'note'             => __( 'Order migrated from LearnDash subscription', 'tutor-lms-migration-tool' ),
				'created_by'       => $user_id,
				'updated_by'       => $user_id,
				'created_at_gmt'   => $created_at_gmt,
				'updated_at_gmt'   => $updated_at_gmt,
				'items'            => array(
					array(
						'item_id'       => $plan->id,
						'regular_price' => $plan->regular_price,
						'sale_price'    => ( isset( $plan->sale_price ) && $plan->sale_price > 0 ) ? $plan->sale_price : null,
					),
				),
				'meta_data'        => $this->prepare_meta_data( $ld_order, $plan, $order_type ),
			);

			$is_first = false;
		}

		return $order_data;
	}

	/**
	 * Resolve transaction amount.
	 *
	 * @since 2.5.0
	 *
	 * @param int    $transaction_id Transaction ID.
	 * @param object $plan           Tutor plan.
	 *
	 * @return float
	 */
	private function get_transaction_price( int $transaction_id, $plan ): float {
		$pricing = maybe_unserialize( get_post_meta( $transaction_id, 'pricing_info', true ) );
		if ( is_array( $pricing ) && isset( $pricing['price'] ) ) {
			return (float) $pricing['price'];
		}

		if ( is_object( $pricing ) && isset( $pricing->price ) ) {
			return (float) $pricing->price;
		}

		$amount = get_post_meta( $transaction_id, 'mc_gross', true );
		if ( '' !== $amount && null !== $amount ) {
			return (float) $amount;
		}

		return (float) $plan->regular_price;
	}

	/**
	 * Extract gateway payment args (mirrors TutorOrder).
	 *
	 * @since 2.5.0
	 *
	 * @param int $transaction_id Transaction ID.
	 *
	 * @return array
	 */
	private function get_payment_payloads( int $transaction_id ): array {
		$payment_data = get_post_meta( $transaction_id );
		$payment_data = array_map(
			static function ( $val ) {
				return is_array( $val ) ? $val[0] : $val;
			},
			$payment_data
		);

		$args = array();

		if ( isset( $payment_data['ld_payment_processor'] ) && 'paypal_ipn' === $payment_data['ld_payment_processor'] ) {
			$gateway_transaction = isset( $payment_data['gateway_transaction'] ) ? maybe_unserialize( $payment_data['gateway_transaction'] ) : array();
			$event               = is_array( $gateway_transaction ) ? ( $gateway_transaction['event'] ?? array() ) : array();
			$args                = array(
				'payment_method'   => 'paypal',
				'transaction_id'   => $event['txn_id'] ?? (string) $transaction_id,
				'payment_payloads' => $payment_data['gateway_transaction'] ?? array(),
			);
		}

		if ( isset( $payment_data['ld_payment_processor'] ) && 'razorpay' === $payment_data['ld_payment_processor'] ) {
			$gateway_transaction = isset( $payment_data['gateway_transaction'] ) ? maybe_unserialize( $payment_data['gateway_transaction'] ) : array();
			$event               = is_array( $gateway_transaction ) ? ( $gateway_transaction['event'] ?? array() ) : array();
			$payload             = $event['payload'] ?? array();
			$payment             = $payload['payment'] ?? array();
			$entity              = $payment['entity'] ?? array();
			$args                = array(
				'payment_method'   => 'razorpay',
				'transaction_id'   => $entity['id'] ?? (string) $transaction_id,
				'payment_payloads' => $payment_data['gateway_transaction'] ?? array(),
			);
		}

		if ( isset( $payment_data['ld_payment_processor'] ) && 'stripe_connect' === $payment_data['ld_payment_processor'] ) {
			$gateway_transaction = isset( $payment_data['gateway_transaction'] ) ? maybe_unserialize( $payment_data['gateway_transaction'] ) : array();
			$args                = array(
				'payment_method'   => 'stripe',
				'transaction_id'   => is_array( $gateway_transaction ) ? ( $gateway_transaction['id'] ?? (string) $transaction_id ) : (string) $transaction_id,
				'payment_payloads' => $payment_data['gateway_transaction'] ?? array(),
			);
		}

		if ( isset( $payment_data['action'] ) && 'ld_stripe_init_checkout' === $payment_data['action'] ) {
			$args = array(
				'payment_method' => 'stripe',
				'transaction_id' => (string) $transaction_id,
			);
		}

		return $args;
	}

	/**
	 * Prepare order meta rows.
	 *
	 * @since 2.5.0
	 *
	 * @param \WP_Post $order      LD order post.
	 * @param object   $tutor_plan Tutor plan.
	 * @param string   $order_type Order type.
	 *
	 * @return array
	 */
	private function prepare_meta_data( $order, $tutor_plan, string $order_type ): array {
		$user   = get_userdata( (int) $order->post_author );
		$gmt_now = Helper::to_gmt_datetime( $order->post_date_gmt ? $order->post_date_gmt : $order->post_date );

		$billing_data = array(
			'id'                 => (int) $order->post_author,
			'user_id'            => (int) $order->post_author,
			'billing_first_name' => $user ? $user->first_name : '',
			'billing_last_name'  => $user ? $user->last_name : '',
			'billing_email'      => $user ? $user->user_email : '',
			'billing_phone'      => '',
			'billing_zip_code'   => '',
			'billing_address'    => '',
			'billing_country'    => '',
			'billing_state'      => '',
			'billing_city'       => '',
		);

		$meta = array(
			array(
				'meta_key'       => OrderModel::META_KEY_BILLING_ADDRESS,
				'meta_value'     => wp_json_encode( $billing_data, JSON_UNESCAPED_UNICODE ),
				'created_at_gmt' => $gmt_now,
				'updated_at_gmt' => $gmt_now,
				'created_by'     => (int) $order->post_author,
				'updated_by'     => (int) $order->post_author,
			),
			array(
				'meta_key'       => OrderModel::META_PLAN_INFO,
				'meta_value'     => maybe_serialize( $tutor_plan ),
				'created_at_gmt' => $gmt_now,
				'updated_at_gmt' => $gmt_now,
				'created_by'     => (int) $order->post_author,
				'updated_by'     => (int) $order->post_author,
			),
		);

		if ( OrderModel::TYPE_SUBSCRIPTION === $order_type && ! empty( $tutor_plan->trial_value ) && $tutor_plan->trial_value > 0 ) {
			$meta[] = array(
				'meta_key'       => OrderModel::META_IS_PLAN_TRIAL_ORDER,
				'meta_value'     => true,
				'created_at_gmt' => $gmt_now,
				'updated_at_gmt' => $gmt_now,
				'created_by'     => (int) $order->post_author,
				'updated_by'     => (int) $order->post_author,
			);
		}

		return $meta;
	}
}
