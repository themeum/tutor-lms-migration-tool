<?php
/**
 * Manage LearnDash to Tutor migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Helper as SubscriptionHelper;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Subscriptions;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\MigrationLogger;

defined( 'ABSPATH' ) || exit;

	if ( ! class_exists( 'LDtoTutorMigration' ) ) {
	class LDtoTutorMigration {

		const LD_COURSE_TYPE = 'sfwd-courses';

		/**
		 * Courses processed per AJAX request to stay under FastCGI/PHP timeouts.
		 *
		 * @since 2.5.1
		 */
		const COURSE_BATCH_SIZE = 5;

		/**
		 * Student enrollments processed per AJAX request.
		 *
		 * @since 2.5.1
		 */
		const ENROLLMENT_BATCH_SIZE = 50;

		/**
		 * One-time orders processed per AJAX request.
		 *
		 * @since 2.5.1
		 */
		const ORDER_BATCH_SIZE = 10;

		/**
		 * Subscriptions processed per AJAX request.
		 *
		 * @since 2.5.1
		 */
		const SUBSCRIPTION_BATCH_SIZE = 5;

		/**
		 * Reviews processed per AJAX request.
		 *
		 * @since 2.5.1
		 */
		const REVIEW_BATCH_SIZE = 50;

		/**
		 * Option key for enrollment migration total.
		 *
		 * @since 2.5.1
		 */
		const ENROLLMENT_MIGRATION_TOTAL_OPT = '_tlmt_ld_enrollments_migration_total';

		/**
		 * Post meta marking a course whose enrollments/progress were migrated.
		 *
		 * @since 2.5.1
		 */
		const ENROLLMENT_MIGRATED_META = '_tlmt_ld_enrollment_migrated';

		/**
		 * User meta prefix marking a student whose enrollment/progress was migrated for a course.
		 * Full key: `{prefix}{course_id}`.
		 *
		 * @since 2.5.1
		 */
		const ENROLLMENT_USER_META_PREFIX = '_tlmt_ld_enroll_user_';

		/**
		 * Option key for order migration total.
		 *
		 * @since 2.5.1
		 */
		const ORDER_MIGRATION_TOTAL_OPT = '_tlmt_ld_orders_migration_total';

		/**
		 * Option key for subscription migration total.
		 *
		 * @since 2.5.1
		 */
		const SUBSCRIPTION_MIGRATION_TOTAL_OPT = '_tlmt_ld_subscriptions_migration_total';

		/**
		 * Option key for review migration total.
		 *
		 * @since 2.5.1
		 */
		const REVIEW_MIGRATION_TOTAL_OPT = '_tlmt_ld_reviews_migration_total';

		public function __construct() {
			add_filter( 'tutor_tool_pages', array( $this, 'ld_tool_pages' ) );
			add_action( 'wp_ajax_insert_tutor_migration_data', array( $this, 'insert_tutor_migration_data' ) );
			add_action( 'wp_ajax_ld_migrate_all_data_to_tutor', array( $this, 'ld_migrate_all_data_to_tutor' ) );
			add_action( 'wp_ajax_ld_reset_migrated_items_count', array( $this, 'ld_reset_migrated_items_count' ) );
			add_action( 'wp_ajax__get_ld_live_progress_course_migrating_info', array( $this, '_get_ld_live_progress_course_migrating_info' ) );
			add_action( 'tutor_action_ld_order_migrate', array( $this, 'ld_order_migrate' ) );
		}

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


		public function ld_tool_pages( $pages ) {
			// if (defined('LEARNDASH_VERSION') && !defined('LEARNPRESS_VERSION') ) {
			if ( defined( 'LEARNDASH_VERSION' ) ) {
				// $pages['migration_ld'] = array('title' =>  __('LearnDash Migration', 'tutor-lms-migration-tool'), 'view_path' => TLMT_PATH.'views/migration_ld.php');
				$pages['migration_ld'] = array(
					'label'     => __( 'LearnDash Migration', 'tutor' ),
					'slug'      => 'migration_ld',
					'desc'      => __( 'LearnDash Migration', 'tutor' ),
					'template'  => 'migration_ld',
					'view_path' => TLMT_PATH . 'views/',
					'icon'      => 'tutor-icon-brand-learndash',
					'blocks'    => array(
						'block' => array(),
					),
				);
			}
			return $pages;
		}


		public function ld_reset_migrated_items_count() {

			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			delete_option( '_tutor_migrated_items_count' );
		}


		/**
		 * LD to Tutor migration
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function ld_migrate_all_data_to_tutor() {
			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			if ( isset( $_POST['migrate_type'] ) ) {
				$migrate_type = sanitize_text_field( $_POST['migrate_type'] );

				switch ( $migrate_type ) {
					case ContentTypes::COURSE:
						try {
							$result = $this->ld_migrate_course_to_tutor();
							wp_send_json_success(
								array_merge(
									array(
										'step'    => ContentTypes::COURSE,
										'message' => $result['has_more']
											? __( 'Course batch migrated successfully.', 'tutor-lms-migration-tool' )
											: __( 'Courses migrated successfully.', 'tutor-lms-migration-tool' ),
									),
									$result
								)
							);
						} catch ( \Throwable $th ) {
							error_log( $th->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							wp_send_json_error(
								array(
									'step'    => ContentTypes::COURSE,
									'message' => $th->getMessage(),
								)
							);
						}
						break;
					case ContentTypes::ENROLLMENTS:
						try {
							$enrollments_result = $this->ld_enrollments_migrate();
							if ( false === $enrollments_result ) {
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
										'message' => ! empty( $enrollments_result['has_more'] )
											? __( 'Enrollment batch migrated successfully.', 'tutor-lms-migration-tool' )
											: __( 'Enrollments migrated successfully.', 'tutor-lms-migration-tool' ),
									),
									is_array( $enrollments_result ) ? $enrollments_result : array()
								)
							);
						} catch ( \Throwable $th ) {
							error_log( $th->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							wp_send_json_error(
								array(
									'step'    => ContentTypes::ENROLLMENTS,
									'message' => $th->getMessage(),
								)
							);
						}
						break;
					case ContentTypes::ORDERS:
						$orders_result = $this->ld_order_migrate();
						if ( false === $orders_result ) {
							wp_send_json_error(
								array(
									'step'    => ContentTypes::ORDERS,
									'message' => ErrorHandler::get_error_message( 'order' ),
								)
							);
						}
						wp_send_json_success(
							array_merge(
								array( 'step' => ContentTypes::ORDERS ),
								is_array( $orders_result ) ? $orders_result : array()
							)
						);
						break;
					case ContentTypes::SUBSCRIPTIONS:
						$subscriptions_result = $this->ld_subscriptions_migrate();
						if ( false === $subscriptions_result ) {
							wp_send_json_error(
								array(
									'step'    => ContentTypes::SUBSCRIPTIONS,
									'message' => ErrorHandler::get_error_message( ContentTypes::SUBSCRIPTIONS ),
								)
							);
						}
						wp_send_json_success(
							array_merge(
								array( 'step' => ContentTypes::SUBSCRIPTIONS ),
								is_array( $subscriptions_result ) ? $subscriptions_result : array()
							)
						);
						break;
					case ContentTypes::COURSE_REVIEWS:
						$reviews_result = $this->ld_reviews_migrate();
						if ( ! empty( $reviews_result['has_more'] ) ) {
							wp_send_json_success(
								array_merge(
									array( 'step' => ContentTypes::COURSE_REVIEWS ),
									$reviews_result
								)
							);
						}
						$log = (array) MigrationLogger::get_log();
						wp_send_json_success(
							array_merge(
								$log,
								is_array( $reviews_result ) ? $reviews_result : array(),
								array( 'step' => ContentTypes::COURSE_REVIEWS )
							)
						);
						break;
				}

				wp_send_json_error();
			}

			wp_send_json_error();
		}

		/**
		 * Migrate learndash reviews to tutor.
		 *
		 * @since 2.3.0
		 * @since 2.5.1 Added batch processing return payload.
		 *
		 * @return array{
		 *     has_more: bool,
		 *     migrated: int,
		 *     total: int,
		 *     remaining: int,
		 *     batch_size: int
		 * }
		 */
		public function ld_reviews_migrate() {
			$this->raise_migration_resource_limits();

			$batch_size = (int) apply_filters( 'tlmt_ld_review_migration_batch_size', self::REVIEW_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::REVIEW_BATCH_SIZE;
			}

			$is_first_batch = ! empty( $_POST['ld_review_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ld_migrate_all_data_to_tutor().
			if ( $is_first_batch ) {
				delete_option( self::REVIEW_MIGRATION_TOTAL_OPT );
				delete_option( '_tutor_migrated_items_count' );
			}

			$remaining_total = (int) get_comments(
				array(
					'type'       => ContentTypes::LD_REVIEW_TYPE,
					'count'      => true,
					'status'     => 'any',
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_tlmt_ld_review_migration_failed',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);

			if ( $is_first_batch ) {
				update_option( self::REVIEW_MIGRATION_TOTAL_OPT, $remaining_total, false );
			}

			$total_reviews = (int) get_option( self::REVIEW_MIGRATION_TOTAL_OPT, $remaining_total );

			if ( 0 === $remaining_total ) {
				delete_option( self::REVIEW_MIGRATION_TOTAL_OPT );
				$this->cleanup_after_ld_migration_complete();
				return array(
					'has_more'   => false,
					'migrated'   => $total_reviews,
					'total'      => $total_reviews,
					'remaining'  => 0,
					'batch_size' => $batch_size,
				);
			}

			$ld_reviews = get_comments(
				array(
					'type'       => ContentTypes::LD_REVIEW_TYPE,
					'number'     => $batch_size,
					'status'     => 'any',
					'orderby'    => 'comment_ID',
					'order'      => 'ASC',
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_tlmt_ld_review_migration_failed',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);

			$item_idx         = (int) get_option( '_tutor_migrated_items_count' );
			$migration_errors = array();

			try {
				$reviews = tlmt_get_review_obj( MigrationTypes::LD_TO_TUTOR );
			} catch ( \Throwable $th ) {
				ErrorHandler::set_error( 'review', __( 'Error review migration order object', 'tutor-lms-migration-tool' ) );
				return array(
					'has_more'   => false,
					'migrated'   => 0,
					'total'      => $total_reviews,
					'remaining'  => $remaining_total,
					'batch_size' => $batch_size,
				);
			}

			foreach ( $ld_reviews as $review ) {
				++$item_idx;
				update_option( '_tutor_migrated_items_count', $item_idx );
				try {
					$reviews->migrate( $review );
				} catch ( \Throwable $th ) {
					update_comment_meta( $review->comment_ID, '_tlmt_ld_review_migration_failed', 1 );
					array_push( $migration_errors, $review->comment_ID );
				}
			}

			if ( $migration_errors ) {
				ErrorHandler::set_error( 'review', __( 'Could not migrate reviews :', 'tutor-lms-migration-tool' ) . implode( ',', $migration_errors ) );
			}

			$remaining_after = (int) get_comments(
				array(
					'type'       => ContentTypes::LD_REVIEW_TYPE,
					'count'      => true,
					'status'     => 'any',
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_tlmt_ld_review_migration_failed',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
			$has_more        = $remaining_after > 0;
			$migrated_count  = max( 0, $total_reviews - $remaining_after );

			if ( ! $has_more ) {
				delete_option( self::REVIEW_MIGRATION_TOTAL_OPT );
				$this->cleanup_after_ld_migration_complete();
			}

			return array(
				'has_more'   => $has_more,
				'migrated'   => $migrated_count,
				'total'      => $total_reviews,
				'remaining'  => $remaining_after,
				'batch_size' => $batch_size,
			);
		}

		/**
		 * Run irreversible LearnDash cleanup after every migration step has finished.
		 *
		 * Reviews are the final AJAX step (courses → enrollments/progress → orders →
		 * subscriptions → reviews). Quiz questions must stay available until student
		 * progress has rebuilt attempt answers from the LearnDash question bank.
		 *
		 * @since 2.5.1
		 *
		 * @return void
		 */
		private function cleanup_after_ld_migration_complete() {
			/**
			 * Fires to delete all LearnDash quiz questions after full LD migration.
			 *
			 * @since 2.3.0
			 *
			 * @hook tlml_delete_learndash_quiz_questions
			 */
			do_action( 'tlml_delete_learndash_quiz_questions' );
		}

		/**
		 * Migration from LD courses to tutor courses
		 *
		 * Processes courses in batches so large migrations stay under server timeouts.
		 * Migrated courses change post_type, so each request pulls the next remaining
		 * LearnDash courses without needing a persistent offset.
		 *
		 * @since 1.0.0
		 * @since 2.5.1 Added batch processing return payload.
		 *
		 * @param boolean $return_type Unused legacy parameter.
		 *
		 * @throws \Exception If course not available.
		 * @throws \Throwable If any error occurs.
		 *
		 * @return array{
		 *     has_more: bool,
		 *     migrated: int,
		 *     total: int,
		 *     remaining: int,
		 *     batch_size: int
		 * }
		 */
		public function ld_migrate_course_to_tutor( $return_type = false ) {
			global $wpdb;

			$this->raise_migration_resource_limits();

			$batch_size = (int) apply_filters( 'tlmt_ld_course_migration_batch_size', self::COURSE_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::COURSE_BATCH_SIZE;
			}

			$remaining_total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'draft')",
					self::LD_COURSE_TYPE
				)
			);

			if ( empty( $remaining_total ) ) {
				throw new Exception( __( 'No course available for migration', 'tutor-lms-migration-tool' ) );
			}

			$is_first_batch = ! empty( $_POST['ld_course_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ld_migrate_all_data_to_tutor().

			if ( $is_first_batch ) {
				MigrationLogger::clear_log();
				delete_option( '_tutor_migrated_items_count' );

				if ( SubscriptionHelper::is_subscription_migration_available() ) {
					( new Subscriptions() )->reset_map();
				}

				MigrationLogger::update_migration_log( $remaining_total );
			}

			$migration_log = maybe_unserialize( get_option( MigrationLogger::MIGRATION_STATUS_OPT_NAME ) );
			if ( ! is_array( $migration_log ) || empty( $migration_log['total_course_count'] ) ) {
				MigrationLogger::update_migration_log( $remaining_total );
				$total_courses = $remaining_total;
			} else {
				$total_courses = (int) $migration_log['total_course_count'];
			}

			$ld_courses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_status
					FROM {$wpdb->posts}
					WHERE post_type = %s AND post_status IN ('publish', 'draft')
					ORDER BY ID ASC
					LIMIT %d",
					self::LD_COURSE_TYPE,
					$batch_size
				)
			);

			$course_type = tutor()->course_post_type;
			$course_i    = (int) get_option( '_tutor_migrated_items_count' );

			foreach ( $ld_courses as $ld_course ) {
				++$course_i;
				$course_id = $this->update_post( $ld_course->ID, $course_type, 0, '' );
				if ( $course_id ) {
					try {
						$this->migrate_course( $ld_course->ID, $course_id );

						do_action( 'tlmt_course_migrated', $course_id, MigrationTypes::LD_TO_TUTOR );

						update_option( '_tutor_migrated_items_count', $course_i );

						// Attached Product
						do_action( 'tlmt_attach_product', $course_id, MigrationTypes::LD_TO_TUTOR );

						// Attached Prerequisite.
						$this->attached_prerequisite( $course_id );

						// Attached thumbnail.
						$this->insert_thumbnail( $ld_course->ID, $course_id );

						MigrationLogger::update_course_migration_log( $course_id, true );
					} catch ( \Throwable $th ) {
						$this->revert_failed_course( $course_id, $ld_course );
						MigrationLogger::update_course_migration_log( $course_id, false );
						throw $th;
					}
				}
			}

			$remaining_after = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'draft')",
					self::LD_COURSE_TYPE
				)
			);
			$has_more        = $remaining_after > 0;
			$migrated_count  = max( 0, $total_courses - $remaining_after );

			if ( ! $has_more ) {
				// Migrate Assignment Files.
				tlmt_get_post_obj( ContentTypes::ASSIGNMENT, MigrationTypes::LD_TO_TUTOR )->migrate_assignment_files();
			}

			return array(
				'has_more'   => $has_more,
				'migrated'   => $migrated_count,
				'total'      => $total_courses,
				'remaining'  => $remaining_after,
				'batch_size' => $batch_size,
			);
		}

		/**
		 * Revert a course to LearnDash when migration fails mid-course.
		 *
		 * @since 2.5.1
		 *
		 * @param int      $course_id Course post ID.
		 * @param \WP_Post $ld_course Original LearnDash course post.
		 *
		 * @return void
		 */
		private function revert_failed_course( $course_id, $ld_course ) {
			wp_update_post(
				array(
					'ID'          => $course_id,
					'post_type'   => self::LD_COURSE_TYPE,
					'post_status' => $ld_course->post_status,
				)
			);
		}

		public function attached_prerequisite( $course_id ) {
			$course_data = get_post_meta( $course_id, '_sfwd-courses', true );
			if ( $course_data['sfwd-courses_course_prerequisite'] ) {
				update_post_meta( $course_id, '_tutor_course_prerequisites_ids', $course_data['sfwd-courses_course_prerequisite'] );
			}
		}

		/**
		 * Insert thumbnail ID.
		 */
		public function insert_thumbnail( $new_thumbnail_id, $thumbnail_id ) {
			$thumbnail = get_post_meta( $thumbnail_id, '_thumbnail_id', true );
			if ( $thumbnail ) {
				set_post_thumbnail( $new_thumbnail_id, $thumbnail );
			}
		}


		/**
		 * Insert Enrollment LD to Tutor for a single student.
		 *
		 * @since 2.5.1
		 *
		 * @param int $course_id Tutor course ID (same ID as LearnDash course).
		 * @param int $user_id   Student user ID.
		 *
		 * @return void
		 */
		public function insert_enrollment_for_user( $course_id, $user_id ) {
			global $wpdb;

			$course_id = (int) $course_id;
			$user_id   = (int) $user_id;

			$has_completed = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}learndash_user_activity
					WHERE activity_type = 'course' AND activity_status = 1 AND course_id = %d AND user_id = %d",
					$course_id,
					$user_id
				)
			);

			$has_access = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}learndash_user_activity
					WHERE course_id = %d AND user_id = %d AND activity_type = 'access' AND activity_status = 0",
					$course_id,
					$user_id
				)
			);

			if ( ( $has_access || $has_completed ) && ! tutils()->is_enrolled( $course_id, $user_id ) ) {
				$enrollment_id = wp_insert_post(
					array(
						'post_type'   => 'tutor_enrolled',
						'post_title'  => __( 'Course Enrolled', 'tutor' ),
						'post_status' => 'completed',
						'post_author' => $user_id,
						'post_parent' => $course_id,
					)
				);

				if ( $enrollment_id ) {
					update_user_meta( $user_id, '_is_tutor_student', tutor_time() );
				}
			}

			if ( ! $has_completed ) {
				return;
			}

			$existing_completion = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(comment_ID) FROM {$wpdb->comments}
					WHERE comment_agent = 'TutorLMSPlugin'
						AND comment_type = 'course_completed'
						AND user_id = %d
						AND comment_post_ID = %d",
					$user_id,
					$course_id
				)
			);

			if ( $existing_completion ) {
				return;
			}

			$date = date( 'Y-m-d H:i:s', tutor_time() );

			do {
				$hash    = substr( md5( wp_generate_password( 32 ) . $date . $course_id . $user_id ), 0, 16 );
				$hasHash = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(comment_ID) from {$wpdb->comments}
							WHERE comment_agent = 'TutorLMSPlugin' AND comment_type = 'course_completed' AND comment_content = %s ",
						$hash
					)
				);
			} while ( $hasHash > 0 );

			wp_insert_comment(
				array(
					'comment_type'     => 'course_completed',
					'comment_agent'    => 'TutorLMSPlugin',
					'comment_approved' => 'approved',
					'comment_content'  => $hash,
					'user_id'          => $user_id,
					'comment_author'   => $user_id,
					'comment_post_ID'  => $course_id,
				)
			);
		}

		/**
		 * Migrate LearnDash enrollments and student progress to Tutor in batches.
		 *
		 * Runs after course migration. Each request processes a limited number of
		 * students (not whole courses) so large enrollments stay under server timeouts.
		 * Per-user meta tracks resume state; a course is marked complete only after
		 * all of its students are migrated.
		 *
		 * @since 2.5.1
		 *
		 * @throws \Throwable If enrollment or progress migration fails for a student.
		 *
		 * @return array|false Batch payload, or false on blocking error.
		 */
		public function ld_enrollments_migrate() {
			$this->raise_migration_resource_limits();

			if ( ! $this->has_migrated_tutor_courses() ) {
				ErrorHandler::set_error(
					ContentTypes::ENROLLMENTS,
					__( 'No Tutor courses found. Complete course migration before migrating enrollments.', 'tutor-lms-migration-tool' )
				);
				return false;
			}

			$batch_size = (int) apply_filters( 'tlmt_ld_enrollment_migration_batch_size', self::ENROLLMENT_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::ENROLLMENT_BATCH_SIZE;
			}

			$is_first_batch = ! empty( $_POST['ld_enrollment_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ld_migrate_all_data_to_tutor().
			if ( $is_first_batch ) {
				delete_option( self::ENROLLMENT_MIGRATION_TOTAL_OPT );
				delete_option( '_tutor_migrated_items_count' );
				$this->mark_courses_without_enrollment_activity();
			}

			$remaining_total = $this->count_pending_enrollment_users();
			if ( $is_first_batch ) {
				update_option( self::ENROLLMENT_MIGRATION_TOTAL_OPT, $remaining_total, false );
			}

			$total_enrollments = (int) get_option( self::ENROLLMENT_MIGRATION_TOTAL_OPT, $remaining_total );

			if ( 0 === $remaining_total ) {
				$this->mark_courses_without_enrollment_activity();
				delete_option( self::ENROLLMENT_MIGRATION_TOTAL_OPT );
				return array(
					'has_more'   => false,
					'migrated'   => $total_enrollments,
					'total'      => $total_enrollments,
					'remaining'  => 0,
					'batch_size' => $batch_size,
				);
			}

			$students = $this->get_pending_enrollment_users( $batch_size );
			$item_i   = (int) get_option( '_tutor_migrated_items_count' );

			try {
				$progress = \Themeum\TutorLMSMigrationTool\Factories\StudentProgressFactory::create( MigrationTypes::LD_TO_TUTOR );
			} catch ( \Throwable $th ) {
				ErrorHandler::set_error(
					ContentTypes::ENROLLMENTS,
					__( 'Error creating student progress migration object.', 'tutor-lms-migration-tool' )
				);
				return false;
			}

			$touched_courses = array();

			foreach ( $students as $student ) {
				++$item_i;
				$course_id = (int) $student->course_id;
				$user_id   = (int) $student->user_id;

				try {
					$this->insert_enrollment_for_user( $course_id, $user_id );
					$progress->migrate( $course_id, $user_id );
					update_user_meta( $user_id, $this->enrollment_user_meta_key( $course_id ), 1 );
					update_option( '_tutor_migrated_items_count', $item_i );
					$touched_courses[ $course_id ] = true;
				} catch ( \Throwable $th ) {
					ErrorHandler::set_error(
						ContentTypes::ENROLLMENTS,
						sprintf(
							/* translators: 1: course ID, 2: user ID */
							__( 'Failed to migrate enrollment for course %1$d, user %2$d.', 'tutor-lms-migration-tool' ),
							$course_id,
							$user_id
						) . ' ' . $th->getMessage()
					);
					throw $th;
				}
			}

			foreach ( array_keys( $touched_courses ) as $course_id ) {
				if ( 0 === $this->count_pending_enrollment_users_for_course( (int) $course_id ) ) {
					update_post_meta( (int) $course_id, self::ENROLLMENT_MIGRATED_META, 1 );
				}
			}

			$this->mark_courses_without_enrollment_activity();

			$remaining_after = $this->count_pending_enrollment_users();
			$has_more        = $remaining_after > 0;
			$migrated_count  = max( 0, $total_enrollments - $remaining_after );

			if ( ! $has_more ) {
				delete_option( self::ENROLLMENT_MIGRATION_TOTAL_OPT );
			}

			return array(
				'has_more'   => $has_more,
				'migrated'   => $migrated_count,
				'total'      => $total_enrollments,
				'remaining'  => $remaining_after,
				'batch_size' => $batch_size,
			);
		}

		/**
		 * User meta key for a migrated enrollment/progress pair.
		 *
		 * @since 2.5.1
		 *
		 * @param int $course_id Course ID.
		 *
		 * @return string
		 */
		private function enrollment_user_meta_key( int $course_id ): string {
			return self::ENROLLMENT_USER_META_PREFIX . $course_id;
		}

		/**
		 * Mark Tutor courses that have no LD enrollment/completion activity as done.
		 *
		 * @since 2.5.1
		 *
		 * @return void
		 */
		private function mark_courses_without_enrollment_activity() {
			global $wpdb;

			$course_type = tutor()->course_post_type;
			$course_ids  = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm
						ON p.ID = pm.post_id AND pm.meta_key = %s
					LEFT JOIN {$wpdb->prefix}learndash_user_activity a
						ON a.course_id = p.ID
						AND (
							( a.activity_type = 'access' AND a.activity_status = 0 )
							OR ( a.activity_type = 'course' AND a.activity_status = 1 )
						)
					WHERE p.post_type = %s
						AND p.post_status IN ('publish', 'draft', 'private')
						AND pm.meta_id IS NULL
						AND a.activity_id IS NULL",
					self::ENROLLMENT_MIGRATED_META,
					$course_type
				)
			);

			foreach ( $course_ids ? $course_ids : array() as $course_id ) {
				update_post_meta( (int) $course_id, self::ENROLLMENT_MIGRATED_META, 1 );
			}
		}

		/**
		 * Count pending student-course enrollment pairs.
		 *
		 * @since 2.5.1
		 *
		 * @return int
		 */
		private function count_pending_enrollment_users(): int {
			global $wpdb;

			$course_type = tutor()->course_post_type;

			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM (
						SELECT DISTINCT a.course_id, a.user_id
						FROM {$wpdb->prefix}learndash_user_activity a
						INNER JOIN {$wpdb->posts} p ON p.ID = a.course_id
						LEFT JOIN {$wpdb->postmeta} pm
							ON p.ID = pm.post_id AND pm.meta_key = %s
						LEFT JOIN {$wpdb->usermeta} um
							ON um.user_id = a.user_id
							AND um.meta_key = CONCAT(%s, a.course_id)
						WHERE p.post_type = %s
							AND p.post_status IN ('publish', 'draft', 'private')
							AND pm.meta_id IS NULL
							AND um.umeta_id IS NULL
							AND (
								( a.activity_type = 'access' AND a.activity_status = 0 )
								OR ( a.activity_type = 'course' AND a.activity_status = 1 )
							)
					) pending_enrollments",
					self::ENROLLMENT_MIGRATED_META,
					self::ENROLLMENT_USER_META_PREFIX,
					$course_type
				)
			);
		}

		/**
		 * Count pending students for a single course.
		 *
		 * @since 2.5.1
		 *
		 * @param int $course_id Course ID.
		 *
		 * @return int
		 */
		private function count_pending_enrollment_users_for_course( int $course_id ): int {
			global $wpdb;

			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM (
						SELECT DISTINCT a.user_id
						FROM {$wpdb->prefix}learndash_user_activity a
						LEFT JOIN {$wpdb->usermeta} um
							ON um.user_id = a.user_id
							AND um.meta_key = %s
						WHERE a.course_id = %d
							AND um.umeta_id IS NULL
							AND (
								( a.activity_type = 'access' AND a.activity_status = 0 )
								OR ( a.activity_type = 'course' AND a.activity_status = 1 )
							)
					) pending_course_enrollments",
					$this->enrollment_user_meta_key( $course_id ),
					$course_id
				)
			);
		}

		/**
		 * Fetch a batch of pending student-course enrollment pairs.
		 *
		 * @since 2.5.1
		 *
		 * @param int $limit Batch size.
		 *
		 * @return array<int, object{course_id: int, user_id: int}>
		 */
		private function get_pending_enrollment_users( int $limit ): array {
			global $wpdb;

			$course_type = tutor()->course_post_type;

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT a.course_id, a.user_id
					FROM {$wpdb->prefix}learndash_user_activity a
					INNER JOIN {$wpdb->posts} p ON p.ID = a.course_id
					LEFT JOIN {$wpdb->postmeta} pm
						ON p.ID = pm.post_id AND pm.meta_key = %s
					LEFT JOIN {$wpdb->usermeta} um
						ON um.user_id = a.user_id
						AND um.meta_key = CONCAT(%s, a.course_id)
					WHERE p.post_type = %s
						AND p.post_status IN ('publish', 'draft', 'private')
						AND pm.meta_id IS NULL
						AND um.umeta_id IS NULL
						AND (
							( a.activity_type = 'access' AND a.activity_status = 0 )
							OR ( a.activity_type = 'course' AND a.activity_status = 1 )
						)
					ORDER BY a.course_id ASC, a.user_id ASC
					LIMIT %d",
					self::ENROLLMENT_MIGRATED_META,
					self::ENROLLMENT_USER_META_PREFIX,
					$course_type,
					$limit
				)
			);

			return $results ? $results : array();
		}

		/**
		 * Learndash orders to tutor migration for native, WC and EDD.
		 *
		 * Processes one-time (non-subscription) transactions in batches.
		 *
		 * @since 2.3.0
		 * @since 2.5.1 Added batch processing return payload.
		 *
		 * @return array|false Batch payload, or false on blocking error.
		 */
		public function ld_order_migrate() {
			global $wpdb;

			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			$this->raise_migration_resource_limits();

			if ( ! $this->has_migrated_tutor_courses() ) {
				ErrorHandler::set_error(
					'order',
					__( 'No Tutor courses found. Complete course migration before migrating sales data.', 'tutor-lms-migration-tool' )
				);
				return false;
			}

			$batch_size = (int) apply_filters( 'tlmt_ld_order_migration_batch_size', self::ORDER_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::ORDER_BATCH_SIZE;
			}

			$is_first_batch = ! empty( $_POST['ld_order_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ld_migrate_all_data_to_tutor().
			if ( $is_first_batch ) {
				delete_option( self::ORDER_MIGRATION_TOTAL_OPT );
				delete_option( '_tutor_migrated_items_count' );
			}

			$subscription_related_ids    = SubscriptionHelper::is_subscription_migration_available()
				? SubscriptionHelper::get_subscription_related_transaction_ids()
				: array();
			$subscription_related_lookup = array_fill_keys( $subscription_related_ids, true );

			$remaining_total = $this->count_one_time_ld_orders( $subscription_related_ids );
			if ( $is_first_batch ) {
				update_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total, false );
			}

			$total_orders = (int) get_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total );

			if ( 0 === $remaining_total ) {
				delete_option( self::ORDER_MIGRATION_TOTAL_OPT );
				return array(
					'has_more'  => false,
					'migrated'  => $total_orders,
					'total'     => $total_orders,
					'remaining' => 0,
				);
			}

			$ld_orders = $this->get_one_time_ld_orders( $subscription_related_ids, $batch_size );
			$item_i    = (int) get_option( '_tutor_migrated_items_count' );

			$order_errors      = array();
			$tutor_monetize_by = tutils()->get_option( 'monetize_by' );

			try {
				$order_obj = tlmt_get_order_obj( $tutor_monetize_by, MigrationTypes::LD_TO_TUTOR );
			} catch ( \Throwable $th ) {
				ErrorHandler::set_error( 'order', __( 'Error creating migration order object', 'tutor-lms-migration-tool' ) );
				return false;
			}

			foreach ( $ld_orders as $order ) {
				++$item_i;
				update_option( '_tutor_migrated_items_count', $item_i );

				// Defer subscription transactions to the subscriptions migration step.
				if ( isset( $subscription_related_lookup[ (int) $order->ID ] ) ) {
					continue;
				}

				$course_id = get_post_meta( $order->ID, 'post_id', true );

				if ( ! $course_id ) {
					continue;
				}

				try {
					$order_obj->migrate( $order, $course_id );
					$order_obj->remove_orders( $order->ID );
				} catch ( \Throwable $th ) {
					if ( isset( $order_errors[ $course_id ] ) ) {
						array_push( $order_errors[ $course_id ], $order->ID );
					} else {
						$order_errors[ $course_id ] = array( $order->ID );
					}
				}
			}

			if ( $order_errors ) {
				$err_msg = __( 'Failed to migrate orders for ', 'tutor-lms-migration-tool' );
				foreach ( $order_errors as $course_id => $order_ids ) {
					$err_msg .= __( 'Orders : ', 'tutor-lms-migration-tool ' ) . implode( ',', $order_ids ) . __( ' of Course ', 'tutor-lms-migration-tool' ) . $course_id . ' ';
				}
				ErrorHandler::set_error( 'order', $err_msg );
			}

			$remaining_after = $this->count_one_time_ld_orders( $subscription_related_ids );
			$has_more        = $remaining_after > 0;
			$migrated_count  = max( 0, $total_orders - $remaining_after );

			if ( ! $has_more ) {
				delete_option( self::ORDER_MIGRATION_TOTAL_OPT );
				$stored_errors = ErrorHandler::get_errors( false );
				if ( ! empty( $stored_errors['order'] ) || $order_errors ) {
					return false;
				}
			}

			return array(
				'has_more'   => $has_more,
				'migrated'   => $migrated_count,
				'total'      => $total_orders,
				'remaining'  => $remaining_after,
				'batch_size' => $batch_size,
			);
		}

		/**
		 * Count LearnDash one-time transactions (excluding subscription-related).
		 *
		 * @since 2.5.1
		 *
		 * @param int[] $exclude_ids Transaction IDs to exclude.
		 *
		 * @return int
		 */
		private function count_one_time_ld_orders( array $exclude_ids ): int {
			global $wpdb;

			if ( empty( $exclude_ids ) ) {
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
						'sfwd-transactions',
						'publish'
					)
				);
			}

			$exclude_ids  = array_map( 'intval', $exclude_ids );
			$placeholders = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );
			$sql          = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND ID NOT IN ({$placeholders})";
			$params       = array_merge( array( 'sfwd-transactions', 'publish' ), $exclude_ids );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders built dynamically for NOT IN.
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}

		/**
		 * Fetch a batch of LearnDash one-time transactions.
		 *
		 * @since 2.5.1
		 *
		 * @param int[] $exclude_ids Transaction IDs to exclude.
		 * @param int   $limit       Batch size.
		 *
		 * @return array
		 */
		private function get_one_time_ld_orders( array $exclude_ids, int $limit ): array {
			global $wpdb;

			if ( empty( $exclude_ids ) ) {
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_author, post_date, post_content, post_title, post_status
						FROM {$wpdb->posts}
						WHERE post_type = %s AND post_status = %s
						ORDER BY ID ASC
						LIMIT %d",
						'sfwd-transactions',
						'publish',
						$limit
					)
				);
			}

			$exclude_ids  = array_map( 'intval', $exclude_ids );
			$placeholders = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );
			$sql          = "SELECT ID, post_author, post_date, post_content, post_title, post_status
				FROM {$wpdb->posts}
				WHERE post_type = %s AND post_status = %s AND ID NOT IN ({$placeholders})
				ORDER BY ID ASC
				LIMIT %d";
			$params       = array_merge( array( 'sfwd-transactions', 'publish' ), $exclude_ids, array( $limit ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders built dynamically for NOT IN.
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		}

		/**
		 * Migrate LearnDash recurring subscriptions to Tutor native subscriptions.
		 *
		 * @since 2.5.0
		 * @since 2.5.1 Added batch processing return payload.
		 *
		 * @return array|false Batch payload, or false on blocking error.
		 */
		public function ld_subscriptions_migrate() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			$this->raise_migration_resource_limits();

			if ( ! $this->has_migrated_tutor_courses() ) {
				ErrorHandler::set_error(
					ContentTypes::SUBSCRIPTIONS,
					__( 'No Tutor courses found. Complete course migration before migrating subscriptions.', 'tutor-lms-migration-tool' )
				);
				return false;
			}

			if ( ! SubscriptionHelper::is_subscription_migration_available() ) {
				// Nothing to migrate; allow the pipeline to continue.
				return array(
					'has_more'  => false,
					'migrated'  => 0,
					'total'     => 0,
					'remaining' => 0,
				);
			}

			$batch_size = (int) apply_filters( 'tlmt_ld_subscription_migration_batch_size', self::SUBSCRIPTION_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::SUBSCRIPTION_BATCH_SIZE;
			}

			$is_first_batch = ! empty( $_POST['ld_subscription_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ld_migrate_all_data_to_tutor().
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
					ErrorHandler::set_error(
						ContentTypes::SUBSCRIPTIONS,
						implode( ' ', $result['errors'] )
					);
				}

				if ( ! $has_more ) {
					delete_option( self::SUBSCRIPTION_MIGRATION_TOTAL_OPT );
					$stored_errors = ErrorHandler::get_errors( false );
					if ( ! empty( $stored_errors[ ContentTypes::SUBSCRIPTIONS ] ) ) {
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
				ErrorHandler::set_error(
					ContentTypes::SUBSCRIPTIONS,
					sprintf(
						/* translators: %s: error message */
						__( 'Subscription migration failed: %s', 'tutor-lms-migration-tool' ),
						$th->getMessage()
					)
				);
				return false;
			}
		}

		/*
		* Progress Migration
		*/
		public function _get_ld_live_progress_course_migrating_info() {
			$migrated_count = (int) get_option( '_tutor_migrated_items_count' );
			wp_send_json_success( array( 'migrated_count' => $migrated_count ) );
		}

		/**
		 * Whether any Tutor LMS courses exist (migration prerequisite for sales data).
		 *
		 * @since 2.5.1
		 *
		 * @return bool
		 */
		private function has_migrated_tutor_courses(): bool {
			global $wpdb;

			$course_type = tutor()->course_post_type;
			$count       = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'draft', 'private')",
					$course_type
				)
			);

			return $count > 0;
		}

		public function insert_post( $post_title, $post_content, $author_id, $post_type = 'topics', $menu_order = 0, $post_parent = '' ) {
			$post_arg = array(
				'post_type'    => $post_type,
				'post_title'   => $post_title,
				'post_content' => $post_content,
				'post_status'  => 'publish',
				'post_author'  => $author_id,
				'post_parent'  => $post_parent,
				'menu_order'   => $menu_order,
			);
			return wp_insert_post( $post_arg );
		}

		public function update_post( $post_id, $post_type = 'topics', $menu_order = 0, $post_parent = '' ) {
			global $wpdb;
			$post_arg = array(
				'ID'          => $post_id,
				'post_type'   => $post_type,
				'post_parent' => $post_parent,
				'menu_order'  => $menu_order,
			);
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}posts SET post_type=%s, post_parent=%s, menu_order=%s WHERE ID=%s", $post_type, $post_parent, $menu_order, $post_id ) );
			return $post_id;
		}

		/**
		 * Migrate a quiz
		 *
		 * @since 1.0.0
		 *
		 * @since 2.3.0 $migrate_map added to know the migrated question & answers
		 *
		 * @param int $old_quiz_id LD quiz id.
		 *
		 * @return void
		 */
		public function migrate_quiz( $old_quiz_id ) {
			global $wpdb;
			$xml          = '';
			$question_ids = get_post_meta( $old_quiz_id, 'ld_quiz_questions', true );
			$is_table     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', "{$wpdb->prefix}learndash_pro_quiz_question" ) );

			// Store the question and anser id.
			$migrate_map = array();

			if ( ! empty( $question_ids ) ) {
				$question_ids = array_keys( $question_ids );
				foreach ( $question_ids as $question_single ) {
					$question_id    = get_post_meta( $question_single, 'question_pro_id', true );
					$ld_question_id = $question_id;

					$result = array();
					if ( $is_table ) {
						$result = $wpdb->get_row(
							$wpdb->prepare( "SELECT id, title, question, points, answer_type, answer_data FROM {$wpdb->prefix}learndash_pro_quiz_question where id = %d", $question_id ),
							ARRAY_A
						);
					} else {
						$result = $wpdb->get_row(
							$wpdb->prepare( "SELECT id, title, question, points, answer_type, answer_data FROM {$wpdb->prefix}wp_pro_quiz_question where id = %d", $question_id ),
							ARRAY_A
						);
					}

					$ld_answer_data = array();
					if ( ! empty( $result ) ) {
						$ld_answer_data = maybe_unserialize( $result['answer_data'] );
					}

					if ( empty( $result ) ) {
						continue;
					}

					$question                         = array();
					$question['quiz_id']              = $old_quiz_id;
					$question['question_title']       = $result['title'];
					$question['question_description'] = (string) $result['question'];
					$question['question_mark']        = $result['points'];
					switch ( $result['answer_type'] ) {
						case 'single':
							$question['question_type'] = 'multiple_choice';
							break;

						case 'multiple':
							$question['question_type'] = 'multiple_choice';
							break;

						case 'sort_answer':
						case 'matrix_sort_answer':
							$question['question_type'] = 'ordering';
							break;

						case 'essay':
						case 'assessment_answer':
						case 'free_answer':
							$question['question_type'] = 'open_ended';
							break;

						case 'cloze_answer':
							$question['question_type'] = 'fill_in_the_blank';
							break;
					}

					$question_settings = array(
						'question_type' => $result['answer_type'],
						'question_mark' => $result['points'],
					);

					if ( 'multiple_choice' === $question['question_type'] ) {
						$question_settings['has_multiple_correct_answer'] = 1;
					}

					$question['question_settings'] = maybe_serialize( $question_settings );

					$wpdb->insert( $wpdb->prefix . 'tutor_quiz_questions', $question );

					// Will Return $questions.
					$question_id = $wpdb->insert_id;

					if ( $question_id ) {
						foreach ( $ld_answer_data as $key => $value ) {

							$ans_arr = $value->get_object_as_array();

							$tutor_answer_data = array(
								'belongs_question_id'   => $question_id,
								'belongs_question_type' => $question['question_type'],
								'answer_view_format'    => 'text',
								'answer_title'          => $ans_arr['_answer'],
								'answer_order'          => 0,
								'image_id'              => 0,
								'is_correct'            => $ans_arr['_correct'],
								'answer_two_gap_match'  => false,
							);

							if ( 'fill_in_the_blank' === $question['question_type'] ) {
								// Extract all {string} values.
								$str = $tutor_answer_data['answer_title'];
								preg_match_all( '/\{(.*?)\}/', $str, $matches );
								$extracted = implode( '|', $matches[1] );

								// Replace each {string} with {dash}.
								$updated_str = preg_replace( '/\{.*?\}/', '{dash}', $str );

								$tutor_answer_data['answer_title']         = $updated_str;
								$tutor_answer_data['answer_two_gap_match'] = $extracted;
							}

							$wpdb->insert( $wpdb->prefix . 'tutor_quiz_question_answers', $tutor_answer_data );
							$answer_id = $wpdb->insert_id;
							if ( $answer_id ) {
								$migrate_map[ $ld_question_id ][] = array(
									'tutor_answer_id'   => $answer_id,
									'tutor_question_id' => $question_id,
								);
							}
						}
					}
				}

				update_post_meta( $old_quiz_id, 'tutor_migrated_question_answer_map', $migrate_map );
			}
		}

		public function migrate_course( $course_id, $new_course_id ) {
			global $wpdb;

			$section_heading = get_post_meta( $course_id, 'course_sections', true );
			$section_heading = $section_heading ? json_decode( $section_heading, true ) : array(
				array(
					'order'      => 0,
					'post_title' => 'Tutor Topics',
				),
			);

			$total_data = LDLMS_Factory_Post::course_steps( $course_id );
			$total_data = $total_data->get_steps();

			if ( empty( $total_data ) ) {
				return;
			}

			$lesson_post_type = tutor()->lesson_post_type;

			$i             = 0;
			$section_count = 0;
			$topic_id      = 0;
			$author_id     = get_post_field( 'post_author', $course_id );
			foreach ( $total_data['sfwd-lessons'] as $lesson_key => $lesson_data ) {

				// Topic Section.
				$check = $i == 0 ? 0 : $i + 1;
				if ( isset( $section_heading[ $section_count ]['order'] ) ) {
					if ( $section_heading[ $section_count ]['order'] == $check ) {
						// Insert Topics
						$topic_id = $this->insert_post( $section_heading[ $section_count ]['post_title'], '', $author_id, 'topics', $i, $new_course_id );
						++$section_count;
					}
				}

				if ( $topic_id ) {
					if ( $this->is_assignment( $lesson_key, ContentTypes::LD_LESSONS ) && tlmt_has_tutor_pro() ) {
						$lesson_id = $this->migrate_assignment( $lesson_key, $topic_id, ContentTypes::LD_LESSONS );
					} else {
						$lesson_id = $this->update_post( $lesson_key, $lesson_post_type, $i, $topic_id );
						update_post_meta( $lesson_id, '_tutor_course_id_for_lesson', $course_id );
						do_action( 'tlmt_lesson_migrated', $lesson_id, MigrationTypes::LD_TO_TUTOR );
					}

					foreach ( $lesson_data['sfwd-topic'] as $lesson_inner_key => $lesson_inner ) {

						if ( $this->is_assignment( $lesson_inner_key, ContentTypes::LD_TOPIC ) && tlmt_has_tutor_pro() ) {
							$lesson_id = $this->migrate_assignment( $lesson_inner_key, $topic_id, ContentTypes::LD_TOPIC );
						} else {
							$lesson_id = $this->update_post( $lesson_inner_key, $lesson_post_type, $i, $topic_id ); // Insert Lesson.
							update_post_meta( $lesson_id, '_tutor_course_id_for_lesson', $course_id );
							do_action( 'tlmt_lesson_migrated', $lesson_id, MigrationTypes::LD_TO_TUTOR );
						}

						foreach ( $lesson_inner['sfwd-quiz'] as $quiz_key => $quiz_data ) {
							$quiz_id = $this->update_post( $quiz_key, 'tutor_quiz', $i, $topic_id );

							if ( $quiz_id ) {
								$this->migrate_quiz( $quiz_id );
								do_action( 'tlmt_quiz_migrated', $quiz_id, MigrationTypes::LD_TO_TUTOR );
							}
						}
					}

					foreach ( $lesson_data['sfwd-quiz'] as $quiz_key => $quiz_data ) {
						$quiz_id = $this->update_post( $quiz_key, 'tutor_quiz', $i, $topic_id );
						if ( $quiz_id ) {
							$this->migrate_quiz( $quiz_id );
							do_action( 'tlmt_quiz_migrated', $quiz_id, MigrationTypes::LD_TO_TUTOR );
						}
					}
				}
				++$i;
			}

			if ( ! empty( $total_data['sfwd-quiz'] ) ) {
				if ( ! $topic_id ) {
					$topic_id = $this->insert_post( 'Tutor Topics', '', $author_id, 'topics', $i, $new_course_id );
					++$i;
				}
				foreach ( $total_data['sfwd-quiz'] as $quiz_key => $quiz_data ) {
					$quiz_id = $this->update_post( $quiz_key, 'tutor_quiz', $i, $topic_id );
					if ( $quiz_id ) {
						$this->migrate_quiz( $quiz_id );
						do_action( 'tlmt_quiz_migrated', $quiz_id, MigrationTypes::LD_TO_TUTOR );
					}
				}
			}
		}

		/**
		 * Check whether the give lesson is assignment in tutor context
		 *
		 * @since 2.3.0
		 *
		 * @param integer $ld_lesson_id LD Lesson id.
		 * @param string  $ld_content_type LD Content type i.e (lessons/topic).
		 *
		 * @return boolean
		 */
		private function is_assignment( int $ld_lesson_id, string $ld_content_type ) {
			$lesson_meta = get_post_meta( $ld_lesson_id, "_{$ld_content_type}", true );
			if ( $lesson_meta ) {
				return isset( $lesson_meta[ "{$ld_content_type}_lesson_assignment_upload" ] ) && 'on' === $lesson_meta[ "{$ld_content_type}_lesson_assignment_upload" ];
			}

			return false;
		}

		/**
		 * Migrate assignment
		 *
		 * Fire hooks: tlmt_assignment_migrated, after assignment migration
		 *
		 * @since 2.3.0
		 *
		 * @param integer $ld_lesson_id LD lesson id.
		 * @param integer $tutor_topic_id Tutor topic id.
		 * @param string  $ld_content_type LD Content type i.e (lessons/topic).
		 *
		 * @return int 0 if failed to migrate
		 */
		public function migrate_assignment( int $ld_lesson_id, int $tutor_topic_id, string $ld_content_type ) {
			$lesson = get_post( $ld_lesson_id );
			if ( ! is_a( $lesson, 'WP_Post' ) ) {
				return 0;
			}

			$lesson_meta = get_post_meta( $ld_lesson_id, "_{$ld_content_type}", true );
			if ( ! isset( $lesson_meta[ "{$ld_content_type}_lesson_assignment_upload" ] ) || 'on' !== $lesson_meta[ "{$ld_content_type}_lesson_assignment_upload" ] ) {
				return 0;
			}

			try {
				tlmt_get_post_obj( ContentTypes::ASSIGNMENT, MigrationTypes::LD_TO_TUTOR )->migrate( $lesson, $tutor_topic_id );

				do_action( 'tlmt_assignment_migrated', $ld_lesson_id, MigrationTypes::LD_TO_TUTOR );

				return $ld_lesson_id;
			} catch ( \Throwable $th ) {
				return 0;
			}
		}

		/**
		 * Raise time and memory limits for a migration AJAX batch.
		 *
		 * @since 2.5.1
		 *
		 * @return void
		 */
		private function raise_migration_resource_limits() {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			$current = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
			$target  = 256 * MB_IN_BYTES;

			// Do not lower unlimited (-1) or an already-higher limit.
			if ( -1 !== $current && $current < $target ) {
				@ini_set( 'memory_limit', '256M' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.memory_limit_Disallowed
			}
		}
	}
}
