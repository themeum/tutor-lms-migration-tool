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

		return $wc_earnings;
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
	 * @param int|object $order the order object or id.
	 *
	 * @return MigrationTemplate
	 */
	public function extract( $order ): MigrationTemplate {
		return $this;
	}

	/**
	 * Convert WooCommerce Earnings to Tutor Earnings.
	 *
	 * @since 2.4.0
	 *
	 * @return MigrationTemplate
	 */
	public function transform(): MigrationTemplate {
		return $this;
	}

	/**
	 * Migrate WooCommerce Earnings to Tutor Earnings.
	 *
	 * @since 2.4.0
	 *
	 * @return boolean
	 */
	public function migrate(): bool {
		return true;
	}
}
