<?php
/**
 * Subscription Data Transformer
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Helper;
use Tutor\Helpers\DateTimeHelper;

/**
 * Class SubscriptionDataTransformer
 *
 * @since 2.4.0
 */
class SubscriptionDataTransformer implements DataTransformer {
	/**
	 * Transform WC subscription data to native subscription data.
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Subscription $subscription subscription object.
	 *
	 * @return array
	 */
	public function transform( $subscription ) {
		$wc_plan_id = Helper::get_wc_product_id_by_subscription( $subscription );

		$trial_end_date_gmt = $subscription->get_date( 'trial_end', 'gmt' );
		$is_trial_used      = ( $trial_end_date_gmt && time() > strtotime( $trial_end_date_gmt ) ) ? 1 : 0;
		$end_date_gmt       = $subscription->get_date( 'next_payment', 'gmt' );
		if ( empty( $end_date_gmt ) ) {
			$start_date_gmt = $subscription->get_date( 'start', 'gmt' );
			$interval       = $subscription->get_billing_interval();
			$period         = $subscription->get_billing_period();

			$end_date_gmt = DateTimeHelper::create( $start_date_gmt )->add( $interval, $period )->to_date_time_string();
		}

		$subscription_data = array(
			'user_id'               => $subscription->get_customer_id(),
			'plan_id'               => $wc_plan_id,                             // This will be changed in the migration step.
			'first_order_id'        => $subscription->get_parent_id(),          // This will be changed in the migration step.
			'active_order_id'       => $subscription->get_last_order( 'ids' ),  // This will be changed in the migration step.
			'status'                => Helper::get_subscription_status( $subscription ),
			'auto_renew'            => 1,
			'is_trial_enabled'      => empty( $trial_end_date_gmt ) || $is_trial_used ? 0 : 1,
			'is_trial_used'         => $is_trial_used,
			'trial_end_date_gmt'    => empty( $trial_end_date_gmt ) ? null : $trial_end_date_gmt,
			'start_date_gmt'        => $subscription->get_date( 'start', 'gmt' ),
			'end_date_gmt'          => $end_date_gmt,
			'next_payment_date_gmt' => $end_date_gmt,
			'created_at_gmt'        => $subscription->get_date_created() ? $subscription->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
			'updated_at_gmt'        => $subscription->get_date_modified() ? $subscription->get_date_modified()->date( 'Y-m-d H:i:s' ) : null,
		);

		return $subscription_data;
	}
}
