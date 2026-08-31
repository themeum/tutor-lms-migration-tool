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
use Themeum\TutorLMSMigrationTool\Factories\SalesDataFactory;

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

if ( ! function_exists( 'tlmt_ld_answer_plain_text' ) ) {
	/**
	 * Strip LearnDash answer HTML to plain text.
	 *
	 * @since 2.5.0
	 *
	 * @param string $content Answer content.
	 *
	 * @return string
	 */
	function tlmt_ld_answer_plain_text( $content ) {
		return trim( wp_strip_all_tags( html_entity_decode( (string) $content, ENT_QUOTES, 'UTF-8' ) ) );
	}
}

if ( ! function_exists( 'tlmt_ld_answer_first_image_id' ) ) {
	/**
	 * Resolve the first attachment ID from HTML answer content.
	 *
	 * @since 2.5.0
	 *
	 * @param string $content Answer HTML.
	 *
	 * @return int
	 */
	function tlmt_ld_answer_first_image_id( $content ) {
		if ( empty( $content ) || false === stripos( (string) $content, '<img' ) ) {
			return 0;
		}

		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', (string) $content, $matches );
		if ( empty( $matches[1] ) ) {
			return 0;
		}

		foreach ( $matches[1] as $image_url ) {
			$attachment_id = attachment_url_to_postid( $image_url );
			if ( $attachment_id ) {
				return (int) $attachment_id;
			}

			// Retry without WordPress size suffix (e.g. image-300x200.jpg).
			$full_url = preg_replace( '/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $image_url );
			if ( $full_url && $full_url !== $image_url ) {
				$attachment_id = attachment_url_to_postid( $full_url );
				if ( $attachment_id ) {
					return (int) $attachment_id;
				}
			}
		}

		return 0;
	}
}

if ( ! function_exists( 'tlmt_ld_answer_data_as_array' ) ) {
	/**
	 * Normalize a LearnDash answer row to an array.
	 *
	 * @since 2.5.0
	 *
	 * @param mixed $answer Answer object or array.
	 *
	 * @return array
	 */
	function tlmt_ld_answer_data_as_array( $answer ) {
		if ( is_object( $answer ) && method_exists( $answer, 'get_object_as_array' ) ) {
			return $answer->get_object_as_array();
		}

		if ( is_array( $answer ) ) {
			return $answer;
		}

		return array();
	}
}

if ( ! function_exists( 'tlmt_ld_matrix_criterion_is_image' ) ) {
	/**
	 * Whether a LearnDash matrix criterion row is image-based.
	 *
	 * @since 2.5.0
	 *
	 * @param array $ans_arr LearnDash answer as array.
	 *
	 * @return bool
	 */
	function tlmt_ld_matrix_criterion_is_image( array $ans_arr ) {
		return tlmt_ld_answer_first_image_id( $ans_arr['_answer'] ?? '' ) > 0;
	}
}

if ( ! function_exists( 'tlmt_ld_matrix_is_image_matching' ) ) {
	/**
	 * Whether a LearnDash matrix sort question should migrate as Tutor image matching.
	 *
	 * Tutor cannot mix image and text matching in one question. Uses the majority
	 * criterion type; on a 50/50 split prefers image matching.
	 *
	 * @since 2.5.0
	 *
	 * @param array $ld_answer_data Unserialized LearnDash answer_data rows.
	 *
	 * @return bool
	 */
	function tlmt_ld_matrix_is_image_matching( $ld_answer_data ) {
		if ( ! is_array( $ld_answer_data ) || empty( $ld_answer_data ) ) {
			return false;
		}

		$image_count = 0;
		$text_count  = 0;

		foreach ( $ld_answer_data as $answer ) {
			$ans_arr = tlmt_ld_answer_data_as_array( $answer );
			if ( tlmt_ld_matrix_criterion_is_image( $ans_arr ) ) {
				++$image_count;
			} else {
				++$text_count;
			}
		}

		// Prefer image matching when counts are equal (including 50/50).
		return $image_count >= $text_count && $image_count > 0;
	}
}

if ( ! function_exists( 'tlmt_ld_matrix_answer_matches_mode' ) ) {
	/**
	 * Whether a matrix answer row matches the chosen Tutor matching mode.
	 *
	 * Minority opposite-type rows are excluded during migration.
	 *
	 * @since 2.5.0
	 *
	 * @param array $ans_arr            LearnDash answer as array.
	 * @param bool  $is_image_matching Chosen matching mode.
	 *
	 * @return bool
	 */
	function tlmt_ld_matrix_answer_matches_mode( array $ans_arr, $is_image_matching ) {
		$is_image_row = tlmt_ld_matrix_criterion_is_image( $ans_arr );

		return $is_image_matching ? $is_image_row : ! $is_image_row;
	}
}

if ( ! function_exists( 'tlmt_map_ld_matrix_answer' ) ) {
	/**
	 * Map one LearnDash matrix sort answer to Tutor matching answer fields.
	 *
	 * Text matching: criterion → answer_title, sort string → answer_two_gap_match.
	 * Image matching: criterion image → image_id, sort string → answer_title (draggable).
	 *
	 * @since 2.5.0
	 *
	 * @param array $ans_arr            LearnDash answer as array.
	 * @param bool  $is_image_matching Whether the question uses image matching.
	 *
	 * @return array{answer_title: string, answer_two_gap_match: string, image_id: int, answer_view_format: string}
	 */
	function tlmt_map_ld_matrix_answer( array $ans_arr, $is_image_matching ) {
		$criterion      = (string) ( $ans_arr['_answer'] ?? '' );
		$sort_string    = (string) ( $ans_arr['_sortString'] ?? '' );
		$criterion_text = tlmt_ld_answer_plain_text( $criterion );
		$sort_text      = tlmt_ld_answer_plain_text( $sort_string );
		$image_id       = tlmt_ld_answer_first_image_id( $criterion );

		if ( $is_image_matching ) {
			return array(
				'answer_title'         => $sort_text ? $sort_text : $criterion_text,
				'answer_two_gap_match' => '',
				'image_id'             => $image_id,
				'answer_view_format'   => $image_id > 0 ? 'text_image' : 'text',
			);
		}

		return array(
			'answer_title'         => $criterion_text,
			'answer_two_gap_match' => $sort_text,
			'image_id'             => 0,
			'answer_view_format'   => 'text',
		);
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

if ( ! function_exists( 'tlmt_get_lifter_video_embed' ) ) {
	/**
	 * Get LifterLMS video embed URL from post meta.
	 *
	 * Supports both legacy `_llms_video_embed` and Lifter 3.0+ `_video_embed` keys.
	 *
	 * @since 2.6.1
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	function tlmt_get_lifter_video_embed( int $post_id ): string {
		foreach ( array( '_video_embed', '_llms_video_embed' ) as $meta_key ) {
			$video_embed = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
			if ( '' !== $video_embed ) {
				return $video_embed;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'tlmt_migrate_video_meta' ) ) {
	/**
	 * Map a video URL/embed to Tutor `_video` post meta.
	 *
	 * @since 2.6.1
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $video_url Video URL, shortcode, or embed code.
	 *
	 * @return void
	 */
	function tlmt_migrate_video_meta( int $post_id, string $video_url ): void {
		$video_url = trim( $video_url );
		if ( '' === $video_url || ! function_exists( 'tlmt_get_video_source_by_url' ) ) {
			return;
		}

		$video_info = tlmt_get_video_source_by_url( $video_url );

		if ( function_exists( 'tutor_utils' ) ) {
			tutor_utils()->update_video( $post_id, $video_info );
			return;
		}

		update_post_meta( $post_id, '_video', maybe_serialize( $video_info ) );
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

		if ( preg_match( '/youtube\.com\/watch\?v=([^\&\s]+)/', $url, $matches )
			|| preg_match( '/youtu\.be\/([^\&\s]+)/', $url, $matches )
			|| preg_match( '/youtube\.com\/embed\/([^\&\s?\/]+)/', $url, $matches )
			|| preg_match( '/youtube\.com\/v\/([^\&\s?\/]+)/', $url, $matches )
		) {
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

if ( ! function_exists( 'tlmt_get_minute_by_timestamp' ) ) {
	/**
	 * Get readable formatted time using a timestamp
	 *
	 * @since 2.3.0
	 *
	 * @param int|string $time Timestamp.
	 *
	 * @return int
	 */
	function tlmt_get_minute_by_timestamp( $time ) {
		if ( ! $time ) {
			return 0;
		}

		return floor( $time / 60 );
	}
}

if ( ! function_exists( 'tlmt_get_sales_data_object' ) ) {
	/**
	 * Get readable formatted time using a timestamp
	 *
	 * @since 2.3.0
	 *
	 * @param string $data_type Data type like: orders, subscriptions, etc.
	 * @param string $migration_type Migration type like: woocommerce_to_native.
	 *
	 * @throws \Throwable Throw invalid argument exception if
	 * data or migration type is not supported.
	 * @return MigrationTemplate object
	 */
	function tlmt_get_sales_data_object( $data_type, $migration_type ) {
		try {
			return SalesDataFactory::create( $data_type, $migration_type );
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}
