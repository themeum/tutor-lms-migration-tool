<?php
/**
 * Concrete class to handle earning data migration
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

		try {
			$this->tutor_wc_order_earnings = QueryHelper::get_row(
				$this->tutor_earning_table,
				array(
					'order_id'     => $this->old_order_id,
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
	}

	/**
	 * Convert WooCommerce Earnings to Tutor Earnings.
	 *
	 * @since 2.4.0
	 *
	 * @throws \Exception If earning data not found.
	 *
	 * @return void
	 */
	private function transform() {
		if ( $this->tutor_wc_order_earnings ) {
			$transformed_earnings = array(
				'order_id'   => $this->new_order_id,
				'process_by' => 'tutor',
			);

			$this->tutor_transformed_order_earnings = $transformed_earnings;
		} else {
			throw new \Exception( esc_html__( 'Earnings not found for order', 'tutor-lms-migration-tool' ) ); //phpcs:ignore
		}
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
			$this->transform();
		} catch ( \Exception $e ) {
			throw $e;
		}

		// Update earnings data.
		$result = QueryHelper::update(
			$this->tutor_earning_table,
			$this->tutor_transformed_order_earnings,
			array( 'earning_id' => $this->tutor_wc_order_earnings->earning_id ),
		);

		if ( ! $result ) {
            throw new \Exception( esc_html__( 'Error updating earning data.', 'tutor-lms-migration-tool' ) ); //phpcs:ignore
		}

		return $result;
	}
}
