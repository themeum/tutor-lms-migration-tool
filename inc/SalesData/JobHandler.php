<?php
/**
 * Handle migration job
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData;

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
	 * If not items available then skip and return
	 *
	 * @since 2.4.0
	 *
	 * @throws \Throwable If failed to create sales data object.
	 *
	 * @param string $active_job_type Currently active job type like: orders, coupons, etc.
	 * @param array  $job_data Job data array.
	 *
	 * @return array updated job data.
	 */
	public function process_job( string $active_job_type, array $job_data ) {
		try {
			$requirements    = $job_data['requirements'];
			$active_job      = $requirements[ $active_job_type ];
			$total_items     = $active_job['total'];
			$processed_items = $active_job['processed'];
			$limit           = 10;

			if ( ! $total_items ) {
				return;
			}

			$data_obj = tlmt_get_sales_data_object( $active_job_type, MigrationTypes::WC_TO_NATIVE );
			$items    = $data_obj->get_items( $limit, $processed_items );

			// Migrate each item.
			foreach ( $items as $item ) {
				try {
					$data_obj->extract( $item )->transform()->migrate();

					// Keep track.
					$processed_items++;
					$active_job['succeed'][] = $data_obj->get_item_id( $item );
				} catch ( \Throwable $th ) {
					$job_data['error_log'][] = $th->getMessage();
					$active_job['failed'][]  = $data_obj->get_item_id( $item );
				}
			}

			// Mark as done if all items are processed.
			$active_job['processed'] = $processed_items;
			if ( $processed_items >= $total_items ) {
				$active_job['is_done'] = true;
			}

			// Update job data.
			$requirement[ $active_job_type ] = $active_job;
			$job_data['requirements']        = $requirement;
			$job_data['progress']            = $this->get_job_progress( $job_data );

			$this->update_job_data( $job_data );
			return $job_data;

		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Update job data
	 *
	 * @since 2.4.0
	 *
	 * @param array $job_data Job data array.
	 *
	 * @return void
	 */
	public function update_job_data( array $job_data ) {
		$job_id = $job_data['job_id'];
		update_option( self::JOB_OPT_NAME . $job_id, wp_json_encode( $job_data ) );
	}

	/**
	 * Prepare migrate jobs with total number of orders,
	 * subscriptions, coupons that need to be migrated
	 *
	 * @since 2.4.0
	 *
	 * @throws \Throwable If failed to create sales data object.
	 *
	 * @param array $requirements Migration requirements.
	 * @param mixed $job_id Unique job id.
	 *
	 * @return array
	 */
	public function get_migration_job( array $requirements, $job_id = 0 ): array {
		if ( $job_id ) {
			$job_data = get_option( self::JOB_OPT_NAME . $job_id, null );
			return json_decode( $job_data, true );
		}

		$job_schema       = $this->get_job_schema();
		$job_requirements = $job_schema['requirements'];

		foreach ( $job_requirements as $key => $requirement ) {
			if ( ! in_array( $key, $requirements ) ) {
				unset( $job_requirements[ $key ] );
			}
		}

		// Prepare the migration items.
		foreach ( $job_requirements as $key => $requirement ) {
			try {
				$data_obj = tlmt_get_sales_data_object( $key, MigrationTypes::WC_TO_NATIVE );
			} catch ( \Throwable $th ) {
				throw $th;
			}

			$job_requirements[ $key ]['total'] = call_user_func( array( $data_obj, 'get_total_items_count' ) );
		}

		$job_schema['requirements'] = $job_requirements;

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
	public function get_active_job_type( array $job_data ) {
		$requirements = $job_data['requirements'];
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
		$requirements  = $job_data['requirements'];
		$total_jobs    = count( $requirements );
		$completed_job = 0;
		foreach ( $requirements as $requirement ) {
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
			'job_id'       => uniqid(),
			'started_at'   => current_time( 'mysql', false ),
			'progress'     => 0,
			'status'       => '',
			'message'      => '',
			'requirements' => array(
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
			'error_log'    => array(),

		);
	}
}
