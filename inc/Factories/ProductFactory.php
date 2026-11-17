<?php
/**
 * Product Factory.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\Interfaces\Product;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SenseiMigration\Product\NativePricing;
use Themeum\TutorLMSMigrationTool\SenseiMigration\Product\WCProduct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates Product monetization objects based on migration and monetization type.
 */
abstract class ProductFactory {

	/**
	 * Create Product instance.
	 *
	 * @param string $migration_type Migration type.
	 * @param string $monetize_by    Monetization method ('wc' or 'native').
	 * @throws InvalidArgumentException If invalid type provided.
	 * @return Product
	 */
	public static function create( string $migration_type, string $monetize_by = 'wc' ): Product {
		switch ( $migration_type ) {
			case MigrationTypes::SENSEI_TO_TUTOR:
				if ( 'native' === $monetize_by ) {
					return new NativePricing();
				}
				return new WCProduct();

			default:
				break;
		}

		throw new InvalidArgumentException( esc_html__( 'Invalid migration type for product', 'tutor-lms-migration-tool' ) );
	}
}
