<?php
/**
 * WC migration view
 *
 * @package TutorLMSMigrationTool\Views
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="tutor-migration-page">

	<div id="tutor-migration-wrapper">
		<div class="tutor-migration-area">
			<div class="tutor-migration-top tutor-px-48 tutor-pt-32 tutor-pb-40">
				<div>
					<div class="tutor-fs-3 tutor-fw-medium tutor-color-black tutor-course-content-title">
						<?php esc_html_e( 'Migration', 'tutor-lms-migration-tool' ); ?>
					</div>
					<div class="tutor-migration-top-subtitle tutor-fs-6">
						<?php esc_html_e( 'Seamlessly migrate your courses and sales data to Tutor LMS with the Tutor LMS Migration Tool.', 'tutor-lms-migration-tool' ); ?>
					</div>
				</div>
				<div class="tutor-d-flex tutor-justify-end tutor-align-center">
					<img src="#" alt="import">
				</div>
			</div>

			<div class="tutor-migration-tab">
				<ul class="tutor-nav">
					<li class="tutor-nav-item">
						<a class="tutor-nav-link is-active" href="#" data-tutor-nav-target="tutor-wc-auto-migrate-tab">
							<?php esc_html_e( 'Automated Migration', 'tutor-lms-migration-tool' ); ?>
						</a>
					</li>
					<li class="tutor-nav-item">
						<a class="tutor-nav-link" href="#" data-tutor-nav-target="tutor-wc-custom-migrate-tab">
							<?php esc_html_e( 'Custom Migration', 'tutor-lms-migration-tool' ); ?>
						</a>
					</li>
				</ul>
			</div>

			<!-- Auto Migrate -->
			<div class="tutor-migration-tab-item">
				<form id="wc-sales-data-migration-form">
					<div id="tutor-wc-auto-migrate-tab" class="tutor-tab-item is-active">
						<div class="tutor-tab-item-wrap tutor-pt-32 tutor-pb-40 tutor-px-48">
							<div class="lp-migration-checkbox">
								<!-- Courses -->
								<div id="sectionCourse">
									<label>
										<div class="lp-migration-singlebox">
											<div class="lp-migration-singlebox-checkbox">
												<span class="j-spinner"></span>
												<div class="etutor-updating-message"></div>
											</div>
											<div class="lp-migration-singlebox-desc">
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">
													<?php esc_html_e( 'Orders', 'tutor-lms-migration-tool' ); ?>
												</div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													<?php esc_html_e( 'History and fulfillment data.', 'tutor-lms-migration-tool' ); ?>
												</div>
												<div class="tutor-progress tutor-mb-8" data-percent="0" style="--tutor-progress:0%;"></div>
											</div>
										</div>
									</label>
								</div>

								<!-- Sales Data -->
								<div id="sectionOrders" class="tutor-py-16">
									<label>
										<div class="lp-migration-singlebox">
											<div class="lp-migration-singlebox-checkbox">
												<span class="j-spinner"></span>
											</div>
											<div class="lp-migration-singlebox-desc">
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">
													<?php esc_html_e( 'Customers', 'tutor-lms-migration-tool' ); ?>
												</div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													<?php esc_html_e( 'Includes profiles, contact info, and billing details.', 'tutor-lms-migration-tool' ); ?>
												</div>
												<div class="tutor-progress tutor-mb-8" data-percent="0" style="--tutor-progress:0%;"></div>
											</div>
										</div>
									</label>
								</div>

								<!-- Reviews -->
								<div id="sectionReviews" class="tutor-py-16">
									<label>
										<div class="lp-migration-singlebox">
											<div class="lp-migration-singlebox-checkbox">
												<span class="j-spinner"></span>
											</div>
											<div class="lp-migration-singlebox-desc">
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">
													<?php esc_html_e( 'Coupons', 'tutor-lms-migration-tool' ); ?>
												</div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													<?php esc_html_e( 'Promotional codes, offers, and usage history.', 'tutor-lms-migration-tool' ); ?>
												</div>
												<div class="tutor-progress tutor-mb-8" data-percent="0" style="--tutor-progress:0%;"></div>
											</div>
										</div>
									</label>
								</div>
								<!-- Subscriptions -->
								<div id="sectionSubscriptions" class="tutor-py-16">
									<label>
										<div class="lp-migration-singlebox">
											<div class="lp-migration-singlebox-checkbox">
												<span class="j-spinner"></span>
											</div>
											<div class="lp-migration-singlebox-desc">
												<div class="tutor-fs-6 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">
													<?php esc_html_e( 'Subscriptions', 'tutor-lms-migration-tool' ); ?>
												</div>
												<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-pb-16">
													<?php esc_html_e( 'Payment plans, subscription data, and reports.', 'tutor-lms-migration-tool' ); ?>
												</div>
												<div class="tutor-progress tutor-mb-8" data-percent="0" style="--tutor-progress:0%;"></div>
											</div>
										</div>
									</label>
								</div>
							</div>
						</div>
					</div>

					<!-- Custom Migration -->
					<div id="tutor-wc-custom-migrate-tab" class="tutor-tab-item">
						<div class="tutor-tab-item-wrap tutor-pt-32 tutor-pb-40 tutor-px-48">
							<div class="lp-migration-checkbox">
								<!-- Checkbox Option -->
								<div class="tutor-py-16">
									<label>
										<input type="checkbox" name="contents[]" value="orders">
										<?php esc_html_e( 'Orders', 'tutor-lms-migration-tool' ); ?>
									</label>
								</div>
								<div class="tutor-py-16">
									<label>
										<input type="checkbox" name="contents[]" value="customers">
										<?php esc_html_e( 'Customers', 'tutor-lms-migration-tool' ); ?>
									</label>
								</div>
								<div class="tutor-py-16">
									<label>
										<input type="checkbox" name="contents[]" value="coupons">
										<?php esc_html_e( 'Coupons', 'tutor-lms-migration-tool' ); ?>
									</label>
								</div>
								<div class="tutor-py-16">
									<label>
										<input type="checkbox" name="contents[]" value="subscriptions">
										<?php esc_html_e( 'Subscriptions', 'tutor-lms-migration-tool' ); ?>
									</label>
								</div>
							</div>
						</div>

					</div>
					<div class="tutor-px-48 tutor-py-36 tutor-border-top">
						<div class="tutor-row tutor-align-center">
							<div class="tutor-col-md-8 tutor-d-flex tutor-flex-wrap">
								<div class="tutor-d-flex tutor-gap-1 tutor-align-center">
								<i class="tutor-icon-warning tutor-color-warning"></i>
								<span class="backup-now-subtile tutor-fs-7"><?php esc_html_e( 'It\'s highly recommended to back up your data before migration.', 'tutor-lms-migration-tool' ); ?></span>
								</div>
							</div>
							<div class="migrate-now-btn-wrapper tutor-col-md-4 tutor-d-flex tutor-justify-end">
								<button type="submit" class="migrate-now-btn tutor-btn tutor-btn-primary tutor-btn-lg">
									<?php esc_html_e( 'Migrate Now', 'tutor-lms-migration-tool' ); ?>
								</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
