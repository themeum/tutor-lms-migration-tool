<?php
/**
 * Review Interface.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Interfaces;

/**
 * Reviews interface for review migration class.
 */
interface Review {
	/**
	 * Migrate course reviews to tutor.
	 *
	 * @since 2.3.0
	 *
	 * @throws \Throwable
	 *
	 * @param \WP_Comment|\WP_Post $review the review to migrate.
	 *
	 * @return void wp_json response
	 */
	public function migrate( $review );
}
