<?php
if ( ! defined( 'ABSPATH' ) )
	exit;

if ( ! class_exists('LPtoTutorMigration')){
	class LPtoTutorMigration {

		/**
		 * Courses processed per AJAX request.
		 * Keep small — MAMP FastCGI idle timeout is ~30s.
		 */
		const COURSE_BATCH_SIZE = 3;

		/**
		 * Student–course enrollment pairs processed per AJAX request.
		 */
		const ENROLLMENT_BATCH_SIZE = 50;

		/**
		 * Orders processed per AJAX request.
		 */
		const ORDER_BATCH_SIZE = 20;

		/**
		 * Reviews processed per AJAX request.
		 */
		const REVIEW_BATCH_SIZE = 50;

		/**
		 * Option key for total LP courses at migration start.
		 */
		const COURSE_MIGRATION_TOTAL_OPT = '_tlmt_lp_course_migration_total';

		/**
		 * Option key for total LP orders at migration start.
		 */
		const ORDER_MIGRATION_TOTAL_OPT = '_tlmt_lp_order_migration_total';

		/**
		 * Option key for total LP reviews at migration start.
		 */
		const REVIEW_MIGRATION_TOTAL_OPT = '_tlmt_lp_review_migration_total';

		public function __construct() {
			add_filter('tutor_tool_pages', array($this, 'tutor_tool_pages'));
			add_action('wp_ajax_insert_tutor_migration_data', array($this, 'insert_tutor_migration_data'));
			add_action('wp_ajax_lp_migrate_all_data_to_tutor', array($this, 'lp_migrate_all_data_to_tutor'));
			add_action('wp_ajax_tlmt_reset_migrated_items_count', array($this, 'tlmt_reset_migrated_items_count'));

			add_action('wp_ajax__get_lp_live_progress_course_migrating_info', array($this, '_get_lp_live_progress_course_migrating_info'));

			add_action('tutor_action_migrate_lp_orders', array($this, 'migrate_lp_orders'));
			add_action('tutor_action_migrate_lp_reviews', array($this, 'migrate_lp_reviews'));

			add_action('wp_ajax_tutor_import_from_xml', array($this, 'tutor_import_from_xml'));
			add_action('tutor_action_tutor_lp_export_xml', array($this, 'tutor_lp_export_xml'));
		}

		public function insert_tutor_migration_data(){
			global $wpdb;

			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			$migration_type   = isset( $_POST['migration_type'] ) ? sanitize_text_field( wp_unslash( $_POST['migration_type'] ) ) : '';
			$migration_vendor = isset( $_POST['migration_vendor'] ) ? sanitize_text_field( wp_unslash( $_POST['migration_vendor'] ) ) : '';

			$tutor_migration_table_data = [
				'migration_type'   => $migration_type,
				'migration_vendor' => $migration_vendor,
				'created_by'       => get_current_user_id(),
				'created_at'       => current_time( 'mysql' ),
			];

			$wpdb->insert(
				$wpdb->prefix . 'tutor_migration',
				$tutor_migration_table_data
			);

			// XML import path: convert instructors when LP history is recorded.
			if ( 'lp' === $migration_vendor && 'Imported' === $migration_type ) {
				Utils::convert_all_lp_teachers_to_tutor_instructors();
			}
		}
		public function tutor_tool_pages($pages){
			$hasLPdata = get_option('learnpress_version');

			if (defined('LEARNPRESS_VERSION') ) {
				$pages['migration_lp'] = array(
					'label'    => __( 'LearnPress Migration', 'tutor' ),
					'slug'     => 'migration_lp',
					'desc'     => __( 'LearnPress Migration', 'tutor' ),
					'template' => 'migration_lp',
					'view_path'     => TLMT_PATH . 'views/',
					'icon'     => 'tutor-icon-brand-learnpress',
					'blocks'   => array(
						'block' => array(),
					),
				);
			}

			return $pages;
		}

		/**
		 * Delete Item Count
		 */
		public function tlmt_reset_migrated_items_count(){
			tutor_utils()->checking_nonce();
			
			Utils::check_course_access();
			delete_option('_tutor_migrated_items_count');
		}

		public function lp_migrate_all_data_to_tutor(){
			tutor_utils()->checking_nonce();
			
			Utils::check_course_access();

			if ( ! isset( $_POST['migrate_type'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid migration type.', 'tutor-lms-migration-tool' ) ) );
			}

			$migrate_type = sanitize_text_field( wp_unslash( $_POST['migrate_type'] ) );

			try {
				switch ( $migrate_type ) {
					case 'courses':
						$result = $this->lp_migrate_course_to_tutor();
						wp_send_json_success( $result );
						break;
					case 'enrollments':
						$result = $this->lp_enrollments_migrate();
						if ( false === $result ) {
							wp_send_json_error(
								array(
									'step'    => 'enrollments',
									'message' => \Themeum\TutorLMSMigrationTool\ErrorHandler::get_error_message( \Themeum\TutorLMSMigrationTool\ContentTypes::ENROLLMENTS ),
								)
							);
						}
						wp_send_json_success( is_array( $result ) ? $result : array() );
						break;
					case 'orders':
						$result = $this->migrate_lp_orders();
						wp_send_json_success( is_array( $result ) ? $result : array() );
						break;
					case 'reviews':
						$result = $this->migrate_lp_reviews();
						wp_send_json_success( is_array( $result ) ? $result : array() );
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

		public function lp_migrate_course_to_tutor(){
			global $wpdb;

			$this->raise_migration_resource_limits();

			$batch_size = (int) apply_filters( 'tlmt_lp_course_migration_batch_size', self::COURSE_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::COURSE_BATCH_SIZE;
			}

			$remaining_total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_course'" );

			$is_first_batch = ! empty( $_POST['lp_course_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in lp_migrate_all_data_to_tutor().

			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				update_option( self::COURSE_MIGRATION_TOTAL_OPT, $remaining_total, false );
				// Convert LP teachers immediately so they appear as Tutor instructors without waiting for login.
				Utils::convert_all_lp_teachers_to_tutor_instructors();
			}

			$total_courses = (int) get_option( self::COURSE_MIGRATION_TOTAL_OPT, $remaining_total );
			if ( $total_courses < 1 ) {
				$total_courses = $remaining_total;
			}

			if ( $remaining_total < 1 ) {
				$already = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_was_lp_course'" );
				return array(
					'migrated'           => max( $total_courses, $already ),
					'total'              => max( $total_courses, $already ),
					'total_course_count' => max( $total_courses, $already ),
					'remaining'          => 0,
					'has_more'           => false,
					'batch_size'         => $batch_size,
				);
			}

			$lp_courses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lp_course' ORDER BY ID ASC LIMIT %d",
					$batch_size
				)
			);

			$course_i = (int) get_option( '_tutor_migrated_items_count' );
			foreach ( $lp_courses as $lp_course ) {
				$course_i++;
				$this->migrate_course( $lp_course->ID );
				update_option( '_tutor_migrated_items_count', $course_i );
			}

			$remaining_after = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_course'" );
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
		 * @return void
		 */
		private function raise_migration_resource_limits() {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			$current = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
			$target  = 256 * MB_IN_BYTES;

			if ( -1 !== $current && $current < $target ) {
				@ini_set( 'memory_limit', '256M' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.memory_limit_Disallowed
			}
		}

		/**
		 *
		 * Get Live Update about course migrating info
		 */

		public function _get_lp_live_progress_course_migrating_info(){
			$migrated_count = (int) get_option('_tutor_migrated_items_count');
			wp_send_json_success(array('migrated_count' => $migrated_count ));
		}

		public function migrate_course($course_id){
			global $wpdb;

			$course = learn_press_get_course($course_id);

			if ( ! $course){
				return;
			}

			$curriculum = $course->get_curriculum() ;
			$lesson_post_type = tutor()->lesson_post_type;
			$course_post_type = tutor()->course_post_type;

			$tutor_course = array();
			$i = 0;
			if($curriculum){
				foreach ( $curriculum as $section ) {
					$i++;
					
					$topic = array(
						'post_type'     => 'topics',
						'post_title'    => $section->get_title(),
						'post_content'  => $section->get_description(),
						'post_status'   => 'publish',
						'post_author'   => $course->get_author('id'),
						'post_parent'   => $course_id,
						'menu_order'    => $i,
						'items'         => array()
					);

					$lessons = $section->get_items();
					foreach ( $lessons as $lesson ) {
						$item_post_type     = learn_press_get_post_type( $lesson->get_id() );
						$item_tutor_type    = $lesson_post_type;

						if ( 'lp_quiz' === $item_post_type ) {
							$item_tutor_type = 'tutor_quiz';
						} elseif ( 'lp_lesson' === $item_post_type ) {
							$item_tutor_type = tutor()->lesson_post_type;
						}

						$tutor_lessons = array(
							'ID'          => $lesson->get_id(),
							'post_type'   => $item_tutor_type,
							'post_parent' => '{topic_id}',
						);

						$topic['items'][] = $tutor_lessons;
					}
					$tutor_course[] = $topic;
				}
			}


			if (tutils()->count($tutor_course)){
				foreach ($tutor_course as $course_topic){

					//Remove items from this topic
					$lessons = $course_topic['items'];
					unset($course_topic['items']);

					//Insert Topic post type
					$topic_id = wp_insert_post( $course_topic );

					//Update lesson from LearnPress to TutorLMS
					foreach ($lessons as $lesson){

						if ($lesson['post_type'] === 'tutor_quiz'){
							$quiz_id = tutils()->array_get('ID', $lesson);

							$questions = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT question_id, question_order, questions.ID, questions.post_content, questions.post_title, question_type_meta.meta_value as question_type, question_mark_meta.meta_value as question_mark
									FROM {$wpdb->prefix}learnpress_quiz_questions
									LEFT JOIN {$wpdb->posts} questions on question_id = questions.ID
									LEFT JOIN {$wpdb->postmeta} question_type_meta on question_id = question_type_meta.post_id AND question_type_meta.meta_key = '_lp_type'
									LEFT JOIN {$wpdb->postmeta} question_mark_meta on question_id = question_mark_meta.post_id AND question_mark_meta.meta_key = '_lp_mark'
									WHERE quiz_id = %d ",
									$quiz_id 
								)
							);

							if (tutils()->count($questions)){
								foreach ($questions as $question) {

									$question_type = $this->map_lp_question_type_to_tutor( $question->question_type );

									if ($question_type) {

										$new_question_data = array(
											'quiz_id'              => $quiz_id,
											'question_title'       => $question->post_title,
											'question_description' => $question->post_content,
											'question_type'        => $question_type,
											'question_mark'        => $question->question_mark,
											'question_settings'    => maybe_serialize( $this->build_tutor_question_settings( $question_type, $question->question_mark ) ),
											'question_order'       => $question->question_order,
										);

										$wpdb->insert($wpdb->prefix.'tutor_quiz_questions', $new_question_data);
										$question_id = $wpdb->insert_id;

										$answer_items = $wpdb->get_results( $wpdb->prepare( "SELECT * from {$wpdb->prefix}learnpress_question_answers where question_id = %d ", $question->question_id) );

										if ( tutils()->count( $answer_items ) ) {
											foreach ( $answer_items as $answer_item ) {
												$answer_data = $this->build_tutor_answer_from_lp( $answer_item, $question_id, $question_type );
												$wpdb->insert( $wpdb->prefix . 'tutor_quiz_question_answers', $answer_data );
											}
										}
									}

								}

							}

						}

						$lesson['post_parent'] = $topic_id;
						wp_update_post($lesson);

						$lesson_id = tutils()->array_get('ID', $lesson);

						if ( $lesson['post_type'] === 'tutor_quiz' && $lesson_id ) {
							update_post_meta( $lesson_id, 'tutor_quiz_option', $this->build_tutor_quiz_option_from_lp( $lesson_id ) );
						}

						if ($lesson_id){
							update_post_meta( $lesson_id, '_tutor_course_id_for_lesson', $course_id );
						}

						$_lp_preview = get_post_meta($lesson_id, '_lp_preview', true);
						if ($_lp_preview === 'yes'){
							update_post_meta($lesson_id, '_is_preview', 1);
						}else{
							delete_post_meta($lesson_id, '_is_preview');
						}
					}
				}
			}

			// Migrate categories & tags before the CPT change (LP taxonomies are only registered for lp_course).
			$lp_tax_migrator = new \Themeum\TutorLMSMigrationTool\LPMigration\CourseTaxonomies();
			$lp_tax_migrator->migrate( $course_id );

			//Migrate Course
			$tutor_course = array(
				'ID'            => $course_id,
				'post_type'     => $course_post_type,
			);
			wp_update_post($tutor_course);
			update_post_meta($course_id, '_was_lp_course', true);

			// Migrate additional course meta/settings from LearnPress to Tutor.
			$this->migrate_lp_course_meta_to_tutor( $course_id );

			/**
			 * Course pricing:
			 * 1) Always write Tutor native price meta from LP (needed when monetize_by=tutor).
			 * 2) Optionally create WC / EDD products when those monetization modes are active.
			 */
			$this->migrate_lp_course_pricing_to_tutor( $course_id );

			$tutor_monetize_by = tutils()->get_option( 'monetize_by' );
			$lp_prices        = $this->get_lp_course_prices( $course_id );

			if ( tutils()->has_wc() && ( 'wc' === $tutor_monetize_by || '-1' === $tutor_monetize_by || 'free' === $tutor_monetize_by ) ) {
				if ( $lp_prices['regular'] > 0 ) {
					$product_id = wp_insert_post(
						array(
							'post_title'   => $course->get_title() . ' Product',
							'post_content' => '',
							'post_status'  => 'publish',
							'post_type'    => 'product',
						)
					);

					if ( $product_id ) {
						$wc_price      = $lp_prices['sale'] > 0 ? $lp_prices['sale'] : $lp_prices['regular'];
						$product_metas = array(
							'_stock_status'      => 'instock',
							'total_sales'        => '0',
							'_regular_price'     => $lp_prices['regular'],
							'_sale_price'        => $lp_prices['sale'] > 0 ? $lp_prices['sale'] : '',
							'_price'             => $wc_price,
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

					update_post_meta( $course_id, '_tutor_course_product_id', $product_id );
					$course_post_thumbnail = get_post_meta( $course_id, '_thumbnail_id', true );
					if ( $course_post_thumbnail ) {
						set_post_thumbnail( $product_id, $course_post_thumbnail );
					}
				}
			}

			if ( tutils()->has_edd() && 'edd' === $tutor_monetize_by ) {
				if ( $lp_prices['regular'] > 0 ) {
					$product_id = wp_insert_post(
						array(
							'post_title'   => $course->get_title() . ' Product',
							'post_content' => '',
							'post_status'  => 'publish',
							'post_type'    => 'download',
						)
					);
					$edd_price  = $lp_prices['sale'] > 0 ? $lp_prices['sale'] : $lp_prices['regular'];
					$product_metas = array(
						'edd_price'                       => $edd_price,
						'edd_variable_prices'             => array(),
						'edd_download_files'              => array(),
						'_edd_bundled_products'           => array( '0' ),
						'_edd_bundled_products_conditions' => array( 'all' ),
					);
					foreach ( $product_metas as $key => $value ) {
						update_post_meta( $product_id, $key, $value );
					}
					update_post_meta( $course_id, '_tutor_course_product_id', $product_id );
					$course_post_thumbnail = get_post_meta( $course_id, '_thumbnail_id', true );
					if ( $course_post_thumbnail ) {
						set_post_thumbnail( $product_id, $course_post_thumbnail );
					}
				}
			}

			// Enrollments, completions, and lesson progress run in a separate
			// batched step (lp_enrollments_migrate) after all courses finish.
		}

		/**
		 * Migrate LearnPress enrollments, completions, and lesson progress in batches.
		 *
		 * Runs after course structure migration. Processes student–course pairs
		 * (not whole courses) so large enrollments stay under server timeouts.
		 *
		 * @since 2.5.0
		 *
		 * @return array|false Batch payload, or false on blocking error.
		 */
		public function lp_enrollments_migrate() {
			$this->raise_migration_resource_limits();

			$batch_size = (int) apply_filters( 'tlmt_lp_enrollment_migration_batch_size', self::ENROLLMENT_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::ENROLLMENT_BATCH_SIZE;
			}

			$is_first_batch = ! empty( $_POST['lp_enrollment_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in lp_migrate_all_data_to_tutor().

			return ( new \Themeum\TutorLMSMigrationTool\LPMigration\Enrollments() )->migrate_batch( $batch_size, (bool) $is_first_batch );
		}

		/**
		 * LearnPress ecommerce order migration.
		 *
		 * Routes to Tutor native ecommerce when monetize_by is `tutor`,
		 * otherwise keeps the legacy WooCommerce shop_order conversion.
		 *
		 * @return array
		 */
		public function migrate_lp_orders() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();
			$this->raise_migration_resource_limits();

			if ( tutor_utils()->is_monetize_by_tutor() ) {
				return $this->migrate_lp_orders_to_native();
			}

			return $this->migrate_lp_orders_to_wc();
		}

		/**
		 * Migrate LP orders into Tutor native ecommerce tables.
		 *
		 * @since 2.5.0
		 *
		 * @return array
		 */
		private function migrate_lp_orders_to_native() {
			global $wpdb;

			$total_course_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_was_lp_course'" );

			$migrator = new \Themeum\TutorLMSMigrationTool\LPMigration\Orders\OrderMigrator();

			$batch_size = (int) apply_filters( 'tlmt_lp_order_migration_batch_size', self::ORDER_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::ORDER_BATCH_SIZE;
			}

			$remaining_total = $migrator->get_remaining_count();

			$is_first_batch = ! empty( $_POST['lp_order_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream.
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				update_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total, false );
			}

			$total_orders = (int) get_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total );
			if ( $total_orders < 1 ) {
				$total_orders = $remaining_total;
			}

			if ( $remaining_total < 1 ) {
				return array(
					'migrated'           => $total_orders,
					'total'              => $total_orders,
					'total_course_count' => $total_course_count,
					'remaining'          => 0,
					'has_more'           => false,
					'batch_size'         => $batch_size,
					'target'             => 'native',
				);
			}

			$lp_orders = $migrator->get_batch( $batch_size );
			$item_i    = (int) get_option( '_tutor_migrated_items_count' );

			foreach ( $lp_orders as $lp_order ) {
				$item_i++;
				update_option( '_tutor_migrated_items_count', $item_i );

				try {
					$migrator->migrate_order( $lp_order );
				} catch ( \Throwable $th ) {
					\Themeum\TutorLMSMigrationTool\ErrorHandler::set_error(
						\Themeum\TutorLMSMigrationTool\ContentTypes::ORDERS,
						sprintf(
							/* translators: 1: LP order ID, 2: error message */
							__( 'LP order #%1$d native migration failed: %2$s', 'tutor-lms-migration-tool' ),
							(int) $lp_order->ID,
							$th->getMessage()
						)
					);
					// Mark processed so the batch cannot loop forever on a poison order.
					update_post_meta(
						(int) $lp_order->ID,
						\Themeum\TutorLMSMigrationTool\LPMigration\Orders\OrderMigrator::META_MIGRATED_ORDER_ID,
						0
					);
					update_post_meta(
						(int) $lp_order->ID,
						\Themeum\TutorLMSMigrationTool\LPMigration\Orders\OrderMigrator::META_SKIP_REASON,
						'exception:' . $th->getMessage()
					);
				}
			}

			$remaining_after = $migrator->get_remaining_count();
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
				'target'             => 'native',
			);
		}

		/**
		 * Legacy: convert completed LP orders into WooCommerce shop orders.
		 *
		 * @return array
		 */
		private function migrate_lp_orders_to_wc() {
			global $wpdb;

			$total_course_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_was_lp_course'" );

			// LP orders are converted to WooCommerce shop orders.
			if ( ! tutils()->has_wc() ) {
				return array(
					'migrated'           => 0,
					'total'              => 0,
					'total_course_count' => $total_course_count,
					'remaining'          => 0,
					'has_more'           => false,
					'skipped'            => true,
					'target'             => 'wc',
					'message'            => __( 'WooCommerce is not active; LearnPress orders were skipped. Switch Tutor monetization to Native or activate WooCommerce.', 'tutor-lms-migration-tool' ),
				);
			}

			$batch_size = (int) apply_filters( 'tlmt_lp_order_migration_batch_size', self::ORDER_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::ORDER_BATCH_SIZE;
			}

			$remaining_total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_order' AND post_status = 'lp-completed'" );

			$is_first_batch = ! empty( $_POST['lp_order_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream.
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				update_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total, false );
			}

			$total_orders = (int) get_option( self::ORDER_MIGRATION_TOTAL_OPT, $remaining_total );
			if ( $total_orders < 1 ) {
				$total_orders = $remaining_total;
			}

			if ( $remaining_total < 1 ) {
				return array(
					'migrated'           => $total_orders,
					'total'              => $total_orders,
					'total_course_count' => $total_course_count,
					'remaining'          => 0,
					'has_more'           => false,
					'batch_size'         => $batch_size,
					'target'             => 'wc',
				);
			}

			$lp_orders = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->posts} WHERE post_type = 'lp_order' AND post_status = 'lp-completed' ORDER BY ID ASC LIMIT %d",
					$batch_size
				)
			);

			$item_i = (int) get_option( '_tutor_migrated_items_count' );
			foreach ( $lp_orders as $lp_order ) {
				$item_i++;
				update_option( '_tutor_migrated_items_count', $item_i );

				$order_id           = $lp_order->ID;
				$migrate_order_data = array(
					'ID'          => $order_id,
					'post_status' => 'wc-completed',
					'post_type'   => 'shop_order',
				);

				wp_update_post( $migrate_order_data );

				$_items = $this->get_lp_order_items( $order_id );

				foreach ( $_items as $item ) {

					$item_data = array(
						'order_item_name' => $item->name,
						'order_item_type' => 'line_item',
						'order_id'        => $order_id,
					);

					$wpdb->insert( $wpdb->prefix . 'woocommerce_order_items', $item_data );
					$order_item_id = (int) $wpdb->insert_id;

					$lp_item_metas = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT meta_key, meta_value FROM {$wpdb->prefix}learnpress_order_itemmeta WHERE learnpress_order_item_id = %d ",
							$item->id
						)
					);

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
						'_line_tax_data'     => maybe_serialize( array( 'total' => array(), 'subtotal' => array() ) ),
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

				update_post_meta( $order_id, '_customer_user', get_post_meta( $order_id, '_user_id', true ) );
				update_post_meta( $order_id, '_customer_ip_address', get_post_meta( $order_id, '_user_ip_address', true ) );
				update_post_meta( $order_id, '_customer_user_agent', get_post_meta( $order_id, '_user_agent', true ) );

				$user_email = $wpdb->get_var( $wpdb->prepare( "SELECT user_email from {$wpdb->users} WHERE ID = %d ", $lp_order->post_author ) );
				update_post_meta( $order_id, '_billing_address_index', $user_email );
				update_post_meta( $order_id, '_billing_email', $user_email );
			}

			$remaining_after = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_order' AND post_status = 'lp-completed'" );
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
				'target'             => 'wc',
			);
		}

		/*
		* learnpress Review migrate to Tutor
		*/
		public function migrate_lp_reviews(){
			global $wpdb;

			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			$this->raise_migration_resource_limits();

			$batch_size = (int) apply_filters( 'tlmt_lp_review_migration_batch_size', self::REVIEW_BATCH_SIZE );
			if ( $batch_size < 1 ) {
				$batch_size = self::REVIEW_BATCH_SIZE;
			}

			$total_course_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_was_lp_course'" );
			$remaining_total    = (int) $wpdb->get_var( "SELECT COUNT(comments.comment_ID) FROM {$wpdb->comments} comments INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = comments.comment_ID AND cm.meta_key = '_lpr_rating' WHERE comments.comment_type = 'review'" );

			$is_first_batch = ! empty( $_POST['lp_review_migration_start'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream.
			if ( $is_first_batch ) {
				delete_option( '_tutor_migrated_items_count' );
				update_option( self::REVIEW_MIGRATION_TOTAL_OPT, $remaining_total, false );
			}

			$total_reviews = (int) get_option( self::REVIEW_MIGRATION_TOTAL_OPT, $remaining_total );
			if ( $total_reviews < 1 ) {
				$total_reviews = $remaining_total;
			}

			if ( $remaining_total < 1 ) {
				return array(
					'migrated'           => $total_reviews,
					'total'              => $total_reviews,
					'total_course_count' => $total_course_count,
					'remaining'          => 0,
					'has_more'           => false,
					'batch_size'         => $batch_size,
				);
			}

			$lp_review_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comments.comment_ID FROM {$wpdb->comments} comments
					INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = comments.comment_ID AND cm.meta_key = '_lpr_rating'
					WHERE comments.comment_type = 'review'
					ORDER BY comments.comment_ID ASC
					LIMIT %d",
					$batch_size
				)
			);

			$item_i = (int) get_option('_tutor_migrated_items_count');
			foreach ($lp_review_ids as $lp_review_id){
				$item_i++;
				update_option('_tutor_migrated_items_count', $item_i);

				$review_migrate_data = array(
					'comment_approved'  => 'approved',
					'comment_type'      => 'tutor_course_rating',
					'comment_agent'     => 'TutorLMSPlugin',
				);

				$wpdb->update($wpdb->comments, $review_migrate_data, array( 'comment_ID' => $lp_review_id));
				$wpdb->update($wpdb->commentmeta, array('meta_key' => 'tutor_rating'), array( 'comment_id' => $lp_review_id, 'meta_key' => '_lpr_rating' ));
				$wpdb->delete($wpdb->commentmeta, array('comment_id' => $lp_review_id, 'meta_key' => '_lpr_review_title'));
			}

			$remaining_after = (int) $wpdb->get_var( "SELECT COUNT(comments.comment_ID) FROM {$wpdb->comments} comments INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = comments.comment_ID AND cm.meta_key = '_lpr_rating' WHERE comments.comment_type = 'review'" );
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


		public function get_lp_order_items($order_id){
			global $wpdb;

			$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT order_item_id as id, order_item_name as name
				, oim.meta_value as `course_id`
				# , oim2.meta_value as `quantity`
				# , oim3.meta_value as `total`
			FROM {$wpdb->learnpress_order_items} oi
				INNER JOIN {$wpdb->learnpress_order_itemmeta} oim ON oi.order_item_id = oim.learnpress_order_item_id AND oim.meta_key='_course_id'
				# INNER JOIN {$wpdb->learnpress_order_itemmeta} oim2 ON oi.order_item_id = oim2.learnpress_order_item_id AND oim2.meta_key='_quantity'
				# INNER JOIN {$wpdb->learnpress_order_itemmeta} oim3 ON oi.order_item_id = oim3.learnpress_order_item_id AND oim3.meta_key='_total'
			WHERE order_id = %d ", $order_id ) );

			return $results;
		}


		/**
         *
         * Import From XML
		 */
		public function tutor_import_from_xml(){
		    global $wpdb;

			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			$wpdb->query('START TRANSACTION');
            $error = true;
			if (isset($_FILES['tutor_import_file'])){
				$course_post_type = tutor()->course_post_type;

				$xmlContent = file_get_contents($_FILES['tutor_import_file']['tmp_name']);
				libxml_use_internal_errors(true);
				$replacer = array(
					'&' => '&amp;',
					' allowfullscreen' => ' allowfullscreen="allowfullscreen"', // don't remove space
					' disabled' => ' disabled="disabled"'
				);
				$xmlContent = str_replace(array_keys($replacer), array_values($replacer), $xmlContent);
				$xml_data = simplexml_load_string($xmlContent, null, LIBXML_NOCDATA);
				if($xml_data == false) {
					$errors = libxml_get_errors();
					$error_message = '';
					if(is_array($errors)) {
						$error_message = $errors[0]->message . 'on line number ' . $errors[0]->line;
					}
					wp_send_json([
						'success' => false,
						'message' => $error_message,
					]);
				}

				$xml_data = simplexml_load_string($xmlContent);
				if($xml_data == false) {
					wp_send_json([
						'success' => false,
						'message' => 'Migration not successfull'
					]); 
				}
				$courses = $xml_data->courses;
				if($courses == false) {
					wp_send_json([
						'success' => false,
						'message' => 'Migration not successfull'
					]);
				}
				foreach ($courses as $course){

					$course_data = array(
						'post_author'   => (string) $course->post_author,
						'post_date'   =>(string)$course->post_date,
						'post_date_gmt'   => (string) $course->post_date_gmt,
						'post_content'  => (string) $course->post_content,
						'post_title'    => (string) $course->post_title,
						'post_status'   => 'publish',
						'post_type'     =>  $course_post_type,
					);

					//Inserting Course
					$course_id = wp_insert_post($course_data);

					$course_meta = json_decode(json_encode($course->course_meta), true);
					foreach ($course_meta as $course_meta_key => $course_meta_value){
						if ( is_array($course_meta_value)){
							$course_meta_value = json_encode($course_meta_value);
						}
						if($course_meta_key == '_thumbnail_id') {
							$thumbnail_post = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT  * FROM {$wpdb->posts}
									WHERE `ID` = %d AND `post_type` = %s
									LIMIT %d",
									$course_meta_value, 'attachment', 1
								)
							);
							if(count($thumbnail_post)) {
								$wpdb->insert($wpdb->postmeta, array('post_id' => $course_id, 'meta_key' => $course_meta_key, 'meta_value' =>$course_meta_value));
							}
						} else {
							$wpdb->insert($wpdb->postmeta, array('post_id' => $course_id, 'meta_key' => $course_meta_key, 'meta_value' =>$course_meta_value));
						}
					}

					foreach ($course->topics as $topic){
						$topic_data = array(
							'post_type'     => 'topics',
							'post_title'    => (string) $topic->post_title,
							'post_content'  => (string) $topic->post_content,
							'post_status'   => 'publish',
							'post_author'   => (string) $topic->post_author,
							'post_parent'   => $course_id,
							'menu_order'    => (string) $topic->menu_order,
						);

						//Inserting Topics
						$topic_id = wp_insert_post($topic_data);

						$item_i = 0;
						foreach ($topic->items as $item){
							$item_i++;

							$item_data = array(
								'post_type'     => (string) $item->post_type,
								'post_title'    => (string) $item->post_title,
								'post_content'  => (string) $item->post_content,
								'post_status'   => 'publish',
								'post_author'   => (string) $item->post_author,
								'post_parent'   => $topic_id,
								'menu_order'    => $item_i,
							);

							$item_id = wp_insert_post($item_data);

							$item_metas = json_decode(json_encode($item->item_meta), true);
							foreach ($item_metas as $item_meta_key => $item_meta_value){
								if ( is_array($item_meta_value)){
									$item_meta_value = json_encode($item_meta_value);
								}
								$wpdb->insert($wpdb->postmeta, array('post_id' => $item_id, 'meta_key' => $item_meta_key, 'meta_value'=> (string) $item_meta_value));
							}

							if (isset($item->questions) && is_object($item->questions) && count($item->questions)){
								foreach ($item->questions as $question) {
									$answers = $question->answers;
									$question = (array) $question;
									$question['quiz_id'] = $item_id;
									$question['question_description'] = (string) $question['question_description'];
								
									unset($question['answers']);

									$wpdb->insert($wpdb->prefix.'tutor_quiz_questions', $question);
									$question_id = $wpdb->insert_id;

									foreach ($answers as $answer){
										$answer = (array) $answer;
										$answer['belongs_question_id'] = $question_id;
										$wpdb->insert($wpdb->prefix.'tutor_quiz_question_answers', $answer);
									}
								}
							}

							if ( 'tutor_quiz' === $item_data['post_type'] ) {
								update_post_meta( $item_id, 'tutor_quiz_option', $this->build_tutor_quiz_option_from_lp( $item_id ) );
							}
						}
					}

					if (isset($course->reviews) && is_object($course->reviews) && count($course->reviews) ){
						foreach ($course->reviews as $review){
							$rating_data = array(
								'comment_post_ID'   => $course_id,
								'comment_approved'  => 'approved',
								'comment_type'      => 'tutor_course_rating',
								'comment_date'      => (string) $review->comment_date,
								'comment_date_gmt'  => (string) $review->comment_date,
								'comment_content'   => (string) $review->comment_content,
								'user_id'           => (string) $review->user_id,
								'comment_author'    => (string) $review->comment_author,
								'comment_agent'     => 'TutorLMSPlugin',
							);

							$wpdb->insert($wpdb->comments, $rating_data);
							$comment_id = (int) $wpdb->insert_id;

							$rating_meta_data = array(
								'comment_id' => $comment_id,
								'meta_key' => 'tutor_rating',
								'meta_value' => (string) $review->tutor_rating
							);
							$wpdb->insert( $wpdb->commentmeta,  $rating_meta_data);
						}
					}
				}
				$error = false;
			}
			if($error) {
                $wpdb->query('ROLLBACK');
                wp_send_json([
                    'success' => false,
                    'message' => 'LP Migration not successfull'
                ]);
            } else {
                $wpdb->query('COMMIT');
				wp_send_json([
					'success' => true,
					'message' => 'LP Migration successfull'
				]);
            }
		}


		public function tutor_lp_export_xml(){
			tutor_utils()->checking_nonce();

			Utils::check_course_access();

			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=learnpress_data_for_tutor.xml');
			header('Expires: 0');

			echo $this->generate_xml_data();
			exit;
		}


		public function generate_xml_data(){
			global $wpdb;

			$xml = '<?xml version="1.0" encoding="' . get_bloginfo( 'charset' ) . "\" ?>\n";
			$xml .= $this->start_element('channel');
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

			$lp_courses = $wpdb->get_results("SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_status  FROM {$wpdb->posts} WHERE post_type = 'lp_course' AND post_status = 'publish';");

			if (tutils()->count($lp_courses)){
				$course_i = 0;
				foreach ($lp_courses as $lp_course){
					$course_i++;

					$course_id = $lp_course->ID;

					$xml .= $this->start_element('courses');

					$course_arr = (array) $lp_course;
					foreach ($course_arr as $course_col => $course_col_value){
						$xml .= "<{$course_col}>{$course_col_value}</{$course_col}>\n";
					}


					$course_metas = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value from {$wpdb->postmeta} where post_id = %d ", $course_id ) );

					$xml .= $this->start_element('course_meta');
					foreach ($course_metas as $course_meta){
						$xml .= "<{$course_meta->meta_key}>{$course_meta->meta_value}</{$course_meta->meta_key}>\n";
					}
					$xml .= $this->close_element('course_meta');

					$course = learn_press_get_course($course_id);

					$lesson_post_type = tutor()->lesson_post_type;
					$course_post_type = tutor()->course_post_type;

					if ( $course) {
						$curriculum = $course->get_curriculum();

						$i            = 0;

						if($curriculum) {
							foreach ( $curriculum as $section ) {
								$i ++;

								$xml .= $this->start_element('topics');

								/**
								 * Topic
								 */
								$xml .= "<post_type>topics</post_type>\n";
								$xml .= "<post_title>{$section->get_title()}</post_title>\n";

								$topic_content = ! empty($section->get_description()) ? $this->xml_cdata($section->get_description()) : '';

								$xml .= "<post_content>{$topic_content}</post_content>\n";
								$xml .= "<post_status>publish</post_status>\n";
								$xml .= "<post_author>{$course->get_author( 'id' )}</post_author>\n";
								$xml .= "<post_parent>{$course_id}</post_parent>";
								$xml .= "<menu_order>{$i}</menu_order>\n";

								/**
								 * Lessons
								 */
								$lessons = $this->get_lp_section_items($section->get_id());

								foreach ( $lessons as $lesson ) {
									$item_post_type = $lesson->item_type;

									if ( $item_post_type !== 'lp_lesson' ) {
										if ( $item_post_type === 'lp_quiz' ) {
											$lesson_post_type = 'tutor_quiz';
										}
									}

									//Item
									$xml .= $this->start_element('items');

									$xml .= "<item_id>{$lesson->id}</item_id>\n";
									$xml .= "<post_type>{$lesson_post_type}</post_type>\n";
									$xml .= "<post_author>{$lesson->post_author}</post_author>\n";
									$xml .= "<post_date>{$lesson->post_date}</post_date>\n";
									$xml .= "<post_title>{$lesson->post_title}</post_title>\n";
									$xml .= "<post_content>{$this->xml_cdata($lesson->post_content)}</post_content>\n";
									$xml .= "<post_parent>{topic_id}</post_parent>\n";

									$xml .= $this->start_element('item_meta');

									$item_metas = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ", $lesson->id ) );

									if (is_array($item_metas) && count($item_metas)){
										foreach ($item_metas as $item_meta){
											$xml .= "<{$item_meta->meta_key}>{$this->xml_cdata($item_meta->meta_value)}</{$item_meta->meta_key}>\n";
										}
									}

									$xml .= $this->close_element('item_meta');

									if ($lesson_post_type === 'tutor_quiz'){
										$quiz_id = $lesson->id;

										$questions = $wpdb->get_results(
											$wpdb->prepare(
												"SELECT question_id, question_order, questions.ID, questions.post_content, questions.post_title, question_type_meta.meta_value as question_type, question_mark_meta.meta_value as question_mark
												FROM {$wpdb->prefix}learnpress_quiz_questions
												LEFT JOIN {$wpdb->posts} questions on question_id = questions.ID
												LEFT JOIN {$wpdb->postmeta} question_type_meta on question_id = question_type_meta.post_id AND question_type_meta.meta_key = '_lp_type'
												LEFT JOIN {$wpdb->postmeta} question_mark_meta on question_id = question_mark_meta.post_id AND question_mark_meta.meta_key = '_lp_mark'
												WHERE quiz_id = %d  ",
												$quiz_id
											)
										);

										if (tutils()->count($questions)){

											foreach ($questions as $question) {

												$question_type = $this->map_lp_question_type_to_tutor( $question->question_type );

												if ($question_type) {
													$xml .= $this->start_element('questions');
													$new_question_data = array(
														'quiz_id'              => $quiz_id,
														'question_title'       => $question->post_title,
														'question_description' => $question->post_content,
														'question_type'        => $question_type,
														'question_mark'        => $question->question_mark,
														'question_settings'    => maybe_serialize( $this->build_tutor_question_settings( $question_type, $question->question_mark ) ),
														'question_order'       => $question->question_order,
													);

													foreach ($new_question_data as $question_key => $question_value){
														$xml .= "<{$question_key}>$question_value</{$question_key}>\n";
													}

													$answer_items = $wpdb->get_results( $wpdb->prepare( "SELECT * from {$wpdb->prefix}learnpress_question_answers where question_id = %d ", $question->question_id ) );

													if (tutils()->count($answer_items)){
														foreach ($answer_items as $answer_item){
															$answer_data = $this->build_tutor_answer_from_lp( $answer_item, (int) $answer_item->question_id, $question_type );

															$xml .= $this->start_element('answers');

															foreach ($answer_data as $answers_key => $answers_value){
																$xml .= "<{$answers_key}>$answers_value</{$answers_key}>\n";
															}
															$xml .= $this->close_element('answers');
														}
													}

													$xml .= $this->close_element('questions');
												}
											}
										}
									}

									$xml .= $this->close_element('items');
								}

								//Close Topic Tag
								$xml .= $this->close_element('topics');
							}
						}
					}

					$lp_reviews = $wpdb->get_results("SELECT comments.comment_post_ID,
                    comments.comment_post_ID,
                    comments.comment_author,
                    comments.comment_author_email,
                    comments.comment_author_IP,
                    comments.comment_date,
                    comments.comment_date_gmt,
                    comments.comment_content,
                    comments.user_id,
                    cm.meta_value as tutor_rating
                     FROM {$wpdb->comments} comments INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = comments.comment_ID AND cm.meta_key = '_lpr_rating' WHERE comments.comment_type = 'review';", ARRAY_A);

					if (tutils()->count($lp_reviews)){
						foreach ($lp_reviews as $lp_review){
							$lp_review['comment_approved'] = 'approved';
							$lp_review['comment_agent'] = 'TutorLMSPlugin';
							$lp_review['comment_type'] = 'tutor_course_rating';

							$xml .= $this->start_element('reviews');
							foreach ($lp_review as $lp_review_key => $lp_review_value){
								$xml .= "<{$lp_review_key}>{$this->xml_cdata($lp_review_value)}</{$lp_review_key}>\n";
							}
							$xml .= $this->close_element('reviews');
						}
					}

					$xml .= $this->close_element('courses');
				}
			}

			$xml .= $this->close_element('channel');
			return $xml;
		}

		public function start_element($element = ''){
			return "\n<{$element}>\n";
		}
		public function close_element($element = ''){
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

		public function get_lp_section_items( $section_id  ) {
			global $wpdb;

			$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT item_id id, item_type, it.post_author, it.post_date, it.post_content, it.post_title, it.post_excerpt

			FROM {$wpdb->learnpress_section_items} si

			INNER JOIN {$wpdb->learnpress_sections} s ON si.section_id = s.section_id
			INNER JOIN {$wpdb->posts} c ON c.ID = s.section_course_id
			INNER JOIN {$wpdb->posts} it ON it.ID = si.item_id

			WHERE s.section_id = %d
			AND it.post_status = %s
			ORDER BY si.item_order, si.section_item_id ASC
		", $section_id, /*'publish',*/ 'publish' ) );

			return $results;
		}

		/**
		 * Map a LearnPress question type slug to Tutor's equivalent.
		 *
		 * Unsupported LP types return null and are skipped during migration.
		 *
		 * @since 2.5.0
		 *
		 * @param string $lp_type LearnPress `_lp_type` meta value.
		 *
		 * @return string|null Tutor question type, or null if unsupported.
		 */
		private function map_lp_question_type_to_tutor( $lp_type ) {
			$map = array(
				'true_or_false'  => 'true_false',
				'single_choice'  => 'single_choice',
				'multi_choice'   => 'multiple_choice',
				'fill_in_blanks' => \Themeum\TutorLMSMigrationTool\LPMigration\Quizzes\FillInBlanksTransformer::TUTOR_TYPE,
			);

			return isset( $map[ $lp_type ] ) ? $map[ $lp_type ] : null;
		}

		/**
		 * Build Tutor `question_settings` for a migrated LearnPress question.
		 *
		 * Tutor's student quiz UI renders checkboxes only when
		 * `has_multiple_correct_answer` is the string `'1'`. The course builder
		 * defaults a missing flag to true for `multiple_choice`, which is why
		 * migrated multi-answer MCQs looked correct in the builder but showed
		 * radios for students until re-saved.
		 *
		 * @since 2.5.0
		 *
		 * @param string     $question_type Tutor question type slug.
		 * @param int|string $question_mark Question mark/points.
		 *
		 * @return array
		 */
		private function build_tutor_question_settings( $question_type, $question_mark ) {
			$settings = array(
				'question_type'      => $question_type,
				'question_mark'      => $question_mark,
				'answer_required'    => 0,
				'randomize_question' => 0,
				'show_question_mark' => 0,
			);

			if ( 'multiple_choice' === $question_type ) {
				$settings['has_multiple_correct_answer'] = '1';
			}

			return $settings;
		}

		/**
		 * Build a Tutor quiz answer row from a LearnPress question_answers row.
		 *
		 * Handles LP 4+ columns, LP 3 serialized `answer_data`, and fill-in-blanks
		 * conversion to Tutor `{dash}` / `answer_two_gap_match` format.
		 *
		 * @since 2.5.0
		 *
		 * @param object $answer_item   LearnPress answer row.
		 * @param int    $question_id   Tutor question ID (or LP question ID for XML export).
		 * @param string $question_type Tutor question type slug.
		 *
		 * @return array
		 */
		private function build_tutor_answer_from_lp( $answer_item, $question_id, $question_type ) {
			// LearnPress 4+ uses title/is_true/order; LP 3 used serialized answer_data.
			if ( isset( $answer_item->title ) ) {
				$answer_title = $answer_item->title;
				$is_correct   = ( isset( $answer_item->is_true ) && 'yes' === $answer_item->is_true ) ? 1 : 0;
				$answer_order = isset( $answer_item->order ) ? (int) $answer_item->order : 0;
			} else {
				$legacy_data  = maybe_unserialize( isset( $answer_item->answer_data ) ? $answer_item->answer_data : '' );
				$answer_title = tutils()->array_get( 'text', $legacy_data );
				$is_correct   = tutils()->array_get( 'is_true', $legacy_data ) == 'yes' ? 1 : 0;
				$answer_order = isset( $answer_item->answer_order ) ? (int) $answer_item->answer_order : 0;
			}

			$answer_data = array(
				'belongs_question_id'   => (int) $question_id,
				'belongs_question_type' => $question_type,
				'answer_title'          => $answer_title,
				'is_correct'            => $is_correct,
				'answer_order'          => $answer_order,
			);

			if ( \Themeum\TutorLMSMigrationTool\LPMigration\Quizzes\FillInBlanksTransformer::TUTOR_TYPE === $question_type ) {
				$answer_id = 0;
				if ( isset( $answer_item->question_answer_id ) ) {
					$answer_id = (int) $answer_item->question_answer_id;
				}

				$blanks      = $this->get_lp_answer_blanks( $answer_id );
				$transformer = new \Themeum\TutorLMSMigrationTool\LPMigration\Quizzes\FillInBlanksTransformer();
				$converted   = $transformer->transform( (string) $answer_title, $blanks );

				$answer_data['answer_title']         = $converted['answer_title'];
				$answer_data['answer_two_gap_match'] = $converted['answer_two_gap_match'];
				$answer_data['is_correct']           = 1;
			}

			return $answer_data;
		}

		/**
		 * Fetch LearnPress `_blanks` meta for a question answer.
		 *
		 * @since 2.5.0
		 *
		 * @param int $answer_id LearnPress `question_answer_id`.
		 *
		 * @return array|string|null
		 */
		private function get_lp_answer_blanks( $answer_id ) {
			global $wpdb;

			$answer_id = (int) $answer_id;
			if ( $answer_id <= 0 ) {
				return null;
			}

			if ( function_exists( 'learn_press_get_question_answer_meta' ) ) {
				return learn_press_get_question_answer_meta( $answer_id, '_blanks', true );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$meta_value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->prefix}learnpress_question_answermeta
					WHERE learnpress_question_answer_id = %d AND meta_key = %s
					LIMIT 1",
					$answer_id,
					'_blanks'
				)
			);

			return maybe_unserialize( $meta_value );
		}

		/**
		 * Build Tutor `tutor_quiz_option` from LearnPress quiz meta.
		 *
		 * Maps only settings Tutor already supports:
		 * - `_lp_duration`        → `time_limit`
		 * - `_lp_passing_grade`   → `passing_grade`
		 * - `_lp_retake_count`    → `limit_attempts_allowed` / `attempts_allowed` / `feedback_mode`
		 * - `_lp_instant_check`   → `enable_answer_reveal`
		 *
		 * Unmapped (no Tutor quiz-option equivalent): `_lp_review`, `_lp_negative_marking`.
		 *
		 * @param int $quiz_id Quiz post ID (still holding LP meta, or after meta copy on XML import).
		 * @return array
		 */
		private function build_tutor_quiz_option_from_lp( $quiz_id ) {
			$quiz_option = array(
				'time_limit'                         => array(
					'time_value' => 0,
					'time_type'  => 'minutes',
				),
				'hide_quiz_time_display'             => '0',
				'feedback_mode'                      => 'default',
				'attempts_allowed'                   => 10,
				'limit_attempts_allowed'             => '0',
				'enable_answer_reveal'               => '0',
				'passing_grade'                      => 80,
				'max_questions_for_answer'           => 10,
				'quiz_auto_start'                    => '0',
				'question_layout_view'               => '',
				'questions_order'                    => 'rand',
				'short_answer_characters_limit'      => 200,
				'open_ended_answer_characters_limit' => 500,
				'pass_is_required'                   => '0',
				'content_drip_settings'              => array(
					'unlock_date'           => '',
					'after_xdays_of_enroll' => '',
					'prerequisites'         => array(),
				),
			);

			$time_limit = $this->lp_duration_to_tutor_quiz_time_limit( get_post_meta( $quiz_id, '_lp_duration', true ) );
			if ( ! empty( $time_limit ) ) {
				$quiz_option['time_limit'] = $time_limit;
			}

			$passing_grade = get_post_meta( $quiz_id, '_lp_passing_grade', true );
			if ( is_numeric( $passing_grade ) ) {
				$quiz_option['passing_grade'] = max( 0, min( 100, (int) $passing_grade ) );
			}

			$retake_count = get_post_meta( $quiz_id, '_lp_retake_count', true );
			if ( '' !== $retake_count && null !== $retake_count ) {
				$retake_count = (int) $retake_count;

				if ( -1 === $retake_count ) {
					// Unlimited retakes.
					$quiz_option['limit_attempts_allowed'] = '1';
					$quiz_option['attempts_allowed']       = 0;
					$quiz_option['feedback_mode']          = 'retry';
				} elseif ( $retake_count > 0 ) {
					// LP stores retakes; Tutor stores total attempts (initial + retakes).
					$quiz_option['limit_attempts_allowed'] = '1';
					$quiz_option['attempts_allowed']       = $retake_count + 1;
					$quiz_option['feedback_mode']          = 'retry';
				} else {
					// No retakes — single attempt.
					$quiz_option['limit_attempts_allowed'] = '0';
					$quiz_option['attempts_allowed']       = 1;
					$quiz_option['feedback_mode']          = 'default';
				}
			}

			$instant_check = get_post_meta( $quiz_id, '_lp_instant_check', true );
			if ( 'yes' === $instant_check ) {
				$quiz_option['enable_answer_reveal'] = '1';
			}

			return $quiz_option;
		}

		/**
		 * Convert LearnPress quiz `_lp_duration` into Tutor quiz `time_limit`.
		 *
		 * @param mixed $lp_duration LearnPress duration (e.g. "30 minute", "1 hour", "0").
		 * @return array{time_value:int,time_type:string}|array Empty array when unparseable / no limit text.
		 */
		private function lp_duration_to_tutor_quiz_time_limit( $lp_duration ) {
			if ( '' === $lp_duration || null === $lp_duration ) {
				return array();
			}

			$lp_duration = strtolower( trim( (string) $lp_duration ) );

			// Bare zero (or "0 minute") means no time limit.
			if ( '0' === $lp_duration || preg_match( '/^0\s*(minute|hour|day|week)s?$/', $lp_duration ) ) {
				return array(
					'time_value' => 0,
					'time_type'  => 'minutes',
				);
			}

			if ( ! preg_match( '/^([0-9]+)\s*(minutes?|hours?|days?|weeks?|seconds?)$/', $lp_duration, $match ) ) {
				return array();
			}

			$value = (int) $match[1];
			$unit  = rtrim( $match[2], 's' );

			$type_map = array(
				'second' => 'seconds',
				'minute' => 'minutes',
				'hour'   => 'hours',
				'day'    => 'days',
				'week'   => 'weeks',
			);

			return array(
				'time_value' => $value,
				'time_type'  => isset( $type_map[ $unit ] ) ? $type_map[ $unit ] : 'minutes',
			);
		}

		/**
		 * Resolve LearnPress regular/sale prices for a course.
		 *
		 * Prefers `_lp_regular_price`, falls back to `_lp_price`.
		 * Sale applies only when > 0 and strictly less than regular.
		 *
		 * @since 2.5.0
		 *
		 * @param int $course_id Course ID.
		 *
		 * @return array{regular: float, sale: float}
		 */
		private function get_lp_course_prices( $course_id ) {
			$regular = (float) get_post_meta( $course_id, '_lp_regular_price', true );
			if ( $regular <= 0 ) {
				$regular = (float) get_post_meta( $course_id, '_lp_price', true );
			}

			$sale = (float) get_post_meta( $course_id, '_lp_sale_price', true );
			if ( $sale <= 0 || $sale >= $regular ) {
				$sale = 0;
			}

			return array(
				'regular' => $regular,
				'sale'    => $sale,
			);
		}

		/**
		 * Write Tutor native course price meta from LearnPress prices.
		 *
		 * Always runs so courses are not left as free when monetize_by is Tutor
		 * (or when WC/EDD product creation is skipped).
		 *
		 * @since 2.5.0
		 *
		 * @param int $course_id Course ID.
		 *
		 * @return void
		 */
		private function migrate_lp_course_pricing_to_tutor( $course_id ) {
			$prices = $this->get_lp_course_prices( $course_id );

			if ( $prices['regular'] > 0 ) {
				update_post_meta( $course_id, \TUTOR\Course::COURSE_PRICE_TYPE_META, \TUTOR\Course::PRICE_TYPE_PAID );
				update_post_meta( $course_id, \TUTOR\Course::COURSE_PRICE_META, $prices['regular'] );

				if ( $prices['sale'] > 0 ) {
					update_post_meta( $course_id, \TUTOR\Course::COURSE_SALE_PRICE_META, $prices['sale'] );
				} else {
					delete_post_meta( $course_id, \TUTOR\Course::COURSE_SALE_PRICE_META );
				}

				$this->ensure_native_monetization_for_lp_prices();
			} else {
				update_post_meta( $course_id, \TUTOR\Course::COURSE_PRICE_TYPE_META, \TUTOR\Course::PRICE_TYPE_FREE );
				delete_post_meta( $course_id, \TUTOR\Course::COURSE_PRICE_META );
				delete_post_meta( $course_id, \TUTOR\Course::COURSE_SALE_PRICE_META );
			}
		}

		/**
		 * If site monetization is unset/free and WC/EDD are not the active mode,
		 * switch to Tutor native so paid course prices actually render.
		 *
		 * @since 2.5.0
		 *
		 * @return void
		 */
		private function ensure_native_monetization_for_lp_prices() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;

			if ( tutor_utils()->is_monetize_by_tutor() ) {
				return;
			}

			$monetize_by = tutor_utils()->get_option( 'monetize_by' );

			// Do not override an explicit WC/EDD monetization choice.
			if ( 'wc' === $monetize_by || 'edd' === $monetize_by || 'pmpro' === $monetize_by ) {
				return;
			}

			// If WooCommerce is present and monetize is free/-1/empty, keep legacy WC product path
			// available; only force native when there is no WC store to attach products to.
			if ( tutor_utils()->has_wc() ) {
				return;
			}

			tutor_utils()->update_option( 'monetize_by', \Tutor\Ecommerce\Ecommerce::MONETIZE_BY );
		}

		/**
		 * Map LearnPress course meta to Tutor course meta.
		 *
		 * @param int $course_id Tutor course post id.
		 * @return void
		 */
		private function migrate_lp_course_meta_to_tutor( $course_id ) {
			// Course level.
			$lp_level = get_post_meta( $course_id, '_lp_level', true );
			if ( ! empty( $lp_level ) ) {
				// Tutor expects 'all_levels' while LearnPress uses 'all'.
				if ( 'all' === $lp_level ) {
					$lp_level = 'all_levels';
				}
				update_post_meta( $course_id, '_tutor_course_level', sanitize_text_field( $lp_level ) );
			}

			// Public course (no enrollment required).
			$lp_no_required_enroll = get_post_meta( $course_id, '_lp_no_required_enroll', true );
			update_post_meta(
				$course_id,
				\TUTOR\Course::PUBLIC_COURSE_META,
				( 'yes' === $lp_no_required_enroll ) ? 'yes' : 'no'
			);

			// Course duration (display).
			$lp_duration = get_post_meta( $course_id, '_lp_duration', true );
			$tutor_duration = $this->lp_duration_to_tutor_course_duration( $lp_duration );
			if ( ! empty( $tutor_duration ) ) {
				update_post_meta( $course_id, '_course_duration', $tutor_duration );
			}

			// Newline-separated list metas.
			$requirements = $this->normalize_lp_multiline_meta( get_post_meta( $course_id, '_lp_requirements', true ) );
			if ( '' !== $requirements ) {
				update_post_meta( $course_id, '_tutor_course_requirements', sanitize_textarea_field( $requirements ) );
			}

			$target_audiences = $this->normalize_lp_multiline_meta( get_post_meta( $course_id, '_lp_target_audiences', true ) );
			if ( '' !== $target_audiences ) {
				update_post_meta( $course_id, '_tutor_course_target_audience', sanitize_textarea_field( $target_audiences ) );
			}

			$key_features = $this->normalize_lp_multiline_meta( get_post_meta( $course_id, '_lp_key_features', true ) );
			if ( '' !== $key_features ) {
				update_post_meta( $course_id, '_tutor_course_benefits', sanitize_textarea_field( $key_features ) );
			}

			// Course settings bag: maximum students + enrollment expiry.
			$current_settings = maybe_unserialize( get_post_meta( $course_id, '_tutor_course_settings', true ) );
			if ( ! is_array( $current_settings ) ) {
				$current_settings = array();
			}
			$settings_changed = false;

			$lp_max_students = get_post_meta( $course_id, '_lp_max_students', true );
			if ( is_numeric( $lp_max_students ) ) {
				$current_settings['maximum_students'] = (int) $lp_max_students;
				$settings_changed                     = true;
			}

			// When LP blocks access after duration, map that length to Tutor enrollment_expiry (days).
			$lp_block_expire = get_post_meta( $course_id, '_lp_block_expire_duration', true );
			if ( 'yes' === $lp_block_expire ) {
				$expiry_days = $this->lp_duration_to_days( $lp_duration );
				if ( $expiry_days > 0 ) {
					$current_settings['enrollment_expiry'] = $expiry_days;
					$settings_changed                      = true;
				}
			}

			if ( $settings_changed ) {
				update_post_meta( $course_id, '_tutor_course_settings', maybe_serialize( $current_settings ) );
			}
		}

		/**
		 * Normalize LearnPress list meta (array or string) into Tutor newline string.
		 *
		 * @param mixed $value LearnPress meta value.
		 * @return string Newline-separated list.
		 */
		private function normalize_lp_multiline_meta( $value ) {
			$value = maybe_unserialize( $value );

			if ( empty( $value ) ) {
				return '';
			}

			if ( is_array( $value ) ) {
				$items = array();
				foreach ( $value as $item ) {
					if ( is_array( $item ) ) {
						continue;
					}

					$item = is_string( $item ) ? trim( $item ) : (string) $item;
					if ( '' !== $item ) {
						$items[] = $item;
					}
				}

				return implode( "\n", $items );
			}

			if ( is_string( $value ) ) {
				$value = str_replace( array( "\r\n", "\r" ), "\n", $value );

				// If it looks like a comma-separated string, make it multiline.
				if ( false !== strpos( $value, ',' ) && false === strpos( $value, "\n" ) ) {
					$value = str_replace( ',', "\n", $value );
				}

				return trim( $value );
			}

			return '';
		}

		/**
		 * Parse LearnPress `_lp_duration` into total seconds.
		 *
		 * Supports singular/plural units: minute(s), hour(s), day(s), week(s), month(s).
		 *
		 * @param mixed $lp_duration LearnPress duration meta value.
		 * @return int Total seconds, or 0 on failure.
		 */
		private function lp_duration_to_seconds( $lp_duration ) {
			if ( empty( $lp_duration ) ) {
				return 0;
			}

			$lp_duration = strtolower( trim( (string) $lp_duration ) );
			$lp_duration = str_replace( ',', ' ', $lp_duration );

			$seconds = 0;
			if ( ! preg_match_all( '/([0-9]+)\s*(minutes?|hours?|days?|weeks?|months?)/', $lp_duration, $matches, PREG_SET_ORDER ) ) {
				return 0;
			}

			foreach ( $matches as $match ) {
				$number = (int) $match[1];
				$unit   = rtrim( $match[2], 's' );

				switch ( $unit ) {
					case 'hour':
						$seconds += $number * 3600;
						break;
					case 'day':
						$seconds += $number * 86400;
						break;
					case 'week':
						$seconds += $number * 604800;
						break;
					case 'month':
						// Approximate calendar month as 30 days.
						$seconds += $number * 2592000;
						break;
					case 'minute':
					default:
						$seconds += $number * 60;
						break;
				}
			}

			return $seconds;
		}

		/**
		 * Convert LearnPress `_lp_duration` into Tutor enrollment expiry days.
		 *
		 * @param mixed $lp_duration LearnPress duration meta value.
		 * @return int Number of days (ceiled), or 0 on failure.
		 */
		private function lp_duration_to_days( $lp_duration ) {
			$seconds = $this->lp_duration_to_seconds( $lp_duration );
			if ( $seconds <= 0 ) {
				return 0;
			}

			return (int) max( 1, (int) ceil( $seconds / 86400 ) );
		}

		/**
		 * Convert LearnPress `_lp_duration` (e.g. "4 week", "30 day") into Tutor `_course_duration`.
		 *
		 * Tutor stores duration as a serialized array with keys: hours/minutes/seconds.
		 *
		 * @param mixed $lp_duration LearnPress duration meta value.
		 * @return string Serialized Tutor duration meta value, or empty string on failure.
		 */
		private function lp_duration_to_tutor_course_duration( $lp_duration ) {
			$seconds = $this->lp_duration_to_seconds( $lp_duration );
			if ( $seconds <= 0 ) {
				return '';
			}

			$hours          = (int) floor( $seconds / 3600 );
			$minutes        = (int) floor( ( $seconds % 3600 ) / 60 );
			$remaining_secs = (int) ( $seconds % 60 );

			return maybe_serialize(
				array(
					'hours'   => $hours,
					'minutes' => $minutes,
					'seconds' => $remaining_secs,
				)
			);
		}

	}
}