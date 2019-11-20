<?php
if ( ! defined( 'ABSPATH' ) )
	exit;
?>
<div class="tools-migration-lp-page">
    <?php
    global $wpdb;

    $courses_count = (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_course' AND post_status = 'publish';");
    $orders_count = (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_order';");
    $reviews_count = (int) $wpdb->get_var("SELECT COUNT(comments.comment_ID) FROM {$wpdb->comments} comments INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = comments.comment_ID AND cm.meta_key = '_lpr_rating' WHERE comments.comment_type = 'review';");

    $items_count = $courses_count + $orders_count + $reviews_count;
    ?>

    <div id="lp-area">
        <div class="lp-container">
            <div class="lp-grid lp">
                <div class="lp-migratoin-left">
                    <div class="lp-migration-heading">
                        <h3>LearnPress <span> <?php _e('Migration', 'tutor-lms-migration-tool'); ?> </span> </h3>
	                    <p><?php _e('Explore our integrated online learning destination that helps need to compete successfully.', 'tutor-lms-migration-tool'); ?></p>
                    </div>
                    <form id="tlmt-lp-migrate-to-tutor-lms" action="" method="post">
                        <div class="lp-migration-checkbox">

                            <div id="sectionCourse">
                                <label for="courses">
                                    <div class="lp-migration-singlebox">
                                        <div class="lp-migration-singlebox-checkbox">
<!--
											<input name="import[courses]" type="checkbox" checked="checked" id="courses" value="1">
											<span class="checkmark"></span>
-->
                                            <!--<input type="hidden" name="migrate_data_type" value="course" />-->

                                            <span class="j-spinner"></span>
                                            <div id="courseLoadingDiv" class="etutor-updating-message"></div>
                                        </div>
                                        <div class="lp-migration-singlebox-desc">

                                            <h6><?php _e('Courses', 'tutor-lms-migration-tool'); ?></h6>
                                            <p>
					                            <?php _e('Explore our integrated online learning destination that helps need to compete successfully.', 'tutor-lms-migration-tool'); ?>
                                            </p>


                                            <div class="tutor-progress" data-percent="0" style="--tutor-progress: 0%; display: none"></div>

                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div id="sectionOrders">
                                <label for="sales-data">
                                    <div class="lp-migration-singlebox">
                                        <div class="lp-migration-singlebox-checkbox">
                                            <span class="j-spinner"></span>
                                        </div>
                                        <div class="lp-migration-singlebox-desc">
                                            <h6><?php _e('Sales Data','tutor-lms-migration-tool'); ?></h6>
                                            <p><?php _e('Explore our integrated online learning','tutor-lms-migration-tool'); ?></p>
                                            <div class="tutor-progress" data-percent="0" style="--tutor-progress: 0%; display: none"></div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div id="sectionReviews">
                                <label for="reviews">
                                    <div class="lp-migration-singlebox">
                                        <div class="lp-migration-singlebox-checkbox">
                                            <span class="j-spinner"></span>
                                        </div>
                                        <div class="lp-migration-singlebox-desc">
                                            <h6> <?php _e('Reviews', 'tutor-lms-migration-tool'); ?> </h6>
                                            <p><?php _e('Explore our integrated online learning', 'tutor-lms-migration-tool'); ?></p>
                                            <div class="tutor-progress" data-percent="0" style="--tutor-progress: 0%; display: none"></div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                        </div>

                        <div id="progressCounter"></div>

                        <div class="lp-migration-btn-group">
                            <button type="submit" class="migrate-now-btn">
	                            <?php _e('MIGRATE NOW', 'tutor-lms-migration-tool'); ?>
                            </button>
                            <span>
                                <span id="total_items_migrate_counts"> 0 </span> / <?php echo $items_count; ?> <?php _e('Items Migrates'); ?>
                            </span>
                        </div>

                        <div class="lp-required-migrate-stats">
                            <p id="lp_required_migrate_stats">
                                <?php echo sprintf( __('%s courses, %s Sales Data, %s reviews required migrate'), $courses_count, $orders_count, $reviews_count) ?>
                            </p>
                        </div>
                    </form>
                </div>
                <div class="lp-migratoin-right">
                    <!-- <img src="img/migration-illustration.svg" alt=""> -->
                </div>
            </div>
        </div>
    </div>

    <div id="lp-import-export-area">
        <div class="lp-container">
            <div class="lp-grid">
                <div class="lp-import">
                    <div class="lp-import-text">
                        <h4><?php _e('Import File', 'tutor-lms-migration-tool'); ?></h4>
                        <p><?php _e('Explore our integrated online learning destination that helps everyone gain the skills.', 'tutor-lms-migration-tool'); ?></p>
                    </div>
                    <div class="lp-import-file">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="tutor_action" value="tutor_import_from_xml">
                            <div class="lp-import-file-inner">
                                <input type="file" name="tutor_import_file">
                                <button type="submit" class="import-export-btn">
                                    <img src="<?php echo TLMT_URL.'assets/img/import.svg'; ?>" alt="import">
                                    <span>IMPORT FILE</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="lp-export">
                    <div class="lp-import-text">
                        <h4><?php _e('Export File', 'tutor-lms-migration-tool'); ?></h4>
                        <p><?php _e('Explore our integrated online learning destination that helps everyone gain the skills.', 'tutor-lms-migration-tool'); ?></p>
                    </div>
                    <div class="lp-import-file">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="tutor_action" value="tutor_lp_export_xml">
                            <div class="lp-import-file-inner">
                                <button type="submit" class="import-export-btn">
                                    <img src="<?php echo TLMT_URL.'assets/img/export.svg'; ?>" alt="export">
                                    <span>EXPORT FILE</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="course_migration_progress" style="margin-top: 50px;"></div>

</div>





<div class="lp-migration-modal-wrap">

    <div class="lp-migration-modal">
        <div class="lp-migration-alert lp-import">
            <div class="lp-migration-modal-icon">
                <img src="<?php echo TLMT_URL.'assets/img/yes_no.svg' ?>" alt="export">
            </div>
            <div class="migration-modal-btn-group">
                <p>
                    Are you sure you want to migrate from
                    LearnPress to Tutor LMS?
                </p>
                <a href="#" class="migration-later-btn">
                    <span>NO, MAYBE LATER!</span>
                </a>
                <a href="#" class="migration-start-btn">
                    <span>YES, LET’S START</span>
                </a>
            </div>
            <div class="modal-close migration-modal-close">
                <span class="modal-close-line migration-modal-close-line-one"></span>
                <span class="modal-close-line migration-modal-close-line-two"></span>
            </div>
        </div>
    </div>

</div>

<div class="lp-success-modal">
    <div class="lp-modal-alert">
        <div class="lp-modal-icon lp-modal-success animate">
            <span class="lp-modal-line lp-modal-tip animateSuccessTip"></span>
            <span class="lp-modal-line lp-modal-long animateSuccessLong"></span>
            <div class="lp-modal-placeholder"></div>
            <div class="lp-modal-fix"></div>
        </div>
        <div class="modal-close success-modal-close">
            <span class="modal-close-line success-close-line-one"></span>
            <span class="modal-close-line success-close-line-two"></span>
        </div>

        <h4> <?php _e('Migration Successful!', 'tutor-lms-migration-tool'); ?> </h4>
        <p> <?php _e('The migration from LearnPress to Tutor LMS was successfully.', 'tutor-lms-migration-tool'); ?> </p>

        <a href="#" class="migration-try-btn migration-done-btn">
            <span><?php _e('CLOSE', 'tutor-lms-migration-tool'); ?></span>
        </a>
    </div>
</div>

