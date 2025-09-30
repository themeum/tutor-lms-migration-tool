<?php
/**
 * Concrete class to handle earning data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Earnings;

use AllowDynamicProperties;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;
use Themeum\TutorLMSMigrationTool\MigrationMapper;
use Tutor\Helpers\QueryHelper;

defined( 'ABSPATH' ) || exit;


#[AllowDynamicProperties]
/**
 * WooCommerce to Tutor Earnings migration class.
 */
class Earnings implements MigrationTemplate {

	/**
	 * Tutor earning table.
	 *
	 * @var string
	 */
	private $tutor_earning_table = 'tutor_earnings';

	/**
	 * Tutor WooCommerce Order Earnings.
	 *
	 * @var mixed
	 */
	private $tutor_wc_order_earnings = null;

	/**
	 * Transformed Tutor Order Earnings List.
	 *
	 * @var array
	 */
	private $tutor_transformed_order_earnings = array();

	/**
	 * Tutor Migration Tool Mapper Class Instance.
	 *
	 * @var MigrationMapper
	 */
	private $migration_mapper;

	/**
	 * Earning Migration Class Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get Tutor Earnings List.
	 *
	 * @since 2.4.0
	 *
	 * @throws \Exception If data cannot be obtained.
	 *
	 * @param integer $limit the data limit.
	 * @param integer $offset the data offset.
	 *
	 * @return array
	 */
	public function get_items( int $limit = 5, int $offset = 0 ): array {
		$wc_earnings = array();

		try {
			$wc_earnings = QueryHelper::get_all_with_search(
				$this->tutor_earning_table,
				array(
					'process_by'   => 'woocommerce',
					'order_status' => array(
						'IN',
						tutor_utils()->get_earnings_completed_statuses(),
					),
				),
				array(),
				'created_at',
				$limit,
				$offset,
				'DESC',
				'OBJECT'
			);
		} catch ( \Exception $e ) {
			throw $e;
		}

		return $wc_earnings['results'];
	}

	/**
	 * Get Tutor Earnings Count.
	 *
	 * @since 2.4.0
	 *
	 * @throws \Exception If data cannot be obtained.
	 *
	 * @return integer
	 */
	public function get_total_items_count(): int {
		$wc_earning_count = 0;

		try {
			$wc_earning_count = QueryHelper::get_count(
				$this->tutor_earning_table,
				array(
					'process_by'   => 'woocommerce',
					'order_status' => array(
						'IN',
						tutor_utils()->get_earnings_completed_statuses(),
					),
				),
				array(),
				'earning_id'
			);
		} catch ( \Exception $e ) {
			throw $e;
		}

		return $wc_earning_count;
	}

	/**
	 * Extract earning data from order.
	 *
	 * @since 2.4.0
	 *
	 * @throws \Exception If data cannot be obtained.
	 *
	 * @param int|object $earning the earning object or id.
	 *
	 * @return MigrationTemplate
	 */
	public function extract( $earning ): MigrationTemplate {
		try {
			$this->tutor_wc_order_earnings = QueryHelper::get_row(
				$this->tutor_earning_table,
				array(
					'order_id'     => $earning->order_id,
					'course_id'    => $earning->course_id,
					'process_by'   => 'woocommerce',
					'order_status' => array(
						'IN',
						tutor_utils()->get_earnings_completed_statuses(),
					),
				),
				'created_at'
			);
		} catch ( \Exception $e ) {
			throw $e;
		}

		return $this;
	}

	/**
	 * Convert WooCommerce Earnings to Tutor Earnings.
	 *
	 * @since 2.4.0
	 *
	 * @throws \Exception If earning data not found.
	 *
	 * @return MigrationTemplate
	 */
	public function transform(): MigrationTemplate {
		if ( $this->tutor_wc_order_earnings ) {
			$transformed_earnings = array(
				'user_id'                  => $this->tutor_wc_order_earnings->user_id,
				'order_id'                 => $this->get_total_items_count() + 1, // TODO: need to update this with mapped order id.
				'order_status'             => $this->tutor_wc_order_earnings->order_status,
				'course_price_total'       => $this->tutor_wc_order_earnings->course_price_total,
				'course_price_grand_total' => $this->tutor_wc_order_earnings->course_price_grand_total,
				'instructor_amount'        => $this->tutor_wc_order_earnings->instructor_amount,
				'instructor_rate'          => $this->tutor_wc_order_earnings->instructor_rate,
				'admin_amount'             => $this->tutor_wc_order_earnings->admin_amount,
				'admin_rate'               => $this->tutor_wc_order_earnings->admin_rate,
				'commission_type'          => $this->tutor_wc_order_earnings->commission_type,
				'deduct_fees_amount'       => $this->tutor_wc_order_earnings->deduct_fees_amount,
				'deduct_fees_type'         => $this->tutor_wc_order_earnings->deduct_fees_type,
				'process_by'               => 'tutor',
				'created_at'               => $this->tutor_wc_order_earnings->created_at,
			);

			$this->tutor_transformed_order_earnings = $transformed_earnings;
		} else {
			throw new \Exception( esc_html__( 'Earnings not found for order', 'tutor-lms-migration-tool' ) ); //phpcs:ignore
		}
		return $this;
	}

	/**
	 * Migrate WooCommerce Earnings to Tutor Earnings.
	 *
	 * @since 2.4.0
	 *
	 * @throws \Exception If cannot insert data.
	 *
	 * @return boolean
	 */
	public function migrate(): bool {
		// Insert earnings data.
		try {
			$earning_id = QueryHelper::insert(
				$this->tutor_earning_table,
				$this->tutor_transformed_order_earnings
			);
		} catch ( \Exception $e ) {
			throw $e;
		}

		return true;
	}
}
