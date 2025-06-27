<?php
/**
 * Assignment meta migrator class
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\PostMeta;

use Themeum\TutorLMSMigrationTool\Interfaces\PostMeta;
use Tutor\Helpers\QueryHelper;

/**
 * Handle assignment meta migration
 */
class AssignmentMeta implements PostMeta {

	/**
	 * Current post id
	 *
	 * @since 2.3.0
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Migrate assignment meta
	 *
	 * Migrate meta that are associated with the give post id.
	 *
	 * @since 2.3.0
	 *
	 * @param integer $post_id Post id.
	 *
	 * @throws \Throwable If Database error occur.
	 *
	 * @return void
	 */
	public function migrate( int $post_id ) {
		global $wpdb;

		$this->post_id = $post_id;

		$migrate_able_meta = $this->get_migrate_able_meta();

		if ( ! empty( $migrate_able_meta ) ) {
			// Prepare meta.
			$meta = $this->ld_to_tutor_meta_map( $migrate_able_meta );
			return $meta;
			if ( is_array( $meta ) && count( $meta ) ) {
				if ( ! tlmt_is_multi_dim_arr( $meta ) ) {
					$meta = array( $meta );
				}

				try {
					QueryHelper::insert_multiple_rows( $wpdb->postmeta, $meta, true, false );
				} catch ( \Throwable $th ) {
					throw $th;
				}
			}
		}
	}

	/**
	 * Get all the migrate-able meta from the given post id
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	private function get_migrate_able_meta(): array {
		$all_meta = get_post_meta( $this->post_id, '_sfwd-lessons', true );
		if ( ! empty( $all_meta ) ) {
			$migrate_able_meta = $this->migrate_able_meta();

			return array_intersect_key( $all_meta, array_flip( $migrate_able_meta ) );
		}

		return array();
	}

	/**
	 * Get all the meta that is migrate-able
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	private function migrate_able_meta() {
		return array(
			'sfwd-lessons_assignment_upload_limit_count',
			'sfwd-lessons_assignment_upload_limit_size',
			'sfwd-lessons_lesson_assignment_points_amount',
			'sfwd-lessons_forced_lesson_time',
			'sfwd-lessons_forced_lesson_time_enabled',
			'sfwd-lessons_lesson_schedule',
			'sfwd-lessons_visible_after',
			'sfwd-lessons_visible_after_specific_date',
		);
	}

	/**
	 * Map all ld meta to tutor meta for migration
	 *
	 * @since 2.3.0
	 *
	 * @param array $meta Mapped meta, ready to migrate.
	 *
	 * @return array Multi-dimension array with meta key and value.
	 */
	private function ld_to_tutor_meta_map( array $meta ): array {
		$course = get_post_parent( get_post_parent( $this->post_id ) );

		$assignment_settings = array(
			'upload_files_limit'     => isset( $meta['sfwd-lessons_assignment_upload_limit_count'] ) ? (int) $meta['sfwd-lessons_assignment_upload_limit_count'] : 1,
			'upload_file_size_limit' => isset( $meta['sfwd-lessons_assignment_upload_limit_size'] ) ? (int) $meta['sfwd-lessons_assignment_upload_limit_size'] : 2,
			'total_mark'             => isset( $meta['sfwd-lessons_lesson_assignment_points_amount'] ) ? (int) $meta['sfwd-lessons_lesson_assignment_points_amount'] : 10,
			'pass_mark'              => isset( $meta['sfwd-lessons_lesson_assignment_points_amount'] ) ? ceil( $meta['sfwd-lessons_lesson_assignment_points_amount'] / 2 ) : 5,
			'time_duration'          => array(
				'time'  => 'days',
				'value' => isset( $meta['sfwd-lessons_forced_lesson_time'] ) ? (int) $meta['sfwd-lessons_forced_lesson_time'] : 1,
			),
			'deadline_from_start'    => 1,
		);

		$assignment_meta = array(
			'_tutor_assignment_total_mark'     => $assignment_settings['total_mark'],
			'_tutor_assignment_pass_mark'      => $assignment_settings['pass_mark'],
			'_tutor_course_id_for_assignments' => $course ? $course->ID : 0,
			'_content_drip_settings'           => '',
			'assignment_option'                => maybe_serialize( $assignment_settings ),
		);

		$drip_settings = array();
		if ( ! empty( $meta['sfwd-lessons_lesson_schedule'] ) ) {
			if ( 'visible_after_specific_date' === $meta['sfwd-lessons_lesson_schedule'] && $meta['sfwd-lessons_visible_after_specific_date'] > 0 ) {
				$drip_settings['_content_drip_settings'] = array(
					'unlock_date' => gmdate( 'Y-m-d', $meta['sfwd-lessons_visible_after_specific_date'] ),
				);
			} elseif ( 'visible_after' === $meta['sfwd-lessons_lesson_schedule'] && $meta['sfwd-lessons_visible_after'] > 0 ) {
				$drip_settings['_content_drip_settings'] = array(
					'after_xdays_of_enroll' => (int) $meta['sfwd-lessons_visible_after'],
				);
			}

			// Serialize the data.
			$assignment_meta['_content_drip_settings'] = maybe_serialize( $drip_settings );
		}

		// Prepare to post meta to make it insert-able.
		$prepared_meta = array();

		foreach ( $assignment_meta as $key => $value ) {
			$meta            = array(
				'post_id'    => $this->post_id,
				'meta_key'   => $key,
				'meta_value' => $value,
			);
			$prepared_meta[] = $meta;
		}

		return $prepared_meta;
	}
}
