<?php
/**
 * Concrete class to handle coupon data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Coupons;

use AllowDynamicProperties;
use Themeum\TutorLMSMigrationTool\Interfaces\MigrationTemplate;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon data migration class
 *
 * @since 2.4.0
 */
#[AllowDynamicProperties]
class Coupons implements MigrationTemplate {

	/**
	 * Extract coupon data
	 *
	 * @since 2.4.0
	 *
	 * @param int|object $coupon Coupon id or object.
	 *
	 * @return mixed
	 */
	public function extract( $coupon ) {
		return $this->coupon;
	}

	/**
	 * Transform the coupon data to native coupon
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function transform(): array {
		return array();
	}

	/**
	 * Migrate the coupon, store in database
	 *
	 * @since 2.4.0
	 *
	 * @return bool true|false
	 */
	public function migrate(): bool {
		return true;
	}
}
