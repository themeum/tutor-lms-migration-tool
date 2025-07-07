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
use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\Interfaces\StudentProgress as StudentProgressInterface;


/**
 * Handle student progress migration
 */
class StudentProgress implements StudentProgressInterface {

	const TOPIC                               = 'topic';
	const LESSON                              = 'lesson';
	const QUIZ                                = 'quiz';
	const ATTEMPT_ENDED                       = 'attempt_ended';
	const LD_CLOZE_ANSWER                     = 'cloze_answer';
	const LD_SINGLE_CHOICE                    = 'single';
	const LD_MULTIPLE_CHOICE                  = 'multiple';
	const LD_FREE_CHOICE                      = 'free_answer';
	const LD_SORT_ANSWER                      = 'sort_answer';
	const LD_MATRIX_SORTING                   = 'matrix_sort_answer';
	const LD_ASSESSMENT                       = 'assessment_answer';
	const LD_ESSAY                            = 'essay';
	const TUTOR_QUESTION_TYPE_MULTIPLE_CHOICE = 'multiple_choice';
	const TUTOR_QUESTION_TYPE_ORDERING        = 'ordering';
	const TUTOR_QUESTION_TYPE_MATCHING        = 'matching';


	/**
	 * Register hooks
	 */
	public function __construct() {
		add_action( 'tlmt_delete_learndash_quiz_statistic', array( $this, 'delete_learndash_quiz_statistic' ), 10, 1 );
	}

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

		try {
			$ld_course_progress = $this->user_activity();

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

					case self::LESSON:
					case self::TOPIC:
						update_user_meta( $user_id, "_tutor_completed_lesson_id_{$post_id}", $completed );
						break;

					case self::QUIZ:
						$this->add_quiz_attempt_to_tutor( $progress );
						break;

					default:
						break;
				}
			}
		} catch ( \Throwable $error ) {
			throw $error;
		}
	}

	/**
	 * Fetches user activity records for LearnDash topics, lessons and quizzes.
	 *
	 * @since 2.3.0
	 *
	 * @throws \Exception If there is a database error during query execution.
	 *
	 * @return array List of activity result objects.
	 */
	private function user_activity() {

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
					( activity_type = %s AND activity_status = %d )
					OR
					( activity_type = %s AND activity_status IN (%d, %d))",
				self::TOPIC,
				1,
				self::LESSON,
				1,
				self::QUIZ,
				1,
				0
			)
		);
		// phpcs:enable

		if ( $wpdb->last_error ) {
			ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $wpdb->last_error );
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
		$results = $wpdb->get_results(
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
			ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $wpdb->last_error );
		}

		// Convert to activity meta key => activity meta value associative array.
		$meta = array();
		foreach ( $results as $row ) {
			$meta[ $row->activity_meta_key ] = $row->activity_meta_value;
		}

		return $meta;
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
	 * @return array The ID of the inserted quiz attempt and the statistic reference ID.
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
			ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $wpdb->last_error );
		}

		// If Statistics option in Quiz settings is disable then statistic_ref_id will be 0.
		return (int) $activity_meta['statistic_ref_id'] ? array( $wpdb->insert_id, $activity_meta ) : array();
	}

	/**
	 * Inserts individual quiz attempt answers into the Tutor LMS answers table.
	 *
	 *  @since 2.3.0
	 *
	 * @param int   $quiz_attempt_id The ID of the Tutor LMS `tutor_quiz_attempts` table.
	 * @param array $user_activity_meta The user activity metadata.
	 *
	 * @throws \Exception If the bulk insert fails or database error occurs.
	 *
	 * @return void
	 */
	private function insert_quiz_attempt_answers( $quiz_attempt_id, $user_activity_meta ) {

		global $wpdb;

		$data = array();

		$question_ids         = get_post_meta( intval( $user_activity_meta['quiz'] ), 'tutor_migrated_question_answer_map', true );
		$user_quiz_statistics = $this->fetch_user_quiz_statistic( $user_activity_meta['statistic_ref_id'], $user_activity_meta['pro_quizid'] );

		foreach ( $user_quiz_statistics as $quiz_statistic ) {

			$tutor_question_id = $question_ids[ $quiz_statistic->question_id ][0]['tutor_question_id'] ?? null;
			$data[]            = array(
				'user_id'         => $quiz_statistic->user_id,
				'quiz_id'         => $quiz_statistic->quiz_post_id,
				'quiz_attempt_id' => $quiz_attempt_id,
				'given_answer'    => $quiz_statistic->statistic_answer_data ?? null,
				'question_id'     => $tutor_question_id,
				'question_mark'   => $quiz_statistic->question_points ?? 0,
				'achieved_mark'   => $quiz_statistic->points ?? 0,
				'is_correct'      => $quiz_statistic->correct_count > 0 ? 1 : 0,
			);
		}

		if ( ! empty( $data ) ) {
			$table_name = "{$wpdb->prefix}tutor_quiz_attempt_answers";
			if ( ! QueryHelper::insert_multiple_rows( $table_name, $data ) ) {
				ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $wpdb->last_error );
			}

			/**
			 * Fires an action to delete a specific LearnDash quiz statistic record.
			 *
			 * @since 2.3.0
			 *
			 * @param int $statistic_ref_id The ID of the LearnDash quiz statistic reference to be deleted.
			 *
			 * @hook delete_learndash_quiz_statistic
			 */
			do_action( 'tlmt_delete_learndash_quiz_statistic', $user_activity_meta['statistic_ref_id'] );
		}
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

		list( $quiz_attempt_id, $user_activity_meta ) = $this->insert_quiz_attempts( $progress );

		if ( $quiz_attempt_id ) {
			$this->insert_quiz_attempt_answers( $quiz_attempt_id, $user_activity_meta );
		}
	}

	/**
	 * Fetches and processes LearnDash quiz statistic data for a specific user and quiz.
	 *
	 * @since 2.3.0
	 *
	 * @param int $statistic_ref_id The LearnDash statistic reference ID.
	 * @param int $quiz_id          The ID of the LearnDash quiz.
	 *
	 * @throws \Exception If a database error occurs during query execution.
	 *
	 * @return array An array of processed quiz statistic objects.
	 */
	public function fetch_user_quiz_statistic( $statistic_ref_id, $quiz_id ) {

		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					statistic.points AS points,
					statistic.answer_data AS statistic_answer_data,
					question.id AS question_id,
					question.answer_data AS question_answer_data,
					question.answer_type,
					question.points as  question_points,
					statistic_reference.user_id,
					statistic_reference.quiz_post_id,
					statistic.correct_count
				FROM
					{$wpdb->prefix}learndash_pro_quiz_statistic_ref AS statistic_reference
			    INNER JOIN {$wpdb->prefix}learndash_pro_quiz_statistic AS statistic ON(statistic.statistic_ref_id = statistic_reference.statistic_ref_id)
			    INNER JOIN {$wpdb->prefix}learndash_pro_quiz_question AS question ON(question.id = statistic.question_id)
				WHERE
					statistic_reference.statistic_ref_id = %d		
					AND statistic_reference.quiz_id = %d",
				$statistic_ref_id,
				$quiz_id
			)
		);

		if ( $wpdb->last_error ) {
			ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $wpdb->last_error );
		}

		if ( ! empty( $result ) ) {
			array_walk(
				$result,
				function ( &$row ) {
					if ( null !== $row->statistic_answer_data ) {
						$row->statistic_answer_data = $this->format_as_tutor_answer( $row );
					}
				}
			);
		}

		return $result;
	}

	/**
	 * Formats LearnDash quiz answer data into Tutor LMS-compatible format.
	 *
	 * @since 2.3.0
	 *
	 * @param object $ld_quiz_statistic The LearnDash quiz statistic row object.
	 *
	 * @return string|null Serialized Tutor-compatible answer data or null on failure.
	 */
	private function format_as_tutor_answer( $ld_quiz_statistic ) {

		$ld_quiz_statistic->statistic_answer_data = json_decode( $ld_quiz_statistic->statistic_answer_data );

		switch ( $ld_quiz_statistic->answer_type ) {

			case self::LD_CLOZE_ANSWER:
				return maybe_serialize( $ld_quiz_statistic->statistic_answer_data );

			case self::LD_SINGLE_CHOICE:
			case self::LD_MULTIPLE_CHOICE:
				$submitted_answers = $this->get_learndash_choice_type_quiz_answers( $ld_quiz_statistic->statistic_answer_data );
				return maybe_serialize( $this->get_learndash_choice_type_quiz_answer_ids( $submitted_answers, $ld_quiz_statistic ) );

			case self::LD_FREE_CHOICE:
				return $ld_quiz_statistic->statistic_answer_data[0];

			case self::LD_SORT_ANSWER:
			case self::LD_MATRIX_SORTING:
				$submitted_answers = $this->get_learndash_sorting_type_quiz_answers( $ld_quiz_statistic );
				return maybe_serialize( $this->get_learndash_sorting_type_quiz_answer_ids( $submitted_answers, $ld_quiz_statistic ) );

			case self::LD_ASSESSMENT:
				return $this->get_learndash_assessment_quiz_answers( $ld_quiz_statistic );

			case self::LD_ESSAY:
				return $this->get_learndash_essay_quiz_answers( $ld_quiz_statistic );
		}
	}

	/**
	 * Filters submitted LearnDash quiz answers to return only selected choices. Only For multiple choice and single choice questions.
	 *
	 * @since 2.3.0
	 *
	 * @param array $statistic_answer_data The decoded answer data from LearnDash.
	 *
	 * @return array Array of selected answer keys.
	 */
	private function get_learndash_choice_type_quiz_answers( $statistic_answer_data ) {
		return array_keys(
			array_filter(
				$statistic_answer_data,
				function ( $value ) {
					return 1 === $value;
				}
			)
		);
	}

	/**
	 * Retrieves Tutor LMS answer IDs based on the submitted LearnDash answers.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $answers             Array of submitted answer keys.
	 * @param object $ld_quiz_statistic   The LearnDash quiz statistic row object.
	 *
	 * @return array|null Array of matched Tutor answer IDs, or null if none matched.
	 */
	private function get_learndash_choice_type_quiz_answer_ids( $answers, $ld_quiz_statistic ) {

		$answer_ids                    = array();
		$ld_quiz_question_answers_data = maybe_unserialize( $ld_quiz_statistic->question_answer_data ) ?? null;

		foreach ( $answers as $answer ) {

			$ld_quiz_question_submitted_ans_data = $ld_quiz_question_answers_data[ $answer ] ?? null;

			if ( ! $ld_quiz_question_submitted_ans_data instanceof \WpProQuiz_Model_AnswerTypes ) {
				continue;
			}

			$answer_data = $ld_quiz_question_submitted_ans_data->getAnswer();

			if ( empty( $answer_data ) ) {
				continue;
			}

			$answer_id = $this->get_answer_ids_from_tutor( $answer, $ld_quiz_statistic );

			if ( ! empty( $answer_id ) ) {
				$answer_ids[] = $answer_id;
			}
		}

		return ! empty( $answer_ids ) ? $answer_ids : null;
	}

	/**
	 * Finds a Tutor LMS answer ID that matches a LearnDash answer title.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $answer_key         The index key of the answer in the LearnDash statistic data.
	 * @param object $ld_quiz_statistic The LearnDash quiz statistic object containing question and answer data.
	 *
	 * @throws \Exception If a database error occurs during the query.
	 *
	 * @return int|null The matching Tutor LMS answer ID, or null if not found.
	 */
	private function get_answer_ids_from_tutor( $answer_key, $ld_quiz_statistic ) {

		$ld_quiz_statistic->question_answer_data = maybe_unserialize( $ld_quiz_statistic->question_answer_data );

		if ( empty( $ld_quiz_statistic->quiz_post_id ) || empty( $ld_quiz_statistic->question_id ) ) {
			return;
		}

		$answer_map = get_post_meta( intval( $ld_quiz_statistic->quiz_post_id ), 'tutor_migrated_question_answer_map', true );

		return $answer_map[ $ld_quiz_statistic->question_id ][ $answer_key ]['tutor_answer_id'] ?? null;
	}

	/**
	 * Prepares LearnDash sorting-type quiz answers by decoding and mapping the correct answer order.
	 *
	 * @since 2.3.0
	 *
	 * @param object $ld_quiz_statistic The LearnDash quiz statistic object containing question and answer data.
	 *
	 * @return array|null Modified statistic object as array with resolved answers, or null if no data available.
	 */
	private function get_learndash_sorting_type_quiz_answers( $ld_quiz_statistic ) {

		$ld_quiz_question_answers_data = maybe_unserialize( $ld_quiz_statistic->question_answer_data ) ?? null;

		if ( empty( $ld_quiz_question_answers_data ) ) {
			return null;
		}

		foreach ( $ld_quiz_question_answers_data as $key => $value ) {
			$expected_hash      = md5( intval( $ld_quiz_statistic->user_id ) . $ld_quiz_statistic->question_id . intval( $key ) );
			$submitted_position = array_search( $expected_hash, $ld_quiz_statistic->statistic_answer_data, true );

			if ( false !== $submitted_position ) {
				$ld_quiz_statistic->statistic_answer_data[ $submitted_position ] = $key;
			}
		}

		return (array) $ld_quiz_statistic;
	}

	/**
	 * Converts LearnDash sorting-type submitted answers into corresponding Tutor LMS answer IDs.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $submitted_answers   The submitted LearnDash answer data, typically decoded from the statistic object.
	 * @param object $ld_quiz_statistic   The LearnDash quiz statistic object providing question context.
	 *
	 * @return array| null An array of resolved Tutor LMS answer IDs or null.
	 */
	private function get_learndash_sorting_type_quiz_answer_ids( $submitted_answers, $ld_quiz_statistic ) {

		$answer_data = $submitted_answers['statistic_answer_data'] ?? null;

		if ( is_array( $answer_data ) ) {
			return array_filter(
				array_map(
					function ( $answer ) use ( $ld_quiz_statistic ) {
						return $this->get_answer_ids_from_tutor( $answer, $ld_quiz_statistic );
					},
					$answer_data
				)
			);
		}

		return null;
	}


	/**
	 * Retrieves the correct LearnDash assessment-style quiz answer based on user selection.
	 *
	 * @since 2.3.0
	 *
	 * @param object $ld_quiz_statistic The LearnDash quiz statistic object.
	 *
	 * @return string|null The correct answer value from the assessment data, or null if not found or invalid.
	 */
	private function get_learndash_assessment_quiz_answers( $ld_quiz_statistic ) {

		$question_answer_data = maybe_unserialize( $ld_quiz_statistic->question_answer_data );

		if ( is_array( $question_answer_data ) && $question_answer_data[0] instanceof \WpProQuiz_Model_AnswerTypes ) {

			$assessment_data = learndash_question_assessment_fetch_data( $question_answer_data[0]->getAnswer(), 0, $ld_quiz_statistic->question_id );

			if ( is_array( $assessment_data ) && is_array( $assessment_data['correct'] ?? null ) ) {
				$index = intval( $ld_quiz_statistic->statistic_answer_data[0] ) - 1;
				return $assessment_data['correct'][ $index ] ?? null;
			}
		}

		return null;
	}

	/**
	 * Retrieves the submitted answer for a LearnDash essay-type quiz question.
	 *
	 * @since 2.3.0
	 *
	 * @param object $ld_quiz_statistic The LearnDash quiz statistic object.
	 *
	 * @return string|null The uploaded file URL or essay content, or null if no valid submission exists.
	 */
	private function get_learndash_essay_quiz_answers( $ld_quiz_statistic ) {

		$graded_id = $ld_quiz_statistic->statistic_answer_data->graded_id ?? null;

		if ( empty( $graded_id ) ) {
			return null;
		}

		$upload = get_post_meta( $graded_id, 'upload', true );

		if ( $upload ) {
			return $upload;
		}

		return get_post( $graded_id, ARRAY_A )['post_content'] ?? null;
	}

	/**
	 * Deletes a LearnDash quiz statistic record and its associated entries.
	 *
	 * @since 2.3.0
	 *
	 * @param int $quiz_statistic_ref_id The LearnDash statistic reference ID to delete.
	 *
	 * @throws \Throwable If any database deletion operation fails.
	 *
	 * @return void
	 */
	public function delete_learndash_quiz_statistic( $quiz_statistic_ref_id ) {

		global $wpdb;

		try {
			$tables = array(
				$wpdb->prefix . 'learndash_pro_quiz_statistic_ref',
				$wpdb->prefix . 'learndash_pro_quiz_statistic',
			);

			foreach ( $tables as $table ) {
				$wpdb->delete( $table, array( 'statistic_ref_id' => $quiz_statistic_ref_id ) ); //phpcs:ignore

				if ( $wpdb->last_error ) {
					ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $wpdb->last_error );
				}
			}
		} catch ( \Throwable $error ) {
			ErrorHandler::set_error( ContentTypes::STUDENT_PROGRESS, 'Database error: ' . $error );
		}
	}
}
