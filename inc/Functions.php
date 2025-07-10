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
use Themeum\TutorLMSMigrationTool\Factories\PostFactory;
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
	 * Get post meta object
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

if ( ! function_exists( 'tlmt_get_post_obj' ) ) {
	/**
	 * Get post object
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_type Meta type like: course, lesson, etc.
	 * @param string $migration_type Migration type like: ld_to_tutor.
	 *
	 * @see MigrationTypes & ContentTypes class
	 *
	 * @throws \Throwable If the migration type is not supported.
	 *
	 * @return Post object
	 */
	function tlmt_get_post_obj( $post_type, $migration_type ) {
		try {
			$obj = PostFactory::create( $post_type, $migration_type );
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


if ( ! function_exists( 'set_product_thumbnail' ) ) {

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

if ( ! function_exists( 'tlmt_get_video_source_by_url' ) ) {
	/**
	 * Get video source by url
	 *
	 * @since 2.3.0
	 *
	 * @param string $url Video url, sortcode or embed code.
	 *
	 * @return array
	 */
	function tlmt_get_video_source_by_url( string $url ): array {
		$source              = 'external_url';
		$source_youtube      = '';
		$source_vimeo        = '';
		$source_shortcode    = '';
		$source_embedded     = '';
		$source_external_url = '';
		$source_html5        = '';

		if ( preg_match( '/youtube\.com\/watch\?v=([^\&\s]+)/', $url, $matches ) || preg_match( '/youtu\.be\/([^\&\s]+)/', $url, $matches ) ) {
			$source         = 'youtube';
			$source_youtube = $url;
		} elseif ( preg_match( '/vimeo\.com\/(\d+)/', $url, $matches ) ) {
			$source       = 'vimeo';
			$source_vimeo = $url;
		} elseif ( preg_match( '/^\[.*\]$/s', trim( $url ) ) ) {
			// Shortcode starts and ends with square brackets.
			$source           = 'shortcode';
			$source_shortcode = $url;
		} elseif ( preg_match( '/<iframe.+src="(.+?)"/s', $url, $matches ) ) {
			// Embedded iframe.
			$source          = 'embedded';
			$source_embedded = $url;
		} elseif ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			// Generic external URL (e.g., mp4).
			$source              = 'external_url';
			$source_external_url = $url;
		} else {
			// If none matched, maybe it's HTML5 file (local path).
			$source       = 'html5';
			$source_html5 = $url;
		}

		$video_info = array(
			'source'              => $source,
			'source_video_id'     => '',
			'poster'              => '',
			'poster_url'          => '',
			'source_html5'        => $source_html5,
			'source_external_url' => $source_external_url,
			'source_shortcode'    => $source_shortcode,
			'source_youtube'      => $source_youtube,
			'source_vimeo'        => $source_vimeo,
			'source_embedded'     => $source_embedded,
			'runtime'             => array(
				'hours'   => 0,
				'minutes' => 0,
				'seconds' => 0,
			),
		);

		return $video_info;
	}
}

if ( ! function_exists( 'tlmt_get_formatted_time_by_timestamp' ) ) {
	/**
	 * Get readable formatted time using a timestamp
	 *
	 * @since 2.3.0
	 *
	 * @param int|string $time Timestamp.
	 *
	 * @return string hh:mm
	 */
	function tlmt_get_formatted_time_by_timestamp( $time ) {
		$hours   = floor( $time / 3600 );
		$minutes = floor( ( $time % 3600 ) / 60 );

		// Format with leading zeros.
		$formatted_time = sprintf( '%02d:%02d', $hours, $minutes );

		return $formatted_time;
	}
}

if ( ! function_exists( 'tlmt_get_time_duration_in_hour_min' ) ) {
	/**
	 * Get readable formatted time using a timestamp
	 *
	 * @since 2.3.0
	 *
	 * @param int|string $time Timestamp.
	 *
	 * @return array [hours => 01, minutes => 15]
	 */
	function tlmt_get_time_duration_in_hour_min( $time ) {
		$res = array(
			'hours'   => 0,
			'minutes' => 0,
		);

		if ( ! $time ) {
			return $res;
		}

		$formatted_time = tlmt_get_formatted_time_by_timestamp( $time );
		if ( ! $formatted_time ) {
			return $res;
		}

		$time_arr = explode( ':', $formatted_time );

		if ( ! empty( $time_arr[0] ) ) {
			$res['hours'] = $time_arr[0];
		}
		if ( ! empty( $time_arr[1] ) ) {
			$res['minutes'] = $time_arr[1];
		}

		return $res;
	}
}
