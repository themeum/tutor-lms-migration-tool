<?php
/**
 * Product interface.
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
 * Interface Product
 */
interface Product {

	/**
	 * Attach or migrate monetization product for a course.
	 *
	 * @param int $course_id Course post ID.
	 * @return int Attached product ID or 0.
	 */
	public function attach( int $course_id ): int;
}
