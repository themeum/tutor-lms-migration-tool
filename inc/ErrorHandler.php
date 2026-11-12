<?php
/**
 * Error Handler
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool;

/**
 * Error handler class
 */
class ErrorHandler {

	/**
	 * Migration error option name
	 *
	 * @since 2.3.0
	 */
	const MIGRATION_ERR_OPT_NAME = 'tlmt_migration_error';

	/**
	 * Error handler
	 *
	 * @since 2.3.0
	 *
	 * @param string $error_type Error type.
	 * @param string $err_msg Error message.
	 *
	 * @return void
	 */
	public static function set_error( string $error_type, string $err_msg ) {
		$error_data = maybe_unserialize( get_option( self::MIGRATION_ERR_OPT_NAME ) );
		$error_data = is_array( $error_data ) ? $error_data : array();

		if ( isset( $error_data[ $error_type ] ) ) {
			$error_data[ $error_type ][] = $err_msg;
		} else {
			$error_data[ $error_type ] = array( $err_msg );
		}

		update_option( self::MIGRATION_ERR_OPT_NAME, maybe_serialize( $error_data ), false );
	}

	/**
	 * Get all the errors
	 *
	 * @since 2.3.0
	 *
	 * @param bool $clear_errors Whether to clear the message or not.
	 *
	 * @return array
	 */
	public static function get_errors( bool $clear_errors = true ): array {
		$errors = maybe_unserialize( get_option( self::MIGRATION_ERR_OPT_NAME ) );

		if ( $clear_errors ) {
			update_option( self::MIGRATION_ERR_OPT_NAME, '', false );
		}

		return is_array( $errors ) ? $errors : array();
	}

	/**
	 * Get the latest error message for a type.
	 *
	 * @since 2.5.0
	 *
	 * @param string $error_type Error type key.
	 *
	 * @return string
	 */
	public static function get_error_message( string $error_type ): string {
		$errors = self::get_errors( false );

		if ( empty( $errors[ $error_type ] ) || ! is_array( $errors[ $error_type ] ) ) {
			return __( 'Migration step failed.', 'tutor-lms-migration-tool' );
		}

		return (string) end( $errors[ $error_type ] );
	}

	/**
	 * Get all the errors
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public static function clear_errors() {
		delete_option( self::MIGRATION_ERR_OPT_NAME );
	}
}
