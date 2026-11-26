<?php
/**
 * Manage Sensei LMS to Tutor LMS migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

use Themeum\TutorLMSMigrationTool\Factories\OrderFactory;
use Themeum\TutorLMSMigrationTool\Factories\ProductFactory;
use Themeum\TutorLMSMigrationTool\Factories\ReviewFactory;
use Themeum\TutorLMSMigrationTool\Factories\StudentProgressFactory;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\SenseiMigration\CourseTaxonomies;
use Themeum\TutorLMSMigrationTool\SenseiMigration\Enrollments;
use Themeum\TutorLMSMigrationTool\SenseiMigration\Quizzes\QuizMigrator;

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

			if ( 'sensei' !== $migration_vendor ) {
				return;
			}

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

			Utils::convert_all_sensei_teachers_to_tutor_instructors();
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
					'icon'      => 'tutor-icon-export',
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
		public function _get_sensei_live_progress_course_migrating_info() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
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
				case 'enrollments':
					$this->migrate_sensei_enrollments();
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

			$is_batch   = isset( $_POST['batch'] ) && '1' === (string) sanitize_text_field( wp_unslash( $_POST['batch'] ) );
			$batch_size = isset( $_POST['batch_size'] ) ? max( 1, (int) $_POST['batch_size'] ) : 10;

			$is_first_batch = ! empty( $_POST['sensei_course_migration_start'] );
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				$remaining_total = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
						'course'
					)
				);
				update_option( '_tutor_sensei_course_migration_total', $remaining_total, false );
				Utils::convert_all_sensei_teachers_to_tutor_instructors();
			}

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
					++$migrated_count;
					update_option( '_tutor_migrated_items_count', $migrated_count );
				}
			}

			$remaining = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
					'course'
				)
			);

			$total_courses = (int) get_option( '_tutor_sensei_course_migration_total', 0 );
			if ( $total_courses < 1 ) {
				$total_courses = $migrated_count + $remaining;
			}

			$has_more = $remaining > 0;
			if ( ! $has_more ) {
				delete_option( '_tutor_sensei_course_migration_total' );
			}

			wp_send_json_success(
				array(
					'has_more'           => $has_more,
					'remaining'          => $remaining,
					'migrated'           => $migrated_count,
					'migrated_count'     => $migrated_count,
					'total'              => $total_courses,
					'total_course_count' => $total_courses,
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
			$migrated_quiz_ids   = array();
			$quiz_migrator       = new QuizMigrator();
			$last_topic_id       = 0;

			// 1. Module to Topic transformation
			if ( ! empty( $modules ) ) {
				foreach ( $modules as $module_term ) {
					if ( ! ( $module_term instanceof \WP_Term ) ) {
						continue;
					}

					++$topic_order;
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

					$last_topic_id = (int) $topic_id;
					$lessons       = $this->get_module_lessons_for_course( $course_id, (int) $module_term->term_id );
					$lesson_order  = 0;

					foreach ( $lessons as $lesson ) {
						$lesson_id             = (int) $lesson->ID;
						$migrated_lesson_ids[] = $lesson_id;
						++$lesson_order;

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

						// Migrate lesson quiz if present and has questions.
						$quiz_id = $this->get_lesson_quiz_id( $lesson_id );
						if ( $quiz_id > 0 && $this->quiz_has_questions( $quiz_id ) ) {
							$migrated_quiz_ids[] = $quiz_id;
							++$lesson_order;
							$quiz_migrator->migrate( $quiz_id, $topic_id, $course_id, $lesson_id, $lesson_order );
						}
					}
				}
			}

			// 2. Unassigned / orphan lessons fallback to "General" topic
			$orphan_lessons = $this->get_orphan_lessons_for_course( $course_id, $migrated_lesson_ids );
			if ( ! empty( $orphan_lessons ) ) {
				++$topic_order;
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
					$last_topic_id = (int) $default_topic_id;
					$orphan_order  = 0;
					foreach ( $orphan_lessons as $orphan_lesson ) {
						$orphan_id = (int) $orphan_lesson->ID;
						++$orphan_order;

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

						// Migrate orphan lesson quiz if present and has questions.
						$quiz_id = $this->get_lesson_quiz_id( $orphan_id );
						if ( $quiz_id > 0 && $this->quiz_has_questions( $quiz_id ) ) {
							$migrated_quiz_ids[] = $quiz_id;
							++$orphan_order;
							$quiz_migrator->migrate( $quiz_id, $default_topic_id, $course_id, $orphan_id, $orphan_order );
						}
					}
				}
			}

			// 3. Standalone quizzes directly under the course
			$standalone_quizzes = $this->get_standalone_quizzes_for_course( $course_id, $migrated_quiz_ids );
			if ( ! empty( $standalone_quizzes ) ) {
				$target_topic_id = $last_topic_id;
				if ( ! $target_topic_id ) {
					$target_topic_id = wp_insert_post(
						array(
							'post_type'    => 'topics',
							'post_title'   => __( 'Quizzes', 'tutor-lms-migration-tool' ),
							'post_content' => '',
							'post_status'  => 'publish',
							'post_author'  => $course->post_author,
							'post_parent'  => $course_id,
							'menu_order'   => ++$topic_order,
						)
					);
				}

				if ( $target_topic_id && ! is_wp_error( $target_topic_id ) ) {
					$quiz_order = 100;
					foreach ( $standalone_quizzes as $standalone_quiz ) {
						$s_id = (int) $standalone_quiz->ID;
						++$quiz_order;
						$quiz_migrator->migrate( $s_id, (int) $target_topic_id, $course_id, 0, $quiz_order );
					}
				}
			}

			// 3. Update Course post type to Tutor course post type and strip Sensei content from description.
			$clean_content = $this->strip_sensei_content( $course->post_content );
			$wpdb->update(
				$wpdb->posts,
				array(
					'post_type'    => $course_post_type,
					'post_content' => $clean_content,
				),
				array( 'ID' => $course_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			clean_post_cache( $course_id );

			// 3a. Link instructor to course via user meta (Tutor requires _tutor_instructor_course_id).
			$author_id = (int) $course->post_author;
			if ( $author_id > 0 ) {
				$already_linked = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(umeta_id) FROM {$wpdb->usermeta}
						WHERE user_id = %d
						AND meta_key = '_tutor_instructor_course_id'
						AND meta_value = %d",
						$author_id,
						$course_id
					)
				);
				if ( ! $already_linked ) {
					add_user_meta( $author_id, '_tutor_instructor_course_id', $course_id );
				}
			}

			// 4. Migrate course taxonomies (categories and tags)
			( new CourseTaxonomies() )->migrate( $course_id );

			// 5. Fire course migrated hook for PostMetaFactory CourseMeta
			do_action( 'tlmt_course_migrated', $course_id, MigrationTypes::SENSEI_TO_TUTOR );

			// 6. Monetization Product Attachment
			$monetize_by      = function_exists( 'tutils' ) ? tutils()->get_option( 'monetize_by' ) : 'wc';
			$product_migrator = ProductFactory::create( $monetize_by, MigrationTypes::SENSEI_TO_TUTOR );
			$product_migrator->migrate( $course_id, get_the_title( $course_id ) );

			// 7. Student Enrollments
			$enrollments = new Enrollments();
			$enrollments->migrate_course_enrollments( $course_id );

			// 8. Student Progress (Lesson completions + Quiz attempts)
			$progress_migrator = StudentProgressFactory::create( MigrationTypes::SENSEI_TO_TUTOR );
			$progress_migrator->migrate( $course_id );

			// 9. Mark course as migrated from Sensei
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
					$order_key = array_search( (int) $module->term_id, array_map( 'intval', $order ), true );
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
				function ( $a, $b ) use ( $order_key, $course_id ) {
					$order_a = get_post_meta( $a->ID, $order_key, true );
					$order_b = get_post_meta( $b->ID, $order_key, true );

					if ( '' !== $order_a && '' !== $order_b && (int) $order_a !== (int) $order_b ) {
						return (int) $order_a <=> (int) $order_b;
					}

					$course_order_key = '_order_' . $course_id;
					$course_order_a   = get_post_meta( $a->ID, $course_order_key, true );
					$course_order_b   = get_post_meta( $b->ID, $course_order_key, true );

					if ( '' !== $course_order_a && '' !== $course_order_b && (int) $course_order_a !== (int) $course_order_b ) {
						return (int) $course_order_a <=> (int) $course_order_b;
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

			$order_key = '_order_' . $course_id;
			usort(
				$orphans,
				function ( $a, $b ) use ( $order_key ) {
					$order_a = get_post_meta( $a->ID, $order_key, true );
					$order_b = get_post_meta( $b->ID, $order_key, true );

					if ( '' !== $order_a && '' !== $order_b && (int) $order_a !== (int) $order_b ) {
						return (int) $order_a <=> (int) $order_b;
					}

					return (int) $a->menu_order <=> (int) $b->menu_order;
				}
			);

			return $orphans;
		}

		/**
		 * Check whether a Sensei quiz has at least one question.
		 *
		 * @param int $quiz_id Sensei quiz post ID.
		 * @return bool
		 */
		private function quiz_has_questions( int $quiz_id ): bool {
			$questions = get_posts(
				array(
					'post_type'      => array( 'question', 'multiple_question' ),
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => '_quiz_id',
							'value' => $quiz_id,
						),
					),
				)
			);

			return ! empty( $questions );
		}

		/**
		 * Strip Sensei-specific Gutenberg blocks and shortcodes from post content.
		 *
		 * @param string $content Raw post content.
		 * @return string Cleaned content.
		 */
		private function strip_sensei_content( string $content ): string {
			// Remove Sensei Gutenberg blocks (including nested content).
			$content = preg_replace( '/<!--\s*wp:sensei-lms\/[^>]*-->.*?<!--\s*\/wp:sensei-lms\/[^>]*-->/s', '', $content );
			// Remove self-closing Sensei blocks.
			$content = preg_replace( '/<!--\s*wp:sensei-lms\/[^>]*\/-->/s', '', $content );
			// Remove common Sensei shortcodes.
			$content = preg_replace( '/\[course_lessons[^\]]*\]/', '', $content );
			$content = preg_replace( '/\[sensei_[^\]]*\]/', '', $content );
			return trim( $content );
		}

		/**
		 * Get quiz ID associated with a lesson.
		 *
		 * @param int $lesson_id Lesson post ID.
		 * @return int Quiz post ID or 0.
		 */
		private function get_lesson_quiz_id( int $lesson_id ): int {
			$quiz_id = (int) get_post_meta( $lesson_id, '_lesson_quiz', true );
			if ( $quiz_id > 0 ) {
				return $quiz_id;
			}

			$quizzes = get_posts(
				array(
					'post_type'      => array( 'quiz', tutor()->quiz_post_type ),
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'meta_query'     => array(
						array(
							'key'   => '_quiz_lesson',
							'value' => $lesson_id,
						),
					),
				)
			);

			if ( ! empty( $quizzes ) ) {
				return (int) $quizzes[0]->ID;
			}

			return 0;
		}

		/**
		 * Get standalone quizzes associated with a course.
		 *
		 * @param int   $course_id Course post ID.
		 * @param array $already_migrated_ids Quiz IDs already migrated.
		 * @return array
		 */
		private function get_standalone_quizzes_for_course( int $course_id, array $already_migrated_ids ): array {
			$quizzes = get_posts(
				array(
					'post_type'      => array( 'quiz', tutor()->quiz_post_type ),
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'meta_query'     => array(
						array(
							'key'   => '_quiz_course',
							'value' => $course_id,
						),
					),
				)
			);

			$standalone = array();
			foreach ( $quizzes as $quiz ) {
				if ( ! in_array( (int) $quiz->ID, $already_migrated_ids, true ) ) {
					$standalone[] = $quiz;
				}
			}

			return $standalone;
		}

		/**
		 * Sensei enrollments and student progress migration handler.
		 *
		 * Processes Sensei course enrollments from the sensei_lms_progress table in
		 * batches, creating tutor_enrolled posts and migrating lesson completions and
		 * quiz attempt history for each enrolled student.
		 *
		 * @return void
		 */
		public function migrate_sensei_enrollments() {
			global $wpdb;

			$is_first_batch = ! empty( $_POST['sensei_enrollment_migration_start'] );
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				delete_option( '_tutor_sensei_enrollment_migration_total' );
			}

			$limit  = isset( $_POST['batch_size'] ) ? max( 1, (int) $_POST['batch_size'] ) : 50;
			$offset = (int) get_option( '_tutor_migrated_items_count', 0 );

			$enrollments_migrator = new \Themeum\TutorLMSMigrationTool\SenseiMigration\Enrollments();
			$result               = $enrollments_migrator->migrate_batch( $offset, $limit );

			// Retrieve or seed the total enrollment count so the progress bar has an upper bound.
			$total = (int) get_option( '_tutor_sensei_enrollment_migration_total', 0 );
			if ( $total < 1 ) {
				$progress_table = $wpdb->prefix . 'sensei_lms_progress';
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $progress_table ) ) === $progress_table ) {
					// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$total = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$progress_table} WHERE type = 'course' AND user_id > 0" );
					// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
				if ( $total > 0 ) {
					update_option( '_tutor_sensei_enrollment_migration_total', $total, false );
				}
			}

			// Advance the offset by one full batch so the next call scans the next window.
			$processed = $offset + $limit;
			update_option( '_tutor_migrated_items_count', $processed );

			$has_more = ! empty( $result['has_more'] );
			if ( ! $has_more ) {
				delete_option( '_tutor_sensei_enrollment_migration_total' );
			}

			wp_send_json_success(
				array(
					'step'           => 'enrollments',
					'migrated'       => min( $processed, max( $total, $processed ) ),
					'migrated_count' => min( $processed, max( $total, $processed ) ),
					'total'          => $total > 0 ? $total : 1,
					'remaining'      => $result['remaining'],
					'has_more'       => $has_more,
				)
			);
		}

		/**
		 * Sensei orders migration handler
		 *
		 * @return void
		 */
		public function migrate_sensei_orders() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			$is_first_batch = ! empty( $_POST['sensei_order_migration_start'] );
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				delete_option( '_tutor_sensei_order_migration_total' );
			}

			$limit = isset( $_POST['limit'] ) ? max( 1, (int) $_POST['limit'] ) : 50;
			if ( isset( $_POST['batch_size'] ) ) {
				$limit = max( 1, (int) $_POST['batch_size'] );
			}

			$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : (int) get_option( '_tutor_migrated_items_count', 0 );

			$order_migrator = new \Themeum\TutorLMSMigrationTool\SenseiMigration\Orders\OrderMigrator();
			$result         = $order_migrator->migrate( $offset, $limit );

			$migrated_count = $offset + (int) $result['migrated'];
			update_option( '_tutor_migrated_items_count', $migrated_count );

			$total_orders = (int) get_option( '_tutor_sensei_order_migration_total', 0 );
			if ( $total_orders < 1 ) {
				$total_orders = (int) ( $result['total'] ?? ( $result['migrated'] + $result['remaining'] ) );
				if ( $total_orders > 0 ) {
					update_option( '_tutor_sensei_order_migration_total', $total_orders, false );
				}
			}

			$has_more = ! empty( $result['has_more'] );
			if ( ! $has_more ) {
				delete_option( '_tutor_sensei_order_migration_total' );
			}

			wp_send_json_success(
				array(
					'step'           => 'orders',
					'migrated'       => $migrated_count,
					'migrated_count' => $migrated_count,
					'total'          => $total_orders,
					'remaining'      => $result['remaining'],
					'has_more'       => $has_more,
				)
			);
		}

		/**
		 * Sensei reviews migration handler
		 *
		 * @return void
		 */
		public function migrate_sensei_reviews() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			$is_first_batch = ! empty( $_POST['sensei_review_migration_start'] );
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				delete_option( '_tutor_sensei_review_migration_total' );
			}

			$limit  = isset( $_POST['batch_size'] ) ? max( 1, (int) $_POST['batch_size'] ) : 100;
			$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : (int) get_option( '_tutor_migrated_items_count', 0 );

			$review_migrator = ReviewFactory::create( MigrationTypes::SENSEI_TO_TUTOR );
			$migrated_batch  = $review_migrator->migrate( 0, $offset, $limit );

			$migrated_count = $offset + $migrated_batch;
			update_option( '_tutor_migrated_items_count', $migrated_count );

			$total_reviews = (int) get_option( '_tutor_sensei_review_migration_total', 0 );
			if ( $total_reviews < 1 ) {
				$total_reviews = $review_migrator->get_total_count();
				if ( $total_reviews > 0 ) {
					update_option( '_tutor_sensei_review_migration_total', $total_reviews, false );
				}
			}

			$has_more = $migrated_batch >= $limit;
			if ( ! $has_more ) {
				delete_option( '_tutor_sensei_review_migration_total' );
			}

			wp_send_json_success(
				array(
					'step'           => 'reviews',
					'migrated'       => $migrated_count,
					'migrated_count' => $migrated_count,
					'total'          => $total_reviews > 0 ? $total_reviews : 1,
					'remaining'      => max( 0, $total_reviews - $migrated_count ),
					'has_more'       => $has_more,
					'failed'         => array(),
				)
			);
		}
	}
}
