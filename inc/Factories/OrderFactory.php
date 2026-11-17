<?php
/**
 * Order Factory.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\Interfaces\Order;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SenseiMigration\Orders\OrderMigrator as SenseiOrderMigrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates Order migrator objects based on migration type.
 */
abstract class OrderFactory {

	/**
	 * Create Order instance.
	 *
	 * @param string $migration_type Migration type.
	 * @throws InvalidArgumentException If invalid type provided.
	 * @return Order
	 */
	public static function create( string $migration_type ): Order {
		switch ( $migration_type ) {
			case MigrationTypes::SENSEI_TO_TUTOR:
				return new SenseiOrderMigrator();

			default:
				break;
		}

		throw new InvalidArgumentException( __( 'Invalid migration type for orders', 'tutor-lms-migration-tool' ) );
	}
}
