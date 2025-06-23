<?php
/**
 * Reviews Interface.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */


interface Reviews {
	/**
	 * Migrate course reviews to tutor.
	 *
	 * @since 2.3.0
	 *
	 * @return void wp_json response
	 */
	public function migrate_reviews();
}
