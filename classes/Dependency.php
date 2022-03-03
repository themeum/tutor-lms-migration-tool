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
			<div class="notice notice-error">
				Notice Error!
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
