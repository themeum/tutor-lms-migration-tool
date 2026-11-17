<?php
/**
 * Sensei Course Reviews Migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\SenseiMigration;

use Themeum\TutorLMSMigrationTool\Interfaces\Review;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles migration of Sensei course reviews and ratings into Tutor LMS format.
 */
class Reviews implements Review {

	/**
	 * Migrate Sensei reviews to Tutor LMS course ratings.
	 *
	 * @param int $course_id Optional course post ID (0 for all).
	 * @return int Number of reviews migrated.
	 */
	public function migrate( int $course_id = 0 ): int {
		global $wpdb;

		$course_clause = $course_id > 0 ? $wpdb->prepare( ' AND c.comment_post_ID = %d', $course_id ) : '';

		// Query comments on courses that have a rating meta or are reviews.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$review_ids = $wpdb->get_col(
			"SELECT DISTINCT c.comment_ID
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} cm
				ON cm.comment_id = c.comment_ID
				AND cm.meta_key IN ('rating', '_rating', '_sensei_rating', 'sensei_rating')
			WHERE c.comment_type IN ('review', 'comment', 'sensei_review')
			AND c.user_id > 0" . $course_clause
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $review_ids ) ) {
			return 0;
		}

		$migrated = 0;
		foreach ( $review_ids as $comment_id ) {
			$cid = (int) $comment_id;

			// Extract rating value.
			$raw_rating = get_comment_meta( $cid, 'rating', true );
			if ( empty( $raw_rating ) ) {
				$raw_rating = get_comment_meta( $cid, '_rating', true );
			}
			if ( empty( $raw_rating ) ) {
				$raw_rating = get_comment_meta( $cid, '_sensei_rating', true );
			}

			$rating = is_numeric( $raw_rating ) ? max( 1, min( 5, (int) $raw_rating ) ) : 5;

			// Update comment to Tutor rating type.
			$wpdb->update(
				$wpdb->comments,
				array(
					'comment_approved' => 'approved',
					'comment_type'     => 'tutor_course_rating',
					'comment_agent'    => 'TutorLMSPlugin',
				),
				array( 'comment_ID' => $cid ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			clean_comment_cache( $cid );

			// Save tutor_rating comment meta.
			update_comment_meta( $cid, 'tutor_rating', $rating );

			++$migrated;
		}

		return $migrated;
	}
}
