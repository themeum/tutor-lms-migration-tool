<?php
/**
 * Concrete class to handle subscription data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions;

use AllowDynamicProperties;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Subscriptions implements MigrationTemplate {

	/**
	 * Extract subscription data
	 *
	 * @since 2.4.0
	 *
	 * @param int|object $subscription Subscription id or object.
	 *
	 * @return mixed
	 */
	public function extract( $subscription ) {
		return $this->subscription;
	}

	/**
	 * Transform the subscription data to native subscription
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function transform(): array {
		return array();
	}

	/**
	 * Migrate the subscription, store in database
	 *
	 * @since 2.4.0
	 *
	 * @return bool true|false
	 */
	public function migrate(): bool {
		return true;
	}
}
