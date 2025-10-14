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
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Helper;
use Tutor\Helpers\QueryHelper;
use Tutor\Models\OrderModel;

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
	 * Tutor WooCommerce Order Earnings IDs.
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
	 * Remove subscription based products from earnings.
	 *
	 * @since 2.4.0
	 *
	 * @return void
	 */
	private function filter_subscription_products() {

		if ( ! count( $this->tutor_wc_order_earnings_id ) ) {
			return;
		}

		foreach ( $this->tutor_wc_order_earnings_id as $key => $tutor_earnings ) {
			$product_id      = get_post_meta( $tutor_earnings['course_id'], '_tutor_course_product_id', true ) ?? 0;
			$is_subscription = wc_get_product( $product_id ) && Helper::check_wc_subscription_product(
				wc_get_product( $product_id )
			);

			if ( $is_subscription ) {
				unset( $this->tutor_wc_order_earnings_id[ $key ] );
			}
		}
	}


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
				'select' => array( 'earning_id', 'course_id', 'order_status' ),
				'where'  => array(
					'order_id'   => $this->old_order_id,
					'process_by' => 'woocommerce',
				),
			),
		);

		$earnings = array();

		if ( count( $this->tutor_wc_order_earnings_id ) ) {
			foreach ( $this->tutor_wc_order_earnings_id as $earning ) {
				$earnings[ $earning->earning_id ] = array(
					'order_id'     => $this->new_order_id,
					'process_by'   => 'tutor',
					'course_id'    => $earning->course_id,
					'order_status' => Helper::get_earning_order_status( $earning->order_status ),
				);
			}
		}

		$this->tutor_wc_order_earnings_id = $earnings;
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
	 * @return void
	 */
	public function migrate( $order ) {
		// Extract the data.
		try {
			$this->extract( $order );
			$this->filter_subscription_products();
		} catch ( \Exception $e ) {
			throw $e;
		}

		if ( ! $this->tutor_wc_order_earnings_id ) {
			return;
		}

		foreach ( $this->tutor_wc_order_earnings_id as $earning_id => $tutor_earnings ) {
			// Update Earning data.
			QueryHelper::update(
				$this->tutor_earning_table,
				$tutor_earnings,
				array( 'earning_id' => $earning_id )
			);
		}
	}
}
