<?php
/**
 * LearnDash → Tutor native subscription migration orchestrator.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions;

use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Transformers\EnrollmentDataTransformer;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Transformers\OrderDataTransformer;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Transformers\PlanDataTransformer;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Transformers\SubscriptionDataTransformer;
use Themeum\TutorLMSMigrationTool\MigrationMapper;
use TUTOR\Course;
use Tutor\Helpers\QueryHelper;
use Tutor\Models\OrderModel;
use TutorPro\Subscription\Models\PlanModel;
use TutorPro\Subscription\Models\SubscriptionModel;

defined( 'ABSPATH' ) || exit;

/**
 * Subscriptions migration class.
 *
 * @since 2.5.0
 */
class Subscriptions {

	/**
	 * Option key for LD → Tutor subscription ID maps.
	 *
	 * @var string
	 */
	const MAP_KEY = 'tutor_ld2tutor_subscription_map';

	/**
	 * Map section keys.
	 *
	 * @var string
	 */
	const PLANS         = 'plans';
	const SUBSCRIPTIONS = 'subscriptions';
	const ORDERS        = 'orders';

	/**
	 * Mapper instance.
	 *
	 * @var MigrationMapper
	 */
	private $mapper;

	/**
	 * Plan model.
	 *
	 * @var PlanModel
	 */
	private $plan_model;

	/**
	 * Order model.
	 *
	 * @var OrderModel
	 */
	private $order_model;

	/**
	 * Subscription model.
	 *
	 * @var SubscriptionModel
	 */
	private $subscription_model;

	/**
	 * Plan transformer.
	 *
	 * @var PlanDataTransformer
	 */
	private $plan_transformer;

	/**
	 * Subscription transformer.
	 *
	 * @var SubscriptionDataTransformer
	 */
	private $subscription_transformer;

	/**
	 * Order transformer.
	 *
	 * @var OrderDataTransformer
	 */
	private $order_transformer;

	/**
	 * Enrollment transformer.
	 *
	 * @var EnrollmentDataTransformer
	 */
	private $enrollment_transformer;

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {
		$this->mapper                   = new MigrationMapper( self::MAP_KEY );
		$this->plan_model               = new PlanModel();
		$this->order_model              = new OrderModel();
		$this->subscription_model       = new SubscriptionModel();
		$this->plan_transformer         = new PlanDataTransformer();
		$this->subscription_transformer = new SubscriptionDataTransformer();
		$this->order_transformer        = new OrderDataTransformer();
		$this->enrollment_transformer   = new EnrollmentDataTransformer();
	}

	/**
	 * Reset the migration map (call at start of a full migration run).
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public function reset_map(): void {
		$this->mapper->clear_map();
	}

	/**
	 * Create a Tutor subscription plan for an LD subscribe course.
	 *
	 * @since 2.5.0
	 *
	 * @param int $course_id Course ID (same ID after in-place migration).
	 *
	 * @return int|false New plan ID or false.
	 */
	public function migrate_plan_for_course( int $course_id ) {
		if ( ! Helper::is_subscription_migration_available() ) {
			return false;
		}

		if ( ! Helper::is_ld_subscribe_course( $course_id ) ) {
			return false;
		}

		$plans_map = $this->mapper->get_map_by_key( self::PLANS );
		if ( isset( $plans_map[ $course_id ] ) ) {
			return (int) $plans_map[ $course_id ];
		}

		$plan_data = $this->plan_transformer->transform( $course_id );
		if ( empty( $plan_data ) ) {
			return false;
		}

		$object_id = (int) $plan_data['object_id'];
		unset( $plan_data['object_id'] );

		$plan_id = $this->plan_model->create_subscription_plan( $object_id, $plan_data );
		if ( ! $plan_id ) {
			return false;
		}

		update_post_meta( $object_id, Course::COURSE_PRICE_TYPE_META, Course::PRICE_TYPE_PAID );
		update_post_meta( $object_id, Course::COURSE_SELLING_OPTION_META, Course::SELLING_OPTION_SUBSCRIPTION );

		$plans_map[ $course_id ] = (int) $plan_id;
		$this->mapper->set_map_by_key( self::PLANS, $plans_map );

		return (int) $plan_id;
	}

	/**
	 * Migrate all LD subscription line items to Tutor subscriptions.
	 *
	 * @since 2.5.0
	 *
	 * @return array{migrated:int,skipped:int,errors:array}
	 */
	public function migrate_all_subscriptions(): array {
		$result = $this->migrate_subscriptions_batch( 0 );

		return array(
			'migrated' => (int) ( $result['migrated_in_batch'] ?? 0 ),
			'skipped'  => (int) ( $result['skipped'] ?? 0 ),
			'errors'   => $result['errors'] ?? array(),
		);
	}

	/**
	 * Migrate a batch of LD subscription line items.
	 *
	 * @since 2.5.1
	 *
	 * @param int $batch_size Number of pending subscriptions to process. 0 = all remaining.
	 *
	 * @return array{
	 *     migrated_in_batch:int,
	 *     skipped:int,
	 *     errors:array,
	 *     has_more:bool,
	 *     remaining:int,
	 *     total:int
	 * }
	 */
	public function migrate_subscriptions_batch( int $batch_size = 5 ): array {
		$result = array(
			'migrated_in_batch' => 0,
			'skipped'           => 0,
			'errors'            => array(),
			'has_more'          => false,
			'remaining'         => 0,
			'total'             => 0,
		);

		if ( ! Helper::is_subscription_migration_available() ) {
			$result['errors'][] = __( 'Tutor Pro Subscriptions addon with Native Payment is required.', 'tutor-lms-migration-tool' );
			return $result;
		}

		$subscription_ids = Helper::get_subscription_transaction_ids();
		$existing_map     = $this->mapper->get_map_by_key( self::SUBSCRIPTIONS );
		$pending_ids      = array();

		foreach ( $subscription_ids as $transaction_id ) {
			if ( ! isset( $existing_map[ $transaction_id ] ) ) {
				$pending_ids[] = (int) $transaction_id;
			}
		}

		$result['total'] = count( $subscription_ids );

		if ( empty( $pending_ids ) ) {
			$access_only         = $this->migrate_enrolled_subscribers_without_transactions();
			$result['migrated_in_batch'] += $access_only['migrated'];
			$result['skipped']           += $access_only['skipped'];
			$result['errors']             = array_merge( $result['errors'], $access_only['errors'] );
			$result['remaining']          = 0;
			$result['has_more']           = false;
			return $result;
		}

		$batch_ids = ( $batch_size > 0 ) ? array_slice( $pending_ids, 0, $batch_size ) : $pending_ids;

		foreach ( $batch_ids as $transaction_id ) {
			if ( isset( $existing_map[ $transaction_id ] ) ) {
				++$result['skipped'];
				continue;
			}

			try {
				$tutor_subscription_id = $this->migrate_subscription( $transaction_id );
				if ( $tutor_subscription_id ) {
					++$result['migrated_in_batch'];
					$existing_map[ $transaction_id ] = $tutor_subscription_id;
				} else {
					++$result['skipped'];
					// Mark as processed so subsequent batches do not retry forever.
					$existing_map[ $transaction_id ] = 0;
				}
				$this->mapper->set_map_by_key( self::SUBSCRIPTIONS, $existing_map );
			} catch ( \Throwable $th ) {
				$result['errors'][] = sprintf(
					/* translators: 1: transaction id, 2: error message */
					__( 'Failed subscription transaction %1$d: %2$s', 'tutor-lms-migration-tool' ),
					$transaction_id,
					$th->getMessage()
				);
				$existing_map[ $transaction_id ] = 0;
				$this->mapper->set_map_by_key( self::SUBSCRIPTIONS, $existing_map );
			}
		}

		$subscription_ids_after = Helper::get_subscription_transaction_ids();
		$existing_map_after     = $this->mapper->get_map_by_key( self::SUBSCRIPTIONS );
		$pending_after          = 0;
		foreach ( $subscription_ids_after as $transaction_id ) {
			if ( ! isset( $existing_map_after[ $transaction_id ] ) ) {
				++$pending_after;
			}
		}

		$result['remaining'] = $pending_after;
		$result['has_more']  = $pending_after > 0;

		if ( ! $result['has_more'] ) {
			// LD can grant subscribe-course access without a payment transaction (manual /
			// admin assignment). Those students still need Tutor subscription rows.
			$access_only = $this->migrate_enrolled_subscribers_without_transactions();
			$result['migrated_in_batch'] += $access_only['migrated'];
			$result['skipped']           += $access_only['skipped'];
			$result['errors']             = array_merge( $result['errors'], $access_only['errors'] );
		}

		return $result;
	}

	/**
	 * Migrate a single LD subscription transaction.
	 *
	 * @since 2.5.0
	 *
	 * @param int $transaction_id LD subscription transaction ID.
	 *
	 * @return int|false Tutor subscription ID.
	 *
	 * @throws \Throwable When create fails.
	 */
	public function migrate_subscription( int $transaction_id ) {
		$subscription_data = $this->subscription_transformer->transform( $transaction_id );
		if ( empty( $subscription_data ) ) {
			return false;
		}

		$course_id = (int) ( $subscription_data['ld_course_id'] ?? 0 );
		$plans_map = $this->mapper->get_map_by_key( self::PLANS );

		if ( $course_id && empty( $plans_map[ $course_id ] ) ) {
			$this->migrate_plan_for_course( $course_id );
			$plans_map = $this->mapper->get_map_by_key( self::PLANS );
		}

		if ( empty( $plans_map[ $course_id ] ) ) {
			return false;
		}

		// Ensure unique user+plan: reuse existing Tutor subscription if present.
		$user_id  = (int) $subscription_data['user_id'];
		$plan_id  = (int) $plans_map[ $course_id ];
		$existing = $this->subscription_model->get_user_subscription_by_plan( $plan_id, $user_id );
		if ( $existing ) {
			$this->link_enrollments( $transaction_id, (int) $existing->id );
			return (int) $existing->id;
		}

		$orders_data = $this->order_transformer->transform( $transaction_id );
		$orders_map  = array();

		foreach ( $orders_data as $order ) {
			$ld_order_id = (int) $order['ld_order_id'];
			$meta_data   = $order['meta_data'] ?? array();
			unset( $order['ld_order_id'], $order['meta_data'] );

			$tutor_order_id             = $this->order_model->create_order( $order );
			$orders_map[ $ld_order_id ] = $tutor_order_id;

			if ( ! empty( $meta_data ) ) {
				$meta_data = array_map(
					static function ( $meta ) use ( $tutor_order_id ) {
						$meta['order_id'] = $tutor_order_id;
						return $meta;
					},
					$meta_data
				);
				QueryHelper::insert_multiple_rows( 'tutor_ordermeta', $meta_data, false, false );
			}
		}

		if ( empty( $orders_map ) ) {
			return false;
		}

		// Point renewals at the first subscription order as parent.
		$first_ld_order_id = (int) $subscription_data['first_order_id'];
		$parent_tutor_id   = $orders_map[ $first_ld_order_id ] ?? reset( $orders_map );

		foreach ( $orders_map as $tutor_order_id ) {
			if ( (int) $tutor_order_id === (int) $parent_tutor_id ) {
				continue;
			}
			QueryHelper::update(
				'tutor_orders',
				array( 'parent_id' => $parent_tutor_id ),
				array( 'id' => $tutor_order_id )
			);
		}

		$merged_orders = $this->mapper->get_map_by_key( self::ORDERS );
		$this->mapper->set_map_by_key( self::ORDERS, $merged_orders + $orders_map );

		$first_order_id  = $orders_map[ $subscription_data['first_order_id'] ] ?? $parent_tutor_id;
		$active_order_id = $orders_map[ $subscription_data['active_order_id'] ] ?? $parent_tutor_id;

		unset( $subscription_data['ld_transaction_id'], $subscription_data['ld_course_id'] );

		$subscription_data['plan_id']         = $plan_id;
		$subscription_data['first_order_id']  = $first_order_id;
		$subscription_data['active_order_id'] = $active_order_id;

		if ( empty( $subscription_data['next_payment_date_gmt'] ) ) {
			$subscription_data['next_payment_date_gmt'] = $subscription_data['end_date_gmt']
				?? $subscription_data['start_date_gmt']
				?? gmdate( 'Y-m-d H:i:s' );
		}

		$tutor_subscription_id = $this->subscription_model->create( $subscription_data );
		if ( ! $tutor_subscription_id ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: LD transaction ID */
					__( 'Could not create Tutor subscription for transaction %d', 'tutor-lms-migration-tool' ),
					$transaction_id
				)
			);
		}

		$this->link_enrollments( $transaction_id, (int) $tutor_subscription_id );
		$this->remove_related_transactions( $transaction_id );

		return (int) $tutor_subscription_id;
	}

	/**
	 * Create Tutor subscriptions for enrolled students who have no LD payment txn.
	 *
	 * @since 2.5.1
	 *
	 * @return array{migrated:int,skipped:int,errors:array}
	 */
	private function migrate_enrolled_subscribers_without_transactions(): array {
		global $wpdb;

		$result = array(
			'migrated' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		$plans_map = $this->mapper->get_map_by_key( self::PLANS );
		if ( empty( $plans_map ) ) {
			return $result;
		}

		foreach ( $plans_map as $course_id => $plan_id ) {
			$course_id = (int) $course_id;
			$plan_id   = (int) $plan_id;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$enrollments = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_author, post_date_gmt, post_date
					FROM {$wpdb->posts}
					WHERE post_type = %s
					AND post_parent = %d
					AND post_status IN ( 'completed', 'cancel', 'publish', 'private' )",
					'tutor_enrolled',
					$course_id
				)
			);

			if ( empty( $enrollments ) ) {
				continue;
			}

			foreach ( $enrollments as $enrollment ) {
				$user_id = (int) $enrollment->post_author;
				if ( ! $user_id ) {
					++$result['skipped'];
					continue;
				}

				$existing = $this->subscription_model->get_user_subscription_by_plan( $plan_id, $user_id );
				if ( $existing ) {
					SubscriptionModel::mark_as_subscription_enrollment( (int) $enrollment->ID, (int) $existing->id );
					++$result['skipped'];
					continue;
				}

				try {
					$tutor_subscription_id = $this->create_access_only_subscription(
						$user_id,
						$course_id,
						$plan_id,
						$enrollment
					);
					if ( $tutor_subscription_id ) {
						++$result['migrated'];
					} else {
						++$result['skipped'];
					}
				} catch ( \Throwable $th ) {
					$result['errors'][] = sprintf(
						/* translators: 1: user id, 2: course id, 3: error message */
						__( 'Failed access-only subscription for user %1$d on course %2$d: %3$s', 'tutor-lms-migration-tool' ),
						$user_id,
						$course_id,
						$th->getMessage()
					);
				}
			}
		}

		return $result;
	}

	/**
	 * Create a Tutor order + subscription for an enrolled student without an LD txn.
	 *
	 * @since 2.5.1
	 *
	 * @param int      $user_id    User ID.
	 * @param int      $course_id  Course ID.
	 * @param int      $plan_id    Tutor plan ID.
	 * @param \stdClass $enrollment Enrollment post row.
	 *
	 * @return int|false
	 *
	 * @throws \Throwable When create fails.
	 */
	private function create_access_only_subscription( int $user_id, int $course_id, int $plan_id, $enrollment ) {
		global $wpdb;

		$plan = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tutor_subscription_plans WHERE id = %d",
				$plan_id
			)
		);
		if ( ! $plan ) {
			return false;
		}

		$start_gmt = Helper::to_gmt_datetime( $enrollment->post_date_gmt ? $enrollment->post_date_gmt : $enrollment->post_date );
		if ( empty( $start_gmt ) ) {
			$start_gmt = gmdate( 'Y-m-d H:i:s' );
		}

		$price = (float) $plan->regular_price;
		$order = array(
			'order_type'       => OrderModel::TYPE_SUBSCRIPTION,
			'parent_id'        => 0,
			'transaction_id'   => 'ld-access-' . $course_id . '-' . $user_id,
			'user_id'          => $user_id,
			'order_status'     => OrderModel::ORDER_COMPLETED,
			'payment_status'   => OrderModel::PAYMENT_PAID,
			'subtotal_price'   => $price,
			'pre_tax_price'    => $price,
			'tax_type'         => null,
			'tax_rate'         => 0,
			'tax_amount'       => 0,
			'total_price'      => $price,
			'net_payment'      => $price,
			'coupon_code'      => null,
			'coupon_amount'    => 0,
			'discount_amount'  => 0,
			'discount_type'    => null,
			'discount_reason'  => '',
			'fees'             => 0,
			'refund_amount'    => 0,
			'earnings'         => $price,
			'payment_method'   => 'manual',
			'payment_payloads' => '',
			'note'             => __( 'Order migrated from LearnDash course access (no payment transaction)', 'tutor-lms-migration-tool' ),
			'created_by'       => $user_id,
			'updated_by'       => $user_id,
			'created_at_gmt'   => $start_gmt,
			'updated_at_gmt'   => $start_gmt,
			'items'            => array(
				array(
					'item_id'       => $plan->id,
					'regular_price' => $plan->regular_price,
					'sale_price'    => ( isset( $plan->sale_price ) && $plan->sale_price > 0 ) ? $plan->sale_price : null,
				),
			),
		);

		$tutor_order_id = $this->order_model->create_order( $order );
		if ( ! $tutor_order_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		QueryHelper::insert_multiple_rows(
			'tutor_ordermeta',
			array(
				array(
					'order_id'       => $tutor_order_id,
					'meta_key'       => OrderModel::META_KEY_BILLING_ADDRESS,
					'meta_value'     => wp_json_encode(
						array(
							'id'                 => $user_id,
							'user_id'            => $user_id,
							'billing_first_name' => $user ? $user->first_name : '',
							'billing_last_name'  => $user ? $user->last_name : '',
							'billing_email'      => $user ? $user->user_email : '',
							'billing_phone'      => '',
							'billing_zip_code'   => '',
							'billing_address'    => '',
							'billing_country'    => '',
							'billing_state'      => '',
							'billing_city'       => '',
						),
						JSON_UNESCAPED_UNICODE
					),
					'created_at_gmt' => $start_gmt,
					'updated_at_gmt' => $start_gmt,
					'created_by'     => $user_id,
					'updated_by'     => $user_id,
				),
				array(
					'order_id'       => $tutor_order_id,
					'meta_key'       => OrderModel::META_PLAN_INFO,
					'meta_value'     => maybe_serialize( $plan ),
					'created_at_gmt' => $start_gmt,
					'updated_at_gmt' => $start_gmt,
					'created_by'     => $user_id,
					'updated_by'     => $user_id,
				),
			),
			false,
			false
		);

		$subscription_data = array(
			'user_id'               => $user_id,
			'plan_id'               => $plan_id,
			'first_order_id'        => $tutor_order_id,
			'active_order_id'       => $tutor_order_id,
			'status'                => SubscriptionModel::STATUS_ACTIVE,
			'auto_renew'            => 1,
			'is_trial_enabled'      => 0,
			'is_trial_used'         => 0,
			'trial_end_date_gmt'    => null,
			'start_date_gmt'        => $start_gmt,
			'end_date_gmt'          => $start_gmt,
			'next_payment_date_gmt' => $start_gmt,
			'created_at_gmt'        => $start_gmt,
			'updated_at_gmt'        => $start_gmt,
			'note'                  => __( 'Subscription migrated from LearnDash course access', 'tutor-lms-migration-tool' ),
		);

		$tutor_subscription_id = $this->subscription_model->create( $subscription_data );
		if ( ! $tutor_subscription_id ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: user id, 2: course id */
					__( 'Could not create Tutor subscription for user %1$d on course %2$d', 'tutor-lms-migration-tool' ),
					$user_id,
					$course_id
				)
			);
		}

		SubscriptionModel::mark_as_subscription_enrollment( (int) $enrollment->ID, (int) $tutor_subscription_id );

		return (int) $tutor_subscription_id;
	}

	/**
	 * Link existing Tutor enrollments to the new subscription.
	 *
	 * @since 2.5.0
	 *
	 * @param int $transaction_id        LD subscription transaction ID.
	 * @param int $tutor_subscription_id Tutor subscription ID.
	 *
	 * @return void
	 */
	private function link_enrollments( int $transaction_id, int $tutor_subscription_id ): void {
		$enrollments = $this->enrollment_transformer->transform( $transaction_id );
		foreach ( $enrollments as $enrollment ) {
			$enrollment_id = is_object( $enrollment ) ? (int) $enrollment->ID : (int) $enrollment;
			if ( $enrollment_id ) {
				SubscriptionModel::mark_as_subscription_enrollment( $enrollment_id, $tutor_subscription_id );
			}
		}
	}

	/**
	 * Remove LD transactions after a successful subscription migrate.
	 *
	 * @since 2.5.0
	 *
	 * @param int $transaction_id Subscription line-item ID.
	 *
	 * @return void
	 */
	private function remove_related_transactions( int $transaction_id ): void {
		$parent = (int) wp_get_post_parent_id( $transaction_id );
		$ids    = array( $transaction_id );

		$children = get_children(
			array(
				'post_parent' => $transaction_id,
				'post_type'   => 'sfwd-transactions',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		if ( ! empty( $children ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $children ) );
		}

		foreach ( array_unique( $ids ) as $id ) {
			wp_delete_post( $id, true );
		}

		if ( ! $parent ) {
			return;
		}

		$siblings = get_children(
			array(
				'post_parent' => $parent,
				'post_type'   => 'sfwd-transactions',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		// Only remove the parent order when it has no remaining line items.
		if ( empty( $siblings ) ) {
			wp_delete_post( $parent, true );
		}
	}
}
