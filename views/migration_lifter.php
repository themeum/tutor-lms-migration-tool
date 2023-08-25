<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tutor-migration-page">
	<?php
	global $wpdb;

	$utils = new Utils();

	$tutor_migration_history = $utils->fetch_history( 'lf' );
	$courses_count = $utils->lfter_course_count();
	$orders_count  = $utils->lifter_orders_count();
	$reviews_count = $utils->lifter_reviews_count();
    $tt= new LIFtoTutorMigration();
	$items_count = $courses_count + $orders_count + $reviews_count;
	?>

	<div id="tutor-migration-wrapper">
		<div class="tutor-migration-area">
			<div class="tutor-migration-top tutor-px-48 tutor-pt-32 tutor-pb-40">
				<div class="">
					<div class="tutor-fs-3 tutor-fw-medium tutor-color-black tutor-course-content-title">
						<?php _e( 'Migration', 'tutor-lms-migration-tool' ); ?>
					</div>
					<div class="tutor-migration-top-subtitle tutor-fs-6">
						<?php _e( 'Explore our integrated online learning destination that helps everyone gain the skills.', 'tutor-lms-migration-tool' ); ?>
					</div>
				</div>
				<div class="tutor-d-flex tutor-justify-end tutor-align-center">
					<img style src="<?php echo TLMT_URL . 'assets/img/lifterlms-icon-color.png'; ?>" width="100" height="100" alt="import">
				</div>
			</div>

			<div class="tutor-migration-tab">
				<ul class="tutor-nav">
					<li class="tutor-nav-item">
						<a class="tutor-nav-link is-active" href="#" data-tutor-nav-target="tutor-auto-migrate-tab-lf"><?php _e( 'Auto Migrate', 'tutor-lms-migration-tool' ); ?></a>
					</li>
					<li class="tutor-nav-item">
						<a class="tutor-nav-link" href="#" data-tutor-nav-target="tutor-manual-migrate-tab-lf"><?php _e( 'Upload File', 'tutor-lms-migration-tool' ); ?></a>
					</li>
					<li class="tutor-nav-item tutor-nav-more tutor-d-none">
						<a class="tutor-nav-link tutor-nav-more-item" href="#">
							<span class="tutor-mr-4"><?php _e( 'More', 'tutor-lms-migration-tool' ); ?></span> 
							<span class="tutor-nav-more-icon tutor-icon-times"></span>
						</a>
						<ul class="tutor-nav-more-list tutor-dropdown"></ul>
					</li>
				</ul>
			</div>

			<div class="tutor-migration-tab-item">
				<div id="tutor-auto-migrate-tab-lf" class="tutor-tab-item is-active">
					<div class="tutor-tab-item-wrap tutor-pt-32 tutor-pb-40 tutor-px-48">
						<form id="tlmt-lp-migrate-to-tutor-lms" action="lif_migrate_all_data_to_tutor" method="post">
							<div class="lp-migration-checkbox">
								<div id="sectionCourse">
									<label for="courses">
										<div class="lp-migration-singlebox">
											<div class="lp-migration-singlebox-checkbox">
												<span class="j-spinner"></span>
												<div id="courseLoadingDiv" class="etutor-updating-message"></div>
											</div>
											<div class="lp-migration-singlebox-desc">
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title"><?php _e( 'Courses', 'tutor-lms-migration-tool' ); ?></div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													<?php _e( 'Destination that helps everyone gain the skills.', 'tutor-lms-migration-tool' ); ?>
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
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title"><?php _e( 'Sales Data', 'tutor-lms-migration-tool' ); ?></div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													<?php _e( 'Explore our integrated online learning', 'tutor-lms-migration-tool' ); ?>
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
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title"><?php _e( 'Reviews', 'tutor-lms-migration-tool' ); ?></div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													<?php _e( 'Reviews left by your customers for your courses.', 'tutor-lms-migration-tool' ); ?>
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
					<div class="tutor-auto-migrate-tab-lf-footer tutor-px-48 tutor-py-36 tutor-border-top">
						<div class="tutor-row tutor-align-center">
							<div class="tutor-col-md-8 tutor-d-flex tutor-flex-wrap">
								<sapn class="backup-now-subtile tutor-fs-7"><?php _e( 'Please take a complete a backup for safety.', 'tutor-lms-migration-tool' ); ?></sapn>
								<form id="tutor_migration_export_form" method="post" enctype="multipart/form-data">
									<input type="hidden" id="tutor_migration_vendor" name="tutor_migration_vendor" value="lp">
									<input type="hidden" name="tutor_action" value="tutor_lp_export_xml">
									<button <?php echo $items_count ? '' : 'disabled'; ?> type="submit" class="backup-now-btn tutor-fs-7 tutor-fw-medium tutor-color-black"><?php _e( 'Backup Now', 'tutor-lms-migration-tool' ); ?></button>
								</form>
							</div>
							<div class="migrate-now-btn-wrapper tutor-col-md-4 tutor-d-flex tutor-justify-end">
								<span id="total_items_migrate_counts" class="tutor-d-none" data-count="<?php echo $items_count; ?>"> </span>
								<button type="submit" class="migrate-now-btn tutor-btn tutor-btn-primary tutor-btn-lg" <?php echo $items_count ? '' : 'disabled'; ?> >
									<?php _e( 'Migrate Now', 'tutor-lms-migration-tool' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
				<div id="tutor-manual-migrate-tab-lf" class="tutor-tab-item">
					<div class="tutor-tab-item-wrap tutor-p-48">
						<div class="tutor-migration-upload-area tutor-migration-drag-drop-zone flex-center tutor-px-48 tutor-py-68">
							<div class="tutor-migration-upload-circle tutor-mb-16 flex-center">
								<span class="tutor-fs-3 tutor-fw-medium tutor-color-primary tutor-icon-import"></span>
							</div>
							<form id="tutor-manual-migrate-form" method="post" enctype="multipart/form-data">
								<input type="hidden" name="tutor_action" value="tutor_import_from_xml">
								<div id="tutor-migration-browse-file-link" class="tutor-fs-5 tutor-fw-medium"> 
									<div class="tutor-color-black"><?php _e( 'Drag & Drop XML file here', 'tutor-lms-migration-tool' ); ?></div>
									or <a href="" class="tutor-color-primary"><?php _e( 'Browse File', 'tutor-lms-migration-tool' ); ?></a>
								</div>
								<input id="tutor-migration-browse-file" name="tutor_import_file" hidden type="file" accept=".xml" required>
								<span class="file-info tutor-fw-medium backup-now-subtile tutor-fs-6"></span>
							</form>
						</div>
					</div>
					<div class="tutor-px-48 tutor-py-36 tutor-border-top">
						<div class="tutor-row tutor-align-center">
							<div class="tutor-col-md-8 tutor-d-flex tutor-flex-wrap">
								<sapn class="backup-now-subtile tutor-fs-7"><?php _e( 'Please take a complete a backup for safety.', 'tutor-lms-migration-tool' ); ?></sapn>
								<form id="tutor_migration_export_form" method="post" enctype="multipart/form-data">
									<input type="hidden" id="tutor_migration_vendor" name="tutor_migration_vendor" value="lp">
									<input type="hidden" name="tutor_action" value="tutor_lp_export_xml">
									<button <?php echo $items_count ? '' : 'disabled'; ?> type="submit" class="backup-now-btn tutor-fs-7 tutor-fw-medium tutor-color-black"><?php _e( 'Backup Now', 'tutor-lms-migration-tool' ); ?></button>
								</form>
							</div>
							<div class="migrate-now-btn-wrapper tutor-col-md-4 tutor-d-flex tutor-justify-end">
								<button type="submit" id="manual-migrate-now-btn" class="tutor-btn tutor-btn-primary tutor-btn-lg" disabled>
									<?php _e( 'Migrate Now', 'tutor-lms-migration-tool' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- migration history area -->
		<?php if ( count( $tutor_migration_history ) ) : ?>
		<div class="tutor-migration-history">
			<div class="tutor-migration-history-heading tutor-fs-5 tutor-color-subdued tutor-mt-24 tutor-mb-16">
				<?php _e( 'Settings History', 'tutor-lms-migration-tool' ); ?>
			</div>
			
			<div class="tutor-table-responsive">
					<table class="tutor-table tutor-table-middle table-instructors tutor-table-with-checkbox">
						<thead>
							<tr>
								<th class="tutor-table-rows-sorting" style="padding-left: 38px;">
									<?php _e( 'Date', 'tutor-lms-migration-tool' ); ?>
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
											echo ', ' . date( 'h:i A', strtotime( $tutor_history->created_at ) );
										?>
										 
									</div>
								</td>
								<td>
									<?php
										$migration_type_class = '';
									if ( $tutor_history->migration_type == 'Imported' ) {
										$migration_type_class = 'success';
									} elseif ( $tutor_history->migration_type == 'Exported' ) {
										$migration_type_class = 'warning';
									}
									?>
									<div class="tutor-d-flex tutor-justify-end tutor-pr-32">
										<span class="tutor-badge-label label-<?php echo $migration_type_class; ?>">
											<?php echo $tutor_history->migration_type; ?>
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
				<img src="<?php echo TLMT_URL . 'assets/img/yes_no.svg'; ?>" alt="export">
			</div>
			<div class="migration-modal-btn-group flex-center tutor-flex-column">
				<div class="tutor-fs-5 tutor-fw-normal tutor-color-black tutor-mb-32 tutor-mt-16">
					<?php _e( 'Are you sure you want to migrate from', 'tutor-lms-migration-tool' ); ?>
					<br>
					<?php _e( 'LearnPress to Tutor LMS?', 'tutor-lms-migration-tool' ); ?>
				</div>
				<div class="tutor-d-flex">
					<a href="#" class="migration-later-btn tutor-btn tutor-btn-outline-primary tutor-btn-lg tutor-mr-24">
						<span> <?php _e( 'No, Maybe Later!', 'tutor-lms-migration-tool' ); ?></span>
					</a>
					<a href="#" class="migration-start-btn tutor-btn tutor-btn-primary tutor-btn-md">
						<span>
							<?php _e( 'Yes, Let’s Start', 'tutor-lms-migration-tool' ); ?>
						</span>
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

			<div class="tutor-fs-3 tutor-fw-normal tutor-color-black tutor-mt-28"> <?php _e( 'Migration Successful!', 'tutor-lms-migration-tool' ); ?> </div>
			<div class="tutor-fs-6 tutor-fw-normal tutor-color-black tutor-mt-16 tutor-px-12"> <?php _e( 'The migration from LearnPress to Tutor LMS is successfully done.', 'tutor-lms-migration-tool' ); ?> </div>

			<a href="<?php echo esc_url( admin_url() ); ?>admin.php?page=tutor" class="migration-try-btn migration-done-btn tutor-btn tutor-btn-primary tutor-btn-lg tutor-mt-44 tutor-mb-20">
				<?php _e( 'Go to dashboard', 'tutor-lms-migration-tool' ); ?>
			</a>
		</div>
	</div>
</div>


<div class="lp-error-modal-wrap">
	<div class="lp-error-modal">
		<div class="lp-modal-alert tutor-p-40">
			<img class="tutor-mt-12" style="width: 80px; height: 80px;" src="<?php esc_attr_e( TLMT_URL . 'assets/img/error-modal-icon.jpg' ); ?>" alt="error-midal-alert-icon">
			<div class="modal-close success-modal-close">
				<span class="modal-close-line success-close-line-one"></span>
				<span class="modal-close-line success-close-line-two"></span>
			</div>
			<div class="tutor-fs-3 tutor-fw-normal tutor-color-black tutor-mt-28"> 
				<?php _e( 'Migration Failed!', 'tutor-lms-migration-tool' ); ?> 
			</div>
			<div class="tutor-fs-6 tutor-fw-normal tutor-color-black tutor-mt-16 tutor-px-12"> 
				<?php _e( 'Oops... The migration from LearnPress to Tutor LMS was unsuccessful. Please review everything and try again.', 'tutor-lms-migration-tool' ); ?> 
			</div>
			<a href="" class="migration-try-again-btn migration-done-btn tutor-btn tutor-btn-primary tutor-btn-lg tutor-mt-44 tutor-mb-20">
				<?php _e( 'Try Again', 'tutor-lms-migration-tool' ); ?>
			</a>
		</div>
	</div>
</div>
