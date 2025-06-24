<?php
/**
 * Learndash Review migration class.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\LDMigration;

use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\Interfaces\Review;

/**
 * Review migration class for learndash.
 */
class Reviews implements Review {

	/**
	 * Migrate learndash reviews to tutor.
	 *
	 * @since 2.3.0
	 *
	 * @throws \Throwable
	 *
	 * @param \WP_Comment|\WP_Post $review the review to migrate.
	 *
	 * @return void wp_json response
	 */
	public function migrate( $review ) {
		global $wpdb;

		$review_migration = array();

		try {
			$review_migration['comment_type']     = ContentTypes::TUTOR_REVIEW_TYPE;
			$review_migration['comment_agent']    = 'TutorLMSPlugin';
			$review_migration['comment_approved'] = 'approved';

			$wpdb->update( $wpdb->comments, $review_migration, array( 'comment_ID' => $review->comment_ID ) );

			$wpdb->update(
				$wpdb->commentmeta,
				array( 'meta_key' => ContentTypes::TUTOR_RATING_META_KEY ),
				array(
					'comment_ID' => $review->comment_ID,
					'meta_key'   => ContentTypes::LD_RATING_META_KEY,
				)
			);

			delete_comment_meta( $review->comment_ID, ContentTypes::LD_REVIEW_TITLE_META_KEY );

		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}
