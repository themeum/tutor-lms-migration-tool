<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'LIFtoTutorMigration' ) ) {
	class LIFtoTutorMigration {

		public function __construct() {
			add_filter( 'tutor_tool_pages', array( $this, 'tutor_tool_pages' ) );

			add_action( 'wp_ajax_lif_migrate_all_data_to_tutor', array( $this, 'lif_migrate_all_data_to_tutor' ) );
			add_action( 'wp_ajax_tlmt_reset_migrated_items_count', array( $this, 'tlmt_reset_migrated_items_count' ) );

			add_action( 'wp_ajax__get_lif_live_progress_course_migrating_info', array( $this, '_get_lif_live_progress_course_migrating_info' ) );

			add_action( 'tutor_action_migrate_lif_orders', array( $this, 'migrate_lif_orders' ) );
			add_action( 'tutor_action_migrate_lif_reviews', array( $this, 'migrate_lif_reviews' ) );

			add_action( 'wp_ajax_tutor_import_from_xml', array( $this, 'tutor_import_from_xml' ) );
			add_action( 'tutor_action_tutor_lp_export_xml', array( $this, 'tutor_lp_export_xml' ) );
		}

		public function tutor_tool_pages( $pages ) {
			$hasLPdata = get_option( 'lifter_version' );

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
			delete_option( '_tutor_migrated_items_count' );
		}

		public function lif_migrate_all_data_to_tutor() {

			if ( isset( $_POST['migrate_type'] ) ) {
				$migrate_type = sanitize_text_field( $_POST['migrate_type'] );

				switch ( $migrate_type ) {
					case 'courses':
						$this->lif_migrate_course_to_tutor();
						break;
					case 'orders':
						$this->migrate_lif_orders();
						break;
					case 'reviews':
						$this->migrate_lif_reviews();
						break;
				}
				wp_send_json_success();
			}
			wp_send_json_error();
		}

		public function lif_migrate_course_to_tutor() {
			global $wpdb;

			$lif_courses = $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'course';" );
			if ( tutils()->count( $lif_courses ) ) {
				$course_i = (int) get_option( '_tutor_migrated_items_count' );
				foreach ( $lif_courses as $lif_course ) {
					$course_i++;
					$this->migrate_course( $lif_course->ID );
					update_option( '_tutor_migrated_items_count', $course_i );
				}
			}
			wp_send_json_success();
		}

		/**
		 *
		 * Get Live Update about course migrating info
		 */

		public function _get_lif_live_progress_course_migrating_info() {
			$migrated_count = (int) get_option( '_tutor_migrated_items_count' );
			wp_send_json_success( array( 'migrated_count' => $migrated_count ) );
		}

		public function migrate_course( $course_id ) {
			global $wpdb;

			$course = learn_press_get_course( $course_id );

			if ( ! $course ) {
				return;
			}

			$curriculum = $course->get_curriculum();

			$lesson_post_type = tutor()->lesson_post_type;
			$course_post_type = tutor()->course_post_type;

			$tutor_course = array();
			$i            = 0;
			if ( $curriculum ) {
				foreach ( $curriculum as $section ) {
					$i++;

					$topic = array(
						'post_type'    => 'topics',
						'post_title'   => $section->get_title(),
						'post_content' => $section->get_description(),
						'post_status'  => 'publish',
						'post_author'  => $course->get_author( 'id' ),
						'post_parent'  => $course_id,
						'menu_order'   => $i,
						'items'        => array(),
					);

					$lessons = $section->get_items();
					foreach ( $lessons as $lesson ) {
						$item_post_type = learn_press_get_post_type( $lesson->get_id() );

						if ( $item_post_type !== 'lp_lesson' ) {
							if ( $item_post_type === 'lp_quiz' ) {
								$lesson_post_type = 'tutor_quiz';
							}
						}

						$tutor_lessons = array(
							'ID'          => $lesson->get_id(),
							'post_type'   => $lesson_post_type,
							'post_parent' => '{topic_id}',
						);

						$topic['items'][] = $tutor_lessons;
					}
					$tutor_course[] = $topic;
				}
			}

			if ( tutils()->count( $tutor_course ) ) {
				foreach ( $tutor_course as $course_topic ) {

					// Remove items from this topic
					$lessons = $course_topic['items'];
					unset( $course_topic['items'] );

					// Insert Topic post type
					$topic_id = wp_insert_post( $course_topic );

					// Update lesson from LearnPress to TutorLMS
					foreach ( $lessons as $lesson ) {

						if ( $lesson['post_type'] === 'tutor_quiz' ) {
							$quiz_id = tutils()->array_get( 'ID', $lesson );

							$questions = $wpdb->get_results(
								"SELECT question_id, question_order, questions.ID, questions.post_content, questions.post_title, question_type_meta.meta_value as question_type, question_mark_meta.meta_value as question_mark
						FROM {$wpdb->prefix}learnpress_quiz_questions
						LEFT JOIN {$wpdb->posts} questions on question_id = questions.ID
						LEFT JOIN {$wpdb->postmeta} question_type_meta on question_id = question_type_meta.post_id AND question_type_meta.meta_key = '_lp_type'
						LEFT JOIN {$wpdb->postmeta} question_mark_meta on question_id = question_mark_meta.post_id AND question_mark_meta.meta_key = '_lp_mark'
						WHERE quiz_id = {$quiz_id}  "
							);

							if ( tutils()->count( $questions ) ) {
								foreach ( $questions as $question ) {

									$question_type = null;
									if ( $question->question_type === 'true_or_false' ) {
										$question_type = 'true_false';
									}
									if ( $question->question_type === 'single_choice' ) {
										$question_type = 'single_choice';
									}
									if ( $question->question_type === 'multiple_choice' ) {
										$question_type = 'multi_choice';
									}

									if ( $question_type ) {

										$new_question_data = array(
											'quiz_id' => $quiz_id,
											'question_title' => $question->post_title,
											'question_description' => $question->post_content,
											'question_type' => $question_type,
											'question_mark' => $question->question_mark,
											'question_settings' => maybe_serialize( array() ),
											'question_order' => $question->question_order,
										);

										$wpdb->insert( $wpdb->prefix . 'tutor_quiz_questions', $new_question_data );
										$question_id = $wpdb->insert_id;

										$answer_items = $wpdb->get_results( "SELECT * from {$wpdb->prefix}learnpress_question_answers where question_id = {$question->question_id} " );

										if ( tutils()->count( $answer_items ) ) {
											foreach ( $answer_items as $answer_item ) {
												$answer_data = maybe_unserialize( $answer_item->answer_data );

												$answer_data = array(
													'belongs_question_id'   => $question_id,
													'belongs_question_type' => $question_type,
													'answer_title'          => tutils()->array_get( 'text', $answer_data ),
													'is_correct'            => tutils()->array_get( 'is_true', $answer_data ) == 'yes' ? 1 : 0,
													'answer_order'          => $answer_item->answer_order,
												);

												$wpdb->insert( $wpdb->prefix . 'tutor_quiz_question_answers', $answer_data );
											}
										}
									}
								}
							}
						}

						$lesson['post_parent'] = $topic_id;
						wp_update_post( $lesson );

						$lesson_id = tutils()->array_get( 'ID', $lesson );
						if ( $lesson_id ) {
							update_post_meta( $lesson_id, '_tutor_course_id_for_lesson', $course_id );
						}

						$_lp_preview = get_post_meta( $lesson_id, '_lp_preview', true );
						if ( $_lp_preview === 'yes' ) {
							update_post_meta( $lesson_id, '_is_preview', 1 );
						} else {
							delete_post_meta( $lesson_id, '_is_preview' );
						}
					}
				}
			}

			// Migrate Course
			$tutor_course = array(
				'ID'        => $course_id,
				'post_type' => $course_post_type,
			);
			wp_update_post( $tutor_course );
			update_post_meta( $course_id, '_was_lp_course', true );

			/**
			 * Create WC Product and attaching it with course
			 */
			update_post_meta( $course_id, '_tutor_course_price_type', 'free' );
			$tutor_monetize_by = tutils()->get_option( 'monetize_by' );

			if ( tutils()->has_wc() && $tutor_monetize_by == 'wc' || $tutor_monetize_by == '-1' || $tutor_monetize_by == 'free' ) {

				$_lp_price      = get_post_meta( $course_id, '_lp_price', true );
				$_lp_sale_price = get_post_meta( $course_id, '_lp_sale_price', true );

				if ( $_lp_price ) {

					update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );

					$product_id = wp_insert_post(
						array(
							'post_title'   => $course->get_title() . ' Product',
							'post_content' => '',
							'post_status'  => 'publish',
							'post_type'    => 'product',
						)
					);

					if ( $product_id ) {

						$product_metas = array(
							'_stock_status'      => 'instock',
							'total_sales'        => '0',
							'_regular_price'     => $_lp_price,
							'_sale_price'        => $_lp_sale_price,
							'_price'             => $_lp_price,
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
					$coursePostThumbnail = get_post_meta( $course_id, '_thumbnail_id', true );
					if ( $coursePostThumbnail ) {
						set_post_thumbnail( $product_id, $coursePostThumbnail );
					}
				} else {
					update_post_meta( $course_id, '_tutor_course_price_type', 'free' );
				}
			}

			/**
			 * Create EDD Product and linked with the course
			 */
			if ( tutils()->has_edd() && $tutor_monetize_by == 'edd' ) {
				$_lp_price      = get_post_meta( $course_id, '_lp_price', true );
				$_lp_sale_price = get_post_meta( $course_id, '_lp_sale_price', true );

				if ( $_lp_price ) {
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
						'edd_price'                        => $_lp_price,
						'edd_variable_prices'              => array(),
						'edd_download_files'               => array(),
						'_edd_bundled_products'            => array( '0' ),
						'_edd_bundled_products_conditions' => array( 'all' ),
					);
					foreach ( $product_metas as $key => $value ) {
						update_post_meta( $product_id, $key, $value );
					}
					update_post_meta( $course_id, '_tutor_course_product_id', $product_id );
					$coursePostThumbnail = get_post_meta( $course_id, '_thumbnail_id', true );
					if ( $coursePostThumbnail ) {
						set_post_thumbnail( $product_id, $coursePostThumbnail );
					}
				} else {
					update_post_meta( $course_id, '_tutor_course_price_type', 'free' );
				}
			}

			/**
			 * Course Complete Status Migration
			 */

			$lp_course_complete_datas = $wpdb->get_results(
				"SELECT lp_user_items.*,
				lif_order.ID as order_id,
				lif_order.post_date as order_time

				FROM {$wpdb->prefix}learnpress_user_items lp_user_items
				LEFT JOIN {$wpdb->posts} lif_order ON lp_user_items.ref_id = lif_order.ID
				WHERE item_id = {$course_id} AND item_type = 'lp_course' AND graduation ='passed'"
			);

			foreach ( $lp_course_complete_datas as $lp_course_complete_data ) {
				$user_id = $lp_course_complete_data->user_id;

				if ( ! tutils()->is_enrolled( $course_id, $user_id ) ) {

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

					$tutor_course_complete_data = array(
						'comment_type'     => 'course_completed',
						'comment_agent'    => 'TutorLMSPlugin',
						'comment_approved' => 'approved',
						'comment_content'  => $hash,
						'user_id'          => $user_id,
						'comment_author'   => $user_id,
						'comment_post_ID'  => $course_id,
					);

					$isEnrolled = wp_insert_comment( $tutor_course_complete_data );

				}
			}

			/**
			 * Enrollment Migration to this course
			 */
			$lp_enrollments = $wpdb->get_results(
				"SELECT lp_user_items.*,
				lif_order.ID as order_id,
				lif_order.post_date as order_time

				FROM {$wpdb->prefix}learnpress_user_items lp_user_items
				LEFT JOIN {$wpdb->posts} lif_order ON lp_user_items.ref_id = lif_order.ID
				WHERE item_id = {$course_id} AND ref_type = 'lif_order'"
			);

			foreach ( $lp_enrollments as $lp_enrollment ) {
				$user_id = $lp_enrollment->user_id;

				if ( ! tutils()->is_enrolled( $course_id, $user_id ) ) {
					$order_time = strtotime( $lp_enrollment->order_time );

					$title                 = __( 'Course Enrolled', 'tutor' ) . ' &ndash; ' . date( get_option( 'date_format' ), $order_time ) . ' @ ' . date( get_option( 'time_format' ), $order_time );
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

		/*
		* Lifter LMS  order migrate to WC
		*/
		public function migrate_lif_orders() {
			global $wpdb;

			$lif_orders = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_type = 'llms_order' AND post_status = array( 'llms-active', 'llms-completed' ) ;" );

			$item_i = (int) get_option( '_tutor_migrated_items_count' );
			foreach ( $lif_orders as $lif_order ) {
				$item_i++;
				update_option( '_tutor_migrated_items_count', $item_i );

				$order_id           = $lif_order->ID;
				$migrate_order_data = array(
					'ID'          => $order_id,
					'post_status' => 'wc-completed',
					'post_type'   => 'shop_order',
				);

				wp_update_post( $migrate_order_data );

				$_items = $this->get_lif_order_items( $order_id );

				foreach ( $_items as $item ) {

					$item_data = array(
						'order_item_name' => $item->name,
						'order_item_type' => 'line_item',
						'order_id'        => $order_id,
					);

					$wpdb->insert( $wpdb->prefix . 'woocommerce_order_items', $item_data );
					$order_item_id = (int) $wpdb->insert_id;

					$lp_item_metas = $wpdb->get_results( "SELECT meta_key, meta_value FROM {$wpdb->prefix}learnpress_order_itemmeta WHERE learnpress_order_item_id = {$item->id} " );

					$lp_formatted_metas = array();
					foreach ( $lp_item_metas as $item_meta ) {
						$lp_formatted_metas[ $item_meta->meta_key ] = $item_meta->meta_value;
					}

					$_course_id = tutils()->array_get( '_course_id', $lp_formatted_metas );
					$_quantity  = tutils()->array_get( '_quantity', $lp_formatted_metas );
					$_subtotal  = tutils()->array_get( '_subtotal', $lp_formatted_metas );
					$_total     = tutils()->array_get( '_total', $lp_formatted_metas );

					$wc_item_metas = array(
						'_product_id'        => $_course_id,
						'_variation_id'      => 0,
						'_qty'               => $_quantity,
						'_tax_class'         => '',
						'_line_subtotal'     => $_subtotal,
						'_line_subtotal_tax' => 0,
						'_line_total'        => $_total,
						'_line_tax'          => 0,
						'_line_tax_data'     => maybe_serialize(
							array(
								'total'    => array(),
								'subtotal' => array(),
							)
						),
					);

					foreach ( $wc_item_metas as $wc_item_meta_key => $wc_item_meta_value ) {
						$wc_item_metas = array(
							'order_item_id' => $order_item_id,
							'meta_key'      => $wc_item_meta_key,
							'meta_value'    => $wc_item_meta_value,
						);
						$wpdb->insert( $wpdb->prefix . 'woocommerce_order_itemmeta', $wc_item_metas );
					}
				}

				update_post_meta( $order_id, '_customer_user', get_post_meta( $order_id, '_user_id', true ) );
				update_post_meta( $order_id, '_customer_ip_address', get_post_meta( $order_id, '_user_ip_address', true ) );
				update_post_meta( $order_id, '_customer_user_agent', get_post_meta( $order_id, '_user_agent', true ) );

				$user_email = $wpdb->get_var( "SELECT user_email from {$wpdb->users} WHERE ID = {$lif_order->post_author} " );
				update_post_meta( $order_id, '_billing_address_index', $user_email );
				update_post_meta( $order_id, '_billing_email', $user_email );
			}

		}

		/*
		* learnpress Review migrate to Tutor
		*/
		public function migrate_lif_reviews() {
			global $wpdb;

			$lif_review_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='llms_review';" );

			if ( tutils()->count( $lif_review_ids ) ) {
				$item_i = (int) get_option( '_tutor_migrated_items_count' );
				foreach ( $lif_review_ids as $lif_review_id ) {
					$item_i++;
					update_option( '_tutor_migrated_items_count', $item_i );

					$review_migrate_data = array(
						'comment_approved' => 'approved',
						'comment_type'     => 'tutor_course_rating',
						'comment_agent'    => 'TutorLMSPlugin',
					);

					$wpdb->update( $wpdb->comments, $review_migrate_data, array( 'comment_ID' => $lif_review_id ) );
					$wpdb->update(
						$wpdb->commentmeta,
						array( 'meta_key' => 'tutor_rating' ),
						array(
							'comment_id' => $lif_review_id,
							'meta_key'   => '_lif_rating',
						)
					);
				}
			}

		}


		public function get_lif_order_items( $order_id ) {
			global $wpdb;

			$query = $wpdb->prepare(
				"
				SELECT orders.id as order_id, 
				(SELECT meta_value as course_id FROM $wpdb->postmeta WHERE post_id=orders.id AND meta_key='_llms_product_id') as course_id,
				(SELECT meta_value as course_id FROM $wpdb->postmeta WHERE post_id=orders.id AND meta_key='_llms_product_title') as course_title
				FROM $wpdb->posts as orders
				WHERE orders.post_type='llms_order' AND id=%d ",
				$order_id
			);

			return $wpdb->get_results( $query );
		}


		/**
		 *
		 * Import From XML
		 */
		public function tutor_import_from_xml() {
			global $wpdb;
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
						'message' => 'LP Migration not successfull',
					)
				);
			} else {
				$wpdb->query( 'COMMIT' );
				wp_send_json(
					array(
						'success' => true,
						'message' => 'LP Migration successfull',
					)
				);
			}
		}


		public function tutor_lp_export_xml() {
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename=learnpress_data_for_tutor.xml' );
			header( 'Expires: 0' );

			echo $this->generate_xml_data();
			exit;
		}


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

				$lp_courses = $wpdb->get_results( "SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_status  FROM {$wpdb->posts} WHERE post_type = 'lp_course' AND post_status = 'publish';" );

				if ( tutils()->count( $lp_courses ) ) {
					$course_i = 0;
					foreach ( $lp_courses as $lp_course ) {
						$course_i++;

						$course_id = $lp_course->ID;

						$xml .= $this->start_element( 'courses' );

						$course_arr = (array) $lp_course;
						foreach ( $course_arr as $course_col => $course_col_value ) {
							$xml .= "<{$course_col}>{$course_col_value}</{$course_col}>\n";
						}

						$course_metas = $wpdb->get_results( "SELECT meta_key, meta_value from {$wpdb->postmeta} where post_id = {$course_id}" );

						$xml .= $this->start_element( 'course_meta' );
						foreach ( $course_metas as $course_meta ) {
							$xml .= "<{$course_meta->meta_key}>{$course_meta->meta_value}</{$course_meta->meta_key}>\n";
						}
						$xml .= $this->close_element( 'course_meta' );

						$course = learn_press_get_course( $course_id );

						$lesson_post_type = tutor()->lesson_post_type;
						$course_post_type = tutor()->course_post_type;

						if ( $course ) {
							$curriculum = $course->get_curriculum();

							$i = 0;

							if ( $curriculum ) {
								foreach ( $curriculum as $section ) {
									$i ++;

									$xml .= $this->start_element( 'topics' );

									/**
									 * Topic
									 */
									$xml .= "<post_type>topics</post_type>\n";
									$xml .= "<post_title>{$section->get_title()}</post_title>\n";

									$topic_content = ! empty( $section->get_description() ) ? $this->xml_cdata( $section->get_description() ) : '';

									$xml .= "<post_content>{$topic_content}</post_content>\n";
									$xml .= "<post_status>publish</post_status>\n";
									$xml .= "<post_author>{$course->get_author( 'id' )}</post_author>\n";
									$xml .= "<post_parent>{$course_id}</post_parent>";
									$xml .= "<menu_order>{$i}</menu_order>\n";

									/**
									 * Lessons
									 */
									$lessons = $this->get_lp_section_items( $section->get_id() );

									foreach ( $lessons as $lesson ) {
										$item_post_type = $lesson->item_type;

										if ( $item_post_type !== 'lp_lesson' ) {
											if ( $item_post_type === 'lp_quiz' ) {
												$lesson_post_type = 'tutor_quiz';
											}
										}

										// Item
										$xml .= $this->start_element( 'items' );

										$xml .= "<item_id>{$lesson->id}</item_id>\n";
										$xml .= "<post_type>{$lesson_post_type}</post_type>\n";
										$xml .= "<post_author>{$lesson->post_author}</post_author>\n";
										$xml .= "<post_date>{$lesson->post_date}</post_date>\n";
										$xml .= "<post_title>{$lesson->post_title}</post_title>\n";
										$xml .= "<post_content>{$this->xml_cdata($lesson->post_content)}</post_content>\n";
										$xml .= "<post_parent>{topic_id}</post_parent>\n";

										$xml .= $this->start_element( 'item_meta' );

										$item_metas = $wpdb->get_results( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = {$lesson->id} " );

										if ( is_array( $item_metas ) && count( $item_metas ) ) {
											foreach ( $item_metas as $item_meta ) {
												$xml .= "<{$item_meta->meta_key}> {$this->xml_cdata($item_meta->meta_key)} </{$item_meta->meta_key}>\n";
											}
										}

										$xml .= $this->close_element( 'item_meta' );

										if ( $lesson_post_type === 'tutor_quiz' ) {
											$quiz_id = $lesson->id;

											$questions = $wpdb->get_results(
												"SELECT question_id, question_order, questions.ID, questions.post_content, questions.post_title, question_type_meta.meta_value as question_type, question_mark_meta.meta_value as question_mark
										FROM {$wpdb->prefix}learnpress_quiz_questions
										LEFT JOIN {$wpdb->posts} questions on question_id = questions.ID
										LEFT JOIN {$wpdb->postmeta} question_type_meta on question_id = question_type_meta.post_id AND question_type_meta.meta_key = '_lp_type'
										LEFT JOIN {$wpdb->postmeta} question_mark_meta on question_id = question_mark_meta.post_id AND question_mark_meta.meta_key = '_lp_mark'
										WHERE quiz_id = {$quiz_id}  "
											);

											if ( tutils()->count( $questions ) ) {

												foreach ( $questions as $question ) {

													$question_type = null;
													if ( $question->question_type === 'true_or_false' ) {
														$question_type = 'true_false';
													}
													if ( $question->question_type === 'single_choice' ) {
														$question_type = 'single_choice';
													}
													if ( $question->question_type === 'multi_choice' ) {
														$question_type = 'multiple_choice';
													}

													if ( $question_type ) {
														$xml              .= $this->start_element( 'questions' );
														$new_question_data = array(
															'quiz_id'              => '{quiz_id}',
															'question_title'       => $question->post_title,
															'question_description' => $question->post_content,
															'question_type'        => $question_type,
															'question_mark'        => $question->question_mark,
															'question_settings'    => maybe_serialize( array() ),
															'question_order'       => $question->question_order,
														);

														foreach ( $new_question_data as $question_key => $question_value ) {
															$xml .= "<{$question_key}>{$this->xml_cdata($question_value)}</{$question_key}>\n";
														}

														$answer_items = $wpdb->get_results( "SELECT * from {$wpdb->prefix}learnpress_question_answers where question_id = {$question->question_id} " );

														if ( tutils()->count( $answer_items ) ) {
															foreach ( $answer_items as $answer_item ) {
																$answer_data = maybe_unserialize( $answer_item->answer_data );

																$answer_data = array(
																	'belongs_question_id'   => '{question_id}',
																	'belongs_question_type' => $question_type,
																	'answer_title'          => tutils()->array_get( 'text', $answer_data ),
																	'is_correct'            => tutils()->array_get( 'is_true', $answer_data ) == 'yes' ? 1 : 0,
																	'answer_order'          => $answer_item->answer_order,
																);

																$xml .= $this->start_element( 'answers' );

																foreach ( $answer_data as $answers_key => $answers_value ) {
																	$xml .= "<{$answers_key}>{$this->xml_cdata($answers_value)}</{$answers_key}>\n";
																}
																$xml .= $this->close_element( 'answers' );
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

						$lp_reviews = $wpdb->get_results(
							"SELECT comments.comment_post_ID,
                    comments.comment_post_ID,
                    comments.comment_author,
                    comments.comment_author_email,
                    comments.comment_author_IP,
                    comments.comment_date,
                    comments.comment_date_gmt,
                    comments.comment_content,
                    comments.user_id,
                    cm.meta_value as tutor_rating
                     FROM {$wpdb->comments} comments INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = comments.comment_ID AND cm.meta_key = '_lpr_rating' WHERE comments.comment_type = 'review';",
							ARRAY_A
						);

						if ( tutils()->count( $lp_reviews ) ) {
							foreach ( $lp_reviews as $lp_review ) {
								$lp_review['comment_approved'] = 'approved';
								$lp_review['comment_agent']    = 'TutorLMSPlugin';
								$lp_review['comment_type']     = 'tutor_course_rating';

								$xml .= $this->start_element( 'reviews' );
								foreach ( $lp_review as $lp_review_key => $lp_review_value ) {
									$xml .= "<{$lp_review_key}>{$this->xml_cdata($lp_review_value)}</{$lp_review_key}>\n";
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

		public function start_element( $element = '' ) {
			return "\n<{$element}>\n";
		}
		public function close_element( $element = '' ) {
			return "\n</{$element}>\n";
		}

		function xml_cdata( $str ) {
			if ( ! seems_utf8( $str ) ) {
				$str = utf8_encode( $str );
			}
			$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';

			return $str;
		}

		/**
		 * @param $section_id
		 *
		 * @return array|null|object
		 *
		 * Get items (lesson|quiz) by section ID
		 */

		public function get_lp_section_items( $section_id ) {
			global $wpdb;

			$query = $wpdb->prepare(
				"
			SELECT item_id id, item_type, it.post_author, it.post_date, it.post_content, it.post_title, it.post_excerpt

			FROM {$wpdb->learnpress_section_items} si

			INNER JOIN {$wpdb->learnpress_sections} s ON si.section_id = s.section_id
			INNER JOIN {$wpdb->posts} c ON c.ID = s.section_course_id
			INNER JOIN {$wpdb->posts} it ON it.ID = si.item_id

			WHERE s.section_id = %d
			AND it.post_status = %s
			ORDER BY si.item_order, si.section_item_id ASC
		",
				$section_id, /*'publish',*/
				'publish'
			);

			return $wpdb->get_results( $query );
		}
	}
}
