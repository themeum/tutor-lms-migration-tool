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

defined( 'ABSPATH' ) || exit;

/**
 * Order data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Order implements MigrationTemplate {

	/**
	 * Resolve props
	 *
	 * @since 2.4.0
	 *
	 * @param int|WC_Order $order Order id object.
	 */
	public function __construct( $order ) {
		$this->order = $order;
	}

	/**
	 * Extract order data
	 *
	 * @since 2.4.0
	 *
	 * @return WC_Order
	 */
	public function extract() {
		return $this->order;
	}

	/**
	 * Transform the order data to native order
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function transform(): array {
		return array();
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
