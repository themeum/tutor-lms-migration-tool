<?php
/**
 * Student Progress Migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration;

use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;


/**
 * Handle student progress migration
 */
class StudentProgress implements StudentProgressInterface {

	const TOPIC  = 'topic';
	const LESSON = 'lesson';
	const QUIZ   = 'quiz';

	const ATTEMPT_ENDED = 'attempt_ended';

	/**
	 * Allowed activity types for migration
	 *
	 * @since 2.3.0
	 */
	const ALLOWED_ACTIVITY_TYPES = array(
		self::LESSON,
		self::TOPIC,
		self::QUIZ,
	);

	public function migrate() {

		$ld_course_progress = $this->get_completed_activity_status();

		foreach ( $ld_course_progress as $progress ) {
			$user_id   = $progress->user_id ?? null;
			$course_id = $progress->course_id ?? null;
			$post_id   = $progress->post_id ?? null;
			$type      = $progress->activity_type ?? null;
			$completed = $progress->activity_completed ?? null;

			if ( ! $user_id || ! $course_id || ! tutils()->is_enrolled( $course_id, $user_id ) ) {
				continue;
			}

			if ( self::TOPIC === $type ) {
				update_user_meta( $user_id, "_tutor_completed_lesson_id_{$post_id}", $completed );
			}

			if ( self::QUIZ === $type ) {
				$quiz_attempt_id = $this->insert_quiz_attempts( $progress );
				$this->insert_quiz_attempt_answers( $quiz_attempt_id, $progress );
			}
		}
	}

	private function get_completed_activity_status() {
		global $wpdb;

		$activity_types = "'" . implode( "', '", self::ALLOWED_ACTIVITY_TYPES ) . "'";

        // phpcs:disable
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    * 
                FROM {$wpdb->prefix}learndash_user_activity 
                WHERE activity_type IN ( $activity_types ) 
                AND activity_status = %d",
				1
			)
		);
        // phpcs:enable

		if ( $wpdb->last_error ) {
			throw new \Exception( 'Database error: ' . $wpdb->last_error ); //phpcs:ignore
		}

		return $result;
	}

	private function get_user_activity_meta( $activity_id ) {
		global $wpdb;

        // phpcs:disable
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}learndash_user_activity_meta WHERE activity_id = %d",
                $activity_id
            )
        );
        // phpcs:enable

		if ( $wpdb->last_error ) {
            throw new \Exception( 'Database error: ' . $wpdb->last_error ); //phpcs:ignore
		}

		return $result;
	}

	private function insert_quiz_attempts( $progress_info ) {

		global $wpdb;
		$activity_meta = $this->get_user_activity_meta( $progress_info->activity_id );

		$attempt_data = array(
			'course_id'                => $progress_info->course_id,
			'quiz_id'                  => $progress_info->post_id,
			'user_id'                  => $progress_info->user_id,
			'total_questions'          => $activity_meta['question_show_count'] ?? 0,
			'total_answered_questions' => $activity_meta['question_show_count'] ?? 0,
			'total_marks'              => $activity_meta['total_points'] ?? 0,
			'earned_marks'             => $activity_meta['score'] ?? 0,
			'attempt_info'             => get_post_meta( $progress_info->post_id, 'tutor_quiz_option', true ),
			'attempt_status'           => self::ATTEMPT_ENDED,
			'attempt_started_at'       => wp_date( 'Y-m-d H:i:s', $progress_info->activity_started ),
			'attempt_ended_at'         => wp_date( 'Y-m-d H:i:s', $progress_info->activity_completed ),
		);

		$inserted = $wpdb->insert( "{$wpdb->prefix}tutor_quiz_attempts", $attempt_data );

		if ( ! $inserted ) {
			throw new \Exception( 'Database insert failed: ' . $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}

	private function insert_quiz_attempt_answers( $quiz_attempt_id, $progress_info ) {

		global $wpdb;

		$question_ids = get_post_meta( $progress_info->post_id, 'ld_quiz_questions' );

		foreach ( $question_ids as $question_id ) {
			$data = array(
				'user_id'         => $progress_info->user_id,
				'quiz_id'         => $progress_info->post_id,
				'quiz_attempt_id' => $quiz_attempt_id,
				'question_id'     => $question_id,
				'question_marks'  => get_post_meta( $question_id, 'question_points', true ) ?: 0,
				'achieved_marks' => 0,
			);
		}
	}
}
