<?php
/**
 * Migration Types
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool;

/**
 * MigrationTypes class
 */
abstract class MigrationTypes {

	const LD_TO_TUTOR     = 'learndash_to_tutor';
	const LP_TO_TUTOR     = 'learnpress_to_tutor';
	const LIF_TO_TUTOR    = 'lifter_to_tutor';
	const SENSEI_TO_TUTOR = 'sensei_to_tutor';

	// sales data migration types.
	const WC_TO_NATIVE = 'woocommerce_to_native';
}
