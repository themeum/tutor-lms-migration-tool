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

use Themeum\TutorLMSMigrationTool\Interfaces\PostMeta;
use Tutor\Helpers\QueryHelper;

/**
 * Handle course meta migration
 */
class CourseMeta implements PostMeta {

	/**
	 * Current post id
	 *
	 * @since 2.3.0
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Migrate course meta
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
			if ( is_array( $meta ) && count( $meta ) ) {
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
		$all_meta = get_post_meta( $this->post_id, '_sfwd-courses', true );
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
			'sfwd-courses_course_seats_limit',
			'sfwd-courses_expire_access_days',
			'sfwd-courses_course_materials',
			'sfwd-courses_course_price',
			'sfwd-courses_course_price_type',
			'sfwd-courses_course_start_date',
			'sfwd-courses_course_end_date',
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
		$ld_tutor_meta_map = array(
			'_tutor_course_price_type'        => 'sfwd-courses_course_price_type',
			'_tutor_course_material_includes' => 'sfwd-courses_course_materials',
			'tutor_course_regular_price'      => 'sfwd-courses_course_price',
		);

		$course_settings = array();
		$has_tutor_pro   = function_exists( 'tutor_pro' );

		if ( $has_tutor_pro ) {
			$course_settings = array(
				'maximum_students'         => 0,
				'enrollment_expiry'        => 0,
				'enable_content_drip'      => 0,
				'content_drip_type'        => '',
				'course_enrollment_period' => '',
				'enrollment_starts_at'     => '',
				'enrollment_ends_at'       => '',
				'pause_enrollment'         => '',
			);

			// Enroll start date.
			$enrollment_start_date = $meta['sfwd-courses_course_start_date'] ?? false;
			if ( $enrollment_start_date ) {
				$start_date = gmdate( 'Y-m-d H:i:s', $enrollment_start_date );

				$course_settings['course_enrollment_period'] = 'yes';
				$course_settings['enrollment_starts_at']     = $start_date;

				if ( ! empty( $meta['sfwd-courses_course_end_date'] ) ) {
					$end_date = gmdate( 'Y-m-d H:i:s', $meta['sfwd-courses_course_end_date'] );

					$course_settings['enrollment_ends_at'] = $end_date;
				}
			}
		}

		$tutor_meta_map = array();

		$ld_keys = array_keys( $meta );
		foreach ( $ld_tutor_meta_map as $key => $value ) {
			if ( in_array( $value, $ld_keys ) ) {
				$tutor_meta_map[ $key ] = $meta[ $value ];
			}
		}

		// Price type.
		$price_type = $tutor_meta_map['_tutor_course_price_type'];
		if ( 'open' === $price_type ) {
			$tutor_meta_map['_tutor_course_price_type'] = 'free';
			// Set visibility public.
			$tutor_meta_map['_tutor_is_public_course'] = 'yes';
		} elseif ( 'paynow' === $price_type ) {
			$tutor_meta_map['_tutor_course_price_type'] = 'paid';
		} elseif ( 'closed' === $price_type && $has_tutor_pro ) {
			$course_settings['pause_enrollment'] = 'yes';
			if ( ! empty( $tutor_meta_map['tutor_course_regular_price'] ) ) {
				$tutor_meta_map['_tutor_course_price_type'] = 'paid';
			} else {
				$tutor_meta_map['_tutor_course_price_type'] = 'free';
			}
		} else {
			$tutor_meta_map['_tutor_course_price_type'] = 'free';
		}

		if ( $has_tutor_pro ) {
			// Max students.
			if ( ! empty( $meta['sfwd-courses_course_seats_limit'] ) ) {
				$course_settings['maximum_students'] = (int) $meta['sfwd-courses_course_seats_limit'];
			}

			// Enrollment expiry.
			if ( ! empty( $meta['sfwd-courses_expire_access'] && 'on' === $meta[ $meta['sfwd-courses_expire_access'] ] ) ) {
				$course_settings['enrollment_expiry'] = (int) $meta['sfwd-courses_expire_access_days'];
			}
		}

		// Add course settings in meta.
		$tutor_meta_map['_tutor_course_settings'] = maybe_serialize( $course_settings );

		// Prepare to post meta to make it insert-able.
		$prepared_meta = array();
		foreach ( $tutor_meta_map as $key => $value ) {
			$new_meta = array(
				'post_id'    => $this->post_id,
				'meta_key'   => $key,
				'meta_value' => $value,
			);

			$prepared_meta[] = $new_meta;
		}

		return $prepared_meta;
	}
}
