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
		$this->migrate_complexity( $post_id );
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
				$video['source_embedded'] = $this->sanitize_embedded_video( $raw_video );
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

	/**
	 * Migrate lesson complexity
	 *
	 * @param int $post_id Lesson post ID.
	 * @return void
	 */
	private function migrate_complexity( int $post_id ) {
		$complexity = get_post_meta( $post_id, '_lesson_complexity', true );
		if ( empty( $complexity ) ) {
			return;
		}

		$complexity_map = array(
			'easy'         => 'beginner',
			'intermediate' => 'intermediate',
			'medium'       => 'intermediate',
			'hard'         => 'expert',
			'expert'       => 'expert',
		);

		$normalized   = strtolower( trim( (string) $complexity ) );
		$mapped_level = isset( $complexity_map[ $normalized ] ) ? $complexity_map[ $normalized ] : 'all_levels';

		update_post_meta( $post_id, '_lesson_complexity', sanitize_text_field( $complexity ) );

		$course_id = (int) get_post_meta( $post_id, '_tutor_course_id_for_lesson', true );
		if ( $course_id > 0 ) {
			$existing_level = get_post_meta( $course_id, '_tutor_course_level', true );
			if ( empty( $existing_level ) ) {
				update_post_meta( $course_id, '_tutor_course_level', $mapped_level );
			}
		}
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
