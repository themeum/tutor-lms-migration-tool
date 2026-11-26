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
	 * Default batch size for review migration.
	 *
	 * @since 2.3.0
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Migrate Sensei reviews to Tutor LMS course ratings.
	 *
	 * @since 2.3.0
	 *
	 * @param int|\WP_Comment|\WP_Post $review  Optional course post ID or 0 for all.
	 * @param int                      $offset  Pagination offset. Default 0.
	 * @param int                      $limit   Max reviews to process. Default BATCH_SIZE.
	 * @return int Number of reviews migrated.
	 */
	public function migrate( $review = 0, int $offset = 0, int $limit = self::BATCH_SIZE ) {
		global $wpdb;

		$course_id = is_numeric( $review ) ? (int) $review : 0;
		if ( is_object( $review ) && isset( $review->comment_post_ID ) ) {
			$course_id = (int) $review->comment_post_ID;
		}

		$course_clause = $course_id > 0 ? $wpdb->prepare( ' AND c.comment_post_ID = %d', $course_id ) : '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$review_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT c.comment_ID
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm
					ON cm.comment_id = c.comment_ID
					AND cm.meta_key IN ('rating', '_rating', '_sensei_rating', 'sensei_rating')
				INNER JOIN {$wpdb->posts} p
					ON p.ID = c.comment_post_ID
					AND p.post_type IN ('course', 'courses')
				WHERE c.comment_type IN ('review', 'comment', 'sensei_review')
				AND c.user_id > 0" . $course_clause . '
				ORDER BY c.comment_ID ASC
				LIMIT %d, %d',
				$offset,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $review_ids ) ) {
			return 0;
		}

		$migrated = 0;
		foreach ( $review_ids as $comment_id ) {
			$cid = (int) $comment_id;

			$raw_rating = get_comment_meta( $cid, 'rating', true );
			if ( empty( $raw_rating ) ) {
				$raw_rating = get_comment_meta( $cid, '_rating', true );
			}
			if ( empty( $raw_rating ) ) {
				$raw_rating = get_comment_meta( $cid, '_sensei_rating', true );
			}
			if ( empty( $raw_rating ) ) {
				$raw_rating = get_comment_meta( $cid, 'sensei_rating', true );
			}

			$rating = is_numeric( $raw_rating ) ? max( 1, min( 5, (int) $raw_rating ) ) : 5;

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
			update_comment_meta( $cid, 'tutor_rating', $rating );

			++$migrated;
		}

		return $migrated;
	}

	/**
	 * Get total unmigrated Sensei review count.
	 *
	 * @since 2.3.0
	 *
	 * @param int $course_id Optional course ID filter. Default 0 (all).
	 * @return int
	 */
	public function get_total_count( int $course_id = 0 ): int {
		global $wpdb;

		$course_clause = $course_id > 0 ? $wpdb->prepare( ' AND c.comment_post_ID = %d', $course_id ) : '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT c.comment_ID)
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} cm
				ON cm.comment_id = c.comment_ID
				AND cm.meta_key IN ('rating', '_rating', '_sensei_rating', 'sensei_rating')
			INNER JOIN {$wpdb->posts} p
				ON p.ID = c.comment_post_ID
				AND p.post_type IN ('course', 'courses')
			WHERE c.comment_type IN ('review', 'comment', 'sensei_review')
			AND c.user_id > 0" . $course_clause
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}
}
