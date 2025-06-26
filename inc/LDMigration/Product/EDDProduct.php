<?php
/**
 * Learndash Product to Tutor EDD product migration class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Product;

use Themeum\TutorLMSMigrationTool\Interfaces\Product;

/**
 * Product migration class to migrate product from learndash to tutor EDD.
 */
class EDDProduct implements Product {

	/**
	 * Migration method to migrate from learndash to edd product.
	 *
	 * @since 2.3.0
	 *
	 * @param integer $course_id the course id.
	 * @param string  $course_title the course title.
	 *
	 * @throws \Throwable
	 *
	 * @return void
	 */
	public function migrate( int $course_id, string $course_title ) {
		$course_details = get_post_meta( $course_id, '_sfwd-courses', true );
		update_post_meta( $course_id, '_tutor_course_price_type', 'free' );

		if ( $course_details['sfwd-courses_course_price'] ) {
			update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );
			try {
				$product_id = wp_insert_post(
					array(
						'post_title'   => $course_title . ' Product',
						'post_content' => '',
						'post_status'  => 'publish',
						'post_type'    => 'download',
					)
				);
			} catch ( \Throwable $th ) {
				return $th;
			}

			$product_metas = array(
				'edd_price'              => $course_details['sfwd-courses_course_price'],
				'_edd_download_earnings' => 0,
				'_edd_download_sales'    => 0,
			);
			foreach ( $product_metas as $key => $value ) {
				update_post_meta( $product_id, $key, $value );
			}
			update_post_meta( $course_id, '_tutor_course_product_id', $product_id );
			$coursePostThumbnail = get_post_meta( $course_id, '_thumbnail_id', true );
			if ( $coursePostThumbnail ) {
				set_post_thumbnail( $product_id, $coursePostThumbnail );
			}
		} else {
			update_post_meta( $course_id, '_tutor_course_price_type', 'free' );
		}
	}
}
