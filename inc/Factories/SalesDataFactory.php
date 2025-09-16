<?php
/**
 * Sale Data Factory class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Orders\Order;

/**
 * OrderFactory class which gives order migration object based on monetization type.
 */
abstract class SalesDataFactory {

	/**
	 * Create object for sales data
	 *
	 * @since 2.3.0
	 *
	 * @param string $content_type   Type of content (order, subscription etc).
	 * @param string $migration_type Type of migration (wc_to_native).
	 *
	 * @throws InvalidArgumentException If invalid argument passed.
	 *
	 * @return MigrationTemplate
	 */
	public static function create( string $content_type, string $migration_type ): MigrationTemplate {
		switch ( $migration_type ) {
			case MigrationTypes::WC_TO_NATIVE:
				if ( ContentTypes::SALES_ORDER === $content_type ) {
					return new Order();
				}
				break;
			default:
				break;
		}

		throw new InvalidArgumentException( __( 'Invalid argument passed', 'tutor-lms-migration-tool' ) );
	}
}
