<?php
/**
 * Register hooks to take actions
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool;

use Themeum\TutorLMSMigrationTool\Factories\StudentProgressFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action hook handler
 */
class ActionHandler {

	/**
	 * Migration error option name
	 *
	 * @since 2.3.0
	 */
	const MIGRATION_ERR_OPT_NAME = 'tlmt_migration_error';

	/**
	 * Register hooks
	 */
	public function __construct() {
		add_action( 'tlmt_course_migrated', array( $this, 'migrate_course_meta' ), 10, 2 );
		add_action( 'tlmt_student_progress_migrated', array( $this, 'migrate_student_progress' ));
	}

	/**
	 * Migrate course meta
	 *
	 * @since 2.3.0
	 *
	 * @param int    $course_id Course id.
	 * @param string $migration_type Migration type.
	 *
	 * @return void
	 */
	public function migrate_course_meta( $course_id, $migration_type ) {
		try {
			$course          = get_post( $course_id );
			$course_meta_obj = tlmt_get_meta_obj( ContentTypes::COURSE_META, $migration_type );

			try {
				$course_meta_obj->migrate( $course_id );
			} catch ( \Throwable $th ) {
				$this->update_migration_error( 'course_meta', "Failed to create meta data for this course: $course->post_title " );
			}
		} catch ( \Throwable $th ) {
			$this->update_migration_error( 'course_meta', "Failed to create meta data for this course: $course->post_title " );
		}
	}

	/**
	 * Update migration error message
	 *
	 * @since 2.3.0
	 *
	 * @param string $key Error key.
	 * @param string $error_msg Error message.
	 *
	 * @return void
	 */
	public function update_migration_error( string $key, string $error_msg ) {
		$error_data         = get_option( self::MIGRATION_ERR_OPT_NAME );
		$error_data[ $key ] = $error_msg;

		update_option( self::MIGRATION_ERR_OPT_NAME, maybe_serialize( $error_data ) );
	}

	public function migrate_student_progress( string $migration_type ) {
		try {
			$student_progress_obj = StudentProgressFactory::create( $migration_type );
			$student_progress_obj->migrate();
		} catch ( \Throwable $th ) {
			$this->update_migration_error( ContentTypes::STUDENT_PROGRESS, "Failed to migrate student progress. " . $th->getMessage() );
		}
	}
}
