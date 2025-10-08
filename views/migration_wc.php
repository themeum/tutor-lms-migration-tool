<?php
/**
 * WC migration view
 *
 * @package TutorLMSMigrationTool\Views
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

use Themeum\TutorLMSMigrationTool\SalesData\MigrationHandler;

defined( 'ABSPATH' ) || exit;

$items_count = 10;
?>
<div class="tutor-migration-page">

	<div id="tutor-migration-wrapper">
		<div class="tutor-migration-area">
			<div class="tutor-migration-top tutor-px-48 tutor-pt-32 tutor-pb-40">
				<div>
					<div class="tutor-fs-3 tutor-fw-medium tutor-color-black tutor-course-content-title">
						<?php esc_html_e( 'WooCommerce Migration', 'tutor-lms-migration-tool' ); ?>
					</div>
					<div class="tutor-migration-top-subtitle tutor-fs-6">
						<?php esc_html_e( 'Effortlessly transfer your WooCommerce data to Tutor LMS using our dedicated Migration Tool.', 'tutor-lms-migration-tool' ); ?>
					</div>
				</div>
				<div class="tutor-d-flex tutor-justify-end tutor-align-center">
					<div class="tutor-radius-50 tutor-border tutor-px-16 tutor-py-12 tutor-fs-2 tutor-d-flex tutor-align-center">
						<img class="tutor-mr-16" src="<?php echo esc_url( TLMT_URL . 'assets/img/woocommerce.svg' ); ?>" alt="WooCommerce">
						<img class="tutor-mr-8" src="<?php echo esc_url( TLMT_URL . 'assets/img/arrow-split.svg' ); ?>" alt="Arrow Split">
						<img src="<?php echo esc_url( TLMT_URL . 'assets/img/tutor-logo-new.svg' ); ?>" alt="Tutor">
					</div>
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
					<?php tutor_nonce_field(); ?>
					<div id="tutor-wc-auto-migrate-tab" class="tutor-tab-item is-active">
						<div class="tutor-tab-item-wrap tutor-pt-32 tutor-pb-40 tutor-px-48">
							<div class="lp-migration-checkbox">
								<!-- Courses -->
								<div id="sectionOrders" class="tutor-pb-16">
									<label>
										<div class="lp-migration-singlebox wc-migration-singlebox">
											<div class="lp-migration-singlebox-checkbox ">
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
											</div>
										</div>
									</label>
								</div>

								<!-- Reviews -->
								<div id="sectionCoupons" class="tutor-pb-16">
									<label>
										<div class="lp-migration-singlebox wc-migration-singlebox">
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
											</div>
										</div>
									</label>
								</div>
								<!-- Subscriptions -->
								<?php if ( MigrationHandler::is_active_wc_subscription() ) : ?>
								<div id="sectionSubscriptions">
									<label>
										<div class="lp-migration-singlebox wc-migration-singlebox">
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
											</div>
										</div>
									</label>
								</div>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Custom Migration -->
					<div id="tutor-wc-custom-migrate-tab" class="tutor-tab-item">
						<div class="tutor-tab-item-wrap tutor-pt-32 tutor-pb-40 tutor-px-48">
							<div class="lp-migration-checkbox">
								<!-- Checkbox Option -->
								<div id="sectionOrders" class="tutor-pb-16">
									<div class="tutor-form-check lp-migration-singlebox wc-migration-singlebox">
										<input id="woo-orders" type="checkbox" name="job_requirements[]" value="orders" class="tutor-form-check-input lp-migration-singlebox-checkbox">
										<div class="tutor-mt-2">
											<label for="woo-orders" class="tutor-form-check-label">
												<?php esc_html_e( 'Orders', 'tutor-lms-migration-tool' ); ?>
											</label>
											<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-mt-8">
												<?php esc_html_e( 'Order history, fulfillment data.', 'tutor-lms-migration-tool' ); ?>
											</div>
										</div>
									</div>
								</div>
								<div id="sectionCoupons" class="tutor-py-16">
									<div class="tutor-form-check lp-migration-singlebox wc-migration-singlebox">
										<input id="woo-coupons" type="checkbox" name="job_requirements[]" value="coupons" class="tutor-form-check-input lp-migration-singlebox-checkbox">
										<div class="tutor-mt-2">
											<label for="woo-coupons" class="tutor-form-check-label">
												<?php esc_html_e( 'Coupons', 'tutor-lms-migration-tool' ); ?>
											</label>
											<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-mt-8">
												<?php esc_html_e( 'Promotional codes, offers, and their usage history.', 'tutor-lms-migration-tool' ); ?>
											</div>
										</div>
									</div>
								</div>
								<?php if ( MigrationHandler::is_active_wc_subscription() ) : ?>
								<div id="sectionSubscriptions" class="tutor-py-16">
									<div class="tutor-form-check lp-migration-singlebox wc-migration-singlebox">
										<input id="woo-subscriptions" type="checkbox" name="job_requirements[]" value="subscriptions" class="tutor-form-check-input lp-migration-singlebox-checkbox">
										<div class="tutor-mt-2">
											<label for="woo-subscriptions" class="tutor-form-check-label">
												<?php esc_html_e( 'Subscriptions', 'tutor-lms-migration-tool' ); ?>
											</label>
											<div class="tutor-color-muted tutor-fs-6 tutor-fw-normal tutor-mt-8">
												<?php esc_html_e( 'Payment plans, subscription data, and reports.', 'tutor-lms-migration-tool' ); ?>
											</div>
										</div>
									</div>
								</div>
								<?php endif; ?>
							</div>
						</div>

					</div>
					<div class="tutor-px-48 tutor-py-36 tutor-border-top">
						<div class="tutor-row tutor-align-center">
							<div class="tutor-col-md-9 tutor-d-flex tutor-flex-wrap">
								<div class="tutor-d-flex tutor-gap-1 tutor-align-center tutor-fs-6">
									<i class="tutor-icon-circle-info tutor-color-warning tutor-fs-5"></i>
									<span class="backup-now-subtile"><?php esc_html_e( 'It\'s highly recommended to back up your data before migration.', 'tutor-lms-migration-tool' ); ?></span>
								</div>
							</div>
							<div class="migrate-now-btn-wrapper tutor-col-md-3 tutor-d-flex tutor-justify-end">
								<span id="total_items_migrate_counts" class="tutor-d-none" data-count="<?php echo esc_attr( $items_count ); ?>"> </span>

								<!-- get active tab using php -->
								<button type="submit" class="migrate-now-btn tutor-btn tutor-btn-primary" <?php echo esc_attr( $items_count <= 0 ? 'disabled' : '' ); ?>>
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

<!-- Modal: Confirmation -->
<div class="lp-migration-modal-wrap">
	<div class="lp-migration-modal">
		<div class="lp-migration-alert lp-import flex-center tutor-flex-column tutor-py-60 tutor-text-center">
			<div class="lp-migration-modal-icon">
				<img src="<?php echo esc_url( TLMT_URL . 'assets/img/yes_no.svg' ); ?>" alt="export">
			</div>
			<div class="migration-modal-btn-group flex-center tutor-flex-column">
				<div class="tutor-fs-5 tutor-fw-normal tutor-color-black tutor-mb-32 tutor-mt-16">
					<?php
						// translators: %s: Line break tag (<br>).
						printf( esc_html__( 'Are you sure you want to migrate from %s LearnDash to Tutor LMS?', 'tutor-lms-migration-tool' ), '<br>' );
					?>
				</div>
				<div class="tutor-d-flex">
					<a href="#" class="migration-later-btn tutor-btn tutor-btn-outline-primary tutor-btn-lg tutor-mr-24">
						<span>
							<?php esc_html_e( 'No, Maybe Later!', 'tutor-lms-migration-tool' ); ?>
						</span>
					</a>
					<a href="#" class="migration-start-btn tutor-btn tutor-btn-primary tutor-btn-lg">
						<?php esc_html_e( "Yes, Let's Start", 'tutor-lms-migration-tool' ); ?>
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
				<?php esc_html_e( 'Migration Successful!', 'tutor-lms-migration-tool' ); ?>
			</div>
			<div class="tutor-fs-6 tutor-fw-normal tutor-color-black tutor-mt-16 tutor-px-12 tutor-mb-40">
				<?php esc_html_e( 'Migration from WoCommerce to Tutor LMS has been completed. Please check your contents and ensure everything is working as expected.', 'tutor-lms-migration-tool' ); ?>
			</div>
			<!-- <a href="#" class="migration-try-btn migration-done-btn tutor-btn tutor-btn-primary tutor-btn-lg tutor-mt-44 tutor-mb-20">
				<?php esc_html_e( 'Go to courses', 'tutor-lms-migration-tool' ); ?>
			</a> -->
		</div>
	</div>
</div>

<!-- Modal: Error -->
<div class="lp-error-modal-wrap">
	<div class="lp-error-modal">
		<div class="lp-modal-alert tutor-p-40">
			<img class="tutor-mt-12" style="width: 80px; height: 80px;" src="<?php echo esc_url( TLMT_URL . 'assets/img/error-modal-icon.jpg' ); ?>" alt="error-midal-alert-icon">
			<div class="modal-close success-modal-close">
				<span class="modal-close-line success-close-line-one"></span>
				<span class="modal-close-line success-close-line-two"></span>
			</div>
			<div class="tutor-fs-3 tutor-fw-normal tutor-color-black tutor-mt-28">
				<?php esc_html_e( 'Migration Failed!', 'tutor-lms-migration-tool' ); ?>
			</div>
			<div class="tutor-fs-6 tutor-fw-normal tutor-color-black tutor-mt-16 tutor-px-12">
				<?php esc_html_e( 'Oops... The migration from WoCommerce to Tutor LMS was unsuccessful. Please review everything and try again.', 'tutor-lms-migration-tool' ); ?>
			</div>
			<a href="#" class="migration-try-again-btn migration-done-btn tutor-btn tutor-btn-primary tutor-btn-lg tutor-mt-44 tutor-mb-20">
				<?php esc_html_e( 'Try Again', 'tutor-lms-migration-tool' ); ?>
			</a>
		</div>
	</div>
</div>

