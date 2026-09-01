<?php
/**
 * LifterLMS → Tutor native subscription migration orchestrator.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions;

use Themeum\TutorLMSMigrationTool\LIFMigration\Orders\OrderMigrator;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Transformers\EnrollmentDataTransformer;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Transformers\OrderDataTransformer;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Transformers\PlanDataTransformer;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Transformers\SubscriptionDataTransformer;
use Themeum\TutorLMSMigrationTool\MigrationMapper;
use Tutor\Helpers\QueryHelper;
use Tutor\Models\OrderModel;
use TutorPro\Subscription\Models\PlanModel;
use TutorPro\Subscription\Models\SubscriptionModel;

defined( 'ABSPATH' ) || exit;

/**
 * Subscriptions migration class.
 *
 * @since 2.6.0
 */
class Subscriptions {

	/**
	 * Option key for Lifter → Tutor subscription ID maps.
	 *
	 * @var string
	 */
	const MAP_KEY = 'tutor_lif2tutor_subscription_map';

	/**
	 * Map section keys.
	 *
	 * @var string
	 */
	const PLANS         = 'plans';
	const SUBSCRIPTIONS = 'subscriptions';
	const ORDERS        = 'orders';

	/**
	 * Meta on llms_order after subscription migration.
	 *
	 * @var string
	 */
	const META_MIGRATED_SUB_ID = '_tlmt_migrated_to_native_subscription_id';

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
	 * @since 2.6.0
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
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function reset_map(): void {
		$this->mapper->clear_map();
	}

	/**
	 * Create a Tutor subscription plan from a Lifter access plan.
	 *
	 * @since 2.6.0
	 *
	 * @param int $llms_plan_id Lifter access plan ID.
	 * @param int $course_id    Course ID.
	 *
	 * @return int|false New Tutor plan ID or false.
	 */
	public function migrate_plan_for_access_plan( int $llms_plan_id, int $course_id ) {
		if ( ! Helper::is_subscription_migration_available() ) {
			return false;
		}

		if ( $llms_plan_id < 1 || $course_id < 1 ) {
			return false;
		}

		$plans_map = $this->mapper->get_map_by_key( self::PLANS );
		if ( isset( $plans_map[ $llms_plan_id ] ) ) {
			return (int) $plans_map[ $llms_plan_id ];
		}

		$plan_data = $this->plan_transformer->transform( $llms_plan_id );
		if ( empty( $plan_data ) ) {
			return false;
		}

		$object_id = (int) ( $plan_data['object_id'] ?? $course_id );
		unset( $plan_data['object_id'], $plan_data['llms_plan_id'] );

		$plan_id = $this->plan_model->create_subscription_plan( $object_id, $plan_data );
		if ( ! $plan_id ) {
			return false;
		}

		$plans_map[ $llms_plan_id ] = (int) $plan_id;
		$this->mapper->set_map_by_key( self::PLANS, $plans_map );

		return (int) $plan_id;
	}

	/**
	 * Migrate a batch of Lifter recurring orders to Tutor subscriptions.
	 *
	 * @since 2.6.0
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

		$order_ids    = Helper::get_recurring_order_ids();
		$existing_map = $this->mapper->get_map_by_key( self::SUBSCRIPTIONS );
		$pending_ids  = array();

		foreach ( $order_ids as $order_id ) {
			if ( ! isset( $existing_map[ $order_id ] ) ) {
				$pending_ids[] = (int) $order_id;
			}
		}

		$result['total'] = count( $order_ids );

		if ( empty( $pending_ids ) ) {
			$result['remaining'] = 0;
			$result['has_more']  = false;
			return $result;
		}

		$batch_ids = ( $batch_size > 0 ) ? array_slice( $pending_ids, 0, $batch_size ) : $pending_ids;

		foreach ( $batch_ids as $llms_order_id ) {
			if ( isset( $existing_map[ $llms_order_id ] ) ) {
				++$result['skipped'];
				continue;
			}

			try {
				$tutor_subscription_id = $this->migrate_subscription( $llms_order_id );
				if ( $tutor_subscription_id ) {
					++$result['migrated_in_batch'];
					$existing_map[ $llms_order_id ] = $tutor_subscription_id;
					update_post_meta( $llms_order_id, self::META_MIGRATED_SUB_ID, $tutor_subscription_id );
					OrderMigrator::remove_llms_order( $llms_order_id );
				} else {
					++$result['skipped'];
					$existing_map[ $llms_order_id ] = 0;
					update_post_meta( $llms_order_id, self::META_MIGRATED_SUB_ID, 0 );
				}
				$this->mapper->set_map_by_key( self::SUBSCRIPTIONS, $existing_map );
			} catch ( \Throwable $th ) {
				$result['errors'][] = sprintf(
					/* translators: 1: Lifter order id, 2: error message */
					__( 'Failed Lifter subscription order %1$d: %2$s', 'tutor-lms-migration-tool' ),
					$llms_order_id,
					$th->getMessage()
				);
				$existing_map[ $llms_order_id ] = 0;
				$this->mapper->set_map_by_key( self::SUBSCRIPTIONS, $existing_map );
			}
		}

		$order_ids_after    = Helper::get_recurring_order_ids();
		$existing_map_after = $this->mapper->get_map_by_key( self::SUBSCRIPTIONS );
		$pending_after      = 0;
		foreach ( $order_ids_after as $order_id ) {
			if ( ! isset( $existing_map_after[ $order_id ] ) ) {
				++$pending_after;
			}
		}

		$result['remaining'] = $pending_after;
		$result['has_more']  = $pending_after > 0;

		return $result;
	}

	/**
	 * Migrate a single Lifter recurring order.
	 *
	 * @since 2.6.0
	 *
	 * @param int $llms_order_id Lifter order ID.
	 *
	 * @return int|false Tutor subscription ID.
	 *
	 * @throws \Throwable When create fails.
	 */
	public function migrate_subscription( int $llms_order_id ) {
		$subscription_data = $this->subscription_transformer->transform( $llms_order_id );
		if ( empty( $subscription_data ) ) {
			return false;
		}

		$llms_plan_id = (int) ( $subscription_data['llms_plan_id'] ?? 0 );
		$course_id    = (int) ( $subscription_data['llms_course_id'] ?? 0 );
		$plans_map    = $this->mapper->get_map_by_key( self::PLANS );

		if ( $llms_plan_id && empty( $plans_map[ $llms_plan_id ] ) && $course_id ) {
			$this->migrate_plan_for_access_plan( $llms_plan_id, $course_id );
			$plans_map = $this->mapper->get_map_by_key( self::PLANS );
		}

		if ( empty( $plans_map[ $llms_plan_id ] ) ) {
			// Try any plan for this course.
			foreach ( $plans_map as $mapped_llms_plan => $tutor_plan ) {
				if ( $course_id === (int) get_post_meta( (int) $mapped_llms_plan, '_llms_product_id', true ) ) {
					$llms_plan_id = (int) $mapped_llms_plan;
					break;
				}
			}
		}

		if ( empty( $plans_map[ $llms_plan_id ] ) ) {
			return false;
		}

		$user_id  = (int) $subscription_data['user_id'];
		$plan_id  = (int) $plans_map[ $llms_plan_id ];
		$existing = $this->subscription_model->get_user_subscription_by_plan( $plan_id, $user_id );
		if ( $existing ) {
			$this->link_enrollments( $llms_order_id, (int) $existing->id );
			return (int) $existing->id;
		}

		$orders_data = $this->order_transformer->transform( $llms_order_id );
		$orders_map  = array();

		foreach ( $orders_data as $order ) {
			$source_id = (int) ( $order['llms_source_id'] ?? $order['llms_order_id'] ?? 0 );
			$meta_data = $order['meta_data'] ?? array();
			unset( $order['llms_source_id'], $order['llms_order_id'], $order['meta_data'], $order['tutor_plan_id'] );

			$tutor_order_id           = $this->order_model->create_order( $order );
			$orders_map[ $source_id ] = $tutor_order_id;

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

		// Point every order (initial + renewals) at the first subscription order as parent.
		$first_tutor_order_id  = (int) reset( $orders_map );
		$active_tutor_order_id = (int) end( $orders_map );

		foreach ( $orders_map as $tutor_order_id ) {
			QueryHelper::update(
				'tutor_orders',
				array( 'parent_id' => $first_tutor_order_id ),
				array( 'id' => (int) $tutor_order_id )
			);
		}

		$merged_orders = $this->mapper->get_map_by_key( self::ORDERS );
		$this->mapper->set_map_by_key( self::ORDERS, $merged_orders + $orders_map );

		unset(
			$subscription_data['llms_order_id'],
			$subscription_data['llms_course_id'],
			$subscription_data['llms_plan_id']
		);

		$subscription_data['plan_id']         = $plan_id;
		$subscription_data['first_order_id']  = $first_tutor_order_id;
		$subscription_data['active_order_id'] = $active_tutor_order_id;

		if ( empty( $subscription_data['next_payment_date_gmt'] ) ) {
			$subscription_data['next_payment_date_gmt'] = $subscription_data['end_date_gmt']
				?? $subscription_data['start_date_gmt']
				?? gmdate( 'Y-m-d H:i:s' );
		}

		$tutor_subscription_id = $this->subscription_model->create( $subscription_data );
		if ( ! $tutor_subscription_id ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: Lifter order ID */
					__( 'Could not create Tutor subscription for Lifter order %d', 'tutor-lms-migration-tool' ),
					$llms_order_id
				)
			);
		}

		$this->link_enrollments( $llms_order_id, (int) $tutor_subscription_id );

		return (int) $tutor_subscription_id;
	}

	/**
	 * Stamp `_tutor_subscription_id` on matching enrollments.
	 *
	 * @since 2.6.0
	 *
	 * @param int $llms_order_id         Lifter order ID.
	 * @param int $tutor_subscription_id Tutor subscription ID.
	 *
	 * @return void
	 */
	private function link_enrollments( int $llms_order_id, int $tutor_subscription_id ): void {
		if ( $tutor_subscription_id < 1 ) {
			return;
		}

		$enrollments = $this->enrollment_transformer->transform( $llms_order_id );
		foreach ( $enrollments as $enrollment ) {
			if ( empty( $enrollment->ID ) ) {
				continue;
			}
			SubscriptionModel::mark_as_subscription_enrollment( (int) $enrollment->ID, $tutor_subscription_id );
		}
	}
}
