=== Tutor LMS - Migration Tool ===
Contributors: themeum
Donate link: https://www.themeum.com
Tags: lms, migration, course, elearning, education
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Move all your course, quiz, order data information and everything else from your LMS to the better Tutor LMS by simply clicking a button.

== Description ==
Migrate to [Tutor LMS](https://tutorlms.com/) for a better, richer, and smarter [eLearning](https://wordpress.org/plugins/tutor/) experience. 

If you are using the LearnDash, LearnPress, or LifterLMS plugin and want to migrate to Tutor LMS, this plugin can help you migrate without losing your valuable data (Courses, Lessons, Quizzes, Sales Data, Reviews, Students, Instructors, Course Enrollment and Course Complete Status).

No need to hassle with complicated migration settings when you can use Tutor LMS Migration Tool with its easy user interface. With its simple one-click auto migration settings you can have all the information from your old LMS plugin transferred without any database error.

= UPDATES =

In this version of Tutor LMS Migration Tool, we are introducing migration options from major LMS plugin to Tutor LMS. We have a roadmap to bring you compatibility support for migration with many other plugins in the future.

Currently Tutor LMS Migration support migration options from

* LearnDash
* LearnPress &
* LifterLMS

= Pre-requisites =

To get started with migrating your information from LearnDash/LearnPress/Lifter LMS to Tutor LMS in the easiest way, you need to make sure you have the following plugins installed.

**Note:** Currently the Tutor LMS Migration Tool supports migration from LearnDash, LearnPress, and LifterLMS to Tutor LMS. We are working hard to bring migration options from other LMS to Tutor LMS as soon as possible. But as it only supports migration from LearnPress/LearnDash the following plugins are required

  * Tutor LMS (Version 3.6 or later)

And the following plugins for their respective migrations

  * For LearnDash ( Version 4.0 or later)
  * For LearnPress (Version 4.0 or later)
  * For LifterLMS ( Version 8.0 or later)

You need the mentioned versions or later of the plugin for the migration to work properly.

= Get Started =

After you install the migration plugin in your WordPress site you will find the migration option in the Tools menu of Tutor LMS.

LearnDash Migration supports the migration of the following data types:

  * Courses
  * Lessons
  * Quizzes
  * Quiz Attempts
  * Assignments
  * Sales Data
  * Students Progress
  * Course Reviews

LearnPress Migration supports the migration of the following data types:

  * Courses
  * Lessons
  * Quizzes
  * Sales Data
  * Reviews
  * Students
  * Instructors
  * Course Enrollment
  * Course Complete Status

LifterLMS Migration supports the migration of the following data types:

  * Courses
  * Lessons
  * Quizzes
  * Assignments
  * Sales Data
  * Enrollment

For quick migrations, click on the "Migrate Now" button to get your migration process started. Sit back and enjoy while the Tutor LMS Migration Tool handles all your database information during the migration process. After the migration tool is done, you can start using Tutor LMS from where you left off in your previous LMS.

The whole process is so seamless that you will feel like you just opted-in for a better LMS without even changing anything at all.

= Separate Import & Export Option =

With Tutor LMS Migration Tool you can also separately export the data to your local folder or import the database file by uploading the XML format file.

== Installation ==

= Minimum Requirements =

* PHP version 7.2 or greater (PHP 7.4 or greater is recommended)
* MySQL version 5.0 or greater (MySQL 5.6 or greater is recommended)

= Automatic installation =

The automatic installation is the easiest way to install any plugin in WordPress. You can perform an automatic installation of Tutor by logging in to your WordPress dashboard, navigating to the "Plugins" menu and click on the "Add New" button.

This will open up a page showing all the available plugins in WordPress. In the search field, type Tutor. The search result will show you our Tutor plugin, you can then see the detailed info by clicking on "More Details" and to install just click on the "Install Now" button.

= Manual installation =

To install Tutor LMS Migration Tool manually, you need to download the plugin and upload it to your webserver via any FTP application.

The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Frequently Asked Questions ==

= Is This Plugin has any Dependency? =

Yes, You need to install the [Tutor LMS](https://wordpress.org/plugins/tutor/) plugin in order to use this plugin. Take a backaup of your full website before migrate.

= I need migrate others LMS data to Tutor LMS, what should I do? =

We will add others LMS migration to this plugin, so you can migrate it. Or if you don't required any previous data, just delete other LMS and move to Tutor LMS.

== Screenshots ==

1. Introduction Page
2. Confirmation Alert
3. Success Alert
4. Error Alert

== Changelog ==

= 2.3.0 - 15 July, 2025 =

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