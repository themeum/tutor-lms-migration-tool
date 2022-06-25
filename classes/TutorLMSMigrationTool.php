<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TutorLMSMigrationTool {

	/**
	 * The single instance of the class.
	 *
	 * @since v.1.2.0
	 */
	protected static $_instance = null;
	protected $classes          = array();

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
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->load_assets();
		add_filter( 'plugin_action_links_' . plugin_basename( TLMT_FILE ), array( $this, 'plugin_action_links' ) );

		if ( $this->check_installed() ) {
			$this->includes();
			$this->used_classes();
			$this->classes_initialize();
		} else {
			add_action( 'wp_ajax_install_tutor_plugin', array( $this, 'install_tutor_plugin' ) );
			add_action( 'admin_action_activate_tutor_free', array( $this, 'activate_tutor_free' ) );
		}
		add_action( 'admin_notices', array( $this, 'check_if_ld_lp_is_activated' ) );
	}


	public function check_if_ld_lp_is_activated() {

		if ( defined( 'LEARNPRESS_VERSION' ) && defined( 'LEARNDASH_VERSION' ) ) {

			$class   = 'notice notice-error';
			$message = __( 'For the migration to work properly, please ensure only the LMS plugin you want to migrate from and Tutor LMS is active. Deactivate all other LMS plugins.', 'sample-text-domain' );

			if ( isset( $_GET['page'] ) && 'tutor-tools' === $_GET['page'] ) {
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			}
		}
	}

	public function check_installed() {
		$default     = true;
		$source_file = WP_PLUGIN_DIR . '/tutor/tutor.php';
		if ( file_exists( $source_file ) && ! is_plugin_active( 'tutor/tutor.php' ) ) {
			$default = false;
			add_action( 'admin_notices', array( $this, 'free_plugin_installed_but_inactive_notice' ) );
		} elseif ( ! file_exists( $source_file ) ) {
			$default = false;
			add_action( 'admin_notices', array( $this, 'free_plugin_not_installed' ) );
		}
		return $default;
	}


	public function free_plugin_installed_but_inactive_notice() {
		?>
		<div class="notice notice-error tutor-install-notice">
			<div class="tutor-install-notice-inner">
				<div class="tutor-install-notice-icon">
					<img src="<?php echo TLMT_URL . 'assets/img/tutor-logo.jpg'; ?>" alt="<?php _e( 'Tutor Logo', 'tutor-lms-migration-tool' ); ?>">
				</div>
				<div class="tutor-install-notice-content">
					<h2><?php _e( 'Thanks for using Tutor LMS - Migration Tool', 'tutor-lms-migration-tool' ); ?></h2>
					<p><?php echo sprintf( __( 'You must have <a href="%s" target="_blank">Tutor</a> core version installed and activated on this website in order to use Tutor LMS - Migration Tool.', 'tutor-lms-migration-tool' ), esc_url( 'https://wordpress.org/plugins/tutor/' ) ); ?></p>
					<a href="https://www.themeum.com/product/tutor-lms/" target="_blank"><?php _e( 'Learn more about Tutor', 'tutor-lms-migration-tool' ); ?></a>
				</div>
				<div class="tutor-install-notice-button">
					<a  class="button button-primary" href="<?php echo add_query_arg( array( 'action' => 'activate_tutor_free' ), admin_url() ); ?>"><?php _e( 'Activate Tutor', 'tutor-lms-migration-tool' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}


	public function free_plugin_not_installed() {
		?>
		<div class="notice notice-error tutor-install-notice">
			<div class="tutor-install-notice-inner">
				<div class="tutor-install-notice-icon">
					<img src="<?php echo TLMT_URL . 'assets/img/tutor-logo.jpg'; ?>" alt="<?php _e( 'Tutor Logo', 'tutor-lms-migration-tool' ); ?>">
				</div>
				<div class="tutor-install-notice-content">
					<h2><?php _e( 'Thanks for using Tutor LMS - Migration Tool', 'tutor-lms-migration-tool' ); ?></h2>
					<p><?php echo sprintf( __( 'You must have <a href="%s" target="_blank">Tutor</a> core version installed and activated on this website in order to use Tutor LMS - Migration Tool.', 'tutor-lms-migration-tool' ), esc_url( 'https://wordpress.org/plugins/tutor/' ) ); ?></p>
					<a href="https://www.themeum.com/product/tutor-lms/" target="_blank"><?php _e( 'Learn more about Tutor', 'tutor-lms-migration-tool' ); ?></a>
				</div>
				<div class="tutor-install-notice-button">
					<a class="install-tutor-button button button-primary" data-slug="tutor" href="<?php echo add_query_arg( array( 'action' => 'install_tutor_plugin' ), admin_url() ); ?>"><?php _e( 'Install Tutor', 'tutor-lms-migration-tool' ); ?></a>
				</div>
			</div>
			<div id="tutor_install_msg"></div>
		</div>
		<?php
	}

	public function activate_tutor_free() {
		activate_plugin( 'tutor/tutor.php' );
	}


	public function install_tutor_plugin() {
		include ABSPATH . 'wp-admin/includes/plugin-install.php';
		include ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			include ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}
		if ( ! class_exists( 'Plugin_Installer_Skin' ) ) {
			include ABSPATH . 'wp-admin/includes/class-plugin-installer-skin.php';
		}

		$plugin = 'tutor';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			wp_die( $api );
		}

		$title = sprintf( __( 'Installing Plugin: %s' ), $api->name . ' ' . $api->version );
		$nonce = 'install-plugin_' . $plugin;
		$url   = 'update.php?action=install-plugin&plugin=' . urlencode( $plugin );

		$upgrader = new \Plugin_Upgrader( new \Plugin_Installer_Skin( compact( 'title', 'url', 'nonce', 'plugin', 'api' ) ) );
		$upgrader->install( $api->download_link );
		die();
	}


	public function includes() {
		include TLMT_PATH . 'classes/LPtoTutorMigration.php';
		include TLMT_PATH . 'classes/LDtoTutorMigration.php';
		include TLMT_PATH . 'classes/LDtoTutorExport.php';
		include TLMT_PATH . 'classes/Utils.php';
	}

	public function used_classes() {
		$this->classes[] = 'LPtoTutorMigration';
		$this->classes[] = 'LDtoTutorMigration';
	}

	/**
	 * Initialize Classes
	 *
	 * @since v.1.0.0
	 */
	public function classes_initialize() {
		$classes = $this->classes;

		if ( is_array( $classes ) && count( $classes ) ) {
			foreach ( $classes as $class ) {
				new $class();
			}
		}
	}

	public function load_assets() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}
	public function admin_scripts() {
		wp_enqueue_style( 'tlmt-admin', TLMT_URL . 'assets/css/admin.css', array(), TLMT_VERSION );
		if ( function_exists( 'tutils' ) ) {
			wp_enqueue_script( 'tlmt-admin', TLMT_URL . 'assets/js/admin.js', array( 'jquery', 'tutor-admin' ), TLMT_VERSION, true );
		} else {
			wp_enqueue_script( 'tlmt-admin', TLMT_URL . 'assets/js/admin.js', array( 'jquery' ), TLMT_VERSION, true );
		}
	}
	public function plugin_action_links( $actions ) {
		if ( defined( 'LP_PLUGIN_FILE' ) ) {
			$actions['settings'] = '<a href="admin.php?page=tutor-tools&sub_page=migration_lp">' . __( 'Settings', 'tutor-lms-migration-tool' ) . '</a>';
		} else {
			if ( defined( 'LEARNDASH_VERSION' ) ) {
				$actions['settings'] = '<a href="admin.php?page=tutor-tools&sub_page=migration_ld">' . __( 'Settings', 'tutor-lms-migration-tool' ) . '</a>';
			} else {
				$actions['settings'] = '<a href="admin.php?page=tutor-tools&sub_page=tutor_pages">' . __( 'Settings', 'tutor-lms-migration-tool' ) . '</a>';
			}
		}
		return $actions;
	}

}
