<?php
/**
 * Review Factory.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\Interfaces\Review;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SenseiMigration\Reviews as SenseiReviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates Review objects based on migration type.
 */
abstract class ReviewFactory {

	/**
	 * Create Review instance.
	 *
	 * @param string $migration_type Migration type.
	 * @throws InvalidArgumentException If invalid type provided.
	 * @return Review
	 */
	public static function create( string $migration_type ): Review {
		switch ( $migration_type ) {
			case MigrationTypes::SENSEI_TO_TUTOR:
				return new SenseiReviews();

			default:
				break;
		}

		throw new InvalidArgumentException( esc_html__( 'Invalid migration type for reviews', 'tutor-lms-migration-tool' ) );
	}
}
