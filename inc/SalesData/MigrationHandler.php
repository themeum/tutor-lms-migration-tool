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
use Tutor\Helpers\HttpHelper;
use Tutor\Traits\JsonResponse;
use Themeum\TutorLMSMigrationTool\SalesDataTypes;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Helper;

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
	const STATUS_COMPLETED   = 'completed';
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
		add_action( 'wp_ajax_tmlt_get_sales_data_history', array( $this, 'ajax_get_sales_data_history' ) );
		add_action( 'wp_ajax_tlmt_delete_sales_data_history', array( $this, 'ajax_delete_sales_data_history' ) );
		$this->job_handler = new JobHandler();
	}

	/**
	 * Handle ajax request for migration
	 *
	 * @since 2.4.0
	 *
	 * @return void wp_json response
	 */
	public function ajax_handle_migration() {
		if ( ! tutor_utils()->is_nonce_verified() ) {
			$this->response_bad_request( __( 'Invalid nonce', 'tutor-lms-migration-tool' ) );
		}

		tutor_utils()->check_current_user_capability();

		$job_data = null;
		$job_id   = Input::post( 'job_id' );
		if ( $job_id ) {
			$job_data = $this->job_handler->get_job_data( $job_id );
		} else {
			$requirements = Input::post( 'job_requirements', array(), Input::TYPE_ARRAY );
			if ( ! $requirements ) {
				$this->response_bad_request( __( 'Invalid job id or requirements', 'tutor-lms-migration-tool' ) );
			}

			$job_data = $this->job_handler->get_migration_job( $requirements, $job_id );
		}

		if ( ! $job_data || empty( $job_data['requirements'] ) ) {
			$this->response_bad_request( __( 'Invalid job data', 'tutor-lms-migration-tool' ) );
		}

		$active_job_type = $this->job_handler->get_active_job_type( $job_data );
		if ( $active_job_type ) {
			try {
				$job_data = $this->job_handler->process_job( $active_job_type, $job_data );
				if ( $job_data['progress'] >= 100 ) {
					// Action hook.
					do_action( 'tlmt_after_job_complete', $job_data );

					$this->json_response( __( 'Migration completed successfully', 'tutor-lms-migration-tool' ), $job_data );
				}

				$this->json_response( __( 'Migration in progress', 'tutor-lms-migration-tool' ), $job_data );
			} catch ( \Throwable $th ) {
				$this->json_response( __( 'Migration failed', 'tutor-lms-migration-tool' ), $job_data, HttpHelper::STATUS_INTERNAL_SERVER_ERROR );
			}
		}
	}

	/**
	 * Get migration data types
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_migration_data_types() {
		$types = array(
			SalesDataTypes::ORDERS,
			SalesDataTypes::COUPONS,
		);

		if ( self::is_active_wc_subscription() ) {
			$types[] = SalesDataTypes::SUBSCRIPTIONS;
		}

		return $types;
	}

	/**
	 * Check if WooCommerce Subscriptions plugin is active
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	public static function is_active_wc_subscription(): bool {
		return is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) && tutor_utils()->is_addon_enabled( 'wc-subscriptions' );
	}

	/**
	 * Get sales data history
	 *
	 * @since 2.4.0
	 *
	 * @return void wp_json response
	 */
	public function ajax_get_sales_data_history() {
		if ( ! tutor_utils()->is_nonce_verified() ) {
			$this->response_bad_request( __( 'Invalid nonce', 'tutor-lms-migration-tool' ) );
		}

		tutor_utils()->check_current_user_capability();

		$history = Helper::get_wc_migration_history();
		$this->json_response( __( 'Sales data history retrieved successfully', 'tutor-lms-migration-tool' ), $history );
	}

	/**
	 * Delete sales data history
	 *
	 * @since 2.4.0
	 *
	 * @return void wp_json response
	 */
	public function ajax_delete_sales_data_history() {
		if ( ! tutor_utils()->is_nonce_verified() ) {
			$this->response_bad_request( __( 'Invalid nonce', 'tutor-lms-migration-tool' ) );
		}

		$option_id = Input::post( 'option_id', 0, Input::TYPE_INT );

		if ( ! $option_id ) {
			$this->response_bad_request( __( 'Option ID is required to delete history', 'tutor-lms-migration-tool' ) );
		}

		try {
			$this->delete_sales_data_history( $option_id );
		} catch ( \InvalidArgumentException $e ) {
			$this->response_bad_request( $e->getMessage() );
		} catch ( \Exception $e ) {
			$this->response_bad_request( $e->getMessage() );
		}

		$this->json_response( __( 'History deleted successfully!', 'tutor-lms-migration-tool' ) );
	}

	/**
	 * Delete sales data history
	 *
	 * @since 2.4.0
	 *
	 * @param string $option_id option id.
	 *
	 * @return bool|\WP_Error
	 */
	public function delete_sales_data_history( string $option_id ) {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options
				WHERE option_id = %d
				AND option_name LIKE %s",
				$option_id,
				'tutor_migration_%'
			)
		);

		if ( false === $deleted ) {
			return new \WP_Error( 'db_error', __( 'Database error occurred while deleting history', 'tutor-lms-migration-tool' ) );
		}

		return true;
	}
}
