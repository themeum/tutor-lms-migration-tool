<?php
/**
 * Transform Lifter access plan into Tutor subscription plan data.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;
use Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Helper;
use TutorPro\Subscription\Models\PlanModel;

defined( 'ABSPATH' ) || exit;

/**
 * PlanDataTransformer class.
 *
 * @since 2.6.0
 */
class PlanDataTransformer implements DataTransformer {

	/**
	 * Transform a Lifter access plan into Tutor plan payload.
	 *
	 * @since 2.6.0
	 *
	 * @param mixed $plan_id Lifter access plan ID.
	 *
	 * @return array
	 */
	public function transform( $plan_id ) {
		$plan_id = (int) $plan_id;
		$plan    = get_post( $plan_id );

		if ( ! $plan || 'llms_access_plan' !== $plan->post_type ) {
			return array();
		}

		$frequency = (int) get_post_meta( $plan_id, '_llms_frequency', true );
		if ( $frequency < 1 ) {
			return array();
		}

		$course_id = (int) get_post_meta( $plan_id, '_llms_product_id', true );
		if ( $course_id < 1 ) {
			return array();
		}

		$regular_price = (float) get_post_meta( $plan_id, '_llms_price', true );
		$sale_raw      = get_post_meta( $plan_id, '_llms_sale_price', true );
		$sale_price    = ( '' !== $sale_raw && null !== $sale_raw && (float) $sale_raw > 0 )
			? (float) $sale_raw
			: null;

		$period = (string) get_post_meta( $plan_id, '_llms_period', true );
		$length = (int) get_post_meta( $plan_id, '_llms_length', true );

		$plan_name = $plan->post_title
			? $plan->post_title
			: sprintf(
				/* translators: %d: access plan ID */
				__( 'Lifter Plan #%d', 'tutor-lms-migration-tool' ),
				$plan_id
			);

		$trial_length = (int) get_post_meta( $plan_id, '_llms_trial_length', true );
		$trial_period = (string) get_post_meta( $plan_id, '_llms_trial_period', true );

		return array(
			'object_id'          => $course_id,
			'payment_type'       => PlanModel::PAYMENT_RECURRING,
			'plan_type'          => PlanModel::TYPE_COURSE,
			'plan_name'          => $plan_name,
			'short_description'  => '',
			'regular_price'      => $regular_price,
			'sale_price'         => $sale_price,
			'tax_collection'     => 1,
			'enrollment_fee'     => 0,
			'recurring_value'    => max( 1, $frequency ),
			'recurring_interval' => Helper::map_billing_interval( $period ),
			'recurring_limit'    => Helper::map_recurring_limit( $length ),
			'trial_value'        => $trial_length > 0 ? $trial_length : 0,
			'trial_interval'     => $trial_length > 0 ? Helper::map_billing_interval( $trial_period ) : null,
			'trial_fee'          => 0,
			'provide_certificate' => 1,
			'is_enabled'          => 1,
			'plan_order'          => 1,
			'llms_plan_id'        => $plan_id,
		);
	}
}
