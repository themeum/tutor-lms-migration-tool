<?php
/**
 * Register hooks to take actions
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool;

use Themeum\TutorLMSMigrationTool\Factories\StudentProgressFactory;
use Themeum\TutorLMSMigrationTool\LDMigration\CourseTaxonomies;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Helper as SubscriptionHelper;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Subscriptions;
use TUTOR\Course;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action hook handler
 */
class ActionHandler {

	/**
	 * Register hooks
	 */
	public function __construct() {
		add_action( 'tlmt_course_migrated', array( $this, 'migrate_post_meta' ), 10, 2 );
		add_action( 'tlmt_course_migrated', array( $this, 'migrate_course_taxonomies' ), 15, 2 );
		add_action( 'tlmt_course_migrated', array( $this, 'migrate_subscription_plan' ), 20, 2 );
		add_action( 'tlmt_lesson_migrated', array( $this, 'migrate_post_meta' ), 10, 2 );
		add_action( 'tlmt_quiz_migrated', array( $this, 'migrate_post_meta' ), 10, 2 );
		add_action( 'tlmt_attach_product', array( $this, 'migrate_products' ), 10, 2 );
		add_action( 'tlmt_student_progress_migrated', array( $this, 'migrate_student_progress' ), 10, 2 );
		add_action( 'tlmt_assignment_migrated', array( $this, 'migrate_assignment_meta' ) );
		add_action( 'tlml_delete_learndash_quiz_questions', array( $this, 'delete_learndash_quiz_questions' ) );
	}

	/**
	 * Migrate post meta
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id Post id.
	 * @param string $migration_type Migration type.
	 *
	 * @return void
	 */
	public function migrate_post_meta( $post_id, $migration_type ) {
		$post_type    = get_post_type( $post_id );
		$content_type = $this->get_meta_content_type( $post_type );
		if ( ! $content_type ) {
			return;
		}

		try {
			$post     = get_post( $post_id );
			$meta_obj = tlmt_get_meta_obj( $content_type, $migration_type );

			try {
				$meta_obj->migrate( $post_id );
			} catch ( \Throwable $th ) {
				$this->update_migration_error( $content_type, "Failed to migrate meta data for this post: $post->post_title post_type: $post_type" );
			}
		} catch ( \Throwable $th ) {
			$this->update_migration_error( $content_type, "Failed to migrate meta data for this post: $post->post_title post_type: $post_type" );
		}
	}

	/**
	 * Migrate assignment meta
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id Post id.
	 *
	 * @return void
	 */
	public function migrate_assignment_meta( $post_id ) {
		$post     = get_post( $post_id );
		$meta_obj = tlmt_get_meta_obj( ContentTypes::ASSIGNMENT_META, MigrationTypes::LD_TO_TUTOR );
		try {
			$meta_obj->migrate( $post_id );
		} catch ( \Throwable $th ) {
			$this->update_migration_error( ContentTypes::ASSIGNMENT_META, "Failed to migrate meta data for this post: $post->post_title post_type: $post->post_type" );
		}
	}

	/**
	 * Get meta content type by post type
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_type Post type.
	 *
	 * @return string|null
	 */
	public function get_meta_content_type( string $post_type ) {
		$content_type = null;

		if ( $post_type ) {
			if ( tutor()->course_post_type === $post_type || 'sfwd-courses' === $post_type ) {
				$content_type = ContentTypes::COURSE_META;
			} elseif ( tutor()->lesson_post_type === $post_type || 'sfwd-lessons' === $post_type ) {
				$content_type = ContentTypes::LESSON_META;
			} elseif ( tutor()->quiz_post_type === $post_type || 'sfwd-quiz' === $post_type ) {
				$content_type = ContentTypes::QUIZ_META;
			}
		}

		return $content_type;
	}

	/**
	 * Migrate LearnDash course categories and tags to Tutor taxonomies.
	 *
	 * @since 2.5.0
	 *
	 * @param int    $course_id      Course ID.
	 * @param string $migration_type Migration type.
	 *
	 * @return void
	 */
	public function migrate_course_taxonomies( $course_id, $migration_type ) {
		if ( MigrationTypes::LD_TO_TUTOR !== $migration_type ) {
			return;
		}

		try {
			( new CourseTaxonomies() )->migrate( (int) $course_id );
		} catch ( \Throwable $th ) {
			$this->update_migration_error(
				ContentTypes::COURSE_TAXONOMIES,
				sprintf(
					/* translators: 1: course id, 2: error message */
					__( 'Failed to migrate taxonomies for course %1$d: %2$s', 'tutor-lms-migration-tool' ),
					(int) $course_id,
					$th->getMessage()
				)
			);
		}
	}

	/**
	 * Create a Tutor native subscription plan for LD subscribe courses.
	 *
	 * @since 2.5.0
	 *
	 * @param int    $course_id       Course ID.
	 * @param string $migration_type Migration type.
	 *
	 * @return void
	 */
	public function migrate_subscription_plan( $course_id, $migration_type ) {
		if ( MigrationTypes::LD_TO_TUTOR !== $migration_type ) {
			return;
		}

		if ( ! SubscriptionHelper::is_subscription_migration_available() ) {
			return;
		}

		try {
			$subscriptions = new Subscriptions();
			$subscriptions->migrate_plan_for_course( (int) $course_id );
		} catch ( \Throwable $th ) {
			$this->update_migration_error(
				ContentTypes::SUBSCRIPTIONS,
				sprintf(
					/* translators: 1: course id, 2: error message */
					__( 'Failed to migrate subscription plan for course %1$d: %2$s', 'tutor-lms-migration-tool' ),
					$course_id,
					$th->getMessage()
				)
			);
		}
	}

	/**
	 * Migrate products.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $course_id the course id.
	 * @param string $migration_type the migration type.
	 *
	 * @return void
	 */
	public function migrate_products( $course_id, $migration_type ) {
		try {
			$course = get_post( $course_id );

			// Native subscription plans replace one-time product attach for subscribe courses.
			$selling_option = get_post_meta( $course_id, Course::COURSE_SELLING_OPTION_META, true );
			if (
				Course::SELLING_OPTION_SUBSCRIPTION === $selling_option
				&& function_exists( 'tutor_utils' )
				&& tutor_utils()->is_monetize_by_tutor()
			) {
				return;
			}

			$monetize_by = tutor_utils()->get_option( 'monetize_by' );
			$product_obj = tlmt_get_product_obj( $monetize_by, $migration_type );

			try {
				$product_obj->migrate( $course_id, $course->post_title );
			} catch ( \Throwable $th ) {
				$this->update_migration_error( 'product', "Failed to attach product to course: $course->post_title " );
			}
		} catch ( \Throwable $th ) {
			$this->update_migration_error( 'product', "Failed to attach product to course: $course->post_title " );
		}
	}


	/**
	 * Update migration error message
	 *
	 * @since 2.3.0
	 *
	 * @param string $key Error key.
	 * @param string $error_msg Error message.
	 *
	 * @return void
	 */
	public function update_migration_error( string $key, string $error_msg ) {
		ErrorHandler::set_error( $key, $error_msg );
	}

	/**
	 * Migrates student progress based on the given migration type.
	 *
	 * @since 2.3.0
	 * @since 4.0.0 parameter $course_id added.
	 *
	 * @param int    $course_id the course id.
	 * @param string $migration_type The type of migration to perform.
	 */
	public function migrate_student_progress(  string $migration_type, int $course_id ) {
		try {
			$student_progress_obj = StudentProgressFactory::create( $migration_type );
			$student_progress_obj->migrate( $course_id );
		} catch ( \Throwable $th ) {
			$this->update_migration_error( ContentTypes::STUDENT_PROGRESS, 'Failed to migrate student progress. ' . $th->getMessage() );
		}
	}

	/**
	 * Deletes all records from the LearnDash quiz questions table after full LD migration.
	 *
	 * Must run only after enrollments/student progress have finished, since progress
	 * rebuilds attempt answers via JOIN on this table.
	 *
	 * @since 2.3.0
	 *
	 * Catches and logs any exceptions that occur during the deletion process.
	 *
	 * @return void
	 */
	public function delete_learndash_quiz_questions() {

		global $wpdb;

		$table = $wpdb->prefix . 'learndash_pro_quiz_question';
		try {

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table}" ) ); // phpcs:ignore

			if ( $wpdb->last_error ) {
				$this->update_migration_error( ContentTypes::STUDENT_PROGRESS, 'Failed to delete quiz questions. ' . $wpdb->last_error );
			}
		} catch ( \Throwable $th ) {
			$this->update_migration_error( ContentTypes::STUDENT_PROGRESS, 'Failed to delete quiz questions. ' . $th->getMessage() );
		}
	}
}
