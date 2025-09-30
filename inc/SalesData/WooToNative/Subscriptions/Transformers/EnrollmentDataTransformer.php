<?php
/**
 * Subscription Enrollment Data Transformer
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Transformers;

use Themeum\TutorLMSMigrationTool\Interfaces\DataTransformer;
use Themeum\TutorLMSMigrationTool\SalesData\WooToNative\Subscriptions\Helper;
use Tutor\Helpers\QueryHelper;

/**
 * Class EnrollmentDataTransformer
 *
 * @since 2.4.0
 */
class EnrollmentDataTransformer implements DataTransformer {
	/**
	 * Transform WC enrollment data to native enrollment data
	 *
	 * @since 2.4.0
	 *
	 * @param WC_Subscription $subscription subscription object.
	 *
	 * @return array
	 */
	public function transform( $subscription ) {
		$product_id = Helper::get_wc_product_id_by_subscription( $subscription );

		$primary_table  = 'postmeta pm';
		$joining_tables = array(
			array(
				'type'  => 'INNER',
				'table' => 'posts p',
				'on'    => 'p.ID = pm.post_id',
			),
		);

		$enrollment_data = QueryHelper::get_joined_data(
			$primary_table,
			$joining_tables,
			array( 'pm.*' ),
			array(
				'meta_key'   => '_tutor_enrolled_by_product_id',
				'meta_value' => $product_id,
			),
			array(),
			'meta_id'
		);

		$enrollment_data = $enrollment_data['total_count'] > 0 ? $enrollment_data['results'] : array();

		return $enrollment_data;
	}
}
