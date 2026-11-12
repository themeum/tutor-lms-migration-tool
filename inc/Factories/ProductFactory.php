<?php
/**
 * Product Factory class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */
namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\Interfaces\Product;
use Themeum\TutorLMSMigrationTool\LDMigration\Product\EDDProduct as LD_EDD_Product;
use Themeum\TutorLMSMigrationTool\LDMigration\Product\TutorProduct as LD_Tutor_Product;
use Themeum\TutorLMSMigrationTool\LDMigration\Product\WCProduct as LD_WC_Product;
use Themeum\TutorLMSMigrationTool\LIFMigration\Product\NativePricing as LIF_Tutor_Product;
use Themeum\TutorLMSMigrationTool\MigrationTypes;

/**
 * Provides product object for migration.
 */
abstract class ProductFactory {

	/**
	 * Create product objects based on migration and monetization type.
	 *
	 * @since 2.3.0
	 * @since 2.6.0 Added LifterLMS native Tutor product migration.
	 *
	 * @param string $monetization_type the monetization type such as tutor, wc etc.
	 * @param string $migration_type    the migration type such as ld_to_tutor.
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return Product
	 */
	public static function create( string $monetization_type, string $migration_type ): Product {
		switch ( $migration_type ) {
			case MigrationTypes::LD_TO_TUTOR:
				if ( tutor_utils()->has_edd() && 'edd' === $monetization_type ) {
					return new LD_EDD_Product();
				}
				if ( tutor_utils()->has_wc() && 'wc' === $monetization_type ) {
					return new LD_WC_Product();
				}
				if ( 'tutor' === $monetization_type ) {
					return new LD_Tutor_Product();
				}
				break;
			case MigrationTypes::LIF_TO_TUTOR:
				if ( 'tutor' === $monetization_type ) {
					return new LIF_Tutor_Product();
				}
				break;
			default:
				break;
		}
		throw new InvalidArgumentException( __( 'Invalid argument passed', 'tutor-lms-migration-tool' ) );
	}
}
