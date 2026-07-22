<?php
/**
 * Transform LearnDash subscribe course settings into Tutor plan data.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.5.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;
use Themeum\TutorLMSMigrationTool\LDMigration\Subscriptions\Helper;
use TutorPro\Subscription\Models\PlanModel;

defined( 'ABSPATH' ) || exit;

/**
 * PlanDataTransformer class.
 *
 * @since 2.5.0
 */
class PlanDataTransformer implements DataTransformer {

	/**
	 * Transform LD course subscription settings to Tutor plan payload.
	 *
	 * @since 2.5.0
	 *
	 * @param mixed $course_id Course ID.
	 *
	 * @return array
	 */
	public function transform( $course_id ) {
		$course_id = (int) $course_id;
		$course    = get_post( $course_id );

		if ( ! $course || ! Helper::is_ld_subscribe_course( $course_id ) ) {
			return array();
		}

		$regular_price      = (float) Helper::get_ld_course_setting( $course_id, 'course_price' );
		$recurring_value    = (int) Helper::get_ld_course_setting( $course_id, 'course_price_billing_p3' );
		$recurring_interval = Helper::map_billing_interval(
			(string) Helper::get_ld_course_setting( $course_id, 'course_price_billing_t3' )
		);
		$cycles             = (int) Helper::get_ld_course_setting( $course_id, 'course_no_of_cycles' );
		$trial_value        = (int) Helper::get_ld_course_setting( $course_id, 'course_trial_duration_p1' );
		$trial_interval_raw = (string) Helper::get_ld_course_setting( $course_id, 'course_trial_duration_t1' );
		$trial_fee          = (float) Helper::get_ld_course_setting( $course_id, 'course_trial_price' );

		if ( $recurring_value < 1 ) {
			$recurring_value = 1;
		}

		$plan_name = sprintf(
			/* translators: %s: course title */
			__( '%s Subscription', 'tutor-lms-migration-tool' ),
			$course->post_title
		);

		return array(
			'object_id'          => $course_id,
			'payment_type'       => PlanModel::PAYMENT_RECURRING,
			'plan_type'          => PlanModel::TYPE_COURSE,
			'plan_name'          => $plan_name,
			'short_description'  => '',
			'regular_price'      => $regular_price,
			'sale_price'         => null,
			'tax_collection'     => 1,
			'enrollment_fee'     => 0,
			'recurring_value'    => $recurring_value,
			'recurring_interval' => $recurring_interval,
			'recurring_limit'    => Helper::map_recurring_limit( $cycles ),
			'trial_value'         => $trial_value,
			'trial_interval'      => $trial_value > 0 ? Helper::map_billing_interval( $trial_interval_raw ) : null,
			'trial_fee'           => $trial_fee,
			'provide_certificate' => 1,
			'is_enabled'          => 1,
			'plan_order'          => 1,
		);
	}
}
