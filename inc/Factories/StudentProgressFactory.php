<?php
/**
 * PostMeta Factory
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\LDMigration\StudentProgress;
use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;

/**
 * Create the post meta objects based on migration type and meta type
 */
abstract class StudentProgressFactory {

	/**
	 * Creates student progress objects based on migration type
	 *
	 * @since 2.3.0
	 *
	 * @param string $migration_type Type of migration.
	 *
	 * @throws InvalidArgumentException If invalid migration type provided.
	 *
	 * @return StudentProgressInterface Student progress object for the specified type
	 */
	public static function create( $migration_type ): StudentProgressInterface {
		switch ( $migration_type ) {
			case MigrationTypes::LD_TO_TUTOR:
				return new StudentProgress();
			default:
				break;
		}

		throw new InvalidArgumentException( __( 'Invalid argument passed', 'tutor-lms-migration-tool' ) ); //phpcs:ignore
	}
}
