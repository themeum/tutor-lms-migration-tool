<?php
/**
 * Handle user actions for migrating sales data
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData;

use Tutor\Traits\JsonResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Handle data migration
 *
 * @since 2.4.0
 */
class MigrationHandler {

	use JsonResponse;

	/**
	 * Register hooks
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_tlmt_migrate_sales_data', array( $this, 'ajax_handle_migration' ) );
	}

	/**
	 * Handle ajax request for migration
	 *
	 * @since 2.4.0
	 *
	 * @return void send wp_json response
	 */
	public function ajax_handle_migration() {
		// TODO.
	}

	/**
	 * Prepare migrate jobs with total number of orders,
	 * subscriptions, coupons that need to be migrated
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_migration_job() {

	}

	/**
	 * Get job schema
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_job_schema() {
		return array(
			'started_at'       => current_time( 'mysql', false ),
			'progress'         => 0,
			'status'           => 'in progress',
			'message'          => 'Migrating sales data...',
			'job_requirements' => array(
				'orders'        => array(
					'total'     => 0,
					'processed' => 0,
					'succeed'   => array(),
					'failed'    => array(),
				),
				'subscriptions' => array(
					'total'     => 0,
					'succeed'   => array(),
					'failed'    => array(),
					'processed' => 0,
				),
				'coupons'       => array(
					'total'     => 0,
					'succeed'   => array(),
					'failed'    => array(),
					'processed' => 0,
				),
			),

		);
	}
}
