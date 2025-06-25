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
use Themeum\TutorLMSMigrationTool\LDMigration\Order\TutorOrder;
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
				if ( 'tutor' === $monetization_type ) {
					return new TutorOrder();
				}
			default:
				break;
		}

		throw new InvalidArgumentException( __( 'Invalid argument passed', 'tutor-lms-migration-tool' ) );
	}
}
