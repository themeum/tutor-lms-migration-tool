=== Tutor LMS - Migration Tool ===
Contributors: themeum
Donate link: https://www.themeum.com
Tags: LMS, migration, course, elearning, education
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.4.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Move all your course, quiz, order data information and everything else from your LMS to the better Tutor LMS by simply clicking a button.

== Description ==
Migrate to [Tutor LMS](https://tutorlms.com/) for a better, richer, and smarter [eLearning](https://wordpress.org/plugins/tutor/) experience. 

If you are using the LearnDash, LearnPress, or LifterLMS plugin and want to migrate to Tutor LMS, this plugin can help you migrate without losing your valuable data (Courses, Lessons, Quizzes, Sales Data, Reviews, Students, Instructors, Course Enrollment and Course Complete Status).

No need to hassle with complicated migration settings when you can use Tutor LMS Migration Tool with its easy user interface. With its simple one-click auto migration settings you can have all the information from your old LMS plugin transferred without any database error.

= KEY FEATURES =

The Tutor LMS Migration Tool allows you to seamlessly migrate data from major LMS plugins and WooCommerce into Tutor LMS. We're also actively working on expanding compatibility with more plugins and platforms in future updates.

Currently Tutor LMS Migration support migration options from

* LearnDash
* LearnPress
* LifterLMS &
* WooCommerce to Tutor LMS Native eCommerce migration

= Pre-requisites =

To get started with migrating your data from existing LMS to Tutor LMS in the easiest way, you need to make sure you have the following plugins installed.

**Note:** Currently the Tutor LMS Migration Tool supports migration from LearnDash, LearnPress, LifterLMS, and WooCommerce to Tutor LMS.

  * Tutor LMS (v3.8 or later)

And the following plugins for their respective migrations

  * For LearnDash (v4.0 or later)
  * For LearnPress (v4.0 or later)
  * For LifterLMS (v8.0 or later)
  * For WooCommerce (v10.0 or later)
  * For WooCommerce Subscriptions (v7.8 or later)

You need the mentioned versions or later of the plugin for the migration to work properly.

= Get Started =

fter installing the Migration Tool plugin on your WordPress site, you’ll find the migration option under the Tools menu in Tutor LMS. Below is a list of available data which you can migrate to Tutor LMS:

**LearnDash Migration** supports the migration of the following data types:

  * Courses
  * Lessons
  * Quizzes
  * Quiz Attempts
  * Assignments
  * Sales Data
  * Students Progress
  * Course Reviews

**LearnPress Migration** supports the migration of the following data types:

  * Courses
  * Lessons
  * Quizzes
  * Sales Data
  * Reviews
  * Students
  * Instructors
  * Course Enrollment
  * Course Complete Status

**LifterLMS Migration** supports the migration of the following data types:

  * Courses
  * Lessons
  * Quizzes
  * Assignments
  * Sales Data
  * Enrollment

**WooCommerce Migration** supports the migration of the following data types:

  * Orders
  * Subscriptions
  * Coupons

For quick migrations, click on the "Migrate Now" button to get your migration process started. Sit back and enjoy while the Tutor LMS Migration Tool handles all your database information during the migration process. After the migration tool is done, you can start using Tutor LMS from where you left off in your previous LMS.

The whole process is so seamless that you will feel like you just opted-in for a better LMS without even changing anything at all.

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

= Is this plugin has any dependency? =

Yes, you need to install the [Tutor LMS](https://wordpress.org/plugins/tutor/) plugin in order to use this plugin. Take a backaup of your full website before migrating your data.

= I want to migrate data from another LMS to Tutor LMS. How can I do that? =

You can use the Tutor LMS Migration Tool to transfer data from another LMS platforms to Tutor LMS. To learn more about the full process, please check the [official migration documentation](https://docs.themeum.com/tutor-lms/migration/).

== Screenshots ==

1. Introduction Page
2. Confirmation Alert
3. Success Alert
4. Error Alert

== Changelog ==

= 2.4.1 - 11 November, 2025 =

Fix: LearnDash to Tutor migration fails due to permission issue.

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

None Available