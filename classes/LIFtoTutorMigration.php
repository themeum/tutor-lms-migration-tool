<?php
/**
 * Manage LifterLMS to Tutor migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 1.0.0
 */

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\LIFMigration\Quizzes\FillInBlanksTransformer;
use Themeum\TutorLMSMigrationTool\LIFMigration\Reviews as LIFReviews;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Helper;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Subscriptions;
use Themeum\TutorLMSMigrationTool\MigrationTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'LIFtoTutorMigration' ) ) {
	/**
	 * Lifter migration class
	 */
	class LIFtoTutorMigration {

		/**
		 * Courses processed per AJAX request.
		 * Keep small — MAMP FastCGI idle timeout is ~30s.
		 *
		 * @since 2.6.0
		 */
		const COURSE_BATCH_SIZE = 1;

		/**
		 * Student–course enrollment pairs processed per AJAX request.
		 *
		 * @since 2.6.0
		 */
		const ENROLLMENT_BATCH_SIZE = 50;

		/**
		 * Orders processed per AJAX request.
		 *
		 * @since 2.6.0
		 */
		const ORDER_BATCH_SIZE = 20;

		/**
		 * Subscriptions processed per AJAX request.
		 *
		 * @since 2.6.0
		 */
		const SUBSCRIPTION_BATCH_SIZE = 5;

		/**
		 * Reviews processed per AJAX request.
		 *
		 * @since 2.6.0
		 */
		const REVIEW_BATCH_SIZE = 50;

		/**
		 * Option key for total Lifter courses at migration start.
		 *
		 * @since 2.6.0
		 */
		const COURSE_MIGRATION_TOTAL_OPT = '_tlmt_lif_course_migration_total';

		/**
		 * Option key for total Lifter subscriptions at migration start.
		 *
		 * @since 2.6.0
		 */
		const SUBSCRIPTION_MIGRATION_TOTAL_OPT = '_tlmt_lif_subscriptions_migration_total';

		/**
		 * Option key for total Lifter orders at migration start.
		 *
		 * @since 2.6.0
		 */
		const ORDER_MIGRATION_TOTAL_OPT = '_tlmt_lif_order_migration_total';

		/**
		 * Option key for total Lifter reviews at migration start.
		 *
		 * @since 2.6.0
		 */
		const REVIEW_MIGRATION_TOTAL_OPT = '_tlmt_lif_review_migration_total';

		/**
		 * Constructor function
		 */
		public function __construct() {
			add_filter( 'tutor_tool_pages', array( $this, 'tutor_tool_pages' ) );
			add_action( 'wp_ajax_insert_tutor_migration_data', array( $this, 'insert_tutor_migration_data' ) );
			add_action( 'wp_ajax_lif_migrate_all_data_to_tutor', array( $this, 'lif_migrate_all_data_to_tutor' ) );
			add_action( 'wp_ajax_tlmt_reset_migrated_items_count', array( $this, 'tlmt_reset_migrated_items_count' ) );

			add_action( 'wp_ajax__get_lif_live_progress_course_migrating_info', array( $this, '_get_lif_live_progress_course_migrating_info' ) );
			add_action( 'tutor_action_migrate_lif_orders_earning', array( $this, 'migrate_lif_orders_earning' ) );
			add_action( 'tutor_action_migrate_lif_orders', array( $this, 'migrate_lif_orders' ) );
			//add_action( 'tutor_action_migrate_lif_reviews', array( $this, 'migrate_lif_reviews' ) );
			add_action( 'wp_ajax_tutor_import_from_xml_lif', array( $this, 'tutor_import_from_xml_lif' ) );
			add_action( 'tutor_action_tutor_lif_export_xml', array( $this, 'tutor_lif_export_xml' ) );
		}


		/**
		 * Insert function
		 *
		 * @return void
		 */
		public function insert_tutor_migration_data() {
			global $wpdb;

			tutor_utils()->checking_nonce();
			
			Utils::check_course_access();
			
			$tutor_migration_table_data = array(
				'migration_type'   => $_POST['migration_type'],
				'migration_vendor' => $_POST['migration_vendor'],
				'created_by'       => get_current_user_id(),
				'created_at'       => current_time( 'mysql' ),
			);

			$wpdb->insert(
				$wpdb->prefix . 'tutor_migration',
				$tutor_migration_table_data
			);
		}

		/**
		 * Tutor tools pages
		 *
		 * @param [void] $pages Comment for the $pages parameter.
		 * @return $pages
		 */
		public function tutor_tool_pages( $pages ) {

			if ( defined( 'LLMS_VERSION' ) ) {
				$pages['migration_lif'] = array(
					'label'     => __( 'LifterLMS Migration', 'tutor' ),
					'slug'      => 'migration_lif',
					'desc'      => __( 'LifterLMS Migration', 'tutor' ),
					'template'  => 'migration_lifter',
					'view_path' => TLMT_PATH . 'views/',
					'icon'      => 'tutor-icon-brand-lifter',
					'blocks'    => array(
						'block' => array(),
					),
				);
			}

			return $pages;
		}


		/**
		 * Delete Item Count
		 */
		public function tlmt_reset_migrated_items_count() {
			tutor_utils()->checking_nonce();
			
			Utils::check_course_access();

			delete_option( '_tutor_migrated_items_count' );
		}
		/**
		 * Lifter to tutor data migrate
		 *
		 * @since 1.0.0
		 * @since 2.6.0 Return batch payloads for courses, enrollments, orders, and reviews.
		 *
		 * @return void
		 */
		public function lif_migrate_all_data_to_tutor() {
			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			if ( ! isset( $_POST['migrate_type'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid migration type.', 'tutor-lms-migration-tool' ) ) );
			}

			$migrate_type = sanitize_text_field( wp_unslash( $_POST['migrate_type'] ) );

			try {
				switch ( $migrate_type ) {
					case ContentTypes::COURSE:
						$result = $this->lif_migrate_course_to_tutor();
						wp_send_json_success(
							array_merge(
								array(
									'step'    => ContentTypes::COURSE,
									'message' => ! empty( $result['has_more'] )
										? __( 'Course batch migrated successfully.', 'tutor-lms-migration-tool' )
										: __( 'Courses migrated successfully.', 'tutor-lms-migration-tool' ),
								),
								is_array( $result ) ? $result : array()
							)
						);
						break;
					case ContentTypes::ENROLLMENTS:
						$result = $this->lif_enrollments_migrate();
						if ( false === $result ) {
							wp_send_json_error(
								array(
									'step'    => ContentTypes::ENROLLMENTS,
									'message' => ErrorHandler::get_error_message( ContentTypes::ENROLLMENTS ),
								)
							);
						}
						wp_send_json_success(
							array_merge(
								array(
									'step'    => ContentTypes::ENROLLMENTS,
									'message' => ! empty( $result['has_more'] )
										? __( 'Enrollment batch migrated successfully.', 'tutor-lms-migration-tool' )
										: __( 'Enrollments migrated successfully.', 'tutor-lms-migration-tool' ),
								),
								is_array( $result ) ? $result : array()
							)
						);
						break;
					case ContentTypes::ORDERS:
						$result = $this->migrate_lif_orders();
						if ( false === $result ) {
							wp_send_json_error(
								array(
									'step'    => ContentTypes::ORDERS,
									'message' => ErrorHandler::get_error_message( ContentTypes::ORDERS ),
								)
							);
						}
						wp_send_json_success(
							array_merge(
								array(
									'step'    => ContentTypes::ORDERS,
									'message' => ! empty( $result['has_more'] )
										? __( 'Order batch migrated successfully.', 'tutor-lms-migration-tool' )
										: __( 'Orders migrated successfully.', 'tutor-lms-migration-tool' ),
								),
								is_array( $result ) ? $result : array()
							)
						);
						break;
					case ContentTypes::SUBSCRIPTIONS:
						$result = $this->lif_subscriptions_migrate();
						if ( false === $result ) {
							wp_send_json_error(
								array(
									'step'    => ContentTypes::SUBSCRIPTIONS,
									'message' => ErrorHandler::get_error_message( ContentTypes::SUBSCRIPTIONS ),
								)
							);
						}
						wp_send_json_success(
							array_merge(
								array(
									'step'    => ContentTypes::SUBSCRIPTIONS,
									'message' => ! empty( $result['has_more'] )
										? __( 'Subscription batch migrated successfully.', 'tutor-lms-migration-tool' )
										: __( 'Subscriptions migrated successfully.', 'tutor-lms-migration-tool' ),
								),
								is_array( $result ) ? $result : array()
							)
						);
						break;
					case ContentTypes::COURSE_REVIEWS:
						$result = $this->migrate_lif_reviews();
						if ( false === $result ) {
							wp_send_json_error(
								array(
									'step'    => ContentTypes::COURSE_REVIEWS,
									'message' => ErrorHandler::get_error_message( ContentTypes::COURSE_REVIEWS ),
								)
							);
						}
						wp_send_json_success(
							array_merge(
								array(
									'step'    => ContentTypes::COURSE_REVIEWS,
									'message' => ! empty( $result['has_more'] )
										? __( 'Review batch migrated successfully.', 'tutor-lms-migration-tool' )
										: __( 'Reviews migrated successfully.', 'tutor-lms-migration-tool' ),
								),
								is_array( $result ) ? $result : array()
							)
						);
						break;
					default:
						wp_send_json_error( array( 'message' => __( 'Invalid migration type.', 'tutor-lms-migration-tool' ) ) );
				}
			} catch ( \Throwable $th ) {
				error_log( $th->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				wp_send_json_error(
					array(
						'step'    => $migrate_type,
						'message' => $th->getMessage(),
					)
				);
			}
		}

		/**
		 * Course migrate function.
		 *
		 * Processes courses in batches so large migrations stay under server timeouts.
		 * Migrated courses change post_type, so each request pulls the next remaining
		 * LifterLMS courses without needing a persistent offset.
		 *
		 * @since 1.0.0
		 * @since 2.6.0 Added batch processing return payload.
		 *
		 * @return array{
		 *     migrated: int,
		 *     total: int,
		 *     total_course_count: int,
		 *     remaining: int,
		 *     has_more: bool,
		 *     batch_size: int
		 * }
		 */
		public function lif_migrate_course_to_tutor() {
			global $wpdb;

			$this->raise_migration_resource_limits();

			$batch_size = (int) apply_filters( 'tlmt_lif_course_migration_batch_size', self::COURSE_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::COURSE_BATCH_SIZE;
			}

			$remaining_total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'course'" );

			$is_first_batch = ! empty( $_POST['lif_course_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in lif_migrate_all_data_to_tutor().

			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				update_option( self::COURSE_MIGRATION_TOTAL_OPT, $remaining_total, false );

				if ( Helper::is_subscription_migration_available() ) {
					( new Subscriptions() )->reset_map();
				}
			}

			$total_courses = (int) get_option( self::COURSE_MIGRATION_TOTAL_OPT, $remaining_total );
			if ( $total_courses < 1 ) {
				$total_courses = $remaining_total;
			}

			if ( $remaining_total < 1 ) {
				$already = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_was_lif_course'" );
				return array(
					'migrated'           => max( $total_courses, $already ),
					'total'              => max( $total_courses, $already ),
					'total_course_count' => max( $total_courses, $already ),
					'remaining'          => 0,
					'has_more'           => false,
					'batch_size'         => $batch_size,
				);
			}

			$lif_courses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'course' ORDER BY ID ASC LIMIT %d",
					$batch_size
				)
			);

			$course_i         = (int) get_option( '_tutor_migrated_items_count' );
			$course_post_type = tutor()->course_post_type;
			foreach ( $lif_courses as $lif_course ) {
				++$course_i;
				$course_id = (int) $lif_course->ID;
				try {
					$this->migrate_course( $course_id );
					update_option( '_tutor_migrated_items_count', $course_i );
				} catch ( \Throwable $th ) {
					// Convert CPT so the next batch does not retry this course forever.
					$this->mark_course_as_tutor( $course_id, $course_post_type );
					update_post_meta( $course_id, '_tlmt_lif_course_migration_failed', $th->getMessage() );
					ErrorHandler::set_error(
						ContentTypes::COURSE,
						sprintf(
							/* translators: 1: course id, 2: error message */
							__( 'Failed to migrate course #%1$d: %2$s', 'tutor-lms-migration-tool' ),
							$course_id,
							$th->getMessage()
						)
					);
					error_log( $th->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					update_option( '_tutor_migrated_items_count', $course_i );
				}
			}

			$remaining_after = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'course'" );
			$has_more        = $remaining_after > 0;
			$migrated_count  = max( 0, $total_courses - $remaining_after );

			if ( ! $has_more ) {
				delete_option( self::COURSE_MIGRATION_TOTAL_OPT );
			}

			return array(
				'migrated'           => $migrated_count,
				'total'              => $total_courses,
				'total_course_count' => $total_courses,
				'remaining'          => $remaining_after,
				'has_more'           => $has_more,
				'batch_size'         => $batch_size,
			);
		}

		/**
		 * Raise time/memory limits for a migration batch.
		 *
		 * @since 2.6.0
		 *
		 * @return void
		 */
		private function raise_migration_resource_limits() {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			$current = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
			$target  = 512 * MB_IN_BYTES;

			if ( -1 !== $current && $current < $target ) {
				@ini_set( 'memory_limit', '512M' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.memory_limit_Disallowed
			}
		}

		/**
		 *
		 * Get Live Update about course migrating info
		 */

		public function _get_lif_live_progress_course_migrating_info() {
			$migrated_count = (int) get_option( '_tutor_migrated_items_count' );
			wp_send_json_success( array( 'migrated_count' => $migrated_count ) );
		}
		/**
		 * Migrate course
		 *
		 * @param [type] $course_id for course.
		 * @return void
		 */
		public function migrate_course( $course_id ) {
			global $wpdb;

			$course_post_type = tutor()->course_post_type;

			if ( ! function_exists( 'llms_get_post' ) ) {
				$this->mark_course_as_tutor( $course_id, $course_post_type );
				return;
			}

			$course = llms_get_post( $course_id );

			if ( ! $course ) {
				// Convert anyway so the next batch does not retry a missing Lifter course forever.
				$this->mark_course_as_tutor( $course_id, $course_post_type );
				return;
			}

			$course           = new LLMS_Course( $course_id );
			$sections         = $course->get_sections();
			$lesson_post_type = tutor()->lesson_post_type;
			$section_ids      = array();

			$tutor_course = array();
			$i            = 0;
			if ( $sections ) {
				foreach ( $sections as $section ) {
					$i++;
					$section_id = (int) ( $section->get( 'id' ) ?? $section->id ?? 0 );
					if ( $section_id > 0 ) {
						$section_ids[] = $section_id;
					}
					$topic = array(
						'post_type'    => 'topics',
						'post_title'   => $section->post->post_title,
						'post_content' => $section->post->post_content,
						'post_status'  => 'publish',
						'post_author'  => (int) $course->get( 'author' ),
						'post_parent'  => $course_id,
						'menu_order'   => $i,
						'items'        => array(),
					);

					$lessons    = $section->get_lessons();
					$item_order = 0;

					foreach ( $lessons as $lesson ) {
						++$item_order;
						$topic['items'][] = array(
							'ID'           => $lesson->id,
							'post_type'    => $lesson_post_type,
							'post_title'   => $lesson->post->post_title,
							'post_content' => $lesson->post->post_content,
							'post_status'  => 'publish',
							'post_parent'  => '{topic_id}',
							'menu_order'   => $item_order,
						);

						if ( $lesson->has_quiz() ) {
							$quiz = $lesson->get_quiz();
							if ( $quiz ) {
								++$item_order;
								$topic['items'][] = array(
									'ID'           => $quiz->get( 'id' ),
									'post_type'    => 'tutor_quiz',
									'post_title'   => $quiz->get( 'title' ),
									'post_content' => $quiz->post->post_content,
									'post_status'  => 'publish',
									'post_parent'  => '{topic_id}',
									'menu_order'   => $item_order,
								);
							}
						}

						if ( is_plugin_active( 'lifterlms-assignments/lifterlms-assignments.php' )
							&& function_exists( 'llms_lesson_has_assignment' )
							&& llms_lesson_has_assignment( $lesson )
						) {
							$assignment = llms_lesson_get_assignment( $lesson );
							if ( $assignment ) {
								++$item_order;
								$topic['items'][] = array(
									'ID'           => $assignment->id,
									'post_type'    => 'tutor_assignments',
									'post_title'   => $assignment->post->post_title,
									'post_content' => $assignment->post->post_content,
									'post_status'  => 'publish',
									'post_parent'  => '{topic_id}',
									'menu_order'   => $item_order,
								);
							}
						}
					}

					$tutor_course[] = $topic;
				}
			}

			if ( tutils()->count( $tutor_course ) ) {
				foreach ( $tutor_course as $course_topic ) {
					// Remove items from this topic.
					$lessons = $course_topic['items'];
					unset( $course_topic['items'] );

					// Insert Topic post type.
					$topic_id = wp_insert_post( $course_topic );

					// Update lesson from lifter to TutorLMS.
					foreach ( $lessons as $lesson ) {
						$quiz_option = null;

						if ( 'tutor_quiz' === $lesson['post_type'] ) {
							$quiz_id     = (int) tutils()->array_get( 'ID', $lesson );
							$quiz_option = $this->build_tutor_quiz_option_from_lif( $quiz_id );
							$this->migrate_lif_quiz_questions( $quiz_id );
						}

						$lesson['post_parent'] = $topic_id;
						wp_update_post( $lesson );

						$lesson_id = tutils()->array_get( 'ID', $lesson );
						if ( $lesson_id ) {
							update_post_meta( $lesson_id, '_tutor_course_id_for_lesson', $course_id );
						}

						if ( is_array( $quiz_option ) && $lesson_id ) {
							update_post_meta( $lesson_id, 'tutor_quiz_option', $quiz_option );
						}

						$_lif_preview = get_post_meta( $lesson_id, '_llms_free_lesson', true );
						if ( 'yes' === $_lif_preview ) {
							update_post_meta( $lesson_id, '_is_preview', 1 );
						} else {
							delete_post_meta( $lesson_id, '_is_preview' );
						}

						if ( $lesson_id && tutor()->lesson_post_type === $lesson['post_type'] ) {
							do_action( 'tlmt_lesson_migrated', $lesson_id, MigrationTypes::LIF_TO_TUTOR );
						}
					}
				}
			}

			// Lifter section posts are replaced by Tutor topics — remove the orphans.
			foreach ( array_unique( $section_ids ) as $section_id ) {
				wp_delete_post( (int) $section_id, true );
			}

			// Migrate Course CPT first so ActionHandler hooks see Tutor post types.
			$tutor_course = array(
				'ID'        => $course_id,
				'post_type' => $course_post_type,
			);
			wp_update_post( $tutor_course );
			update_post_meta( $course_id, '_was_lif_course', true );

			/**
			 * Course meta (PostMetaFactory) + taxonomies (ActionHandler).
			 *
			 * Term relationships persist after CPT conversion, matching the LD pipeline.
			 */
			do_action( 'tlmt_course_migrated', $course_id, MigrationTypes::LIF_TO_TUTOR );

			/**
			 * Pricing / product attach — native Tutor ecommerce via ProductFactory,
			 * or WC / EDD inline below.
			 */
			update_post_meta( $course_id, '_tutor_course_price_type', 'free' );
			$tutor_monetize_by = tutils()->get_option( 'monetize_by' );

			if ( function_exists( 'tutor_utils' ) && tutor_utils()->is_monetize_by_tutor() ) {
				do_action( 'tlmt_attach_product', $course_id, MigrationTypes::LIF_TO_TUTOR );
			} elseif ( tutils()->has_wc() && ( 'wc' === $tutor_monetize_by || '-1' === $tutor_monetize_by || 'free' === $tutor_monetize_by ) ) {
				global $wpdb;
				$order_plan_id    = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_llms_product_id' AND meta_value = %d ", $course_id ) );
				$_llms_price      = get_post_meta( $order_plan_id, '_llms_price', true );
				$_llms_sale_price = get_post_meta( $course_id, '_llms_sale_price', true );
				$llms_product_id  = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_llms_wc_pid' AND post_id = %d ", $order_plan_id ) );

				if ( $_llms_price ) {

					update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );

					$product_id = wp_insert_post(
						array(
							'post_title'   => $course->post->post_title . ' Product',
							'post_content' => '',
							'post_status'  => 'publish',
							'post_type'    => 'product',
						)
					);

					if ( $product_id ) {

						$product_metas = array(
							'_stock_status'      => 'instock',
							'total_sales'        => '0',
							'_regular_price'     => $_llms_price,
							'_sale_price'        => $_llms_sale_price,
							'_price'             => $_llms_price,
							'_sold_individually' => 'no',
							'_manage_stock'      => 'no',
							'_backorders'        => 'no',
							'_stock'             => '',
							'_virtual'           => 'yes',
							'_tutor_product'     => 'yes',
						);

						foreach ( $product_metas as $key => $value ) {
							update_post_meta( $product_id, $key, $value );
						}
					}

					/**
					 * Attaching product to course
					 */
					update_post_meta( $course_id, '_tutor_course_product_id', $product_id );
					$course_thumbnail = get_post_meta( $course_id, '_thumbnail_id', true );
					if ( $course_thumbnail ) {
						set_post_thumbnail( $product_id, $course_thumbnail );
					}
				} elseif ( ! empty( $llms_product_id ) ) {

					update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );
					add_post_meta( $course_id, '_tutor_course_product_id', $llms_product_id );

				} else {
					update_post_meta( $course_id, '_tutor_course_price_type', 'free' );
				}
			}

			/**
			 * Create EDD Product and linked with the course
			 */
			if ( tutils()->has_edd() && $tutor_monetize_by == 'edd' ) {
				$_llms_price      = get_post_meta( $course_id, '_llms_price', true );
				$_llms_sale_price = get_post_meta( $course_id, '_llms_sale_price', true );

				if ( $_llms_price ) {
					update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );
					$product_id    = wp_insert_post(
						array(
							'post_title'   => $course->get_title() . ' Product',
							'post_content' => '',
							'post_status'  => 'publish',
							'post_type'    => 'download',
						)
					);
					$product_metas = array(
						'edd_price'                        => $_llms_price,
						'edd_variable_prices'              => array(),
						'edd_download_files'               => array(),
						'_edd_bundled_products'            => array( '0' ),
						'_edd_bundled_products_conditions' => array( 'all' ),
					);
					foreach ( $product_metas as $key => $value ) {
						update_post_meta( $product_id, $key, $value );
					}
					update_post_meta( $course_id, '_tutor_course_product_id', $product_id );
					$course_thumbnail = get_post_meta( $course_id, '_thumbnail_id', true );
					if ( $course_thumbnail ) {
						set_post_thumbnail( $product_id, $course_thumbnail );
					}
				} else {
					update_post_meta( $course_id, '_tutor_course_price_type', 'free' );
				}
			}

			// Enrollments, completions, and progress run in a separate
			// batched step (lif_enrollments_migrate) after all courses finish.
		}

		/**
		 * Migrate LifterLMS quiz questions and answers into Tutor tables.
		 *
		 * @since 2.6.0
		 *
		 * @param int $quiz_id Lifter quiz post ID (converted to tutor_quiz).
		 *
		 * @return void
		 */
		private function migrate_lif_quiz_questions( $quiz_id ) {
			global $wpdb;

			$quiz_id = (int) $quiz_id;
			if ( $quiz_id < 1 || ! function_exists( 'llms_get_post' ) ) {
				return;
			}

			$quiz = llms_get_post( $quiz_id );
			if ( ! $quiz || ! method_exists( $quiz, 'get_questions' ) ) {
				return;
			}

			$questions = $quiz->get_questions();
			if ( ! tutils()->count( $questions ) ) {
				return;
			}

			$question_order = 0;
			foreach ( $questions as $question ) {
				$question_type = $this->map_lif_question_type( get_post_meta( $question->id, '_llms_question_type', true ) );
				if ( ! $question_type ) {
					continue;
				}

				++$question_order;
				$question_mark = 1;
				if ( method_exists( $question, 'get' ) ) {
					$points = (int) $question->get( 'points' );
					if ( $points > 0 ) {
						$question_mark = $points;
					}
				}

				$has_multiple = ( 'multiple_choice' === $question_type && method_exists( $question, 'get' ) && 'yes' === $question->get( 'multi_choices' ) );

				$wpdb->insert(
					$wpdb->prefix . 'tutor_quiz_questions',
					array(
						'quiz_id'              => $quiz_id,
						'question_title'       => $question->post->post_title,
						'question_description' => $question->post->post_content,
						'question_type'        => $question_type,
						'question_mark'        => $question_mark,
						'question_settings'    => maybe_serialize( $this->build_tutor_question_settings( $question_type, $question_mark, $has_multiple ) ),
						'question_order'       => $question_order,
					)
				);

				$question_id = (int) $wpdb->insert_id;
				if ( $question_id < 1 ) {
					continue;
				}

				if ( 'fill_in_the_blank' === $question_type ) {
					$this->migrate_lif_fill_in_blank_answer( $question, $question_id );
					continue;
				}

				if ( ! method_exists( $question, 'get_choices' ) ) {
					continue;
				}

				$answer_items = $question->get_choices();
				if ( ! tutils()->count( $answer_items ) ) {
					continue;
				}

				$answer_order = 0;
				foreach ( $answer_items as $answer_item ) {
					++$answer_order;
					$wpdb->insert(
						$wpdb->prefix . 'tutor_quiz_question_answers',
						$this->build_tutor_answer_from_lif_choice( $answer_item, $question_type, $question_id, $answer_order )
					);
				}
			}
		}

		/**
		 * Build Tutor answer row fields from a Lifter question choice.
		 *
		 * Picture choices store an attachment array in `choice` (`{ id, src, … }`).
		 * Those map to Tutor multiple-choice answers with `image_id` + `answer_view_format`.
		 *
		 * @since 2.6.0
		 *
		 * @param object $answer_item   Lifter LLMS_Question_Choice.
		 * @param string $question_type Tutor question type slug.
		 * @param int    $question_id   Tutor question ID.
		 * @param int    $answer_order  Answer order.
		 *
		 * @return array
		 */
		private function build_tutor_answer_from_lif_choice( $answer_item, $question_type, $question_id, $answer_order ) {
			$choice_type = method_exists( $answer_item, 'get' ) ? (string) $answer_item->get( 'choice_type' ) : 'text';
			$choice      = method_exists( $answer_item, 'get' ) ? $answer_item->get( 'choice' ) : '';
			$correct     = method_exists( $answer_item, 'is_correct' )
				? (bool) $answer_item->is_correct()
				: (bool) ( method_exists( $answer_item, 'get' ) ? $answer_item->get( 'correct' ) : false );

			$answer_title       = '';
			$image_id           = 0;
			$answer_view_format = 'text';

			if ( 'image' === $choice_type || is_array( $choice ) ) {
				$image_id = is_array( $choice ) ? absint( $choice['id'] ?? 0 ) : 0;
				if ( $image_id > 0 ) {
					$answer_view_format = 'image';
					$alt                = (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true );
					$answer_title       = '' !== trim( $alt ) ? $alt : (string) get_the_title( $image_id );
				}
			} elseif ( is_scalar( $choice ) ) {
				$answer_title = (string) $choice;
			}

			return array(
				'belongs_question_id'   => $question_id,
				'belongs_question_type' => $question_type,
				'answer_title'          => $answer_title,
				'image_id'              => $image_id,
				'answer_view_format'    => $answer_view_format,
				'is_correct'            => $correct ? 1 : 0,
				'answer_order'          => $answer_order,
			);
		}

		/**
		 * Migrate a Lifter fill-in-the-blank question answer into Tutor tables.
		 *
		 * @since 2.6.0
		 *
		 * @param object $question    Lifter question object.
		 * @param int    $question_id Tutor question ID.
		 *
		 * @return void
		 */
		private function migrate_lif_fill_in_blank_answer( $question, int $question_id ) {
			global $wpdb;

			$correct_value = '';
			if ( method_exists( $question, 'get' ) ) {
				$correct_value = (string) $question->get( 'correct_value' );
			}
			if ( '' === $correct_value ) {
				$correct_value = (string) get_post_meta( (int) $question->id, '_llms_correct_value', true );
			}

			$passage = '';
			if ( ! empty( $question->post->post_content ) ) {
				$passage = (string) $question->post->post_content;
			} elseif ( ! empty( $question->post->post_title ) ) {
				$passage = (string) $question->post->post_title;
			}

			$transformed = ( new FillInBlanksTransformer() )->transform( $passage, $correct_value );
			if ( ! $transformed ) {
				return;
			}

			$wpdb->insert(
				$wpdb->prefix . 'tutor_quiz_question_answers',
				array(
					'belongs_question_id'   => $question_id,
					'belongs_question_type' => FillInBlanksTransformer::TUTOR_TYPE,
					'answer_title'          => $transformed['answer_title'],
					'answer_two_gap_match'  => $transformed['answer_two_gap_match'],
					'is_correct'            => 1,
					'answer_order'          => 1,
				)
			);
		}

		/**
		 * Map a LifterLMS question type to a Tutor question type.
		 *
		 * @since 2.6.0
		 *
		 * @param string $ques_type Lifter question type slug.
		 *
		 * @return string|null
		 */
		private function map_lif_question_type( $ques_type ) {
			$map = array(
				'true_false'     => 'true_false',
				'choice'         => 'multiple_choice',
				'picture_choice' => 'multiple_choice',
				'blank'          => 'fill_in_the_blank',
				'short_answer'   => 'short_answer',
				'long_answer'    => 'short_answer',
				'code'           => 'short_answer',
				'reorder'        => 'ordering',
				'upload'         => 'image_answering',
			);

			$ques_type = (string) $ques_type;
			return isset( $map[ $ques_type ] ) ? $map[ $ques_type ] : null;
		}

		/**
		 * Build Tutor `question_settings` for a migrated Lifter question.
		 *
		 * @since 2.6.0
		 *
		 * @param string     $question_type Tutor question type slug.
		 * @param int|string $question_mark Question points.
		 * @param bool       $has_multiple  Whether multiple correct answers are allowed.
		 *
		 * @return array
		 */
		private function build_tutor_question_settings( $question_type, $question_mark, $has_multiple = false ) {
			$settings = array(
				'question_type'      => $question_type,
				'question_mark'      => $question_mark,
				'answer_required'    => 0,
				'randomize_question' => 0,
				'show_question_mark' => 0,
			);

			if ( 'multiple_choice' === $question_type ) {
				$settings['has_multiple_correct_answer'] = $has_multiple ? '1' : '0';
			}

			if ( 'image_matching' === $question_type ) {
				$settings['is_image_matching'] = '1';
			}

			return $settings;
		}

		/**
		 * Build Tutor `tutor_quiz_option` from a LifterLMS quiz.
		 *
		 * Maps only settings Tutor already supports:
		 * - `passing_percent`     → `passing_grade`
		 * - `limit_time` + `time_limit` → `time_limit` (minutes; switch-gated)
		 * - `limit_attempts` + `allowed_attempts` → `limit_attempts_allowed` / `attempts_allowed`
		 * - `show_correct_answer` → `enable_answer_reveal`
		 * - `random_questions`    → `questions_order`
		 *
		 * Lifter has no question-count cap; `max_questions_for_answer` is 0 (unlimited).
		 * Unmapped (no Tutor equivalent): `disable_retake`, `can_be_resumed`.
		 *
		 * Missing quiz options cause the course builder to crash when opening a quiz.
		 *
		 * @since 2.6.0
		 *
		 * @param int $quiz_id Quiz post ID.
		 *
		 * @return array
		 */
		private function build_tutor_quiz_option_from_lif( $quiz_id ) {
			$quiz_option = array(
				'time_limit'                         => array(
					'time_value' => 0,
					'time_type'  => 'minutes',
				),
				'hide_quiz_time_display'             => 0,
				'attempts_allowed'                   => 10,
				'limit_attempts_allowed'             => '0',
				'enable_answer_reveal'               => '0',
				'passing_grade'                      => 80,
				'max_questions_for_answer'           => 0,
				'quiz_auto_start'                    => 0,
				'question_layout_view'               => '',
				'questions_order'                    => 'sorting',
				'short_answer_characters_limit'      => 200,
				'open_ended_answer_characters_limit' => 500,
				'pass_is_required'                   => 0,
				'hide_question_number_overview'      => 0,
				'enable_pagination'                  => '0',
				'pagination_type'                    => 'shape',
			);

			if ( ! function_exists( 'llms_get_post' ) ) {
				return $quiz_option;
			}

			$quiz = llms_get_post( $quiz_id );
			if ( ! $quiz || ! method_exists( $quiz, 'get' ) ) {
				return $quiz_option;
			}

			$passing = $quiz->get( 'passing_percent' );
			if ( is_numeric( $passing ) ) {
				$quiz_option['passing_grade'] = max( 0, min( 100, (int) $passing ) );
			}

			// Lifter stores a default time_limit value even when the switch is off.
			$time_limit = (int) $quiz->get( 'time_limit' );
			$limit_time = method_exists( $quiz, 'has_time_limit' )
				? $quiz->has_time_limit()
				: ( 'yes' === $quiz->get( 'limit_time' ) );
			if ( $limit_time && $time_limit > 0 ) {
				$quiz_option['time_limit'] = array(
					'time_value' => $time_limit,
					'time_type'  => 'minutes',
				);
			}

			// Lifter stores a default allowed_attempts value even when the switch is off.
			$allowed_attempts = (int) $quiz->get( 'allowed_attempts' );
			$limit_attempts   = method_exists( $quiz, 'has_attempt_limit' )
				? $quiz->has_attempt_limit()
				: ( 'yes' === $quiz->get( 'limit_attempts' ) );
			if ( $limit_attempts ) {
				$quiz_option['limit_attempts_allowed'] = '1';
				$quiz_option['attempts_allowed']       = $allowed_attempts > 0 ? $allowed_attempts : 1;
			}

			if ( 'yes' === $quiz->get( 'show_correct_answer' ) ) {
				$quiz_option['enable_answer_reveal'] = '1';
			}

			if ( 'yes' === $quiz->get( 'random_questions' ) ) {
				$quiz_option['questions_order'] = 'rand';
			}

			return $quiz_option;
		}

		/**
		 * Convert a Lifter course post to Tutor so batching can skip it.
		 *
		 * @since 2.6.0
		 *
		 * @param int    $course_id        Course ID.
		 * @param string $course_post_type Tutor course post type.
		 *
		 * @return void
		 */
		private function mark_course_as_tutor( $course_id, $course_post_type ) {
			wp_update_post(
				array(
					'ID'        => $course_id,
					'post_type' => $course_post_type,
				)
			);
			update_post_meta( $course_id, '_was_lif_course', true );
		}

		/**
		 * Migrate LifterLMS enrollments and completions in batches.
		 *
		 * Runs after course structure migration. Processes student–course pairs
		 * so large enrollments stay under server timeouts.
		 *
		 * @since 2.6.0
		 *
		 * @return array|false Batch payload, or false on blocking error.
		 */
		public function lif_enrollments_migrate() {
			$this->raise_migration_resource_limits();

			$batch_size = (int) apply_filters( 'tlmt_lif_enrollment_migration_batch_size', self::ENROLLMENT_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::ENROLLMENT_BATCH_SIZE;
			}

			$is_first_batch = ! empty( $_POST['lif_enrollment_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in lif_migrate_all_data_to_tutor().

			return ( new \Themeum\TutorLMSMigrationTool\LIFMigration\Enrollments() )->migrate_batch( $batch_size, (bool) $is_first_batch );
		}


		/**
		 * Lifter LMS order migrate (native Tutor ecommerce or WC).
		 *
		 * @since 1.0.0
		 * @since 2.6.0 Added batch processing return payload.
		 * @since 2.6.0 Routes to Tutor native orders when monetize_by is tutor.
		 *
		 * @return array|false Batch payload, or false when the final batch has stored errors.
		 */
		public function migrate_lif_orders() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			if ( function_exists( 'tutor_utils' ) && tutor_utils()->is_monetize_by_tutor() ) {
				return $this->migrate_lif_orders_to_native();
			}

			return $this->migrate_lif_orders_to_wc();
		}

		/**
		 * Migrate Lifter one-time orders into Tutor native ecommerce tables.
		 *
		 * @since 2.6.0
		 *
		 * @return array
		 */
		private function migrate_lif_orders_to_native() {
			global $wpdb;

			$this->raise_migration_resource_limits();

			$total_course_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_was_lif_course'" );

			$migrator   = new \Themeum\TutorLMSMigrationTool\LIFMigration\Orders\OrderMigrator();
			$batch_size = (int) apply_filters( 'tlmt_lif_order_migration_batch_size', self::ORDER_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::ORDER_BATCH_SIZE;
			}

			$remaining_total = $migrator->get_remaining_count();

			$is_first_batch = ! empty( $_POST['lif_order_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream.
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				update_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total, false );
				// Drop stale order errors from prior runs so intentional skips cannot poison this run.
				$stored_errors = ErrorHandler::get_errors( false );
				if ( isset( $stored_errors[ ContentTypes::ORDERS ] ) ) {
					unset( $stored_errors[ ContentTypes::ORDERS ] );
					update_option(
						ErrorHandler::MIGRATION_ERR_OPT_NAME,
						empty( $stored_errors ) ? '' : maybe_serialize( $stored_errors ),
						false
					);
				}
			}

			$total_orders = (int) get_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total );
			if ( $total_orders < 1 ) {
				$total_orders = $remaining_total;
			}

			if ( $remaining_total < 1 ) {
				delete_option( self::ORDER_MIGRATION_TOTAL_OPT );
				return array(
					'migrated'           => $total_orders,
					'total'              => $total_orders,
					'total_course_count' => $total_course_count,
					'remaining'          => 0,
					'has_more'           => false,
					'batch_size'         => $batch_size,
				);
			}

			$lif_orders = $migrator->get_batch( $batch_size );
			$item_i     = (int) get_option( '_tutor_migrated_items_count' );

			foreach ( $lif_orders as $lif_order ) {
				++$item_i;
				update_option( '_tutor_migrated_items_count', $item_i );

				try {
					$migrator->migrate_order( $lif_order );
				} catch ( \Throwable $th ) {
					update_post_meta(
						(int) $lif_order->ID,
						\Themeum\TutorLMSMigrationTool\LIFMigration\Orders\OrderMigrator::META_MIGRATED_ORDER_ID,
						0
					);
					update_post_meta(
						(int) $lif_order->ID,
						\Themeum\TutorLMSMigrationTool\LIFMigration\Orders\OrderMigrator::META_SKIP_REASON,
						'exception:' . $th->getMessage()
					);
					// OrderMigrator already recorded ErrorHandler for create failures.
				}
			}

			$remaining_after = $migrator->get_remaining_count();
			$has_more        = $remaining_after > 0;
			$migrated_count  = max( 0, $total_orders - $remaining_after );

			if ( ! $has_more ) {
				delete_option( self::ORDER_MIGRATION_TOTAL_OPT );
				$stored_errors = ErrorHandler::get_errors( false );
				if ( ! empty( $stored_errors[ ContentTypes::ORDERS ] ) ) {
					return false;
				}
			}

			return array(
				'migrated'           => $migrated_count,
				'total'              => $total_orders,
				'total_course_count' => $total_course_count,
				'remaining'          => $remaining_after,
				'has_more'           => $has_more,
				'batch_size'         => $batch_size,
			);
		}

		/**
		 * Lifter LMS order migrate to WC.
		 *
		 * @since 1.0.0
		 * @since 2.6.0 Added batch processing return payload.
		 *
		 * @return array
		 */
		private function migrate_lif_orders_to_wc() {
			global $wpdb;

			$this->raise_migration_resource_limits();

			$total_course_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_was_lif_course'" );

			$batch_size = (int) apply_filters( 'tlmt_lif_order_migration_batch_size', self::ORDER_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::ORDER_BATCH_SIZE;
			}

			$remaining_total = (int) $wpdb->get_var(
				"SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ot
					ON ot.post_id = p.ID AND ot.meta_key = '_llms_order_type' AND ot.meta_value = 'single'
				WHERE p.post_type = 'llms_order' AND p.post_status = 'llms-completed'"
			);

			$is_first_batch = ! empty( $_POST['lif_order_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream.
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				update_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total, false );
				$this->migrate_lif_wc_order_earnings();
			}

			$total_orders = (int) get_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total );
			if ( $total_orders < 1 ) {
				$total_orders = $remaining_total;
			}

			if ( $remaining_total < 1 ) {
				delete_option( self::ORDER_MIGRATION_TOTAL_OPT );
				return array(
					'migrated'           => $total_orders,
					'total'              => $total_orders,
					'total_course_count' => $total_course_count,
					'remaining'          => 0,
					'has_more'           => false,
					'batch_size'         => $batch_size,
				);
			}

			$lif_orders = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.*
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} ot
						ON ot.post_id = p.ID AND ot.meta_key = '_llms_order_type' AND ot.meta_value = 'single'
					WHERE p.post_type = 'llms_order' AND p.post_status = 'llms-completed'
					ORDER BY p.ID ASC
					LIMIT %d",
					$batch_size
				)
			);

			$item_i = (int) get_option( '_tutor_migrated_items_count' );
			foreach ( $lif_orders as $lif_order ) {
				++$item_i;
				update_option( '_tutor_migrated_items_count', $item_i );

				$order_id = $lif_order->ID;
				$_items   = $this->get_lif_order_items( $order_id );

				$migrate_order_data = array(
					'ID'          => $order_id,
					'post_status' => 'wc-completed',
					'post_type'   => 'shop_order',
				);

				wp_update_post( $migrate_order_data );

				foreach ( $_items as $item ) {
					if ( empty( $item->wc_product_id ) ) {
						continue;
					}

					$item_data = array(
						'order_item_name' => $item->name,
						'order_item_type' => 'line_item',
						'order_id'        => $order_id,
					);

					$wpdb->insert( $wpdb->prefix . 'woocommerce_order_items', $item_data );
					$order_item_id = (int) $wpdb->insert_id;

					$wc_item_metas = array(
						'_product_id'        => (int) $item->wc_product_id,
						'_variation_id'      => 0,
						'_qty'               => (int) $item->quantity,
						'_tax_class'         => '',
						'_line_subtotal'     => $item->subtotal,
						'_line_subtotal_tax' => 0,
						'_line_total'        => $item->total,
						'_line_tax'          => 0,
						'_line_tax_data'     => maybe_serialize(
							array(
								'total'    => array(),
								'subtotal' => array(),
							)
						),
					);

					foreach ( $wc_item_metas as $wc_item_meta_key => $wc_item_meta_value ) {
						$wc_item_meta_row = array(
							'order_item_id' => $order_item_id,
							'meta_key'      => $wc_item_meta_key,
							'meta_value'    => $wc_item_meta_value,
						);
						$wpdb->insert( $wpdb->prefix . 'woocommerce_order_itemmeta', $wc_item_meta_row );
					}
				}

				$buyer_id = (int) get_post_meta( $order_id, '_llms_user_id', true );
				if ( $buyer_id < 1 ) {
					$buyer_id = (int) $lif_order->post_author;
				}

				update_post_meta( $order_id, '_customer_user', $buyer_id );
				update_post_meta( $order_id, '_customer_ip_address', get_post_meta( $order_id, '_llms_user_ip_address', true ) );

				$billing_email = (string) get_post_meta( $order_id, '_llms_billing_email', true );
				if ( '' === $billing_email && $buyer_id > 0 ) {
					$user = get_userdata( $buyer_id );
					if ( $user ) {
						$billing_email = $user->user_email;
					}
				}

				update_post_meta( $order_id, '_billing_address_index', $billing_email );
				update_post_meta( $order_id, '_billing_email', $billing_email );

				$order_total = get_post_meta( $order_id, '_llms_total', true );
				if ( '' !== $order_total && null !== $order_total ) {
					update_post_meta( $order_id, '_order_total', $order_total );
				}

				$order_currency = (string) get_post_meta( $order_id, '_llms_currency', true );
				if ( '' !== $order_currency ) {
					update_post_meta( $order_id, '_order_currency', $order_currency );
				}
			}

			$remaining_after = (int) $wpdb->get_var(
				"SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ot
					ON ot.post_id = p.ID AND ot.meta_key = '_llms_order_type' AND ot.meta_value = 'single'
				WHERE p.post_type = 'llms_order' AND p.post_status = 'llms-completed'"
			);
			$has_more        = $remaining_after > 0;
			$migrated_count  = max( 0, $total_orders - $remaining_after );

			if ( ! $has_more ) {
				delete_option( self::ORDER_MIGRATION_TOTAL_OPT );
			}

			return array(
				'migrated'           => $migrated_count,
				'total'              => $total_orders,
				'total_course_count' => $total_course_count,
				'remaining'          => $remaining_after,
				'has_more'           => $has_more,
				'batch_size'         => $batch_size,
			);
		}

		/**
		 * Migrate Lifter recurring orders to Tutor native subscriptions.
		 *
		 * @since 2.6.0
		 *
		 * @return array|false Batch payload, or false on blocking error.
		 */
		public function lif_subscriptions_migrate() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			$this->raise_migration_resource_limits();

			if ( ! Helper::is_subscription_migration_available() ) {
				return array(
					'has_more'  => false,
					'migrated'  => 0,
					'total'     => 0,
					'remaining' => 0,
				);
			}

			$batch_size = (int) apply_filters( 'tlmt_lif_subscription_migration_batch_size', self::SUBSCRIPTION_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::SUBSCRIPTION_BATCH_SIZE;
			}

			$is_first_batch = ! empty( $_POST['lif_subscription_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream.
			if ( $is_first_batch ) {
				delete_option( self::SUBSCRIPTION_MIGRATION_TOTAL_OPT );
				delete_option( '_tutor_migrated_items_count' );
			}

			try {
				$result = ( new Subscriptions() )->migrate_subscriptions_batch( $batch_size );
				$item_i = (int) get_option( '_tutor_migrated_items_count' );
				update_option( '_tutor_migrated_items_count', $item_i + (int) $result['migrated_in_batch'] );

				if ( $is_first_batch && isset( $result['total'] ) ) {
					update_option( self::SUBSCRIPTION_MIGRATION_TOTAL_OPT, (int) $result['total'], false );
				}

				$total     = (int) get_option( self::SUBSCRIPTION_MIGRATION_TOTAL_OPT, $result['total'] ?? 0 );
				$remaining = (int) ( $result['remaining'] ?? 0 );
				$has_more  = ! empty( $result['has_more'] );
				$migrated  = max( 0, $total - $remaining );

				if ( ! empty( $result['errors'] ) ) {
					\Themeum\TutorLMSMigrationTool\ErrorHandler::set_error(
						\Themeum\TutorLMSMigrationTool\ContentTypes::SUBSCRIPTIONS,
						implode( ' ', $result['errors'] )
					);
				}

				if ( ! $has_more ) {
					delete_option( self::SUBSCRIPTION_MIGRATION_TOTAL_OPT );
					$stored_errors = \Themeum\TutorLMSMigrationTool\ErrorHandler::get_errors( false );
					if ( ! empty( $stored_errors[ \Themeum\TutorLMSMigrationTool\ContentTypes::SUBSCRIPTIONS ] ) ) {
						return false;
					}
				}

				return array(
					'has_more'   => $has_more,
					'migrated'   => $migrated,
					'total'      => $total,
					'remaining'  => $remaining,
					'batch_size' => $batch_size,
				);
			} catch ( \Throwable $th ) {
				\Themeum\TutorLMSMigrationTool\ErrorHandler::set_error(
					\Themeum\TutorLMSMigrationTool\ContentTypes::SUBSCRIPTIONS,
					$th->getMessage()
				);
				return false;
			}
		}

		/**
		 * Migrate WooCommerce order earnings from Lifter access plans.
		 *
		 * Runs once on the first order batch so later batches do not duplicate rows.
		 *
		 * @since 2.6.0
		 *
		 * @return void
		 */
		private function migrate_lif_wc_order_earnings() {
			global $wpdb;

			if ( ! function_exists( 'wc_get_orders' ) ) {
				return;
			}

			$wc_orders = wc_get_orders(
				array(
					'limit' => -1,
				)
			);
			foreach ( $wc_orders as $wc_order ) {
				$user_id        = $wc_order->get_user_id();
				$wc_order_items = $wc_order->get_items();
				$order_status   = $wc_order->get_status();
				foreach ( $wc_order_items as $item ) {
					$wc_price          = $item->get_total();
					$order_data        = $item->get_data();
					$order_id          = $order_data['order_id'];
					$product_id        = $order_data['product_id'];
					$course_id         = get_post_meta( $product_id, '_llms_product_id', true );
					$wc_price_grand    = $item->get_subtotal();
					$commission_type   = 'percent';
					$sharing_enabled   = tutor_utils()->get_option( 'enable_revenue_sharing' );
					$instructor_rate   = $sharing_enabled ? tutor_utils()->get_option( 'earning_instructor_commission' ) : 0;
					$admin_rate        = $sharing_enabled ? tutor_utils()->get_option( 'earning_admin_commission' ) : 100;
					$instructor_amount = $instructor_rate > 0 ? ( ( $wc_price_grand * $instructor_rate ) / 100 ) : 0;
					$admin_amount      = $admin_rate > 0 ? ( ( $wc_price_grand * $admin_rate ) / 100 ) : 0;
					$plans             = wc_get_order_item_meta( $product_id, '_llms_access_plan', false );
					$plan              = '';
					foreach ( $plans as $plan ) {
						$plan = $plan ? llms_get_post( $plan ) : false;
					}
					if ( $plan ) {
						$earning_data = array(
							'user_id'                  => $user_id,
							'course_id'                => $course_id,
							'order_id'                 => $order_id,
							'order_status'             => $order_status,
							'course_price_total'       => $wc_price,
							'course_price_grand_total' => $wc_price_grand,
							'instructor_amount'        => $instructor_amount,
							'instructor_rate'          => $instructor_rate,
							'admin_amount'             => $admin_amount,
							'admin_rate'               => $admin_rate,
							'commission_type'          => $commission_type,
							'process_by'               => 'woocommerce',
							'created_at'               => gmdate( 'Y-m-d H:i:s', tutor_time() ),
						);

						$wpdb->insert( $wpdb->prefix . 'tutor_earnings', $earning_data );
					}
				}
			}
		}

		/**
		 * Lifter review migrate to Tutor.
		 *
		 * @since 1.0.0
		 * @since 2.6.0 Added batch processing return payload.
		 *
		 * @return array
		 */
		public function migrate_lif_reviews() {
			global $wpdb;

			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			$this->raise_migration_resource_limits();

			$batch_size = (int) apply_filters( 'tlmt_lif_review_migration_batch_size', self::REVIEW_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::REVIEW_BATCH_SIZE;
			}

			$total_course_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_was_lif_course'" );
			$remaining_total    = $this->count_pending_lif_reviews();

			$is_first_batch = ! empty( $_POST['lif_review_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream.
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				update_option( self::REVIEW_MIGRATION_TOTAL_OPT, $remaining_total, false );
			}

			$total_reviews = (int) get_option( self::REVIEW_MIGRATION_TOTAL_OPT, $remaining_total );
			if ( $total_reviews < 1 ) {
				$total_reviews = $remaining_total;
			}

			if ( $remaining_total < 1 ) {
				delete_option( self::REVIEW_MIGRATION_TOTAL_OPT );
				return array(
					'migrated'           => $total_reviews,
					'total'              => $total_reviews,
					'total_course_count' => $total_course_count,
					'remaining'          => 0,
					'has_more'           => false,
					'batch_size'         => $batch_size,
				);
			}

			$lif_review_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm
						ON p.ID = pm.post_id AND pm.meta_key = %s
					WHERE p.post_type = 'llms_review'
						AND pm.meta_id IS NULL
					ORDER BY p.ID ASC
					LIMIT %d",
					LIFReviews::MIGRATED_META,
					$batch_size
				)
			);

			try {
				$reviews = tlmt_get_review_obj( MigrationTypes::LIF_TO_TUTOR );
			} catch ( \Throwable $th ) {
				ErrorHandler::set_error( ContentTypes::COURSE_REVIEWS, __( 'Error creating Lifter review migration object.', 'tutor-lms-migration-tool' ) );
				return false;
			}

			$item_i           = (int) get_option( '_tutor_migrated_items_count' );
			$migration_errors = array();

			foreach ( $lif_review_ids as $lif_review_id ) {
				++$item_i;
				update_option( '_tutor_migrated_items_count', $item_i );

				$review_post = get_post( (int) $lif_review_id );
				if ( ! $review_post ) {
					continue;
				}

				try {
					$reviews->migrate( $review_post );
				} catch ( \Throwable $th ) {
					$migration_errors[] = (int) $lif_review_id;
				}
			}

			if ( $migration_errors ) {
				ErrorHandler::set_error(
					ContentTypes::COURSE_REVIEWS,
					__( 'Could not migrate reviews: ', 'tutor-lms-migration-tool' ) . implode( ',', $migration_errors )
				);
			}

			$remaining_after = $this->count_pending_lif_reviews();
			$has_more        = $remaining_after > 0;
			$migrated_count  = max( 0, $total_reviews - $remaining_after );

			if ( ! $has_more ) {
				delete_option( self::REVIEW_MIGRATION_TOTAL_OPT );
			}

			return array(
				'migrated'           => $migrated_count,
				'total'              => $total_reviews,
				'total_course_count' => $total_course_count,
				'remaining'          => $remaining_after,
				'has_more'           => $has_more,
				'batch_size'         => $batch_size,
			);
		}

		/**
		 * Count unmigrated Lifter reviews.
		 *
		 * @since 2.6.0
		 *
		 * @return int
		 */
		private function count_pending_lif_reviews() {
			global $wpdb;

			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(p.ID)
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm
						ON p.ID = pm.post_id AND pm.meta_key = %s
					WHERE p.post_type = 'llms_review'
						AND pm.meta_id IS NULL",
					LIFReviews::MIGRATED_META
				)
			);
		}

		/**
		 * Build WooCommerce line items from a Lifter one-time order.
		 *
		 * Lifter stores product/totals on the order post itself (not child line items).
		 *
		 * @since 1.0.0
		 * @since 2.6.1 Fixed product ID, quantity, and meta resolution for WC migration.
		 *
		 * @param int $order_id Lifter order post ID.
		 *
		 * @return array<int, object>
		 */
		public function get_lif_order_items( $order_id ) {
			$order_id = (int) $order_id;
			if ( $order_id < 1 || 'llms_order' !== get_post_type( $order_id ) ) {
				return array();
			}

			$course_id = (int) get_post_meta( $order_id, '_llms_product_id', true );
			if ( $course_id < 1 ) {
				return array();
			}

			$plan_id       = (int) get_post_meta( $order_id, '_llms_plan_id', true );
			$wc_product_id = $this->resolve_wc_product_id_for_lif_course( $course_id, $plan_id );

			$product_title = (string) get_post_meta( $order_id, '_llms_product_title', true );
			if ( '' === $product_title ) {
				$product_title = get_the_title( $course_id );
			}

			$subtotal = get_post_meta( $order_id, '_llms_original_total', true );
			$total    = get_post_meta( $order_id, '_llms_total', true );

			if ( '' === $subtotal || null === $subtotal ) {
				$subtotal = $total;
			}

			return array(
				(object) array(
					'id'            => $order_id,
					'name'          => $product_title,
					'course_id'     => $course_id,
					'wc_product_id' => $wc_product_id,
					'quantity'      => 1,
					'subtotal'      => $subtotal,
					'total'         => $total,
				),
			);
		}

		/**
		 * Resolve the WooCommerce product ID linked to a migrated Lifter course.
		 *
		 * @since 2.6.1
		 *
		 * @param int $course_id Lifter/Tutor course post ID.
		 * @param int $plan_id   Lifter access plan ID from the order.
		 *
		 * @return int WooCommerce product ID, or 0 when not found.
		 */
		private function resolve_wc_product_id_for_lif_course( int $course_id, int $plan_id = 0 ): int {
			if ( $course_id < 1 ) {
				return 0;
			}

			$product_id = (int) get_post_meta( $course_id, '_tutor_course_product_id', true );
			if ( $product_id > 0 ) {
				return $product_id;
			}

			if ( $plan_id > 0 ) {
				$wc_pid = (int) get_post_meta( $plan_id, '_llms_wc_pid', true );
				if ( $wc_pid > 0 ) {
					return $wc_pid;
				}
			}

			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wc_pid = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT pm_wc.meta_value
					FROM {$wpdb->postmeta} pm_product
					INNER JOIN {$wpdb->postmeta} pm_wc
						ON pm_wc.post_id = pm_product.post_id AND pm_wc.meta_key = '_llms_wc_pid'
					WHERE pm_product.meta_key = '_llms_product_id'
						AND pm_product.meta_value = %d
					LIMIT 1",
					$course_id
				)
			);

			return (int) $wc_pid;
		}


		/**
		 *
		 * Import From XML
		 */
		public function tutor_import_from_xml_lif() {
			global $wpdb;

			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			$wpdb->query( 'START TRANSACTION' );
			$error = true;
			if ( isset( $_FILES['tutor_import_file'] ) ) {
				$course_post_type = tutor()->course_post_type;

				$xmlContent = file_get_contents( $_FILES['tutor_import_file']['tmp_name'] );
				libxml_use_internal_errors( true );
				$replacer   = array(
					'&'                => '&amp;',
					' allowfullscreen' => ' allowfullscreen="allowfullscreen"', // don't remove space
					' disabled'        => ' disabled="disabled"',
				);
				$xmlContent = str_replace( array_keys( $replacer ), array_values( $replacer ), $xmlContent );
				$xml_data   = simplexml_load_string( $xmlContent, null, LIBXML_NOCDATA );
				if ( $xml_data == false ) {
					$errors        = libxml_get_errors();
					$error_message = '';
					if ( is_array( $errors ) ) {
						$error_message = $errors[0]->message . 'on line number ' . $errors[0]->line;
					}
					wp_send_json(
						array(
							'success' => false,
							'message' => $error_message,
						)
					);
				}

				$xml_data = simplexml_load_string( $xmlContent );
				if ( $xml_data == false ) {
					wp_send_json(
						array(
							'success' => false,
							'message' => 'Migration not successfull',
						)
					);
				}
				$courses = $xml_data->courses;
				if ( $courses == false ) {
					wp_send_json(
						array(
							'success' => false,
							'message' => 'Migration not successfull',
						)
					);
				}
				foreach ( $courses as $course ) {

					$course_data = array(
						'post_author'   => (string) $course->post_author,
						'post_date'     => (string) $course->post_date,
						'post_date_gmt' => (string) $course->post_date_gmt,
						'post_content'  => (string) $course->post_content,
						'post_title'    => (string) $course->post_title,
						'post_status'   => 'publish',
						'post_type'     => $course_post_type,
					);

					// Inserting Course
					$course_id = wp_insert_post( $course_data );

					$course_meta = json_decode( json_encode( $course->course_meta ), true );
					foreach ( $course_meta as $course_meta_key => $course_meta_value ) {
						if ( is_array( $course_meta_value ) ) {
							$course_meta_value = json_encode( $course_meta_value );
						}
						if ( $course_meta_key == '_thumbnail_id' ) {
							$thumbnail_post = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT  * FROM {$wpdb->posts}
									WHERE `ID` = %d AND `post_type` = %s
									LIMIT %d",
									$course_meta_value,
									'attachment',
									1
								)
							);
							if ( count( $thumbnail_post ) ) {
								$wpdb->insert(
									$wpdb->postmeta,
									array(
										'post_id'    => $course_id,
										'meta_key'   => $course_meta_key,
										'meta_value' => $course_meta_value,
									)
								);
							}
						} else {
							$wpdb->insert(
								$wpdb->postmeta,
								array(
									'post_id'    => $course_id,
									'meta_key'   => $course_meta_key,
									'meta_value' => $course_meta_value,
								)
							);
						}
					}

					foreach ( $course->topics as $topic ) {
						$topic_data = array(
							'post_type'    => 'topics',
							'post_title'   => (string) $topic->post_title,
							'post_content' => (string) $topic->post_content,
							'post_status'  => 'publish',
							'post_author'  => (string) $topic->post_author,
							'post_parent'  => $course_id,
							'menu_order'   => (string) $topic->menu_order,
						);

						// Inserting Topics
						$topic_id = wp_insert_post( $topic_data );

						$item_i = 0;
						foreach ( $topic->items as $item ) {
							$item_i++;

							$item_data = array(
								'post_type'    => (string) $item->post_type,
								'post_title'   => (string) $item->post_title,
								'post_content' => (string) $item->post_content,
								'post_status'  => 'publish',
								'post_author'  => (string) $item->post_author,
								'post_parent'  => $topic_id,
								'menu_order'   => $item_i,
							);

							$item_id = wp_insert_post( $item_data );

							$item_metas = json_decode( json_encode( $item->item_meta ), true );
							foreach ( $item_metas as $item_meta_key => $item_meta_value ) {
								if ( is_array( $item_meta_value ) ) {
									$item_meta_value = json_encode( $item_meta_value );
								}
								$wpdb->insert(
									$wpdb->postmeta,
									array(
										'post_id'    => $item_id,
										'meta_key'   => $item_meta_key,
										'meta_value' => (string) $item_meta_value,
									)
								);
							}

							if ( tutor()->lesson_post_type === $item_data['post_type'] ) {
								do_action( 'tlmt_lesson_migrated', $item_id, MigrationTypes::LIF_TO_TUTOR );
							}

							if ( isset( $item->questions ) && is_object( $item->questions ) && count( $item->questions ) ) {
								foreach ( $item->questions as $question ) {
									$answers = $question->answers;

									$question                         = (array) $question;
									$question['quiz_id']              = $item_id;
									$question['question_description'] = (string) $question['question_description'];
									unset( $question['answers'] );

									$wpdb->insert( $wpdb->prefix . 'tutor_quiz_questions', $question );
									$question_id = $wpdb->insert_id;

									foreach ( $answers as $answer ) {
										$answer                        = (array) $answer;
										$answer['belongs_question_id'] = $question_id;
										$wpdb->insert( $wpdb->prefix . 'tutor_quiz_question_answers', $answer );
									}
								}
							}
						}
					}

					if ( isset( $course->reviews ) && is_object( $course->reviews ) && count( $course->reviews ) ) {
						foreach ( $course->reviews as $review ) {
							$rating_data = array(
								'comment_post_ID'  => $course_id,
								'comment_approved' => 'approved',
								'comment_type'     => 'tutor_course_rating',
								'comment_date'     => (string) $review->comment_date,
								'comment_date_gmt' => (string) $review->comment_date,
								'comment_content'  => (string) $review->comment_content,
								'user_id'          => (string) $review->user_id,
								'comment_author'   => (string) $review->comment_author,
								'comment_agent'    => 'TutorLMSPlugin',
							);

							$wpdb->insert( $wpdb->comments, $rating_data );
							$comment_id = (int) $wpdb->insert_id;

							$rating_meta_data = array(
								'comment_id' => $comment_id,
								'meta_key'   => 'tutor_rating',
								'meta_value' => (string) $review->tutor_rating,
							);
							$wpdb->insert( $wpdb->commentmeta, $rating_meta_data );
						}
					}
				}
				$error = false;
			}
			if ( $error ) {
				$wpdb->query( 'ROLLBACK' );
				wp_send_json(
					array(
						'success' => false,
						'message' => 'LIF Migration not successfull',
					)
				);
			} else {
				$wpdb->query( 'COMMIT' );
				wp_send_json(
					array(
						'success' => true,
						'message' => 'LIF Migration successfull',
					)
				);
			}
		}

		/**
		 * Tutor export function
		 *
		 * @return void
		 */
		public function tutor_lif_export_xml() {

			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename=lifter_data_for_tutor.xml' );
			header( 'Expires: 0' );

			echo $this->generate_xml_data();
			exit;
		}

		/**
		 * Xml file generate function
		 *
		 * @return xml
		 */
		public function generate_xml_data() {
			global $wpdb;

			$xml  = '<?xml version="1.0" encoding="' . get_bloginfo( 'charset' ) . "\" ?>\n";
			$xml .= $this->start_element( 'channel' );
			ob_start();
			?>
				<title><?php bloginfo_rss( 'name' ); ?></title>
				<link><?php bloginfo_rss( 'url' ); ?></link>
				<description><?php bloginfo_rss( 'description' ); ?></description>
				<pubDate><?php echo date( 'D, d M Y H:i:s +0000' ); ?></pubDate>
				<language><?php bloginfo_rss( 'language' ); ?></language>
				<tlmt_version><?php echo TLMT_VERSION; ?></tlmt_version>
				<?php
				$xml .= ob_get_clean();

				$lif_courses = $wpdb->get_results( "SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_status  FROM {$wpdb->posts} WHERE post_type = 'course' AND post_status = 'publish';" );

				if ( tutils()->count( $lif_courses ) ) {
					$course_i = 0;
					foreach ( $lif_courses as $lif_course ) {
						$course_i++;

						$course_id = $lif_course->ID;

						$xml .= $this->start_element( 'courses' );

						$course_arr = (array) $lif_course;
						foreach ( $course_arr as $course_col => $course_col_value ) {
							$xml .= "<{$course_col}>{$course_col_value}</{$course_col}>\n";
						}

						$course_metas = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value from {$wpdb->postmeta} where post_id = %d ", $course_id ) );

						$xml .= $this->start_element( 'course_meta' );
						foreach ( $course_metas as $course_meta ) {
							$xml .= "<{$course_meta->meta_key}>{$course_meta->meta_value}</{$course_meta->meta_key}>\n";
						}
						$xml .= $this->close_element( 'course_meta' );

						$course = new LLMS_Course( $course_id );

						$lesson_post_type = tutor()->lesson_post_type;
						$course_post_type = tutor()->course_post_type;

						if ( $course ) {
							$curriculum = $course->get_sections();

							$i = 0;

							if ( $curriculum ) {
								foreach ( $curriculum as $section ) {
									$i ++;

									$xml .= $this->start_element( 'topics' );

									/**
									 * Topic
									 */
									$xml .= "<post_type>topics</post_type>\n";
									$xml .= "<post_title>{$section->post->post_title}</post_title>\n";

									$topic_content = ! empty( $section->post->post_content ) ? $this->xml_cdata( $section->post->post_content ) : '';

									$xml .= "<post_content>{$topic_content}</post_content>\n";
									$xml .= "<post_status>publish</post_status>\n";
									$xml .= "<post_author>{$course->get_author}</post_author>\n";
									$xml .= "<post_parent>{$course_id}</post_parent>";
									$xml .= "<menu_order>{$i}</menu_order>\n";

									/**
									 * Lessons
									 */
									$i             = 0;
									$section_count = 0;
									$topic_id      = 0;
									$xml_inner     = array();
									$final         = array();
									$lessons       = $section->get_lessons();

									foreach ( $lessons as $lesson ) {
										$item_post_type = $lesson->item_type;

										if ( $lesson->has_quiz() ) {
											$lesson_post_type = 'tutor_quiz';
											$quiz             = $lesson->get_quiz();
											$questions        = $quiz->get_questions();

										} else {
											$lesson_post_type = tutor()->lesson_post_type;
										}

										// Item
										$xml .= $this->start_element( 'items' );

										$xml .= "<item_id>{$lesson->id}</item_id>\n";
										$xml .= "<post_type>{$lesson_post_type}</post_type>\n";
										$xml .= "<post_author>{$lesson->post_author}</post_author>\n";
										$xml .= "<post_date>{$lesson->post_date}</post_date>\n";
										$xml .= "<post_title>{$lesson->post->post_title}</post_title>\n";
										$xml .= "<post_content>{$this->xml_cdata($lesson->post->post_content)}</post_content>\n";
										$xml .= "<post_parent>{$course_id}</post_parent>\n";

										$xml .= $this->start_element( 'item_meta' );

										$item_metas = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ", $lesson->id ) );

										if ( is_array( $item_metas ) && count( $item_metas ) ) {
											foreach ( $item_metas as $item_meta ) {
												$xml .= "<{$item_meta->meta_key}>{$this->xml_cdata($item_meta->meta_value)}</{$item_meta->meta_key}>\n";
											}
										}

										$xml .= $this->close_element( 'item_meta' );

										if ( $lesson_post_type === 'tutor_quiz' ) {
											$quiz_id   = tutils()->array_get( 'ID', $lesson );
											$quiz      = $lesson->get_quiz();
											$questions = $quiz->get_questions();
											if ( tutils()->count( $questions ) ) {
												foreach ( $questions as $question ) {
													$ques_id  = $question->id;
													$meta_key = '_llms_question_type';

													$ques_type = get_post_meta( $ques_id, $meta_key, true );

													$question_type = null;
													if ( $ques_type === 'true_false' ) {
														$question_type = 'true_false';
													}
													if ( $ques_type === 'choice' ) {
														$question_type = 'multiple_choice';
													}
													if ( $ques_type === 'picture_choice' ) {
														$question_type = 'multiple_choice';
													}
													if ( $ques_type === 'blank' ) {
														$question_type = 'fill_in_the_blank';
													}
													if ( $ques_type === 'short_answer' || $ques_type === 'long_answer' || $ques_type === 'code' ) {
														$question_type = 'short_answer';
													}
													if ( $ques_type === 'reorder' ) {
														$question_type = 'ordering';
													}
													if ( $ques_type === 'upload' ) {
														$question_type = 'image_answering';
													}

													if ( $question_type ) {

														$answer_items = $question->get_choices();

														if ( tutils()->count( $answer_items ) ) {
															foreach ( $answer_items as $answer_item ) {
																$choice      = $answer_item->get( 'choice' );
																$correct     = $answer_item->get( 'correct' );
																$question_id = $answer_item->get_question_id();

																$answer_data = array(
																	'belongs_question_id'   => $question_id,
																	'belongs_question_type' => $question_type,
																	'answer_title'          => $choice,
																	'is_correct'            => $correct === true ? 1 : 0,
																	'answer_order'          => '',
																);
															}
														}
														$question1['quiz_id']              = $quiz_id;
														$question1['question_title']       = $question->post->post_title;
														$question1['question_description'] = $question->post->post_content;
														$question1['question_mark']        = $question->post->question_mark;
														$question1['question_settings']    = maybe_serialize(
															array(
																'question_type' => $question_type,
																'question_mark' => $question->post->question_mark,
															)
														);

															$xml .= $this->start_element( 'questions' );
														foreach ( $question1 as $question_key => $question_value ) {
															$xml .= "<{$question_key}>{$this->xml_cdata($question_value)}</{$question_key}>\n";
														}

														if ( $question_id ) {
															foreach ( (array) maybe_unserialize( $answer_items ) as $key => $value ) {
																$i       = 0;
																$answer1 = array();
																foreach ( (array) $value as $k => $val ) {
																	if ( $i == 0 ) {
																		$answer1['answer_title'] = $val;
																		if ( $answer_items == 'cloze_answer' ) {
																			$final_question = wp_strip_all_tags( $val );
																			preg_match_all( '/{.*?\}/', $final_question, $matches );
																			if ( isset( $matches[0] ) ) {
																				foreach ( $matches[0] as $key => $v ) {
																					$v = explode( ']', $v );
																					if ( isset( $v[0] ) ) {
																						$answer_str[] = str_replace( array( '{[', '{', '}' ), '', $v[0] );
																					}
																				}
																				$final_question = str_replace( $matches[0], '{dash}', $final_question );
																			}
																			$answer1['answer_two_gap_match'] = implode( '|', $answer_str );
																			$answer1['answer_title']         = $final_question;
																		}
																	} elseif ( $i == 2 ) {
																		$answer1['is_correct'] = $val ? 0 : 1;
																	} elseif ( $i == 3 ) {
																		$answer1['belongs_question_id']   = $question_id;
																		$answer1['belongs_question_type'] = $question_type;
																		$answer1['answer_view_format']    = 'text';
																		$answer1['answer_order']          = $i + 1;
																		$answer1['image_id']              = 0;
																	}
																	$i++;
																}

																if ( count( $answer1 ) > 0 ) {
																	$xml .= $this->start_element( 'answers' );
																	foreach ( $answer1 as $answers_key => $answers_value ) {
																		$xml .= "<{$answers_key}>{$this->xml_cdata($answers_value)}</{$answers_key}>\n";
																	}
																	$xml .= $this->close_element( 'answers' );
																}
															}
														}

															$xml .= $this->close_element( 'questions' );

													}
												}
											}
										}
										$xml .= $this->close_element( 'items' );
									}

									// Close Topic Tag
									$xml .= $this->close_element( 'topics' );
								}
							}
						}

						$lif_reviews = $wpdb->get_results(
							"SELECT 
								ID,
								post_author,
								post_date,
								post_date_gmt,
								post_content,
								post_author,
								post_content as tutor_rating
                   
							FROM {$wpdb->posts} WHERE post_type = 'llms_review';",
							ARRAY_A
						);

						if ( tutils()->count( $lif_reviews ) ) {
							foreach ( $lif_reviews as $lif_review ) {
								$lif_review['comment_approved'] = 'approved';
								$lif_review['comment_agent']    = 'TutorLMSPlugin';
								$lif_review['comment_type']     = 'tutor_course_rating';

								$xml .= $this->start_element( 'reviews' );
								foreach ( $lif_review as $lif_review_key => $lif_review_value ) {
									$xml .= "<{$lif_review_key}>{$this->xml_cdata($lif_review_value)}</{$lif_review_key}>\n";
								}
								$xml .= $this->close_element( 'reviews' );
							}
						}

						$xml .= $this->close_element( 'courses' );
					}
				}

				$xml .= $this->close_element( 'channel' );
				return $xml;
		}



		/**
		 * StartElement function
		 *
		 * @param string $element for element start.
		 * @return $element
		 */
		public function start_element( $element = '' ) {
			return "\n<{$element}>\n";
		}
		/**
		 * CloseElement function
		 *
		 * @param string $element for element close.
		 * @return $element
		 */
		public function close_element( $element = '' ) {
			return "\n</{$element}>\n";
		}

		/**
		 * Xml cdata function
		 *
		 * @param [cdata] $str return data.
		 * @return $str
		 */
		function xml_cdata( $str ) {
			if ( ! seems_utf8( $str ) ) {
				$str = utf8_encode( $str );
			}
			$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';
			return $str;
		}


	}
}
