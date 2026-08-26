<?php
/**
 * Transform Lifter recurring order into Tutor subscription order payload.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;
use Themeum\TutorLMSMigrationTool\LIFMigration\Orders\PaymentMethodMapper;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Helper;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Subscriptions;
use Themeum\TutorLMSMigrationTool\MigrationMapper;
use Tutor\Models\OrderModel;

defined( 'ABSPATH' ) || exit;

/**
 * OrderDataTransformer class.
 *
 * @since 2.6.0
 */
class OrderDataTransformer implements DataTransformer {

	/**
	 * Build a single subscription order snapshot from a Lifter recurring order.
	 *
	 * @since 2.6.0
	 *
	 * @param mixed $llms_order_id Lifter order ID.
	 *
	 * @return array Empty or one-element list of order payloads.
	 */
	public function transform( $llms_order_id ) {
		$llms_order_id = (int) $llms_order_id;
		$post          = get_post( $llms_order_id );

		if ( ! $post || ! Helper::is_recurring_course_order( $llms_order_id ) ) {
			return array();
		}

		$mapper    = new MigrationMapper( Subscriptions::MAP_KEY );
		$plans_map = $mapper->get_map_by_key( Subscriptions::PLANS );
		$plan_id   = (int) get_post_meta( $llms_order_id, '_llms_plan_id', true );
		$tutor_plan_id = $plans_map[ $plan_id ] ?? 0;

		if ( ! $tutor_plan_id ) {
			$course_id = Helper::get_order_course_id( $llms_order_id );
			// Fall back to any plan mapped for this course's access plans.
			foreach ( $plans_map as $llms_plan => $mapped ) {
				$llms_plan = (int) $llms_plan;
				if ( $course_id === (int) get_post_meta( $llms_plan, '_llms_product_id', true ) ) {
					$tutor_plan_id = (int) $mapped;
					break;
				}
			}
		}

		if ( ! $tutor_plan_id ) {
			return array();
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$plan = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tutor_subscription_plans WHERE id = %d",
				$tutor_plan_id
			)
		);
		if ( ! $plan ) {
			return array();
		}

		$user_id = (int) get_post_meta( $llms_order_id, '_llms_user_id', true );
		if ( $user_id < 1 ) {
			$user_id = (int) $post->post_author;
		}

		$total_raw = get_post_meta( $llms_order_id, '_llms_total', true );
		$original  = (float) get_post_meta( $llms_order_id, '_llms_original_total', true );
		$total     = ( '' === $total_raw || false === $total_raw || null === $total_raw )
			? null
			: (float) $total_raw;

		if ( null === $total ) {
			$total = (float) $plan->regular_price;
		}
		if ( $original <= 0 ) {
			$original = (float) $plan->regular_price;
		}

		$gateway       = (string) get_post_meta( $llms_order_id, '_llms_payment_gateway', true );
		$created_gmt   = Helper::to_gmt_datetime( $post->post_date_gmt ? $post->post_date_gmt : $post->post_date );
		$updated_gmt   = Helper::to_gmt_datetime( $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified );
		$txn_id        = (string) get_post_meta( $llms_order_id, '_llms_gateway_subscription_id', true );
		if ( '' === $txn_id ) {
			$txn_id = (string) $llms_order_id;
		}

		$on_sale    = 'yes' === get_post_meta( $llms_order_id, '_llms_on_sale', true );
		$sale_meta  = get_post_meta( $llms_order_id, '_llms_sale_price', true );
		$has_coupon = 'yes' === get_post_meta( $llms_order_id, '_llms_coupon_used', true );

		$sale_price = null;
		if ( $on_sale && '' !== $sale_meta && false !== $sale_meta && null !== $sale_meta ) {
			$sale_price = (float) $sale_meta;
			if ( $sale_price <= 0 || ( $original > 0 && $sale_price >= $original ) ) {
				$sale_price = null;
			}
		} elseif ( ! $has_coupon && isset( $plan->sale_price ) && $plan->sale_price > 0 ) {
			$sale_price = (float) $plan->sale_price;
		}

		$coupon_code   = null;
		$coupon_amount = 0.0;
		if ( $has_coupon ) {
			$code          = (string) get_post_meta( $llms_order_id, '_llms_coupon_code', true );
			$coupon_code   = '' !== $code ? $code : null;
			$coupon_amount = (float) get_post_meta( $llms_order_id, '_llms_coupon_value', true );
			if ( $coupon_amount <= 0 ) {
				$base = ( null !== $sale_price && $sale_price > 0 ) ? $sale_price : $original;
				if ( $base > $total ) {
					$coupon_amount = $base - $total;
				}
			}
			$coupon_amount = max( 0.0, $coupon_amount );
		}

		return array(
			array(
				'llms_order_id'    => $llms_order_id,
				'order_type'       => OrderModel::TYPE_SUBSCRIPTION,
				'parent_id'        => 0,
				'transaction_id'   => $txn_id,
				'user_id'          => $user_id,
				'order_status'     => OrderModel::ORDER_COMPLETED,
				'payment_status'   => OrderModel::PAYMENT_PAID,
				'subtotal_price'   => $original,
				'pre_tax_price'    => $total,
				'tax_type'         => null,
				'tax_rate'         => 0,
				'tax_amount'       => 0,
				'total_price'      => $total,
				'net_payment'      => $total,
				'coupon_code'      => $coupon_code,
				'coupon_amount'    => $coupon_amount,
				'discount_amount'  => 0,
				'discount_type'    => null,
				'discount_reason'  => '',
				'fees'             => 0,
				'refund_amount'    => 0,
				'earnings'         => $total,
				'payment_method'   => PaymentMethodMapper::map( $gateway, $total ),
				'payment_payloads' => wp_json_encode(
					array(
						'source'        => 'lifterlms',
						'llms_order_id' => $llms_order_id,
						'llms_gateway'  => $gateway,
					)
				),
				'note'             => sprintf(
					/* translators: %d: LifterLMS order ID */
					__( 'Order migrated from LifterLMS subscription #%d', 'tutor-lms-migration-tool' ),
					$llms_order_id
				),
				'created_by'       => $user_id,
				'updated_by'       => $user_id,
				'created_at_gmt'   => $created_gmt ? $created_gmt : gmdate( 'Y-m-d H:i:s' ),
				'updated_at_gmt'   => $updated_gmt ? $updated_gmt : gmdate( 'Y-m-d H:i:s' ),
				'items'            => array(
					array(
						'item_id'       => (int) $plan->id,
						'regular_price' => (float) $plan->regular_price,
						'sale_price'    => $sale_price,
					),
				),
				'meta_data'        => $this->prepare_meta_data( $post, $plan, $user_id, $created_gmt ),
				'tutor_plan_id'    => (int) $plan->id,
			),
		);
	}

	/**
	 * Prepare order meta rows.
	 *
	 * @since 2.6.0
	 *
	 * @param \WP_Post $order      Lifter order post.
	 * @param object   $tutor_plan Tutor plan row.
	 * @param int      $user_id    User ID.
	 * @param string   $gmt_now    GMT datetime.
	 *
	 * @return array
	 */
	private function prepare_meta_data( $order, $tutor_plan, int $user_id, string $gmt_now ): array {
		if ( empty( $gmt_now ) ) {
			$gmt_now = gmdate( 'Y-m-d H:i:s' );
		}

		$user  = get_userdata( $user_id );
		$first = (string) get_post_meta( $order->ID, '_llms_billing_first_name', true );
		$last  = (string) get_post_meta( $order->ID, '_llms_billing_last_name', true );
		$email = (string) get_post_meta( $order->ID, '_llms_billing_email', true );

		if ( $user ) {
			if ( '' === $first ) {
				$first = (string) $user->first_name;
			}
			if ( '' === $last ) {
				$last = (string) $user->last_name;
			}
			if ( '' === $email ) {
				$email = (string) $user->user_email;
			}
		}

		$billing_data = array(
			'id'                 => $user_id,
			'user_id'            => $user_id,
			'billing_first_name' => $first,
			'billing_last_name'  => $last,
			'billing_email'      => $email,
			'billing_phone'      => (string) get_post_meta( $order->ID, '_llms_billing_phone', true ),
			'billing_zip_code'   => (string) get_post_meta( $order->ID, '_llms_billing_zip', true ),
			'billing_address'    => trim(
				(string) get_post_meta( $order->ID, '_llms_billing_address_1', true ) . ' ' .
				(string) get_post_meta( $order->ID, '_llms_billing_address_2', true )
			),
			'billing_country'    => (string) get_post_meta( $order->ID, '_llms_billing_country', true ),
			'billing_state'      => (string) get_post_meta( $order->ID, '_llms_billing_state', true ),
			'billing_city'       => (string) get_post_meta( $order->ID, '_llms_billing_city', true ),
		);

		return array(
			array(
				'meta_key'       => OrderModel::META_KEY_BILLING_ADDRESS,
				'meta_value'     => wp_json_encode( $billing_data, JSON_UNESCAPED_UNICODE ),
				'created_at_gmt' => $gmt_now,
				'updated_at_gmt' => $gmt_now,
				'created_by'     => $user_id,
				'updated_by'     => $user_id,
			),
			array(
				'meta_key'       => OrderModel::META_PLAN_INFO,
				'meta_value'     => maybe_serialize( $tutor_plan ),
				'created_at_gmt' => $gmt_now,
				'updated_at_gmt' => $gmt_now,
				'created_by'     => $user_id,
				'updated_by'     => $user_id,
			),
		);
	}
}
