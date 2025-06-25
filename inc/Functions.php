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

if ( ! function_exists( 'get_media_ids_from_content' ) ) {
	/**
	 * Get media ids from given content
	 *
	 * @since 2.3.0
	 *
	 * @param string $content Content string.
	 *
	 * @return array
	 */
	function get_media_ids_from_content( $content = '' ) {
		if ( empty( $content ) ) {
			return array();
		}

		$media_ids = array();

		// Match all image tags and extract src URLs.
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $image_url ) {
				// Try to get attachment ID from URL.
				$attachment_id = attachment_url_to_postid( $image_url );
				if ( $attachment_id ) {
					$media_ids[] = $attachment_id;
				}
			}
		}

		return $media_ids;
	}
}



