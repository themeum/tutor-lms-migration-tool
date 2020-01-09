jQuery(document).ready(function($){
    'use strict';

    /**
     * LP Migration
     * Since v.1.4.6
     */

    var checkProgress;

    function get_live_progress_course_migrating_info(final_types='lp'){
        $.ajax({
            url : ajaxurl,
            type : 'POST',
            data : {action : '_get_'+final_types+'_live_progress_course_migrating_info' },
            success: function (data) {
                if (data.success) {
                    if (data.data.migrated_count) {
                        $('#total_items_migrate_counts').html(data.data.migrated_count);
                    }
                    checkProgress = setTimeout(get_live_progress_course_migrating_info, 2000);
                }
            }
        });
    }

    var countProgress;
    function migration_progress_bar(cmplete){
        var $progressBar = $('#sectionCourse').find('.tutor-progress');
        var data_parcent = parseInt($progressBar.attr('data-percent'));

        if (cmplete) {
            $progressBar.attr('style', '--tutor-progress : 100% ').attr('data-percent', 100);
        } else {
            data_parcent++;
            $progressBar.show().attr('style', '--tutor-progress : '+data_parcent+'% ').attr('data-percent', data_parcent);
            countProgress = setTimeout(migration_progress_bar, 300, cmplete );
        }
    }

    $(document).on( 'submit', 'form#tlmt-lp-migrate-to-tutor-lms',  function( e ){
        e.preventDefault();

        var $that = $(this);
        var $formData = $(this).serialize()+'&action='+$that.attr('action');

        let final_types = 'lp';
        if($that.attr('action') == 'ld_migrate_all_data_to_tutor') {
            final_types = 'ld';
        }

        $.ajax({
            url : ajaxurl,
            type : 'POST',
            data : $formData+'&migrate_type=courses',
            beforeSend: function (XMLHttpRequest) {
                $('.migrate-now-btn').addClass('tutor-updating-message');
                $('.tutor-progress').attr('style', '--tutor-progress : 0% ').hide().attr('data-percent', 0);
                get_live_progress_course_migrating_info(final_types);
                $('#sectionCourse').find('.j-spinner').addClass('tmtl_spin');
                migration_progress_bar();
            },
            success: function (data) {
                $('#sectionCourse').find('.j-spinner').addClass('tmtl_done');

                migration_progress_bar(true);
                migrate_orders($formData, final_types);
            },
            complete: function () {
                clearTimeout(countProgress);
                clearTimeout(checkProgress);
                $('#sectionCourse').find('.j-spinner').removeClass('tmtl_spin');

                $.post( ajaxurl, {action: 'tlmt_reset_migrated_items_count'} );
            }
        });
    });

    var countOrderProgress;
    function order_migration_progress_bar(cmplete){
        var $progressBar = $('#sectionOrders').find('.tutor-progress');
        var data_parcent = parseInt($progressBar.attr('data-percent'));
        
        if (cmplete) {
            $progressBar.attr('style', '--tutor-progress : 100% ').attr('data-percent', 100);
        } else {
            data_parcent++;
            $progressBar.show().attr('style', '--tutor-progress : '+data_parcent+'% ').attr('data-percent', data_parcent);
            countOrderProgress = setTimeout(order_migration_progress_bar, 300, cmplete );
        }
    }
    function migrate_orders($formData, final_types){

        $.ajax({
            url : ajaxurl,
            type : 'POST',
            data : $formData+'&migrate_type=orders',
            beforeSend: function (XMLHttpRequest) {
                get_live_progress_course_migrating_info();
                $('#sectionOrders').find('.j-spinner').addClass('tmtl_spin');

                order_migration_progress_bar(final_types);
            },
            success: function (data) {
                $('#sectionOrders').find('.j-spinner').addClass('tmtl_done');

                order_migration_progress_bar(true);
                migrate_reviews($formData);
            },
            complete: function () {
                clearTimeout(countOrderProgress);
                clearTimeout(checkProgress);

                $('#sectionOrders').find('.j-spinner').removeClass('tmtl_spin');
                $.post( ajaxurl, {action: 'tlmt_reset_migrated_items_count'} );
            }
        });
    }


    /**
     * Migrate And Progress Reviews
     */

    var countReviewsProgress;
    function reviews_migration_progress_bar(cmplete){
        var $progressBar = $('#sectionReviews').find('.tutor-progress');
        var data_parcent = parseInt($progressBar.attr('data-percent'));

        if (cmplete) {
            $progressBar.attr('style', '--tutor-progress : 100% ').attr('data-percent', 100);
        } else {
            data_parcent++;
            $progressBar.show().attr('style', '--tutor-progress : '+data_parcent+'% ').attr('data-percent', data_parcent);
            countReviewsProgress = setTimeout(reviews_migration_progress_bar, 300, cmplete );
        }
    }
    function migrate_reviews($formData){
        $.ajax({
            url : ajaxurl,
            type : 'POST',
            data : $formData+'&migrate_type=reviews',
            beforeSend: function (XMLHttpRequest) {

                get_live_progress_course_migrating_info();
                $('#sectionReviews').find('.j-spinner').addClass('tmtl_spin');

                reviews_migration_progress_bar();
            },
            success: function (data) {
                $('#sectionReviews').find('.j-spinner').addClass('tmtl_done');
                reviews_migration_progress_bar(true);

                if (data.success){
                    $('.lp-success-modal').addClass('active');
                }

            },
            complete: function () {
                clearTimeout(countReviewsProgress);
                clearTimeout(checkProgress);
                $('.migrate-now-btn').removeClass('tutor-updating-message');
                $('#sectionReviews').find('.j-spinner').removeClass('tmtl_spin');
                $.post( ajaxurl, {action: 'tlmt_reset_migrated_items_count'} );
            }
        });
    }

    /*
    $(document).on( 'click', '#migrate_lp_courses_btn',  function( e ){
        e.preventDefault();

        var $that = $(this);
        $.ajax({
            url : ajaxurl,
            type : 'POST',
            data : {action : 'lp_migrate_course_to_tutor' },
            beforeSend: function (XMLHttpRequest) {
                $that.addClass('tutor-updating-message');
                get_live_progress_course_migrating_info();
            },
            success: function (data) {
                if (data.success) {
                    window.location.reload();
                }
            },
            complete: function () {
                $that.removeClass('tutor-updating-message');
            }
        });
    });
    */

    /**
     * Modal JS
     */




    /**
     * Modal and other JS
     * @since v.1.0.0
     */

    var migrateBtn = $(".migrate-now-btn");
    var migrateLaterBtn = $('.migration-later-btn');
    var migrateStartBtn = $('.migration-start-btn');
    var migrationModal = $('.lp-migration-modal-wrap');
    var successModal = $('.lp-success-modal');
    var errorModal = $('.lp-error-modal');
    var successModalClose = $('.modal-close.success-modal-close');
    var migrateModalClose = $('.modal-close.migration-modal-close');
    var errorModalClose = $('.lp-modal-alert .modal-close.error-modal-close');

    function activeModal(activeItem) {
        $(activeItem).addClass('active');
    }
    function removeModal(removeItem) {
        removeItem.removeClass('active');
    }

// migrate now button click
    $(migrateBtn).on('click',function(event){
        event.preventDefault();
        migrationModal.addClass('active');
    });
// migration later button click action
    $(migrateLaterBtn).on('click', function(event){
        event.preventDefault();
        removeModal(migrationModal);
    });

// migration start button click action
    $(migrateStartBtn).on('click', function(event){
        event.preventDefault();

        $(migrationModal).removeClass('active');
        if ( $('#total_items_migrate_counts').data('count') ) {
            $('#tlmt-lp-migrate-to-tutor-lms').submit();
        }
    });

    $(document).on('click', '.migration-done-btn', function(event){
        event.preventDefault();
        removeModal(successModal);
    });

// successModal close button action
    $(successModalClose).on('click', function(event){
        event.preventDefault();
        removeModal(successModal);
    });
// error modal close button click action
    $(migrateModalClose).on('click', function(event){
        event.preventDefault();
        removeModal(migrationModal);
    });
// error modal close button click action
    $(errorModalClose).on('click', function(event){
        event.preventDefault();
        removeModal(errorModal);
    });



});