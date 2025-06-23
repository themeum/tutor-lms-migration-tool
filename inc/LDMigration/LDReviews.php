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

use Reviews;
use Themeum\TutorLMSMigrationTool\ContentTypes;

/**
 * Review migration class for learndash.
 */
class LDReviews implements Reviews {

	/**
	 * Migrate learndash reviews to tutor.
	 *
	 * @since 2.3.0
	 *
	 * @return void wp_json response
	 */
	public function migrate_reviews() {
		global $wpdb;
		$ld_reviews = get_comments( array( 'type' => ContentTypes::LD_REVIEW_TYPE ) );
		$item_idx   = (int) get_option( '_tutor_migrated_items_count' );

		if ( count( $ld_reviews ) ) {
			foreach ( $ld_reviews as $review ) {
				++$item_idx;
				update_option( '_tutor_migrated_items_count', $item_idx );

				$review_migration                     = array();
				$review_migration['comment_type']     = ContentTypes::TUTOR_REVIEW_TYPE;
				$review_migration['comment_agent']    = 'TutorLMSPlugin';
				$review_migration['comment_approved'] = 'approved';

				$result = $wpdb->update( $wpdb->comments, $review_migration, array( 'comment_ID' => $review->comment_ID ) );

				if ( ! $result ) {
					wp_send_json_error();
				}

				$wpdb->update(
					$wpdb->commentmeta,
					array( 'meta_key' => ContentTypes::TUTOR_RATING_META_KEY ),
					array(
						'comment_ID' => $review->comment_ID,
						'meta_key'   => ContentTypes::LD_RATING_META_KEY,
					)
				);

				delete_comment_meta( $review->comment_ID, ContentTypes::LD_REVIEW_TITLE_META_KEY );
			}
		}

		wp_send_json_success();
	}
}
