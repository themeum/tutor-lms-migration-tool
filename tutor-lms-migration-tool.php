<?php
/*
Plugin Name: Tutor LMS - Migration Tool
Plugin URI: https://www.themeum.com/product/tutor-lms-migration-tool/
Description: A migration toolkit that allows you to migrate data from other LMS platforms to Tutor LMS.
Author: Themeum
Version: 2.0.0
Author URI: http://themeum.com
Requires at least: 4.5
Tested up to: 5.3
License: GPLv2 or later
Text Domain: tutor-lms-migration-tool
*/
include('classes/Dependency.php');

use TutorLMSMigrationTool\TLMT\Dependency;

if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Defining Constant
 * @since v.1.0.0
 */

define('TLMT_VERSION', '2.0.0');
define('TLMT_FILE', __FILE__);
define('TLMT_PATH', plugin_dir_path( TLMT_FILE ));
define('TLMT_URL', plugin_dir_url( TLMT_FILE ));
define('TLMT_BASENAME', plugin_basename( TLMT_FILE ));
define('TLMT_TUTOR_CORE_REQ_VERSION', '2.0.0-beta-1');

if ( ! class_exists('TutorLMSMigrationTool')){

	$dependency = new Dependency;
	if ( ! $dependency->is_tutor_core_has_req_verion() ) {
		add_action( 'admin_notices', array( $dependency, 'show_admin_notice' ) );
		return;
	}

	include_once 'classes/TutorLMSMigrationTool.php';
	TutorLMSMigrationTool::instance();
}