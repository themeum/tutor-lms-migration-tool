# Implementation Tasks: Sensei LMS to Tutor LMS Migration

## 1. Feature 1: UI Scaffolding, Discovery & Teacher Role Conversion

- [ ] 1.1 Register Sensei LMS constant in `inc/MigrationTypes.php` (`SENSEI_TO_TUTOR = 'sensei_to_tutor'`) and hook Sensei tool page in `classes/TutorLMSMigrationTool.php` and verify menu tab appears under `admin.php?page=tutor-tools` when Sensei is active.
- [ ] 1.2 Implement Sensei item counting methods in `classes/Utils.php` (`sensei_course_count`, `sensei_orders_count`, `sensei_reviews_count`) and verify counts match actual Sensei database records via WP-CLI or unit test.
- [ ] 1.3 Create Sensei migration view template in `views/migration_sensei.php` following Tutor LMS design tokens (progress bars, spinners, action buttons) and verify visual rendering matches Tutor design standards.
- [ ] 1.4 Implement Sensei teacher role migration in `tutor-lms-migration-tool.php` and `classes/Utils.php` (converting role `teacher` to `tutor_instructor` with approved status on login and bulk conversion method) and verify role transition.
- [ ] 1.5 Conduct security audit for Feature 1 (validate nonces with `tutor_utils()->checking_nonce()`, ensure `current_user_can('manage_options')` checks, sanitize inputs) and verify LearnDash, LearnPress, and LifterLMS screens remain unaffected.

## 2. Feature 2: Course & Module-to-Topic Hierarchy Migration

- [ ] 2.1 Implement `inc/SenseiMigration/CourseTaxonomies.php` to migrate `course-category` and `lesson-tag` into Tutor course categories and tags and verify taxonomy term relationships in database.
- [ ] 2.2 Implement `inc/SenseiMigration/CourseMeta.php` conforming to `Interfaces\PostMeta` for course prerequisites, video embeds, and featured status, and register it in `inc/Factories/PostMetaFactory.php`.
- [ ] 2.3 Implement Sensei module extractor transforming `module` taxonomy terms to `topics` post objects parented to the migrated course and verify menu order preservation.
- [ ] 2.4 Add courses batch AJAX endpoint in `classes/SenseiToTutorMigration.php` and batch loop runner in `assets/js/admin.js` and verify courses migrate in chunked batches with real-time UI progress updates.
- [ ] 2.5 Conduct security audit for Feature 2 (audit SQL `$wpdb->prepare`, sanitize all POST parameters, verify post ownership capabilities) and verify regression safety for existing LMS course migrators.

## 3. Feature 3: Lesson & Lesson Metadata Migration

- [ ] 3.1 Implement `inc/SenseiMigration/LessonMeta.php` conforming to `Interfaces\PostMeta` for duration (`_lesson_length`), complexity, preview status (`_lesson_preview` to `_is_preview`), and video embeds, and verify meta migration.
- [ ] 3.2 Wire lesson hierarchy mapping inside `classes/SenseiToTutorMigration.php` parenting lessons to their corresponding Tutor topic posts and verify correct ordering within each topic.
- [ ] 3.3 Implement unassigned/orphan lesson handling placing lessons without a module into a designated fallback topic and verify no lessons are lost.
- [ ] 3.4 Conduct security audit for Feature 3 (sanitize post fields, escape HTML embeds, prevent ID spoofing) and verify existing lesson migration logic in LD/LP/LIF remains untouched.

## 4. Feature 4: Quiz & Question Types Migration

- [ ] 4.1 Implement `inc/SenseiMigration/Quizzes/QuizMigrator.php` converting Sensei `quiz` posts to `tutor_quiz` posts parented by topics or lessons with passing grade, time limit, and retry settings, and verify quiz metadata.
- [ ] 4.2 Implement `inc/SenseiMigration/Quizzes/QuestionMigrator.php` mapping Sensei questions to `wp_tutor_quiz_questions` for `multiple-choice`, `boolean`, `single-line`, and `multi-line` types and verify question insertion.
- [ ] 4.3 Implement `inc/SenseiMigration/Quizzes/FillInBlanksTransformer.php` transforming Sensei gap-fill brackets into Tutor LMS `fill_in_the_blank` answer format and verify blank parsing.
- [ ] 4.4 Implement `file-upload` question type migration and insert question answer options into `wp_tutor_quiz_question_answers` and verify quizzes load and function properly in Tutor course builder.
- [ ] 4.5 Conduct security audit for Feature 4 (parameterize all question answer SQL inserts, sanitize question title/content, escape outputs) and verify regression safety for existing quiz migrators.

## 5. Feature 5: Student Enrollments & Progress Migration

- [ ] 5.1 Implement `inc/SenseiMigration/Enrollments.php` to migrate course enrollments into `tutor_enrolled` posts in batches of 50 and verify student course access.
- [ ] 5.2 Implement `inc/SenseiMigration/StudentProgress.php` conforming to `Interfaces\StudentProgress` and register in `inc/Factories/StudentProgressFactory.php` with dual-source support (tables + legacy comments) and verify lesson completion comments.
- [ ] 5.3 Implement student quiz submission and attempt migration into `wp_tutor_quiz_attempts` and `wp_tutor_quiz_attempt_answers` and verify student grade reports.
- [ ] 5.4 Connect enrollment and progress batch AJAX actions in `assets/js/admin.js` and `classes/SenseiToTutorMigration.php` and verify progress bar animates accurately from 0% to 100%.
- [ ] 5.5 Conduct security audit for Feature 5 (validate student user IDs, verify authorization for progress endpoints, protect against user impersonation) and verify regression safety for LD/LP/LIF enrollments.

## 6. Feature 6: Monetization, Orders & WooCommerce Linking

- [ ] 6.1 Implement `inc/SenseiMigration/Product/WCProduct.php` conforming to `Interfaces\Product` and register in `inc/Factories/ProductFactory.php` to map Sensei WooCommerce products (`_course_woocommerce_product`) to Tutor course meta.
- [ ] 6.2 Implement `inc/SenseiMigration/Product/NativePricing.php` for Tutor native pricing configuration when Tutor monetization is active.
- [ ] 6.3 Implement `inc/SenseiMigration/Orders/OrderMigrator.php`, `inc/SenseiMigration/Orders/StatusMapper.php`, and `inc/SenseiMigration/Orders/PaymentMethodMapper.php` registered in `inc/Factories/OrderFactory.php` to migrate single course orders into `tutor_orders` without duplicate enrollment triggers.
- [ ] 6.4 Implement `inc/SenseiMigration/Reviews.php` registered in `inc/Factories/ReviewFactory.php` to migrate course reviews to `tutor_course_rating`.
- [ ] 6.5 Integrate orders and reviews steps into `classes/SenseiToTutorMigration.php` and `assets/js/admin.js` and verify end-to-end multi-step migration sequence (`courses` -> `enrollments` -> `orders` -> `reviews`).
- [ ] 6.6 Conduct security audit for Feature 6 (validate price conversions, verify checkout access capabilities, sanitize product IDs) and verify zero interference with WooCommerce orders and payments.

## 7. Comprehensive Integration, Regression & Acceptance Verification

- [ ] 7.1 Execute full end-to-end migration on sample Sensei LMS course datasets and verify complete course, lesson, quiz, student, and enrollment fidelity in Tutor LMS.
- [ ] 7.2 Run regression test suite across LearnDash, LearnPress, and LifterLMS migrator workflows and verify zero regressions or broken functionality.
- [ ] 7.3 Run PHPCS against WordPress Coding Standards (WPCS) and VIP Go security standards across all modified and newly created files and verify 0 errors.

## Workflow follow-up

- Review proposal, specs, design, and tasks with human reviewer for approval.
- Archive the change upon completion of all implementation phases.
