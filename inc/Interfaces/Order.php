<?php
/**
 * Order interface.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Order
 */
interface Order {

	/**
	 * Migrate orders for a migration vendor.
	 *
	 * @param int $offset Offset for batch.
	 * @param int $limit  Limit for batch.
	 * @return array{migrated: int, remaining: int, has_more: bool}
	 */
	public function migrate( int $offset = 0, int $limit = 50 ): array;
}
