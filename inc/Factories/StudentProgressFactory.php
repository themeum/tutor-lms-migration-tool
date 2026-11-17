<?php
/**
 * StudentProgress Factory.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SenseiMigration\StudentProgress as SenseiStudentProgress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates student progress objects based on migration type.
 */
abstract class StudentProgressFactory {

	/**
	 * Create student progress instance.
	 *
	 * @param string $migration_type Migration type.
	 * @throws InvalidArgumentException If invalid migration type provided.
	 * @return StudentProgress
	 */
	public static function create( string $migration_type ): StudentProgress {
		switch ( $migration_type ) {
			case MigrationTypes::SENSEI_TO_TUTOR:
				return new SenseiStudentProgress();

			default:
				break;
		}

		throw new InvalidArgumentException( esc_html__( 'Invalid migration type for student progress', 'tutor-lms-migration-tool' ) );
	}
}
