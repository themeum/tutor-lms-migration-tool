<?php


/**
 * Learndash Product to Tutor product migration class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Product;

use Themeum\TutorLMSMigrationTool\Interfaces\Product;


class TutorProduct implements Product {

	/**
	 * Migration method to migrate from learndash to tutor product.
	 *
	 * @since 2.3.0
	 *
	 * @param integer $course_id the course id.
	 * @param string  $course_title the course title.
	 *
	 * @return void
	 */
	public function migrate( int $course_id, string $course_title ) {
		$course_details = get_post_meta( $course_id, '_sfwd-courses', true );
		update_post_meta( $course_id, '_tutor_course_price_type', 'free' );

		if ( $course_details['sfwd-courses_course_price'] ) {
			update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );
			update_post_meta( $course_id, 'tutor_course_price', $course_details['sfwd-courses_course_price'] );
			update_post_meta( $course_id, 'tutor_course_sale_price', 0 );
		}
	}
}
