<?php
/**
 * MigrationTemplate interface for migration classes
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\Interfaces;

defined( 'ABSPATH' ) || exit;

interface MigrationTemplate {

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
	public function get_items( int $limit = 5, int $offset = 0 ): array;

	/**
	 * Total items count from source
	 *
	 * @since 2.4.0
	 *
	 * @return int
	 */
	public function get_total_items_count(): int;

	/**
	 * Extract data from source
	 *
	 * @since 2.4.0
	 *
	 * @param object $data Data that we want to migrate. Data property may
	 * vary based on the source & extraction logics.
	 *
	 * @return MigrationTemplate
	 */
	public function extract( $data ): MigrationTemplate;

	/**
	 * Transform extracted data
	 *
	 * @since 2.4.0
	 *
	 * @return MigrationTemplate
	 */
	public function transform(): MigrationTemplate;

	/**
	 * Migrate transformed data
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	public function migrate(): bool;

	/**
	 * Get item id
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $item Item that we want to get id from. Item param may
	 * vary based on the source & extraction logics.
	 *
	 * @return int
	 */
	public function get_item_id( $item ): int;

}
