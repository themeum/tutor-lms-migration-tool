<?php
/**
 * Orders Interface.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */
namespace Themeum\TutorLMSMigrationTool\Interfaces;

/**
 * Orders interface for order migration class.
 */
interface Order {

	/**
	 * Migrate orders from learndash to tutor.
	 *
	 * @since 2.3.0
	 *
	 * @throws \Throwable
	 *
	 * @param \WP_Post|null $order the order post to migrate.
	 * @param int           $course_id the course id of the order.
	 *
	 * @return void wp_json response
	 */
	public function migrate( $order, $course_id );

	/**
	 * Order cleanup function after migration.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function remove_orders();
}
