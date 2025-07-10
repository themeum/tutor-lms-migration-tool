<?php
/**
 * PostMeta Factory
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\PostMeta;

use Tutor\Helpers\QueryHelper;
use Themeum\TutorLMSMigrationTool\Interfaces\PostMeta;

/**
 * Handle course meta migration
 */
class LessonMeta implements PostMeta {

	/**
	 * Post id
	 *
	 * @since 2.3.0
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Migrate post meta
	 *
	 * @since 2.3.0
	 *
	 * @param integer $post_id Lesson post id.
	 *
	 * @throws \Throwable If database error occurs.
	 *
	 * @return void
	 */
	public function migrate( int $post_id ) {
		global $wpdb;

		$this->post_id = $post_id;

		$meta = $this->get_meta();

		if ( is_array( $meta ) && count( $meta ) ) {
			try {
				QueryHelper::insert_multiple_rows( $wpdb->postmeta, $meta, false, false );
			} catch ( \Throwable $th ) {
				throw $th;
			}
		}
	}

	/**
	 * Get prepared meta to insert in the db
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	public function get_meta(): array {
		$tutor_meta = array(
			'_tutor_attachments'     => '',
			'_video'                 => '',
			'_is_preview'            => 0,
			'_content_drip_settings' => '',
		);

		$meta = $this->get_migrate_able_meta();

		if ( isset( $meta['sfwd-lessons_lesson_video_enabled'] ) && 'on' === $meta['sfwd-lessons_lesson_video_enabled'] ) {
			if ( ! empty( $meta['sfwd-lessons_lesson_video_url'] ) ) {
				$tutor_meta['_video'] = maybe_serialize( tlmt_get_video_source_by_url( $meta['sfwd-lessons_lesson_video_url'] ) );
			}
		}

		if ( tlmt_has_tutor_pro() ) {
			if ( isset( $meta['sfwd-lessons_sample_lesson'] ) && 'on' === $meta['sfwd-lessons_sample_lesson'] ) {
				$tutor_meta['_is_preview'] = 'on';
			}

			if ( ! empty( $meta['sfwd-lessons_lesson_schedule'] ) ) {
				if ( 'visible_after_specific_date' === $meta['sfwd-lessons_lesson_schedule'] && $meta['sfwd-lessons_visible_after_specific_date'] > 0 ) {
					$tutor_meta['_content_drip_settings'] = array(
						'unlock_date' => gmdate( 'Y-m-d', $meta['sfwd-lessons_visible_after_specific_date'] ),
					);
				} elseif ( 'visible_after' === $meta['sfwd-lessons_lesson_schedule'] && $meta['sfwd-lessons_visible_after'] > 0 ) {
					$tutor_meta['_content_drip_settings'] = array(
						'after_xdays_of_enroll' => (int) $meta['sfwd-lessons_visible_after'],
					);
				}

				// Serialize the data.
				$tutor_meta['_content_drip_settings'] = maybe_serialize( $tutor_meta['_content_drip_settings'] );
			}
		}

		$prepared_meta = array();
		foreach ( $tutor_meta as $key => $value ) {
			$meta            = array(
				'post_id'    => $this->post_id,
				'meta_key'   => $key,
				'meta_value' => $value,
			);
			$prepared_meta[] = $meta;
		}

		return $prepared_meta;
	}

	/**
	 * Get the migrate-able meta
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	public function get_migrate_able_meta(): array {
		$ld_meta = get_post_meta( $this->post_id, '_sfwd-lessons', true );

		$migrate_able_meta = array(
			'sfwd-lessons_sample_lesson',
			'sfwd-lessons_lesson_schedule',
			'sfwd-lessons_visible_after_specific_date',
			'sfwd-lessons_visible_after',
			'sfwd-lessons_lesson_materials_enabled',
			'sfwd-lessons_lesson_materials',
			'sfwd-lessons_lesson_video_enabled',
			'sfwd-lessons_lesson_video_url',
		);

		return array_intersect_key( $ld_meta, array_flip( $migrate_able_meta ) );
	}

}
