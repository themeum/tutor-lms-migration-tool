<?php
/**
 * Class to handle earning data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Orders;

use AllowDynamicProperties;
use Tutor\Helpers\QueryHelper;

defined( 'ABSPATH' ) || exit;


#[AllowDynamicProperties]
/**
 * WooCommerce to Tutor Earnings migration class.
 */
class Earnings {

	/**
	 * Tutor earning table.
	 *
	 * @var string
	 */
	private $tutor_earning_table = 'tutor_earnings';

	/**
	 * Tutor WooCommerce Order Earnings.
	 *
	 * @var array
	 */
	private $tutor_wc_order_earnings_id = array();

	/**
	 * Old Order ID.
	 *
	 * @var integer
	 */
	private $old_order_id = 0;

	/**
	 * New Order ID.
	 *
	 * @var integer
	 */
	private $new_order_id = 0;


	/**
	 * Extract earning data from order.
	 *
	 * @since 2.4.0
	 *
	 * @throws \Exception If data cannot be obtained.
	 *
	 * @param int|object $order the order object or id.
	 *
	 * @return void
	 */
	private function extract( $order ) {
		$this->old_order_id = (int) $order->old_order_id;
		$this->new_order_id = (int) $order->new_order_id;

		$this->tutor_wc_order_earnings_id = QueryHelper::query(
			$this->tutor_earning_table,
			array(
				'select' => 'earning_id',
				'where'  => array(
					'order_id'     => $this->old_order_id,
					'process_by'   => 'woocommerce',
					'order_status' => array(
						'IN',
						tutor_utils()->get_earnings_completed_statuses(),
					),
				),
			),
		);

		$this->tutor_wc_order_earnings_id = array_column(
			$this->tutor_wc_order_earnings_id,
			'earning_id'
		);
	}

	/**
	 * Migrate WooCommerce Earnings to Tutor Earnings.
	 *
	 * @since 2.4.0
	 *
	 * @throws \Exception If earning data cannot be updated.
	 *
	 * @param object $order the order object.
	 *
	 * @return boolean
	 */
	public function migrate( $order ): bool {
		// Extract the data.
		try {
			$this->extract( $order );
		} catch ( \Exception $e ) {
			throw $e;
		}

		if ( ! $this->tutor_wc_order_earnings_id ) {
			return false;
		}

		$transformed_earnings = array(
			'order_id'   => $this->new_order_id,
			'process_by' => 'tutor',
		);

		$earning_ids = QueryHelper::prepare_in_clause( $this->tutor_wc_order_earnings_id );

		// Update earnings data.
		$result = QueryHelper::update_where_in(
			$this->tutor_earning_table,
			$transformed_earnings,
			$earning_ids,
			'earning_id'
		);

		return $result;
	}
}
