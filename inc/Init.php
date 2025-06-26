<?php
/**
 * Initialize the required files
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Init class
 */
class Init {

	/**
	 * Bootstrap the plugin
	 *
	 * @since 2.3.0
	 */
	public function __construct() {
		$this->boot();
	}

	/**
	 * Bootstrap the required instances
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	private function boot() {
		new ActionHandler();
	}
}
