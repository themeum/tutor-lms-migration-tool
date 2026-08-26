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

		if ( function_exists( 'tutor_utils' ) && tutor_utils()->is_monetize_by_tutor() ) {
			$statuses  = \Themeum\TutorLMSMigrationTool\LIFMigration\Orders\StatusMapper::migratable_single_statuses();
			$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} ot
						ON ot.post_id = p.ID AND ot.meta_key = '_llms_order_type' AND ot.meta_value = 'single'
					WHERE p.post_type = 'llms_order'
						AND p.post_status IN ({$status_in})",
					$statuses
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			return $count;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'llms_order';" );
	}

	/**
	 * Count Lifter recurring course orders eligible for subscription migration.
	 *
	 * @since 2.6.0
	 *
	 * @return int
	 */
	public function lifter_subscriptions_count() {
		if ( ! class_exists( '\Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Helper' )
			|| ! \Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Helper::is_subscription_migration_available() ) {
			return 0;
		}

		return count( \Themeum\TutorLMSMigrationTool\LIFMigration\Subscriptions\Helper::get_recurring_order_ids() );
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
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'publish_tutor_courses' ) ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => tutor_utils()->error_message(),
				)
			);
		}
	}

	/**
	 * LearnPress teacher role slug.
	 *
	 * @return string
	 */
	public static function get_lp_teacher_role() {
		return defined( 'LP_TEACHER_ROLE' ) ? LP_TEACHER_ROLE : 'lp_teacher';
	}

	/**
	 * Convert a LearnPress teacher into an approved Tutor instructor.
	 *
	 * @param int|\WP_User $user User ID or WP_User.
	 * @return bool True when conversion ran.
	 */
	public static function convert_lp_teacher_to_tutor_instructor( $user ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			$user = new \WP_User( (int) $user );
		}

		if ( ! $user->exists() ) {
			return false;
		}

		$lp_teacher_role = self::get_lp_teacher_role();
		if ( ! in_array( $lp_teacher_role, (array) $user->roles, true ) ) {
			return false;
		}

		$user->remove_role( $lp_teacher_role );

		if ( function_exists( 'tutor_utils' ) ) {
			tutor_utils()->add_instructor_role( $user->ID );
			return true;
		}

		$user->add_role( 'tutor_instructor' );
		update_user_meta( $user->ID, '_is_tutor_instructor', time() );
		update_user_meta( $user->ID, '_tutor_instructor_status', 'approved' );
		update_user_meta( $user->ID, '_tutor_instructor_approved', time() );

		return true;
	}

	/**
	 * Convert all LearnPress teachers to Tutor instructors.
	 *
	 * @return int Number of users converted.
	 */
	public static function convert_all_lp_teachers_to_tutor_instructors() {
		$users = get_users(
			array(
				'role'   => self::get_lp_teacher_role(),
				'fields' => 'ID',
			)
		);

		$converted = 0;
		foreach ( $users as $user_id ) {
			if ( self::convert_lp_teacher_to_tutor_instructor( $user_id ) ) {
				++$converted;
			}
		}

		return $converted;
	}
}
