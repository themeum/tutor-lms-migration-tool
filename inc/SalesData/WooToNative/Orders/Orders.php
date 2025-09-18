<?php
/**
 * Concrete class to handle order data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Orders;

use AllowDynamicProperties;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;
use WC_Order_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Order data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Orders implements MigrationTemplate {

	/**
	 * Get items from source
	 *
	 * @since 2.4.0
	 *
	 * @param int $limit  Number of items to fetch from source.
	 * @param int $offset Number of items to skip from source.
	 *
	 * @return array
	 */
	public function get_items( int $limit = 5, int $offset = 0 ): array {
		return $this->get_orders( $limit, $offset );
	}

	/**
	 * Total items count from source
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_total_items_count(): int {
		return $this->get_total_orders_count();
	}

	/**
	 * Fetch WooCommerce orders excluding trashed ones.
	 *
	 * @since 2.4.0
	 *
	 * @param int $limit  Number of orders to fetch per page.
	 * @param int $offset Number of orders to skip.
	 *
	 * @return array Array of orders.
	 */
	public function get_orders( $limit = 10, $offset = 0 ) {
		$orders = wc_get_orders(
			array(
				'limit'   => $limit,
				'offset'  => $offset,
				'status'  => $this->get_order_statuses(),
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);

		return $orders;
	}

	/**
	 * Get total number of orders
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_total_orders_count() {
		$total_query = new WC_Order_Query(
			array(
				'status' => $this->get_order_statuses(),
				'return' => 'ids',
				'limit'  => -1,
			)
		);

		return count( $total_query->get_orders() );
	}

	/**
	 * Get order statuses
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	private function get_order_statuses() {
		$statuses = array_keys( wc_get_order_statuses() );
		$statuses = array_filter(
			$statuses,
			function ( $status ) {
				return 'wc-trash' !== $status;
			}
		);

		return $statuses;
	}

	/**
	 * Extract order data
	 *
	 * @since 2.4.0
	 *
	 * @param int|object $order Order id or object.
	 *
	 * @return MigrationTemplate
	 */
	public function extract( $order ): MigrationTemplate {
		return $this;
	}

	/**
	 * Transform the order data to native order
	 *
	 * @since 2.4.0
	 *
	 * @return MigrationTemplate
	 */
	public function transform(): MigrationTemplate {
		return $this;
	}

	/**
	 * Migrate the order, store in database
	 *
	 * @since 2.4.0
	 *
	 * @return bool true|false
	 */
	public function migrate(): bool {
		return true;
	}
}
