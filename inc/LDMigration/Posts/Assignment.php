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
			$data        = array();

			foreach ( $assignments as $assignment ) {

				$meta      = get_post_meta( $assignment->ID );
				$lesson_id = $meta['lesson_id'][0] ?? 0;
				$user_id   = intval( $meta['user_id'][0] ?? 0 );
				$course_id = intval( $meta['course_id'][0] ?? 0 );

				if ( $lesson_id && $user_id && $course_id ) {

					if ( ! isset( $data[ $user_id ][ $course_id ][ $lesson_id ] ) ) {
						$data[ $user_id ][ $course_id ][ $lesson_id ] = array(
							'assignment_id'    => $assignment->ID,
							'comment_post_ID'  => intval( $lesson_id ),
							'comment_date'     => $assignment->post_date,
							'comment_date_gmt' => $assignment->post_date_gmt,
							'comment_author'   => $meta['disp_name'][0] ?? '',
							'comment_content'  => $meta['post_content'][0] ?? '',
							'comment_parent'   => $course_id,
							'user_id'          => intval( $meta['user_id'][0] ?? 0 ),
						);
					}

					$data[ $user_id ][ $course_id ][ $lesson_id ]['attachment_files'][] = array(
						'name'          => $meta['file_name'][0] ?? '',
						'url'           => $meta['file_link'][0] ?? '',
						'type'          => wp_check_filetype( $meta['file_name'][0] ?? '' )['type'] ?? '',
						'uploaded_path' => $meta['file_path'][0] ? $this->get_uploaded_file_path( $meta['file_path'][0] ) : '',
					);

					// LearnDash allows individual point values per assignment file, while Tutor offers a single, unified point value for all submitted assignments file.
					$data[ $user_id ][ $course_id ][ $lesson_id ]['assignment_mark'] = max(
						intval( $meta['points'][0] ) ?? 10,
						$data[ $user_id ][ $course_id ][ $lesson_id ]['assignment_mark']
					);

					if ( (int) ( $meta['approval_status'][0] ?? 0 ) === 1 ) {
						$data[ $user_id ][ $course_id ][ $lesson_id ]['evaluate_time'] = $assignment->post_modified_gmt;
					}
				}
			}

			if ( ! empty( $data ) ) {
				$this->assign_file_to_tutor( $data );
			}
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Assigns uploaded assignment files to Tutor LMS by inserting comments.
	 *
	 * @since 2.3.0
	 *
	 * If insertion fails for any comment, the error is logged using the ErrorHandler.
	 *
	 * @param array $data A multi-dimensional array of data to insert as wp_comment.
	 *
	 * @return void
	 */
	private function assign_file_to_tutor( $data ) {

		foreach ( $data as $user_info ) {
			foreach ( $user_info as $course_info ) {
				foreach ( $course_info as $ld_content ) {
					$comment_id = wp_insert_comment(
						array(
							'comment_post_ID'  => $ld_content['comment_post_ID'],
							'comment_date'     => $ld_content['comment_date'],
							'comment_date_gmt' => $ld_content['comment_date_gmt'],
							'comment_author'   => $ld_content['comment_author'],
							'comment_content'  => $ld_content['comment_content'],
							'comment_approved' => 'submitted',
							'comment_agent'    => 'TutorLMSPlugin',
							'comment_type'     => self::TUTOR_ASSIGNMENT,
							'comment_parent'   => $ld_content['comment_parent'],
							'user_id'          => $ld_content['user_id'],
						)
					);

					if ( ! $comment_id ) {
						ErrorHandler::set_error(
							ContentTypes::ASSIGNMENT_FILES,
							"Failed to insert comment for assignment ID: {$ld_content['assignment_id']}"
						);
						continue;
					}

					add_comment_meta( $comment_id, 'assignment_mark', $ld_content['assignment_mark'] );
					add_comment_meta( $comment_id, 'uploaded_attachments', wp_json_encode( $ld_content['attachment_files'] ) );

					if ( $ld_content['evaluate_time'] ) {
						add_comment_meta( $comment_id, 'evaluate_time', $ld_content['evaluate_time'] );
					}
				}
			}
		}
	}

	/**
	 * Get the relative uploaded file path from the absolute file path.
	 *
	 * @since 2.3.0
	 *
	 * @param string $file_path The URL-encoded full file path.
	 *
	 * @return string|null The relative file path within the uploads directory, or null if the input is empty.
	 */
	private function get_uploaded_file_path( $file_path ) {

		if ( empty( $file_path ) ) {
			return;
		}

		$uploaded_path = urldecode( $file_path ?? '' );
		$upload_dir    = wp_upload_dir();

		return str_replace( $upload_dir['basedir'], '', $uploaded_path );
	}
}
