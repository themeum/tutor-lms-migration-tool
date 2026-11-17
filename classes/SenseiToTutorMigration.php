<?php
/**
 * Manage Sensei LMS to Tutor LMS migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SenseiMigration\CourseTaxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SenseiToTutorMigration' ) ) {
	/**
	 * Sensei LMS to Tutor LMS Migration class
	 */
	class SenseiToTutorMigration {

		/**
		 * Constructor function
		 */
		public function __construct() {
			add_filter( 'tutor_tool_pages', array( $this, 'tutor_tool_pages' ) );
			add_action( 'wp_ajax_insert_tutor_migration_data', array( $this, 'insert_tutor_migration_data' ) );
			add_action( 'wp_ajax_sensei_migrate_all_data_to_tutor', array( $this, 'sensei_migrate_all_data_to_tutor' ) );
			add_action( 'wp_ajax_tlmt_reset_migrated_items_count', array( $this, 'tlmt_reset_migrated_items_count' ) );

			add_action( 'wp_ajax__get_sensei_live_progress_course_migrating_info', array( $this, '_get_sensei_live_progress_course_migrating_info' ) );
		}

		/**
		 * Insert migration log entry
		 *
		 * @return void
		 */
		public function insert_tutor_migration_data() {
			global $wpdb;

			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			$migration_type   = isset( $_POST['migration_type'] ) ? sanitize_text_field( wp_unslash( $_POST['migration_type'] ) ) : '';
			$migration_vendor = isset( $_POST['migration_vendor'] ) ? sanitize_text_field( wp_unslash( $_POST['migration_vendor'] ) ) : '';

			$tutor_migration_table_data = array(
				'migration_type'   => $migration_type,
				'migration_vendor' => $migration_vendor,
				'created_by'       => get_current_user_id(),
				'created_at'       => current_time( 'mysql' ),
			);

			$wpdb->insert(
				$wpdb->prefix . 'tutor_migration',
				$tutor_migration_table_data
			);
		}

		/**
		 * Register Sensei LMS Migration subpage in Tutor Tools
		 *
		 * @param array $pages Tool pages.
		 * @return array
		 */
		public function tutor_tool_pages( $pages ) {
			if ( defined( 'SENSEI_LMS_VERSION' ) || class_exists( 'Sensei_Main' ) || is_plugin_active( 'sensei-lms/sensei-lms.php' ) ) {
				$pages['migration_sensei'] = array(
					'label'     => __( 'Sensei LMS Migration', 'tutor-lms-migration-tool' ),
					'slug'      => 'migration_sensei',
					'desc'      => __( 'Sensei LMS Migration', 'tutor-lms-migration-tool' ),
					'template'  => 'migration_sensei',
					'view_path' => TLMT_PATH . 'views/',
					'icon'      => 'tutor-icon-brand-sensei',
					'blocks'    => array(
						'block' => array(),
					),
				);
			}

			return $pages;
		}

		/**
		 * Delete migrated item count option
		 *
		 * @return void
		 */
		public function tlmt_reset_migrated_items_count() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			delete_option( '_tutor_migrated_items_count' );
		}

		/**
		 * Get live progress count info
		 *
		 * @return void
		 */
		public function _get_sensei_live_progress_course_migrating_info() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			$migrated_count = (int) get_option( '_tutor_migrated_items_count', 0 );

			wp_send_json_success(
				array(
					'migrated_count' => $migrated_count,
				)
			);
		}

		/**
		 * Main AJAX router for Sensei data migration steps
		 *
		 * @return void
		 */
		public function sensei_migrate_all_data_to_tutor() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			if ( ! isset( $_POST['migrate_type'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid migration type.', 'tutor-lms-migration-tool' ) ) );
			}

			$migrate_type = sanitize_text_field( wp_unslash( $_POST['migrate_type'] ) );

			switch ( $migrate_type ) {
				case 'courses':
					$this->sensei_migrate_course_to_tutor();
					break;
				case 'orders':
					$this->migrate_sensei_orders();
					break;
				case 'reviews':
					$this->migrate_sensei_reviews();
					break;
				default:
					wp_send_json_error( array( 'message' => __( 'Unknown migration type.', 'tutor-lms-migration-tool' ) ) );
					break;
			}
		}

		/**
		 * Migrate Sensei courses in batches or all at once.
		 *
		 * @return void
		 */
		public function sensei_migrate_course_to_tutor() {
			global $wpdb;

			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			$is_batch   = isset( $_POST['batch'] ) && '1' === (string) $_POST['batch'];
			$batch_size = isset( $_POST['batch_size'] ) ? max( 1, (int) $_POST['batch_size'] ) : 10;

			$limit_clause = $is_batch ? $wpdb->prepare( ' LIMIT %d', $batch_size ) : '';

			$courses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC",
					'course'
				) . $limit_clause
			);

			$migrated_count = (int) get_option( '_tutor_migrated_items_count', 0 );

			if ( ! empty( $courses ) ) {
				foreach ( $courses as $course_row ) {
					$course_id = (int) $course_row->ID;
					$this->migrate_course( $course_id );
					$migrated_count++;
					update_option( '_tutor_migrated_items_count', $migrated_count );
				}
			}

			$remaining = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
					'course'
				)
			);

			wp_send_json_success(
				array(
					'has_more'       => $remaining > 0,
					'remaining'      => $remaining,
					'migrated_count' => $migrated_count,
				)
			);
		}

		/**
		 * Migrate single Sensei course to Tutor LMS.
		 *
		 * @param int $course_id Sensei course ID.
		 * @return bool True on success, false on failure.
		 */
		public function migrate_course( int $course_id ): bool {
			global $wpdb;

			if ( $course_id <= 0 ) {
				return false;
			}

			$course = get_post( $course_id );
			if ( ! $course || 'course' !== $course->post_type ) {
				return false;
			}

			$course_post_type = tutor()->course_post_type;
			$lesson_post_type = tutor()->lesson_post_type;

			$modules             = $this->get_course_modules( $course_id );
			$topic_order         = 0;
			$migrated_lesson_ids = array();

			// 1. Module to Topic transformation
			if ( ! empty( $modules ) ) {
				foreach ( $modules as $module_term ) {
					if ( ! ( $module_term instanceof \WP_Term ) ) {
						continue;
					}

					$topic_order++;
					$topic_id = wp_insert_post(
						array(
							'post_type'    => 'topics',
							'post_title'   => $module_term->name,
							'post_content' => $module_term->description,
							'post_status'  => 'publish',
							'post_author'  => $course->post_author,
							'post_parent'  => $course_id,
							'menu_order'   => $topic_order,
						)
					);

					if ( ! $topic_id || is_wp_error( $topic_id ) ) {
						continue;
					}

					$lessons      = $this->get_module_lessons_for_course( $course_id, (int) $module_term->term_id );
					$lesson_order = 0;

					foreach ( $lessons as $lesson ) {
						$lesson_id             = (int) $lesson->ID;
						$migrated_lesson_ids[] = $lesson_id;
						$lesson_order++;

						$wpdb->update(
							$wpdb->posts,
							array(
								'post_type'   => $lesson_post_type,
								'post_parent' => $topic_id,
								'menu_order'  => $lesson_order,
							),
							array( 'ID' => $lesson_id ),
							array( '%s', '%d', '%d' ),
							array( '%d' )
						);

						clean_post_cache( $lesson_id );

						update_post_meta( $lesson_id, '_tutor_course_id_for_lesson', $course_id );

						do_action( 'tlmt_lesson_migrated', $lesson_id, MigrationTypes::SENSEI_TO_TUTOR );
					}
				}
			}

			// 2. Unassigned / orphan lessons fallback to "General" topic
			$orphan_lessons = $this->get_orphan_lessons_for_course( $course_id, $migrated_lesson_ids );
			if ( ! empty( $orphan_lessons ) ) {
				$topic_order++;
				$default_topic_id = wp_insert_post(
					array(
						'post_type'    => 'topics',
						'post_title'   => __( 'General', 'tutor-lms-migration-tool' ),
						'post_content' => '',
						'post_status'  => 'publish',
						'post_author'  => $course->post_author,
						'post_parent'  => $course_id,
						'menu_order'   => $topic_order,
					)
				);

				if ( $default_topic_id && ! is_wp_error( $default_topic_id ) ) {
					$orphan_order = 0;
					foreach ( $orphan_lessons as $orphan_lesson ) {
						$orphan_id = (int) $orphan_lesson->ID;
						$orphan_order++;

						$wpdb->update(
							$wpdb->posts,
							array(
								'post_type'   => $lesson_post_type,
								'post_parent' => $default_topic_id,
								'menu_order'  => $orphan_order,
							),
							array( 'ID' => $orphan_id ),
							array( '%s', '%d', '%d' ),
							array( '%d' )
						);

						clean_post_cache( $orphan_id );

						update_post_meta( $orphan_id, '_tutor_course_id_for_lesson', $course_id );

						do_action( 'tlmt_lesson_migrated', $orphan_id, MigrationTypes::SENSEI_TO_TUTOR );
					}
				}
			}

			// 3. Update Course post type to Tutor course post type
			$wpdb->update(
				$wpdb->posts,
				array( 'post_type' => $course_post_type ),
				array( 'ID' => $course_id ),
				array( '%s' ),
				array( '%d' )
			);
			clean_post_cache( $course_id );

			// 4. Migrate course taxonomies (categories and tags)
			( new CourseTaxonomies() )->migrate( $course_id );

			// 5. Fire course migrated hook for PostMetaFactory CourseMeta
			do_action( 'tlmt_course_migrated', $course_id, MigrationTypes::SENSEI_TO_TUTOR );

			// 6. Mark course as migrated from Sensei
			update_post_meta( $course_id, '_was_sensei_course', true );

			return true;
		}

		/**
		 * Get ordered modules for a Sensei course.
		 *
		 * @param int $course_id Course ID.
		 * @return array
		 */
		private function get_course_modules( int $course_id ): array {
			if ( function_exists( 'Sensei' ) && isset( Sensei()->modules ) && method_exists( Sensei()->modules, 'get_course_modules' ) ) {
				$modules = Sensei()->modules->get_course_modules( $course_id );
				if ( is_array( $modules ) ) {
					return $modules;
				}
			}

			$modules = wp_get_post_terms( $course_id, 'module' );
			if ( is_wp_error( $modules ) || empty( $modules ) ) {
				return array();
			}

			$order = get_post_meta( $course_id, '_module_order', true );
			if ( ! $order ) {
				$order = get_post_meta( $course_id, '_course_module_order', true );
			}

			if ( is_array( $order ) ) {
				$ordered_modules   = array();
				$unordered_modules = array();
				foreach ( $modules as $module ) {
					$order_key = array_search( $module->term_id, $order );
					if ( false !== $order_key ) {
						$ordered_modules[ $order_key ] = $module;
					} else {
						$unordered_modules[] = $module;
					}
				}
				ksort( $ordered_modules );
				return array_merge( $ordered_modules, $unordered_modules );
			}

			return $modules;
		}

		/**
		 * Get lessons belonging to a specific module and course.
		 *
		 * @param int $course_id Course ID.
		 * @param int $module_term_id Module term ID.
		 * @return array
		 */
		private function get_module_lessons_for_course( int $course_id, int $module_term_id ): array {
			if ( function_exists( 'Sensei' ) && isset( Sensei()->modules ) && method_exists( Sensei()->modules, 'get_lessons_query' ) ) {
				$query = Sensei()->modules->get_lessons_query( $course_id, $module_term_id, 'any' );
				if ( $query instanceof \WP_Query && ! empty( $query->posts ) ) {
					return $query->posts;
				}
			}

			$args = array(
				'post_type'      => array( 'lesson', tutor()->lesson_post_type ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'module',
						'field'    => 'term_id',
						'terms'    => $module_term_id,
					),
				),
				'meta_query'     => array(
					array(
						'key'   => '_lesson_course',
						'value' => $course_id,
					),
				),
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			);

			$lessons = get_posts( $args );

			$order_key = '_order_module_' . $module_term_id;
			usort(
				$lessons,
				function ( $a, $b ) use ( $order_key ) {
					$order_a = get_post_meta( $a->ID, $order_key, true );
					$order_b = get_post_meta( $b->ID, $order_key, true );

					if ( '' !== $order_a && '' !== $order_b && (int) $order_a !== (int) $order_b ) {
						return (int) $order_a <=> (int) $order_b;
					}

					return (int) $a->menu_order <=> (int) $b->menu_order;
				}
			);

			return $lessons;
		}

		/**
		 * Get unassigned / orphan lessons for a course.
		 *
		 * @param int   $course_id Course ID.
		 * @param array $already_migrated_ids IDs of lessons already parented to a module.
		 * @return array
		 */
		private function get_orphan_lessons_for_course( int $course_id, array $already_migrated_ids ): array {
			$args = array(
				'post_type'      => array( 'lesson', tutor()->lesson_post_type ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_lesson_course',
						'value' => $course_id,
					),
				),
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			);

			$all_lessons = get_posts( $args );
			$orphans     = array();

			foreach ( $all_lessons as $lesson ) {
				if ( ! in_array( (int) $lesson->ID, $already_migrated_ids, true ) ) {
					$orphans[] = $lesson;
				}
			}

			return $orphans;
		}

		/**
		 * Scaffold order migration handler
		 *
		 * @return void
		 */
		public function migrate_sensei_orders() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			wp_send_json_success(
				array(
					'step'     => 'orders',
					'migrated' => 0,
				)
			);
		}

		/**
		 * Scaffold reviews migration handler
		 *
		 * @return void
		 */
		public function migrate_sensei_reviews() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			wp_send_json_success(
				array(
					'step'     => 'reviews',
					'migrated' => 0,
				)
			);
		}
	}
}
