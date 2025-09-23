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

use Themeum\TutorLMSMigrationTool\SalesDataTypes;
use Tutor\Helpers\HttpHelper;
use TUTOR\Input;
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
	 * Job statuses
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	const STATUS_PENDING     = 'pending';
	const STATUS_IN_PROGRESS = 'in_progress';
	const STATUS_SUCCESS     = 'success';
	const STATUS_FAILED      = 'failed';

	/**
	 * Job handler instance
	 *
	 * @since 2.4.0
	 *
	 * @var JobHandler
	 */
	private $job_handler;

	/**
	 * Register hooks
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_tlmt_migrate_sales_data', array( $this, 'ajax_handle_migration' ) );
		$this->job_handler = new JobHandler();
	}

	/**
	 * Handle ajax request for migration
	 *
	 * @since 2.4.0
	 *
	 * @return wp_json response
	 */
	public function ajax_handle_migration() {
		tutor_utils()->checking_nonce();
		tutor_utils()->check_current_user_capability();

		$job_id       = Input::post( 'job_id' );
		$requirements = $this->get_migration_data_types();
		if ( ! $requirements ) {
			$this->response_bad_request( __( 'Invalid job id or requirements', 'tutor-pro' ) );
		}

		$requirements = is_array( $requirements ) ? $requirements : json_decode( $requirements, true );
		if ( json_last_error() ) {
			$this->response_bad_request( __( 'Invalid job requirements', 'tutor-pro' ) );
		}

		$job_data   = $this->job_handler->get_migration_job( $requirements, $job_id );
		$active_job = $this->job_handler->get_active_job_type( $job_data );
		if ( $active_job ) {
			try {
				$job_data = $this->job_handler->process_job( $active_job, $job_data );
				$this->json_response( __( 'Migration in progress', 'tutor-pro' ), $job_data );
			} catch ( \Throwable $th ) {
				$this->json_response( __( 'Migration failed', 'tutor-pro' ), $job_data, HttpHelper::STATUS_INTERNAL_SERVER_ERROR );
			}
		}

		$job_data['status']   = self::STATUS_SUCCESS;
		$job_data['progress'] = 100;

		return $this->json_response( __( 'Migration completed successfully', 'tutor-pro' ), $job_data );
	}

	/**
	 * Get migration data types
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_migration_data_types() {
		return array(
			SalesDataTypes::ORDERS,
			SalesDataTypes::COUPONS,
			SalesDataTypes::SUBSCRIPTIONS,
		);
	}

}
