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
use Themeum\TutorLMSMigrationTool\LDMigration\StudentProgress as LDStudentProgress;
use Themeum\TutorLMSMigrationTool\LIFMigration\StudentProgress as LIFStudentProgress;
use Themeum\TutorLMSMigrationTool\LPMigration\StudentProgress as LPStudentProgress;
use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;

/**
 * Create the post meta objects based on migration type and meta type
 */
abstract class StudentProgressFactory {

	/**
	 * Creates student progress objects based on migration type
	 *
	 * @since 2.3.0
	 * @since 2.5.0 Added LearnPress student progress migration.
	 * @since 2.5.0 Added LifterLMS student progress migration.
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
				return new LDStudentProgress();
			case MigrationTypes::LP_TO_TUTOR:
				return new LPStudentProgress();
			case MigrationTypes::LIF_TO_TUTOR:
				return new LIFStudentProgress();
			default:
				break;
		}

		throw new InvalidArgumentException( __( 'Invalid argument passed', 'tutor-lms-migration-tool' ) ); //phpcs:ignore
	}
}
