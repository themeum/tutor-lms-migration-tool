<?php

if ( ! defined( 'ABSPATH' ) )
	exit;

final class MoveToTutorLMS{

	/**
	 * The single instance of the class.
	 *
	 * @since v.1.2.0
	 */
	protected static $_instance = null;
	protected $classes = array();

	/**
	 * @return MoveToTutorLMS|null
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
	}

	public function includes(){
		include MTTL_PATH.'classes/LPtoTutorMigration.php';
	}

	public function used_classes(){
		$this->classes[] = 'LPtoTutorMigration';
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
		wp_enqueue_style('mttl-admin', MTTL_URL.'assets/css/admin.css', array(), MTTL_VERSION);
	}


}