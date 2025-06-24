<?php
/**
 * Helper functions
 *
 * Facades of complex logics
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

use Themeum\TutorLMSMigrationTool\Factories\PostMetaFactory;

if ( ! function_exists( 'tlmt_has_tutor_pro' ) ) {
	/**
	 * Check whether tutor pro is installed or not
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	function tlmt_has_tutor_pro() {
		return function_exists( 'tutor_pro' );
	}
}

if ( ! function_exists( 'tlmt_get_meta_obj' ) ) {
	/**
	 * Check whether tutor pro is installed or not
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Meta type like: course, lesson, etc.
	 * @param string $migration_type Migration type like: ld_to_tutor.
	 *
	 * @see MigrationTypes & ContentTypes class
	 *
	 * @throws \Throwable If the migration type is not supported.
	 *
	 * @return PostMeta object
	 */
	function tlmt_get_meta_obj( $meta_type, $migration_type ) {
		try {
			$obj = PostMetaFactory::create( $meta_type, $migration_type );
			return $obj;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}


