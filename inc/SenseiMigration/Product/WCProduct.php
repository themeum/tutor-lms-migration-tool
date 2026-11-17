<?php
/**
 * Sensei WooCommerce Product Linkage.
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
 * Handles mapping Sensei course WooCommerce product associations to Tutor LMS.
 */
class WCProduct implements Product {

	/**
	 * Attach WooCommerce product to Tutor course.
	 *
	 * @param int $course_id Tutor course post ID.
	 * @return int Attached WooCommerce product ID or 0.
	 */
	public function attach( int $course_id ): int {
		if ( $course_id <= 0 ) {
			return 0;
		}

		$product_id = (int) get_post_meta( $course_id, '_course_woocommerce_product', true );
		if ( $product_id <= 0 ) {
			return 0;
		}

		$product_post = get_post( $product_id );
		if ( ! $product_post || 'product' !== $product_post->post_type ) {
			return 0;
		}

		// Configure Tutor course monetization meta
		update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );
		update_post_meta( $course_id, '_tutor_course_product_id', $product_id );

		// Mark product as a Tutor-compatible product for future checkout auto-enrollment
		update_post_meta( $product_id, '_tutor_product', 'yes' );

		return $product_id;
	}
}
