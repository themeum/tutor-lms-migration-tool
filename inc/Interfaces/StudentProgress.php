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
	 * @since 4.0.0 param $course_id added.
	 *
	 * @param int $course_id the course id.
	 *
	 * @return void
	 */
	public function migrate( int $course_id );
}
