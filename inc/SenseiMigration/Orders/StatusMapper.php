<?php
/**
 * Sensei Order Status Mapper.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration\Orders;

use Tutor\Models\OrderModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps WooCommerce order statuses to Tutor LMS order_status + payment_status pairs.
 */
class StatusMapper {

	/**
	 * Map a WooCommerce post_status to Tutor order_status + payment_status.
	 *
	 * @param string $wc_status WooCommerce post status (e.g. wc-completed).
	 * @return array{order_status: string, payment_status: string}
	 */
	public static function map( string $wc_status ): array {
		$clean = strtolower( trim( str_replace( 'wc-', '', $wc_status ) ) );

		switch ( $clean ) {
			case 'completed':
				return array(
					'order_status'   => OrderModel::ORDER_COMPLETED,
					'payment_status' => OrderModel::PAYMENT_PAID,
				);

			case 'processing':
			case 'on-hold':
				return array(
					'order_status'   => OrderModel::ORDER_INCOMPLETE,
					'payment_status' => OrderModel::PAYMENT_PAID,
				);

			case 'pending':
				return array(
					'order_status'   => OrderModel::ORDER_PENDING,
					'payment_status' => OrderModel::PAYMENT_UNPAID,
				);

			case 'refunded':
				return array(
					'order_status'   => OrderModel::ORDER_COMPLETED,
					'payment_status' => OrderModel::PAYMENT_REFUNDED,
				);

			case 'cancelled':
			case 'failed':
				return array(
					'order_status'   => OrderModel::ORDER_CANCELLED,
					'payment_status' => OrderModel::PAYMENT_FAILED,
				);

			default:
				return array(
					'order_status'   => OrderModel::ORDER_COMPLETED,
					'payment_status' => OrderModel::PAYMENT_PAID,
				);
		}
	}
}
