<?php
/**
 * Sensei Native Pricing Linkage.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration\Product;

use Themeum\TutorLMSMigrationTool\Interfaces\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Tutor native monetization pricing when WooCommerce is not used.
 */
class NativePricing implements Product {

	/**
	 * Product migration method conforming to Product interface.
	 *
	 * @param int    $course_id    Course post ID.
	 * @param string $course_title Course title.
	 * @return void
	 */
	public function migrate( int $course_id, string $course_title = '' ) {
		$this->attach( $course_id );
	}

	/**
	 * Attach or configure native pricing for a course.
	 *
	 * @param int $course_id Course post ID.
	 * @return int 1 if paid pricing configured, 0 otherwise.
	 */
	public function attach( int $course_id ): int {
		if ( $course_id <= 0 ) {
			return 0;
		}

		$product_id = (int) get_post_meta( $course_id, '_course_woocommerce_product', true );
		if ( $product_id > 0 ) {
			$regular_price = get_post_meta( $product_id, '_regular_price', true );
			$sale_price    = get_post_meta( $product_id, '_sale_price', true );
			$price         = get_post_meta( $product_id, '_price', true );

			if ( ! empty( $price ) && is_numeric( $price ) && (float) $price > 0 ) {
				$effective_regular = is_numeric( $regular_price ) ? (float) $regular_price : (float) $price;
				update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );
				update_post_meta( $course_id, '_course_price', $effective_regular );
				if ( ! empty( $sale_price ) && is_numeric( $sale_price ) ) {
					update_post_meta( $course_id, '_course_sale_price', (float) $sale_price );
				}
				return 1;
			}
		}

		$existing_type = get_post_meta( $course_id, '_tutor_course_price_type', true );
		if ( empty( $existing_type ) ) {
			update_post_meta( $course_id, '_tutor_course_price_type', 'free' );
		}

		return 0;
	}
}
