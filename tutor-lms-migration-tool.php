<?php
/*
Plugin Name: Tutor LMS - Migration Tool
Plugin URI: https://www.themeum.com/product/tutor-lms-migration-tool/
Description: A migration toolkit that allows you to migrate data from other LMS platforms to Tutor LMS.
Author: Themeum
Version: 1.0.3
Author URI: http://themeum.com
Requires at least: 4.5
Tested up to: 5.3
License: GPLv2 or later
Text Domain: tutor-lms-migration-tool
*/
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Defining Constant
 * @since v.1.0.0
 */

//TLMT

define('TLMT_VERSION', '1.0.3');
define('TLMT_FILE', __FILE__);
define('TLMT_PATH', plugin_dir_path( TLMT_FILE ));
define('TLMT_URL', plugin_dir_url( TLMT_FILE ));
define('TLMT_BASENAME', plugin_basename( TLMT_FILE ));

if ( ! class_exists('TutorLMSMigrationTool')){
	include_once 'classes/TutorLMSMigrationTool.php';
	TutorLMSMigrationTool::instance();
}