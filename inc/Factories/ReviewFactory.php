<?php
/**
 * Review Factory class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Reviews;
use Themeum\TutorLMSMigrationTool\LDMigration\Reviews\LDReviews;
use Themeum\TutorLMSMigrationTool\MigrationTypes;

/**
 * Create review migration objects based on migration type.
 */
abstract class ReviewFactory {
	/**
	 * Create review migration objects based on migration type.
	 *
	 * @since 2.3.0
	 *
	 * @param string $migration_type type of migration (LD to Tutor etc).
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return Reviews
	 */
	public static function create( string $migration_type ): Reviews {
		switch ( $migration_type ) {
			case MigrationTypes::LD_TO_TUTOR:
				return new LDReviews();
			default:
				break;
		}

		throw new InvalidArgumentException( __( 'Invalid argument passed', 'tutor-lms-migration-tool' ) );
	}
}
