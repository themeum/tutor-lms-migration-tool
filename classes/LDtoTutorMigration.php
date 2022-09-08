<?php
defined( 'ABSPATH' ) || exit;

    if (! class_exists('LDtoTutorMigration')) {
        class LDtoTutorMigration
        {
            public function __construct()
            {
                add_filter('tutor_tool_pages', array($this, 'ld_tool_pages'));
                add_action('wp_ajax_insert_tutor_migration_data', array($this, 'insert_tutor_migration_data'));
                add_action('wp_ajax_ld_migrate_all_data_to_tutor', array($this, 'ld_migrate_all_data_to_tutor'));
                add_action('wp_ajax_ld_reset_migrated_items_count', array($this, 'ld_reset_migrated_items_count'));
                add_action('wp_ajax__get_ld_live_progress_course_migrating_info', array($this, '_get_ld_live_progress_course_migrating_info'));
            }

            public function insert_tutor_migration_data(){
                global $wpdb;
                $tutor_migration_table_data = [
                    'migration_type' => $_POST['migration_type'],
                    'migration_vendor' => $_POST['migration_vendor'],
                    'created_by' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                ];

                $wpdb->insert(
                    $wpdb->prefix . 'tutor_migration',
                    $tutor_migration_table_data
                );
            }


            public function ld_tool_pages($pages)
            {
                // if (defined('LEARNDASH_VERSION') && !defined('LEARNPRESS_VERSION') ) {
                if (defined('LEARNDASH_VERSION') ) {
                    // $pages['migration_ld'] = array('title' =>  __('LearnDash Migration', 'tutor-lms-migration-tool'), 'view_path' => TLMT_PATH.'views/migration_ld.php');
                    $pages['migration_ld'] = array(
                        'label'    => __( 'LearnDash Migration', 'tutor' ),
                        'slug'     => 'migration_ld',
                        'desc'     => __( 'LearnDash Migration', 'tutor' ),
                        'template' => 'migration_ld',
                        'view_path'     => TLMT_PATH . 'views/',
                        'icon'     => 'tutor-icon-brand-learndash',
                        'blocks'   => array(
                            'block' => array(),
                        ),
                    );
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

                        case 'orders':
                            $this->ld_order_migrate();
                            break;
                    }
                    
                    wp_send_json_success();
                }
                wp_send_json_error();
            }


            // Order
            public function ld_order_migrate(){
                global $wpdb;

                $monetize_by = tutils()->get_option('monetize_by');

                $ld_orders = $wpdb->get_results("SELECT ID, post_author, post_date, post_content, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'sfwd-transactions' AND post_status = 'publish';");
                $item_i = (int) get_option('_tutor_migrated_items_count');

                if (tutils()->has_wc() && $monetize_by == 'wc') {

                    foreach ($ld_orders as $order) {
                        $item_i++;
                        update_option('_tutor_migrated_items_count', $item_i);

                        $migrate_order_data = array(
                            'ID'            => $order->ID,
                            'post_status'   => 'wc-completed',
                            'post_type'     => 'shop_order',
                        );
                        wp_update_post($migrate_order_data);

                        // Order Item
                        $course_id = get_post_meta($order->ID, 'course_id', true);
                        $item_data = array(
                            'order_item_name'   => get_the_title($course_id),
                            'order_item_type'   => 'line_item',
                            'order_id'          => $course_id,
                        );
                        $wpdb->insert($wpdb->prefix.'woocommerce_order_items', $item_data);
                        $order_item_id = (int) $wpdb->insert_id;

                        // Order Item Meta
                        $_ld_price = get_post_meta( $order->ID, '_sfwd-courses', true );
                        $wc_item_metas = array(
                            '_product_id'        => $order->ID,
                            '_variation_id'      => 0,
                            '_qty'               => 1,
                            '_tax_class'         => '',
                            '_line_subtotal'     => $_ld_price['sfwd-courses_course_price'] ? $_ld_price['sfwd-courses_course_price'] : 0,
                            '_line_subtotal_tax' => 0,
                            '_line_total'        => $_ld_price['sfwd-courses_course_price'] ? $_ld_price['sfwd-courses_course_price'] : 0,
                            '_line_tax'          => 0,
                            '_order_total'       => $_ld_price['sfwd-courses_course_price'] ? $_ld_price['sfwd-courses_course_price'] : 0,
                            '_line_tax_data'     => maybe_serialize( array( 'total' => array(), 'subtotal' => array() ) ),
                        );
                        foreach ($wc_item_metas as $wc_item_meta_key => $wc_item_meta_value ){
                            $wc_item_metas = array(
                                'order_item_id' => $order_item_id,
                                'meta_key'      => $wc_item_meta_key,
                                'meta_value'    => $wc_item_meta_value,
                            );
                            $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', $wc_item_metas);
                        }

                        update_post_meta($order->ID, '_customer_user', $order->post_author);
                        $user_email = $wpdb->get_var("SELECT user_email from {$wpdb->users} WHERE ID = {$order->post_author} ");
                        update_post_meta($order->ID, '_billing_address_index', $user_email );
                        update_post_meta($order->ID, '_billing_email', $user_email );
                    }
                }

                if ( tutils()->has_edd() && $monetize_by == 'edd' ) {

                    foreach ($ld_orders as $order) {
                        $item_i++;
                        update_option('_tutor_migrated_items_count', $item_i);

                        $migrate_order_data = array(
                            'ID'            => $order->ID,
                            'post_status'   => 'publish',
                            'post_type'     => 'edd_payment',
                        );
                        wp_update_post($migrate_order_data);

                        $_ld_price = get_post_meta( $order->ID, '_sfwd-courses', true );
                        $user_email = $wpdb->get_var("SELECT user_email from {$wpdb->users} WHERE ID = {$order->post_author} ");
                        $meta_data = array(
                            '_edd_payment_meta' => array(),
                            '_edd_payment_gateway' => '',
                            '_edd_payment_user_id' => $order->post_author,
                            '_edd_payment_user_email' => $user_email,
                            '_edd_payment_user_ip' => '',
                            '_edd_payment_purchase_key' => '',
                            '_edd_payment_mode' => 'migration',
                            '_edd_payment_tax_rate' => 0,
                            '_edd_payment_customer_id' => $order->post_author,
                            '_edd_payment_total' => $_ld_price['sfwd-courses_course_price'] ? $_ld_price['sfwd-courses_course_price'] : 0,
                            '_edd_payment_tax' => 0,
                            '_edd_completed_date' => $order->post_date,
                        );
                        foreach ($meta_data as $key => $value) {
                            update_post_meta($order->ID, $key, $value);
                        }

                        $display_name = $wpdb->get_var("SELECT display_name from {$wpdb->users} WHERE ID = {$order->post_author} ");
                        $edd_item_metas = array(
                            'user_id' => $order->post_author,
                            'email' => $user_email,
                            'name' => $display_name,
                            'purchase_value' => $_ld_price['sfwd-courses_course_price'] ? $_ld_price['sfwd-courses_course_price'] : 0,
                            'purchase_count' => 1,
                            'notes' => '',
                            'date_created' => $order->post_date,
                        );
                        $wpdb->insert($wpdb->prefix.'edd_customers', $edd_item_metas);
                    }
                }
            }


            public function ld_migrate_course_to_tutor($return_type = false)
            {
                global $wpdb;
                $ld_courses = $wpdb->get_results("SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_status FROM {$wpdb->posts} WHERE post_type = 'sfwd-courses' AND (post_status = 'publish' OR post_status = 'draft');");

                $course_type = tutor()->course_post_type;

                if (tutils()->count($ld_courses)) {
                    $course_i = (int) get_option('_tutor_migrated_items_count');
                    $i = 0;
                    foreach ($ld_courses as $ld_course) {
                        $course_i++;
                        $course_id = $this->update_post($ld_course->ID, $course_type, 0, '');
                        if ($course_id) {
                            $this->migrate_course($ld_course->ID, $course_id);

                            update_option('_tutor_migrated_items_count', $course_i);

                            // Attached Product
                            $this->attached_product($course_id, $ld_course->post_title);

                            // Attached Prerequisite
                            $this->attached_prerequisite($course_id);

                            // Add Enrollments
                            $this->insert_enrollment($course_id);

                            // Attached thumbnail
                            $this->insert_thumbnail($ld_course->ID, $course_id);
                        }
                    }
                }
                wp_send_json_success();
            }

            public function attached_prerequisite($course_id){
                $course_data = get_post_meta($course_id, '_sfwd-courses', true);
                if( $course_data['sfwd-courses_course_prerequisite'] ) {
                    update_post_meta($course_id, '_tutor_course_prerequisites_ids', $course_data['sfwd-courses_course_prerequisite']);
                }
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
                $ld_course_complete_datas = $wpdb->get_results("SELECT * from {$wpdb->prefix}learndash_user_activity WHERE activity_type = 'course' AND activity_status = 1");

                foreach ($ld_course_complete_datas as $ld_course_complete_data){
                    $user_id = $ld_course_complete_data->user_id;
    
                    if ( ! tutils()->is_enrolled($course_id, $user_id)) {
    
                        $tutor_course_complete_data = array(
                            'comment_type'   => 'course_completed',
                            'comment_agent'   => 'TutorLMSPlugin',
                            'comment_approved'   => 'approved',
                            'comment_content'   => '76a73b96cc628c21',
                            'user_id' => $user_id,
                            'comment_author' => $user_id,
                            'comment_post_ID' => $course_id,
                        );
    
                        $isEnrolled = wp_insert_comment( $tutor_course_complete_data );
                        
                    }
                }

                $ld_enrollments = $wpdb->get_results("SELECT * from {$wpdb->prefix}learndash_user_activity WHERE activity_type = 'access'");

                foreach ($ld_enrollments as $ld_enrollment) {
                    $user_id = $ld_enrollment->user_id;

                    if (! tutils()->is_enrolled($course_id, $user_id)) {

                        $title = __('Course Enrolled', 'tutor');
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
                update_post_meta($course_id, '_tutor_course_price_type', 'free');
                $monetize_by = tutils()->get_option('monetize_by');
                if (tutils()->has_wc() && $monetize_by == 'wc') {
                    $_ld_price = get_post_meta($course_id, '_sfwd-courses', true);
                    if ($_ld_price['sfwd-courses_course_price']) {
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
                                '_sale_price'        => $_ld_price['sfwd-courses_course_price'],
                                '_price'             => $_ld_price['sfwd-courses_course_price'],
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

                // Edd Support Add
                if (tutils()->has_edd() && $monetize_by == 'edd') {
                    $_ld_price = get_post_meta($course_id, '_sfwd-courses', true);
                    if ($_ld_price['sfwd-courses_course_price']) {
                        update_post_meta($course_id, '_tutor_course_price_type', 'paid');
                        $product_id = wp_insert_post(array(
                            'post_title' => $course_title.' Product',
                            'post_content' => '',
                            'post_status' => 'publish',
                            'post_type' => "download",
                        ));
                        $product_metas = array(
                            'edd_price'             => $_ld_price['sfwd-courses_course_price'],
                            'edd_variable_prices'   => array(),
                            'edd_download_files'    => array(),
                            '_edd_bundled_products' => array('0'),
                            '_edd_bundled_products_conditions' => array('all'),
                        );
                        foreach ($product_metas as $key => $value) {
                            update_post_meta($product_id, $key, $value);
                        }
                        update_post_meta($course_id, '_tutor_course_product_id', $product_id);
                        $coursePostThumbnail = get_post_meta($course_id, '_thumbnail_id', true);
                        if ($coursePostThumbnail) {
                            set_post_thumbnail($product_id, $coursePostThumbnail);
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

            public function insert_post($post_title, $post_content, $author_id, $post_type = 'topics', $menu_order = 0, $post_parent = '')
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

            public function update_post($post_id, $post_type = 'topics',  $menu_order = 0, $post_parent = '')
            {
                global $wpdb;
                $post_arg = array(
                    'ID'            => $post_id,
                    'post_type'     => $post_type,
                    'post_parent'   => $post_parent,
                    'menu_order'    => $menu_order,
                );
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}posts SET post_type=%s, post_parent=%s, menu_order=%s WHERE ID=%s", $post_type, $post_parent, $menu_order, $post_id));
                return $post_id;
            }

            public function migrate_quiz($old_quiz_id)
            {
                global $wpdb;
                $xml = '';
                $question_ids = get_post_meta($old_quiz_id, 'ld_quiz_questions', true);
                $is_table = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", "{$wpdb->prefix}learndash_pro_quiz_question" ) );

                if (!empty($question_ids)) {
                    $question_ids = array_keys($question_ids);
                    foreach ($question_ids as $question_single) {
                        $question_id = get_post_meta($question_single, 'question_pro_id', true);

                        $result = array();
                        if ($is_table) {
                            $result = $wpdb->get_row("SELECT id, title, question, points, answer_type, answer_data FROM {$wpdb->prefix}learndash_pro_quiz_question where id = {$question_id}", ARRAY_A);
                        } else {
                            $result = $wpdb->get_row("SELECT id, title, question, points, answer_type, answer_data FROM {$wpdb->prefix}wp_pro_quiz_question where id = {$question_id}", ARRAY_A);
                        }

                        $question = array();
                        $question['quiz_id'] = $old_quiz_id;
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

                            case 'sort_answer':
                                $question['question_type'] = 'ordering';
                                break;

                            case 'essay':
                                $question['question_type'] = 'open_ended';
                                break;

                            case 'cloze_answer':
                                $question['question_type'] = 'fill_in_the_blank';
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
                                        if ($result['answer_type'] == 'cloze_answer') {
                                            $final_question = wp_strip_all_tags($val);
                                            preg_match_all('/{.*?\}/', $final_question, $matches);
                                            if (isset($matches[0])) {
                                                foreach ($matches[0] as $key => $v) {
                                                    $v = explode(']', $v);
                                                    if (isset($v[0])) {
                                                        $answer_str[] = str_replace(array('{[','{','}'), '', $v[0]);
                                                    }
                                                }
                                                $final_question = str_replace($matches[0], '{dash}', $final_question);
                                            }
                                            $answer['answer_two_gap_match'] = implode('|', $answer_str);
                                            $answer['answer_title'] = $final_question;
                                        }
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
                                $wpdb->insert( $wpdb->prefix.'tutor_quiz_question_answers', $answer );
                            }
                        }

                        if ($is_table) {
                            $wpdb->delete( $wpdb->prefix.'learndash_pro_quiz_question', array( 'id' => $result->id ) );
                        } else {
                            $wpdb->delete( $wpdb->prefix.'wp_pro_quiz_question', array( 'id' => $result->id ) );
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
                    if (isset($section_heading[$section_count]['order'])) {
                        if ($section_heading[$section_count]['order'] == $check) {
                            // Insert Topics
                            $topic_id = $this->insert_post($section_heading[$section_count]['post_title'], '', $author_id, 'topics', $i, $new_course_id);
                            $section_count++;
                        }
                    }


                    if ($topic_id) {
                        $lesson_id = $this->update_post($lesson_key, $lesson_post_type, $i, $topic_id);

                        update_post_meta($lesson_id, '_tutor_course_id_for_lesson', $course_id);

                        foreach ($lesson_data['sfwd-topic'] as $lesson_inner_key => $lesson_inner) {

                            $lesson_id = $this->update_post($lesson_inner_key, $lesson_post_type, $i, $topic_id); // Insert Lesson

                            update_post_meta($lesson_id, '_tutor_course_id_for_lesson', $course_id);

                            foreach ($lesson_inner['sfwd-quiz'] as $quiz_key => $quiz_data) {
                                $quiz_id = $this->update_post( $quiz_key, 'tutor_quiz', $i, $topic_id );

                                if ($quiz_id) {
                                    $this->migrate_quiz($quiz_id);
                                }
                            }
                        }

                        foreach ($lesson_data['sfwd-quiz'] as $quiz_key => $quiz_data) {
                            $quiz_id = $this->update_post($quiz_key, 'tutor_quiz', $i, $topic_id);
                            if ($quiz_id) {
                                $this->migrate_quiz($quiz_id);
                            }
                        }
                    }
                    $i++;
                }

                if (!empty($total_data['sfwd-quiz'])) {
                    foreach ($total_data['sfwd-quiz'] as $quiz_key => $quiz_data) {
                        $quiz_id = $this->update_post($quiz_key, 'tutor_quiz', $i, $topic_id);
                        if ($quiz_id) {
                            $this->migrate_quiz($quiz_id);
                        }
                    }
                }

            }
        }
    }
