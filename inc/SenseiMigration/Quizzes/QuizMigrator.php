<?php
/**
 * Sensei LMS Quiz Migrator for Tutor LMS.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration\Quizzes;

use Themeum\TutorLMSMigrationTool\MigrationTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles migration of Sensei quizzes into Tutor LMS 'tutor_quiz' posts.
 */
class QuizMigrator {

	/**
	 * Question migrator instance.
	 *
	 * @var QuestionMigrator
	 */
	private $question_migrator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->question_migrator = new QuestionMigrator();
	}

	/**
	 * Migrate a Sensei quiz to Tutor LMS.
	 *
	 * @param int $sensei_quiz_id Sensei quiz post ID.
	 * @param int $topic_id       Tutor topic ID to parent the quiz under.
	 * @param int $course_id      Tutor course ID.
	 * @param int $lesson_id      Sensei lesson ID (optional, 0 if course quiz).
	 * @param int $order          Menu order for the quiz.
	 * @return int Tutor quiz ID, or 0 on failure.
	 */
	public function migrate( int $sensei_quiz_id, int $topic_id, int $course_id, int $lesson_id = 0, int $order = 0 ): int {
		global $wpdb;

		if ( $sensei_quiz_id <= 0 || $topic_id <= 0 || $course_id <= 0 ) {
			return 0;
		}

		$quiz_post = get_post( $sensei_quiz_id );
		if ( ! $quiz_post ) {
			return 0;
		}

		$passmark = get_post_meta( $sensei_quiz_id, '_quiz_passmark', true );
		if ( '' === $passmark || false === $passmark ) {
			$passmark = get_post_meta( $sensei_quiz_id, '_pass_percentage', true );
		}
		$enable_reset         = get_post_meta( $sensei_quiz_id, '_enable_quiz_reset', true );
		$random_order         = get_post_meta( $sensei_quiz_id, '_random_question_order', true );
		$time_limit           = get_post_meta( $sensei_quiz_id, '_quiz_time_limit', true );
		$show_correct_answers = get_post_meta( $sensei_quiz_id, '_failed_show_correct_answers', true );

		$allow_retakes  = ! empty( $enable_reset ) && '0' !== (string) $enable_reset;
		$reveal_answers = 'yes' === $show_correct_answers;

		$quiz_settings = array(
			'time_limit'                    => array(
				'time_type'  => 'minutes',
				'time_value' => is_numeric( $time_limit ) ? (int) $time_limit : 0,
			),
			'feedback_mode'                 => $reveal_answers ? 'reveal' : 'default',
			'retry_mode'                    => $allow_retakes ? 'unlimited' : 'default',
			'attempts_allowed'              => $allow_retakes ? 0 : 1,
			'passing_grade'                 => is_numeric( $passmark ) && (int) $passmark > 0 ? (int) $passmark : 80,
			'max_questions_for_answer'      => 0,
			'questions_order'               => ! empty( $random_order ) && '0' !== (string) $random_order ? 'rand' : 'sorting',
			'question_layout_view'          => 'question_pagination',
			'hide_question_number_overview' => 0,
			'short_answer_characters_limit' => 0,
			'open_ended_characters_limit'   => 0,
		);

		$quiz_post_type = tutor()->quiz_post_type; // 'tutor_quiz'

		$wpdb->update(
			$wpdb->posts,
			array(
				'post_type'   => $quiz_post_type,
				'post_status' => 'publish',
				'post_parent' => $topic_id,
				'menu_order'  => $order,
			),
			array( 'ID' => $sensei_quiz_id ),
			array( '%s', '%s', '%d', '%d' ),
			array( '%d' )
		);

		clean_post_cache( $sensei_quiz_id );

		update_post_meta( $sensei_quiz_id, 'tutor_quiz_option', $quiz_settings );
		update_post_meta( $sensei_quiz_id, '_tutor_course_id_for_quiz', $course_id );

		if ( $lesson_id > 0 ) {
			update_post_meta( $sensei_quiz_id, '_tutor_lesson_id_for_quiz', $lesson_id );
		}

		update_post_meta( $sensei_quiz_id, '_was_sensei_quiz', true );

		// Migrate questions & answer options.
		$this->question_migrator->migrate( $sensei_quiz_id, $sensei_quiz_id );

		// Fire hook.
		do_action( 'tlmt_quiz_migrated', $sensei_quiz_id, MigrationTypes::SENSEI_TO_TUTOR );

		return $sensei_quiz_id;
	}
}
