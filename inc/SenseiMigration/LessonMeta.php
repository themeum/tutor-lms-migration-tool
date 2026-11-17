<?php
/**
 * Sensei LMS Lesson Meta migration.
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
 * Handle Sensei lesson meta migration
 */
class LessonMeta implements PostMeta {

	/**
	 * Migrate lesson meta
	 *
	 * @param int $post_id Lesson post ID.
	 * @return void
	 */
	public function migrate( int $post_id ) {
		if ( $post_id <= 0 ) {
			return;
		}

		$this->migrate_video_and_duration( $post_id );
		$this->migrate_preview( $post_id );
	}

	/**
	 * Migrate lesson video and duration
	 *
	 * @param int $post_id Lesson post ID.
	 * @return void
	 */
	private function migrate_video_and_duration( int $post_id ) {
		$video_embed   = get_post_meta( $post_id, '_lesson_video_embed', true );
		$lesson_length = get_post_meta( $post_id, '_lesson_length', true );

		$video = array(
			'source'          => 'embedded',
			'source_video_id' => 0,
			'poster'          => '',
			'source_youtube'  => '',
			'source_vimeo'    => '',
			'source_embedded' => '',
			'duration_sec'    => 0,
			'runtime'         => array(
				'hours'   => 0,
				'minutes' => 0,
				'seconds' => 0,
			),
		);

		$has_video = ! empty( $video_embed );

		if ( $has_video ) {
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
		}

		if ( ! empty( $lesson_length ) && is_numeric( $lesson_length ) ) {
			$minutes               = (int) $lesson_length;
			$duration_sec          = $minutes * 60;
			$hours                 = floor( $minutes / 60 );
			$remaining_minutes     = $minutes % 60;
			$video['duration_sec'] = $duration_sec;
			$video['runtime']      = array(
				'hours'   => (int) $hours,
				'minutes' => (int) $remaining_minutes,
				'seconds' => 0,
			);
		}

		if ( $has_video || ! empty( $video['duration_sec'] ) ) {
			if ( function_exists( 'tutor_utils' ) && method_exists( tutor_utils(), 'filter_video_meta' ) ) {
				update_post_meta( $post_id, '_video', tutor_utils()->filter_video_meta( $video ) );
			} else {
				update_post_meta( $post_id, '_video', $video );
			}
		}
	}

	/**
	 * Migrate lesson preview status
	 *
	 * @param int $post_id Lesson post ID.
	 * @return void
	 */
	private function migrate_preview( int $post_id ) {
		$preview = get_post_meta( $post_id, '_lesson_preview', true );
		if ( 'preview' === $preview || 'yes' === $preview || '1' === $preview || 'on' === $preview ) {
			update_post_meta( $post_id, '_is_preview', 'yes' );
		} else {
			delete_post_meta( $post_id, '_is_preview' );
		}
	}
}
