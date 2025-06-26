<?php
/**
 * Learndash Orders to EDD Orders migration class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Order;

use Themeum\TutorLMSMigrationTool\Interfaces\Order;

class EDDOrder implements Order {

	public function migrate( $order, $course_id ) {
	}

	public function remove_orders() {
		//Code..
	}
}
