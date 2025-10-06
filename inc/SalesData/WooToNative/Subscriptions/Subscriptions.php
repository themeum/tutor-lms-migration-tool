<?php
/**
 * Concrete class to handle subscription data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions;

use AllowDynamicProperties;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;
use Themeum\TutorLMSMigrationTool\MigrationMapper;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Transformers\EnrollmentDataTransformer;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Transformers\OrderDataTransformer;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Transformers\PlanDataTransformer;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Transformers\SubscriptionDataTransformer;
use TUTOR\Course;
use TUTOR\Earnings;
use Tutor\Helpers\QueryHelper;
use Tutor\Models\OrderModel;
use TutorPro\Subscription\Models\PlanModel;
use TutorPro\Subscription\Models\SubscriptionModel;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Subscriptions implements MigrationTemplate {
	/**
	 * Map key for subscription migration
	 *
	 * @var string
	 */
	const MAP_KEY = 'tutor_wc2native_subscription_map';

	/**
	 * Constants
	 *
	 * @var string
	 */
	const PLANS        = 'plans';
	const SUBSCRIPTION = 'subscription';
	const ENROLLMENTS  = 'enrollments';
	const ORDERS       = 'orders';


	/**
	 * Subscription object
	 *
	 * @var WC_Subscription
	 */
	protected $subscription;

	/**
	 * Transformed data
	 *
	 * @var array
	 */
	protected $transformed_data;

	/**
	 * Plan model
	 *
	 * @var PlanModel
	 */
	protected $plan_model;

	/**
	 * Order model
	 *
	 * @var OrderModel
	 */
	protected $order_model;

	/**
	 * Subscription model
	 *
	 * @var SubscriptionModel
	 */
	protected $subscription_model;

	/**
	 * Subscriptions migration mapper
	 *
	 * @var MigrationMapper
	 */
	protected $mapper;

	/**
	 * Constructor
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		$this->mapper = new MigrationMapper( self::MAP_KEY );
		$this->mapper->clear_map();

		// Models.
		$this->plan_model         = new PlanModel();
		$this->order_model        = new OrderModel();
		$this->subscription_model = new SubscriptionModel();

		// Data Transformers.
		$this->plan_data_transformer         = new PlanDataTransformer();
		$this->order_data_transformer        = new OrderDataTransformer();
		$this->subscription_data_transformer = new SubscriptionDataTransformer();
		$this->enrollment_data_transformer   = new EnrollmentDataTransformer();
	}

	/**
	 * Migrate WC plans to Tutor plans
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	public function migrate_plans(): bool {
		$plans     = $this->plan_data_transformer->transform();
		$plans_map = array();

		foreach ( $plans as $plan ) {
			// Course/Bundle ID.
			$object_id   = $plan['object_id'];
			$new_plan_id = $this->plan_model->create_subscription_plan( $object_id, $plan );

			// Update selling option to subscription.
			update_post_meta( $object_id, Course::COURSE_SELLING_OPTION_META, Course::SELLING_OPTION_SUBSCRIPTION );

			$plans_map[ $plan['product_id'] ] = $new_plan_id;
		}

		$this->mapper->set_map_by_key( 'plans', $plans_map );

		return true;
	}

	/**
	 * Log data
	 *
	 * @since 2.4.0
	 *
	 * @param array $data Data to log.
	 */
	private function log_data( $data ) {
		file_put_contents( __DIR__ . '/subscriptions.json', json_encode( $data, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Get wc subscription status list for migration.
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	private function get_wc_subscription_status_list() {
		return array( 'pending', 'active', 'on-hold', 'cancelled', 'expired' );
	}

	/**
	 * Get total number of subscriptions
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_total_items_count(): int {
		$subscription_ids = wcs_get_subscriptions_for_product(
			Helper::get_wc_plan_ids(),
			'ids',
			array( 'subscription_status' => $this->get_wc_subscription_status_list() )
		);
		return count( $subscription_ids );
	}

	/**
	 * Get list of subscription that need to be migrated
	 *
	 * @since 2.4.0
	 *
	 * @param int $limit  Number of subscriptions to fetch from source.
	 * @param int $offset Number of subscriptions to skip from source.
	 *
	 * @return array list of WC_Subscription
	 */
	public function get_items( int $limit = 5, int $offset = 0 ): array {
		$subscriptions = wcs_get_subscriptions_for_product(
			Helper::get_wc_plan_ids(),
			'subscription',
			array(
				'limit'               => $limit,
				'offset'              => $offset,
				'subscription_status' => $this->get_wc_subscription_status_list(),
			)
		);

		return $subscriptions;
	}

	/**
	 * Get item id
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Subscription $subscription subscription object.
	 *
	 * @return int
	 */
	public function get_item_id( $subscription ): int {
		return $subscription->get_id();
	}

	/**
	 * Extract subscription data
	 *
	 * @since 2.4.0
	 *
	 * @param int|object $subscription Subscription id or object.
	 *
	 * @return MigrationTemplate
	 */
	public function extract( $subscription ): MigrationTemplate {
		$this->subscription = $subscription;

		return $this;
	}

	/**
	 * Transform the subscription data to native subscription
	 *
	 * @since 2.4.0
	 *
	 * @return MigrationTemplate
	 */
	public function transform(): MigrationTemplate {
		$this->transformed_data = array(
			self::PLANS        => $this->plan_data_transformer->transform( $this->subscription ),
			self::SUBSCRIPTION => $this->subscription_data_transformer->transform( $this->subscription ),
			self::ENROLLMENTS  => $this->enrollment_data_transformer->transform( $this->subscription ),
			self::ORDERS       => $this->order_data_transformer->transform( $this->subscription ),
		);

		$this->log_data( $this->transformed_data );

		return $this;
	}

	/**
	 * Migrate the subscription, store in database
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 *
	 * @throws \Throwable If fails to create subscription.
	 */
	public function migrate(): bool {
		try {
			// Orders migration.
			$orders_map = array();
			foreach ( $this->transformed_data[ self::ORDERS ] as $order ) {
				$wc_order_id = $order['wc_order_id'];
				$meta_data   = $order['meta_data'];
				unset( $order['wc_order_id'] );
				unset( $order['meta_data'] );

				$tutor_order_id             = $this->order_model->create_order( $order );
				$orders_map[ $wc_order_id ] = $tutor_order_id;

				// Store order meta data.
				$meta_data = array_map(
					function ( $meta ) use ( $tutor_order_id ) {
						$meta['order_id'] = $tutor_order_id;
						return $meta;
					},
					$meta_data
				);
				QueryHelper::insert_multiple_rows( 'tutor_ordermeta', $meta_data, false, false );
			}

			// Update parent order id.
			$wc_parent_order_id = $this->subscription->get_parent_id();
			if ( isset( $orders_map[ $wc_parent_order_id ] ) ) {
				$tutor_parent_order_id = $orders_map[ $wc_parent_order_id ];

				foreach ( $orders_map as $wc_order_id => $tutor_order_id ) {
					QueryHelper::update(
						'tutor_orders',
						array( 'parent_id' => $tutor_parent_order_id ),
						array( 'id' => $tutor_order_id )
					);
				}
			}

			$this->mapper->set_map_by_key( self::ORDERS, $orders_map );

			// Subscription migration.
			$plans_map         = $this->mapper->get_map_by_key( self::PLANS );
			$subscription_data = $this->transformed_data[ self::SUBSCRIPTION ];
			if ( $subscription_data ) {
				$subscription_data['plan_id']         = $plans_map[ $subscription_data['plan_id'] ];
				$subscription_data['first_order_id']  = $orders_map[ $subscription_data['first_order_id'] ];
				$subscription_data['active_order_id'] = $orders_map[ $subscription_data['active_order_id'] ];

				$tutor_subscription_id = $this->subscription_model->create( $subscription_data );
			}

			// Enrollment migration.
			$enrollments = $this->transformed_data[ self::ENROLLMENTS ];
			if ( tutor_utils()->count( $enrollments ) && $tutor_subscription_id ) {
				foreach ( $enrollments as $enrollment ) {
					$this->subscription_model->mark_as_subscription_enrollment( $enrollment->post_id, $tutor_subscription_id );
				}
			}

			// Earning migration.
			foreach ( $orders_map as $wc_order_id => $tutor_order_id ) {
				QueryHelper::update(
					'tutor_earnings',
					array(
						'order_id'   => $tutor_order_id,
						'process_by' => Earnings::PROCESS_BY_TUTOR,
					),
					array(
						'order_id'   => $wc_order_id,
						'process_by' => Earnings::PROCESS_BY_WOOCOMMERCE,
					)
				);
			}

			return true;
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}
