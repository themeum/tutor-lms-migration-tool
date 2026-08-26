<?php
/**
 * LifterLMS → Tutor native ecommerce order migrator.
 *
 * Migrates one-time course `llms_order` posts into tutor_orders without firing
 * live checkout hooks (no double enrollment).
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration\Orders;

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\ErrorHandler;
use TUTOR\Earnings;
use Tutor\Models\EnrollmentModel;
use Tutor\Models\OrderMetaModel;
use Tutor\Models\OrderModel;

defined( 'ABSPATH' ) || exit;

/**
 * Native order migrator for LifterLMS one-time sales.
 *
 * @since 2.6.0
 */
class OrderMigrator {

	/**
	 * Post meta on llms_order: Tutor order id after successful migration.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	const META_MIGRATED_ORDER_ID = '_tlmt_migrated_to_native_order_id';

	/**
	 * Post meta on llms_order when skipped.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	const META_SKIP_REASON = '_tlmt_native_order_skip_reason';

	/**
	 * Ordermeta key storing the source Lifter order id.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	const ORDERMETA_LIF_ORDER_ID = '_tlmt_lif_order_id';

	/**
	 * Tutor OrderModel.
	 *
	 * @since 2.6.0
	 *
	 * @var OrderModel
	 */
	private $order_model;

	/**
	 * Tutor Earnings instance.
	 *
	 * @since 2.6.0
	 *
	 * @var Earnings
	 */
	private $earnings;

	/**
	 * Constructor.
	 *
	 * @since 2.6.0
	 */
	public function __construct() {
		$this->order_model = new OrderModel();
		$this->earnings    = Earnings::get_instance();
	}

	/**
	 * Count Lifter one-time course orders still pending native migration.
	 *
	 * @since 2.6.0
	 *
	 * @return int
	 */
	public function get_remaining_count(): int {
		global $wpdb;

		$statuses      = StatusMapper::migratable_single_statuses();
		$status_in     = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$migrated_meta = self::META_MIGRATED_ORDER_ID;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} ot
				ON ot.post_id = p.ID AND ot.meta_key = '_llms_order_type' AND ot.meta_value = 'single'
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_type = 'llms_order'
				AND p.post_status IN ({$status_in})
				AND pm.meta_id IS NULL",
			array_merge( array( $migrated_meta ), $statuses )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Fetch a batch of unmigrated one-time Lifter orders.
	 *
	 * @since 2.6.0
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

		$statuses      = StatusMapper::migratable_single_statuses();
		$status_in     = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$migrated_meta = self::META_MIGRATED_ORDER_ID;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT p.*
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} ot
				ON ot.post_id = p.ID AND ot.meta_key = '_llms_order_type' AND ot.meta_value = 'single'
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_type = 'llms_order'
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
	 * Migrate one Lifter one-time order into Tutor native ecommerce.
	 *
	 * @since 2.6.0
	 *
	 * @param \WP_Post|object $llms_order Lifter order post.
	 *
	 * @return int Tutor order id, or 0 when skipped.
	 *
	 * @throws \Throwable When insert fails unexpectedly.
	 */
	public function migrate_order( $llms_order ): int {
		$llms_order_id = (int) ( $llms_order->ID ?? 0 );
		if ( $llms_order_id < 1 ) {
			return 0;
		}

		$existing = get_post_meta( $llms_order_id, self::META_MIGRATED_ORDER_ID, true );
		if ( '' !== $existing && false !== $existing && null !== $existing ) {
			return (int) $existing;
		}

		$course_id = $this->resolve_course_id( $llms_order_id );
		if ( $course_id < 1 ) {
			update_post_meta( $llms_order_id, self::META_MIGRATED_ORDER_ID, 0 );
			update_post_meta( $llms_order_id, self::META_SKIP_REASON, 'not_course_product' );
			ErrorHandler::set_error(
				ContentTypes::ORDERS,
				sprintf(
					/* translators: %d: Lifter order ID */
					__( 'Lifter order #%d skipped: product is not a migratable course.', 'tutor-lms-migration-tool' ),
					$llms_order_id
				)
			);
			return 0;
		}

		$buyer_id = (int) get_post_meta( $llms_order_id, '_llms_user_id', true );
		if ( $buyer_id < 1 ) {
			$buyer_id = (int) ( $llms_order->post_author ?? 0 );
		}

		$items = $this->build_line_items( $llms_order_id, $course_id );
		if ( empty( $items ) ) {
			update_post_meta( $llms_order_id, self::META_MIGRATED_ORDER_ID, 0 );
			update_post_meta( $llms_order_id, self::META_SKIP_REASON, 'no_course_items' );
			return 0;
		}

		try {
			$tutor_order_id = $this->create_native_order( $llms_order, $buyer_id, $items );
		} catch ( \Throwable $th ) {
			update_post_meta( $llms_order_id, self::META_MIGRATED_ORDER_ID, 0 );
			update_post_meta( $llms_order_id, self::META_SKIP_REASON, 'create_failed' );
			ErrorHandler::set_error(
				ContentTypes::ORDERS,
				sprintf(
					/* translators: 1: Lifter order ID, 2: error message */
					__( 'Lifter order #%1$d failed: %2$s', 'tutor-lms-migration-tool' ),
					$llms_order_id,
					$th->getMessage()
				)
			);
			throw $th;
		}

		if ( $tutor_order_id < 1 ) {
			update_post_meta( $llms_order_id, self::META_MIGRATED_ORDER_ID, 0 );
			update_post_meta( $llms_order_id, self::META_SKIP_REASON, 'create_failed' );
			return 0;
		}

		$this->link_enrollments( $tutor_order_id, $buyer_id, $items );
		$this->store_earnings( $tutor_order_id );

		// Marked before removal so a failed delete cannot re-queue the order.
		update_post_meta( $llms_order_id, self::META_MIGRATED_ORDER_ID, $tutor_order_id );
		delete_post_meta( $llms_order_id, self::META_SKIP_REASON );

		self::remove_llms_order( $llms_order_id );

		return $tutor_order_id;
	}

	/**
	 * Remove a Lifter order (and related transactions) once it has been migrated.
	 *
	 * Mirrors LearnPress/LearnDash source-order cleanup after a successful migrate.
	 *
	 * @since 2.6.0
	 *
	 * @param int $llms_order_id Lifter order ID.
	 *
	 * @return void
	 */
	public static function remove_llms_order( int $llms_order_id ): void {
		global $wpdb;

		if ( $llms_order_id < 1 ) {
			return;
		}

		$transaction_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} om
					ON om.post_id = p.ID AND om.meta_key = '_llms_order_id' AND om.meta_value = %s
				WHERE p.post_type = 'llms_transaction'",
				(string) $llms_order_id
			)
		);

		if ( is_array( $transaction_ids ) ) {
			foreach ( $transaction_ids as $txn_id ) {
				$txn_id = (int) $txn_id;
				if ( $txn_id < 1 ) {
					continue;
				}
				$wpdb->query(
					$wpdb->prepare(
						"DELETE p, pm
						FROM {$wpdb->posts} p
						LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						WHERE p.ID = %d",
						$txn_id
					)
				);
				wp_cache_delete( $txn_id, 'posts' );
				wp_cache_delete( $txn_id, 'post_meta' );
			}
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE p, pm
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.ID = %d",
				$llms_order_id
			)
		);

		wp_cache_delete( $llms_order_id, 'posts' );
		wp_cache_delete( $llms_order_id, 'post_meta' );
	}

	/**
	 * Resolve course ID from Lifter order product meta.
	 *
	 * @since 2.6.0
	 *
	 * @param int $llms_order_id Lifter order ID.
	 *
	 * @return int Course ID or 0.
	 */
	private function resolve_course_id( int $llms_order_id ): int {
		$product_type = (string) get_post_meta( $llms_order_id, '_llms_product_type', true );
		if ( 'membership' === $product_type || 'llms_membership' === $product_type ) {
			return 0;
		}

		$product_id = (int) get_post_meta( $llms_order_id, '_llms_product_id', true );
		if ( $product_id < 1 ) {
			return 0;
		}

		$product = get_post( $product_id );
		if ( ! $product ) {
			return 0;
		}

		$course_post_type = tutor()->course_post_type;
		if ( $course_post_type === $product->post_type ) {
			return $product_id;
		}

		if ( get_post_meta( $product_id, '_was_lif_course', true ) ) {
			return $product_id;
		}

		if ( 'course' === $product->post_type || 'course' === $product_type ) {
			return $product_id;
		}

		return 0;
	}

	/**
	 * Build a single course line item from Lifter order totals.
	 *
	 * Sale price is set only for Lifter sale pricing. Coupon discounts are
	 * stored on the order as coupon_amount — not folded into sale_price.
	 *
	 * @since 2.6.0
	 *
	 * @param int $llms_order_id Lifter order ID.
	 * @param int $course_id     Course ID.
	 *
	 * @return array<int, array{course_id: int, regular_price: float, sale_price: float|null}>
	 */
	private function build_line_items( int $llms_order_id, int $course_id ): array {
		$original = (float) get_post_meta( $llms_order_id, '_llms_original_total', true );
		$total    = $this->read_money_meta( $llms_order_id, '_llms_total' );

		$catalog_regular = (float) get_post_meta( $course_id, 'tutor_course_price', true );
		if ( $catalog_regular <= 0 ) {
			$plan_id = (int) get_post_meta( $llms_order_id, '_llms_plan_id', true );
			if ( $plan_id > 0 ) {
				$catalog_regular = (float) get_post_meta( $plan_id, '_llms_price', true );
			}
		}

		$regular = $original > 0 ? $original : $catalog_regular;
		if ( $regular <= 0 && null !== $total && $total > 0 ) {
			$regular = $total;
		}

		$sale_price = $this->resolve_sale_price( $llms_order_id, $regular, $total );

		return array(
			array(
				'course_id'     => $course_id,
				'regular_price' => $regular,
				'sale_price'    => $sale_price,
			),
		);
	}

	/**
	 * Insert a Tutor native order + items + meta.
	 *
	 * @since 2.6.0
	 *
	 * @param \WP_Post|object   $llms_order Lifter order post.
	 * @param int               $buyer_id   WP user id.
	 * @param array<int, array> $items      Prepared line items.
	 *
	 * @return int New Tutor order id.
	 *
	 * @throws \RuntimeException On database failure.
	 */
	private function create_native_order( $llms_order, int $buyer_id, array $items ): int {
		$llms_order_id = (int) $llms_order->ID;
		$statuses      = StatusMapper::map( (string) $llms_order->post_status );

		$subtotal = (float) get_post_meta( $llms_order_id, '_llms_original_total', true );
		$total    = $this->read_money_meta( $llms_order_id, '_llms_total' );
		$refunded = (float) get_post_meta( $llms_order_id, '_llms_refund_amount', true );
		$coupon   = $this->resolve_coupon( $llms_order_id, $subtotal, $items[0]['sale_price'] ?? null, $total );

		if ( $subtotal <= 0 ) {
			$subtotal = (float) ( $items[0]['regular_price'] ?? 0 );
		}
		// Only invent a total when Lifter meta is missing — keep explicit $0 (free / 100% coupon).
		if ( null === $total ) {
			$sale  = $items[0]['sale_price'] ?? null;
			$total = null !== $sale ? (float) $sale : $subtotal;
			if ( $coupon['amount'] > 0 ) {
				$total = max( 0, $total - $coupon['amount'] );
			}
		}
		if ( $refunded < 0 ) {
			$refunded = 0;
		}
		if ( $refunded > $total ) {
			$refunded = $total;
		}

		$gateway = (string) get_post_meta( $llms_order_id, '_llms_payment_gateway', true );
		$txn     = $this->get_first_succeeded_transaction( $llms_order_id );

		$created_gmt = $this->normalize_gmt_datetime( $llms_order->post_date_gmt ?? '', $llms_order->post_date ?? '' );
		$updated_gmt = $this->normalize_gmt_datetime( $llms_order->post_modified_gmt ?? '', $llms_order->post_modified ?? '' );
		$actor_id    = $buyer_id > 0 ? $buyer_id : (int) ( $llms_order->post_author ?? 0 );

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
			'transaction_id'   => $txn['transaction_id'],
			'user_id'          => $buyer_id,
			'order_type'       => OrderModel::TYPE_SINGLE_ORDER,
			'order_status'     => $statuses['order_status'],
			'payment_status'   => $statuses['payment_status'],
			'subtotal_price'   => $subtotal,
			'pre_tax_price'    => $total,
			'tax_rate'         => 0,
			'tax_amount'       => 0,
			'total_price'      => $total,
			'net_payment'      => max( 0, $total - $refunded ),
			'coupon_code'      => $coupon['code'],
			'coupon_amount'    => $coupon['amount'],
			'discount_amount'  => 0,
			'fees'             => 0,
			'earnings'         => max( 0, $total - $refunded ),
			'refund_amount'    => $refunded,
			'payment_method'   => PaymentMethodMapper::map( $gateway, $total ),
			'payment_payloads' => wp_json_encode( $this->build_payment_payload( $llms_order_id, $gateway, $txn ) ),
			'note'             => '',
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
					/* translators: %d: Lifter order ID */
					__( 'Failed to create Tutor native order for Lifter order #%d.', 'tutor-lms-migration-tool' ),
					$llms_order_id
				)
			);
		}

		OrderMetaModel::add_meta( $tutor_order_id, self::ORDERMETA_LIF_ORDER_ID, $llms_order_id );
		OrderMetaModel::add_meta(
			$tutor_order_id,
			OrderModel::META_KEY_BILLING_ADDRESS,
			wp_json_encode( $this->build_billing_address( $buyer_id, $llms_order_id ) )
		);
		OrderMetaModel::add_meta(
			$tutor_order_id,
			'history',
			sprintf(
				/* translators: %d: LifterLMS order ID */
				__( 'Order migrated from LifterLMS #%d', 'tutor-lms-migration-tool' ),
				$llms_order_id
			)
		);

		return $tutor_order_id;
	}

	/**
	 * Read a monetary post meta value; null when meta is missing.
	 *
	 * @since 2.6.0
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 *
	 * @return float|null
	 */
	private function read_money_meta( int $post_id, string $meta_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( '' === $value || false === $value || null === $value ) {
			return null;
		}

		return (float) $value;
	}

	/**
	 * Resolve line-item sale price without absorbing coupon discounts.
	 *
	 * @since 2.6.0
	 *
	 * @param int        $llms_order_id Lifter order ID.
	 * @param float      $regular       Regular / original price.
	 * @param float|null $total         Lifter total (may be null).
	 *
	 * @return float|null
	 */
	private function resolve_sale_price( int $llms_order_id, float $regular, $total ) {
		$on_sale   = 'yes' === get_post_meta( $llms_order_id, '_llms_on_sale', true );
		$sale_meta = $this->read_money_meta( $llms_order_id, '_llms_sale_price' );
		$has_coupon = 'yes' === get_post_meta( $llms_order_id, '_llms_coupon_used', true );

		if ( $on_sale && null !== $sale_meta && $sale_meta > 0 && ( $regular <= 0 || $sale_meta < $regular ) ) {
			return $sale_meta;
		}

		// No coupon: a lower total than regular is treated as a sale price.
		if ( ! $has_coupon && null !== $total && $total > 0 && $regular > 0 && $total < $regular ) {
			return $total;
		}

		return null;
	}

	/**
	 * Map Lifter coupon meta to Tutor coupon_code / coupon_amount.
	 *
	 * @since 2.6.0
	 *
	 * @param int        $llms_order_id Lifter order ID.
	 * @param float      $subtotal      Original / subtotal price.
	 * @param float|null $sale_price    Resolved sale price.
	 * @param float|null $total         Lifter total.
	 *
	 * @return array{code: string|null, amount: float}
	 */
	private function resolve_coupon( int $llms_order_id, float $subtotal, $sale_price, $total ): array {
		if ( 'yes' !== get_post_meta( $llms_order_id, '_llms_coupon_used', true ) ) {
			return array(
				'code'   => null,
				'amount' => 0.0,
			);
		}

		$code   = (string) get_post_meta( $llms_order_id, '_llms_coupon_code', true );
		$amount = (float) get_post_meta( $llms_order_id, '_llms_coupon_value', true );

		if ( $amount <= 0 && null !== $total ) {
			$base = ( null !== $sale_price && $sale_price > 0 ) ? (float) $sale_price : $subtotal;
			if ( $base > $total ) {
				$amount = $base - $total;
			}
		}

		return array(
			'code'   => '' !== $code ? $code : null,
			'amount' => max( 0.0, $amount ),
		);
	}

	/**
	 * Link existing Tutor enrollments to the new native order (no re-enroll).
	 *
	 * @since 2.6.0
	 *
	 * @param int               $tutor_order_id Tutor order id.
	 * @param int               $buyer_id       Buyer user id.
	 * @param array<int, array> $items          Line items with course_id.
	 *
	 * @return void
	 */
	private function link_enrollments( int $tutor_order_id, int $buyer_id, array $items ): void {
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

			$existing_order_id = (int) get_post_meta( $enrollment->ID, EnrollmentModel::ENROLLMENT_ORDER_ID_META, true );
			if ( $existing_order_id > 0 && $existing_order_id !== $tutor_order_id ) {
				continue;
			}

			update_post_meta( $enrollment->ID, EnrollmentModel::ENROLLMENT_ORDER_ID_META, $tutor_order_id );
		}
	}

	/**
	 * Store instructor earnings for a paid native order.
	 *
	 * @since 2.6.0
	 *
	 * @param int $tutor_order_id Tutor order id.
	 *
	 * @return void
	 */
	private function store_earnings( int $tutor_order_id ): void {
		if ( $tutor_order_id < 1 ) {
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
	 * First succeeded Lifter transaction for an order.
	 *
	 * @since 2.6.0
	 *
	 * @param int $llms_order_id Lifter order ID.
	 *
	 * @return array{transaction_id: string, amount: float, gateway: string}
	 */
	private function get_first_succeeded_transaction( int $llms_order_id ): array {
		global $wpdb;

		$defaults = array(
			'transaction_id' => (string) $llms_order_id,
			'amount'         => 0.0,
			'gateway'        => '',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$txn_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} om
					ON om.post_id = p.ID AND om.meta_key = '_llms_order_id' AND om.meta_value = %s
				WHERE p.post_type = 'llms_transaction'
					AND p.post_status = 'llms-txn-succeeded'
				ORDER BY p.ID ASC
				LIMIT 1",
				(string) $llms_order_id
			)
		);

		if ( $txn_id < 1 ) {
			return $defaults;
		}

		$gateway_txn = (string) get_post_meta( $txn_id, '_llms_gateway_transaction_id', true );
		if ( '' === $gateway_txn ) {
			$gateway_txn = (string) $txn_id;
		}

		return array(
			'transaction_id' => $gateway_txn,
			'amount'         => (float) get_post_meta( $txn_id, '_llms_amount', true ),
			'gateway'        => (string) get_post_meta( $txn_id, '_llms_payment_gateway', true ),
		);
	}

	/**
	 * Build payment payload for audit.
	 *
	 * @since 2.6.0
	 *
	 * @param int                  $llms_order_id Lifter order ID.
	 * @param string               $gateway       Order gateway.
	 * @param array<string, mixed> $txn           Transaction snapshot.
	 *
	 * @return array<string, mixed>
	 */
	private function build_payment_payload( int $llms_order_id, string $gateway, array $txn ): array {
		return array(
			'source'          => 'lifterlms',
			'llms_order_id'   => $llms_order_id,
			'llms_gateway'    => $gateway,
			'transaction_id'  => $txn['transaction_id'] ?? '',
			'llms_currency'   => (string) get_post_meta( $llms_order_id, '_llms_currency', true ),
		);
	}

	/**
	 * Build billing_address fields from Lifter order meta / WP user.
	 *
	 * @since 2.6.0
	 *
	 * @param int $buyer_id      User id.
	 * @param int $llms_order_id Lifter order id.
	 *
	 * @return array<string, string>
	 */
	private function build_billing_address( int $buyer_id, int $llms_order_id ): array {
		$first = (string) get_post_meta( $llms_order_id, '_llms_billing_first_name', true );
		$last  = (string) get_post_meta( $llms_order_id, '_llms_billing_last_name', true );
		$email = (string) get_post_meta( $llms_order_id, '_llms_billing_email', true );
		$phone = (string) get_post_meta( $llms_order_id, '_llms_billing_phone', true );

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
			'billing_zip_code'   => (string) get_post_meta( $llms_order_id, '_llms_billing_zip', true ),
			'billing_address'    => trim(
				(string) get_post_meta( $llms_order_id, '_llms_billing_address_1', true ) . ' ' .
				(string) get_post_meta( $llms_order_id, '_llms_billing_address_2', true )
			),
			'billing_country'    => (string) get_post_meta( $llms_order_id, '_llms_billing_country', true ),
			'billing_state'      => (string) get_post_meta( $llms_order_id, '_llms_billing_state', true ),
			'billing_city'       => (string) get_post_meta( $llms_order_id, '_llms_billing_city', true ),
		);
	}

	/**
	 * Normalize a GMT mysql datetime string.
	 *
	 * @since 2.6.0
	 *
	 * @param string $gmt   post_date_gmt.
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
