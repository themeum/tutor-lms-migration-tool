<?php
/**
 * Sensei Order Payment Method Mapper.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration\Orders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps payment methods from WooCommerce / Sensei to Tutor LMS.
 */
class PaymentMethodMapper {

	/**
	 * Map payment method title or slug.
	 *
	 * @param string $method Payment method slug or title.
	 * @return string Mapped payment method name.
	 */
	public static function map( string $method ): string {
		$clean_method = strtolower( trim( $method ) );

		switch ( $clean_method ) {
			case 'stripe':
			case 'stripe_cc':
				return 'Stripe';

			case 'paypal':
			case 'ppec_paypal':
				return 'PayPal';

			case 'bacs':
				return 'Bank Transfer';

			case 'cheque':
				return 'Cheque';

			case 'cod':
				return 'Cash on Delivery';

			default:
				return ! empty( $method ) ? ucwords( str_replace( array( '_', '-' ), ' ', $method ) ) : 'WooCommerce';
		}
	}
}
