<?php
/**
 * Student Progress Interface
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Interfaces;

interface StudentProgress {

	/**
	 * Migrate student progress
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function migrate();
}
