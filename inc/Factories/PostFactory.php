<?php
/**
 * Post Factory
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool\Factories;

use InvalidArgumentException;
use Themeum\TutorLMSMigrationTool\ContentTypes;
use Themeum\TutorLMSMigrationTool\Interfaces\Post;
use Themeum\TutorLMSMigrationTool\LDMigration\Posts\Assignment;
use Themeum\TutorLMSMigrationTool\MigrationTypes;

/**
 * Create the post  objects based on migration type and  type
 */
abstract class PostFactory {

	/**
	 * Creates post objects based on migration and type
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_type Type of  (course, lesson, quiz, assignment).
	 * @param string $migration_type Type of migration (LD to Tutor etc).
	 *
	 * @throws InvalidArgumentException If invalid  or migration type provided.
	 *
	 * @return Post Post object for the specified type
	 */
	public static function create( $post_type, $migration_type ): Post {
		switch ( $migration_type ) {
			case MigrationTypes::LD_TO_TUTOR:
				switch ( $post_type ) {
					case ContentTypes::ASSIGNMENT:
						return new Assignment();
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
