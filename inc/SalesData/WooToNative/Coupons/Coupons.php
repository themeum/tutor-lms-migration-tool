<?php
/**
 * Concrete class to handle coupon data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Coupons;

use AllowDynamicProperties;
use Tutor\Models\CourseModel;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Helper;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;
use Tutor\Models\CouponModel;
use WC_Coupon;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Coupons implements MigrationTemplate {


	const WC_COUPON_POST_TYPE = 'shop_coupon';

	/**
	 * WooCommerce coupon data.
	 *
	 * @since 2.4.0
	 *
	 * @var WC_Coupon|null
	 */
	private $wc_coupon_data;

	/**
	 * Tutor LMS coupon data handler.
	 *
	 * @since 2.4.0
	 *
	 * @var array
	 */
	private $tutor_coupon_data;

	/**
	 * Retrieve coupon posts with pagination.
	 *
	 * @since 2.4.0
	 *
	 * @param int $limit  Number of coupon posts to fetch. Default 5.
	 * @param int $offset Offset for pagination. Default 0.
	 * @return \WP_Post[]  Array of coupon posts.
	 */
	public function get_items( int $limit = 5, int $offset = 0 ): array {

		$args = array(
			'numberposts' => $limit,
			'orderby'     => 'ID',
			'order'       => 'DESC',
			'post_type'   => self::WC_COUPON_POST_TYPE,
			'offset'      => $offset,
			'post_status' => 'any',
		);

		return get_posts( $args );
	}


	/**
	 * Get total number of coupon posts.
	 *
	 * @since 2.4.0
	 *
	 * @return int Total coupon count.
	 */
	public function get_total_items_count(): int {
		return CourseModel::count( 'all', self::WC_COUPON_POST_TYPE );
	}

	/**
	 * Extract coupon data
	 *
	 * @since 2.4.0
	 *
	 * @param int|object $coupon Coupon id or object.
	 *
	 * @return mixed
	 */
	public function extract( $coupon ): MigrationTemplate {

		$this->wc_coupon_data = new WC_Coupon( $coupon->ID );

		return $this;
	}

	/**
	 * Transform the coupon data to native coupon
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function transform(): MigrationTemplate {

		$code                 = $this->wc_coupon_data->get_code();
		$purchase_requirement = $this->get_purchase_requirement();
		$coupon_post          = get_post( $this->wc_coupon_data->get_id() );
		$modified_date        = $this->convert_datetime_to_gmt( $this->wc_coupon_data->get_date_modified() );
		$application_type     = $this->get_coupon_application_type();

		$this->tutor_coupon_data = array(
			'coupon_status'              => Helper::get_coupon_status( $this->wc_coupon_data ),
			'coupon_type'                => 'code',
			'coupon_code'                => $code,
			'coupon_title'               => ucfirst( $code ),
			'coupon_description'         => $this->wc_coupon_data->get_description(),
			'discount_type'              => $this->wc_coupon_data->get_discount_type(),
			'discount_amount'            => $this->wc_coupon_data->get_amount(),
			'applies_to'                 => $application_type['type'],
			'total_usage_limit'          => (int) $this->wc_coupon_data->get_usage_limit(),
			'per_user_usage_limit'       => (int) $this->wc_coupon_data->get_usage_limit_per_user(),
			'purchase_requirement'       => $purchase_requirement['type'],
			'purchase_requirement_value' => $purchase_requirement['amount'],
			'start_date_gmt'             => $this->convert_datetime_to_gmt( $this->wc_coupon_data->get_date_created() ),
			'expire_date_gmt'            => $this->convert_datetime_to_gmt( $this->wc_coupon_data->get_date_expires() ),
			'created_at_gmt'             => $modified_date,
			'created_by'                 => (int) $coupon_post->post_author,
			'updated_at_gmt'             => $modified_date,
			'updated_by'                 => get_post_meta( $coupon_post->ID, '_edit_last', true ),
			'reference_ids'              => $application_type['reference_ids'],
		);

		return $this;
	}

	/**
	 * Migrate the coupon, store in database
	 *
	 * @since 2.4.0
	 *
	 * @return bool true|false
	 */
	public function migrate(): bool {
		return true;
	}

	/**
	 * Determine the coupon application type for tutor.
	 *
	 * @since 2.4.0
	 *
	 * @return array{
	 *     type: string,
	 *     reference_ids: int[]
	 * }
	 */
	private function get_coupon_application_type() {

		$product_categories = $this->wc_coupon_data->get_product_categories();
		$product_ids        = $this->wc_coupon_data->get_product_ids();

		if ( ! empty( $product_ids ) ) {

			return array(
				'type'          => CouponModel::APPLIES_TO_SPECIFIC_COURSES,
				'reference_ids' => $this->get_tutor_course_ids( $product_ids ),
			);
		}

		if ( ! empty( $product_categories ) ) {

			return array(
				'type'          => CouponModel::APPLIES_TO_SPECIFIC_CATEGORY,
				'reference_ids' => $this->get_tutor_category_id( $product_categories ),
			);
		}

		return array(
			'type'          => CouponModel::APPLIES_TO_ALL_COURSES,
			'reference_ids' => array(),
		);
	}


	/**
	 * Get the minimum purchase requirement for tutor coupon.
	 *
	 * @since 2.4.0
	 *
	 * @return array{
	 *     type: string,
	 *     amount: float|null
	 * }
	 */
	private function get_purchase_requirement() {

		$minimum_amount = $this->wc_coupon_data->get_minimum_amount();

		if ( ! empty( $minimum_amount ) ) {
			return array(
				'type'   => CouponModel::REQUIREMENT_MINIMUM_PURCHASE,
				'amount' => $minimum_amount,
			);
		}

		return array(
			'type'   => CouponModel::REQUIREMENT_NO_MINIMUM,
			'amount' => null,
		);
	}


	/**
	 * Convert a WC_DateTime object into a GMT/UTC datetime string.
	 *
	 * @since 2.4.0
	 *
	 * @param \WC_DateTime|null $date_time Date/time object to convert.
	 * @return string|null Formatted datetime string in 'Y-m-d H:i:s' format, or null if empty.
	 */
	private function convert_datetime_to_gmt( $date_time ) {

		if ( empty( $date_time ) ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $date_time->getTimestamp() );
	}

	/**
	 * Ensure Tutor LMS categories exist that correspond to WooCommerce product categories.
	 *
	 * @since 2.4.0
	 *
	 * @param int[] $product_categories WooCommerce product category IDs.
	 * @return int[] Tutor LMS category IDs.
	 */
	private function get_tutor_category_id( $product_categories ) {

		$tutor_category_ids = array();

		foreach ( $product_categories as $category_id ) {

			$wc_term = get_term( $category_id, 'product_cat' );

			if ( empty( $wc_term ) || is_wp_error( $wc_term ) ) {
				tutor_log( $wc_term );
				continue;
			}

			if ( term_exists( $wc_term->slug, CourseModel::COURSE_CATEGORY ) ) {
				$tutor_category_ids[] = $wc_term->term_id;
				continue;
			}

			$is_created = wp_insert_term(
				$wc_term->name,
				CourseModel::COURSE_CATEGORY,
			);

			if ( is_wp_error( $is_created ) ) {
				tutor_log( $is_created );
				continue;
			}

			$tutor_category_ids[] = $is_created['term_id'];
		}

		return $tutor_category_ids;
	}


	/**
	 * Get Tutor course IDs linked to WooCommerce product IDs.
	 *
	 * @since 2.4.0
	 *
	 * @param int[] $wc_product_ids WooCommerce product IDs.
	 * @return int[] Tutor course post IDs.
	 */
	private function get_tutor_course_ids( $wc_product_ids ) {

		$tutor_course_ids = array();

		foreach ( $wc_product_ids as $product_id ) {

			$tutor_course_meta = tutils()->product_belongs_with_course( $product_id );

			if ( empty( $tutor_course_meta ) ) {
				continue;
			}

			$tutor_course_ids[] = $tutor_course_meta->post_id;
		}

		return $tutor_course_ids;
	}
}
