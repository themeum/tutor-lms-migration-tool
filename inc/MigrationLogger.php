<?php
/**
 * Handle migration status
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool;

/**
 * Migration status handler class
 */
class MigrationLogger {

	/**
	 * Option name for storing migration status
	 *
	 * @since 2.3.0
	 *
	 * @var string
	 */
	const MIGRATION_STATUS_OPT_NAME = 'tlmt_migration_status';

	/**
	 * Update course migration status
	 *
	 * @since 2.3.0
	 *
	 * @param integer $course_id Course id.
	 * @param bool    $is_success True|false.
	 *
	 * @return void
	 */
	public static function update_course_migration_status( int $course_id, bool $is_success ) {
		$migration_info = get_option( self::MIGRATION_STATUS_OPT_NAME );

		if ( ! empty( $migration_info ) ) {
			if ( $is_success ) {
				$migration_info['success'][] = $course_id;
			} else {
				$migration_info['failed'][] = $course_id;
			}
		}

		update_option( self::MIGRATION_STATUS_OPT_NAME, maybe_serialize( $migration_info ) );
	}

	/**
	 * Update migration status
	 *
	 * Initial status store when migration start
	 *
	 * @since 2.3.0
	 *
	 * @param integer $total_course_count Total course count for migration.
	 *
	 * @return void
	 */
	public static function update_migration_status( int $total_course_count ) {
		$migration_info = array(
			'total_course_count' => $total_course_count,
			'success'            => array(),
			'failed'             => array(),
		);

		update_option( self::MIGRATION_STATUS_OPT_NAME, maybe_serialize( $migration_info ) );
	}

	/**
	 * Get migration status
	 *
	 * @since 2.3.0
	 *
	 * @return object
	 */
	public static function get_status() {
		$status = get_option( self::MIGRATION_STATUS_OPT_NAME );
		if ( ! $status ) {
			return (object) array();
		}

		$status['errors'] = (object) ErrorHandler::get_errors();
		return (object) $status;
	}
}
