<?php
/**
 * PostMeta Factory
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Interfaces;

interface PostMeta {

	/**
	 * Migrate post meta
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id Post id.
	 *
	 * @return void
	 */
	public function migrate( int $post_id );

}
