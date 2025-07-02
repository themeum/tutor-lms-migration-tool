<?php
/**
 * Order Factory class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\Interfaces\Order;
use Themeum\TutorLMSMigrationTool\LDMigration\Order\EDDOrder as LD_EDD_Order;
use Themeum\TutorLMSMigrationTool\LDMigration\Order\TutorOrder as LD_Tutor_Order;
use Themeum\TutorLMSMigrationTool\LDMigration\Order\WCOrder as LD_WC_Order;
use Themeum\TutorLMSMigrationTool\MigrationTypes;

/**
 * OrderFactory class which gives order migration object based on monetization type.
 */
abstract class OrderFactory {
	/**
	 * Create review migration objects based on migration type.
	 *
	 * @since 2.3.0
	 *
	 * @param string $monetization_type the type of monetization.
	 * @param string $migration_type type of migration (LD to Tutor etc).
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return Order
	 */
	public static function create( string $monetization_type, string $migration_type ): Order {
		switch ( $migration_type ) {
			case MigrationTypes::LD_TO_TUTOR:
				if ( 'tutor' === $monetization_type || $monetization_type == '-1' || $monetization_type == 'free' ) {
					return new LD_Tutor_Order();
				}

				if ( tutor_utils()->has_edd() && 'edd' === $monetization_type ) {
					return new LD_EDD_Order();
				}

				if ( tutor_utils()->has_wc() && 'wc' === $monetization_type ) {
					return new LD_WC_Order();
				}

				break;
			default:
				break;
		}

		throw new InvalidArgumentException( __( 'Invalid argument passed', 'tutor-lms-migration-tool' ) );
	}
}
