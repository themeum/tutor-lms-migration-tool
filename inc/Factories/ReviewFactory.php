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
use Themeum\TutorLMSMigrationTool\Interfaces\Review;
use Themeum\TutorLMSMigrationTool\LDMigration\Reviews as LDReviews;
use Themeum\TutorLMSMigrationTool\LIFMigration\Reviews as LIFReviews;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SenseiMigration\Reviews as SenseiReviews;

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
	 * @throws InvalidArgumentException If invalid migration type provided.
	 *
	 * @return Review
	 */
	public static function create( string $migration_type ): Review {
		switch ( $migration_type ) {
			case MigrationTypes::LD_TO_TUTOR:
				return new LDReviews();
			case MigrationTypes::LIF_TO_TUTOR:
				return new LIFReviews();
			case MigrationTypes::SENSEI_TO_TUTOR:
				return new SenseiReviews();
			default:
				break;
		}

		throw new InvalidArgumentException( esc_html__( 'Invalid argument passed', 'tutor-lms-migration-tool' ) );
	}
}
