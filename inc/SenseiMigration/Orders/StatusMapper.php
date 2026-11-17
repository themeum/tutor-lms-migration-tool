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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps WooCommerce / Sensei order statuses to Tutor LMS order statuses.
 */
class StatusMapper {

	/**
	 * Map WooCommerce order status to Tutor status.
	 *
	 * @param string $source_status WooCommerce or Sensei status.
	 * @return string Mapped Tutor order status.
	 */
	public static function map( string $source_status ): string {
		$clean_status = str_replace( 'wc-', '', strtolower( trim( $source_status ) ) );

		switch ( $clean_status ) {
			case 'completed':
				return 'completed';

			case 'processing':
			case 'on-hold':
				return 'processing';

			case 'pending':
				return 'pending';

			case 'cancelled':
			case 'refunded':
			case 'failed':
				return 'cancelled';

			default:
				return 'completed';
		}
	}
}
