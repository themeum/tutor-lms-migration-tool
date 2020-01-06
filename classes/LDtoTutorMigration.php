<?php
if ( ! defined( 'ABSPATH' ) )
	exit;

if (! class_exists('LDtoTutorMigration')) {
	class LDtoTutorMigration
	{
		public function __construct()
		{
			add_filter('tutor_tool_pages', array($this, 'ld_tool_pages'));

			add_action('wp_ajax_ld_migrate_all_data_to_tutor', array($this, 'ld_migrate_all_data_to_tutor'));
			add_action('wp_ajax_ld_reset_migrated_items_count', array($this, 'ld_reset_migrated_items_count'));

			add_action('wp_ajax__get_ld_live_progress_course_migrating_info', array($this, '_get_ld_live_progress_course_migrating_info'));
		}


		public function ld_tool_pages($pages)
		{
			if (defined('LEARNDASH_VERSION')) {
				$pages['migration_ld'] = array('title' =>  __('LearnDash Migration', 'tutor-lms-migration-tool'), 'view_path' => TLMT_PATH.'views/migration_ld.php');
			}
			return $pages;
		}


		public function ld_reset_migrated_items_count()
		{
			delete_option('_tutor_migrated_items_count');
		}


		public function ld_migrate_all_data_to_tutor()
		{
			if (isset($_POST['migrate_type'])) {
				$migrate_type = sanitize_text_field($_POST['migrate_type']);

				switch ($migrate_type) {
					case 'courses':
						$this->ld_migrate_course_to_tutor();
						break;
				}
				wp_send_json_success();
			}
			wp_send_json_error();
		}



		public function ld_migrate_course_to_tutor($return_type = false)
		{
			global $wpdb;
			$ld_courses = $wpdb->get_results("SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_status FROM {$wpdb->posts} WHERE post_type = 'sfwd-courses' AND post_status = 'publish';");

			die(print_r($ld_courses));

			$course_type = tutor()->course_post_type;

			if (tutils()->count($ld_courses)) {
				$course_i = (int) get_option('_tutor_migrated_items_count');
				$i = 0;
				foreach ($ld_courses as $ld_course) {
					$course_i++;
					$course_id = $this->insert_post($course_type, $ld_course->post_title, $ld_course->post_content, $ld_course->post_author, 0, '');
					if ($course_id) {
						$this->migrate_course($ld_course->ID, $course_id);
						update_option('_tutor_migrated_items_count', $course_i);

						// Attached Product
						$this->attached_product($course_id, $ld_course->post_title);

						// Add Enrollments
						//$this->insert_enrollment($course_id);

						// Attached thumbnail
						$this->insert_thumbnail($ld_course->ID, $course_id);
					}
				}
			}
			wp_send_json_success();
		}

		/**
		 * Insert thumbnail ID
		 */
		public function insert_thumbnail($new_thumbnail_id, $thumbnail_id)
		{
			$thumbnail = get_post_meta($thumbnail_id, '_thumbnail_id', true);
			if ($thumbnail) {
				set_post_thumbnail($new_thumbnail_id, $thumbnail);
			}
		}

		/**
		 * Insert Enbrolement LD to Tutor
		 */
		public function insert_enrollment($course_id)
		{
			global $wpdb;
			$ld_enrollments = $wpdb->get_results("SELECT * from {$wpdb->prefix}usermeta WHERE meta_key = 'course_{$course_id}_access_from'");

			foreach ($ld_enrollments as $ld_enrollment) {
				$user_id = $ld_enrollment->user_id;

				if (! tutils()->is_enrolled($course_id, $user_id)) {
					$order_time = strtotime($ld_enrollment->meta_value);

					$title = __('Course Enrolled', 'tutor')." &ndash; ".date(get_option('date_format'), $order_time).' @ '.date(get_option('time_format'), $order_time);
					$tutor_enrollment_data = array(
						'post_type'   => 'tutor_enrolled',
						'post_title'  => $title,
						'post_status' => 'completed',
						'post_author' => $user_id,
						'post_parent' => $course_id,
					);

					$isEnrolled = wp_insert_post($tutor_enrollment_data);

					if ($isEnrolled) {
						//Mark Current User as Students with user meta data
						update_user_meta($user_id, '_is_tutor_student', $order_time);
					}
				}
			}
		}


		/**
		 * Create WC Product and attaching it with course
		 */
		public function attached_product($course_id, $course_title)
		{
			if (tutils()->has_wc()) {
				$_ld_price = get_post_meta($course_id, '_sfwd-courses', true);
				if ($_ld_price['sfwd-courses_custom_button_url']) {
					update_post_meta($course_id, '_tutor_course_price_type', 'paid');
					$product_id = wp_insert_post(array(
						'post_title' => $course_title.' Product',
						'post_content' => '',
						'post_status' => 'publish',
						'post_type' => "product",
					));
					if ($product_id) {
						$product_metas = array(
							'_stock_status'      => 'instock',
							'total_sales'        => '0',
							'_regular_price'     => '',
							'_sale_price'        => '',
							'_price'             => $_ld_price,
							'_sold_individually' => 'no',
							'_manage_stock'      => 'no',
							'_backorders'        => 'no',
							'_stock'             => '',
							'_virtual'           => 'yes',
							'_tutor_product'     => 'yes',
						);
						foreach ($product_metas as $key => $value) {
							update_post_meta($product_id, $key, $value);
						}

						// Attaching product to course
						update_post_meta($course_id, '_tutor_course_product_id', $product_id);
						$coursePostThumbnail = get_post_meta($course_id, '_thumbnail_id', true);
						if ($coursePostThumbnail) {
							set_post_thumbnail($product_id, $coursePostThumbnail);
						}
					}
				} else {
					update_post_meta($course_id, '_tutor_course_price_type', 'free');
				}
			}
		}


		public function _get_ld_live_progress_course_migrating_info()
		{
			$migrated_count = (int) get_option('_tutor_migrated_items_count');
			wp_send_json_success(array('migrated_count' => $migrated_count ));
		}


		public function insert_post($post_type = 'topics', $post_title, $post_content, $author_id, $menu_order = 0, $post_parent = '')
		{
			$post_arg = array(
				'post_type'     => $post_type,
				'post_title'    => $post_title,
				'post_content'  => $post_content,
				'post_status'   => 'publish',
				'post_author'   => $author_id,
				'post_parent'   => $post_parent,
				'menu_order'    => $menu_order,
			);
			return wp_insert_post($post_arg);
		}

		public function migrate_quiz($new_quiz_id, $old_quiz_id)
		{
			$question_id = get_post_meta($old_quiz_id, 'quiz_pro_id', true);

			if ($question_id) {
				global $wpdb;
				$results = $wpdb->get_results("SELECT id, title, question, points, answer_type, answer_data FROM {$wpdb->prefix}wp_pro_quiz_question where quiz_id = {$question_id}", ARRAY_A);

				foreach ($results as $result) {
					$question = array();
					$question['quiz_id'] = $new_quiz_id;
					$question['question_title'] = $result['title'];
					$question['question_description'] = (string) $result['question'];
					$question['question_mark'] = $result['points'];
					switch ($result['answer_type']) {
						case 'single':
							$question['question_type'] = 'single_choice';
							break;

						case 'multiple':
							$question['question_type'] = 'multiple_choice';
							break;

						default:
							# code...
							break;
					}

					$question['question_settings'] = maybe_serialize(array(
						'question_type' => $result['answer_type'],
						'question_mark' => $result['points']
					));

					$wpdb->insert($wpdb->prefix.'tutor_quiz_questions', $question);

					// Will Return $questions
					$question_id = $wpdb->insert_id;
					if ($question_id) {
						foreach ((array)maybe_unserialize($result['answer_data']) as $key => $value) {
							$i = 0;
							$answer = array();
							foreach ((array)$value as $k => $val) {
								if ($i == 0) {
									$answer['answer_title'] = $val;
								} elseif ($i == 2) {
									$answer['is_correct'] = $val ? 0 : 1;
								} elseif ($i == 3) {
									$answer['belongs_question_id'] = $question_id;
									$answer['belongs_question_type'] = $question['question_type'];
									$answer['answer_view_format'] = 'text';
									$answer['answer_order'] = $i;
									$answer['image_id'] = 0;
								}
								$i++;
							}
							$wpdb->insert($wpdb->prefix.'tutor_quiz_question_answers', $answer);
						}
					}
				}
			}
		}





		public function migrate_course($course_id, $new_course_id)
		{
			global $wpdb;
			$section_heading = get_post_meta($course_id, 'course_sections', true);
			$section_heading = $section_heading ? json_decode($section_heading, true) : array(array('order' => 0, 'post_title' => 'Tutor Topics'));

			$total_data = LDLMS_Factory_Post::course_steps($course_id);
			$total_data = $total_data->get_steps();

			if (empty($total_data)) {
				return;
			}

			$lesson_post_type = tutor()->lesson_post_type;

			$i = 0;
			$section_count = 0;
			$topic_id = 0;

			foreach ($total_data['sfwd-lessons'] as $lesson_key => $lesson_data) {
				$author_id = get_post_field('post_author', $course_id);

				// Topic Section
				$check = $i == 0 ? 0 : $i+1;
				if ($section_heading[$section_count]['order'] == $check) {
					// Insert Topics
					$topic_id = $this->insert_post('topics', $section_heading[$section_count]['post_title'], '', $author_id, $i, $new_course_id);
					$section_count++;
				}


				if ($topic_id) {

					// Insert Lesson
					$post_data = get_post($lesson_key);
					$lesson_id = $this->insert_post($lesson_post_type, $post_data->post_title, $post_data->post_content, $author_id, $i, $topic_id);
					update_post_meta($lesson_id, '_tutor_course_id_for_lesson', $course_id);

					// sfwd-topic to lesson
					foreach ($lesson_data['sfwd-topic'] as $lesson_inner_key => $lesson_inner) {

						// Insert Lesson
						$post_data = get_post($lesson_inner_key);
						$lesson_id = $this->insert_post($lesson_post_type, $post_data->post_title, $post_data->post_content, $author_id, $i, $topic_id);
						update_post_meta($lesson_id, '_tutor_course_id_for_lesson', $course_id);

						foreach ($lesson_inner['sfwd-quiz'] as $quiz_key => $quiz_data) {
							$post_data = get_post($quiz_key);
							$quiz_id = $this->insert_post('tutor_quiz', $post_data->post_title, $post_data->post_content, $author_id, $i, $topic_id);

							if ($quiz_id) {
								$this->migrate_quiz($quiz_id, $post_data->ID);
							}
						}
					}

					foreach ($lesson_data['sfwd-quiz'] as $quiz_key => $quiz_data) {
						$post_data = get_post($quiz_key);
						$quiz_id = $this->insert_post('tutor_quiz', $post_data->post_title, $post_data->post_content, $author_id, $i, $topic_id);

						if ($quiz_id) {
							$this->migrate_quiz($quiz_id, $post_data->ID);
						}
					}
				}
				$i++;
			}


			if (!empty($total_data['sfwd-quiz'])) {
				foreach ($total_data['sfwd-quiz'] as $quiz_key => $quiz_data) {
					$post_data = get_post($quiz_key);
					$quiz_id = $this->insert_post('tutor_quiz', $post_data->post_title, $post_data->post_content, $author_id, $i, $topic_id);

					if ($quiz_id) {
						$this->migrate_quiz($quiz_id, $post_data->ID);
					}
				}
			}
		}
	}
}