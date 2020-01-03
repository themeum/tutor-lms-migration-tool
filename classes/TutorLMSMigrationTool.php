<?php

if ( ! defined( 'ABSPATH' ) )
	exit;

final class TutorLMSMigrationTool{

	/**
	 * The single instance of the class.
	 *
	 * @since v.1.2.0
	 */
	protected static $_instance = null;
	protected $classes = array();

	/**
	 * @return TutorLMSMigrationTool|null
	 *
	 * Run Main class
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	function __construct() {
		$this->includes();
		$this->used_classes();
		$this->classes_initialize();
		$this->load_assets();

		add_filter('plugin_action_links_' . plugin_basename(TLMT_FILE), array( $this, 'plugin_action_links' ) );
	}

	public function includes(){
		include TLMT_PATH.'classes/LPtoTutorMigration.php';
		include TLMT_PATH.'classes/LDtoTutorMigration.php';

		include TLMT_PATH.'classes/LDtoTutorExport.php';
		
	}

	public function used_classes(){
		$this->classes[] = 'LPtoTutorMigration';
		$this->classes[] = 'LDtoTutorMigration';
	}

	/**
	 * Initialize Classes
	 * @since v.1.0.0
	 */
	public function classes_initialize(){
		$classes = $this->classes;


		if (is_array($classes) && count($classes)){
			foreach ($classes as $class){
				new $class();
			}
		}
	}

	public function load_assets(){
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
	}
	public function admin_scripts(){
		wp_enqueue_style('tlmt-admin', TLMT_URL.'assets/css/admin.css', array(), TLMT_VERSION);
		wp_enqueue_script('tlmt-admin', TLMT_URL.'assets/js/admin.js', array('jquery', 'tutor-admin'), TLMT_VERSION, true);
	}
	public function plugin_action_links($actions){
		$actions['settings'] = '<a href="admin.php?page=tutor-tools&sub_page=migration_lp">' . __('Settings', 'tutor-lms-migration-tool') . '</a>';
		return $actions;
	}


}