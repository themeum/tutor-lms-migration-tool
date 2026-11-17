<?php
/**
 * Sensei LMS Question Migrator for Tutor LMS.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration\Quizzes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles migration of Sensei questions and answer options into Tutor LMS tables.
 */
class QuestionMigrator {

	/**
	 * Transformer for gap-fill questions.
	 *
	 * @var FillInBlanksTransformer
	 */
	private $blanks_transformer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->blanks_transformer = new FillInBlanksTransformer();
	}

	/**
	 * Migrate all questions for a given Sensei quiz to Tutor LMS.
	 *
	 * @param int $sensei_quiz_id Sensei quiz post ID.
	 * @param int $tutor_quiz_id  Tutor quiz post ID.
	 * @return int Number of questions migrated.
	 */
	public function migrate( int $sensei_quiz_id, int $tutor_quiz_id ): int {
		global $wpdb;

		if ( $sensei_quiz_id <= 0 || $tutor_quiz_id <= 0 ) {
			return 0;
		}

		$questions = $this->get_sensei_questions( $sensei_quiz_id );
		if ( empty( $questions ) ) {
			return 0;
		}

		$migrated_count = 0;
		$order          = 0;

		foreach ( $questions as $question ) {
			$question_id = (int) $question->ID;
			++$order;

			$sensei_type = $this->get_sensei_question_type( $question_id );
			$grade       = $this->get_question_grade( $question_id );

			// Map type and determine single vs multi-choice.
			$right_answers = (array) get_post_meta( $question_id, '_question_right_answer', true );
			$wrong_answers = (array) get_post_meta( $question_id, '_question_wrong_answers', true );

			$tutor_type = $this->map_question_type( $sensei_type, count( $right_answers ) );

			$question_data = array(
				'quiz_id'              => $tutor_quiz_id,
				'question_title'       => sanitize_text_field( $question->post_title ),
				'question_description' => wp_kses_post( $question->post_content ),
				'question_type'        => $tutor_type,
				'question_mark'        => $grade > 0 ? $grade : 1.00,
				'question_settings'    => maybe_serialize( array() ),
				'question_order'       => $order,
			);

			$inserted = $wpdb->insert(
				$wpdb->prefix . 'tutor_quiz_questions',
				$question_data,
				array( '%d', '%s', '%s', '%s', '%f', '%s', '%d' )
			);

			if ( ! $inserted ) {
				continue;
			}

			$tutor_question_id = (int) $wpdb->insert_id;
			$this->migrate_answers( $question, $tutor_question_id, $tutor_type, $sensei_type, $right_answers, $wrong_answers );

			++$migrated_count;
		}

		return $migrated_count;
	}

	/**
	 * Retrieve questions belonging to a Sensei quiz, respecting ordering.
	 *
	 * @param int $quiz_id Sensei quiz post ID.
	 * @return array
	 */
	private function get_sensei_questions( int $quiz_id ): array {
		$args = array(
			'post_type'      => array( 'question', 'multiple_question' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_quiz_id',
					'value' => $quiz_id,
				),
			),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		$questions = get_posts( $args );

		$order_key = '_quiz_question_order' . $quiz_id;
		usort(
			$questions,
			function ( $a, $b ) use ( $order_key ) {
				$order_a = get_post_meta( $a->ID, $order_key, true );
				$order_b = get_post_meta( $b->ID, $order_key, true );

				if ( '' !== $order_a && '' !== $order_b && (int) $order_a !== (int) $order_b ) {
					return (int) $order_a <=> (int) $order_b;
				}

				return (int) $a->menu_order <=> (int) $b->menu_order;
			}
		);

		return $questions;
	}

	/**
	 * Detect Sensei question type from taxonomy or meta.
	 *
	 * @param int $question_id Question post ID.
	 * @return string
	 */
	private function get_sensei_question_type( int $question_id ): string {
		$types = wp_get_post_terms( $question_id, 'question-type' );
		if ( ! is_wp_error( $types ) && ! empty( $types ) ) {
			return $types[0]->slug;
		}

		$meta_type = get_post_meta( $question_id, '_question_type', true );
		if ( ! empty( $meta_type ) ) {
			return (string) $meta_type;
		}

		return 'multiple-choice';
	}

	/**
	 * Retrieve the grade/points for a question.
	 *
	 * @param int $question_id Question ID.
	 * @return float
	 */
	private function get_question_grade( int $question_id ): float {
		$grade = get_post_meta( $question_id, '_question_grade', true );
		if ( is_numeric( $grade ) ) {
			return (float) $grade;
		}

		return 1.00;
	}

	/**
	 * Map Sensei question type slug to Tutor LMS question type string.
	 *
	 * @param string $sensei_type Sensei question type.
	 * @param int    $right_answer_count Count of correct answers.
	 * @return string
	 */
	private function map_question_type( string $sensei_type, int $right_answer_count = 1 ): string {
		switch ( $sensei_type ) {
			case 'boolean':
				return 'true_false';

			case 'multiple-choice':
				return $right_answer_count > 1 ? 'multiple_choice' : 'single_choice';

			case 'gap-fill':
				return 'fill_in_the_blank';

			case 'single-line':
				return 'short_answer';

			case 'multi-line':
				return 'open_ended';

			case 'file-upload':
				return 'image_answering';

			default:
				return 'single_choice';
		}
	}

	/**
	 * Migrate question answers based on mapped Tutor question type.
	 *
	 * @param \WP_Post $question          Original question post.
	 * @param int      $tutor_question_id Tutor question ID.
	 * @param string   $tutor_type        Tutor question type string.
	 * @param string   $sensei_type       Sensei question type slug.
	 * @param array    $right_answers     Right answers array.
	 * @param array    $wrong_answers     Wrong answers array.
	 * @return void
	 */
	private function migrate_answers(
		\WP_Post $question,
		int $tutor_question_id,
		string $tutor_type,
		string $sensei_type,
		array $right_answers,
		array $wrong_answers
	) {
		global $wpdb;

		$question_id = (int) $question->ID;
		$ans_order   = 0;

		switch ( $tutor_type ) {
			case 'true_false':
				$right_val = strtolower( trim( (string) get_post_meta( $question_id, '_question_right_answer', true ) ) );
				$is_true   = in_array( $right_val, array( 'true', '1', 'yes' ), true );

				$options = array(
					array(
						'title'   => __( 'True', 'tutor-lms-migration-tool' ),
						'correct' => $is_true ? 1 : 0,
					),
					array(
						'title'   => __( 'False', 'tutor-lms-migration-tool' ),
						'correct' => ! $is_true ? 1 : 0,
					),
				);

				foreach ( $options as $opt ) {
					++$ans_order;
					$wpdb->insert(
						$wpdb->prefix . 'tutor_quiz_question_answers',
						array(
							'belongs_question_id'   => $tutor_question_id,
							'belongs_question_type' => $tutor_type,
							'answer_title'          => $opt['title'],
							'is_correct'            => $opt['correct'],
							'image_id'              => 0,
							'answer_two_gap_match'  => '',
							'answer_view_format'    => 'text',
							'answer_settings'       => maybe_serialize( array() ),
							'answer_order'          => $ans_order,
						),
						array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
					);
				}
				break;

			case 'single_choice':
			case 'multiple_choice':
				foreach ( $right_answers as $ans ) {
					$ans_title = is_string( $ans ) ? $ans : ( $ans['label'] ?? '' );
					if ( '' === trim( (string) $ans_title ) ) {
						continue;
					}
					++$ans_order;
					$wpdb->insert(
						$wpdb->prefix . 'tutor_quiz_question_answers',
						array(
							'belongs_question_id'   => $tutor_question_id,
							'belongs_question_type' => $tutor_type,
							'answer_title'          => sanitize_text_field( (string) $ans_title ),
							'is_correct'            => 1,
							'image_id'              => 0,
							'answer_two_gap_match'  => '',
							'answer_view_format'    => 'text',
							'answer_settings'       => maybe_serialize( array() ),
							'answer_order'          => $ans_order,
						),
						array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
					);
				}

				foreach ( $wrong_answers as $ans ) {
					$ans_title = is_string( $ans ) ? $ans : ( $ans['label'] ?? '' );
					if ( '' === trim( (string) $ans_title ) ) {
						continue;
					}
					++$ans_order;
					$wpdb->insert(
						$wpdb->prefix . 'tutor_quiz_question_answers',
						array(
							'belongs_question_id'   => $tutor_question_id,
							'belongs_question_type' => $tutor_type,
							'answer_title'          => sanitize_text_field( (string) $ans_title ),
							'is_correct'            => 0,
							'image_id'              => 0,
							'answer_two_gap_match'  => '',
							'answer_view_format'    => 'text',
							'answer_settings'       => maybe_serialize( array() ),
							'answer_order'          => $ans_order,
						),
						array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
					);
				}
				break;

			case 'fill_in_the_blank':
				$right_val   = (string) get_post_meta( $question_id, '_question_right_answer', true );
				$text_source = $question->post_content ? $question->post_content : $question->post_title;
				$transformed = $this->blanks_transformer->transform( $right_val, $text_source );

				$wpdb->insert(
					$wpdb->prefix . 'tutor_quiz_question_answers',
					array(
						'belongs_question_id'   => $tutor_question_id,
						'belongs_question_type' => $tutor_type,
						'answer_title'          => sanitize_text_field( $transformed['answer_title'] ),
						'is_correct'            => 1,
						'image_id'              => 0,
						'answer_two_gap_match'  => sanitize_text_field( $transformed['answer_two_gap_match'] ),
						'answer_view_format'    => 'text',
						'answer_settings'       => maybe_serialize( array() ),
						'answer_order'          => 1,
					),
					array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
				);
				break;

			case 'short_answer':
			case 'open_ended':
			case 'image_answering':
			default:
				$right_val = (string) get_post_meta( $question_id, '_question_right_answer', true );
				$wpdb->insert(
					$wpdb->prefix . 'tutor_quiz_question_answers',
					array(
						'belongs_question_id'   => $tutor_question_id,
						'belongs_question_type' => $tutor_type,
						'answer_title'          => sanitize_text_field( $right_val ),
						'is_correct'            => 1,
						'image_id'              => 0,
						'answer_two_gap_match'  => '',
						'answer_view_format'    => 'text',
						'answer_settings'       => maybe_serialize( array() ),
						'answer_order'          => 1,
					),
					array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
				);
				break;
		}
	}
}
