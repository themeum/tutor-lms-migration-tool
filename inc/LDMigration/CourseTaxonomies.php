<?php
/**
 * LearnDash course taxonomy migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.1
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration;

use Tutor\Models\CourseModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates LearnDash course categories and tags to Tutor course taxonomies.
 */
class CourseTaxonomies {

	/**
	 * LearnDash course category taxonomy.
	 *
	 * @since 2.4.1
	 *
	 * @var string
	 */
	const LD_COURSE_CATEGORY = 'ld_course_category';

	/**
	 * LearnDash course tag taxonomy.
	 *
	 * @since 2.4.1
	 *
	 * @var string
	 */
	const LD_COURSE_TAG = 'ld_course_tag';

	/**
	 * Cached map of LD term ID => Tutor term ID, keyed by source taxonomy.
	 *
	 * @since 2.4.1
	 *
	 * @var array<string, array<int, int>>
	 */
	private static $term_id_map = array();

	/**
	 * Migrate course-specific categories and tags for a course.
	 *
	 * @since 2.4.1
	 *
	 * @param int $course_id Course post ID (same ID after in-place CPT conversion).
	 *
	 * @return void
	 */
	public function migrate( int $course_id ) {
		if ( $course_id <= 0 ) {
			return;
		}

		$this->migrate_taxonomy(
			$course_id,
			self::LD_COURSE_CATEGORY,
			CourseModel::COURSE_CATEGORY,
			true
		);

		$this->migrate_taxonomy(
			$course_id,
			self::LD_COURSE_TAG,
			CourseModel::COURSE_TAG,
			false
		);
	}

	/**
	 * Migrate terms from a LearnDash taxonomy to a Tutor taxonomy for one course.
	 *
	 * @since 2.4.1
	 *
	 * @param int    $course_id     Course ID.
	 * @param string $source_tax    LearnDash taxonomy name.
	 * @param string $destination_tax Tutor taxonomy name.
	 * @param bool   $hierarchical  Whether parent terms should be remapped.
	 *
	 * @return void
	 */
	private function migrate_taxonomy( int $course_id, string $source_tax, string $destination_tax, bool $hierarchical ) {
		if ( ! taxonomy_exists( $source_tax ) || ! taxonomy_exists( $destination_tax ) ) {
			return;
		}

		$terms = wp_get_object_terms( $course_id, $source_tax );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$tutor_term_ids = array();
		foreach ( $terms as $term ) {
			$tutor_term_id = $this->ensure_tutor_term( $term, $source_tax, $destination_tax, $hierarchical );
			if ( $tutor_term_id > 0 ) {
				$tutor_term_ids[] = $tutor_term_id;
			}
		}

		if ( empty( $tutor_term_ids ) ) {
			return;
		}

		wp_set_object_terms( $course_id, array_values( array_unique( $tutor_term_ids ) ), $destination_tax, false );

		// LD taxonomies are not registered on Tutor course CPTs after conversion.
		wp_delete_object_term_relationships( $course_id, $source_tax );
	}

	/**
	 * Ensure a Tutor term exists for the given LearnDash term (including parents).
	 *
	 * @since 2.4.1
	 *
	 * @param \WP_Term $term             LearnDash term.
	 * @param string   $source_tax       LearnDash taxonomy.
	 * @param string   $destination_tax  Tutor taxonomy.
	 * @param bool     $hierarchical     Whether to migrate parent chain.
	 *
	 * @return int Tutor term ID, or 0 on failure.
	 */
	private function ensure_tutor_term( \WP_Term $term, string $source_tax, string $destination_tax, bool $hierarchical ): int {
		$ld_term_id = (int) $term->term_id;

		if ( isset( self::$term_id_map[ $source_tax ][ $ld_term_id ] ) ) {
			return self::$term_id_map[ $source_tax ][ $ld_term_id ];
		}

		$parent_tutor_id = 0;
		if ( $hierarchical && (int) $term->parent > 0 ) {
			$parent_term = get_term( (int) $term->parent, $source_tax );
			if ( $parent_term instanceof \WP_Term ) {
				$parent_tutor_id = $this->ensure_tutor_term( $parent_term, $source_tax, $destination_tax, true );
			}
		}

		$existing = term_exists( $term->slug, $destination_tax );
		if ( $existing ) {
			$tutor_term_id = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );

			if ( $hierarchical && $parent_tutor_id > 0 ) {
				$existing_term = get_term( $tutor_term_id, $destination_tax );
				if ( $existing_term instanceof \WP_Term && (int) $existing_term->parent !== $parent_tutor_id ) {
					wp_update_term(
						$tutor_term_id,
						$destination_tax,
						array(
							'parent' => $parent_tutor_id,
						)
					);
				}
			}

			self::$term_id_map[ $source_tax ][ $ld_term_id ] = $tutor_term_id;
			return $tutor_term_id;
		}

		$insert_args = array(
			'slug'        => $term->slug,
			'description' => $term->description,
		);

		if ( $hierarchical ) {
			$insert_args['parent'] = $parent_tutor_id;
		}

		$result = wp_insert_term( $term->name, $destination_tax, $insert_args );
		if ( is_wp_error( $result ) ) {
			// Slug collision with a different term shape — retry matching by name.
			$by_name = term_exists( $term->name, $destination_tax );
			if ( $by_name ) {
				$tutor_term_id = (int) ( is_array( $by_name ) ? $by_name['term_id'] : $by_name );
				self::$term_id_map[ $source_tax ][ $ld_term_id ] = $tutor_term_id;
				return $tutor_term_id;
			}

			return 0;
		}

		$tutor_term_id = (int) $result['term_id'];
		self::$term_id_map[ $source_tax ][ $ld_term_id ] = $tutor_term_id;

		return $tutor_term_id;
	}
}
