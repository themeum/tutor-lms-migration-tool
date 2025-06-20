<?php
/**
 * PostMeta Factory
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool;

/**
 * Contains constant for the available migration types.
 *
 * @since 2.3.0
 */
abstract class MigrationTypes {

	const LD_TO_TUTOR  = 'learndash_to_tutor';
	const LP_TO_TUTOR  = 'learnpress_to_tutor';
	const LIF_TO_TUTOR = 'lifter_to_tutor';
}
