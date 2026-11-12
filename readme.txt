=== Tutor LMS - Migration Tool ===
Contributors: themeum
Donate link: https://www.themeum.com
Tags: LMS, migration, course, elearning, education
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.6.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Migrate your courses, lessons, quizzes, enrollments, sales data, and more from other LMS plugins to Tutor LMS.

== Description ==

Your courses, students, and sales data took months, maybe years, to build. Switching LMS platforms shouldn't put any of that at risk.

The [Tutor LMS](https://tutorlms.com/) Migration Tool moves everything from your existing LMS straight into Tutor LMS without data loss, manual re-uploads, or complicated setup. Courses, lessons, quizzes, assignments, student progress, enrollment records, sales history, reviews, and instructor data all transfer cleanly in one go.

Already on LearnDash, LearnPress, or LifterLMS? This tool handles the full migration pipeline for all three. Running an eLearning store on WooCommerce? It covers that too, including orders, subscriptions, and coupons.

No database errors. No content mismatches. No rebuilding from scratch. The one-click auto migration takes care of everything under the hood, so by the time it finishes, you're already where you need to be. Same courses. Same students. Same history. Just a better platform running it all.

It's not a migration. It's an upgrade.

= KEY FEATURES =

* One-click migration from supported LMS platforms
* Migrate courses, lessons, quizzes, quiz attempts, assignments, etc
* Transfer students, instructors, enrollments, and course progress
* Migrate course reviews, sales history, and completion status
* Import and export migration data in XML format
* Easy-to-use migration interface
* Built specifically for Tutor LMS

Currently, Tutor LMS Migration Tool supports migration from:

* LearnDash
* LearnPress
* LifterLMS
* WooCommerce to Tutor LMS Native eCommerce

We're continuously working on expanding compatibility with more LMS platforms.

= PRE-REQUISITES =

Before starting your migration, make sure you have the following plugins installed.

**Required:**

* Tutor LMS (v4.0 or later)

Depending on your migration source, you'll also need:

* LearnDash (v4.0 or later)
* LearnPress (v4.0 or later)
* LifterLMS (v8.0 or later)
* WooCommerce (v10.0 or later)
* WooCommerce Subscriptions (v7.8 or later)

You need the mentioned versions or later for the migration to work properly.

**We strongly recommend creating a complete backup of your website before initiating the migration.**

= GETTING STARTED =

After installing the Tutor LMS Migration Tool, go to **Tutor LMS > Tools > Migration** in your WordPress dashboard.

Choose your migration source and click **Migrate Now**.

Below is a list of supported data for each migration.

The following data can be migrated from **LearnDash**:

* Courses
* Lessons
* Quizzes
* Quiz Attempts
* Assignments
* Sales Data
* Subscriptions
* Taxonomies
* Student Progress
* Course Reviews

Follow this video tutorial to migrate from LearnDash to Tutor LMS:

https://www.youtube.com/watch?v=k_HkZin-V0c

**LearnPress Migration** supports:

* Courses
* Lessons
* Quizzes
* Sales Data
* Reviews
* Students
* Instructors
* Course Enrollment
* Course Complete Status

Watch the full LearnPress to Tutor LMS migration tutorial:

https://www.youtube.com/watch?v=_4LfZn5lux4

**LifterLMS Migration** supports:

* Courses
* Lessons
* Quizzes
* Assignments
* Sales Data
* Enrollment

Watch the complete LifterLMS to Tutor LMS migration walkthrough:

https://www.youtube.com/watch?v=YhrPgiPKeNQ

**WooCommerce to Tutor LMS Native eCommerce Migration** supports the following data migration:

* Orders
* Subscriptions
* Coupons

Once you start the migration, the plugin automatically transfers all supported data into Tutor LMS. Tutor LMS Migration Tool handles all your database information during the migration process. After the migration is completed, you can start using Tutor LMS from where you left off in your previous LMS.

The whole process is so seamless that you will feel like you just opted-in for a better LMS without even changing anything at all. When the process is complete, you can continue managing your courses, students, and instructors without rebuilding your learning platform.

= Separate Import & Export Option =

With Tutor LMS Migration Tool you can also separately export the data to your local folder or import the database file by uploading the XML format file.

== Installation ==

= Minimum Requirements =

* PHP version 7.2 or greater (PHP 7.4 or greater is recommended)
* MySQL version 5.0 or greater (MySQL 5.6 or greater is recommended)

= Automatic installation =

The easiest way to install any plugin in WordPress is through automatic installation. To install Tutor LMS Migration Tool this way, log in to your WordPress dashboard, navigate to the Plugins menu, and click the Add New button.

On the Add Plugins page, use the search bar to type “Tutor LMS Migration Tool”. Once the plugin appears in the search results, simply click the Install Now button.

= Manual installation =

To install Tutor LMS Migration Tool manually, you need to download the plugin and upload it to your webserver via any FTP application.

The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Frequently Asked Questions ==

= Is the Tutor LMS Migration Tool free? =

Yes, the Tutor LMS Migration Tool is completely free to use. You just need the free version of [Tutor LMS](https://wordpress.org/plugins/tutor/) installed on your WordPress site to run the migration.

= Does this plugin require Tutor LMS? =

Yes, the [Tutor LMS](https://wordpress.org/plugins/tutor/) core plugin is required to use the Migration Tool. Before starting the migration, we highly recommend creating a complete backup of your website.

= Which LMS plugins can I migrate from? =

Currently, the Tutor LMS Migration Tool supports migration from:

* LearnDash
* LearnPress
* LifterLMS
* WooCommerce to Tutor LMS Native eCommerce

We're continuously working on adding support for more migration sources.

= Do I need to keep my old LMS plugin active during migration? =

Yes, the migration tool needs access to your old LMS database to read and transfer the data. Keep the source plugin active until the migration is complete.

= Will my students need to re-enroll after migration? =

No. Student enrollment data, course progress, and completion status are all migrated. Your students can continue learning without any disruption.

= What happens to my course media files (videos, images, PDFs)? =

Media files linked within your courses are preserved. The migration tool maps existing media to the new Tutor LMS course structure. No need to re-upload.

= Is it safe to migrate a live website? =

Yes. However, we strongly recommend creating a full website backup before starting the migration. For large production websites, testing the migration on a staging site first is considered best practice.

= Where can I find the migration guides? =

You can find the complete documentation and step-by-step migration guides in our [official documentation](https://tutorlms.com/docs/migration-tool-overview/).

== Screenshots ==

1. Introduction Page
2. Confirmation Alert
3. Success Alert
4. Error Alert

== Changelog ==

= 2.6.0 - 01 September, 2026 =

New: Added support for importing LifterLMS course taxonomies, metadata, and reviews.
New: Introduced a dedicated Enrollments section for migrating LifterLMS enrollment data.
New: Added support for migrating learner progress from LifterLMS to Tutor LMS.
New: Added support for migrating Fill in the Blanks quiz questions from LifterLMS.
New: Added subscription migration support for recurring LifterLMS orders.
New: Added support for migrating LifterLMS eCommerce orders to native Tutor LMS eCommerce orders.
Update: Improved LifterLMS course, order, review, and subscription imports with batch processing for better migration performance.

= 2.5.4 - 19 August, 2026 =

Update: WordPress 7.0 compatibility updated.

= 2.5.0 - 18 August, 2026 =

New: Added course taxonomy import support. (LearnDash, LearnPress)
New: Added course meta import support. (LearnPress)
New: Added course pricing migration to Tutor LMS native e-commerce. (LearnPress)
New: Added support for fill-in-the-blanks quiz questions. (LearnPress)
New: Added quiz settings migration support. (LearnPress)
New: Added order migration to Tutor LMS native e-commerce. (LearnPress)
New: Added a safety warning modal before deleting data. (LearnDash, LearnPress)
New: Added an enrollments section to the migration dashboard. (LearnPress)
Update: Improved the enrollment import mechanism. (LearnDash, LearnPress)
Update: Improved the course import mechanism by introducing a batch system. (LearnDash, LearnPress)
Update: Separated student enrollment import from course import. (LearnDash, LearnPress)
Update: Added subscription import support. (LearnDash)
Update: Improved quiz question and answer mapping. (LearnPress)
Update: Added a native Tutor LMS path for order migration. (LearnPress)
Fix: Fixed a crash issue during quiz migration. (LearnDash)
Fix: Fixed an accuracy issue with course progress import. (LearnDash)

= 2.4.1 - 11 November, 2025 =

Fix: LearnDash to Tutor LMS migration fails due to permission issue.

= 2.4.0 - 15 October, 2025 =

New: Migrate orders from WooCommerce to Tutor Native, including associated enrollments, customers, and earnings.
New: Migrate subscriptions from WooCommerce to Tutor Native, including related enrollments, customers, and earnings.
New: Migrate coupons from WooCommerce to Tutor Native.

= 2.3.0 - 14 July, 2025 =

New: Added support for migrating assignments from LearnDash to Tutor LMS.
New: Student progress (lessons, assignments, and quiz attempts) can now be migrated from LearnDash to Tutor LMS.
New: Course reviews from the LearnDash Course Review addon can now be migrated to Tutor LMS.
New: Migrate LearnDash Native Orders to Tutor LMS native orders.
New: Migrate LearnDash "Buy Now" courses to Tutor LMS paid courses.
New: Orders migrated to Tutor Native or Tutor WooCommerce are now reflected in the Reports page.
Update: Migrate LearnDash Native Orders to Tutor WooCommerce and Easy Digital Downloads.
Update: Migrate LearnDash "Buy Now" courses to Tutor WooCommerce and Easy Digital Downloads products.
Fix: Fixed an issue where the course builder would crash when opening a quiz migrated from LearnDash to Tutor LMS.

= 2.2.2 - 29 August, 2024 =

Fix: Security vulnerabilities

= 2.2.1 - 07 August, 2024 =

*Fix: Security fixes.

= 2.2.0 - 15 November, 2023 =

*New: LifterLMS courses will be migrated to Tutor LMS now.
*New: LifterLMS lessons will be migrated to Tutor LMS now.
*New: LifterLMS quizzes will be migrated to Tutor LMS now.
*New: LifterLMS assignments will be migrated to Tutor LMS now.
*New: LifterLMS enrolment records will be migrated to Tutor LMS now.
*New: LifterLMS eCommerce products will be migrated to Tutor LMS now.
*Fix: Resolved LearnPress to Tutor LMS quiz migration error.

= 2.1.0 - 29 September, 2022 =

* New: LearnPress eCommerce Order Records will be migrated to Tutor LMS WooCommerce now
* New: LearnPress Enrolment Records will be Migrated to Tutor LMS now
* New: LearnPress Course Completion Status will be Migrated to Tutor LMS now
* New: Certificates will be generated for respective Students after migration to LearnPress
* New: LearnPress Ecommerce Products will be migrated to Tutor LMS EDD now
* New: LearnPress Ecommerce Products will be migrated to Tutor LMS WooCommerce now
* New: LearnPress Instructors will be Migrated to Tutor LMS now
* New: LearnDash Instructors will be Migrated to Tutor LMS now
* New: LearnDash Enrolment Records will be Migrated to Tutor LMS now
* New: LearnDash Course Completion Status will be Migrated to Tutor LMS now
* New: LearnDash Students will be Migrated to Tutor LMS now
* New: LearnDash Ecommerce Products will be migrated to Tutor LMS EDD now
* New: LearnDash eCommerce Order Records will be migrated to Tutor LMS WooCommerce now
* New: Certificates will be generated for respective Students after migration to LearnDash
* New: LearnDash Ecommerce Products will be migrated to Tutor LMS WooCommerce now
* New: LearnDash Closed Type course data will be migrated to WooCommerce and EDD (If Price Field not empty or 0)

= 2.0.0 - 29 June, 2022 =

* New: New design is introduced for the Migration Tool to make it more user friendly
* New: Migration performance is enhanced
* New: Migration History will be shown for each Export and Import event
* Update: Compatibility with WordPress 6.0 and Tutor LMS 2.0 is introduced
* Fix: Once migration is initiated, it kept loading behind the screen until the page is refreshed

= 1.0.4 - 30 January, 2020 =

* Added: LearnDash to Tutor LMS Migration Tool

= 1.0.3 - 04 December, 2019 =

* Updated: migration page design fix

= 1.0.2 - 03 December, 2019 =

* Updated: Design

= 1.0.1 - 03 December, 2019 =

* Updated: Design tweeks

= 1.0.0 - 28 November, 2019 =

* Initial Release

== Upgrade Notice ==

Update to the latest version.