<?php
/**
 * Posts interface
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Interfaces;

use WP_Post;

interface Post {

	/**
	 * Migrate a post and link with the given parent
	 *
	 * @since 2.3.0
	 *
	 * @param WP_Post $post Post object.
	 * @param int     $parent_post_id Parent Post id.
	 *
	 * @return void
	 */
	public function migrate( WP_Post $post, int $parent_post_id );

}
