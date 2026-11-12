# Proposal: Sensei LMS to Tutor LMS Migration

## Why

Many WordPress LMS site owners running Sensei LMS (by Automattic) need a reliable, automated path to migrate to Tutor LMS without data loss, downtime, or manual recreation of instructional content. Providing native Sensei LMS migration within `tutor-lms-migration-tool` enables educators to seamlessly transfer course structures, modules (topics), lessons, rich quiz assessments, student enrollments, learning progress, sales/orders, and teacher accounts into Tutor LMS.

To ensure consistency, maintainability, and code quality, the implementation strictly mirrors the proven coding structure, design patterns, and factory architecture established in `LDMigration`, `LPMigration`, and `LIFMigration`.

## What Changes

This change introduces a comprehensive Sensei LMS to Tutor LMS migration subsystem within `tutor-lms-migration-tool` using the established architectural standards of the plugin.

Key architectural and functional additions:
- **Coding Structure & Design Patterns**:
  - Follows the established PSR-4 namespace: `Themeum\TutorLMSMigrationTool\SenseiMigration` located under `inc/SenseiMigration/`.
  - Dedicated sub-namespaces matching existing LMS modules:
    - `inc/SenseiMigration/Orders/`: `OrderMigrator.php`, `StatusMapper.php`, `PaymentMethodMapper.php` (native order migration matching `LIFMigration/Orders` and `LPMigration/Orders`).
    - `inc/SenseiMigration/Quizzes/`: `FillInBlanksTransformer.php`, `QuizMigrator.php`, `QuestionMigrator.php` (quiz and assessment transformer matching `LIFMigration/Quizzes`).
    - `inc/SenseiMigration/Product/`: `WCProduct.php` and `NativePricing.php` (product and pricing mapping matching `LDMigration/Product` and `LIFMigration/Product`).
    - `inc/SenseiMigration/CourseTaxonomies.php`: handles category and tag mapping matching `LIFMigration/CourseTaxonomies.php`.
    - `inc/SenseiMigration/CourseMeta.php`: implements `Interfaces\PostMeta` for course-level metadata.
    - `inc/SenseiMigration/LessonMeta.php`: implements `Interfaces\PostMeta` for lesson-level metadata.
    - `inc/SenseiMigration/Enrollments.php`: chunked enrollment batch processor matching `LIFMigration/Enrollments.php`.
    - `inc/SenseiMigration/StudentProgress.php`: implements `Interfaces\StudentProgress` for lesson completion and quiz attempt histories.
    - `inc/SenseiMigration/Reviews.php`: implements `Interfaces\Review` for course feedback.
  - Main orchestrator class: `classes/SenseiToTutorMigration.php` structured symmetrically to `classes/LIFtoTutorMigration.php`.
  - Integration with plugin factories: `PostMetaFactory`, `StudentProgressFactory`, `ProductFactory`, `OrderFactory`, and `ReviewFactory`.

- **Course Content Data Map**:
  - `course` post type $\rightarrow$ Tutor LMS `courses` post type.
  - `module` taxonomy terms $\rightarrow$ Tutor LMS `topics` hierarchical posts (`post_type='topics'`, `post_parent=course_id`), preserving module display order (`get_course_module_order`).
  - `lesson` post type $\rightarrow$ Tutor LMS `lesson` (`tutor()->lesson_post_type`), parented to its corresponding topic post (`post_parent=topic_id`). Unassigned lessons fall back into a default general topic.
  - Course taxonomies: `course-category` $\rightarrow$ Tutor `course-category`; `lesson-tag` $\rightarrow$ Tutor `course-tag`.
  - Course metadata: video embed (`_course_video_embed`), course prerequisites (`_course_prerequisite`), and featured flag (`_course_featured`).
  - Lesson metadata: duration/length (`_lesson_length`), difficulty (`_lesson_complexity`), free preview (`_lesson_preview` $\rightarrow$ `_is_preview = 'yes'`), and lesson video embeds (`_lesson_video_embed`).
  - `quiz` post type $\rightarrow$ Tutor LMS `tutor_quiz` parented by topic/lesson, transferring passing grade percentage (`_quiz_passmark`), retake limit (`_enable_quiz_reset`), time limit, and question randomization (`_random_question_order`).
  - `question` post type $\rightarrow$ Tutor `wp_tutor_quiz_questions` and `wp_tutor_quiz_question_answers` supporting all 6 Sensei question types:
    - `multiple-choice` $\rightarrow$ `single_choice` or `multiple_choice`.
    - `boolean` $\rightarrow$ `true_false`.
    - `gap-fill` $\rightarrow$ `fill_in_the_blank` with bracketed delimiter transformation via `FillInBlanksTransformer`.
    - `single-line` $\rightarrow$ `short_answer`.
    - `multi-line` $\rightarrow$ `open_ended`.
    - `file-upload` $\rightarrow$ `file_upload`.

- **Teacher & Instructor Map**:
  - Sensei `teacher` role $\rightarrow$ Tutor LMS `tutor_instructor` role.
  - Grants instructor permissions: sets `_is_tutor_instructor`, `_tutor_instructor_status = 'approved'`, and `_tutor_instructor_approved = time()`.
  - Automatic role migration hook on `wp_login` in `tutor-lms-migration-tool.php` when Sensei migration history exists.
  - Static utility method `Utils::convert_all_sensei_teachers_to_tutor_instructors()` for bulk conversion during migration.

- **Student & Progress Map**:
  - Dual-source compatibility: supports both modern Sensei tables (`wp_sensei_lms_progress`, `wp_sensei_lms_quiz_submissions`, `wp_sensei_lms_quiz_grades`) and legacy comment-based records (`sensei_course_status`, `sensei_lesson_status`, `sensei_quiz_submission`).
  - Student course enrollments $\rightarrow$ Tutor LMS `tutor_enrolled` posts (`post_parent=course_id`, `post_author=student_id`, `post_status='completed'`), recording enrollment date and meta tag `_tutor_enrolled_by = 'sensei_migration'`.
  - Lesson completion records $\rightarrow$ Tutor `wp_comments` records with `comment_type = 'tutor_lesson_completed'`, linking user and lesson ID with completion timestamps.
  - Quiz attempt history $\rightarrow$ Tutor `wp_tutor_quiz_attempts` and `wp_tutor_quiz_attempt_answers`, capturing total marks, earned score, pass/fail status, and student response records.

- **Sales Data Map**:
  - Dual monetization mode handling:
    - *WooCommerce Monetization*: Courses linked to WooCommerce products via `_course_woocommerce_product` are connected to Tutor course meta (`_tutor_course_price_type = 'paid'`, `_tutor_course_product_id = product_id`). Existing WooCommerce customer orders remain seamlessly connected.
    - *Tutor Native Monetization*: When Tutor native monetization is active, course orders are extracted and mapped into `tutor_orders`, `tutor_order_items`, and `tutor_ordermeta` via `OrderMigrator`, using `StatusMapper` and `PaymentMethodMapper` to preserve financial transaction history without triggering live checkout emails or duplicate enrollments.

- **Admin UI & Resilient Batch Engine**:
  - Adds dedicated tool page `admin.php?page=tutor-tools&sub_page=migration_sensei` in `views/migration_sensei.php` following Tutor LMS UI design system.
  - Batched AJAX execution with memory and execution limit protections (`COURSE_BATCH_SIZE = 1`, `ENROLLMENT_BATCH_SIZE = 50`, `ORDER_BATCH_SIZE = 20`).
  - Seamless frontend orchestration in `assets/js/admin.js` updating step progress bars (Courses $\rightarrow$ Enrollments $\rightarrow$ Sales $\rightarrow$ Reviews).

- **Non-breaking Architecture & Security**:
  - All Sensei logic is isolated in dedicated namespaces and classes.
  - LearnDash (`LDMigration`), LearnPress (`LPMigration`), and LifterLMS (`LIFMigration`) code remains 100% untouched and protected against regression.
  - Strict security controls: CSRF nonce check (`tutor_utils()->checking_nonce()`), capability checks (`manage_options` / `publish_tutor_courses`), input sanitization, and SQL query parameterization (`$wpdb->prepare`).

## Capabilities

### New Capabilities
- `sensei-migration`: Complete data migration capability transferring Sensei LMS courses, modules (topics), lessons, quizzes, questions, student enrollments, completion progress, sales data, and teacher user roles into Tutor LMS.

### Modified Capabilities
<!-- None: existing migration specifications remain unchanged -->

## Impact

- **Affected Files / New Files**:
  - Main loader: [tutor-lms-migration-tool.php](file:///Users/shewa/Local%20Sites/tutor-lms-400/app/public/wp-content/plugins/tutor-lms-migration-tool/tutor-lms-migration-tool.php) (teacher login hook)
  - Tool coordinator: [classes/TutorLMSMigrationTool.php](file:///Users/shewa/Local%20Sites/tutor-lms-400/app/public/wp-content/plugins/tutor-lms-migration-tool/classes/TutorLMSMigrationTool.php) (class registration & settings links)
  - Utility helpers: [classes/Utils.php](file:///Users/shewa/Local%20Sites/tutor-lms-400/app/public/wp-content/plugins/tutor-lms-migration-tool/classes/Utils.php) (item counts & teacher conversion methods)
  - Primary orchestrator: `classes/SenseiToTutorMigration.php` (AJAX controllers, batch handlers)
  - View template: `views/migration_sensei.php` (Tutor UI layout)
  - Frontend script: [assets/js/admin.js](file:///Users/shewa/Local%20Sites/tutor-lms-400/app/public/wp-content/plugins/tutor-lms-migration-tool/assets/js/admin.js) (batch request loop, progress rendering)
  - Core subsystem classes under `inc/SenseiMigration/`:
    - `CourseTaxonomies.php`
    - `CourseMeta.php`
    - `LessonMeta.php`
    - `Enrollments.php`
    - `StudentProgress.php`
    - `Reviews.php`
    - `Quizzes/QuizMigrator.php`
    - `Quizzes/QuestionMigrator.php`
    - `Quizzes/FillInBlanksTransformer.php`
    - `Product/WCProduct.php`
    - `Product/NativePricing.php`
    - `Orders/OrderMigrator.php`
    - `Orders/StatusMapper.php`
    - `Orders/PaymentMethodMapper.php`
  - Integration in [inc/MigrationTypes.php](file:///Users/shewa/Local%20Sites/tutor-lms-400/app/public/wp-content/plugins/tutor-lms-migration-tool/inc/MigrationTypes.php) & Factory classes (`PostMetaFactory`, `StudentProgressFactory`, `ProductFactory`, `OrderFactory`, `ReviewFactory`).
- **Dependencies**: Sensei LMS (v4.0+), Tutor LMS (v2.6+ / v3.6+ core).
- **Security & APIs**: Nonce verification (`tutor_utils()->checking_nonce()`), capability checks (`manage_options` / `publish_tutor_courses`), strict input sanitization, and SQL preparation.
