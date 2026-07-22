<?php
/**
 * LearnDash subscription migration helpers.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions;

use TutorPro\Subscription\Models\PlanModel;
use TutorPro\Subscription\Models\SubscriptionModel;

defined( 'ABSPATH' ) || exit;

/**
 * Helper utilities for LD → Tutor subscription migration.
 *
 * @since 2.5.0
 */
class Helper {

	/**
	 * LearnDash subscribe price type.
	 *
	 * @var string
	 */
	const LD_PRICE_TYPE_SUBSCRIBE = 'subscribe';

	/**
	 * Whether native subscription migration can run.
	 *
	 * @since 2.5.0
	 *
	 * @return bool
	 */
	public static function is_subscription_migration_available(): bool {
		if ( ! function_exists( 'tutor_pro' ) || ! function_exists( 'tutor_utils' ) ) {
			return false;
		}

		if ( ! tutor_utils()->is_monetize_by_tutor() ) {
			return false;
		}

		if ( ! class_exists( PlanModel::class ) || ! class_exists( SubscriptionModel::class ) ) {
			return false;
		}

		$basename = plugin_basename( 'tutor-pro/addons/subscription/subscription.php' );
		if ( defined( 'TUTOR_SUBSCRIPTION_FILE' ) ) {
			$basename = plugin_basename( TUTOR_SUBSCRIPTION_FILE );
		}

		return (bool) tutor_utils()->is_addon_enabled( $basename );
	}

	/**
	 * Whether a course is LearnDash recurring (subscribe).
	 *
	 * @since 2.5.0
	 *
	 * @param int $course_id Course ID.
	 *
	 * @return bool
	 */
	public static function is_ld_subscribe_course( int $course_id ): bool {
		$price_type = self::get_ld_course_setting( $course_id, 'course_price_type' );

		if ( empty( $price_type ) ) {
			$meta = get_post_meta( $course_id, '_sfwd-courses', true );
			if ( is_array( $meta ) ) {
				$price_type = $meta['sfwd-courses_course_price_type'] ?? '';
			}
		}

		return self::LD_PRICE_TYPE_SUBSCRIBE === $price_type;
	}

	/**
	 * Get a LearnDash course setting with serialized-meta fallback.
	 *
	 * @since 2.5.0
	 *
	 * @param int    $course_id Course ID.
	 * @param string $key       Setting key without sfwd-courses_ prefix.
	 *
	 * @return mixed
	 */
	public static function get_ld_course_setting( int $course_id, string $key ) {
		if ( function_exists( 'learndash_get_setting' ) ) {
			$value = learndash_get_setting( $course_id, $key );
			if ( null !== $value && false !== $value && '' !== $value ) {
				return $value;
			}
		}

		$meta = get_post_meta( $course_id, '_sfwd-courses', true );
		if ( ! is_array( $meta ) ) {
			return '';
		}

		$prefixed = 'sfwd-courses_' . $key;
		return $meta[ $prefixed ] ?? ( $meta[ $key ] ?? '' );
	}

	/**
	 * Map LearnDash billing unit to Tutor interval.
	 *
	 * @since 2.5.0
	 *
	 * @param string $ld_unit D|W|M|Y or day|week|month|year.
	 *
	 * @return string
	 */
	public static function map_billing_interval( string $ld_unit ): string {
		$ld_unit = strtoupper( trim( $ld_unit ) );
		$map     = array(
			'D'     => PlanModel::INTERVAL_DAY,
			'W'     => PlanModel::INTERVAL_WEEK,
			'M'     => PlanModel::INTERVAL_MONTH,
			'Y'     => PlanModel::INTERVAL_YEAR,
			'DAY'   => PlanModel::INTERVAL_DAY,
			'WEEK'  => PlanModel::INTERVAL_WEEK,
			'MONTH' => PlanModel::INTERVAL_MONTH,
			'YEAR'  => PlanModel::INTERVAL_YEAR,
		);

		$normalized = strtolower( $ld_unit );
		if ( in_array( $normalized, array( PlanModel::INTERVAL_DAY, PlanModel::INTERVAL_WEEK, PlanModel::INTERVAL_MONTH, PlanModel::INTERVAL_YEAR ), true ) ) {
			return $normalized;
		}

		return $map[ $ld_unit ] ?? PlanModel::INTERVAL_MONTH;
	}

	/**
	 * Map LearnDash product_status to Tutor subscription status.
	 *
	 * @since 2.5.0
	 *
	 * @param string $ld_status LD status.
	 *
	 * @return string
	 */
	public static function map_subscription_status( string $ld_status ): string {
		$map = array(
			'active'   => SubscriptionModel::STATUS_ACTIVE,
			'trial'    => SubscriptionModel::STATUS_ACTIVE,
			'canceled' => SubscriptionModel::STATUS_CANCELLED,
			'cancelled'=> SubscriptionModel::STATUS_CANCELLED,
			'expired'  => SubscriptionModel::STATUS_EXPIRED,
		);

		return $map[ strtolower( $ld_status ) ] ?? SubscriptionModel::STATUS_PENDING;
	}

	/**
	 * Map LearnDash cycle count to Tutor recurring_limit.
	 *
	 * LD 0 = until cancelled. LD N = N total billing periods → Tutor renewals after first = N - 1.
	 *
	 * @since 2.5.0
	 *
	 * @param int $cycles LD course_no_of_cycles.
	 *
	 * @return int
	 */
	public static function map_recurring_limit( int $cycles ): int {
		if ( $cycles <= 0 ) {
			return 0;
		}

		if ( 1 === $cycles ) {
			return -1;
		}

		return $cycles - 1;
	}

	/**
	 * Whether a transaction post is a subscription line item.
	 *
	 * @since 2.5.0
	 *
	 * @param int $transaction_id Transaction post ID.
	 *
	 * @return bool
	 */
	public static function is_subscription_transaction( int $transaction_id ): bool {
		$price_type = get_post_meta( $transaction_id, 'price_type', true );
		if ( self::LD_PRICE_TYPE_SUBSCRIBE === $price_type ) {
			return true;
		}

		$stripe_price_type = get_post_meta( $transaction_id, 'stripe_price_type', true );
		if ( self::LD_PRICE_TYPE_SUBSCRIBE === $stripe_price_type ) {
			return true;
		}

		$subscr_id = get_post_meta( $transaction_id, 'subscr_id', true );
		$txn_type  = get_post_meta( $transaction_id, 'txn_type', true );
		if ( $subscr_id && 'subscr_signup' === $txn_type ) {
			return true;
		}

		return false;
	}

	/**
	 * Get all LD subscription line-item transaction IDs.
	 *
	 * @since 2.5.0
	 *
	 * @return int[]
	 */
	public static function get_subscription_transaction_ids(): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status = %s
				AND (
					( pm.meta_key = 'price_type' AND pm.meta_value = %s )
					OR ( pm.meta_key = 'stripe_price_type' AND pm.meta_value = %s )
					OR ( pm.meta_key = 'txn_type' AND pm.meta_value = 'subscr_signup' )
				)",
				'sfwd-transactions',
				'publish',
				self::LD_PRICE_TYPE_SUBSCRIBE,
				self::LD_PRICE_TYPE_SUBSCRIBE
			)
		);

		return array_map( 'intval', array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Get subscription line items plus parent orders and renewal charges.
	 *
	 * @since 2.5.0
	 *
	 * @return int[]
	 */
	public static function get_subscription_related_transaction_ids(): array {
		global $wpdb;

		$subscription_ids = self::get_subscription_transaction_ids();
		if ( empty( $subscription_ids ) ) {
			return array();
		}

		$related      = $subscription_ids;
		$id_list      = implode( ',', array_map( 'intval', $subscription_ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs sanitized via intval.
		$parents      = $wpdb->get_col(
			"SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE ID IN ({$id_list}) AND post_parent > 0"
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs sanitized via intval.
		$children     = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_parent IN ({$id_list}) AND post_type = %s",
				'sfwd-transactions'
			)
		);

		if ( ! empty( $parents ) ) {
			$related = array_merge( $related, array_map( 'intval', $parents ) );
		}
		if ( ! empty( $children ) ) {
			$related = array_merge( $related, array_map( 'intval', $children ) );
		}

		return array_values( array_unique( array_filter( $related ) ) );
	}

	/**
	 * Get course ID linked to an LD transaction.
	 *
	 * @since 2.5.0
	 *
	 * @param int $transaction_id Transaction ID.
	 *
	 * @return int
	 */
	public static function get_transaction_course_id( int $transaction_id ): int {
		$course_id = (int) get_post_meta( $transaction_id, 'post_id', true );
		if ( $course_id ) {
			return $course_id;
		}

		$course_id = (int) get_post_meta( $transaction_id, 'course_id', true );
		return $course_id;
	}

	/**
	 * Format a unix timestamp or datetime string as GMT MySQL datetime.
	 *
	 * @since 2.5.0
	 *
	 * @param mixed $value Timestamp or datetime string.
	 *
	 * @return string|null
	 */
	public static function to_gmt_datetime( $value ): ?string {
		if ( empty( $value ) ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $value );
		}

		$timestamp = strtotime( (string) $value );
		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Whether WooCommerce Subscriptions path can be reused after LD migration.
	 *
	 * @since 2.5.0
	 *
	 * @return bool
	 */
	public static function is_woo_subscription_path_available(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' )
			&& function_exists( 'tutor_utils' )
			&& tutor_utils()->is_addon_enabled( 'wc-subscriptions' );
	}
}
