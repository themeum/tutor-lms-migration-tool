jQuery(document).ready(function ($) {
    'use strict';

    $(document).on("click", ".install-tutor-button", function (t) {
        t.preventDefault();
        var select = $(this);
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: { install_plugin: "tutor", action: "install_tutor_plugin" },
            beforeSend: function () {
                select.addClass("updating-message");
            },
            success: function (t) {
                $(".install-qubely-button").remove(),
                    $("#qubely_install_msg").html(t);
            },
            complete: function () {
                select.removeClass("updating-message");
                location.reload();
            }
        });
    });

    /**
     * LP Migration
     * Since v.1.4.6
     */

    var checkProgress;

    function get_live_progress_course_migrating_info(final_types = 'lp') {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: '_get_' + final_types + '_live_progress_course_migrating_info' },
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
    function migration_progress_bar(cmplete) {
        var $progressBar = $('#sectionCourse').find('.tutor-progress');
        var data_parcent = parseInt($progressBar.attr('data-percent'));

        if (cmplete) {
            $progressBar.attr('style', '--tutor-progress : 100% ').attr('data-percent', 100);
        } else {
            data_parcent++;
            $progressBar.show().attr('style', '--tutor-progress : ' + data_parcent + '% ').attr('data-percent', data_parcent);
            countProgress = setTimeout(migration_progress_bar, 300, cmplete);
        }
    }
    var migration_vendor = 'lp';
    $(document).on('submit', 'form#tlmt-lp-migrate-to-tutor-lms', function (e) {
        e.preventDefault();

        var $that = $(this);
        var $formData = $(this).serialize() + '&action=' + $that.attr('action');

        let final_types = 'lp';
        if ($that.attr('action') == 'ld_migrate_all_data_to_tutor') {
            final_types = 'ld';
            migration_vendor = 'ld';
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $formData + '&migrate_type=courses',
            beforeSend: function (XMLHttpRequest) {
                migrateBtn.attr('disabled', 'disabled');
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
                clearTimeout(countReviewsProgress);
                clearTimeout(checkProgress);
                clearTimeout(countProgress);
                $('#sectionCourse').find('.j-spinner').removeClass('tmtl_spin');

                $.post(ajaxurl, { action: 'tlmt_reset_migrated_items_count' });
            }
        });
    });

    var countOrderProgress;
    function order_migration_progress_bar(cmplete) {
        var $progressBar = $('#sectionOrders').find('.tutor-progress');
        var data_parcent = parseInt($progressBar.attr('data-percent'));

        if (cmplete) {
            $progressBar.attr('style', '--tutor-progress : 100% ').attr('data-percent', 100);
        } else {
            data_parcent++;
            $progressBar.show().attr('style', '--tutor-progress : ' + data_parcent + '% ').attr('data-percent', data_parcent);
            countOrderProgress = setTimeout(order_migration_progress_bar, 300, cmplete);
        }
    }
    function migrate_orders($formData, final_types) {

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $formData + '&migrate_type=orders',
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
                clearTimeout(countReviewsProgress);
                clearTimeout(checkProgress);
                clearTimeout(countProgress);
                $('#sectionOrders').find('.j-spinner').removeClass('tmtl_spin');
                $.post(ajaxurl, { action: 'tlmt_reset_migrated_items_count' });
            }
        });
    }


    /**
     * Migrate And Progress Reviews
     */

    var countReviewsProgress;
    function reviews_migration_progress_bar(cmplete) {
        var $progressBar = $('#sectionReviews').find('.tutor-progress');
        var data_parcent = parseInt($progressBar.attr('data-percent'));

        if (cmplete) {
            $progressBar.attr('style', '--tutor-progress : 100% ').attr('data-percent', 100);
        } else {
            data_parcent++;
            $progressBar.show().attr('style', '--tutor-progress : ' + data_parcent + '% ').attr('data-percent', data_parcent);
            countReviewsProgress = setTimeout(reviews_migration_progress_bar, 300, cmplete);
        }
    }
    function migrate_reviews($formData) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $formData + '&migrate_type=reviews',
            beforeSend: function (XMLHttpRequest) {

                get_live_progress_course_migrating_info();
                $('#sectionReviews').find('.j-spinner').addClass('tmtl_spin');

                reviews_migration_progress_bar();
            },
            success: function (data) {
                $('#sectionReviews').find('.j-spinner').addClass('tmtl_done');
                reviews_migration_progress_bar(true);

                if (data.success) {
                    clearTimeout(countReviewsProgress);
                    clearTimeout(checkProgress);
                    clearTimeout(countProgress);
                    $.post(ajaxurl, { 
                        migration_type : 'Imported',
                        migration_vendor : migration_vendor,
                        action: 'insert_tutor_migration_data'
                    });
                    $('.lp-success-modal').addClass('active');
                }

            },
            complete: function () {
                clearTimeout(countReviewsProgress);
                clearTimeout(checkProgress);
                clearTimeout(countProgress);
                $('.migrate-now-btn').removeClass('tutor-updating-message');
                $('#sectionReviews').find('.j-spinner').removeClass('tmtl_spin');
                $.post(ajaxurl, { action: 'tlmt_reset_migrated_items_count' });
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

    var migrateBtn = $(".migrate-now-btn");
    var migrateLaterBtn = $('.migration-later-btn');
    var migrateStartBtn = $('.migration-start-btn');
    var migrationModal = $('.lp-migration-modal-wrap');
    var successModal = $('.lp-success-modal');
    var errorModal = $('.lp-error-modal');
    var successModalClose = $('.modal-close');
    var migrateModalClose = $('.modal-close.migration-modal-close');
    var errorModalClose = $('.lp-modal-alert .modal-close.error-modal-close');
    var totalItemsMigrateCounts = $('#total_items_migrate_counts').data('count');

    var tutorMigrationUploadArea = $('.tutor-migration-upload-area');

    function activeModal(activeItem) {
        $(activeItem).addClass('active');
    }
    function removeModal(removeItem) {
        removeItem.removeClass('active');
    }

    
    // migrate now button click
    $(migrateBtn).on('click', function (event) {
        event.preventDefault();
        if (totalItemsMigrateCounts > 0) {
            migrationModal.addClass('active');
        }
        //  else {
        //     if (!event.detail || event.detail == 1) {
        //         tutor_toast('Warning', 'Nothing to migrate from ' + document.querySelector('.lp-migration-heading h3').innerText.split(' ')[0], 'warning');
        //     }
        // }
    });
    
    // migrate now button click
    $(migrateStartBtn).on('click', function (event) {
        event.preventDefault();
        if (totalItemsMigrateCounts > 0) {
            migrationModal.removeClass('active');
            $('#tlmt-lp-migrate-to-tutor-lms').submit();
        }
    });


    // migration later button click action
    $(migrateLaterBtn).on('click', function (event) {
        event.preventDefault();
        removeModal(migrationModal);
    });

    // successModal close button action
    $(successModalClose).on('click', function (event) {
        event.preventDefault();
        removeModal(successModal);
        removeModal(errorModal);
    });

    $('.migration-try-again-btn').on('click', function (event) {
        event.preventDefault();
        // removeModal(successModal);
        removeModal(errorModal);
    });
    // error modal close button click action
    $(migrateModalClose).on('click', function (event) {
        event.preventDefault();
        removeModal(migrationModal);
    });
    // error modal close button click action
    $(errorModalClose).on('click', function (event) {
        event.preventDefault();
        removeModal(errorModal);
    });

    var tutorMigrationBrowseFile = $('#tutor-migration-browse-file-link');
    var tutorMigrationBrowseFileInput = $('#tutor-migration-browse-file');
    var tutorManualMigrateForm = $('#tutor-manual-migrate-form');
    var manualMigrateNowBtn = $('#manual-migrate-now-btn');

    $(document).on('click', '#tutor-migration-browse-file-link a', function(event) {
        event.preventDefault();
        $('#tutor-migration-browse-file').click();
    });
    var dropZone = $('.tutor-migration-drag-drop-zone');
    $(document).on('change', '#tutor-migration-browse-file', function(event){
        var inputEl = $('#tutor-migration-browse-file');
        if(this.files[0]) {
            manualMigrateNowBtn.removeAttr('disabled');
            getFilesAndUpdateDOM(this.files[0], inputEl);
        } else {
            manualMigrateNowBtn.attr('disabled', 'disabled');
        }
    });

    var getFilesAndUpdateDOM = (files, inputEl) => {
        if (files) {
        	inputEl.files = files;
        	dropZone.addClass('file-attached');
        	dropZone.find('.file-info').html(`File attached - ${files.name}`);
        } else {
        	dropZone.removeClass('file-attached');
        	dropZone.find('.file-info').html('');
        }
    };

    $(document).on('click', '.backup-now-btn', function(event) {
        event.preventDefault();
        $.post(ajaxurl, { 
            migration_type : 'Exported',
            migration_vendor : $('#tutor_migration_vendor').val(),
            action: 'insert_tutor_migration_data'
        });
        $('form#tutor_migration_export_form').submit();
    })

    $(document).on('click', '#manual-migrate-now-btn', function(event) {
        var fileType = $('input[name="tutor_import_file"]')[0].files[0].type;
        if(fileType != 'text/xml') {
            alert('Not supported file. Upload xml file here!');
            return;
        }
        var action_name = $('#tutor-manual-migrate-form input[name="tutor_action"]').val();
        var formData = new FormData();
        formData.append("tutor_import_file", $('input[name="tutor_import_file"]')[0].files[0]);
        formData.append("action", action_name);
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function (XMLHttpRequest) {
                manualMigrateNowBtn.attr('disabled', 'disabled');
            },
            success: function (res) {
                console.log(res);
                if (res.success) {
                    $('.lp-success-modal').addClass('active');
                    $.post(ajaxurl, { 
                        migration_type : 'Imported',
                        migration_vendor : $('#tutor_migration_vendor').val(),
                        action: 'insert_tutor_migration_data'
                    });
                    manualMigrateNowBtn.attr('disabled', 'disabled');
                    $('.tutor-migration-upload-area.file-attached').removeClass('file-attached');
                    $('.file-info').html('');
                } else {
                    manualMigrateNowBtn.removeAttr('disabled', 'disabled');
                    activeModal(errorModal);
                }
            },
        });
    });

}); /* ./ jQuery */



const dropZoneInputs = document.querySelectorAll('.tutor-migration-drag-drop-zone input[type=file]');

dropZoneInputs.forEach((inputEl) => {
	const dropZone = inputEl.closest('.tutor-migration-drag-drop-zone');

	['dragover', 'dragleave', 'dragend'].forEach((dragEvent) => {
		if (dragEvent === 'dragover') {
			dropZone.addEventListener(dragEvent, (e) => {
				e.preventDefault();
				dropZone.classList.add('dragover');
			});
		} else {
			dropZone.addEventListener(dragEvent, (e) => {
				dropZone.classList.remove('dragover');
			});
		}
	});

	dropZone.addEventListener('drop', (e) => {
		e.preventDefault();
		const files = e.dataTransfer.files;
		getFilesAndUpdateDOM(files, inputEl, dropZone);
		dropZone.classList.remove('dragover');
	});

	// inputEl.addEventListener('change', (e) => {
    //     const files = e.target.files;
	// 	getFilesAndUpdateDOM(files, inputEl, dropZone);
	// });
	
});

const getFilesAndUpdateDOM = (files, inputEl, dropZone) => {
	if (files.length) {
		inputEl.files = files;
		dropZone.classList.add('file-attached');
		dropZone.querySelector('.file-info').innerHTML = `File attached - ${files[0].name}`;
        document.querySelector('#manual-migrate-now-btn').removeAttribute('disabled');
	} else {
		dropZone.classList.remove('file-attached');
		dropZone.querySelector('.file-info').innerHTML = '';
	}
};