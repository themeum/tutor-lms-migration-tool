<?php
/**
 * Sensei LMS Migration View
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tutor-migration-page">
	<?php
	global $wpdb;

	$utils = new Utils();

	$tutor_migration_history = $utils->fetch_history( 'sensei' );
	$courses_count           = $utils->sensei_course_count();
	$orders_count            = $utils->sensei_orders_count();
	$reviews_count           = $utils->sensei_reviews_count();
	$items_count             = $courses_count + $orders_count + $reviews_count;
	?>

	<div id="tutor-migration-wrapper">
		<div class="tutor-migration-area">
			<div class="tutor-migration-top tutor-px-48 tutor-pt-32 tutor-pb-40">
				<div class="">
					<div class="tutor-fs-3 tutor-fw-medium tutor-color-black tutor-course-content-title">
						<?php esc_html_e( 'Migration', 'tutor-lms-migration-tool' ); ?>
					</div>
					<div class="tutor-migration-top-subtitle tutor-fs-6">
						<?php esc_html_e( 'Seamlessly migrate your Sensei LMS courses to Tutor LMS with the Tutor LMS Migration Tool.', 'tutor-lms-migration-tool' ); ?>
					</div>
				</div>
				<div class="tutor-d-flex tutor-justify-end tutor-align-center">
					<img src="<?php echo esc_url( TLMT_URL . 'assets/img/sensei-icon.png' ); ?>" width="100" height="100" alt="<?php esc_attr_e( 'Sensei LMS', 'tutor-lms-migration-tool' ); ?>">
				</div>
			</div>

			<div class="tutor-migration-tab">
				<ul class="tutor-nav">
					<li class="tutor-nav-item">
						<a class="tutor-nav-link is-active" href="#" data-tutor-nav-target="tutor-auto-migrate-tab"><?php esc_html_e( 'Auto Migrate', 'tutor-lms-migration-tool' ); ?></a>
					</li>
					<li class="tutor-nav-item tutor-nav-more tutor-d-none">
						<a class="tutor-nav-link tutor-nav-more-item" href="#">
							<span class="tutor-mr-4"><?php esc_html_e( 'More', 'tutor-lms-migration-tool' ); ?></span> 
							<span class="tutor-nav-more-icon tutor-icon-times"></span>
						</a>
						<ul class="tutor-nav-more-list tutor-dropdown"></ul>
					</li>
				</ul>
			</div>

			<div class="tutor-migration-tab-item">
				<div id="tutor-auto-migrate-tab" class="tutor-tab-item is-active">
					<div class="tutor-tab-item-wrap tutor-pt-32 tutor-pb-40 tutor-px-48">
						<form id="tlmt-lp-migrate-to-tutor-lms" action="sensei_migrate_all_data_to_tutor" method="post">
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
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">
													<?php esc_html_e( 'Courses', 'tutor-lms-migration-tool' ); ?>
												</div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													<?php esc_html_e( 'Transfer courses, modules (topics), lessons, quizzes, questions, etc. to Tutor LMS.', 'tutor-lms-migration-tool' ); ?>
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
													<?php esc_html_e( 'Migrate course pricing, WooCommerce product links, and sales data to Tutor LMS.', 'tutor-lms-migration-tool' ); ?>
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
					<div class="tutor-auto-migrate-tab-footer tutor-px-48 tutor-py-36 tutor-border-top">
						<div class="tutor-row tutor-align-center">
							<div class="tutor-col-md-8 tutor-d-flex tutor-flex-wrap">
								<span class="backup-now-subtile tutor-fs-7"><?php esc_html_e( 'Please take a complete backup for safety.', 'tutor-lms-migration-tool' ); ?></span>
							</div>
							<div class="migrate-now-btn-wrapper tutor-col-md-4 tutor-d-flex tutor-justify-end">
								<span id="total_items_migrate_counts" class="tutor-d-none" data-count="<?php echo esc_attr( $items_count ); ?>"></span>
								<button type="submit" class="migrate-now-btn tutor-btn tutor-btn-primary tutor-btn-lg" <?php echo $items_count ? '' : 'disabled'; ?> >
									<?php esc_html_e( 'Migrate Now', 'tutor-lms-migration-tool' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- migration history area -->
		<?php if ( is_array( $tutor_migration_history ) && count( $tutor_migration_history ) ) : ?>
		<div class="tutor-migration-history">
			<div class="tutor-migration-history-heading tutor-fs-5 tutor-color-subdued tutor-mt-24 tutor-mb-16">
				<?php esc_html_e( 'Settings History', 'tutor-lms-migration-tool' ); ?>
			</div>
			
			<div class="tutor-table-responsive">
					<table class="tutor-table tutor-table-middle table-instructors tutor-table-with-checkbox">
						<thead>
							<tr>
								<th class="tutor-table-rows-sorting" style="padding-left: 38px;">
									<?php esc_html_e( 'Date', 'tutor-lms-migration-tool' ); ?>
								</th>
								<th class="tutor-table-rows-sorting">
								</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $tutor_migration_history as $tutor_history ) : ?>
							<tr>
								<td>
									<div class="tutor-migration-history-time tutor-fs-6 tutor-pl-24 tutor-fw-normal">
										<?php
											echo esc_html( tutor_get_formated_date( get_option( 'date_format' ), $tutor_history->created_at ) );
											echo ', ' . esc_html( gmdate( 'h:i A', strtotime( $tutor_history->created_at ) ) );
										?>
																			</div>
								</td>
								<td>
									<?php
										$migration_type_class = '';
									if ( 'Imported' === $tutor_history->migration_type ) {
										$migration_type_class = 'success';
									} elseif ( 'Exported' === $tutor_history->migration_type ) {
										$migration_type_class = 'warning';
									}
									?>
									<div class="tutor-d-flex tutor-justify-end tutor-pr-32">
										<span class="tutor-badge-label label-<?php echo esc_attr( $migration_type_class ); ?>">
											<?php echo esc_html( $tutor_history->migration_type ); ?>
										</span>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
			</div>
		</div>
		<?php endif; ?>
		<!-- ./ tutor-migration-history -->

	</div>

	<div id="course_migration_progress" style="margin-top: 50px;"></div>
</div>

<div class="lp-migration-modal-wrap">
	<div class="lp-migration-modal">
		<div class="lp-migration-alert lp-import flex-center tutor-flex-column tutor-py-60 tutor-text-center">
			<div class="lp-migration-modal-icon">
				<img src="<?php echo esc_url( TLMT_URL . 'assets/img/yes_no.svg' ); ?>" alt="export">
			</div>
			<div class="migration-modal-btn-group flex-center tutor-flex-column">
				<div class="tutor-fs-5 tutor-fw-normal tutor-color-black tutor-mb-32 tutor-mt-16">
					<?php esc_html_e( 'Are you sure you want to migrate from', 'tutor-lms-migration-tool' ); ?>
					<br>
					<?php esc_html_e( 'Sensei LMS to Tutor LMS?', 'tutor-lms-migration-tool' ); ?>
				</div>
				<div class="tutor-d-flex">
					<a href="#" class="migration-later-btn tutor-btn tutor-btn-outline-primary tutor-btn-lg tutor-mr-24">
						<span><?php esc_html_e( 'No, Maybe Later!', 'tutor-lms-migration-tool' ); ?></span>
					</a>
					<a href="#" class="migration-start-btn tutor-btn tutor-btn-primary tutor-btn-md">
						<span><?php esc_html_e( 'Yes, Let’s Start', 'tutor-lms-migration-tool' ); ?></span>
					</a>
				</div>
			</div>
			<div class="modal-close migration-modal-close">
				<span class="modal-close-line migration-modal-close-line-one"></span>
				<span class="modal-close-line migration-modal-close-line-two"></span>
			</div>
		</div>
	</div>
</div>

<div class="lp-success-modal-wrap">
	<div class="lp-success-modal">
		<div class="lp-modal-alert tutor-p-40">
			<div class="lp-modal-icon lp-modal-success animate tutor-p-60">
				<span class="lp-modal-line lp-modal-tip animateSuccessTip"></span>
				<span class="lp-modal-line lp-modal-long animateSuccessLong"></span>
				<div class="lp-modal-placeholder"></div>
				<div class="lp-modal-fix"></div>
			</div>
			<div class="modal-close success-modal-close">
				<span class="modal-close-line success-close-line-one"></span>
				<span class="modal-close-line success-close-line-two"></span>
			</div>

			<div class="tutor-fs-3 tutor-fw-normal tutor-color-black tutor-mt-28">
				<?php esc_html_e( 'Migration Successful!', 'tutor-lms-migration-tool' ); ?>
			</div>
			<div class="tutor-fs-6 tutor-fw-normal tutor-color-black tutor-mt-16 tutor-px-12">
				<?php esc_html_e( 'The migration from Sensei LMS to Tutor LMS is successfully done.', 'tutor-lms-migration-tool' ); ?>
			</div>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tutor' ) ); ?>" class="migration-try-btn migration-done-btn tutor-btn tutor-btn-primary tutor-btn-lg tutor-mt-44 tutor-mb-20">
				<?php esc_html_e( 'Go to dashboard', 'tutor-lms-migration-tool' ); ?>
			</a>
		</div>
	</div>
</div>

<div class="lp-error-modal-wrap">
	<div class="lp-error-modal">
		<div class="lp-modal-alert tutor-p-40">
			<img class="tutor-mt-12" style="width: 80px; height: 80px;" src="<?php echo esc_url( TLMT_URL . 'assets/img/error-modal-icon.jpg' ); ?>" alt="error-modal-alert-icon">
			<div class="modal-close success-modal-close">
				<span class="modal-close-line success-close-line-one"></span>
				<span class="modal-close-line success-close-line-two"></span>
			</div>
			<div class="tutor-fs-3 tutor-fw-normal tutor-color-black tutor-mt-28"> 
				<?php esc_html_e( 'Migration Failed!', 'tutor-lms-migration-tool' ); ?> 
			</div>
			<div class="tutor-fs-6 tutor-fw-normal tutor-color-black tutor-mt-16 tutor-px-12"> 
				<?php esc_html_e( 'Oops... The migration from Sensei LMS to Tutor LMS was unsuccessful. Please review everything and try again.', 'tutor-lms-migration-tool' ); ?> 
			</div>
			<a href="" class="migration-try-again-btn migration-done-btn tutor-btn tutor-btn-primary tutor-btn-lg tutor-mt-44 tutor-mb-20">
				<?php esc_html_e( 'Try Again', 'tutor-lms-migration-tool' ); ?>
			</a>
		</div>
	</div>
</div>
