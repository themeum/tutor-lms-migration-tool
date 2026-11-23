<?php
/**
 * Map LifterLMS payment gateways to Tutor native payment method slugs.
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
 * Payment method mapper for Lifter → Tutor native orders.
 *
 * @since 2.6.0
 */
class PaymentMethodMapper {

	/**
	 * Map Lifter gateway id to a Tutor payment_method slug.
	 *
	 * @since 2.6.0
	 *
	 * @param string $llms_gateway Lifter `_llms_payment_gateway` value.
	 * @param float  $total_price  Order total (0 → free).
	 *
	 * @return string
	 */
	public static function map( string $llms_gateway, float $total_price = 0.0 ): string {
		if ( $total_price <= 0 ) {
			return OrderModel::PAYMENT_METHOD_FREE;
		}

		$llms_gateway = strtolower( trim( $llms_gateway ) );

		if ( '' === $llms_gateway ) {
			return OrderModel::PAYMENT_METHOD_MANUAL;
		}

		$map = array(
			'manual'            => OrderModel::PAYMENT_METHOD_MANUAL,
			'manual_payment'    => OrderModel::PAYMENT_METHOD_MANUAL,
			'free'              => OrderModel::PAYMENT_METHOD_FREE,
			'paypal'            => 'paypal',
			'llms-paypal'       => 'paypal',
			'stripe'            => 'stripe',
			'llms-stripe'       => 'stripe',
			'woocommerce'       => OrderModel::PAYMENT_METHOD_MANUAL,
			'lifterlms-stripe'  => 'stripe',
			'lifterlms-paypal'  => 'paypal',
		);

		/**
		 * Filter Lifter → Tutor payment method slug map.
		 *
		 * @since 2.6.0
		 *
		 * @param array  $map          Method map.
		 * @param string $llms_gateway Original Lifter gateway.
		 */
		$map = apply_filters( 'tlmt_lif_native_payment_method_map', $map, $llms_gateway );

		if ( isset( $map[ $llms_gateway ] ) ) {
			return (string) $map[ $llms_gateway ];
		}

		return sanitize_key( $llms_gateway );
	}
}
