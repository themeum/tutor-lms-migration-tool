<?php
/**
 * Concrete class to handle customer data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Customers;

use AllowDynamicProperties;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;

defined( 'ABSPATH' ) || exit;

/**
 * Customer data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Customers implements MigrationTemplate {

	/**
	 * Extract customer data
	 *
	 * @since 2.4.0
	 *
	 * @param int|object $customer Customer id or object.
	 *
	 * @return mixed
	 */
	public function extract( $customer ) {
		return $this->customer;
	}

	/**
	 * Transform the customer data to native customer
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function transform(): array {
		return array();
	}

	/**
	 * Migrate the customer, store in database
	 *
	 * @since 2.4.0
	 *
	 * @return bool true|false
	 */
	public function migrate(): bool {
		return true;
	}
}
