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

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use TUTOR\Earnings;
use Tutor\Models\OrderMetaModel;
use Tutor\Models\OrderModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles migration of Sensei course purchases from WooCommerce orders into
 * Tutor LMS native ecommerce (tutor_orders, tutor_order_items, tutor_ordermeta,
 * tutor_earnings) while also ensuring tutor_enrolled records exist and are
 * linked back to the new native order.
 */
class OrderMigrator {

	/**
	 * Post meta key on the WC order post after a successful native order migration.
	 *
	 * Used for idempotency: re-running the migrator skips orders already processed.
	 *
	 * @var string
	 */
	const META_MIGRATED_ORDER_ID = '_tlmt_sensei_migrated_to_native_order_id';

	/**
	 * Order meta key that back-references the original WC order ID.
	 *
	 * @var string
	 */
	const ORDERMETA_WC_ORDER_ID = '_tlmt_wc_order_id';

	/**
	 * Tutor OrderModel instance (null when Tutor ecommerce is unavailable).
	 *
	 * @var OrderModel|null
	 */
	private $order_model;

	/**
	 * Tutor Earnings instance (null when unavailable).
	 *
	 * @var Earnings|null
	 */
	private $earnings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->order_model = class_exists( OrderModel::class ) ? new OrderModel() : null;
		$this->earnings    = class_exists( Earnings::class ) ? Earnings::get_instance() : null;
	}

	/**
	 * Migrate WooCommerce orders for Sensei courses in batches.
	 *
	 * @param int $offset Pagination offset.
	 * @param int $limit  Batch limit.
	 * @return array{migrated: int, total: int, remaining: int, has_more: bool}
	 */
	public function migrate( int $offset = 0, int $limit = 50 ): array {
		global $wpdb;

		$course_product_map = $this->get_course_product_map();
		if ( empty( $course_product_map ) ) {
			return array(
				'migrated'  => 0,
				'total'     => 0,
				'remaining' => 0,
				'has_more'  => false,
			);
		}

		$product_ids = array_keys( $course_product_map );
		$format_pids = implode( ',', array_map( 'intval', $product_ids ) );

		// All WC statuses that represent a meaningful order we should migrate.
		$wc_statuses = "'wc-completed','wc-processing','wc-refunded','wc-cancelled','wc-failed','wc-pending'";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT oi.order_id, p.post_date, p.post_status
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
					ON oi.order_item_id = oim.order_item_id
					AND oim.meta_key IN ('_product_id', '_variation_id')
				INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
				WHERE oim.meta_value IN ({$format_pids})
				AND p.post_status IN ({$wc_statuses})
				ORDER BY oi.order_id ASC
				LIMIT %d, %d",
				$offset,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$migrated = 0;

		if ( ! empty( $orders ) ) {
			foreach ( $orders as $order ) {
				$order_id = (int) $order->order_id;
				if ( $order_id <= 0 ) {
					continue;
				}

				// Idempotency: skip WC orders that already have a Tutor native order.
				$existing = get_post_meta( $order_id, self::META_MIGRATED_ORDER_ID, true );
				if ( '' !== $existing && false !== $existing ) {
					++$migrated;
					continue;
				}

				$customer_id = (int) get_post_meta( $order_id, '_customer_user', true );
				if ( $customer_id <= 0 ) {
					continue;
				}

				$order_date = ! empty( $order->post_date ) ? $order->post_date : current_time( 'mysql' );
				$wc_status  = (string) $order->post_status;

				// Get per-item prices for course-linked products in this order.
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				$product_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT oim_pid.meta_value AS product_id,
							oim_sub.meta_value AS line_subtotal,
							oim_tot.meta_value AS line_total
						FROM {$wpdb->prefix}woocommerce_order_items oi
						INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_pid
							ON oi.order_item_id = oim_pid.order_item_id
							AND oim_pid.meta_key = '_product_id'
						LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_sub
							ON oi.order_item_id = oim_sub.order_item_id
							AND oim_sub.meta_key = '_line_subtotal'
						LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_tot
							ON oi.order_item_id = oim_tot.order_item_id
							AND oim_tot.meta_key = '_line_total'
						WHERE oi.order_id = %d
						AND oim_pid.meta_value IN ({$format_pids})",
						$order_id
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

				if ( empty( $product_rows ) ) {
					continue;
				}

				// Build Tutor line items array.
				$items = array();
				foreach ( $product_rows as $row ) {
					$pid       = (int) $row->product_id;
					$course_id = $course_product_map[ $pid ] ?? 0;
					if ( $course_id <= 0 ) {
						continue;
					}
					$regular    = (float) $row->line_subtotal;
					$line_total = (float) $row->line_total;
					$items[]    = array(
						'course_id'     => $course_id,
						'regular_price' => $regular > 0 ? $regular : $line_total,
						'sale_price'    => ( $line_total > 0 && $regular > 0 && $line_total < $regular ) ? $line_total : null,
					);
				}

				if ( empty( $items ) ) {
					continue;
				}

				// Attempt to create a Tutor native order for this WC order.
				$tutor_order_id = 0;
				if ( $this->order_model ) {
					try {
						$tutor_order_id = $this->create_native_order( $order_id, $customer_id, $wc_status, $order_date, $items );
					} catch ( \Throwable $th ) {
						ErrorHandler::set_error(
							ContentTypes::ORDERS,
							sprintf(
								/* translators: 1: WC order ID, 2: error message */
								__( 'WC order #%1$d failed native order creation: %2$s', 'tutor-lms-migration-tool' ),
								$order_id,
								$th->getMessage()
							)
						);
					}
				}

				// Ensure tutor_enrolled posts exist and are linked to the native order.
				foreach ( $items as $item ) {
					$course_id = (int) $item['course_id'];
					if ( $course_id <= 0 ) {
						continue;
					}

					$link_order_id = $tutor_order_id > 0 ? $tutor_order_id : $order_id;

					$existing_enroll_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts}
							WHERE post_type = 'tutor_enrolled'
							AND post_author = %d
							AND post_parent = %d
							AND post_status = 'completed'
							LIMIT 1",
							$customer_id,
							$course_id
						)
					);

					if ( $existing_enroll_id ) {
						// Backfill order link on an enrollment created earlier by the free-enroll migrator.
						if ( ! get_post_meta( (int) $existing_enroll_id, '_tutor_enrolled_by_order_id', true ) ) {
							update_post_meta( (int) $existing_enroll_id, '_tutor_enrolled_by_order_id', $link_order_id );
						}
					} else {
						// Create the enrollment for a paid course.
						/* translators: %d: WC order ID */
						$enroll_title  = sprintf( __( 'Course Enrolled (Order #%d)', 'tutor-lms-migration-tool' ), $order_id );
						$new_enroll_id = wp_insert_post(
							array(
								'post_type'   => 'tutor_enrolled',
								'post_title'  => $enroll_title,
								'post_status' => 'completed',
								'post_author' => $customer_id,
								'post_parent' => $course_id,
								'post_date'   => $order_date,
							)
						);

						if ( $new_enroll_id && ! is_wp_error( $new_enroll_id ) ) {
							// Force 'completed' via direct DB write, bypassing Tutor's save_post hook
							// that sets paid-course enrollments to 'cancel' without a native order.
							$wpdb->update(
								$wpdb->posts,
								array( 'post_status' => 'completed' ),
								array( 'ID' => $new_enroll_id ),
								array( '%s' ),
								array( '%d' )
							);
							clean_post_cache( $new_enroll_id );
							update_post_meta( $new_enroll_id, '_tutor_enrolled_by', 'sensei_migration' );
							update_post_meta( $new_enroll_id, '_tutor_enrolled_by_order_id', $link_order_id );
							update_user_meta( $customer_id, '_is_tutor_student', $order_date );
						}
					}
				}

				// Store instructor earnings for paid orders.
				if ( $tutor_order_id > 0 ) {
					$this->store_earnings( $tutor_order_id );
				}

				// Mark WC order as processed (idempotency guard).
				update_post_meta( $order_id, self::META_MIGRATED_ORDER_ID, $tutor_order_id > 0 ? $tutor_order_id : -1 );

				++$migrated;
			}
		}

		// Calculate total eligible WC order count.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$total_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT oi.order_id)
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
				ON oi.order_item_id = oim.order_item_id
				AND oim.meta_key IN ('_product_id', '_variation_id')
			INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
			WHERE oim.meta_value IN ({$format_pids})
			AND p.post_status IN ({$wc_statuses})"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$remaining = max( 0, $total_count - ( $offset + count( $orders ) ) );

		return array(
			'migrated'  => $migrated,
			'total'     => $total_count,
			'remaining' => $remaining,
			'has_more'  => $remaining > 0,
		);
	}

	/**
	 * Insert a Tutor native order + order items + order meta for one WC order.
	 *
	 * @param int    $wc_order_id WC order post ID.
	 * @param int    $buyer_id    Customer user ID.
	 * @param string $wc_status   WC post_status (e.g. wc-completed).
	 * @param string $order_date  MySQL datetime from WC post_date.
	 * @param array  $items       Line items: [{course_id, regular_price, sale_price}].
	 * @return int Tutor order ID.
	 * @throws \RuntimeException When OrderModel::create_order() returns 0.
	 */
	private function create_native_order( int $wc_order_id, int $buyer_id, string $wc_status, string $order_date, array $items ): int {
		$statuses = StatusMapper::map( $wc_status );

		$order_total = (float) get_post_meta( $wc_order_id, '_order_total', true );
		$subtotal    = (float) array_sum( array_column( $items, 'regular_price' ) );
		if ( $subtotal <= 0 ) {
			$subtotal = $order_total;
		}

		$discount = (float) get_post_meta( $wc_order_id, '_cart_discount', true );
		if ( $discount <= 0 ) {
			$discount = (float) get_post_meta( $wc_order_id, '_discount_total', true );
		}

		$gateway        = (string) get_post_meta( $wc_order_id, '_payment_method', true );
		$gateway_title  = (string) get_post_meta( $wc_order_id, '_payment_method_title', true );
		$transaction_id = (string) get_post_meta( $wc_order_id, '_transaction_id', true );
		if ( '' === $transaction_id ) {
			$transaction_id = (string) $wc_order_id;
		}

		$payment_method = '' !== $gateway_title ? $gateway_title : PaymentMethodMapper::map( $gateway );
		$created_gmt    = $this->to_gmt( $order_date );

		$order_items = array();
		foreach ( $items as $item ) {
			$order_items[] = array(
				'item_id'       => (int) $item['course_id'],
				'regular_price' => (float) $item['regular_price'],
				'sale_price'    => $item['sale_price'],
			);
		}

		$data = array(
			'parent_id'        => 0,
			'transaction_id'   => $transaction_id,
			'user_id'          => $buyer_id,
			'order_type'       => OrderModel::TYPE_SINGLE_ORDER,
			'order_status'     => $statuses['order_status'],
			'payment_status'   => $statuses['payment_status'],
			'subtotal_price'   => $subtotal,
			'pre_tax_price'    => $order_total,
			'tax_rate'         => 0,
			'tax_amount'       => 0,
			'total_price'      => $order_total,
			'net_payment'      => $order_total,
			'coupon_code'      => null,
			'coupon_amount'    => $discount > 0 ? $discount : 0,
			'discount_amount'  => 0,
			'fees'             => 0,
			'earnings'         => $order_total,
			'refund_amount'    => 0,
			'payment_method'   => $payment_method,
			'payment_payloads' => wp_json_encode(
				array(
					'source'         => 'woocommerce',
					'wc_order_id'    => $wc_order_id,
					'wc_gateway'     => $gateway,
					'transaction_id' => $transaction_id,
				)
			),
			'note'             => '',
			'created_at_gmt'   => $created_gmt,
			'created_by'       => $buyer_id,
			'updated_at_gmt'   => $created_gmt,
			'updated_by'       => $buyer_id,
			'items'            => $order_items,
		);

		$tutor_order_id = (int) $this->order_model->create_order( $data );
		if ( $tutor_order_id < 1 ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: WC order ID */
					__( 'Failed to create Tutor native order for WC order #%d.', 'tutor-lms-migration-tool' ),
					$wc_order_id
				)
			);
		}

		OrderMetaModel::add_meta( $tutor_order_id, self::ORDERMETA_WC_ORDER_ID, $wc_order_id );
		OrderMetaModel::add_meta(
			$tutor_order_id,
			OrderModel::META_KEY_BILLING_ADDRESS,
			wp_json_encode( $this->build_billing_address( $buyer_id, $wc_order_id ) )
		);
		OrderMetaModel::add_meta(
			$tutor_order_id,
			'history',
			sprintf(
				/* translators: %d: WC order ID */
				__( 'Order migrated from WooCommerce #%d (Sensei LMS)', 'tutor-lms-migration-tool' ),
				$wc_order_id
			)
		);

		return $tutor_order_id;
	}

	/**
	 * Store instructor earnings for a paid Tutor native order.
	 *
	 * @param int $tutor_order_id Tutor order ID.
	 * @return void
	 */
	private function store_earnings( int $tutor_order_id ): void {
		if ( $tutor_order_id < 1 || ! $this->earnings || ! $this->order_model ) {
			return;
		}

		$order = $this->order_model->get_order_by_id( $tutor_order_id );
		if ( ! $order || OrderModel::PAYMENT_PAID !== ( $order->payment_status ?? '' ) ) {
			return;
		}

		try {
			$this->earnings->prepare_order_earnings( $tutor_order_id );
			$this->earnings->remove_before_store_earnings();
		} catch ( \Throwable $th ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Earnings are best-effort; order migration should still succeed.
		}
	}

	/**
	 * Build billing address array from WC order meta, falling back to WP user data.
	 *
	 * @param int $buyer_id    Customer user ID.
	 * @param int $wc_order_id WC order post ID.
	 * @return array<string, string>
	 */
	private function build_billing_address( int $buyer_id, int $wc_order_id ): array {
		$first = (string) get_post_meta( $wc_order_id, '_billing_first_name', true );
		$last  = (string) get_post_meta( $wc_order_id, '_billing_last_name', true );
		$email = (string) get_post_meta( $wc_order_id, '_billing_email', true );
		$phone = (string) get_post_meta( $wc_order_id, '_billing_phone', true );

		if ( $buyer_id > 0 ) {
			$user = get_userdata( $buyer_id );
			if ( $user ) {
				if ( '' === $first ) {
					$first = (string) $user->first_name;
				}
				if ( '' === $last ) {
					$last = (string) $user->last_name;
				}
				if ( '' === $email ) {
					$email = (string) $user->user_email;
				}
				if ( '' === $first && '' === $last ) {
					$first = (string) $user->display_name;
				}
			}
		}

		return array(
			'billing_first_name' => $first,
			'billing_last_name'  => $last,
			'billing_email'      => $email,
			'billing_phone'      => $phone,
			'billing_zip_code'   => (string) get_post_meta( $wc_order_id, '_billing_postcode', true ),
			'billing_address'    => trim(
				(string) get_post_meta( $wc_order_id, '_billing_address_1', true ) . ' ' .
				(string) get_post_meta( $wc_order_id, '_billing_address_2', true )
			),
			'billing_country'    => (string) get_post_meta( $wc_order_id, '_billing_country', true ),
			'billing_state'      => (string) get_post_meta( $wc_order_id, '_billing_state', true ),
			'billing_city'       => (string) get_post_meta( $wc_order_id, '_billing_city', true ),
		);
	}

	/**
	 * Convert a local MySQL datetime to UTC.
	 *
	 * @param string $local MySQL datetime in site local time.
	 * @return string UTC datetime string.
	 */
	private function to_gmt( string $local ): string {
		if ( ! empty( $local ) && '0000-00-00 00:00:00' !== $local ) {
			$timestamp = strtotime( $local );
			if ( false !== $timestamp ) {
				return gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Map WooCommerce product ID => Sensei course ID.
	 *
	 * @return array<int, int>
	 */
	private function get_course_product_map(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id AS course_id, meta_value AS product_id
				FROM {$wpdb->postmeta}
				WHERE meta_key = %s
				AND meta_value > 0",
				'_course_woocommerce_product'
			)
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
