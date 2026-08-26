<?php
/**
 * LifterLMS access-plan → Tutor native course pricing.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration\Product;

use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Helper;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Subscriptions;
use TUTOR\Course;

defined( 'ABSPATH' ) || exit;

/**
 * Attaches Tutor native price meta (and subscription plans when available).
 *
 * @since 2.6.0
 */
class NativePricing {

	/**
	 * Migrate Lifter access-plan pricing onto a Tutor course.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course post ID (same ID after CPT conversion).
	 *
	 * @return void
	 */
	public function migrate( int $course_id ): void {
		if ( $course_id < 1 || ! function_exists( 'tutor_utils' ) || ! tutor_utils()->is_monetize_by_tutor() ) {
			return;
		}

		$plans = $this->get_access_plans( $course_id );
		if ( empty( $plans ) ) {
			update_post_meta( $course_id, Course::COURSE_PRICE_TYPE_META, Course::PRICE_TYPE_FREE );
			return;
		}

		$one_time  = array();
		$recurring = array();

		foreach ( $plans as $plan_id ) {
			$frequency = (int) get_post_meta( $plan_id, '_llms_frequency', true );
			if ( $frequency > 0 ) {
				$recurring[] = $plan_id;
			} else {
				$one_time[] = $plan_id;
			}
		}

		$has_one_time  = false;
		$has_recurring = false;

		if ( ! empty( $one_time ) ) {
			$has_one_time = $this->apply_one_time_price( $course_id, (int) $one_time[0] );
		}

		if ( ! empty( $recurring ) && Helper::is_subscription_migration_available() ) {
			$subscriptions = new Subscriptions();
			foreach ( $recurring as $plan_id ) {
				$tutor_plan_id = $subscriptions->migrate_plan_for_access_plan( (int) $plan_id, $course_id );
				if ( $tutor_plan_id ) {
					$has_recurring = true;
				}
			}
		}

		if ( $has_one_time && $has_recurring ) {
			update_post_meta( $course_id, Course::COURSE_PRICE_TYPE_META, Course::PRICE_TYPE_PAID );
			update_post_meta( $course_id, Course::COURSE_SELLING_OPTION_META, Course::SELLING_OPTION_BOTH );
			return;
		}

		if ( $has_recurring ) {
			update_post_meta( $course_id, Course::COURSE_PRICE_TYPE_META, Course::PRICE_TYPE_PAID );
			update_post_meta( $course_id, Course::COURSE_SELLING_OPTION_META, Course::SELLING_OPTION_SUBSCRIPTION );
			return;
		}

		if ( $has_one_time ) {
			update_post_meta( $course_id, Course::COURSE_PRICE_TYPE_META, Course::PRICE_TYPE_PAID );
			update_post_meta( $course_id, Course::COURSE_SELLING_OPTION_META, Course::SELLING_OPTION_ONE_TIME );
			return;
		}

		update_post_meta( $course_id, Course::COURSE_PRICE_TYPE_META, Course::PRICE_TYPE_FREE );
	}

	/**
	 * Write one-time catalog price from an access plan.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course ID.
	 * @param int $plan_id   Lifter access plan ID.
	 *
	 * @return bool True when a paid price was applied.
	 */
	private function apply_one_time_price( int $course_id, int $plan_id ): bool {
		if ( 'yes' === get_post_meta( $plan_id, '_llms_is_free', true ) ) {
			return false;
		}

		$regular = (float) get_post_meta( $plan_id, '_llms_price', true );
		$sale    = get_post_meta( $plan_id, '_llms_sale_price', true );

		// Lifter sometimes stores sale on the course; fall back.
		if ( '' === $sale || null === $sale ) {
			$sale = get_post_meta( $course_id, '_llms_sale_price', true );
		}

		$sale = ( '' !== $sale && null !== $sale ) ? (float) $sale : null;

		if ( $regular <= 0 && ( null === $sale || $sale <= 0 ) ) {
			return false;
		}

		if ( $regular <= 0 && null !== $sale && $sale > 0 ) {
			$regular = $sale;
			$sale    = null;
		}

		update_post_meta( $course_id, Course::COURSE_PRICE_META, $regular );

		if ( null !== $sale && $sale > 0 && $sale < $regular ) {
			update_post_meta( $course_id, Course::COURSE_SALE_PRICE_META, $sale );
		} else {
			delete_post_meta( $course_id, Course::COURSE_SALE_PRICE_META );
		}

		return true;
	}

	/**
	 * Published access plans for a course, oldest first.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course ID.
	 *
	 * @return int[]
	 */
	private function get_access_plans( int $course_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm
					ON pm.post_id = p.ID
					AND pm.meta_key = '_llms_product_id'
					AND pm.meta_value = %s
				WHERE p.post_type = 'llms_access_plan'
					AND p.post_status = 'publish'
				ORDER BY p.ID ASC",
				(string) $course_id
			)
		);

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_map( 'intval', $ids );
	}
}
