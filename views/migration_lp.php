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
                            <label for="courses">
                                <div class="lp-migration-singlebox">
                                    <div class="lp-migration-singlebox-checkbox">
                                        <input name="import[courses]" type="checkbox" checked="checked" id="courses" value="1">
                                        <span class="checkmark"></span>
                                    </div>
                                    <div class="lp-migration-singlebox-desc">
                                        <h6><?php _e('Courses', 'tutor-lms-migration-tool'); ?></h6>
                                        <p>
	                                        <?php _e('Explore our integrated online learning destination that helps need to compete successfully.', 'tutor-lms-migration-tool'); ?>
                                        </p>
                                    </div>
                                </div>
                            </label>
                            <label for="sales-data">
                                <div class="lp-migration-singlebox">
                                    <div class="lp-migration-singlebox-checkbox">
                                        <input name="import[orders]" type="checkbox" checked="checked" id="sales-data">
                                        <span class="checkmark"></span>
                                    </div>
                                    <div class="lp-migration-singlebox-desc">
                                        <h6>Sales Data</h6>
                                        <p>
                                            Explore our integrated online learning
                                        </p>
                                    </div>
                                </div>
                            </label>
                            <label for="reviews">
                                <div class="lp-migration-singlebox">
                                    <div class="lp-migration-singlebox-checkbox">
                                        <input name="import[reviews]" type="checkbox" checked="checked" id="reviews">
                                        <span class="checkmark"></span>
                                    </div>
                                    <div class="lp-migration-singlebox-desc">

                                        <h6> <?php _e('Reviews', 'tutor-lms-migration-tool'); ?> </h6>
                                        <p>Explore our integrated online learning</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div class="lp-migration-btn-group">
                            <button type="submit" class="migrate-now-btn">
                                MIGRATE NOW
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
                        <h4>Import File</h4>
                        <p>Explore our integrated online learning destination that
                            helps everyone gain the skills.</p>
                    </div>
                    <div class="lp-import-file">
                        <form action="" method="get">
                            <div class="lp-import-file-inner">
                                <input type="file" name="import-file">
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
                        <h4>Export File</h4>
                        <p>Explore our integrated online learning destination that
                            helps everyone gain the skills.</p>
                    </div>
                    <div class="lp-import-file">
                        <form action="" method="get">
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


    <button id="migrate_lp_courses_btn" class="tutor-button tutor-button-primary">
        <?php echo sprintf(__('Migrate %s courses', 'tutor'), $courses_count); ?>
    </button>

    <form method="post">
        <input type="hidden" name="tutor_action" value="migrate_lp_orders">
        <button type="submit" id="migrate_lp_orders_btn" class="tutor-button button-success">
            <?php echo sprintf(__('Migrate %s orders', 'tutor'), $orders_count); ?>
        </button>
    </form>

    <form method="post">
        <input type="hidden" name="tutor_action" value="migrate_lp_reviews">
        <button type="submit" id="migrate_lp_orders_btn" class="tutor-button button-success">
            <?php echo sprintf(__('Migrate %s Reviews', 'tutor'), $reviews_count); ?>
        </button>
    </form>

    <div id="course_migration_progress" style="margin-top: 50px;"></div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="tutor_action" value="tutor_lp_export_xml">
        <button type="submit" class="tutor-button button-success">
			<?php echo sprintf(__('Export Data', 'tutor'), $reviews_count); ?>
        </button>
    </form>

    <form method="post" enctype="multipart/form-data">
        <input type="file" name="tutor_import_file">

        <input type="hidden" name="tutor_action" value="tutor_import_from_xml">
        <button type="submit" class="tutor-button button-success">
		    <?php echo sprintf(__('Import', 'tutor'), $reviews_count); ?>
        </button>
    </form>



</div>