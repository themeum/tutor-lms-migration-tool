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

use Themeum\TutorLMSMigrationTool\Factories\OrderFactory;
use Themeum\TutorLMSMigrationTool\Factories\PostMetaFactory;
use Themeum\TutorLMSMigrationTool\Factories\ProductFactory;
use Themeum\TutorLMSMigrationTool\Factories\ReviewFactory;

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

if ( ! function_exists( 'tlmt_is_multi_dim_arr' ) ) {
	/**
	 * Check whether tutor pro is installed or not
	 *
	 * @since 2.3.0
	 *
	 * @param array $arr Array to check.
	 *
	 * @return bool
	 */
	function tlmt_is_multi_dim_arr( $arr ) {
		if ( ! is_array( $arr ) ) {
			return false;
		}

		return is_array( $arr[0] );
	}
}



if ( ! function_exists( 'tlmt_get_review_obj' ) ) {

	/**
	 * Obtain a review migration class object.
	 *
	 * @since 2.3.0
	 *
	 * @param string $migration_type the migration type.
	 *
	 * @throws \Throwable if migration type is not supported.
	 *
	 * @return Review obj
	 */
	function tlmt_get_review_obj( $migration_type ) {
		try {
			$obj = ReviewFactory::create( $migration_type );
			return $obj;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}

if ( ! function_exists( 'tlmt_get_order_obj' ) ) {

	/**
	 * Obtain a order migration class object.
	 *
	 * @since 2.3.0
	 *
	 * @param string $monetization_type the monetization type.
	 * @param string $migration_type the migration type.
	 *
	 * @throws \Throwable if migration type is not supported.
	 *
	 * @return Order obj
	 */
	function tlmt_get_order_obj( $monetization_type, $migration_type ) {
		try {
			$obj = OrderFactory::create( $monetization_type, $migration_type );
			return $obj;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}


if ( ! function_exists( 'tlmt_get_product_obj' ) ) {

	/**
	 * Obtain a product migration class object.
	 *
	 * @since 2.3.0
	 *
	 * @param string $monetization_type the monetization type.
	 * @param string $migration_type the migration type.
	 *
	 * @throws \Throwable if migration type is not supported.
	 *
	 * @return Product obj
	 */
	function tlmt_get_product_obj( $monetization_type, $migration_type ) {
		try {
			$obj = ProductFactory::create( $monetization_type, $migration_type );
			return $obj;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}


if ( ! function_exists( 'set_product_thumbnail') ) {

	/**
	 * Set product thumbnail from course.
	 *
	 * @since 2.3.0
	 *
	 * @param int $course_id the course id.
	 * @param int $product_id the product id.
	 *
	 * @return void
	 */
	function set_product_thumbnail( $course_id, $product_id ) {
		$coursePostThumbnail = get_post_meta( $course_id, '_thumbnail_id', true );

		if ( $coursePostThumbnail ) {
			set_post_thumbnail( $product_id, $coursePostThumbnail );
		}
	}
}