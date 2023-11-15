<?php
/**
 * Check dependency
 *
 * @package DTLMSDependency
 *
 * @since v2.0.0
 */

namespace TutorLMSMigrationTool\TLMT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage Tutor LMS Divi Modules dependency on Tutor Core
 *
 * @since v2.0.0
 */
class Dependency {

	/**
	 * Register hooks
	 *
	 * @since v2.0.0
	 */

	public function show_admin_notice() {
		?>
		<div class="notice notice-error etlms-install-notice">
				<div class="dtlms-install-notice-inner" style="display:flex; justify-content: space-between; align-items: center; padding: 10px 0">
					<div style="column-gap: 10px; display:flex; align-items: center;">
						<div class="etlms-install-notice-icon">
							<img src="<?php esc_attr_e( TLMT_URL . 'assets/img/tutor-logo.jpg' ); ?>" alt="Tutor Divi Modules Logo">
						</div>
						<div class="etlms-install-notice-content">
							<h2 style="margin-bottom: 5px">
								<i class="tutor-icon-warning-f" style="color:#ffb200;"></i> <?php esc_html_e( 'WARNING: YOU NEED TO INSTALL THE REQUIRED TUTOR LMS', 'tutor-lms-migration-tool' ); ?></h2>
							<p style="margin-bottom: 5px">
					<?php
						esc_html_e(
							'Install Tutor LMS first before migrating your courses. ',
							'tutor-lms-migration-tool'
						);
					?>
							</p>
							<p style="color: #757C8E;">
								<?php esc_html_e( 'Note: '.TLMT_PLUGIN_NAME. ' ' . TLMT_VERSION .' will be installed but you will not be able to avail any of its’ features as well specific Tutor LMS add-ons.', 'tutor-lms-migration-tool' ); ?>
							</p>
						</div>
					</div>
					<div class="etlms-install-notice-button">
						<a  class="button button-primary install-dtlms-dependency-plugin-button" data-slug="tutor" href="https://downloads.wordpress.org/plugin/tutor.zip" target="_blank"><?php esc_html_e( 'Install Tutor LMS', 'tutor-lms-migration-tool' ); ?></a>
					</div>
				</div>
			</div>
		<?php
	}

	/**
	 * Check whether Tutor core has required version installed
	 *
	 * @return bool | if has return true otherwise false
	 *
	 * @since v2.0.0
	 */
	public function is_tutor_core_has_req_verion(): bool {
		$file_path              = WP_PLUGIN_DIR . '/tutor/tutor.php';
		$plugin_data            = get_file_data(
			$file_path,
			array(
				'Version' => 'Version',
			)
		);
		$tutor_version          = $plugin_data['Version'];
		$tutor_core_req_version = TLMT_TUTOR_CORE_REQ_VERSION;
		$is_compatible          = version_compare( $tutor_version, $tutor_core_req_version, '>=' );
		return $is_compatible ? true : false;
	}

	/**
	 * Check if Tutor file is available
	 *
	 * @return boolean
	 */
	public function is_tutor_file_available(): bool {
		return file_exists( WP_PLUGIN_DIR . '/tutor/tutor.php' );
	}
}
