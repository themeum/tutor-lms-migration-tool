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
	 * Extract data from source
	 *
	 * @since 2.4.0
	 *
	 * @return mixed
	 */
	public function extract();

	/**
	 * Transform extracted data
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function transform(): array;

	/**
	 * Migrate transformed data
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	public function migrate(): bool;
}
