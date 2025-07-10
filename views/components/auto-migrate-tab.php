<?php
/**
 * Auto migrate tab
 *
 * @package TutorLMSMigrationTool\Views
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

?>

<div id="tutor-auto-migrate-tab" class="tutor-tab-item is-active">
	<div class="tutor-tab-item-wrap tutor-pt-32 tutor-pb-40 tutor-p-48">
		<form id="tlmt-lp-migrate-to-tutor-lms" action="ld_migrate_all_data_to_tutor" method="post">
			<?php tutor_nonce_field(); ?>
			<div class="lp-migration-checkbox">
				<div id="sectionCourse">
					<label for="courses">
						<div class="lp-migration-singlebox">
							<div class="lp-migration-singlebox-checkbox">
								<span class="j-spinner"></span>
								<div id="courseLoadingDiv" class="etutor-updating-message"></div>
							</div>
							<div class="lp-migration-singlebox-desc">
								<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">Courses</div>
								<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
									<?php esc_html_e( 'Transfer courses, lessons, quizzes, assignments, etc to Tutor LMS.', 'tutor-lms-migration-tool' ); ?>
								</div>
								<div class="tutor-progress tutor-mb-8" data-percent="0" style="--tutor-progress: 0%;"></div>
							</div>
						</div>
					</label>
				</div>
				<div id="sectionOrders" class="tutor-py-16">
					<label for="sales-data">
						<div class="lp-migration-singlebox">
							<div class="lp-migration-singlebox-checkbox">
								<span class="j-spinner"></span>
							</div>
							<div class="lp-migration-singlebox-desc">
								<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">
									<?php esc_html_e( 'Sales Data', 'tutor-lms-migration-tool' ); ?>
								</div>
								<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
									<?php esc_html_e( 'Migrate revenue and sales data to Tutor LMS.', 'tutor-lms-migration-tool' ); ?>
								</div>
								<div class="tutor-progress tutor-mb-8" data-percent="0" style="--tutor-progress: 0%;"></div>
							</div>
						</div>
					</label>
				</div>
				<div id="sectionReviews" class="tutor-py-16">
					<label for="reviews">
						<div class="lp-migration-singlebox">
							<div class="lp-migration-singlebox-checkbox">
								<span class="j-spinner"></span>
							</div>
							<div class="lp-migration-singlebox-desc">
								<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">
									<?php esc_html_e( 'Reviews', 'tutor-lms-migration-tool' ); ?>
								</div>
								<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
									<?php esc_html_e( 'All of the course reviews will be carried over to Tutor LMS.', 'tutor-lms-migration-tool' ); ?>
								</div>
								<div class="tutor-progress tutor-mb-8" data-percent="0" style="--tutor-progress: 0%;"></div>
							</div>
						</div>
					</label>
				</div>
			</div>
			<div id="progressCounter"></div>
		</form>
	</div>
	<div class="tutor-backup-area tutor-px-48 tutor-py-36 tutor-border-top">
		<?php require __DIR__ . '/components/migrate-now.php'; ?>
	</div>
</div>
