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
	 * @return void
	 */
	public function migrate( int $post_id ) {
		$this->post_id = $post_id;

		$migrate_able_meta = $this->get_migrate_able_meta();

		if ( ! empty( $migrate_able_meta ) ) {
			// Prepare meta.
			$meta = $this->ld_to_tutor_meta_map( $migrate_able_meta );
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
		);
	}

	/**
	 * Map all ld meta to tutor meta for migration
	 *
	 * @since 2.3.0
	 *
	 * @param array $meta Mapped meta, ready to migrate.
	 *
	 * @return array
	 */
	private function ld_to_tutor_meta_map( array $meta ): array {
		$ld_tutor_meta_map = array(
			'_tutor_course_price_type'        => 'sfwd-courses_course_price_type',
			'_tutor_course_material_includes' => 'sfwd-courses_course_materials',
			'tutor_course_sale_price'         => 'sfwd-courses_course_price_type',
		);

		$ld_keys = array_values( $ld_tutor_meta_map );

		$tutor_meta_map = array();
		foreach ( $meta as $key => $value ) {
			if ( in_array( $key, $ld_keys ) ) {
				$tutor_meta_map[ $ld_tutor_meta_map[ $key ] ] = $value;
			}
		}

		return $tutor_meta_map;
	}
}
