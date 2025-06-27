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

use Themeum\TutorLMSMigrationTool\Interfaces\Post;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assignment migration class
 */
class Assignment implements Post {

	/**
	 * Migrate assignment using the post id
	 *
	 * @param WP_Post $post Post object.
	 * @param int     $parent_post_id Parent to link the post.
	 *
	 * @return void
	 */
	public function migrate( WP_Post $post, int $parent_post_id ) {

	}
}
