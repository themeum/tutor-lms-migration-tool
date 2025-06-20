<?php
/**
 * Migration ContentTypes
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

namespace Themeum\TutorLMSMigrationTool;

/**
 * Contains constant for the available migration types.
 *
 * @since 2.3.0
 */
abstract class ContentTypes {

	const COURSE          = 'course';
	const COURSE_META     = 'course_meta';
	const COURSE_REVIEWS  = 'course_reviews';
	const COURSE_PROGRESS = 'course_progress';

	const SALES       = 'sales';
	const ORDERS      = 'orders';
	const WC_PRODUCTS = 'wc_products';

	const LESSON      = 'lesson_meta';
	const LESSON_META = 'lesson';

	const ASSIGNMENT      = 'assignment';
	const ASSIGNMENT_META = 'assignment_meta';

	const QUIZ      = 'quiz';
	const QUIZ_META = 'quiz_meta';
}
