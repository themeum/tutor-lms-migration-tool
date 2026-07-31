<?php
/**
 * LearnPress → Tutor native ecommerce order migrator.
 *
 * Migrates single-course LP orders into tutor_orders / tutor_order_items /
 * tutor_ordermeta without firing live checkout hooks (no double enrollment).
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LPMigration\Orders;

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use Tutor\Models\EnrollmentModel;
use Tutor\Models\OrderMetaModel;
use Tutor\Models\OrderModel;

defined( 'ABSPATH' ) || exit;

/**
 * Native order migrator for LearnPress.
 *
 * @since 2.5.0
 */
class OrderMigrator {

	/**
	 * Post meta on lp_order: Tutor order id(s) after successful migration.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const META_MIGRATED_ORDER_ID = '_tlmt_migrated_to_native_order_id';

	/**
	 * Post meta on lp_order when skipped (no migratable items).
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const META_SKIP_REASON = '_tlmt_native_order_skip_reason';

	/**
	 * Ordermeta key storing the source LP order id.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	const ORDERMETA_LP_ORDER_ID = '_tlmt_lp_order_id';

	/**
	 * Tutor OrderModel.
	 *
	 * @since 2.5.0
	 *
	 * @var OrderModel
	 */
	private $order_model;

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {
		$this->order_model = new OrderModel();
	}

	/**
	 * Count LP orders still pending native migration.
	 *
	 * @since 2.5.0
	 *
	 * @return int
	 */
	public function get_remaining_count(): int {
		global $wpdb;

		$statuses      = StatusMapper::migratable_lp_statuses();
		$status_in     = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$migrated_meta = self::META_MIGRATED_ORDER_ID;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT COUNT(p.ID)
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_type = 'lp_order'
				AND p.post_status IN ({$status_in})
				AND pm.meta_id IS NULL",
			array_merge( array( $migrated_meta ), $statuses )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Fetch a batch of unmigrated LP orders.
	 *
	 * @since 2.5.0
	 *
	 * @param int $limit Batch size.
	 *
	 * @return array<int, \WP_Post>
	 */
	public function get_batch( int $limit ): array {
		global $wpdb;

		if ( $limit < 1 ) {
			return array();
		}

		$statuses      = StatusMapper::migratable_lp_statuses();
		$status_in     = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$migrated_meta = self::META_MIGRATED_ORDER_ID;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT p.*
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_type = 'lp_order'
				AND p.post_status IN ({$status_in})
				AND pm.meta_id IS NULL
			ORDER BY p.ID ASC
			LIMIT %d",
			array_merge( array( $migrated_meta ), $statuses, array( $limit ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$rows = $wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$orders = array();
		foreach ( $rows as $row ) {
			$orders[] = new \WP_Post( $row );
		}

		return $orders;
	}

	/**
	 * Migrate one LP order into Tutor native ecommerce.
	 *
	 * Idempotent: if already marked migrated, returns existing id without insert.
	 * The source lp_order is removed on success; skipped or failed orders are
	 * left in place with a skip reason. Does not fire tutor_order_placed.
	 *
	 * @since 2.5.0
	 *
	 * @param \WP_Post|object $lp_order LP order post.
	 *
	 * @return int Tutor order id, or 0 when skipped.
	 *
	 * @throws \Throwable When insert fails unexpectedly.
	 */
	public function migrate_order( $lp_order ): int {
		$lp_order_id = (int) ( $lp_order->ID ?? 0 );
		if ( $lp_order_id < 1 ) {
			return 0;
		}

		$existing = get_post_meta( $lp_order_id, self::META_MIGRATED_ORDER_ID, true );
		if ( '' !== $existing && false !== $existing && null !== $existing ) {
			return (int) $existing;
		}

		$buyer_ids = $this->resolve_buyer_ids( $lp_order_id );
		$items     = $this->get_course_line_items( $lp_order_id );

		if ( empty( $items ) ) {
			update_post_meta( $lp_order_id, self::META_MIGRATED_ORDER_ID, 0 );
			update_post_meta( $lp_order_id, self::META_SKIP_REASON, 'no_course_items' );
			ErrorHandler::set_error(
				ContentTypes::ORDERS,
				sprintf(
					/* translators: %d: LearnPress order ID */
					__( 'LP order #%d skipped: no migratable course line items.', 'tutor-lms-migration-tool' ),
					$lp_order_id
				)
			);
			return 0;
		}

		// One Tutor order per buyer when LP stores multi-user _user_id.
		// Empty buyer list → guest order with user_id 0 (sales history only).
		if ( empty( $buyer_ids ) ) {
			$buyer_ids = array( 0 );
		}

		$created_ids = array();

		foreach ( $buyer_ids as $buyer_id ) {
			try {
				$tutor_order_id = $this->create_native_order( $lp_order, (int) $buyer_id, $items );
				if ( $tutor_order_id > 0 ) {
					$created_ids[] = $tutor_order_id;
					$this->link_enrollments( $tutor_order_id, (int) $buyer_id, $items, $lp_order_id );
				}
			} catch ( \Throwable $th ) {
				ErrorHandler::set_error(
					ContentTypes::ORDERS,
					sprintf(
						/* translators: 1: LP order ID, 2: user ID, 3: error message */
						__( 'LP order #%1$d buyer #%2$d failed: %3$s', 'tutor-lms-migration-tool' ),
						$lp_order_id,
						(int) $buyer_id,
						$th->getMessage()
					)
				);
			}
		}

		if ( empty( $created_ids ) ) {
			update_post_meta( $lp_order_id, self::META_MIGRATED_ORDER_ID, 0 );
			update_post_meta( $lp_order_id, self::META_SKIP_REASON, 'create_failed' );
			return 0;
		}

		$primary_id = (int) $created_ids[0];

		// Marked before removal so a failed delete cannot re-queue the order.
		update_post_meta( $lp_order_id, self::META_MIGRATED_ORDER_ID, $primary_id );
		delete_post_meta( $lp_order_id, self::META_SKIP_REASON );

		$this->remove_lp_order( $lp_order_id );

		return $primary_id;
	}

	/**
	 * Remove a LearnPress order once it has been migrated.
	 *
	 * Rows are deleted directly instead of via wp_delete_post() because
	 * LearnPress cascades order deletion into learnpress_user_items, which
	 * still holds the enrollment and lesson progress source data.
	 *
	 * @since 2.5.0
	 *
	 * @param int $lp_order_id LP order id.
	 *
	 * @return void
	 */
	private function remove_lp_order( int $lp_order_id ): void {
		global $wpdb;

		$items_table    = $wpdb->prefix . 'learnpress_order_items';
		$itemmeta_table = $wpdb->prefix . 'learnpress_order_itemmeta';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE oim
				FROM {$itemmeta_table} oim
				INNER JOIN {$items_table} oi ON oi.order_item_id = oim.learnpress_order_item_id
				WHERE oi.order_id = %d",
				$lp_order_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$wpdb->delete( $items_table, array( 'order_id' => $lp_order_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $lp_order_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->posts, array( 'ID' => $lp_order_id ), array( '%d' ) );

		wp_cache_delete( $lp_order_id, 'posts' );
		wp_cache_delete( $lp_order_id, 'post_meta' );
	}

	/**
	 * Insert a Tutor native order + items + meta for one buyer.
	 *
	 * @since 2.5.0
	 *
	 * @param \WP_Post|object     $lp_order LP order post.
	 * @param int                 $buyer_id WP user id (0 = guest).
	 * @param array<int, array>   $items    Prepared line items.
	 *
	 * @return int New Tutor order id.
	 *
	 * @throws \Throwable On database failure.
	 */
	private function create_native_order( $lp_order, int $buyer_id, array $items ): int {
		$lp_order_id = (int) $lp_order->ID;
		$statuses    = StatusMapper::map( (string) $lp_order->post_status );

		$subtotal = (float) get_post_meta( $lp_order_id, '_order_subtotal', true );
		$total    = (float) get_post_meta( $lp_order_id, '_order_total', true );
		$refunded = (float) get_post_meta( $lp_order_id, '_lp_refunded_amount', true );

		if ( $subtotal <= 0 ) {
			$subtotal = $this->sum_item_amounts( $items, 'subtotal' );
		}
		if ( $total <= 0 ) {
			$total = $this->sum_item_amounts( $items, 'total' );
		}
		if ( $refunded < 0 ) {
			$refunded = 0;
		}
		if ( $refunded > $total ) {
			$refunded = $total;
		}

		$lp_method = (string) get_post_meta( $lp_order_id, '_payment_method', true );
		$txn_id    = (string) get_post_meta( $lp_order_id, '_transaction_id', true );

		$created_gmt = $this->normalize_gmt_datetime( $lp_order->post_date_gmt ?? '', $lp_order->post_date ?? '' );
		$updated_gmt = $this->normalize_gmt_datetime( $lp_order->post_modified_gmt ?? '', $lp_order->post_modified ?? '' );

		$actor_id = $buyer_id > 0 ? $buyer_id : (int) ( $lp_order->post_author ?? 0 );

		$order_items = array();
		foreach ( $items as $item ) {
			$order_items[] = array(
				'item_id'       => (int) $item['course_id'],
				'regular_price' => (float) $item['regular_price'],
				'sale_price'    => $item['sale_price'],
			);
		}

		$data = array(
			'parent_id'        => (int) ( $lp_order->post_parent ?? 0 ),
			'transaction_id'   => $txn_id,
			'user_id'          => $buyer_id,
			'order_type'       => OrderModel::TYPE_SINGLE_ORDER,
			'order_status'     => $statuses['order_status'],
			'payment_status'   => $statuses['payment_status'],
			'subtotal_price'   => $subtotal,
			'pre_tax_price'    => $subtotal,
			'tax_rate'         => 0,
			'tax_amount'       => 0,
			'total_price'      => $total,
			'net_payment'      => max( 0, $total - $refunded ),
			'coupon_amount'    => 0,
			'discount_amount'  => 0,
			'fees'             => 0,
			'earnings'         => max( 0, $total - $refunded ),
			'refund_amount'    => $refunded,
			'payment_method'   => PaymentMethodMapper::map( $lp_method, $total ),
			'payment_payloads' => wp_json_encode( $this->build_payment_payload( $lp_order_id, $lp_method ) ),
			'note'             => isset( $lp_order->post_excerpt ) ? (string) $lp_order->post_excerpt : '',
			'created_at_gmt'   => $created_gmt,
			'created_by'       => $actor_id,
			'updated_at_gmt'   => $updated_gmt,
			'updated_by'       => $actor_id,
			'items'            => $order_items,
		);

		$tutor_order_id = (int) $this->order_model->create_order( $data );
		if ( $tutor_order_id < 1 ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: LearnPress order ID */
					__( 'Failed to create Tutor native order for LP order #%d.', 'tutor-lms-migration-tool' ),
					$lp_order_id
				)
			);
		}

		OrderMetaModel::add_meta( $tutor_order_id, self::ORDERMETA_LP_ORDER_ID, $lp_order_id );
		OrderMetaModel::add_meta(
			$tutor_order_id,
			OrderModel::META_KEY_BILLING_ADDRESS,
			wp_json_encode( $this->build_billing_address( $buyer_id, $lp_order_id ) )
		);
		OrderMetaModel::add_meta(
			$tutor_order_id,
			'history',
			__( 'Order migrated from LearnPress', 'tutor-lms-migration-tool' )
		);

		return $tutor_order_id;
	}

	/**
	 * Link existing Tutor enrollments to the new native order (no re-enroll).
	 *
	 * Only for paid/completed-equivalent orders with a real user.
	 * Replaces a provisional LP order ID written during enrollment migration.
	 *
	 * @since 2.5.0
	 *
	 * @param int               $tutor_order_id Tutor order id.
	 * @param int               $buyer_id       Buyer user id.
	 * @param array<int, array> $items          Line items with course_id.
	 * @param int               $lp_order_id    Source LearnPress order id.
	 *
	 * @return void
	 */
	private function link_enrollments( int $tutor_order_id, int $buyer_id, array $items, int $lp_order_id = 0 ): void {
		if ( $buyer_id < 1 || $tutor_order_id < 1 ) {
			return;
		}

		$order = $this->order_model->get_order_by_id( $tutor_order_id );
		if ( ! $order ) {
			return;
		}

		$is_paid = OrderModel::PAYMENT_PAID === ( $order->payment_status ?? '' )
			|| OrderModel::PAYMENT_REFUNDED === ( $order->payment_status ?? '' )
			|| OrderModel::PAYMENT_PARTIALLY_REFUNDED === ( $order->payment_status ?? '' );

		if ( ! $is_paid ) {
			return;
		}

		foreach ( $items as $item ) {
			$course_id = (int) $item['course_id'];
			if ( $course_id < 1 ) {
				continue;
			}

			$enrollment = EnrollmentModel::get_enrolled_data( $buyer_id, $course_id, '' );
			if ( ! $enrollment || empty( $enrollment->ID ) ) {
				continue;
			}

			// Do not overwrite a different order link — except provisional LP order IDs.
			$existing_order_id = (int) get_post_meta( $enrollment->ID, EnrollmentModel::ENROLLMENT_ORDER_ID_META, true );
			if ( $existing_order_id > 0 && $existing_order_id !== $tutor_order_id ) {
				$is_provisional_lp = $lp_order_id > 0 && $existing_order_id === $lp_order_id;
				if ( ! $is_provisional_lp ) {
					continue;
				}
			}

			update_post_meta( $enrollment->ID, EnrollmentModel::ENROLLMENT_ORDER_ID_META, $tutor_order_id );
		}
	}

	/**
	 * Resolve buyer user id(s) from LP `_user_id` meta.
	 *
	 * @since 2.5.0
	 *
	 * @param int $lp_order_id LP order id.
	 *
	 * @return int[] Unique positive user ids (may be empty for guests).
	 */
	private function resolve_buyer_ids( int $lp_order_id ): array {
		$raw = get_post_meta( $lp_order_id, '_user_id', true );

		if ( is_array( $raw ) ) {
			$ids = array_map( 'intval', $raw );
		} elseif ( is_numeric( $raw ) ) {
			$ids = array( (int) $raw );
		} else {
			$unserialized = maybe_unserialize( $raw );
			if ( is_array( $unserialized ) ) {
				$ids = array_map( 'intval', $unserialized );
			} elseif ( is_numeric( $unserialized ) ) {
				$ids = array( (int) $unserialized );
			} else {
				$ids = array();
			}
		}

		$ids = array_values(
			array_unique(
				array_filter(
					$ids,
					static function ( $id ) {
						return (int) $id > 0;
					}
				)
			)
		);

		return $ids;
	}

	/**
	 * Load migratable course line items for an LP order.
	 *
	 * @since 2.5.0
	 *
	 * @param int $lp_order_id LP order id.
	 *
	 * @return array<int, array{course_id: int, regular_price: float, sale_price: float|null, subtotal: float, total: float}>
	 */
	private function get_course_line_items( int $lp_order_id ): array {
		global $wpdb;

		$items_table = $wpdb->prefix . 'learnpress_order_items';
		$meta_table  = $wpdb->prefix . 'learnpress_order_itemmeta';

		// Prefer LP4 item_id column; fall back to _course_id meta.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					oi.order_item_id,
					oi.item_id AS column_item_id,
					MAX( CASE WHEN oim.meta_key = '_course_id' THEN oim.meta_value END ) AS meta_course_id,
					MAX( CASE WHEN oim.meta_key = '_quantity' THEN oim.meta_value END ) AS quantity,
					MAX( CASE WHEN oim.meta_key = '_subtotal' THEN oim.meta_value END ) AS subtotal,
					MAX( CASE WHEN oim.meta_key = '_total' THEN oim.meta_value END ) AS total
				FROM {$items_table} oi
				LEFT JOIN {$meta_table} oim
					ON oim.learnpress_order_item_id = oi.order_item_id
				WHERE oi.order_id = %d
				GROUP BY oi.order_item_id, oi.item_id",
				$lp_order_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$course_post_type = tutor()->course_post_type;
		$prepared         = array();

		foreach ( $rows as $row ) {
			$course_id = (int) ( $row->column_item_id ?? 0 );
			if ( $course_id < 1 ) {
				$course_id = (int) ( $row->meta_course_id ?? 0 );
			}

			if ( $course_id < 1 ) {
				continue;
			}

			$course = get_post( $course_id );
			if ( ! $course || $course_post_type !== $course->post_type ) {
				// Allow not-yet-converted only if flagged as former LP course.
				if ( ! $course || ! get_post_meta( $course_id, '_was_lp_course', true ) ) {
					ErrorHandler::set_error(
						ContentTypes::ORDERS,
						sprintf(
							/* translators: 1: LP order ID, 2: course ID */
							__( 'LP order #%1$d: course #%2$d is not a Tutor course; line skipped.', 'tutor-lms-migration-tool' ),
							$lp_order_id,
							$course_id
						)
					);
					continue;
				}
			}

			$qty      = max( 1, (int) ( $row->quantity ?? 1 ) );
			$subtotal = (float) ( $row->subtotal ?? 0 );
			$total    = (float) ( $row->total ?? 0 );

			$unit_regular = $qty > 0 ? round( $subtotal / $qty, 2 ) : $subtotal;
			$unit_paid    = $qty > 0 ? round( $total / $qty, 2 ) : $total;

			// Prefer course catalog regular price when available.
			$catalog_regular = (float) get_post_meta( $course_id, '_lp_regular_price', true );
			if ( $catalog_regular <= 0 ) {
				$catalog_regular = (float) get_post_meta( $course_id, 'tutor_course_price', true );
			}
			if ( $catalog_regular > 0 ) {
				$unit_regular = $catalog_regular;
			}

			$sale_price = null;
			if ( $unit_paid > 0 && $unit_paid < $unit_regular ) {
				$sale_price = $unit_paid;
			} elseif ( $unit_paid > 0 && $unit_regular <= 0 ) {
				$unit_regular = $unit_paid;
			}

			$prepared[] = array(
				'course_id'     => $course_id,
				'regular_price' => $unit_regular,
				'sale_price'    => $sale_price,
				'subtotal'      => $subtotal > 0 ? $subtotal : ( $unit_regular * $qty ),
				'total'         => $total > 0 ? $total : ( ( null !== $sale_price ? $sale_price : $unit_regular ) * $qty ),
			);
		}

		return $prepared;
	}

	/**
	 * Sum a numeric field across prepared items.
	 *
	 * @since 2.5.0
	 *
	 * @param array<int, array> $items Items.
	 * @param string            $field Field key.
	 *
	 * @return float
	 */
	private function sum_item_amounts( array $items, string $field ): float {
		$sum = 0.0;
		foreach ( $items as $item ) {
			$sum += (float) ( $item[ $field ] ?? 0 );
		}
		return $sum;
	}

	/**
	 * Build a compact payment payload for audit (not for live gateway use).
	 *
	 * @since 2.5.0
	 *
	 * @param int    $lp_order_id LP order id.
	 * @param string $lp_method   LP payment method.
	 *
	 * @return array<string, mixed>
	 */
	private function build_payment_payload( int $lp_order_id, string $lp_method ): array {
		return array(
			'source'                => 'learnpress',
			'lp_order_id'           => $lp_order_id,
			'lp_payment_method'     => $lp_method,
			'lp_payment_method_title' => (string) get_post_meta( $lp_order_id, '_payment_method_title', true ),
			'lp_transaction_id'     => (string) get_post_meta( $lp_order_id, '_transaction_id', true ),
			'lp_order_currency'     => (string) get_post_meta( $lp_order_id, '_order_currency', true ),
			'lp_created_via'        => (string) get_post_meta( $lp_order_id, '_created_via', true ),
		);
	}

	/**
	 * Build billing_address JSON fields from WP user / LP checkout email.
	 *
	 * @since 2.5.0
	 *
	 * @param int $buyer_id    User id (0 = guest).
	 * @param int $lp_order_id LP order id.
	 *
	 * @return array<string, string>
	 */
	private function build_billing_address( int $buyer_id, int $lp_order_id ): array {
		$first = '';
		$last  = '';
		$email = (string) get_post_meta( $lp_order_id, '_checkout_email', true );

		if ( $buyer_id > 0 ) {
			$user = get_userdata( $buyer_id );
			if ( $user ) {
				$first = (string) $user->first_name;
				$last  = (string) $user->last_name;
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
			'billing_phone'      => '',
			'billing_zip_code'   => '',
			'billing_address'    => '',
			'billing_country'    => '',
			'billing_state'      => '',
			'billing_city'       => '',
		);
	}

	/**
	 * Normalize a GMT mysql datetime string.
	 *
	 * @since 2.5.0
	 *
	 * @param string $gmt   post_date_gmt / post_modified_gmt.
	 * @param string $local Fallback local datetime.
	 *
	 * @return string
	 */
	private function normalize_gmt_datetime( string $gmt, string $local ): string {
		if ( ! empty( $gmt ) && '0000-00-00 00:00:00' !== $gmt ) {
			return $gmt;
		}

		if ( ! empty( $local ) && '0000-00-00 00:00:00' !== $local ) {
			$timestamp = strtotime( $local );
			if ( false !== $timestamp ) {
				return gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		return gmdate( 'Y-m-d H:i:s' );
	}
}
