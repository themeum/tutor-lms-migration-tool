<?php
/**
 * Initialize sales data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize sales data migration
 *
 * @since 2.4.0
 */
class InitSalesMigration {

	/**
	 * Initialize sales data migration
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		add_filter( 'tutor_tool_pages', array( $this, 'set_migration_option' ) );
	}

	/**
	 * Add migration option on the tools so that user
	 * can trigger migration process from there
	 *
	 * @since 2.4.0
	 *
	 * @param array $tool_pages Default tools pages.
	 */
	public function set_migration_option( $tool_pages ) {
		$migration_plugins = array(
			'migration_wc' => array(
				'is_active' => is_plugin_active( 'woocommerce/woocommerce.php' ),
				'callback'  => 'setup_wc_monetization',
			),
			// TODO: Other migration like PMPRO, EDD.
		);

		foreach ( $migration_plugins as $key => $plugin ) {
			if ( $plugin['is_active'] ) {
				$configuration      = call_user_func( array( $this, $plugin['callback'] ) );
				$tool_pages[ $key ] = $configuration;
			}
		}

		return $tool_pages;
	}

	/**
	 * WC to native sales data migration setup
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function setup_wc_monetization() {
		return array(
			'label'     => __( 'WooCommerce Migration', 'tutor-lms-migration-tool' ),
			'slug'      => 'migration_wc',
			'desc'      => __( 'WooCommerce Migration', 'tutor-lms-migration-tool' ),
			'template'  => 'migration_wc',
			'view_path' => TLMT_PATH . 'views/',
			'icon'      => 'tutor-icon-brand-woocommerce',
			'blocks'    => array(
				'block' => array(),
			),
		);
	}
}
