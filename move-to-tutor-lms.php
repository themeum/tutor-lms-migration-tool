<?php
/*
Plugin Name: Move To Tutor LMS
Plugin URI: https://www.themeum.com/product/move-to-tutor-lms/
Description: A migration toolkit that allow you to migrate others LMS data to Tutor LMS
Author: Themeum
Version: 1.0.0
Author URI: http://themeum.com
Requires at least: 4.5
Tested up to: 5.2
License: GPLv2 or later
Text Domain: move-to-tutor-lms
*/
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Defining Constant
 * @since v.1.0.0
 */

define('MTTL_VERSION', '1.0.0');
define('MTTL_FILE', __FILE__);
define('MTTL_PATH', plugin_dir_path( MTTL_FILE ));
define('MTTL_URL', plugin_dir_url( MTTL_FILE ));
define('MTTL_BASENAME', plugin_basename( MTTL_FILE ));

if ( ! class_exists('MoveToTutorLMS')){
	include_once 'classes/MoveToTutorLMS.php';
	MoveToTutorLMS::instance();
}