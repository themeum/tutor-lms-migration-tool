<?php
if ( ! defined( 'ABSPATH' ) )
    exit;

    
if (! class_exists('LDtoTutorExport')) {
    
    class LDtoTutorExport
    {


        public function __construct() {
            // add_action('tutor_action_tutor_import_from_xml', array($this, 'tutor_import_from_xml'));
            add_action('tutor_action_tutor_ld_export_xml', array($this, 'tutor_ld_export_xml'));
            // add_action('init', array($this, 'generate_xml_data'));
        }


        public function tutor_ld_export_xml(){
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=LearnDash_Data_for_Tutor.xml');
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
				<language>languagess</language>
				<tlmt_version><?php echo TLMT_VERSION; ?></tlmt_version>
				<?php
            $xml .= ob_get_clean();

            global $wpdb;
            $ld_courses = $wpdb->get_results("SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_status FROM {$wpdb->posts} WHERE post_type = 'sfwd-courses' AND post_status = 'publish';");

            $course_type = tutor()->course_post_type;

            if (tutils()->count($ld_courses)) {
                $i = 0;
                foreach ($ld_courses as $ld_course) {
                    $course_id = $ld_course->ID;
                    $author_id = get_post_field('post_author', $course_id);

                    $section_heading = get_post_meta($course_id, 'course_sections', true);
                    $section_heading = $section_heading ? json_decode($section_heading, true) : array(array('order' => 0, 'post_title' => 'Tutor Topics'));


                    $xml .= $this->start_element('courses');
                    $course_arr = (array) $ld_course;
                    if ($course_id) {
                        foreach ($course_arr as $course_col => $course_col_value) {
                            $xml .= "<{$course_col}>{$course_col_value}</{$course_col}>\n";
                        }

                        $total_data = LDLMS_Factory_Post::course_steps($course_id);
                        $total_data = $total_data->get_steps();
        
                        if (empty($total_data)) {
                            return;
                        }

                        $lesson_post_type = tutor()->lesson_post_type;

                        $i = 0;
                        $section_count = 0;
                        $topic_id = 0;
                        $xml_inner = array();
                        $final = array();

                        foreach ($total_data['sfwd-lessons'] as $lesson_key => $lesson_data) {

                            $check = $i == 0 ? 0 : $i+1;
                            if ($section_heading[$section_count]['order'] == $check) {
                                $xml_inner[] = 'topics';
                                $section_count++;
                            }
                            $i++;

                            $ld_lessons = get_post($lesson_key);
                            $xml_lesson = $this->start_element('items');
                                $xml_lesson .= "<item_id>{$ld_lessons->ID}</item_id>\n";
								$xml_lesson .= "<post_type>{$lesson_post_type}</post_type>\n";
								$xml_lesson .= "<post_author>{$ld_lessons->post_author}</post_author>\n";
								$xml_lesson .= "<post_date>{$ld_lessons->post_date}</post_date>\n";
								$xml_lesson .= "<post_title>{$ld_lessons->post_title}</post_title>\n";
								$xml_lesson .= "<post_content>{$this->xml_cdata($ld_lessons->post_content)}</post_content>\n";
								$xml_lesson .= "<post_parent>{$course_id}</post_parent>\n";
                            $xml_lesson .= $this->close_element('items');
                            $xml_inner[] = $xml_lesson;

                            
                            // Item
                            foreach ($lesson_data['sfwd-topic'] as $lesson_inner_key => $lesson_inner) {
                                $ld_lessons = get_post($lesson_inner_key);
                                $xml_lesson = $this->start_element('items');
                                    $xml_lesson .= "<item_id>{$ld_lessons->ID}</item_id>\n";
                                    $xml_lesson .= "<post_type>{$lesson_post_type}</post_type>\n";
                                    $xml_lesson .= "<post_author>{$ld_lessons->post_author}</post_author>\n";
                                    $xml_lesson .= "<post_date>{$ld_lessons->post_date}</post_date>\n";
                                    $xml_lesson .= "<post_title>{$ld_lessons->post_title}</post_title>\n";
                                    $xml_lesson .= "<post_content>{$this->xml_cdata($ld_lessons->post_content)}</post_content>\n";
                                    $xml_lesson .= "<post_parent>{$course_id}</post_parent>\n";
                                $xml_lesson .= $this->close_element('items');
                                $xml_inner[] = $xml_lesson;

                                foreach ($lesson_inner['sfwd-quiz'] as $quiz_key => $quiz_data) {
                                    $quiz = get_post($quiz_key);
                                    $xml_quiz = $this->start_element('items');
                                        $xml_quiz .= "<item_id>{$quiz->ID}</item_id>\n";
                                        $xml_quiz .= "<post_type>tutor_quiz</post_type>\n";
                                        $xml_quiz .= "<post_author>{$quiz->post_author}</post_author>\n";
                                        $xml_quiz .= "<post_date>{$quiz->post_date}</post_date>\n";
                                        $xml_quiz .= "<post_title>{$quiz->post_title}</post_title>\n";
                                        $xml_quiz .= "<post_content>{$this->xml_cdata($quiz->post_content)}</post_content>\n";
                                        $xml_quiz .= "<post_parent>{$course_id}</post_parent>\n";

                                        $xml_quiz .= $this->migrate_quiz($quiz->ID);

                                    $xml_quiz .= $this->close_element('items');
                                    $xml_inner[] = $xml_quiz;
                                }
                            }

                            foreach ($lesson_data['sfwd-quiz'] as $quiz_key => $quiz_data) {
                                $quiz = get_post($quiz_key);
                                $xml_quiz = $this->start_element('items');
                                    $xml_quiz .= "<item_id>{$quiz->ID}</item_id>\n";
                                    $xml_quiz .= "<post_type>tutor_quiz</post_type>\n";
                                    $xml_quiz .= "<post_author>{$quiz->post_author}</post_author>\n";
                                    $xml_quiz .= "<post_date>{$quiz->post_date}</post_date>\n";
                                    $xml_quiz .= "<post_title>{$quiz->post_title}</post_title>\n";
                                    $xml_quiz .= "<post_content>{$this->xml_cdata($quiz->post_content)}</post_content>\n";
                                    $xml_quiz .= "<post_parent>{$course_id}</post_parent>\n";

                                    $xml_quiz .= $this->migrate_quiz($quiz->ID);

                                $xml_quiz .= $this->close_element('items');
                                $xml_inner[] = $xml_quiz;
                            }
                        }

                        $heading = '';
                        $temp = '';
                        $j = 0;
                        for ($i = 0; $i < count($xml_inner) ; $i++) {

                            if ($xml_inner[$i] == 'topics' && $heading == '') {
                                $xml .= $this->start_element('topics');
                                $xml .= "<post_type>topics</post_type>\n";
                                $topics_title = $section_heading[$j]['post_title'];
                                $xml .= "<post_title>{$topics_title}</post_title>\n";
                                $xml .= "<post_content></post_content>\n";
                                $xml .= "<post_status>publish</post_status>\n";
                                $xml .= "<post_author>{$author_id}</post_author>\n";
                                $xml .= "<post_parent>{$course_id}</post_parent>";
                                $xml .= "<menu_order>{$i}</menu_order>\n";
                                $j++;
                            } else if ($xml_inner[$i] == 'topics') {
                                $xml .= $heading;
                                $xml .= $this->close_element('topics');
                                
                                $xml .= $this->start_element('topics');
                                $xml .= "<post_type>topics</post_type>\n";
                                $topics_title = $section_heading[$j]['post_title'];
                                $xml .= "<post_title>{$topics_title}</post_title>\n";
                                $xml .= "<post_content></post_content>\n";
                                $xml .= "<post_status>publish</post_status>\n";
                                $xml .= "<post_author>{$author_id}</post_author>\n";
                                $xml .= "<post_parent>{$course_id}</post_parent>";
                                $xml .= "<menu_order>{$i}</menu_order>\n";
                                $j++;

                                $heading = '';
                            } else if ($i == (count($xml_inner)-1)) {
                                $xml .= $heading.$xml_inner[$i];
                                $xml .= $this->close_element('topics');
                            }

                            if ($xml_inner[$i] != 'topics') {
                                $heading .= $xml_inner[$i];
                            }
                        }
                    }
                    $xml .= $this->close_element('courses');
                }
            }

            
            $xml .= $this->close_element('channel');
            
            // echo '<code>';
            // print_r($xml);
            // echo '</code>';

            return $xml;
        }


        public function migrate_quiz($old_quiz_id)
        {
            $xml = '';
            $question_id = get_post_meta($old_quiz_id, 'quiz_pro_id', true);

            if ($question_id) {
                global $wpdb;
                $results = $wpdb->get_results("SELECT id, title, question, points, answer_type, answer_data FROM {$wpdb->prefix}wp_pro_quiz_question where quiz_id = {$question_id}", ARRAY_A);
                
                foreach ($results as $result) {
                    $question = array();
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
                    if (isset($question['question_type'])) {

                        $question['quiz_id'] = '{quiz_id}';
                        $question['question_title'] = $result['title'];
                        $question['question_description'] = (string) $result['question'];
                        $question['question_mark'] = $result['points'];
                        $question['question_settings'] = maybe_serialize(array(
                            'question_type' => $result['answer_type'],
                            'question_mark' => $result['points']
                        ));

                        $xml .= $this->start_element('questions');
                        foreach ($question as $question_key => $question_value) {
                            $xml .= "<{$question_key}>{$this->xml_cdata($question_value)}</{$question_key}>\n";
                        }

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

                                if (count($answer) > 0) {
                                    $xml .= $this->start_element('answers');
                                    foreach ($answer as $answers_key => $answers_value){
                                        $xml .= "<{$answers_key}>{$this->xml_cdata($answers_value)}</{$answers_key}>\n";
                                    }
                                    $xml .= $this->close_element('answers');
                                }

                            }
                        }

                        $xml .= $this->close_element('questions');
                    }
                }
            }

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
         *
         * Import From XML
		 */
		public function tutor_import_from_xml(){
		    global $wpdb;

			if (isset($_FILES['tutor_import_file'])){
				$course_post_type = tutor()->course_post_type;

				$xmlContent = file_get_contents($_FILES['tutor_import_file']['tmp_name']);
				$xmlContent = str_replace(array( '<![CDATA[', ']]>'),'', $xmlContent);

				$xml_data = simplexml_load_string($xmlContent);
				$courses = $xml_data->courses;

				foreach ($courses as $course){

					$course_data = array(
						'post_author'   => (string) $course->post_author,
						'post_date'     => (string)$course->post_date,
						'post_date_gmt' => (string) $course->post_date_gmt,
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
					    $wpdb->insert($wpdb->postmeta, array('post_id' => $course_id, 'meta_key' => $course_meta_key, 'meta_value' =>$course_meta_value));
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
			}
		}

        
    }
    new LDtoTutorExport();
}