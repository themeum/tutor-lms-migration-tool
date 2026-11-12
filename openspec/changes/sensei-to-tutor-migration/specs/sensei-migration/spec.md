# Spec Delta: sensei-migration

## Purpose

Enables automated, batch-driven migration of courses, modules, lessons, quizzes, questions, student enrollments, progress, and teacher roles from Sensei LMS to Tutor LMS.

## ADDED Requirements

### Requirement: Sensei Migration Tool Administration Screen
The system SHALL register a dedicated Sensei LMS migration tab under the Tutor LMS Tools page (`admin.php?page=tutor-tools&sub_page=migration_sensei`) when Sensei LMS is active, presenting migration status, item counts, progress bars, and consent controls conforming to the Tutor LMS design system.

#### Scenario: Sensei plugin active
- **WHEN** an administrator navigates to the Tutor LMS Tools page and Sensei LMS is activated
- **THEN** the Sensei LMS Migration subpage is visible with total course, order, review, and enrollment counts

#### Scenario: Unauthorized access attempt
- **WHEN** a user without `manage_options` attempts to access the Sensei migration screen or trigger AJAX endpoints
- **THEN** the request is rejected with a JSON authorization error

### Requirement: Sensei Teacher to Tutor Instructor Role Conversion
The system SHALL convert users with the Sensei `teacher` role into Tutor LMS `tutor_instructor` users, setting their status to approved and preserving their course authoring capabilities.

#### Scenario: Teacher login conversion
- **WHEN** a user with the `teacher` role logs in after a Sensei migration has occurred
- **THEN** their role is updated to `tutor_instructor` and instructor status metadata is marked as approved

#### Scenario: Bulk teacher conversion
- **WHEN** an administrator triggers teacher conversion during migration
- **THEN** all Sensei teachers are mapped to Tutor instructors without altering user passwords or metadata

### Requirement: Course and Taxonomy Migration
The system SHALL migrate Sensei `course` post records to Tutor LMS `courses` post type in batched AJAX requests, preserving title, content, author, publish status, thumbnail, `course-category` terms, and `lesson-tag` tags.

#### Scenario: Course post and taxonomy migration
- **WHEN** the courses migration step executes for a Sensei course
- **THEN** a corresponding Tutor LMS course is updated or created with the same author, content, status, categories, and tags

#### Scenario: Course metadata preservation
- **WHEN** a Sensei course has video embeds or prerequisites defined
- **THEN** the migrated Tutor course contains equivalent Tutor LMS video settings and prerequisite post IDs

### Requirement: Course Module to Topic Hierarchy Transformation
The system SHALL extract Sensei `module` taxonomy terms associated with each course in their defined sequence and generate Tutor LMS `topics` posts with `post_parent` set to the migrated course ID.

#### Scenario: Ordered modules converted to topics
- **WHEN** a Sensei course containing ordered modules is migrated
- **THEN** Tutor LMS topic posts are created in matching menu order with module titles and descriptions

#### Scenario: Unassigned lessons handling
- **WHEN** a course contains lessons not assigned to any Sensei module
- **THEN** a default fallback topic is created to house those lessons under the course

### Requirement: Lesson and Lesson Metadata Migration
The system SHALL migrate Sensei `lesson` post records into Tutor LMS `lesson` post types, placing each lesson under its corresponding topic post and retaining lesson duration, difficulty complexity, preview status, and video embeds.

#### Scenario: Lesson hierarchical assignment
- **WHEN** a lesson belonging to a module is processed
- **THEN** the lesson's `post_parent` is updated to the corresponding Tutor topic ID and ordered by its Sensei lesson order

#### Scenario: Lesson preview and video migration
- **WHEN** a Sensei lesson has `_lesson_preview` enabled and `_lesson_video_embed` populated
- **THEN** the migrated Tutor lesson has `_is_preview` set to `'yes'` and Tutor video meta populated

### Requirement: Quiz Settings and Structure Migration
The system SHALL migrate Sensei `quiz` post records into Tutor LMS `tutor_quiz` post types parented by the respective topic or lesson, retaining pass percentages, retry limits, time limits, and question randomization settings.

#### Scenario: Quiz migration with passmark
- **WHEN** a Sensei quiz with passmark 80% and retake allowed is migrated
- **THEN** the Tutor quiz post is created with passing grade set to 80% and retry limit configured

### Requirement: Quiz Question and Answer Types Migration
The system SHALL extract Sensei `question` posts linked via `_quiz_id` and insert them into `tutor_quiz_questions` and `tutor_quiz_question_answers`, accurately mapping multiple-choice, boolean, gap-fill, single-line, multi-line, and file-upload question types.

#### Scenario: Multiple-choice question migration
- **WHEN** a Sensei multiple-choice question is processed
- **THEN** it is saved to `tutor_quiz_questions` as `single_choice` or `multiple_choice` and answer options are recorded with correct choice flags

#### Scenario: Gap-fill question migration
- **WHEN** a Sensei gap-fill question with bracketed gaps is processed
- **THEN** it is saved to `tutor_quiz_questions` as `fill_in_the_blank` with mapped answer delimiters

#### Scenario: Boolean and open-ended question migration
- **WHEN** a boolean, single-line, or multi-line question is processed
- **THEN** it is mapped respectively to `true_false`, `short_answer`, or `open_ended` question types

### Requirement: Student Course Enrollment Migration
The system SHALL migrate student course enrollments from `sensei_lms_progress` (or legacy `sensei_course_status` comments) into Tutor LMS `tutor_enrolled` posts in batches, linking student user ID, course ID, and original enrollment timestamp.

#### Scenario: Batch enrollment transfer
- **WHEN** the enrollments migration step executes for a batch of student course registrations
- **THEN** `tutor_enrolled` posts are inserted for active students with `post_status` set to `'completed'` and metadata recording the enrollment source

### Requirement: Student Lesson Completion and Progress Migration
The system SHALL transfer lesson completion records from Sensei progress records into Tutor LMS `tutor_lesson_completed` comment records for each student and lesson pair.

#### Scenario: Lesson completion migration
- **WHEN** a student has completed a Sensei lesson
- **THEN** a `tutor_lesson_completed` comment is created linking the user ID and Tutor lesson ID with completion timestamp

### Requirement: Quiz Attempt History and Grading Migration
The system SHALL migrate student quiz submissions and grades from `sensei_lms_quiz_submissions` and `sensei_lms_quiz_grades` into `tutor_quiz_attempts` and `tutor_quiz_attempt_answers`.

#### Scenario: Quiz submission history transfer
- **WHEN** a student has finished a quiz submission with an assigned grade
- **THEN** an attempt row is inserted into `tutor_quiz_attempts` with student ID, earned marks, and completion status

### Requirement: WooCommerce Monetization Association Migration
The system SHALL map Sensei course WooCommerce product links (`_course_woocommerce_product`) to Tutor LMS course monetization meta (`_tutor_course_price_type` = `'paid'`, `_tutor_course_product_id` = product ID).

#### Scenario: WooCommerce product attachment
- **WHEN** a Sensei course is monetized via a WooCommerce product and Tutor uses WooCommerce monetization
- **THEN** the migrated Tutor course is linked to the same WooCommerce product ID with price type marked as paid

### Requirement: Security Authorization and Nonce Enforcement
The system SHALL authenticate and authorize every migration AJAX request with `tutor_utils()->checking_nonce()` and verify administrator or course publishing capabilities, rejecting unauthorized calls and sanitizing all inputs.

#### Scenario: Validated migration request
- **WHEN** an authorized administrator submits a migration action with a valid Tutor nonce
- **THEN** the request is accepted, inputs are sanitized, and batch data is processed safely

#### Scenario: Missing or invalid nonce
- **WHEN** an AJAX request arrives with an invalid or expired nonce
- **THEN** execution terminates immediately with a 403 Forbidden or nonce error response

### Requirement: Non-Interference with Existing LMS Migrators
The system SHALL isolate all Sensei LMS migration logic within its own namespace and dedicated class handlers, ensuring LearnDash, LearnPress, and LifterLMS migrations remain fully operational and unaffected.

#### Scenario: Regression safety verification
- **WHEN** LearnDash, LearnPress, or LifterLMS migrations are executed
- **THEN** their respective classes, queries, AJAX endpoints, and factory resolutions function without alteration
