<?php


/**
 * Learndash Product to Tutor WC product migration class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Product;

use Themeum\TutorLMSMigrationTool\Interfaces\Product;



class WCProduct implements Product {
	/**
	 * Migrate learndash product to tutor WC product.
	 *
	 * @since 2.3.0
	 *
	 * @param integer $course_id
	 * @param string $course_title
	 *
	 * @throws \Throwable
	 *
	 * @return void
	 */
	public function migrate( int $course_id, string $course_title ) {
		$course_details = get_post_meta( $course_id, '_sfwd-courses', true );
		update_post_meta( $course_id, '_tutor_course_price_type', 'free' );

		if ( ! is_array( $course_details ) || empty( $course_details['sfwd-courses_course_price'] ) ) {
			return;
		}

		try {
			$product_id = wp_insert_post(
				array(
					'post_title'   => $course_title . ' Product',
					'post_content' => '',
					'post_status'  => 'publish',
					'post_type'    => 'product',
				)
			);
		} catch ( \Throwable $th ) {
			return $th;
		}

		$product_meta = $this->prepare_product_meta( $course_details['sfwd-courses_course_price'] );

		foreach ( $product_meta as $key => $value ) {
			update_post_meta( $product_id, $key, $value );
		}

		update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );
		update_post_meta( $course_id, '_tutor_course_product_id', $product_id );

		set_product_thumbnail( $course_id, $product_id );
	}

	/**
	 * Prepare WC product meta.
	 *
	 * @since 2.3.0
	 *
	 * @param int|string $price the product price.
	 *
	 * @return array
	 */
	private function prepare_product_meta( $price ) {
		$product_meta = array(
			'_regular_price'     => $price,
			'total_sales'        => 0,
			'_tax_status'        => 'taxable',
			'_tax_class'         => '',
			'_manage_stock'      => 'no',
			'_backorders'        => 'no',
			'_sold_individually' => 'yes',
			'_virtual'           => 'yes',
			'_downloadable'      => 'no',
			'_download_limit'    => -1,
			'_download_expiry'   => -1,
			'_stock'             => null,
			'_stock_status'      => 'instock',
			'_price'             => $price,
			'_tutor_product'     => 'yes',
		);

		return $product_meta;
	}
}
