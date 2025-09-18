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
		return array();
	}

	/**
	 * Total items count from source
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_total_items_count(): int {
		return 0;
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
