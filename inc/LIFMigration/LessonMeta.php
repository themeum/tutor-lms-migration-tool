<?php
/**
 * LifterLMS lesson meta migration.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.6.0
 */

namespace Themeum\TutorLMSMigrationTool\LIFMigration;

use Themeum\TutorLMSMigrationTool\Interfaces\PostMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates LifterLMS lesson settings into Tutor lesson meta.
 *
 * @since 2.6.0
 */
class LessonMeta implements PostMeta {

	/**
	 * Current lesson ID.
	 *
	 * @since 2.6.0
	 *
	 * @var int
	 */
	private $lesson_id = 0;

	/**
	 * Migrate lesson meta for a LifterLMS lesson post.
	 *
	 * @since 2.6.0
	 *
	 * @param int $post_id Lesson post ID.
	 *
	 * @return void
	 */
	public function migrate( int $post_id ) {
		if ( $post_id < 1 ) {
			return;
		}

		$this->lesson_id = $post_id;

		$this->migrate_video();
		$this->migrate_preview();
	}

	/**
	 * Map Lifter video embed URL to Tutor `_video` meta.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	private function migrate_video() {
		$video_embed = tlmt_get_lifter_video_embed( $this->lesson_id );
		if ( '' === $video_embed ) {
			return;
		}

		tlmt_migrate_video_meta( $this->lesson_id, $video_embed );
	}

	/**
	 * Map Lifter free lesson flag to Tutor preview meta.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	private function migrate_preview() {
		$free_lesson = get_post_meta( $this->lesson_id, '_llms_free_lesson', true );
		if ( 'yes' === $free_lesson ) {
			update_post_meta( $this->lesson_id, '_is_preview', 1 );
		}
	}
}
