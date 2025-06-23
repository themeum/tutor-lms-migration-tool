<?php
/**
 * PostMeta Factory
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\Interfaces\PostMeta;
use Themeum\TutorLMSMigrationTool\LDMigration\PostMeta\CourseMeta;
use Themeum\TutorLMSMigrationTool\MigrationTypes;

/**
 * Create the post meta objects based on migration type and meta type
 */
abstract class PostMetaFactory {

	/**
	 * Creates post meta objects based on migration and meta type
	 *
	 * @since 2.3.0
	 *
	 * @param string $meta_type Type of meta (course, lesson, quiz, assignment).
	 * @param string $migration_type Type of migration (LD to Tutor etc).
	 *
	 * @throws InvalidArgumentException If invalid meta or migration type provided.
	 *
	 * @return PostMeta Post meta object for the specified type
	 */
	public static function create( $meta_type, $migration_type ): PostMeta {
		switch ( $migration_type ) {
			case MigrationTypes::LD_TO_TUTOR:
				switch ( $meta_type ) {
					case ContentTypes::COURSE_META:
						return new CourseMeta();
					default:
						break;
				}
				break;
			default:
				break;
		}

		throw new InvalidArgumentException( __( 'Invalid argument passed', 'tutor-lms-migration-tool' ) );
	}
}
