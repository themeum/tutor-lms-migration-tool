<?php

use Tutor\Helpers\QueryHelper;

class Utils {

	public function fetch_history( $vendor ) {
		global $wpdb;
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT  * FROM {$wpdb->prefix}tutor_migration
                WHERE `migration_vendor` = %s
                ORDER BY ID DESC
                LIMIT %d, %d",
				$vendor,
				0,
				20
			)
		);
		return $result;
	}
	/**
	 * LearnDash functions.
	 */
	public function ld_course_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'sfwd-courses' AND post_status = 'publish';" );
	}


	public function ld_orders_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'sfwd-transactions' AND post_status = 'publish';" );
	}

	/**
	 * LearnPress functions.
	 */
	public function lp_course_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_course' AND post_status = 'publish';" );
	}

	public function lp_orders_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_order';" );
	}

	public function lp_reviews_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(comments.comment_ID) FROM {$wpdb->comments} comments INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = comments.comment_ID AND cm.meta_key = '_lpr_rating' WHERE comments.comment_type = 'review';" );
	}

	/**
	 * Lifter lms functions .
	 *
	 * @return void
	 */
	public function lfter_course_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'course' AND post_status = 'publish';" );
	}
	public function lifter_orders_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'llms_order';" );
	}
	public function lifter_reviews_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='llms_review';" );
	}

	/**
	 * Check if user has access to tutor courses.
	 *
	 * @return void
	 */
	public static function check_course_access() {
		if ( ! current_user_can( 'publish_tutor_courses' ) ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => tutor_utils()->error_message(),
				)
			);
		}
	}

	/**
	 * Generate progress message
	 *
	 * @since 2.4.0
	 *
	 * @param array $options_value Options value.
	 *
	 * @return string
	 */
	private function generate_progress_message( array $options_value ): string {
		$message_parts = array();

		foreach ( $options_value['requirements'] as $key => $requirement ) {
			if ( isset( $requirement['succeed'] ) && is_array( $requirement['succeed'] ) ) {
				$succeed_count = count( $requirement['succeed'] );

				$capitalized_key = ucfirst( strtolower( $key ) );

				if ( $succeed_count > 0 ) {
					$message_parts[] = "{$capitalized_key} ({$succeed_count})";
				}
			}
		}

		return implode( ', ', $message_parts );
	}

	/**
	 * Get WC migration history
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_wc_migration_history(): array {
		global $wpdb;
		$data = array();

		$fetch = QueryHelper::get_all(
			$wpdb->options,
			array(
				'option_name LIKE %s AND option_value NOT LIKE %s' => array(
					'RAW',
					array(
						'tutor_migration_%',
						'%"job_progress";i:0%',
					),
				),
			),
			'option_id',
			10
		);

		if ( ! $fetch ) {
			return $data;
		}

		foreach ( $fetch as $item ) {
			if ( ! isset( $item->option_name ) || ! isset( $item->option_value ) ) {
				continue;
			}

			$options_value = json_decode( $item->option_value, true );

			if ( ! is_array( $options_value ) ) {
				continue;
			}

			$title = $this->generate_progress_message( $options_value );

			$converted_item = array(
				'status'     => $options_value['status'] ?? '',
				'id'         => (int) ( $item->option_id ?? 0 ),
				'started_at' => ! empty( $options_value['started_at'] ) ? tutor_i18n_get_formated_date( $options_value['started_at'] ) : '',
				'title'      => $title,
			);

			$data[] = $converted_item;
		}

		return $data;
	}
}
