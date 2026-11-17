<?php
/**
 * Review interface.
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
 * Interface Review
 */
interface Review {

	/**
	 * Migrate reviews/ratings for a course or all courses.
	 *
	 * @param int $course_id Course post ID (optional, 0 for all).
	 * @return int Number of migrated reviews.
	 */
	public function migrate( int $course_id = 0 ): int;
}
