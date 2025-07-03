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

	const LD_ASSIGNMENT = 'sfwd-assignment';

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

	// public function migrate_assignment_files() {
	// 	try {
	// 		$uploaded_assignments = get_posts(
	// 			array(
	// 				'post_type' => self::LD_ASSIGNMENT,
	// 			)
	// 		);

	// 		foreach ( $uploaded_assignments as $assignment ) {
				
	// 		}
	// 	} catch ( \Throwable $th ) {
	// 		throw $th;
	// 	}
	// }
}
