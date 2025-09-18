<?php
/**
 * Handle migration job
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace TutorLMSMigrationTool\SalesData;

use Themeum\TutorLMSMigrationTool\MigrationTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Job handler class
 *
 * @since 2.4.0
 */
class JobHandler {

	/**
	 * Job option name
	 *
	 * The job_id will concat with the option name to uniquely
	 * identify the job
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	const JOB_OPT_NAME = 'tutor_migration_';

	/**
	 * Process the active job
	 *
	 * @since 2.4.0
	 *
	 * @param string $active_job Currently active job type.
	 * @param array  $job_data Job data array.
	 *
	 * @return wp_rest response
	 */
	public function process_job( string $active_job, array $job_data ) {
		try {
			$job_object = tlmt_get_sales_data_object( $active_job, MigrationTypes::WC_TO_NATIVE );

			$requirements    = $job_data['requirements'];
			$active_job      = $requirements[ $active_job ];
			$total_items     = $active_job['total'];
			$processed_items = $active_job['processed'];
			$limit           = 5;

			// TODO migrate each ite.
			$this->get_orders( $limit, $processed_items );

		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Prepare migrate jobs with total number of orders,
	 * subscriptions, coupons that need to be migrated
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $job_id Unique job id.
	 * @param array $requirements Migration requirements.
	 *
	 * @return array
	 */
	public function get_migration_job( $job_id, array $requirements ): array {
		if ( $job_id ) {
			$job_data = get_option( self::JOB_OPT_NAME . $job_id, null );
			return json_decode( $job_data );
		}

		$job_schema       = $this->get_job_schema();
		$job_requirements = $job_schema['job_requirements'];

		foreach ( $job_requirements as $key => $requirement ) {
			if ( ! in_array( $key, $requirements ) ) {
				unset( $job_requirements[ $key ] );
			}
		}

		// Prepare the migration items.
		foreach ( $job_requirements as $key => $requirement ) {
			$job_requirements[ $key ]['total'] = call_user_func( array( $this, "get_total_{$key}_count" ) );
		}

		$job_schema['job_requirements'] = $job_requirements;

		return $job_schema;
	}

	/**
	 * Get active job from the job data
	 *
	 * @since 2.4.0
	 *
	 * @param array $job_data Job data array.
	 *
	 * @return string|bool Active job key or false if no active job found
	 */
	public function get_active_job( array $job_data ) {
		$requirements = $job_data['job_requirements'];
		foreach ( $requirements as $key => $requirement ) {
			if ( $requirement['is_done'] ) {
				continue;
			}
			return $key;
		}

		return false;
	}

	/**
	 * Get job progress
	 *
	 * @since 2.4.0
	 *
	 * @throws \Throwable If an error occurs during calculation.
	 *
	 * @param array $job_data Job data.
	 *
	 * @return int
	 */
	public function get_job_progress( array $job_data ) {
		$requirements  = $job_data['job_requirements'];
		$total_jobs    = count( $requirements );
		$completed_job = 0;
		foreach ( $requirements as $key => $requirement ) {
			if ( $requirement['is_done'] ) {
				$completed_job++;
			}
		}

		try {
			$progress = ( $completed_job / $total_jobs ) * 100;
			return (int) $progress;
		} catch ( \Throwable $th ) {
			throw $th;
		}
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
			'job_id'           => wp_rand( 10, 15 ),
			'started_at'       => current_time( 'mysql', false ),
			'progress'         => 0,
			'status'           => '',
			'message'          => 'Migrating sales data...',
			'job_requirements' => array(
				'orders'        => array(
					'total'     => 0,
					'processed' => 0,
					'succeed'   => array(),
					'failed'    => array(),
					'is_done'   => false,
				),
				'subscriptions' => array(
					'total'     => 0,
					'processed' => 0,
					'succeed'   => array(),
					'failed'    => array(),
					'is_done'   => false,
				),
				'coupons'       => array(
					'total'     => 0,
					'processed' => 0,
					'succeed'   => array(),
					'failed'    => array(),
					'is_done'   => false,
				),
			),
			'error_log'        => array(),

		);
	}
}
