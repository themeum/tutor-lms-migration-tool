<?php
/**
 * LifterLMS subscription migration helpers.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions;

use TutorPro\Subscription\Models\PlanModel;
use TutorPro\Subscription\Models\SubscriptionModel;

defined( 'ABSPATH' ) || exit;

/**
 * Helper utilities for Lifter → Tutor subscription migration.
 *
 * @since 2.6.0
 */
class Helper {

	/**
	 * Whether native subscription migration can run.
	 *
	 * @since 2.6.0
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
	 * Map Lifter billing period to Tutor interval.
	 *
	 * @since 2.6.0
	 *
	 * @param string $period day|week|month|year.
	 *
	 * @return string
	 */
	public static function map_billing_interval( string $period ): string {
		$period = strtolower( trim( $period ) );
		$map    = array(
			'day'   => PlanModel::INTERVAL_DAY,
			'days'  => PlanModel::INTERVAL_DAY,
			'week'  => PlanModel::INTERVAL_WEEK,
			'weeks' => PlanModel::INTERVAL_WEEK,
			'month' => PlanModel::INTERVAL_MONTH,
			'months' => PlanModel::INTERVAL_MONTH,
			'year'   => PlanModel::INTERVAL_YEAR,
			'years'  => PlanModel::INTERVAL_YEAR,
		);

		return $map[ $period ] ?? PlanModel::INTERVAL_MONTH;
	}

	/**
	 * Map Lifter billing length to Tutor recurring_limit.
	 *
	 * Lifter 0 = until cancelled. N = N total billing periods → Tutor renewals = N - 1.
	 *
	 * @since 2.6.0
	 *
	 * @param int $length Lifter `_llms_length` / `_llms_billing_length`.
	 *
	 * @return int
	 */
	public static function map_recurring_limit( int $length ): int {
		if ( $length <= 0 ) {
			return 0;
		}

		if ( 1 === $length ) {
			return -1;
		}

		return $length - 1;
	}

	/**
	 * Map Lifter recurring order status to Tutor subscription status.
	 *
	 * @since 2.6.0
	 *
	 * @param string $llms_status Lifter post_status.
	 *
	 * @return string
	 */
	public static function map_subscription_status( string $llms_status ): string {
		$llms_status = strtolower( trim( $llms_status ) );
		$map         = array(
			'llms-active'         => SubscriptionModel::STATUS_ACTIVE,
			'llms-expired'        => SubscriptionModel::STATUS_EXPIRED,
			'llms-on-hold'        => SubscriptionModel::STATUS_HOLD,
			'llms-cancelled'      => SubscriptionModel::STATUS_CANCELLED,
			'llms-pending-cancel' => SubscriptionModel::STATUS_CANCELLED,
			'llms-pending'        => SubscriptionModel::STATUS_PENDING,
			'llms-completed'      => SubscriptionModel::STATUS_EXPIRED,
			'llms-refunded'       => SubscriptionModel::STATUS_CANCELLED,
			'llms-failed'         => SubscriptionModel::STATUS_CANCELLED,
		);

		return $map[ $llms_status ] ?? SubscriptionModel::STATUS_PENDING;
	}

	/**
	 * Recurring Lifter order statuses included in subscription migration.
	 *
	 * @since 2.6.0
	 *
	 * @return string[]
	 */
	public static function migratable_recurring_statuses(): array {
		return array(
			'llms-active',
			'llms-expired',
			'llms-on-hold',
			'llms-cancelled',
			'llms-pending-cancel',
			'llms-pending',
			'llms-completed',
			'llms-refunded',
		);
	}

	/**
	 * Whether an llms_order is a recurring course subscription.
	 *
	 * @since 2.6.0
	 *
	 * @param int $order_id Lifter order ID.
	 *
	 * @return bool
	 */
	public static function is_recurring_course_order( int $order_id ): bool {
		$order_type = (string) get_post_meta( $order_id, '_llms_order_type', true );
		if ( 'recurring' !== $order_type ) {
			$frequency = (int) get_post_meta( $order_id, '_llms_billing_frequency', true );
			if ( $frequency < 1 ) {
				return false;
			}
		}

		$product_type = (string) get_post_meta( $order_id, '_llms_product_type', true );
		if ( 'membership' === $product_type || 'llms_membership' === $product_type ) {
			return false;
		}

		$course_id = self::get_order_course_id( $order_id );
		return $course_id > 0;
	}

	/**
	 * Resolve course ID from a Lifter order.
	 *
	 * @since 2.6.0
	 *
	 * @param int $order_id Lifter order ID.
	 *
	 * @return int
	 */
	public static function get_order_course_id( int $order_id ): int {
		$product_id = (int) get_post_meta( $order_id, '_llms_product_id', true );
		if ( $product_id < 1 ) {
			return 0;
		}

		$product = get_post( $product_id );
		if ( ! $product ) {
			return 0;
		}

		$course_post_type = function_exists( 'tutor' ) ? tutor()->course_post_type : 'courses';
		if ( $course_post_type === $product->post_type ) {
			return $product_id;
		}

		if ( get_post_meta( $product_id, '_was_lif_course', true ) ) {
			return $product_id;
		}

		if ( 'course' === $product->post_type ) {
			return $product_id;
		}

		return 0;
	}

	/**
	 * IDs of recurring Lifter course orders pending/available for migration.
	 *
	 * @since 2.6.0
	 *
	 * @return int[]
	 */
	public static function get_recurring_order_ids(): array {
		global $wpdb;

		$statuses  = self::migratable_recurring_statuses();
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ot
					ON ot.post_id = p.ID AND ot.meta_key = '_llms_order_type' AND ot.meta_value = 'recurring'
				WHERE p.post_type = 'llms_order'
					AND p.post_status IN ({$status_in})
				ORDER BY p.ID ASC",
				$statuses
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( ! is_array( $ids ) ) {
			return array();
		}

		$filtered = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( self::is_recurring_course_order( $id ) ) {
				$filtered[] = $id;
			}
		}

		return $filtered;
	}

	/**
	 * Succeeded Lifter transaction IDs for an order, oldest first.
	 *
	 * @since 2.6.0
	 *
	 * @param int $llms_order_id Lifter order ID.
	 *
	 * @return int[]
	 */
	public static function get_succeeded_transaction_ids( int $llms_order_id ): array {
		global $wpdb;

		if ( $llms_order_id < 1 ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} om
					ON om.post_id = p.ID AND om.meta_key = '_llms_order_id' AND om.meta_value = %s
				WHERE p.post_type = 'llms_transaction'
					AND p.post_status = 'llms-txn-succeeded'
				ORDER BY p.post_date_gmt ASC, p.ID ASC",
				(string) $llms_order_id
			)
		);

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'intval', $ids )
			)
		);
	}

	/**
	 * Convert a datetime string to GMT MySQL format.
	 *
	 * @since 2.6.0
	 *
	 * @param mixed $value Raw datetime.
	 *
	 * @return string Empty when unparseable.
	 */
	public static function to_gmt_datetime( $value ): string {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );
		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
