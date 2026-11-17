<?php
/**
 * StudentProgress interface
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface StudentProgress
 */
interface StudentProgress {

	/**
	 * Migrate student progress for a course or user.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID (optional, 0 for all students of course).
	 * @return int Number of progress records migrated.
	 */
	public function migrate( int $course_id, int $user_id = 0 ): int;
}
