<?php
/**
 * PostMeta Factory
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\PostMeta;

use Tutor\Helpers\QueryHelper;
use Themeum\TutorLMSMigrationTool\Interfaces\PostMeta;

/**
 * Handle quiz meta migration
 */
class QuizMeta implements PostMeta {

	/**
	 * Current post id
	 *
	 * @since 2.3.0
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Migrate quiz meta
	 *
	 * Migrate meta that are associated with the give post id.
	 *
	 * @since 2.3.0
	 *
	 * @param integer $post_id Post id.
	 *
	 * @throws \Throwable If Database error occur.
	 *
	 * @return void
	 */
	public function migrate( int $post_id ) {
		global $wpdb;

		$this->post_id = $post_id;

		$migrate_able_meta = $this->get_migrate_able_meta();

		/*
		 * Always write tutor_quiz_option. Empty LearnDash quizzes often only have
		 * keys like sfwd-quiz_quiz_pro / sfwd-quiz_course, so migrate-able settings
		 * are empty — without defaults Tutor fatals when opening the quiz.
		 */
		$meta = $this->ld_to_tutor_meta_map( $migrate_able_meta );
		if ( is_array( $meta ) && count( $meta ) ) {
			if ( ! tlmt_is_multi_dim_arr( $meta ) ) {
				$meta = array( $meta );
			}

			try {
				QueryHelper::insert_multiple_rows( $wpdb->postmeta, $meta, false, false );
			} catch ( \Throwable $th ) {
				throw $th;
			}
		}
	}

	/**
	 * Get all the migrate-able meta from the given post id
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	private function get_migrate_able_meta(): array {
		$all_meta = get_post_meta( $this->post_id, '_sfwd-quiz', true );
		if ( ! empty( $all_meta ) ) {
			$migrate_able_meta = $this->migrate_able_meta();

			return array_intersect_key( $all_meta, array_flip( $migrate_able_meta ) );
		}

		return array();
	}

	/**
	 * Get all the meta that is migrate-able
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	private function migrate_able_meta() {
		return array(
			'sfwd-quiz_passingpercentage',
			'sfwd-quiz_certificate',
			'sfwd-quiz_threshold',
			'sfwd-quiz_repeats',
			'sfwd-quiz_autostart',
			'sfwd-quiz_hideResultQuizTime',
			'sfwd-quiz_hideQuestionPositionOverview',
			'sfwd-quiz_custom_sorting',
			'sfwd-quiz_quizModus_multiple_questionsPerPage',
			'sfwd-quiz_quizModus_single_feedback',
			'sfwd-quiz_quiz_time_limit_enabled',
			'sfwd-quiz_timeLimit',
			'sfwd-quiz_lesson_schedule',
			'sfwd-quiz_visible_after_specific_date',
			'sfwd-quiz_visible_after',
		);
	}

	/**
	 * Map all ld meta to tutor meta for migration
	 *
	 * @since 2.3.0
	 *
	 * @param array $meta Mapped meta, ready to migrate.
	 *
	 * @return array Multi-dimension array with meta key and value.
	 */
	private function ld_to_tutor_meta_map( array $meta ): array {
		$tutor_quiz_settings = array(
			'passing_grade'                 => isset( $meta['sfwd-quiz_passingpercentage'] ) ? (int) $meta['sfwd-quiz_passingpercentage'] : 80,
			'pass_is_required'              => ! empty( $meta['sfwd-quiz_certificate'] ) || ! empty( $meta['sfwd-quiz_threshold'] ) ? 1 : 0,
			'attempts_allowed'              => isset( $meta['sfwd-quiz_repeats'] ) ? (int) $meta['sfwd-quiz_repeats'] : 0,
			'quiz_auto_start'               => ! empty( $meta['sfwd-quiz_autostart'] ) ? 1 : 0,
			'hide_quiz_time_display'        => ! empty( $meta['sfwd-quiz_hideResultQuizTime'] ) ? 1 : 0,
			'hide_question_number_overview' => ! empty( $meta['sfwd-quiz_hideQuestionPositionOverview'] ) ? 1 : 0,
			'questions_order'               => ! empty( $meta['sfwd-quiz_custom_sorting'] ) ? 'sorting' : 'rand',
			'question_layout_view'          => ! empty( $meta['sfwd-quiz_quizModus_multiple_questionsPerPage'] ) ? 'question_below_each_other' : 'question_pagination',
			'time_limit'                    => ! empty( $meta['sfwd-quiz_quiz_time_limit_enabled'] ) && ! empty( $meta['sfwd-quiz_timeLimit'] ) ? array(
				'time_type'          => 'minutes',
				'time_value'         => tlmt_get_minute_by_timestamp( $meta['sfwd-quiz_timeLimit'] ),
				'time_limit_seconds' => $meta['sfwd-quiz_timeLimit'],
			) : array(
				'time_type'          => 'minutes',
				'time_value'         => 0,
				'time_limit_seconds' => 0,
			),
		);

		$feedback_mode = array(
			'feedback_mode' => 'default',
		);

		if ( isset( $meta['sfwd-quiz_retry_restrictions'] ) && 'on' === $meta['sfwd-quiz_retry_restrictions'] ) {
			$feedback_mode['feedback_mode']    = 'retry';
			$feedback_mode['attempts_allowed'] = (int) isset( $meta['sfwd-quiz_repeats'] ) ? $meta['sfwd-quiz_repeats'] : 10;
		}

		$drip_settings = array();
		if ( tlmt_has_tutor_pro() && ! empty( $meta['sfwd-quiz_lesson_schedule'] ) ) {
			if ( 'visible_after_specific_date' === $meta['sfwd-quiz_lesson_schedule'] && $meta['sfwd-quiz_visible_after_specific_date'] > 0 ) {
				$drip_settings['unlock_date'] = gmdate( 'Y-m-d', $meta['sfwd-quiz_visible_after_specific_date'] );
			} elseif ( 'visible_after' === $meta['sfwd-quiz_lesson_schedule'] && $meta['sfwd-quiz_visible_after'] > 0 ) {
				$drip_settings['after_xdays_of_enroll'] = (int) $meta['sfwd-quiz_visible_after'];
			}

			$tutor_quiz_settings['content_drip_settings'] = $drip_settings;
		}

		// Prepare to post meta to make it insert-able.
		$prepared_meta = array(
			array(
				'post_id'    => $this->post_id,
				'meta_key'   => 'tutor_quiz_option',
				'meta_value' => maybe_serialize( $tutor_quiz_settings ),
			),
		);

		if ( ! empty( $drip_settings ) ) {
			$prepared_meta[] = array(
				'post_id'    => $this->post_id,
				'meta_key'   => '_content_drip_settings',
				'meta_value' => maybe_serialize( $drip_settings ),
			);
		}

		return $prepared_meta;
	}
}
