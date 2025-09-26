<div class="tutor-migration-page">
	<div id="tutor-migration-wrapper">
		<div class="tutor-migration-area">
			<div class="tutor-migration-top tutor-px-48 tutor-pt-32 tutor-pb-40">
				<div class="">
					<div class="tutor-fs-3 tutor-fw-medium tutor-color-black tutor-course-content-title">
						Migration
					</div>
					<div class="tutor-migration-top-subtitle tutor-fs-6">
						Seamlessly migrate your Learndash courses to Tutor LMS with the Tutor LMS Migration Tool.
					</div>
				</div>
				<div class="tutor-d-flex tutor-justify-end tutor-align-center">
					<img src="assets/img/learndash.jpg" alt="import">
				</div>
			</div>

			<div class="tutor-migration-tab">
				<ul class="tutor-nav">
					<li class="tutor-nav-item">
						<a class="tutor-nav-link is-active" href="#" data-tutor-nav-target="tutor-auto-migrate-tab">
							Auto Migrate
						</a>
					</li>
					<li class="tutor-nav-item">
						<div class="tutor-d-flex tutor-align-center">
							<a class="tutor-nav-link" style="cursor: default; padding-right: 8px;">
								Upload File
							</a>
							<span class="tutor-rounded-pill tutor-border tutor-px-8" style="border-radius: 10px;">
								Coming soon
							</span>
						</div>
					</li>
					<li class="tutor-nav-item tutor-nav-more tutor-d-none">
						<a class="tutor-nav-link tutor-nav-more-item" href="#">
							<span class="tutor-mr-4">More</span>
							<span class="tutor-nav-more-icon tutor-icon-times"></span>
						</a>
						<ul class="tutor-nav-more-list tutor-dropdown"></ul>
					</li>
				</ul>
			</div>

			<div class="tutor-migration-tab-item">
				<div id="tutor-auto-migrate-tab" class="tutor-tab-item is-active">
					<div class="tutor-tab-item-wrap tutor-pt-32 tutor-pb-40 tutor-p-48">
						<form id="tlmt-lp-migrate-to-tutor-lms">
							<div class="lp-migration-checkbox">
								<div id="sectionCourse">
									<label>
										<div class="lp-migration-singlebox">
											<div class="lp-migration-singlebox-checkbox">
												<span class="j-spinner"></span>
												<div id="courseLoadingDiv" class="etutor-updating-message"></div>
											</div>
											<div class="lp-migration-singlebox-desc">
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">Courses</div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													Transfer courses, lessons, quizzes, assignments, etc to Tutor LMS.
												</div>
												<div class="tutor-progress tutor-mb-8" data-percent="0" style="--tutor-progress: 0%;"></div>
											</div>
										</div>
									</label>
								</div>
								<div id="sectionOrders" class="tutor-py-16">
									<label>
										<div class="lp-migration-singlebox">
											<div class="lp-migration-singlebox-checkbox">
												<span class="j-spinner"></span>
											</div>
											<div class="lp-migration-singlebox-desc">
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">
													Sales Data
												</div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													Migrate revenue and sales data to Tutor LMS.
												</div>
												<div class="tutor-progress tutor-mb-8" data-percent="0" style="--tutor-progress: 0%;"></div>
											</div>
										</div>
									</label>
								</div>
								<div id="sectionReviews" class="tutor-py-16">
									<label>
										<div class="lp-migration-singlebox">
											<div class="lp-migration-singlebox-checkbox">
												<span class="j-spinner"></span>
											</div>
											<div class="lp-migration-singlebox-desc">
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">
													Reviews
												</div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													All of the course reviews will be carried over to Tutor LMS.
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
						<button class="tutor-btn tutor-btn-primary tutor-btn-lg">Migrate Now</button>
					</div>
				</div>

				<div id="tutor-manual-migrate-tab" class="tutor-tab-item">
					<div class="tutor-tab-item-wrap tutor-p-48">
						<div class="tutor-migration-upload-area tutor-migration-drag-drop-zone flex-center tutor-px-48 tutor-py-68">
							<div class="tutor-migration-upload-circle tutor-mb-16 flex-center">
								<span class="tutor-fs-3 tutor-fw-medium tutor-color-primary tutor-icon-import"></span>
							</div>
							<form id="tutor-manual-migrate-form">
								<input type="file" id="tutor-migration-browse-file" hidden accept=".xml" required>
								<div id="tutor-migration-browse-file-link" class="tutor-fs-5 tutor-fw-medium">
									<div class="tutor-color-black">Drag & Drop XML file here</div>
									or <a href="#" class="tutor-color-primary">Browse File</a>
								</div>
								<span class="file-info tutor-fw-medium backup-now-subtile tutor-fs-6"></span>
							</form>
						</div>
					</div>
							
					<div class="tutor-backup-area tutor-px-48 tutor-py-36 tutor-border-top">
						<div class="tutor-row tutor-align-center">
							<div class="tutor-col-md-8 tutor-d-flex tutor-flex-wrap">
								<span class="backup-now-subtile tutor-fs-7">Please take a complete backup for safety.</span>
								<button type="submit" class="backup-now-btn tutor-fs-7 tutor-fw-medium tutor-color-black">
									Backup Now
								</button>
							</div>
							<div class="migrate-now-btn-wrapper tutor-col-md-4 tutor-d-flex tutor-justify-end">
								<button type="submit" id="manual-migrate-now-btn" class="tutor-btn tutor-btn-primary tutor-btn-lg" disabled>
									Migrate Now
								</button>
							</div>
						</div>
					</div>
				</div>
			</div> 
		</div>

		<!-- migration history area -->
		<div class="tutor-migration-history">
			<div class="tutor-migration-history-heading tutor-fs-5 tutor-color-subdued tutor-mt-24 tutor-mb-16">
				Settings History
			</div>
			<div class="tutor-table-responsive">
				<table class="tutor-table tutor-table-middle table-instructors tutor-table-with-checkbox">
					<thead>
						<tr>
							<th style="padding-left: 38px;">Date</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								<div class="tutor-migration-history-time tutor-fs-6 tutor-pl-24 tutor-fw-normal">
									Sept 12, 2025, 03:15 PM
								</div>
							</td>
							<td>
								<div class="tutor-d-flex tutor-justify-end tutor-pr-32">
									<span class="tutor-badge-label label-success">Imported</span>
								</div>
							</td>
						</tr>
						<tr>
							<td>
								<div class="tutor-migration-history-time tutor-fs-6 tutor-pl-24 tutor-fw-normal">
									Sept 10, 2025, 11:45 AM
								</div>
							</td>
							<td>
								<div class="tutor-d-flex tutor-justify-end tutor-pr-32">
									<span class="tutor-badge-label label-warning">Exported</span>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<!-- Modal: Confirmation -->
<div class="lp-migration-modal-wrap">
	<div class="lp-migration-modal">
		<div class="lp-migration-alert lp-import flex-center tutor-flex-column tutor-py-60 tutor-text-center">
			<div class="lp-migration-modal-icon">
				<img src="assets/img/yes_no.svg" alt="export">
			</div>
			<div class="migration-modal-btn-group flex-center tutor-flex-column">
				<div class="tutor-fs-5 tutor-fw-normal tutor-color-black tutor-mb-32 tutor-mt-16">
					Are you sure you want to migrate from <br> LearnDash to Tutor LMS?
				</div>
				<div class="tutor-d-flex">
					<a href="#" class="migration-later-btn tutor-btn tutor-btn-outline-primary tutor-btn-lg tutor-mr-24">
						<span>No, Maybe Later!</span>
					</a>
					<a href="#" class="migration-start-btn tutor-btn tutor-btn-primary tutor-btn-lg">
						Yes, Let’s Start
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

<!-- Modal: Success -->
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
				Migration Successful!
			</div>
			<div class="tutor-fs-6 tutor-fw-normal tutor-color-black tutor-mt-16 tutor-px-12">
				Migration from LearnDash to Tutor LMS has been completed. Please check your contents and ensure everything is working as expected.
			</div>
			<a href="#" class="migration-try-btn migration-done-btn tutor-btn tutor-btn-primary tutor-btn-lg tutor-mt-44 tutor-mb-20">
				Go to courses
			</a>
		</div>
	</div>
</div>

<!-- Modal: Error -->
<div class="lp-error-modal-wrap">
	<div class="lp-error-modal">
		<div class="lp-modal-alert tutor-p-40">
			<img class="tutor-mt-12" style="width: 80px; height: 80px;" src="assets/img/error-modal-icon.jpg" alt="error-midal-alert-icon">
			<div class="modal-close success-modal-close">
				<span class="modal-close-line success-close-line-one"></span>
				<span class="modal-close-line success-close-line-two"></span>
			</div>
			<div class="tutor-fs-3 tutor-fw-normal tutor-color-black tutor-mt-28">
				Migration Failed!
			</div>
			<div class="tutor-fs-6 tutor-fw-normal tutor-color-black tutor-mt-16 tutor-px-12">
				Oops... The migration from LearnDash to Tutor LMS was unsuccessful. Please review everything and try again.
			</div>
			<a href="#" class="migration-try-again-btn migration-done-btn tutor-btn tutor-btn-primary tutor-btn-lg tutor-mt-44 tutor-mb-20">
				Try Again
			</a>
		</div>
	</div>
</div>
