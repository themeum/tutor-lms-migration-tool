<?php
/**
 * Concrete class to handle subscription data migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Helper;
use TutorPro\Subscription\Models\PlanModel;
use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;

/**
 * Class PlanDataTransformer
 *
 * @since 2.4.0
 */
class PlanDataTransformer implements DataTransformer {
	/**
	 * Transform data from woo to native
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Subscription $subscription subscription object.
	 *
	 * @return array
	 */
	public function transform( $subscription = null ) {
		$wc_plans = Helper::get_wc_plans();

		foreach ( $wc_plans as $plan ) {
			$id        = $plan->get_id();
			$object_id = (int) tutor_utils()->product_belongs_with_course( $id )->post_id;
			$plan_type = tutor()->course_post_type === get_post_type( $object_id )
							? PlanModel::TYPE_COURSE
							: PlanModel::TYPE_BUNDLE;

			$regular_price      = (float) $plan->get_regular_price();
			$sale_price         = (float) $plan->get_sale_price();
			$signup_fee         = (float) get_post_meta( $id, '_subscription_sign_up_fee', true );
			$recurring_value    = (int) get_post_meta( $id, '_subscription_period_interval', true );
			$recurring_interval = get_post_meta( $id, '_subscription_period', true );
			$recurring_limit    = (int) get_post_meta( $id, '_subscription_length', true );

			$trial_value    = (int) get_post_meta( $id, '_subscription_trial_length', true );
			$trial_interval = get_post_meta( $id, '_subscription_trial_period', true );

			$sale_from = get_post_meta( $id, '_sale_price_dates_from', true );
			$sale_to   = get_post_meta( $id, '_sale_price_dates_to', true );

			$data[] = array(
				'object_id'          => $object_id,
				'product_id'         => $id,
				'plan_name'          => $plan->get_name(),
				'plan_type'          => $plan_type,
				'short_description'  => $plan->get_short_description(),
				'regular_price'      => $regular_price,
				'sale_price'         => $sale_price,
				'tax_collection'     => $plan->is_taxable(),
				'enrollment_fee'     => $signup_fee,
				'recurring_value'    => $recurring_value,
				'recurring_interval' => $recurring_interval,
				'recurring_limit'    => $recurring_limit,

				'trial_value'        => $trial_value,
				'trial_interval'     => $trial_interval,
				'trial_fee'          => 0,
				'sale_price_from'    => $sale_from ? wp_date( 'Y-m-d H:i:s', $sale_from ) : null,
				'sale_price_to'      => $sale_to ? wp_date( 'Y-m-d H:i:s', $sale_to ) : null,
			);
		}

		return $data;
	}
}
