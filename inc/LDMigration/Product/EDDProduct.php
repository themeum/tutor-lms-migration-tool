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

			$product_metas = $this->prepare_product_meta( $course_details['sfwd-courses_course_price'] );

			foreach ( $product_metas as $key => $value ) {
				update_post_meta( $product_id, $key, $value );
			}

			update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );
			update_post_meta( $course_id, '_tutor_course_product_id', $product_id );

			set_product_thumbnail( $course_id, $product_id );

		} else {
			update_post_meta( $course_id, '_tutor_course_price_type', 'free' );
		}
	}

	/**
	 * Prepare EDD product meta.
	 *
	 * @since 2.3.0
	 *
	 * @param string|int $price the product price.
	 *
	 * @return array
	 */
	private function prepare_product_meta( $price ) {
		$product_metas = array(
			'edd_price'              => $price,
			'_edd_download_earnings' => 0,
			'_edd_download_sales'    => 0,
		);

		return $product_metas;
	}
}
