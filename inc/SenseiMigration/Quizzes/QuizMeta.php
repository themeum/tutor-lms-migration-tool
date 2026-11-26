<?php
/**
 * Sensei LMS Quiz Meta Migration for Tutor LMS.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration\Quizzes;

use Themeum\TutorLMSMigrationTool\Interfaces\PostMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Sensei quiz meta migration.
 */
class QuizMeta implements PostMeta {

	/**
	 * Migrate quiz meta for a given quiz post.
	 *
	 * @param int $post_id Quiz post ID.
	 * @return void
	 */
	public function migrate( int $post_id ) {
		if ( $post_id <= 0 ) {
			return;
		}

		$passmark = get_post_meta( $post_id, '_quiz_passmark', true );
		if ( '' === $passmark || false === $passmark ) {
			$passmark = get_post_meta( $post_id, '_pass_percentage', true );
		}
		$enable_reset         = get_post_meta( $post_id, '_enable_quiz_reset', true );
		$random_order         = get_post_meta( $post_id, '_random_question_order', true );
		$time_limit           = get_post_meta( $post_id, '_quiz_time_limit', true );
		$show_correct_answers = get_post_meta( $post_id, '_failed_show_correct_answers', true );

		$existing_settings = get_post_meta( $post_id, 'tutor_quiz_option', true );
		if ( empty( $existing_settings ) || ! is_array( $existing_settings ) ) {
			$quiz_settings = array(
				'time_limit'                    => array(
					'time_type'  => 'minutes',
					'time_value' => is_numeric( $time_limit ) ? (int) $time_limit : 0,
				),
				'feedback_mode'                 => 'yes' === $show_correct_answers ? 'reveal' : 'default',
				'retry_mode'                    => 'default',
				'attempts_allowed'              => ! empty( $enable_reset ) ? 0 : 1,
				'passing_grade'                 => is_numeric( $passmark ) && (int) $passmark > 0 ? (int) $passmark : 80,
				'max_questions_for_answer'      => 0,
				'questions_order'               => ! empty( $random_order ) && '0' !== (string) $random_order ? 'rand' : 'sorting',
				'question_layout_view'          => 'question_pagination',
				'hide_question_number_overview' => 0,
				'short_answer_characters_limit' => 0,
				'open_ended_characters_limit'   => 0,
			);
			update_post_meta( $post_id, 'tutor_quiz_option', $quiz_settings );
		}
	}
}
