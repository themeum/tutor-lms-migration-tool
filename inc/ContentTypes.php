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

	const COURSE            = 'courses';
	const COURSE_META       = 'course_meta';
	const COURSE_TAXONOMIES = 'course_taxonomies';
	const COURSE_REVIEWS    = 'reviews';
	const COURSE_PROGRESS   = 'course_progress';
	const ENROLLMENTS       = 'enrollments';

	const SALES         = 'sales';
	const ORDERS        = 'orders';
	const SUBSCRIPTIONS = 'subscriptions';
	const WC_PRODUCTS   = 'wc_products';

	const LESSON      = 'lesson';
	const LESSON_META = 'lesson_meta';

	const ASSIGNMENT      = 'assignment';
	const ASSIGNMENT_META = 'assignment_meta';

	const QUIZ      = 'quiz';
	const QUIZ_META = 'quiz_meta';

	const LD_REVIEW_TYPE           = 'ld_review';
	const LD_RATING_META_KEY       = 'rating';
	const LD_REVIEW_TITLE_META_KEY = 'review_title';

	const TUTOR_REVIEW_TYPE     = 'tutor_course_rating';
	const TUTOR_RATING_META_KEY = 'tutor_rating';

	const STUDENT_PROGRESS = 'student_progress';
	const ASSIGNMENT_FILES = 'assignment_files';

	const LD_TOPIC   = 'sfwd-topic';
	const LD_LESSONS = 'sfwd-lessons';
}
