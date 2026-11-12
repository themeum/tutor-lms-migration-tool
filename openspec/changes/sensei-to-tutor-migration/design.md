# Technical Design: Sensei LMS to Tutor LMS Migration

## Context

The `tutor-lms-migration-tool` plugin facilitates migration from third-party LMS platforms (LearnDash, LearnPress, LifterLMS) to Tutor LMS. Sensei LMS (by Automattic) powers thousands of learning environments, utilizing WordPress post types (`course`, `lesson`, `quiz`, `question`), taxonomies (`course-category`, `lesson-tag`, `module`), and custom storage structures (modern tables `wp_sensei_lms_progress`, `wp_sensei_lms_quiz_*` alongside legacy comment-based tracking).

This design defines the architecture, data transformation pipeline, design process, security controls, and step-by-step phased development plan for Sensei LMS migration into Tutor LMS without breaking existing functionality.

## Goals / Non-Goals

**Goals:**
- Provide a 1-click and batch-orchestrated migration tool for Sensei LMS directly inside Tutor LMS Tools.
- Strictly adhere to the existing architectural design patterns and directory layout established by `LDMigration`, `LPMigration`, and `LIFMigration`.
- Transform hierarchical Sensei course structures (`course` -> `module` -> `lesson` -> `quiz` -> `question`) into Tutor LMS structures (`courses` -> `topics` -> `lesson` / `tutor_quiz` -> `tutor_quiz_questions`).
- Fully preserve quiz questions across 6 key question types (multiple-choice, boolean, gap-fill, single-line, multi-line, file-upload) and student answer options using `FillInBlanksTransformer`.
- Migrate student enrollments, completed lessons, and quiz attempt histories with grading across both modern tables and legacy comment storage.
- Maintain WooCommerce monetization connections (`_course_woocommerce_product`) and support Tutor native orders via `OrderMigrator`, `StatusMapper`, and `PaymentMethodMapper`.
- Automatically convert Sensei `teacher` users into Tutor `tutor_instructor` users.
- Provide incremental, feature-by-feature implementation with strict security auditing and regression verification.

**Non-Goals:**
- Altering existing Sensei database tables or deleting Sensei source data without explicit user confirmation.
- Modifying existing LearnDash, LearnPress, or LifterLMS migrator workflows or core contracts.
- Migrating third-party proprietary Sensei add-on extensions outside core Sensei LMS data models.

## Architectural Overview & Design Process

### 1. UI & Design System Integration
The migration UI follows the standard Tutor LMS administrative design system utilized by LearnDash and LifterLMS migrators:
- **Location**: `admin.php?page=tutor-tools&sub_page=migration_sensei`.
- **View File**: `views/migration_sensei.php`.
- **Design Tokens**: Tutor UI CSS utility classes (`tutor-px-48`, `tutor-pt-32`, `tutor-fs-3`, `tutor-fw-medium`, `tutor-color-black`, `tutor-progress`, `j-spinner`).
- **Interactive State Machine**:
  1. *Pre-Migration*: Displays counted source records (courses, enrollments, sales/orders, reviews) using `Utils.php` helper queries.
  2. *In-Flight*: Sequential AJAX execution (`courses` -> `enrollments` -> `orders` -> `reviews`) with animated spinners (`.tmtl_spin`) and real-time percentage progress bars.
  3. *Post-Migration*: Step checkmarks (`.tmtl_done`), success confirmation, and audit logs.

### 2. Client-Server AJAX Batching Engine
Long-running migrations run the risk of HTTP timeouts (e.g., FastCGI 30s limits). The migration engine employs chunked AJAX batching:
- Client (`assets/js/admin.js`): Dispatches batched POST requests with `action=sensei_migrate_all_data_to_tutor` and `migrate_type=<step>`.
- Payload response specifies `{ has_more: bool, migrated: int, total: int, remaining: int }`.
- When `has_more: true`, the frontend recursively invokes the next batch until complete, advancing to the subsequent step.

```
[Browser Admin UI]
       │
       ▼ AJAX (action: sensei_migrate_all_data_to_tutor, type: courses)
[SenseiToTutorMigration::sensei_migrate_all_data_to_tutor]
       ├── Security Check (Nonce & manage_options / publish_tutor_courses)
       ├── Batch Processor (Fetch batch limit)
       ├── Course Post Transformation (course -> courses)
       ├── Modules Extraction (module taxonomy -> topics post)
       ├── Lessons Migration (lesson -> lesson, parented to topic)
       ├── Quizzes Migration (quiz -> tutor_quiz, parented to topic/lesson)
       ├── Questions & Answers Migration (question -> tutor_quiz_questions + answers table)
       └── Return JSON { success: true, has_more: false/true, migrated: N, total: M }
```

### 3. Structural Class Architecture (Symmetric to LIFMigration & LPMigration)

```
inc/SenseiMigration/
├── CourseTaxonomies.php             # Category ('course-category') and tag ('lesson-tag' -> 'course-tag') mapper
├── CourseMeta.php                   # Implements PostMeta interface (video embeds, prerequisites, featured)
├── LessonMeta.php                   # Implements PostMeta interface (durations, complexity, preview, video)
├── Enrollments.php                  # Chunked batch processor for course enrollments (BATCH_SIZE = 50)
├── StudentProgress.php              # Implements StudentProgress interface (lesson completion & quiz attempts)
├── Reviews.php                      # Implements Review interface for course feedback
├── Orders/
│   ├── OrderMigrator.php            # Native Tutor orders migrator (tutor_orders, items, ordermeta)
│   ├── StatusMapper.php             # Sensei / WC order status to Tutor order status mapper
│   └── PaymentMethodMapper.php      # Payment gateway mapper (Stripe, PayPal, BACS, Cheque)
├── Quizzes/
│   ├── FillInBlanksTransformer.php  # Transforms Sensei {gap} syntax to Tutor fill-in-the-blank format
│   ├── QuizMigrator.php             # Quiz settings (passmark, retries, time limits)
│   └── QuestionMigrator.php         # 6 Question types & answers table insertion
└── Product/
    ├── WCProduct.php                # Implements Product interface (WooCommerce product linkage)
    └── NativePricing.php            # Native pricing mapper when Tutor monetization is active
```

### 4. Comprehensive Data Model Mapping Matrix

| Sensei LMS Entity | Sensei Source Storage | Tutor LMS Target Entity | Tutor Target Storage |
|---|---|---|---|
| Course | `wp_posts` (`post_type='course'`) | Course | `wp_posts` (`post_type='courses'`) |
| Course Categories | `wp_term_relationships` (`course-category`) | Categories | `wp_term_relationships` (`course-category`) |
| Course Tags | `wp_term_relationships` (`lesson-tag`) | Tags | `wp_term_relationships` (`course-tag`) |
| Course Module | `wp_terms` (`taxonomy='module'`) | Topic | `wp_posts` (`post_type='topics'`, `post_parent=course_id`) |
| Lesson | `wp_posts` (`post_type='lesson'`) | Lesson | `wp_posts` (`post_type='lesson'`, `post_parent=topic_id`) |
| Lesson Preview | Meta `_lesson_preview` ('on'/'yes') | Preview | Meta `_is_preview` ('yes') |
| Lesson Length | Meta `_lesson_length` (minutes) | Duration | Meta `_video[playtime]` |
| Lesson Video | Meta `_lesson_video_embed` (URL/embed) | Video | Meta `_video[source]`, `_video[source_embedded]` |
| Quiz | `wp_posts` (`post_type='quiz'`) | Quiz | `wp_posts` (`post_type='tutor_quiz'`, `post_parent=topic_id`) |
| Quiz Passmark | Meta `_quiz_passmark` | Passing Grade | Meta `passinggrade` (percentage) |
| Quiz Questions | `wp_posts` (`post_type='question'`, `_quiz_id`) | Questions | `wp_tutor_quiz_questions` |
| Multiple Choice | Taxonomy term `multiple-choice` | Choice | `single_choice` or `multiple_choice` |
| True/False | Taxonomy term `boolean` | True/False | `true_false` |
| Gap Fill | Taxonomy term `gap-fill` | Fill Blank | `fill_in_the_blank` (via `FillInBlanksTransformer`) |
| Single Line | Taxonomy term `single-line` | Short Answer | `short_answer` |
| Multi Line | Taxonomy term `multi-line` | Open Ended | `open_ended` |
| File Upload | Taxonomy term `file-upload` | File Upload | `file_upload` |
| Enrollments | `wp_sensei_lms_progress` or `sensei_course_status` | Enrollment | `wp_posts` (`post_type='tutor_enrolled'`) |
| Lesson Complete | `wp_sensei_lms_progress` or `sensei_lesson_status` | Completed | `wp_comments` (`comment_type='tutor_lesson_completed'`) |
| Quiz Attempt | `wp_sensei_lms_quiz_submissions` | Attempt | `wp_tutor_quiz_attempts` & attempt answers |
| User Role | Role `teacher` | Instructor | Role `tutor_instructor`, status approved |
| WC Products | Meta `_course_woocommerce_product` | Product Link | Meta `_tutor_course_price_type`, `_tutor_course_product_id` |
| Native Orders | WooCommerce / Sensei single orders | Native Order | `tutor_orders`, `tutor_order_items`, `tutor_ordermeta` |

## Key Decisions & Technical Rationale

1. **Transforming Sensei Modules to Tutor Topics**:
   - *Rationale*: Sensei groups lessons within a course via the `module` taxonomy, whereas Tutor LMS organizes lessons under hierarchical `topics` posts (`post_type='topics'`). Creating a topic post for each module and parenting the module's lessons to that topic mirrors the exact instructional hierarchy.
   - *Alternative Considered*: Flattening all lessons directly under the course without topics. Rejected because it destroys course structure and violates Tutor's curriculum format.

2. **Dual-Source Progress Support (Tables + Legacy Comments)**:
   - *Rationale*: Sensei 4.x introduced custom tables (`sensei_lms_progress`), but legacy sites or sites with specific feature flags store progress in WordPress comments (`sensei_course_status`, `sensei_lesson_status`). Checking table existence first and falling back to comment records guarantees universal compatibility.

3. **Factory & Polymorphic Architecture**:
   - *Rationale*: The existing plugin codebase employs factory abstractions (`PostMetaFactory`, `StudentProgressFactory`, `ProductFactory`, `OrderFactory`, `ReviewFactory`) driven by constants in `MigrationTypes`. Registering `MigrationTypes::SENSEI_TO_TUTOR` and injecting Sensei concrete classes adheres to Open/Closed principles without modifying existing vendors.

4. **Batch Size Strategy**:
   - *Courses*: 1-3 courses per request (with recursive topic/lesson/quiz sub-tree handling).
   - *Enrollments*: 50 student pairs per request.
   - *Orders*: 20 orders per request.
   - *Progress & Quiz Attempts*: 50 records per request.

## Phased Feature-by-Feature Development Process

Development proceeds in strict, decoupled feature slices. Each feature must pass unit verification, regression checks, and security auditing before proceeding to the subsequent feature:

```
┌────────────────────────────────────────────────────────┐
│ Feature 1: UI Scaffolding, Discovery & Role Migration   │
│  - Settings link, Tool subpage, Utils counts, Teacher   │
│  - Security audit & Nonce verification                 │
│  - Regression check for LD/LP/LIF                       │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ Feature 2: Course & Module-to-Topic Hierarchy Engine    │
│  - Course post, categories, tags, module -> topics      │
│  - Security audit & SQL prepare check                  │
│  - Regression check for LD/LP/LIF                       │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ Feature 3: Lesson & Lesson Metadata Migration          │
│  - Lesson parenting, preview, duration, video embed    │
│  - Security audit & Meta sanitization                  │
│  - Regression check for LD/LP/LIF                       │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ Feature 4: Quiz & Question Types Migration             │
│  - Quizzes, 6 question types, answers table, options   │
│  - Gap-fill delimiter transformer                      │
│  - Security audit & DB insertion check                 │
│  - Regression check for LD/LP/LIF                       │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ Feature 5: Student Enrollments & Progress Migration     │
│  - Course enrollments, lesson completions, quiz grades │
│  - Dual support (tables & legacy comments)             │
│  - Security audit & User permission checks             │
│  - Regression check for LD/LP/LIF                       │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ Feature 6: Monetization, Orders & WooCommerce Linking  │
│  - Product ID mapping, OrderMigrator, StatusMapper     │
│  - Security audit & Final end-to-end audit             │
│  - Regression check for LD/LP/LIF                       │
└────────────────────────────────────────────────────────┘
```

## Security & Reliability Controls

- **Capability Checks**: Every endpoint strictly requires `manage_options` or `publish_tutor_courses` via `Utils::check_course_access()`.
- **Nonce Authentication**: All AJAX triggers enforce `tutor_utils()->checking_nonce()`.
- **Database Safety**: All custom SQL queries utilize `$wpdb->prepare` with explicit type placeholders (`%d`, `%s`). No variable concatenation or interpolated raw SQL.
- **Input Sanitization**: All `$_POST` variables are stripped of slashes (`wp_unslash`) and sanitized (`sanitize_text_field`, `absint`).
- **Resource Management**: Batch controllers dynamically raise memory limits (`wp_raise_memory_limit('admin')`) and execution time (`set_time_limit(300)`).
- **Regression Isolation**: All new files are scoped to `inc/SenseiMigration/` and `classes/SenseiToTutorMigration.php`. Existing vendor directories (`LDMigration`, `LPMigration`, `LIFMigration`) are completely untouched.

## Risks & Mitigations

- *Risk*: Sites with thousands of lessons timing out during course migration.
  - *Mitigation*: Process courses one by one per request (`COURSE_BATCH_SIZE = 1`) with filterable batch sizes (`tlmt_sensei_course_migration_batch_size`).
- *Risk*: Incomplete question types causing malformed quiz data.
  - *Mitigation*: Fallback handlers for unsupported or third-party custom question types gracefully mapping to `short_answer` with admin log notices.
- *Risk*: Legacy comment progress missing on newer Sensei installs.
  - *Mitigation*: Check for existence of `sensei_lms_progress` table before fallback to comments.
