jQuery(document).ready(function($){
    'use strict';

    /**
     * LP Migration
     * Since v.1.4.6
     */

    var checkProgress;



    function get_live_progress_course_migrating_info(){
        $.ajax({
            url : ajaxurl,
            type : 'POST',
            data : {action : '_get_lp_live_progress_course_migrating_info' },
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


    $(document).on( 'submit', 'form#tlmt-lp-migrate-to-tutor-lms',  function( e ){
        e.preventDefault();

        var $that = $(this);
        var data = $(this).serialize()+'&action=lp_migrate_all_data_to_tutor';

        $.ajax({
            url : ajaxurl,
            type : 'POST',
            data : data,
            beforeSend: function (XMLHttpRequest) {
                $that.find('.migrate-now-btn').addClass('tutor-updating-message');
                get_live_progress_course_migrating_info();
            },
            success: function (data) {
                if (data.success) {

                }
            },
            complete: function () {
                clearTimeout(checkProgress);
                $that.find('.migrate-now-btn').removeClass('tutor-updating-message');
                $.post( ajaxurl, {action: 'tlmt_reset_migrated_items_count'} );
            }
        });
    });


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




});
