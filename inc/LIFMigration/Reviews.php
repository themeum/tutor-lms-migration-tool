<?php
/**
 * LifterLMS review migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration;

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\Interfaces\Review;

defined( 'ABSPATH' ) || exit;

/**
 * Creates Tutor course-rating comments from Lifter `llms_review` posts.
 *
 * Lifter stores reviews as posts (`post_parent` = course ID). Tutor stores
 * ratings as comments of type `tutor_course_rating`. Course IDs are preserved
 * when Lifter courses are converted in place.
 *
 * @since 2.6.0
 */
class Reviews implements Review {

	/**
	 * Post meta storing the created Tutor comment ID (success marker).
	 *
	 * Using a dedicated key so reviews wrongly marked by the old
	 * comments-UPDATE-by-post-ID path are picked up again.
	 *
	 * @since 2.6.0
	 *
	 * @var string
	 */
	const MIGRATED_META = '_tlmt_tutor_review_id';

	/**
	 * Default star rating when Lifter has none (core Lifter is text-only).
	 *
	 * @since 2.6.0
	 *
	 * @var int
	 */
	const DEFAULT_RATING = 5;

	/**
	 * Migrate a Lifter review post to a Tutor course rating comment.
	 *
	 * @since 2.6.0
	 *
	 * @throws \InvalidArgumentException When $review is not a WP_Post.
	 * @throws \RuntimeException         When insert fails or course is invalid.
	 *
	 * @param \WP_Comment|\WP_Post $review Lifter `llms_review` post.
	 *
	 * @return void
	 */
	public function migrate( $review ) {
		global $wpdb;

		if ( ! $review instanceof \WP_Post ) {
			throw new \InvalidArgumentException(
				__( 'Lifter review migration expects a WP_Post.', 'tutor-lms-migration-tool' )
			);
		}

		if ( 'llms_review' !== $review->post_type ) {
			throw new \InvalidArgumentException(
				__( 'Invalid Lifter review post type.', 'tutor-lms-migration-tool' )
			);
		}

		$existing = get_post_meta( $review->ID, self::MIGRATED_META, true );
		if ( '' !== $existing && false !== $existing ) {
			return;
		}

		$course_id = (int) $review->post_parent;
		if ( $course_id < 1 || ! $this->is_tutor_course( $course_id ) ) {
			update_post_meta( $review->ID, self::MIGRATED_META, 0 );
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: Lifter review post ID */
					__( 'Lifter review #%d skipped: parent is not a Tutor course.', 'tutor-lms-migration-tool' ),
					$review->ID
				)
			);
		}

		$user_id = (int) $review->post_author;
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

		$author_name = '';
		if ( $user ) {
			$author_name = $user->display_name ? $user->display_name : $user->user_login;
		}

		$comment_content = $this->build_comment_content( $review );
		$rating          = $this->resolve_rating( $review );

		$comment_data = array(
			'comment_post_ID'  => $course_id,
			'comment_approved' => 'publish' === $review->post_status ? 'approved' : 'hold',
			'comment_type'     => ContentTypes::TUTOR_REVIEW_TYPE,
			'comment_date'     => $review->post_date,
			'comment_date_gmt' => $review->post_date_gmt ? $review->post_date_gmt : get_gmt_from_date( $review->post_date ),
			'comment_content'  => $comment_content,
			'user_id'          => $user_id,
			'comment_author'   => $author_name,
			'comment_agent'    => 'TutorLMSPlugin',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $wpdb->comments, $comment_data );
		if ( ! $inserted ) {
			update_post_meta( $review->ID, self::MIGRATED_META, 0 );
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: Lifter review post ID */
					__( 'Could not create Tutor rating for Lifter review #%d.', 'tutor-lms-migration-tool' ),
					$review->ID
				)
			);
		}

		$comment_id = (int) $wpdb->insert_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->commentmeta,
			array(
				'comment_id' => $comment_id,
				'meta_key'   => ContentTypes::TUTOR_RATING_META_KEY,
				'meta_value' => (string) $rating,
			)
		);

		update_post_meta( $review->ID, self::MIGRATED_META, $comment_id );
		clean_comment_cache( $comment_id );
	}

	/**
	 * Whether the post is a Tutor course.
	 *
	 * @since 2.6.0
	 *
	 * @param int $course_id Course post ID.
	 *
	 * @return bool
	 */
	private function is_tutor_course( int $course_id ): bool {
		$post = get_post( $course_id );
		if ( ! $post ) {
			return false;
		}

		$tutor_type = function_exists( 'tutor' ) ? tutor()->course_post_type : 'courses';

		return $tutor_type === $post->post_type;
	}

	/**
	 * Build Tutor comment content from Lifter title + body.
	 *
	 * @since 2.6.0
	 *
	 * @param \WP_Post $review Lifter review post.
	 *
	 * @return string
	 */
	private function build_comment_content( \WP_Post $review ): string {
		$title   = trim( wp_strip_all_tags( (string) $review->post_title ) );
		$content = trim( (string) $review->post_content );

		if ( '' !== $title && '' !== $content ) {
			return $title . "\n\n" . $content;
		}

		if ( '' !== $content ) {
			return $content;
		}

		return $title;
	}

	/**
	 * Resolve a 1–5 star rating for Tutor.
	 *
	 * Core LifterLMS reviews are text-only. Optional `_llms_rating` / `_lif_rating`
	 * meta is honored when present (addons); otherwise defaults to 5.
	 *
	 * @since 2.6.0
	 *
	 * @param \WP_Post $review Lifter review post.
	 *
	 * @return int
	 */
	private function resolve_rating( \WP_Post $review ): int {
		$raw = get_post_meta( $review->ID, '_llms_rating', true );
		if ( '' === $raw || false === $raw ) {
			$raw = get_post_meta( $review->ID, '_lif_rating', true );
		}

		$rating = is_numeric( $raw ) ? (int) $raw : self::DEFAULT_RATING;
		$rating = max( 1, min( 5, $rating ) );

		/**
		 * Filter the Tutor star rating used when migrating a Lifter review.
		 *
		 * @since 2.6.0
		 *
		 * @param int      $rating Resolved 1–5 rating.
		 * @param \WP_Post $review Lifter review post.
		 */
		return (int) apply_filters( 'tlmt_lif_review_default_rating', $rating, $review );
	}
}
