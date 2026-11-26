<?php
/**
 * Sensei LMS Course Meta migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration;

use Themeum\TutorLMSMigrationTool\Interfaces\PostMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Sensei course meta migration
 */
class CourseMeta implements PostMeta {

	/**
	 * Migrate course meta
	 *
	 * @param int $post_id Course post ID.
	 * @return void
	 */
	public function migrate( int $post_id ) {
		if ( $post_id <= 0 ) {
			return;
		}

		$this->migrate_video( $post_id );
		$this->migrate_prerequisites( $post_id );
		$this->migrate_monetization( $post_id );
		$this->migrate_featured( $post_id );
		$this->migrate_duration( $post_id );
	}

	/**
	 * Migrate course video
	 *
	 * @param int $post_id Course ID.
	 * @return void
	 */
	private function migrate_video( int $post_id ) {
		$video_embed = get_post_meta( $post_id, '_course_video_embed', true );
		if ( empty( $video_embed ) ) {
			return;
		}

		$video = array(
			'source'          => 'embedded',
			'source_video_id' => 0,
			'poster'          => '',
			'source_youtube'  => '',
			'source_vimeo'    => '',
			'source_embedded' => '',
		);

		$raw_video = trim( (string) $video_embed );

		if ( preg_match( '/^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\/.+/i', $raw_video ) ) {
			$video['source']         = 'youtube';
			$video['source_youtube'] = esc_url_raw( $raw_video );
		} elseif ( preg_match( '/^https?:\/\/(www\.)?vimeo\.com\/.+/i', $raw_video ) ) {
			$video['source']       = 'vimeo';
			$video['source_vimeo'] = esc_url_raw( $raw_video );
		} elseif ( preg_match( '/^https?:\/\/.+\.(mp4|webm|ogg|ogv|m4v|mov)(\?.*)?$/i', $raw_video ) ) {
			// Direct video file URL: wrap in an HTML5 <video> tag so the Tutor embedded
			// player can render it rather than showing the URL as plain unplayable text.
			$safe_url                 = esc_url( $raw_video );
			$video['source']          = 'embedded';
			$video['source_embedded'] = '<video controls style="max-width:100%"><source src="' . $safe_url . '" type="video/mp4"></video>';
		} else {
			$video['source']          = 'embedded';
			$video['source_embedded'] = $this->sanitize_embedded_video( $raw_video );
		}

		update_post_meta( $post_id, '_video', $video );
	}

	/**
	 * Migrate course prerequisites
	 *
	 * @param int $post_id Course ID.
	 * @return void
	 */
	private function migrate_prerequisites( int $post_id ) {
		$prerequisite_id = (int) get_post_meta( $post_id, '_course_prerequisite', true );
		if ( $prerequisite_id > 0 ) {
			update_post_meta( $post_id, '_tutor_course_prerequisites_ids', array( $prerequisite_id ) );
		}
	}

	/**
	 * Migrate course monetization / WooCommerce product linkage
	 *
	 * @param int $post_id Course ID.
	 * @return void
	 */
	private function migrate_monetization( int $post_id ) {
		// Sensei _open_access = 'yes' means the course is freely accessible without enrollment.
		// Map to Tutor free pricing so anyone can enroll at no cost.
		$open_access = get_post_meta( $post_id, '_open_access', true );
		if ( in_array( $open_access, array( 'yes', '1', 'on' ), true ) ) {
			update_post_meta( $post_id, '_tutor_course_price_type', 'free' );
			return;
		}

		$wc_product_id = (int) get_post_meta( $post_id, '_course_woocommerce_product', true );
		if ( $wc_product_id > 0 ) {
			update_post_meta( $post_id, '_tutor_course_price_type', 'paid' );
			update_post_meta( $post_id, '_tutor_course_product_id', $wc_product_id );
		} else {
			$existing_price_type = get_post_meta( $post_id, '_tutor_course_price_type', true );
			if ( empty( $existing_price_type ) ) {
				update_post_meta( $post_id, '_tutor_course_price_type', 'free' );
			}
		}
	}

	/**
	 * Migrate course featured status
	 *
	 * @param int $post_id Course ID.
	 * @return void
	 */
	private function migrate_featured( int $post_id ) {
		$featured = get_post_meta( $post_id, '_course_featured', true );
		if ( 'featured' === $featured || 'yes' === $featured || 'on' === $featured ) {
			update_post_meta( $post_id, '_tutor_is_featured', 'yes' );
		}
	}

	/**
	 * Migrate course duration by summing Sensei per-lesson lengths.
	 *
	 * Sensei stores duration per-lesson as _lesson_length (minutes). Tutor stores
	 * it on the course as _course_duration: array( 'hours' => int, 'minutes' => int ).
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id Course ID.
	 * @return void
	 */
	private function migrate_duration( int $post_id ) {
		// Skip if already set.
		if ( ! empty( get_post_meta( $post_id, '_course_duration', true ) ) ) {
			return;
		}

		$lessons = get_posts(
			array(
				'post_type'      => array( 'lesson', tutor()->lesson_post_type ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_lesson_course',
						'value' => $post_id,
					),
				),
			)
		);

		if ( empty( $lessons ) ) {
			return;
		}

		$total_minutes = 0;
		foreach ( $lessons as $lesson_id ) {
			$length = get_post_meta( (int) $lesson_id, '_lesson_length', true );
			if ( is_numeric( $length ) && (int) $length > 0 ) {
				$total_minutes += (int) $length;
			}
		}

		if ( $total_minutes <= 0 ) {
			return;
		}

		update_post_meta(
			$post_id,
			'_course_duration',
			array(
				'hours'   => (int) floor( $total_minutes / 60 ),
				'minutes' => $total_minutes % 60,
			)
		);
	}

	/**
	 * Sanitize embedded video markup, allowing safe iframes while stripping script tags.
	 *
	 * @param string $raw_video Video HTML.
	 * @return string
	 */
	private function sanitize_embedded_video( string $raw_video ): string {
		$allowed_html           = wp_kses_allowed_html( 'post' );
		$allowed_html['iframe'] = array(
			'src'             => true,
			'width'           => true,
			'height'          => true,
			'frameborder'     => true,
			'allowfullscreen' => true,
			'title'           => true,
			'allow'           => true,
		);

		return wp_kses( $raw_video, $allowed_html );
	}
}
