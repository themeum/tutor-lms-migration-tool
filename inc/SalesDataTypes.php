<?php
/**
 * Sales data types
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool;

/**
 * Contains constant for the available migration types.
 *
 * @since 2.3.0
 */
abstract class SalesDataTypes {

	const ORDERS        = 'orders';
	const COUPONS       = 'coupons';
	const SUBSCRIPTIONS = 'subscriptions';
	const CUSTOMERS     = 'customers';
	const EARNINGS      = 'earnings';
}
