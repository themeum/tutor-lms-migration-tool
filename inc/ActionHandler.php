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
		add_action( 'tlmt_lesson_migrated', array( $this, 'migrate_post_meta' ), 10, 2 );
		add_action( 'tlmt_quiz_migrated', array( $this, 'migrate_post_meta' ), 10, 2 );
		add_action( 'tlmt_assignment_migrated', array( $this, 'migrate_post_meta' ), 10, 2 );
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
		switch ( $post_type ) {
			case tutor()->course_post_type:
				$content_type = ContentTypes::COURSE_META;
				break;
			case tutor()->lesson_post_type:
				$content_type = ContentTypes::LESSON_META;
				break;
			case tutor()->quiz_post_type:
				$content_type = ContentTypes::QUIZ_META;
				break;

			default:
				// code...
				break;
		}

		return $content_type;
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
}
