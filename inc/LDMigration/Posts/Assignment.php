<?php
/**
 * Assignment migration class
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Posts;

use WP_Post;
use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Themeum\TutorLMSMigrationTool\MigrationTypes;
use Themeum\TutorLMSMigrationTool\Interfaces\Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assignment migration class
 */
class Assignment implements Post {

	/**
	 * Assignment post type
	 *
	 * @since 2.3.0
	 *
	 * @var string
	 */
	private $post_type;

	const LD_ASSIGNMENT    = 'sfwd-assignment';
	const TUTOR_ASSIGNMENT = 'tutor_assignment';

	/**
	 * Set member variables
	 */
	public function __construct() {
		$this->post_type = tutor()->assignment_post_type;
	}

	/**
	 * Migrate assignment using the post id
	 *
	 * @param WP_Post $post Post object.
	 * @param int     $parent_post_id Parent to link the post.
	 *
	 * @return void
	 */
	public function migrate( WP_Post $post, int $parent_post_id ) {

		$update = array(
			'ID'          => $post->ID,
			'post_type'   => $this->post_type,
			'post_parent' => $parent_post_id,
		);

		wp_update_post( $update, false, false );

		tlmt_get_meta_obj( ContentTypes::ASSIGNMENT_META, MigrationTypes::LD_TO_TUTOR )->migrate( $post->ID, $post->post_type );
	}

	/**
	 * Migrates all LearnDash assignment files to TutorLMS format.
	 *
	 * @since 2.3.0
	 *
	 * @throws \Throwable If any unexpected exception occurs during the migration process.
	 *
	 * @return void
	 */
	public function migrate_assignment_files() {
		try {
			$assignments = get_posts( array( 'post_type' => self::LD_ASSIGNMENT ) );
			array_map(
				function ( $assignment ) {
					if ( ! $this->assign_file_to_tutor( $assignment ) ) {
						ErrorHandler::set_error( ContentTypes::ASSIGNMENT_FILES, 'Failed To Transfer Assignment For Assignment ID: ' . $assignment->ID );
					}
				},
				$assignments
			);
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Assigns a single LearnDash assignment file to TutorLMS.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_Post $assignment The assignment post object to migrate.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function assign_file_to_tutor( WP_Post $assignment ) {

		$meta = get_post_meta( $assignment->ID );

		if ( empty( $meta ) ) {
			return false;
		}

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => intval( $meta['lesson_id'][0] ?? 0 ),
				'comment_date'     => $assignment->post_date,
				'comment_date_gmt' => $assignment->post_date_gmt,
				'comment_author'   => $meta['disp_name'][0] ?? '',
				'comment_content'  => $meta['post_content'][0] ?? '',
				'comment_approved' => 'submitted',
				'comment_agent'    => 'TutorLMSPlugin',
				'comment_type'     => self::TUTOR_ASSIGNMENT,
				'comment_parent'   => intval( $meta['course_id'][0] ?? 0 ),
				'user_id'          => intval( $meta['user_id'][0] ?? 0 ),
			)
		);

		if ( ! $comment_id ) {
			ErrorHandler::set_error(
				ContentTypes::ASSIGNMENT_FILES,
				"Failed to insert comment for assignment ID: {$assignment->ID}"
			);
			return false;
		}

		$uploaded_path = urldecode( $meta['file_path'][0] ?? '' );
		$upload_dir    = wp_upload_dir();
		$relative_path = str_replace( $upload_dir['basedir'], '', $uploaded_path );

		$attachments = array(
			array(
				'url'           => $meta['file_link'][0] ?? '',
				'type'          => wp_check_filetype( $meta['file_name'][0] ?? '' )['type'] ?? '',
				'uploaded_path' => $relative_path ?? '',
			),
		);

		add_comment_meta( $comment_id, 'assignment_mark', $meta['points'][0] ?? 10 );
		add_comment_meta( $comment_id, 'uploaded_attachments', wp_json_encode( $attachments ) );

		// If approved, set evaluation time.
		if ( (int) ( $meta['approval_status'][0] ?? 0 ) === 1 ) {
			add_comment_meta( $comment_id, 'evaluate_time', $assignment->post_modified_gmt );
		}

		return true;
	}
}
