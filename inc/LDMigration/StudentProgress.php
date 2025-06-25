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

use Tutor\Helpers\QueryHelper;
use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;


/**
 * Handle student progress migration
 */
class StudentProgress implements StudentProgressInterface {

	const TOPIC         = 'topic';
	const LESSON        = 'lesson';
	const QUIZ          = 'quiz';
	const ATTEMPT_ENDED = 'attempt_ended';

	/**
	 * Migrates LearnDash course progress to Tutor LMS.
	 *
	 * @since 2.3.0
	 *
	 * - For topic activities: Marks the lesson as completed in Tutor LMS.
	 * - For quiz activities: Creates a corresponding quiz attempt and stores related answers.
	 *
	 * @return void
	 */
	public function migrate() {

		$ld_course_progress = $this->fetch_quiz_and_topic_activity();

		foreach ( $ld_course_progress as $progress ) {
			$user_id   = $progress->user_id ?? null;
			$course_id = $progress->course_id ?? null;
			$post_id   = $progress->post_id ?? null;
			$type      = $progress->activity_type ?? null;
			$completed = $progress->activity_completed ?? null;

			if ( ! $user_id || ! $course_id || ! tutils()->is_enrolled( $course_id, $user_id ) ) {
				continue;
			}

			switch ( $type ) {

				case self::TOPIC:
					update_user_meta( $user_id, "_tutor_completed_lesson_id_{$post_id}", $completed );
					break;

				case self::QUIZ:
					// @todo Need to test properly for `question_id`.
					$this->add_quiz_attempt_to_tutor( $progress );
					break;

				default:
					break;
			}
		}
	}

	/**
	 * Fetches user activity records for LearnDash topics and quizzes.
	 *
	 * @since 2.3.0
	 *
	 * @throws \Exception If there is a database error during query execution.
	 *
	 * @return array List of activity result objects.
	 */
	private function fetch_quiz_and_topic_activity() {

		global $wpdb;

        // phpcs:disable
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    * 
                FROM {$wpdb->prefix}learndash_user_activity 
                WHERE 
					( activity_type = %s AND activity_status = %d )
					OR
					( activity_type = %s AND activity_status IN (%d, %d))",
				self::TOPIC,
				1,
				self::QUIZ,
				1,
				0
			)
		);
        // phpcs:enable

		if ( $wpdb->last_error ) {
			throw new \Exception( 'Database error: ' . $wpdb->last_error ); //phpcs:ignore
		}

		return $result;
	}

	/**
	 * Retrieves metadata entries associated with a specific LearnDash user activity.
	 *
	 * @since 2.3.0
	 *
	 * @param int $activity_id The ID of the LearnDash user activity.
	 *
	 * @throws \Exception If a database error occurs during the query.
	 *
	 * @return array List of metadata result objects for the activity.
	 */
	private function get_user_activity_meta( $activity_id ) {
		global $wpdb;

        // phpcs:disable
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
					* 
				FROM 
					{$wpdb->prefix}learndash_user_activity_meta 
				WHERE 
					activity_id = %d",
                $activity_id
            )
        );
        // phpcs:enable

		if ( $wpdb->last_error ) {
            throw new \Exception( 'Database error: ' . $wpdb->last_error ); //phpcs:ignore
		}

		return $result;
	}

	/**
	 * Inserts a quiz attempt record into the Tutor LMS `tutor_quiz_attempts` table.
	 *
	 * @since 2.3.0
	 *
	 * @param object $progress_info An object containing quiz progress data.
	 *
	 * @throws \Exception If the insert operation fails or if activity meta is invalid.
	 *
	 * @return int The ID of the inserted quiz attempt.
	 */
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
			throw new \Exception( 'Database insert failed: ' . $wpdb->last_error ); //phpcs:ignore
		}

		return $wpdb->insert_id;
	}

	/**
	 * Inserts individual quiz attempt answers into the Tutor LMS answers table.
	 *
	 *  @since 2.3.0
	 *
	 * @param int    $quiz_attempt_id The ID of the Tutor LMS `tutor_quiz_attempts` table.
	 * @param object $progress_info   Object containing progress data.
	 *
	 * @throws \Exception If the bulk insert fails or database error occurs.
	 *
	 * @return void
	 */
	private function insert_quiz_attempt_answers( $quiz_attempt_id, $progress_info ) {

		global $wpdb;

		$data               = array();
		$ld_quiz_statistics = $this->get_learndash_quiz_stats( $progress_info );
		$question_ids       = get_post_meta( $progress_info->post_id, 'learndash_to_tutor_migration' );

		foreach ( $ld_quiz_statistics as $ld_quiz_statistic ) {
			foreach ( $question_ids as $question_id ) {
				$data[] = array(
					'user_id'         => $progress_info->user_id,
					'quiz_id'         => $progress_info->post_id,
					'quiz_attempt_id' => $quiz_attempt_id,
					'question_id'     => $question_id,
					'question_marks'  => get_post_meta( $ld_quiz_statistic->question_post_id, 'question_points', true ) ?? 0,
					'achieved_marks'  => $ld_quiz_statistic->points ?? 0,
					'is_correct'      => $ld_quiz_statistic->correct_count ?? 0,
				);
			}
		}

		if ( ! empty( $data ) ) {
			$table_name = "{$wpdb->prefix}tutor_quiz_attempt_answers";
			if ( ! QueryHelper::insert_multiple_rows( $table_name, $data ) ) {
				throw new \Exception( 'Database insert failed: ' . $wpdb->last_error ); //phpcs:ignore
			}
		}
	}

	/**
	 * Retrieves detailed quiz statistics from LearnDash for a specific user and quiz.
	 *
	 *  @since 2.3.0
	 *
	 * @param object $progress Object containing progress data.
	 * @throws \Exception If a database error occurs during the query.
	 *
	 * @return array List of quiz statistic result objects.
	 */
	private function get_learndash_quiz_stats( $progress ) {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
						statistic.*,
						statistic_ref.quiz_post_id
					FROM 
						{$wpdb->prefix}learndash_pro_quiz_statistic AS statistic
					LEFT JOIN 
						{$wpdb->prefix}learndash_pro_quiz_statistic_ref AS statistic_ref
					 ON 
						statistic.statistic_ref_id = statistic_ref.statistic_ref_id
					WHERE statistic_ref.user_id = %d
						 AND statistic_ref.quiz_post_id = %d
						 AND statistic_ref.course_post_id = %d",
				$progress->user_id,
				$progress->post_id,
				$progress->course_id
			)
		);

		if ( $wpdb->last_error ) {
			throw new \Exception( 'Database error: ' . $wpdb->last_error ); //phpcs:ignore
		}

		return $result;
	}

	/**
	 * Migrates a LearnDash quiz attempt to Tutor LMS.
	 *
	 * @since 2.3.0
	 *
	 * @param object $progress Object containing user quiz progress information.
	 *
	 * @return void
	 */
	private function add_quiz_attempt_to_tutor( $progress ) {

		$quiz_attempt_id = $this->insert_quiz_attempts( $progress );

		if ( $quiz_attempt_id ) {
			$this->insert_quiz_attempt_answers( $quiz_attempt_id, $progress );
		}
	}
}
