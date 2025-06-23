<?php
/**
 * Orders Interface.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

/**
 * Orders interface for review migration class.
 */
interface Orders {

	/**
	 * Migrate orders from learndash to tutor.
	 *
	 * @since 2.3.0
	 *
	 * @return void wp_json response
	 */
	public function migrate_orders();
}
