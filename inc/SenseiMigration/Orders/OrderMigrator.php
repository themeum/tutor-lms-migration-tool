<?php
/**
 * Sensei LMS Orders Migrator.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration\Orders;

use Themeum\TutorLMSMigrationTool\Interfaces\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles migration of Sensei course purchases from WooCommerce orders.
 */
class OrderMigrator implements Order {

	/**
	 * Migrate orders in batches.
	 *
	 * @param int $offset Offset number.
	 * @param int $limit  Batch limit.
	 * @return array{migrated: int, remaining: int, has_more: bool}
	 */
	public function migrate( int $offset = 0, int $limit = 50 ): array {
		global $wpdb;

		// 1. Get WooCommerce product IDs linked to Sensei courses
		$course_product_map = $this->get_course_product_map();
		if ( empty( $course_product_map ) ) {
			return array(
				'migrated'  => 0,
				'remaining' => 0,
				'has_more'  => false,
			);
		}

		$product_ids = array_keys( $course_product_map );
		$format_pids = implode( ',', array_map( 'intval', $product_ids ) );

		// 2. Query WooCommerce order items associated with these products
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.order_id, oim.meta_value AS product_id, p.post_date, p.post_status
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
					ON oi.order_item_id = oim.order_item_id
					AND oim.meta_key IN ('_product_id', '_variation_id')
				INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
				WHERE oim.meta_value IN ({$format_pids})
				AND p.post_status IN ('wc-completed', 'wc-processing')
				ORDER BY oi.order_id ASC
				LIMIT %d, %d",
				$offset,
				$limit
			)
		);

		$migrated = 0;
		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				$pid       = (int) $item->product_id;
				$course_id = $course_product_map[ $pid ] ?? 0;
				$order_id  = (int) $item->order_id;

				if ( $course_id <= 0 ) {
					continue;
				}

				// Get customer ID
				$customer_id = (int) get_post_meta( $order_id, '_customer_user', true );
				if ( $customer_id <= 0 ) {
					continue;
				}

				// Ensure student enrollment without duplicate trigger
				if ( function_exists( 'tutor_utils' ) && ! tutor_utils()->is_enrolled( $course_id, $customer_id ) ) {
					$order_date = ! empty( $item->post_date ) ? $item->post_date : current_time( 'mysql' );
					wp_insert_post(
						array(
							'post_type'   => 'tutor_enrolled',
							'post_title'  => sprintf( __( 'Course Enrolled (Order #%d)', 'tutor-lms-migration-tool' ), $order_id ),
							'post_status' => 'completed',
							'post_author' => $customer_id,
							'post_parent' => $course_id,
							'post_date'   => $order_date,
						)
					);
					update_user_meta( $customer_id, '_is_tutor_student', $order_date );
				}

				$migrated++;
			}
		}

		// Calculate remaining count
		$total_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT oi.order_id)
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
				ON oi.order_item_id = oim.order_item_id
				AND oim.meta_key IN ('_product_id', '_variation_id')
			INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
			WHERE oim.meta_value IN ({$format_pids})
			AND p.post_status IN ('wc-completed', 'wc-processing')"
		);

		$remaining = max( 0, $total_count - ( $offset + $limit ) );

		return array(
			'migrated'  => $migrated,
			'remaining' => $remaining,
			'has_more'  => $remaining > 0,
		);
	}

	/**
	 * Map WooCommerce product ID => Course ID.
	 *
	 * @return array<int, int>
	 */
	private function get_course_product_map(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT post_id AS course_id, meta_value AS product_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_course_woocommerce_product'
			AND meta_value > 0"
		);

		$map = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$map[ (int) $row->product_id ] = (int) $row->course_id;
			}
		}

		return $map;
	}
}
