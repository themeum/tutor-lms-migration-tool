<?php
/**
 * Map LearnPress order statuses to Tutor native ecommerce statuses.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LPMigration\Orders;

use Tutor\Models\OrderModel;

defined( 'ABSPATH' ) || exit;

/**
 * Status mapper for LP → Tutor native orders.
 *
 * @since 2.5.0
 */
class StatusMapper {

	/**
	 * Map an LP post_status to Tutor order_status + payment_status.
	 *
	 * @since 2.5.0
	 *
	 * @param string $lp_status LP post status (e.g. lp-completed).
	 *
	 * @return array{order_status: string, payment_status: string}
	 */
	public static function map( string $lp_status ): array {
		$lp_status = strtolower( trim( $lp_status ) );

		switch ( $lp_status ) {
			case 'lp-completed':
				return array(
					'order_status'   => OrderModel::ORDER_COMPLETED,
					'payment_status' => OrderModel::PAYMENT_PAID,
				);

			case 'lp-pending':
				return array(
					'order_status'   => OrderModel::ORDER_PENDING,
					'payment_status' => OrderModel::PAYMENT_UNPAID,
				);

			case 'lp-processing':
				return array(
					'order_status'   => OrderModel::ORDER_INCOMPLETE,
					'payment_status' => OrderModel::PAYMENT_PENDING,
				);

			case 'lp-cancelled':
				return array(
					'order_status'   => OrderModel::ORDER_CANCELLED,
					'payment_status' => OrderModel::PAYMENT_FAILED,
				);

			case 'lp-failed':
				return array(
					'order_status'   => OrderModel::ORDER_CANCELLED,
					'payment_status' => OrderModel::PAYMENT_FAILED,
				);

			case 'lp-refunded':
				return array(
					'order_status'   => OrderModel::ORDER_CANCELLED,
					'payment_status' => OrderModel::PAYMENT_REFUNDED,
				);

			case 'trash':
			case 'lp-trash':
				return array(
					'order_status'   => OrderModel::ORDER_TRASH,
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
	 * LP post statuses included in native order migration.
	 *
	 * @since 2.5.0
	 *
	 * @return string[]
	 */
	public static function migratable_lp_statuses(): array {
		return array(
			'lp-completed',
			'lp-pending',
			'lp-processing',
			'lp-cancelled',
			'lp-failed',
			'lp-refunded',
		);
	}
}
