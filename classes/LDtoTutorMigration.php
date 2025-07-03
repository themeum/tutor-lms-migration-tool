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
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\MigrationLogger;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'LDtoTutorMigration' ) ) {
	class LDtoTutorMigration {

		const LD_COURSE_TYPE = 'sfwd-courses';

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
							$this->ld_migrate_course_to_tutor();
							tlmt_get_post_obj( ContentTypes::ASSIGNMENT, MigrationTypes::LD_TO_TUTOR )->migrate_assignment_files();
						} catch ( \Throwable $th ) {
							error_log( $th->getMessage() );
						}
						wp_send_json_success();
						break;
					case ContentTypes::ORDERS:
						$this->ld_order_migrate();
						wp_send_json_success();
						break;
					case ContentTypes::COURSE_REVIEWS:
						$this->ld_reviews_migrate();
						break;
				}

				// Send response & clear on finish.
				$status = MigrationLogger::get_log();
				MigrationLogger::clear_log();
				wp_send_json_success( $status );
			}

			wp_send_json_error();
		}

		/**
		 * Migrate learndash reviews to tutor.
		 *
		 * @since 2.3.0
		 *
		 * @return void wp_json response.
		 */
		public function ld_reviews_migrate() {
			$ld_reviews = get_comments( array( 'type' => ContentTypes::LD_REVIEW_TYPE ) );
			$item_idx   = (int) get_option( '_tutor_migrated_items_count' );

			$migration_errors = array();

			try {
				$reviews = tlmt_get_review_obj( MigrationTypes::LD_TO_TUTOR );
			} catch ( \Throwable $th ) {
				ErrorHandler::set_error( 'review', __( 'Error review migration order object', 'tutor-lms-migration-tool' ) );
				return;
			}

			if ( count( $ld_reviews ) ) {
				foreach ( $ld_reviews as $review ) {
					$item_idx++;
					update_option( '_tutor_migrated_items_count', $item_idx );
					try {
						$reviews->migrate( $review );
					} catch ( \Throwable $th ) {
						array_push( $migration_errors, $review->comment_ID );
					}
				}
			}

			if ( $migration_errors ) {
				ErrorHandler::set_error( 'review', __( 'Could not migrate reviews :', 'tutor-lms-migration-tool' ) . implode( ',', $migration_errors ) );
			}
		}
		/**
		 * Migration from LD courses to tutor courses
		 *
		 * @since 1.0.0
		 *
		 * @param boolean $return_type Return type.
		 *
		 * @throws \Exception If course not available.
		 * @throws \Throwable If any error occurs.
		 *
		 * @return void
		 */
		public function ld_migrate_course_to_tutor( $return_type = false ) {
			global $wpdb;
			$ld_courses = $wpdb->get_results( "SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_status FROM {$wpdb->posts} WHERE post_type = 'sfwd-courses' AND (post_status = 'publish' OR post_status = 'draft');" );

			$total_courses = tutils()->count( $ld_courses );
			$course_type   = tutor()->course_post_type;

			if ( $total_courses ) {
				MigrationLogger::update_migration_log( $total_courses );

				$course_i = (int) get_option( '_tutor_migrated_items_count' );
				foreach ( $ld_courses as $ld_course ) {
					$course_i++;
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

							// Add Enrollments.
							$this->insert_enrollment( $course_id );

							// Attached thumbnail.
							$this->insert_thumbnail( $ld_course->ID, $course_id );

							/**
							 * Insert Student Progress
							 *
							 * @since 2.3.0
							 */
							do_action( 'tlmt_student_progress_migrated', MigrationTypes::LD_TO_TUTOR );

							MigrationLogger::update_course_migration_log( $course_id, true );
						} catch ( \Throwable $th ) {
							// Revert the status if failed to migrate.
							$revert = array(
								'ID'          => $course_id,
								'post_status' => self::LD_COURSE_TYPE,
							);

							wp_update_post( $revert );

							MigrationLogger::update_course_migration_log( $course_id, true );
							throw $th;
						}
					}
				}
			}

			throw new Exception( __( 'No course available for migration', 'tutor-lms-migration-tool' ) );
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
		 * Insert Enrollment LD to Tutor.
		 */
		public function insert_enrollment( $course_id ) {
			global $wpdb;
			$ld_course_complete_datas = $wpdb->get_results( "SELECT * from {$wpdb->prefix}learndash_user_activity WHERE activity_type = 'course' AND activity_status = 1" );

			foreach ( $ld_course_complete_datas as $ld_course_complete_data ) {
				$user_id            = $ld_course_complete_data->user_id;
				$complete_course_id = $ld_course_complete_data->course_id;

				if ( ! tutils()->is_enrolled( $course_id, $user_id ) ) {

					$date = date( 'Y-m-d H:i:s', tutor_time() );

					do {
						$hash    = substr( md5( wp_generate_password( 32 ) . $date . $complete_course_id . $user_id ), 0, 16 );
						$hasHash = (int) $wpdb->get_var(
							$wpdb->prepare(
								"SELECT COUNT(comment_ID) from {$wpdb->comments}
                                    WHERE comment_agent = 'TutorLMSPlugin' AND comment_type = 'course_completed' AND comment_content = %s ",
								$hash
							)
						);

					} while ( $hasHash > 0 );

					$tutor_course_complete_data = array(
						'comment_type'     => 'course_completed',
						'comment_agent'    => 'TutorLMSPlugin',
						'comment_approved' => 'approved',
						'comment_content'  => $hash,
						'user_id'          => $user_id,
						'comment_author'   => $user_id,
						'comment_post_ID'  => $complete_course_id,
					);

					$isEnrolled = wp_insert_comment( $tutor_course_complete_data );

				}
			}

			$ld_enrollments = $wpdb->get_results( $wpdb->prepare( "SELECT * from {$wpdb->prefix}learndash_user_activity WHERE course_id = %d AND activity_type = 'access' AND activity_status = 0", $course_id ) );

			foreach ( $ld_enrollments as $ld_enrollment ) {
				$user_id = $ld_enrollment->user_id;

				if ( ! tutils()->is_enrolled( $course_id, $user_id ) ) {

					$title                 = __( 'Course Enrolled', 'tutor' );
					$tutor_enrollment_data = array(
						'post_type'   => 'tutor_enrolled',
						'post_title'  => $title,
						'post_status' => 'completed',
						'post_author' => $user_id,
						'post_parent' => $course_id,
					);

					$isEnrolled = wp_insert_post( $tutor_enrollment_data );

					if ( $isEnrolled ) {
						// Mark Current User as Students with user meta data
						update_user_meta( $user_id, '_is_tutor_student', $order_time );
					}
				}
			}
		}

		/**
		 * Learndash orders to tutor migration for native, WC and EDD.
		 *
		 * @since 2.3.0
		 *
		 * @return void wp_json response.
		 */
		public function ld_order_migrate() {
			global $wpdb;

			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			$tutor_monetize_by = tutils()->get_option( 'monetize_by' );

			$ld_orders = $wpdb->get_results( "SELECT ID, post_author, post_date, post_content, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'sfwd-transactions' AND post_status = 'publish';" );
			$item_i    = (int) get_option( '_tutor_migrated_items_count' );

			$order_errors = array();

			try {
				$order_obj = tlmt_get_order_obj( $tutor_monetize_by, MigrationTypes::LD_TO_TUTOR );
			} catch ( \Throwable $th ) {
				ErrorHandler::set_error( 'order', __( 'Error creating migration order object', 'tutor-lms-migration-tool' ) );
				return;
			}

			foreach ( $ld_orders as $order ) {
				$item_i++;
				update_option( '_tutor_migrated_items_count', $item_i );
				$course_id = get_post_meta( $order->ID, 'post_id', true );

				if ( ! $course_id ) {
					$order_obj->remove_orders( $order->ID );
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
		}

		/*
		* Progress Migration
		*/
		public function _get_ld_live_progress_course_migrating_info() {
			$migrated_count = (int) get_option( '_tutor_migrated_items_count' );
			wp_send_json_success( array( 'migrated_count' => $migrated_count ) );
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

					$question['question_settings'] = maybe_serialize(
						array(
							'question_type' => $result['answer_type'],
							'question_mark' => $result['points'],
						)
					);

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
				do_action( 'tlmt_quiz_migrated', $old_quiz_id, MigrationTypes::LD_TO_TUTOR );
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
			foreach ( $total_data['sfwd-lessons'] as $lesson_key => $lesson_data ) {
				$author_id = get_post_field( 'post_author', $course_id );

				// Topic Section.
				$check = $i == 0 ? 0 : $i + 1;
				if ( isset( $section_heading[ $section_count ]['order'] ) ) {
					if ( $section_heading[ $section_count ]['order'] == $check ) {
						// Insert Topics
						$topic_id = $this->insert_post( $section_heading[ $section_count ]['post_title'], '', $author_id, 'topics', $i, $new_course_id );
						$section_count++;
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
							}
						}
					}

					foreach ( $lesson_data['sfwd-quiz'] as $quiz_key => $quiz_data ) {
						$quiz_id = $this->update_post( $quiz_key, 'tutor_quiz', $i, $topic_id );
						if ( $quiz_id ) {
							$this->migrate_quiz( $quiz_id );
						}
					}
				}
				$i++;
			}

			if ( ! empty( $total_data['sfwd-quiz'] ) ) {
				foreach ( $total_data['sfwd-quiz'] as $quiz_key => $quiz_data ) {
					$quiz_id = $this->update_post( $quiz_key, 'tutor_quiz', $i, $topic_id );
					if ( $quiz_id ) {
						$this->migrate_quiz( $quiz_id );
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
	}
}
