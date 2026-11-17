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
		} else {
			$video['source']          = 'embedded';
			$video['source_embedded'] = $raw_video;
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
}
