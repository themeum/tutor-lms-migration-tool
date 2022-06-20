<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tools-migration-lp-page">
	<?php
	global $wpdb;

    $tutor_migration_history = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT  * FROM {$wpdb->prefix}tutor_migration
            WHERE `migration_vendor` = %s
            ORDER BY ID DESC
            LIMIT %d, %d",
            'lp', 0, 20
        )
    );

	$courses_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_course' AND post_status = 'publish';" );
	$orders_count  = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_order';" );
	$reviews_count = (int) $wpdb->get_var( "SELECT COUNT(comments.comment_ID) FROM {$wpdb->comments} comments INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = comments.comment_ID AND cm.meta_key = '_lpr_rating' WHERE comments.comment_type = 'review';" );

	$items_count = $courses_count + $orders_count + $reviews_count;
	?>

	<div id="lp-area">

        <div class="tutor-px-48 tutor-pt-32 tutor-pb-40 tutor-row">
            <div class="tutor-col-md-8 tutor-col-sm-12">
                <div class="tutor-fs-3 tutor-fw-medium tutor-color-black tutor-mb-12 tutor-course-content-title">
                    Migration
                </div>
                <div class="tutor-fs-6 tutor-color-muted">
                    Explore our integrated online learning destination that helps everyone gain the skills.
                </div>
            </div>
            <div class="tutor-col-md-4 tutor-col-sm-12 tutor-d-flex tutor-justify-end tutor-align-center">
                <img style src="<?php echo TLMT_URL.'assets/img/learnpress-logo.svg'; ?>" alt="import">
            </div>
        </div>

		<div class="tutor-migration-tab">
            <ul class="tutor-nav">
                <li class="tutor-nav-item">
                    <a class="tutor-nav-link is-active" href="#" data-tutor-nav-target="tutor-auto-migrate-tab">Auto Migrate</a>
                </li>
                <li class="tutor-nav-item">
                    <a class="tutor-nav-link" href="#" data-tutor-nav-target="tutor-manual-migrate-tab">Upload File</a>
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

        <div class="tutor-migration-tab">
            <div id="tutor-auto-migrate-tab" class="tutor-tab-item is-active">
                <div class="tutor-tab-item-wrap tutor-py-40 tutor-px-48">
                    <form id="tlmt-lp-migrate-to-tutor-lms" action="lp_migrate_all_data_to_tutor" method="post">
                        <div class="lp-migration-checkbox">
                            <div id="sectionCourse">
                                <label for="courses">
                                    <div class="lp-migration-singlebox">
                                        <div class="lp-migration-singlebox-checkbox">
                                            <span class="j-spinner"></span>
                                            <div id="courseLoadingDiv" class="etutor-updating-message"></div>
                                        </div>
                                        <div class="lp-migration-singlebox-desc">
                                            <div class="tutor-fs-5 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">Courses</div>
                                            <div class="tutor-color-muted tutor-fs-6 tutor-pb-20">
                                                Course and it’s relevant informations in LearnDash
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
                                            <div class="tutor-fs-5 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">Sales Data</div>
                                            <div class="tutor-color-muted tutor-fs-6 tutor-pb-20">
                                                Explore our integrated online learning
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
                                            <div class="tutor-fs-5 tutor-fw-medium tutor-color-black tutor-mb-4 tutor-course-content-title">Reviews</div>
                                            <div class="tutor-color-muted tutor-fs-6 tutor-pb-20">
												Reviews left by your customers for your courses.
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
                <div class="tutor-px-48 tutor-py-36 tutor-border-top">
                    <div class="tutor-row tutor-align-center">
                        <div class="tutor-col-md-8 tutor-d-flex">
                            <sapn class="tutor-fs-7">Please take a complete a backup for safety.</sapn>
							<form id="tutor_migration_export_form" method="post" enctype="multipart/form-data">
                                <input type="hidden" id="tutor_migration_vendor" name="tutor_migration_vendor" value="lp">
								<input type="hidden" name="tutor_action" value="tutor_lp_export_xml">
								<button <?php echo $items_count ? '' : 'disabled'; ?> type="submit" class="backup-now-btn tutor-fs-7 tutor-fw-medium tutor-color-black">Backup Now</button>
							</form>
                        </div>
                        <div class="tutor-col-md-4 tutor-d-flex tutor-justify-end">
							<span id="total_items_migrate_counts" class="tutor-d-none" data-count="<?php echo $items_count; ?>"> </span>
                            <button type="submit" class="migrate-now-btn tutor-btn tutor-btn-primary tutor-btn-lg" <?php echo $items_count ? '' : 'disabled'; ?> >
                                Migrate Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="tutor-manual-migrate-tab" class="tutor-tab-item">
                <div class="tutor-tab-item-wrap tutor-p-48">
                    <div class="tutor-migration-upload-area drag-drop-zone flex-center tutor-px-48 tutor-py-68">
                        <div class="tutor-migration-upload-circle tutor-mb-20 flex-center">
                            <span class="tutor-fs-3 tutor-fw-medium tutor-color-primary tutor-icon-import"></span>
                        </div>
                        <form id="tutor-manual-migrate-form" method="post" enctype="multipart/form-data">
                            <input type="hidden" id="tutor_migration_vendor" name="tutor_migration_vendor" value="lp">
							<input type="hidden" name="tutor_action" value="tutor_import_from_xml">
                            <div id="tutor-migration-browse-file-link" class="tutor-fs-5 tutor-fw-medium"> 
                                <div class="tutor-color-black">Drag & Drop Your Video here,</div>
                                or <a href="" class="tutor-color-primary">Browse File</a>
                            </div>
                            <input id="tutor-migration-browse-file" name="tutor_import_file" hidden type="file" accept=".xml" required>
                            <span class="file-info tutor-fs-6 tutor-fw-medium"></span>
                        </form>
                    </div>
                </div>
                <div class="migration_tool tutor-px-48 tutor-py-28 tutor-border-top">
                    <div class="tutor-row tutor-align-center">
						<div class="tutor-col-md-8 tutor-d-flex">
                            <sapn class="tutor-fs-7">Please take a complete a backup for safety.</sapn>
							<form id="tutor_migration_export_form" method="post" enctype="multipart/form-data">
                                <input type="hidden" id="tutor_migration_vendor" name="tutor_migration_vendor" value="lp">
								<input type="hidden" name="tutor_action" value="tutor_lp_export_xml">
								<button <?php echo $items_count ? '' : 'disabled'; ?> type="submit" class="backup-now-btn tutor-fs-7 tutor-fw-medium tutor-color-black">Backup Now</button>
							</form>
                        </div>
                        <div class="tutor-col-md-4 tutor-d-flex tutor-justify-end">
                            <button type="submit" id="manual-migrate-now-btn" class="tutor-btn tutor-btn-primary tutor-btn-lg" disabled>
                                Migrate Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
	

        <!-- migration history area -->
        <div class="tutor-migration-history">
            <div class="tutor-migration-history-heading tutor-fs-5 tutor-color-subdued">
                Settings History
            </div>
            
            <div class="tutor-table-responsive">
                <?php if(count($tutor_migration_history)) : ?>
                    <table class="tutor-table tutor-table-middle table-instructors tutor-table-with-checkbox">
                        <thead>
                            <tr>
                                <th class="tutor-table-rows-sorting" style="padding-left: 38px;">
                                    Date
                                </th>
                                <th class="tutor-table-rows-sorting">
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tutor_migration_history as $tutor_history) : ?>
                            <tr>
                                <td>
                                    <div class="tutor-fs-6 tutor-color-subdued tutor-pl-24">
                                        <?php 
                                            echo esc_html( tutor_get_formated_date( get_option( 'date_format' ), $tutor_history->created_at ) ); 
                                            echo ', ' . date('h:i A', strtotime($tutor_history->created_at)); 
                                        ?> 
                                    </div>
                                </td>
                                <td>
                                    <div class="tutor-d-flex tutor-justify-end tutor-pr-32">
                                        <span class="tutor-badge-label label-warning">
                                            <?php echo $tutor_history->migration_type; ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <!-- ./ tutor-migration-history -->

	</div>

	<div id="course_migration_progress" style="margin-top: 50px;"></div>
</div>



<div class="lp-migration-modal-wrap">

	<div class="lp-migration-modal">
		<div class="lp-migration-alert lp-import tutor-p-60">
			<div class="lp-migration-modal-icon">
				<img src="<?php echo TLMT_URL . 'assets/img/yes_no.svg'; ?>" alt="export">
			</div>
			<div class="migration-modal-btn-group">
				<div class="tutor-fs-5 tutor-fw-mediumd tutor-color-black tutor-mb-32 tutor-mr-60">
					<?php _e( 'Are you sure you want to migrate from LearnDash to Tutor LMS?', 'tutor-lms-migration-tool' ); ?>
				</div>
                <div class="tutor-d-flex">
                    <a href="#" class="migration-later-btn tutor-btn tutor-btn-outline-primary tutor-btn-md tutor-mr-24">
                        <span> <?php _e( 'No, Maybe Later!', 'tutor-lms-migration-tool' ); ?></span>
                    </a>
                    <a href="#" class="migration-start-btn tutor-btn tutor-btn-primary tutor-btn-md">
                        <span>
                            <?php
                            if ( $items_count ) {
                                _e( 'Yes, Let’s Start', 'tutor-lms-migration-tool' );
                            } else {
                                _e( 'No, Maybe later!', 'tutor-lms-migration-tool' );
                            }
                            ?>
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

			<div class="tutor-fs-3 tutor-fw-mediumd tutor-color-black tutor-mt-28"> <?php _e( 'Migration Successful!', 'tutor-lms-migration-tool' ); ?> </div>
			<div class="tutor-fs-6 tutor-fw-mediumd tutor-color-black tutor-mt-12"> <?php _e( 'The migration from LearnPress to Tutor LMS is successfully done.', 'tutor-lms-migration-tool' ); ?> </div>

			<a href="#" class="migration-try-btn migration-done-btn tutor-btn tutor-btn-primary tutor-btn-md tutor-mt-40">
				<?php _e( 'Go to dashboard', 'tutor-lms-migration-tool' ); ?>
			</a>
		</div>
	</div>
</div>