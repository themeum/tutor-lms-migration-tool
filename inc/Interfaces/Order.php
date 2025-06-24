<?php
/**
 * Orders Interface.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */
namespace Themeum\TutorLMSMigrationTool\Interfaces;

/**
 * Orders interface for review migration class.
 */
interface Order {

	/**
	 * Migrate orders from learndash to tutor.
	 *
	 * @since 2.3.0
	 *
	 * @return void wp_json response
	 */
	public function migrate();
}
