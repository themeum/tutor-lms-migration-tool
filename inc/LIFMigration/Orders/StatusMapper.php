<?php
/**
 * Map LifterLMS order statuses to Tutor native ecommerce statuses.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration\Orders;

use Tutor\Models\OrderModel;

defined( 'ABSPATH' ) || exit;

/**
 * Status mapper for Lifter → Tutor native one-time orders.
 *
 * @since 2.6.0
 */
class StatusMapper {

	/**
	 * Map a Lifter post_status to Tutor order_status + payment_status.
	 *
	 * @since 2.6.0
	 *
	 * @param string $llms_status Lifter post status (e.g. llms-completed).
	 *
	 * @return array{order_status: string, payment_status: string}
	 */
	public static function map( string $llms_status ): array {
		$llms_status = strtolower( trim( $llms_status ) );

		switch ( $llms_status ) {
			case 'llms-completed':
				return array(
					'order_status'   => OrderModel::ORDER_COMPLETED,
					'payment_status' => OrderModel::PAYMENT_PAID,
				);

			case 'llms-pending':
				return array(
					'order_status'   => OrderModel::ORDER_PENDING,
					'payment_status' => OrderModel::PAYMENT_UNPAID,
				);

			case 'llms-failed':
				return array(
					'order_status'   => OrderModel::ORDER_CANCELLED,
					'payment_status' => OrderModel::PAYMENT_FAILED,
				);

			case 'llms-refunded':
				return array(
					'order_status'   => OrderModel::ORDER_COMPLETED,
					'payment_status' => OrderModel::PAYMENT_REFUNDED,
				);

			case 'llms-cancelled':
				return array(
					'order_status'   => OrderModel::ORDER_CANCELLED,
					'payment_status' => OrderModel::PAYMENT_UNPAID,
				);

			default:
				return array(
					'order_status'   => OrderModel::ORDER_INCOMPLETE,
					'payment_status' => OrderModel::PAYMENT_UNPAID,
				);
		}
	}

	/**
	 * Lifter post statuses included in one-time native order migration.
	 *
	 * @since 2.6.0
	 *
	 * @return string[]
	 */
	public static function migratable_single_statuses(): array {
		return array(
			'llms-completed',
			'llms-pending',
			'llms-failed',
			'llms-refunded',
			'llms-cancelled',
		);
	}
}
