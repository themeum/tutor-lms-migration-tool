<?php
/**
 * Map LearnPress payment gateways to Tutor native payment method slugs.
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
 * Payment method mapper for LP → Tutor native orders.
 *
 * @since 2.5.0
 */
class PaymentMethodMapper {

	/**
	 * Map LP gateway id to a Tutor payment_method slug.
	 *
	 * @since 2.5.0
	 *
	 * @param string $lp_method   LP `_payment_method` value.
	 * @param float  $total_price Order total (0 → free).
	 *
	 * @return string
	 */
	public static function map( string $lp_method, float $total_price = 0.0 ): string {
		if ( $total_price <= 0 ) {
			return OrderModel::PAYMENT_METHOD_FREE;
		}

		$lp_method = strtolower( trim( $lp_method ) );

		if ( '' === $lp_method ) {
			return OrderModel::PAYMENT_METHOD_MANUAL;
		}

		$map = array(
			'paypal'           => 'paypal',
			'offline-payment'  => OrderModel::PAYMENT_METHOD_MANUAL,
			'offline_payment'  => OrderModel::PAYMENT_METHOD_MANUAL,
			'manual'           => OrderModel::PAYMENT_METHOD_MANUAL,
			'free'             => OrderModel::PAYMENT_METHOD_FREE,
			'stripe'           => 'stripe',
		);

		/**
		 * Filter LP → Tutor payment method slug map.
		 *
		 * @since 2.5.0
		 *
		 * @param array  $map       Method map.
		 * @param string $lp_method Original LP method.
		 */
		$map = apply_filters( 'tlmt_lp_native_payment_method_map', $map, $lp_method );

		if ( isset( $map[ $lp_method ] ) ) {
			return (string) $map[ $lp_method ];
		}

		// Preserve unknown addon gateway slugs for auditability.
		return sanitize_key( $lp_method );
	}
}
