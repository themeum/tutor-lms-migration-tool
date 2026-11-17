<?php
/**
 * Manage Sensei LMS to Tutor LMS migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SenseiToTutorMigration' ) ) {
	/**
	 * Sensei LMS to Tutor LMS Migration class
	 */
	class SenseiToTutorMigration {

		/**
		 * Constructor function
		 */
		public function __construct() {
			add_filter( 'tutor_tool_pages', array( $this, 'tutor_tool_pages' ) );
			add_action( 'wp_ajax_insert_tutor_migration_data', array( $this, 'insert_tutor_migration_data' ) );
			add_action( 'wp_ajax_sensei_migrate_all_data_to_tutor', array( $this, 'sensei_migrate_all_data_to_tutor' ) );
			add_action( 'wp_ajax_tlmt_reset_migrated_items_count', array( $this, 'tlmt_reset_migrated_items_count' ) );

			add_action( 'wp_ajax__get_sensei_live_progress_course_migrating_info', array( $this, '_get_sensei_live_progress_course_migrating_info' ) );
		}

		/**
		 * Insert migration log entry
		 *
		 * @return void
		 */
		public function insert_tutor_migration_data() {
			global $wpdb;

			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			$migration_type   = isset( $_POST['migration_type'] ) ? sanitize_text_field( wp_unslash( $_POST['migration_type'] ) ) : '';
			$migration_vendor = isset( $_POST['migration_vendor'] ) ? sanitize_text_field( wp_unslash( $_POST['migration_vendor'] ) ) : '';

			$tutor_migration_table_data = array(
				'migration_type'   => $migration_type,
				'migration_vendor' => $migration_vendor,
				'created_by'       => get_current_user_id(),
				'created_at'       => current_time( 'mysql' ),
			);

			$wpdb->insert(
				$wpdb->prefix . 'tutor_migration',
				$tutor_migration_table_data
			);
		}

		/**
		 * Register Sensei LMS Migration subpage in Tutor Tools
		 *
		 * @param array $pages Tool pages.
		 * @return array
		 */
		public function tutor_tool_pages( $pages ) {
			if ( defined( 'SENSEI_LMS_VERSION' ) || class_exists( 'Sensei_Main' ) || is_plugin_active( 'sensei-lms/sensei-lms.php' ) ) {
				$pages['migration_sensei'] = array(
					'label'     => __( 'Sensei LMS Migration', 'tutor-lms-migration-tool' ),
					'slug'      => 'migration_sensei',
					'desc'      => __( 'Sensei LMS Migration', 'tutor-lms-migration-tool' ),
					'template'  => 'migration_sensei',
					'view_path' => TLMT_PATH . 'views/',
					'icon'      => 'tutor-icon-brand-sensei',
					'blocks'    => array(
						'block' => array(),
					),
				);
			}

			return $pages;
		}

		/**
		 * Delete migrated item count option
		 *
		 * @return void
		 */
		public function tlmt_reset_migrated_items_count() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			delete_option( '_tutor_migrated_items_count' );
		}

		/**
		 * Get live progress count info
		 *
		 * @return void
		 */
		public function _get_sensei_live_progress_course_migrating_info() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			$migrated_count = (int) get_option( '_tutor_migrated_items_count', 0 );

			wp_send_json_success(
				array(
					'migrated_count' => $migrated_count,
				)
			);
		}

		/**
		 * Main AJAX router for Sensei data migration steps
		 *
		 * @return void
		 */
		public function sensei_migrate_all_data_to_tutor() {
			tutor_utils()->checking_nonce();
			Utils::check_course_access();

			if ( ! isset( $_POST['migrate_type'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid migration type.', 'tutor-lms-migration-tool' ) ) );
			}

			$migrate_type = sanitize_text_field( wp_unslash( $_POST['migrate_type'] ) );

			wp_send_json_success(
				array(
					'step'    => $migrate_type,
					'message' => __( 'Step processed.', 'tutor-lms-migration-tool' ),
				)
			);
		}
	}
}
