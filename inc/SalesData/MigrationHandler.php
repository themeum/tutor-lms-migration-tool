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
		$requirements = sanitize_text_field( wp_unslash( $_POST['job_requirements'] ?? '' ) );
		if ( ! $job_id || ! $requirements ) {
			$this->response_bad_request( __( 'Invalid job id or requirements', 'tutor-pro' ) );
		}

		$requirements = json_decode( stripslashes( $requirements ), true );
		if ( json_last_error_msg() ) {
			$this->response_bad_request( __( 'Invalid job requirements', 'tutor-pro' ) );
		}

		$job_data   = $this->job_handler->get_migration_job( $job_id, $requirements );
		$active_job = $this->job_handler->get_active_job( $job_data );
		if ( $active_job ) {
			return $this->job_handler->process_job( $active_job, $job_data );
		}

		$job_data['status']   = self::STATUS_SUCCESS;
		$job_data['progress'] = 100;

		return $this->json_response( __( 'Migration completed successfully', 'tutor-pro' ), $job_data );
	}

}
