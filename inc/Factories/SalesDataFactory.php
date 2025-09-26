<?php
/**
 * Sale Data Factory class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Coupons\Coupons;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Customers\Customers;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Orders\Orders;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Subscriptions;
use Themeum\TutorLMSMigrationTool\SalesDataTypes;

/**
 * OrderFactory class which gives order migration object based on monetization type.
 */
abstract class SalesDataFactory {

	/**
	 * Create object for sales data
	 *
	 * @since 2.4.0
	 *
	 * @param string $data_type   Type of content (order, subscription etc).
	 * @param string $migration_type Type of migration (wc_to_native).
	 *
	 * @throws InvalidArgumentException If invalid argument passed.
	 *
	 * @return MigrationTemplate
	 */
	public static function create( string $data_type, string $migration_type ): MigrationTemplate {
		switch ( $migration_type ) {
			case MigrationTypes::WC_TO_NATIVE:
				if ( SalesDataTypes::ORDERS === $data_type ) {
					return new Orders();
				} elseif ( SalesDataTypes::SUBSCRIPTIONS === $data_type ) {
					return new Subscriptions();
				} elseif ( SalesDataTypes::CUSTOMERS === $data_type ) {
					return new Customers();
				} elseif ( SalesDataTypes::COUPONS === $data_type ) {
					return new Coupons();
				}
				break;
			default:
				break;
		}

		throw new InvalidArgumentException( __( 'Invalid argument passed', 'tutor-lms-migration-tool' ) );
	}
}
