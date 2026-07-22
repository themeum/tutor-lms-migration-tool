const { __ } = wp.i18n;

jQuery(document).ready(function ($) {
    'use strict';
    const { __ } = wp.i18n;
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
    function handleMigrationStepFailure(data, sectionSelector) {
        clearTimeout(countReviewsProgress);
        clearTimeout(checkProgress);
        clearTimeout(countProgress);
        clearTimeout(countOrderProgress);
        clearTimeout(countSubscriptionProgress);

        if (sectionSelector) {
            $(sectionSelector).find('.j-spinner').removeClass('tmtl_spin');
        }

        $('.migrate-now-btn').removeAttr('disabled').removeClass('tutor-updating-message');
        $('.lp-error-modal').addClass('active');

        if (data && data.data && data.data.message) {
            console.error('Migration step failed:', data.data);
        }
    }

	function migration_progress_bar(cmplete, percent) {
        var $progressBar = $('#sectionCourse').find('.tutor-progress');
        if (typeof percent === 'number' && !isNaN(percent)) {
            var clamped = Math.max(0, Math.min(100, Math.round(percent)));
            $progressBar.show().attr('style', '--tutor-progress : ' + clamped + '% ').attr('data-percent', clamped);
            if (cmplete || clamped >= 100) {
                clearTimeout(countProgress);
            }
            return;
        }
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
        if ($that.attr('action') == 'lif_migrate_all_data_to_tutor') {
            final_types = 'lif';
            migration_vendor = 'lif';
        }

        function finishCourseMigrationStep() {
            clearTimeout(countReviewsProgress);
            clearTimeout(checkProgress);
            clearTimeout(countProgress);
            $('#sectionCourse').find('.j-spinner').removeClass('tmtl_spin').addClass('tmtl_done');
            migration_progress_bar(true, 100);
            $.post(ajaxurl, { action: 'tlmt_reset_migrated_items_count' });
            migrate_orders($formData, final_types);
        }

        function migrate_courses_batch(isFirstBatch) {
            var requestData = $formData + '&migrate_type=courses';
            if (isFirstBatch && final_types === 'ld') {
                requestData += '&ld_course_migration_start=1';
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: requestData,
                beforeSend: function () {
                    if (isFirstBatch) {
                        migrateBtn.attr('disabled', 'disabled');
                        $('.tutor-progress').attr('style', '--tutor-progress : 0% ').hide().attr('data-percent', 0);
                        get_live_progress_course_migrating_info(final_types);
                        $('#sectionCourse').find('.j-spinner').addClass('tmtl_spin');
                        if (final_types === 'ld') {
                            migration_progress_bar(false, 0);
                        } else {
                            migration_progress_bar();
                        }
                    }
                },
                success: function (data) {
                    if (!data || !data.success) {
                        handleMigrationStepFailure(data, '#sectionCourse');
                        return;
                    }

                    var payload = data.data || {};
                    if (final_types === 'ld' && payload.total > 0) {
                        var percent = (Number(payload.migrated) / Number(payload.total)) * 100;
                        migration_progress_bar(false, percent);
                    }

                    if (payload.has_more) {
                        migrate_courses_batch(false);
                        return;
                    }

                    finishCourseMigrationStep();
                },
                error: function (xhr) {
                    handleMigrationStepFailure(xhr.responseJSON || {}, '#sectionCourse');
                }
            });
        }

        migrate_courses_batch(true);
    });

    var countOrderProgress;
	function order_migration_progress_bar(cmplete, percent) {
        var $progressBar = $('#sectionOrders').find('.tutor-progress');
        if (typeof percent === 'number' && !isNaN(percent)) {
            var clamped = Math.max(0, Math.min(100, Math.round(percent)));
            $progressBar.show().attr('style', '--tutor-progress : ' + clamped + '% ').attr('data-percent', clamped);
            if (cmplete || clamped >= 100) {
                clearTimeout(countOrderProgress);
            }
            return;
        }
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
        function finishOrdersMigrationStep() {
            clearTimeout(countOrderProgress);
            clearTimeout(countReviewsProgress);
            clearTimeout(checkProgress);
            clearTimeout(countProgress);
            $('#sectionOrders').find('.j-spinner').removeClass('tmtl_spin').addClass('tmtl_done');
            order_migration_progress_bar(true, 100);
            $.post(ajaxurl, { action: 'tlmt_reset_migrated_items_count' });
            migrate_subscriptions($formData, final_types);
        }

        function migrate_orders_batch(isFirstBatch) {
            var requestData = $formData + '&migrate_type=orders';
            if (isFirstBatch && final_types === 'ld') {
                requestData += '&ld_order_migration_start=1';
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: requestData,
                beforeSend: function () {
                    if (isFirstBatch) {
                        get_live_progress_course_migrating_info(final_types);
                        $('#sectionOrders').find('.j-spinner').addClass('tmtl_spin');
                        if (final_types === 'ld') {
                            order_migration_progress_bar(false, 0);
                        } else {
                            order_migration_progress_bar();
                        }
                    }
                },
                success: function (data) {
                    if (!data || !data.success) {
                        handleMigrationStepFailure(data, '#sectionOrders');
                        return;
                    }

                    var payload = data.data || {};
                    if (final_types === 'ld' && payload.total > 0) {
                        var percent = (Number(payload.migrated) / Number(payload.total)) * 100;
                        order_migration_progress_bar(false, percent);
                    }

                    if (payload.has_more) {
                        migrate_orders_batch(false);
                        return;
                    }

                    finishOrdersMigrationStep();
                },
                error: function (xhr) {
                    handleMigrationStepFailure(xhr.responseJSON || {}, '#sectionOrders');
                }
            });
        }

        migrate_orders_batch(true);
    }

    var countSubscriptionProgress;
    function subscription_migration_progress_bar(cmplete, percent) {
        var $progressBar = $('#sectionSubscriptions').find('.tutor-progress');
        if (!$progressBar.length) {
            return;
        }
        if (typeof percent === 'number' && !isNaN(percent)) {
            var clamped = Math.max(0, Math.min(100, Math.round(percent)));
            $progressBar.show().attr('style', '--tutor-progress : ' + clamped + '% ').attr('data-percent', clamped);
            if (cmplete || clamped >= 100) {
                clearTimeout(countSubscriptionProgress);
            }
            return;
        }
        var data_parcent = parseInt($progressBar.attr('data-percent'));

        if (cmplete) {
            $progressBar.attr('style', '--tutor-progress : 100% ').attr('data-percent', 100);
        } else {
            data_parcent++;
            $progressBar.show().attr('style', '--tutor-progress : ' + data_parcent + '% ').attr('data-percent', data_parcent);
            countSubscriptionProgress = setTimeout(subscription_migration_progress_bar, 300, cmplete);
        }
    }

    function migrate_subscriptions($formData, final_types) {
        if (!$('#sectionSubscriptions').length) {
            migrate_reviews($formData, final_types);
            return;
        }

        final_types = final_types || 'ld';

        function finishSubscriptionsMigrationStep() {
            clearTimeout(countSubscriptionProgress);
            clearTimeout(countReviewsProgress);
            clearTimeout(checkProgress);
            clearTimeout(countProgress);
            $('#sectionSubscriptions').find('.j-spinner').removeClass('tmtl_spin').addClass('tmtl_done');
            subscription_migration_progress_bar(true, 100);
            $.post(ajaxurl, { action: 'tlmt_reset_migrated_items_count' });
            migrate_reviews($formData, final_types);
        }

        function migrate_subscriptions_batch(isFirstBatch) {
            var requestData = $formData + '&migrate_type=subscriptions';
            if (isFirstBatch && final_types === 'ld') {
                requestData += '&ld_subscription_migration_start=1';
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: requestData,
                beforeSend: function () {
                    if (isFirstBatch) {
                        get_live_progress_course_migrating_info(final_types);
                        $('#sectionSubscriptions').find('.j-spinner').addClass('tmtl_spin');
                        if (final_types === 'ld') {
                            subscription_migration_progress_bar(false, 0);
                        } else {
                            subscription_migration_progress_bar();
                        }
                    }
                },
                success: function (data) {
                    if (!data || !data.success) {
                        handleMigrationStepFailure(data, '#sectionSubscriptions');
                        return;
                    }

                    var payload = data.data || {};
                    if (final_types === 'ld' && payload.total > 0) {
                        var percent = (Number(payload.migrated) / Number(payload.total)) * 100;
                        subscription_migration_progress_bar(false, percent);
                    }

                    if (payload.has_more) {
                        migrate_subscriptions_batch(false);
                        return;
                    }

                    finishSubscriptionsMigrationStep();
                },
                error: function (xhr) {
                    handleMigrationStepFailure(xhr.responseJSON || {}, '#sectionSubscriptions');
                }
            });
        }

        migrate_subscriptions_batch(true);
    }


    /**
     * Migrate And Progress Reviews
     */
    var countReviewsProgress;
    function reviews_migration_progress_bar(cmplete, percent) {
        var $progressBar = $('#sectionReviews').find('.tutor-progress');
        if (typeof percent === 'number' && !isNaN(percent)) {
            var clamped = Math.max(0, Math.min(100, Math.round(percent)));
            $progressBar.show().attr('style', '--tutor-progress : ' + clamped + '% ').attr('data-percent', clamped);
            if (cmplete || clamped >= 100) {
                clearTimeout(countReviewsProgress);
            }
            return;
        }
        var data_parcent = parseInt($progressBar.attr('data-percent'));
        if (cmplete) {
            $progressBar.attr('style', '--tutor-progress : 100% ').attr('data-percent', 100);
        } else {
            data_parcent++;
            $progressBar.show().attr('style', '--tutor-progress : ' + data_parcent + '% ').attr('data-percent', data_parcent);
            countReviewsProgress = setTimeout(reviews_migration_progress_bar, 300, cmplete);
        }
    }

    function migrate_reviews($formData, final_types) {
        final_types = final_types || migration_vendor || 'ld';

        function finishReviewsMigrationStep(data) {
            clearTimeout(countReviewsProgress);
            clearTimeout(checkProgress);
            clearTimeout(countProgress);
            $('#sectionReviews').find('.j-spinner').removeClass('tmtl_spin').addClass('tmtl_done');
            reviews_migration_progress_bar(true, 100);
            $('.migrate-now-btn').removeClass('tutor-updating-message');
            $.post(ajaxurl, { action: 'tlmt_reset_migrated_items_count' });

            if (data && data.success) {
                $.post(ajaxurl, {
                    migration_type: 'Imported',
                    migration_vendor: migration_vendor,
                    action: 'insert_tutor_migration_data'
                });
            }

            const res = (data && data.data) || {};
            const { total_course_count = 0, failed = [] } = res;

            if (Number(total_course_count) > 0) {
                $('.lp-success-modal').addClass('active');
            } else {
                $('.lp-error-modal').addClass('active');
            }
        }

        function migrate_reviews_batch(isFirstBatch) {
            var requestData = $formData + '&migrate_type=reviews';
            if (isFirstBatch && final_types === 'ld') {
                requestData += '&ld_review_migration_start=1';
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: requestData,
                beforeSend: function () {
                    if (isFirstBatch) {
                        get_live_progress_course_migrating_info(final_types);
                        $('#sectionReviews').find('.j-spinner').addClass('tmtl_spin');
                        if (final_types === 'ld') {
                            reviews_migration_progress_bar(false, 0);
                        } else {
                            reviews_migration_progress_bar();
                        }
                    }
                },
                success: function (data) {
                    if (!data || !data.success) {
                        handleMigrationStepFailure(data, '#sectionReviews');
                        return;
                    }

                    var payload = data.data || {};
                    if (final_types === 'ld' && payload.total > 0) {
                        var percent = (Number(payload.migrated) / Number(payload.total)) * 100;
                        reviews_migration_progress_bar(false, percent);
                    }

                    if (payload.has_more) {
                        migrate_reviews_batch(false);
                        return;
                    }

                    finishReviewsMigrationStep(data);
                },
                error: function (xhr) {
                    handleMigrationStepFailure(xhr.responseJSON || {}, '#sectionReviews');
                }
            });
        }

        migrate_reviews_batch(true);
    }

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

    $(document).on('click', '#tutor-migration-browse-file-link a', function (event) {
        event.preventDefault();
        $('#tutor-migration-browse-file').click();
    });
    var dropZone = $('.tutor-migration-drag-drop-zone');
    $(document).on('change', '#tutor-migration-browse-file', function (event) {
        var inputEl = $('#tutor-migration-browse-file');
        if (this.files[0]) {
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

    $(document).on('click', '.backup-now-btn', function (event) {
        event.preventDefault();
        let button = $(this);
        let form = button.closest('form#tutor_migration_export_form');

        $.post(ajaxurl, {
            migration_type: 'Exported',
            migration_vendor: form.children("#tutor_migration_vendor").val(),
            action: 'insert_tutor_migration_data'
        });
        form.submit();
    })

    $(document).on('click', '#manual-migrate-now-btn', function (event) {
        let button = $(this);
        var fileType = $('input[name="tutor_import_file"]')[0].files[0].type;
        if (fileType != 'text/xml') {
            alert('Not supported file. Upload xml file here!');
            return;
        }
        var action_name = $('#tutor-manual-migrate-form input[name="tutor_action"]').val();
        let tutor_nonce = $("#tutor-manual-migrate-form input[name='_tutor_nonce']").val();
        let http_referer = $("#tutor-manual-migrate-form input[name='_wp_http_referer']").val();

        var formData = new FormData();
        formData.append("tutor_import_file", $('input[name="tutor_import_file"]')[0].files[0]);
        formData.append("action", action_name);
        formData.append("_tutor_nonce", tutor_nonce);
        formData.append("_wp_http_referer", http_referer);
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
                if (res.success) {
                    $('.lp-success-modal').addClass('active');
                    $.post(ajaxurl, {
                        migration_type: 'Imported',
                        migration_vendor: $('#tutor_migration_vendor').val(),
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


    /**
     * 
     * @param {string} addonBaseName 
     * @returns boolean
     */

    function isAddonEnabled(addonBaseName) {
        return !!window._tutorobject?.addons_data?.find(
            (addon) => addon.base_name === addonBaseName && addon.is_enabled
        );
    }


    /**
     * WooCommerce Migration Block
     * 
     * @author Themeum <support@themeum.com>
     * @link https://themeum.com
     * @since 2.4.0
     */

    // WooCommerce Migration Configuration
    const WOO_CONFIG = {
        TABS: {
            CUSTOM: 'tutor-wc-custom-migrate-tab',
            AUTO: 'tutor-wc-auto-migrate-tab'
        },
        SELECTORS: {
            migrationPage: '.tutor-migration-page-wc',
            migrateBtn: '.migrate-now-btn-wc',
            migrationStartBtn: '#migration-start-btn-wc',
            navLink: '.tutor-nav-link',
            activeNavLink: '.tutor-nav-link.is-active',
            form: '#wc-sales-data-migration-form'
        },
        SECTION_MAP: {
            orders: "#sectionOrders .j-spinner",
            coupons: "#sectionCoupons .j-spinner",
            subscriptions: "#sectionSubscriptions .j-spinner"
        },
        CHECKBOX_CONFIGS: [
            { id: 'woo-orders', name: 'job_requirements[]', value: 'orders' },
            { id: 'woo-coupons', name: 'job_requirements[]', value: 'coupons' },
            { id: 'woo-subscriptions', name: 'job_requirements[]', value: 'subscriptions' }
        ],
        WOO_SUBSCRIPTIONS_ADDON_BASE_NAME: 'wc-subscriptions',
        WOO_MIGRATION_SUCCESS_TITLE: '[data-woo-migration-success-title]',
        WOO_MIGRATION_SUCCESS_DESC: '[data-woo-migration-success-desc]',
        WOO_REPORT_ITEMS: {
            SUCCESS: '[data-woo-migration-report-item="success"]',
            FAILED: '[data-woo-migration-report-item="failed"]',
        },
        WOO_REPORT_DESCRIPTIONS: {
            SUCCESS: '[data-woo-migration-report="success"]',
            FAILED: '[data-woo-migration-report="failed"]',
        },
        WOO_REPORT_DETAILS: '.migration-complete-report-details',
        WOO_REPORT_DETAILS_TOGGLE: '[data-report-details-toggle]',
    };

    const $wooMigrationPage = $(WOO_CONFIG.SELECTORS.migrationPage);
    const $wooMigrateBtn = $wooMigrationPage.find(WOO_CONFIG.SELECTORS.migrateBtn);
    const $wooCheckboxes = $wooMigrationPage.find(`#${WOO_CONFIG.TABS.CUSTOM} input[type="checkbox"]`);

    $wooMigrateBtn.on('click', function (event) {
        event.preventDefault();

        migrationModal.addClass('active');
    });

    $(WOO_CONFIG.SELECTORS.migrationStartBtn).on('click', function (event) {
        event.preventDefault();

        migrationModal.removeClass('active');
        $(WOO_CONFIG.SELECTORS.form).submit();
    });



    // Store original checkbox state
    let wooOriginalCheckboxes = [];

    function getWooActiveTab() {
        return $wooMigrationPage.find(WOO_CONFIG.SELECTORS.activeNavLink).data('tutorNavTarget');
    }

    function toggleWooMigrateBtn() {
        setTimeout(function () {
            const isCustomTab = getWooActiveTab() === WOO_CONFIG.TABS.CUSTOM;
            const shouldDisable = isCustomTab && !$wooCheckboxes.is(':checked');
            $wooMigrateBtn.prop('disabled', shouldDisable);
        }, 0);
    }

    function toggleWooSpinners(tab, mode) {
        $(`#${tab}`).find('span.j-spinner').each(function () {
            const $spinner = $(this);
            $spinner.removeClass('tmtl_spin tmtl_done');

            if (mode === 'spin') {
                $spinner.addClass('tmtl_spin');
            } else if (mode === 'done') {
                $spinner.addClass('tmtl_done');
            }
        });
    }

    function storeWooCheckboxState() {
        const $checkboxes = $(`#${WOO_CONFIG.TABS.CUSTOM}`).find('input[type="checkbox"]');
        wooOriginalCheckboxes = [];

        $checkboxes.each(function () {
            const $checkbox = $(this);
            wooOriginalCheckboxes.push({
                id: $checkbox.attr('id'),
                name: $checkbox.attr('name'),
                value: $checkbox.attr('value'),
                class: $checkbox.attr('class'),
                checked: $checkbox.is(':checked')
            });
        });
    }

    function replaceWooCheckboxesWithSpinners() {
        storeWooCheckboxState();

        const $checkedCheckboxes = $(`#${WOO_CONFIG.TABS.CUSTOM}`).find('input[type="checkbox"]:checked');
        $checkedCheckboxes.each(function () {
            const inputId = $(this).attr('id');
            $(this).replaceWith(
                `<span id="spinner-${inputId}" class="j-spinner tmtl_spin" data-original-id="${inputId}"></span>`
            );
        });
        const $uncheckedCheckboxes = $(`#${WOO_CONFIG.TABS.CUSTOM}`).find('input[type="checkbox"]:not(:checked)');
        $uncheckedCheckboxes.each(function () {
            $(this).attr('disabled', 'disabled');
        });
    }

    function revertWooCheckboxes() {
        $(`#${WOO_CONFIG.TABS.CUSTOM}`).find('span.j-spinner').remove();
        $(`#${WOO_CONFIG.TABS.CUSTOM}`).find('input[type="checkbox"]:not(:checked):is([disabled])').remove();

        for (const config of WOO_CONFIG.CHECKBOX_CONFIGS) {
            const checkboxHtml = `
            <input 
                id="${config.id}" 
                type="checkbox" 
                name="${config.name}" 
                value="${config.value}" 
                class="tutor-form-check-input lp-migration-singlebox-checkbox"
            >`;

            $(`label[for="${config.id}"]`)
                .closest('.tutor-form-check')
                .prepend(checkboxHtml);
        };

        toggleWooMigrateBtn();
    }

    function updateWooProgressSpinners(response) {
        if (!response.requirements) return;

        const activeTab = getWooActiveTab();

        for (const [key, requirement] of Object.entries(response.requirements)) {
            const $spinner = $(`#${activeTab} ${WOO_CONFIG.SECTION_MAP[key]}`);

            if (!$spinner.length) {
                return;
            }

            $spinner.toggleClass('tmtl_spin', !requirement.is_done)
                .toggleClass('tmtl_done', requirement.is_done);
        };
    }

    /**
     * Generate progress message
     *
     * @since 2.4.0
     * @param {Object} optionsValue - Migration options data.
     * @param {'succeed' | 'failed'} type - Type of progress to report.
     * @return {string}
     */
    function generateProgressMessage(optionsValue, type = 'succeed') {
        if (!optionsValue?.requirements || typeof optionsValue.requirements !== 'object') {
            return '';
        }

        const messageParts = [];

        for (const [key, requirement] of Object.entries(optionsValue.requirements)) {
            if (Array.isArray(requirement[type])) {
                const count = requirement[type].length;
                const capitalizedKey = key.charAt(0).toUpperCase() + key.slice(1).toLowerCase();

                if (count > 0) {
                    messageParts.push(`${capitalizedKey} (${count})`);
                }
            }
        }

        return messageParts.join(', ');
    }

    /**
     * Generate Error Report Details
     * 
     * @param {Object} optionsValue - Migration options data.
     * 
     * @return {string}
     */
    function generateErrorReportDetails(optionsValue) {
        const reportDetails = [];
        for (const [key, requirement] of Object.entries(optionsValue?.requirements)) {
            if (Array.isArray(requirement.failed)) {
                if (requirement.failed.length === 0) continue;

                const failedIds = requirement.failed.map((failedId) => `<div class="migration-complete-report-details-ids-item">${failedId}</div>`).join('');
                reportDetails.push(`
                    <div class="migration-complete-report-details-item">
                        <div class="migration-complete-report-details-title">
                            ${key.charAt(0).toUpperCase() + key.slice(1).toLowerCase()}
                        </div>
                        <div class="migration-complete-report-details-ids">
                            ${failedIds}
                        </div>
                    </div>
                `);
            }
        }
        return reportDetails.join('');
    }

    function handleWooMigrationSuccess(response) {
        const succeedReport = generateProgressMessage(response, 'succeed');
        const failedReport = generateProgressMessage(response, 'failed');
        const activeTab = getWooActiveTab();

        const hasSuccess = succeedReport.length > 0;
        const hasFailed = failedReport.length > 0;

        $(hasSuccess ? '.lp-success-modal' : '.lp-error-modal').addClass('active');

        const [title, description] = hasSuccess && hasFailed
            ? [__('Migration Complete with Errors', 'tutor-lms-migration-tool'), __('The migration process has finished, but some items could not be imported. ', 'tutor-lms-migration-tool')]
            : [__('Migration Successful!', 'tutor-lms-migration-tool'), __('Your data has been successfully migrated from WooCommerce to Tutor LMS native eCommerce.', 'tutor-lms-migration-tool')];

        $(WOO_CONFIG.WOO_MIGRATION_SUCCESS_TITLE).text(__(title, 'tutor-lms-migration-tool'));
        $(WOO_CONFIG.WOO_MIGRATION_SUCCESS_DESC).text(__(description, 'tutor-lms-migration-tool'));

        $wooMigrateBtn.removeAttr('disabled');
        window.onbeforeunload = null;
        $('.tutor-migration-tab .tutor-nav-link').removeClass('disabled');
        toggleWooSpinners(activeTab, 'stop');

        if (activeTab === WOO_CONFIG.TABS.CUSTOM) {
            revertWooCheckboxes();
        }

        $(WOO_CONFIG.WOO_REPORT_ITEMS.SUCCESS).toggle(hasSuccess);
        $(WOO_CONFIG.WOO_REPORT_ITEMS.FAILED).toggle(hasFailed);

        if (hasSuccess) {
            $(WOO_CONFIG.WOO_REPORT_DESCRIPTIONS.SUCCESS).text(succeedReport);
        }

        if (hasFailed) {
            $(WOO_CONFIG.WOO_REPORT_DESCRIPTIONS.FAILED).text(failedReport);
            $(WOO_CONFIG.WOO_REPORT_DETAILS).html(generateErrorReportDetails(response));
        }

        getWooMigrationHistory();
    }

    function handleWooMigrationError() {
        $('.lp-error-modal').addClass('active');
        const activeTab = getWooActiveTab();
        toggleWooSpinners(activeTab, 'stop');
        $('.tutor-migration-tab .tutor-nav-link')
            .removeClass('disabled');
        $wooMigrateBtn.removeAttr('disabled');
        window.onbeforeunload = null;

        if (activeTab === WOO_CONFIG.TABS.CUSTOM) {
            revertWooCheckboxes();
        }
    }

    function pollWooMigrationProgress(jobId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tlmt_migrate_sales_data',
                job_id: jobId
            },
            success: function (data) {
                const activeTab = getWooActiveTab();
                const response = data.data;

                if (!response) {
                    console.error("Invalid WooCommerce migration progress response", response);
                    return;
                }

                updateWooProgressSpinners(response);

                const progress = parseInt(response.progress, 10);

                if (progress < 100) {
                    pollWooMigrationProgress(jobId);
                } else {
                    handleWooMigrationSuccess(response);
                }
            },
            error: function (xhr, status, error) {
                console.error("WooCommerce migration polling failed", { status, error, response: xhr.responseText });
                handleWooMigrationError();
                throw new Error("WooCommerce migration polling failed");
            }
        });
    }

    function handleWooMigrationStart() {
        $wooMigrateBtn.attr('disabled', 'disabled');
        $('.tutor-migration-tab .tutor-nav-link')
            .addClass('disabled');

        if (getWooActiveTab() === WOO_CONFIG.TABS.CUSTOM) {
            replaceWooCheckboxesWithSpinners();
        } else {
            toggleWooSpinners(WOO_CONFIG.TABS.AUTO, 'spin');
        }

        window.onbeforeunload = function () {
            return 'Migration is in progress. Are you sure you want to leave?';
        };
    }

    function prepareWooFormData(form) {
        const formData = new FormData(form);
        formData.append('action', 'tlmt_migrate_sales_data');
        formData.append('job_id', 0);

        if (getWooActiveTab() !== WOO_CONFIG.TABS.CUSTOM) {
            formData.delete('job_requirements[]');
            formData.append('job_requirements[]', 'orders');
            formData.append('job_requirements[]', 'coupons');
            if (isAddonEnabled(WOO_CONFIG.WOO_SUBSCRIPTIONS_ADDON_BASE_NAME)) {
                formData.append('job_requirements[]', 'subscriptions');
            }
        }

        return formData;
    }

    function getWooMigrationHistory() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tmlt_get_sales_data_history',
            },
            success: function (data) {
                const history = data.data;
                const $migrationHistory = $('.tutor-migration-history');

                if (!history.length) {
                    $migrationHistory.addClass('tutor-d-none');
                    return;
                }

                $migrationHistory.removeClass('tutor-d-none');

                const $tbody = $('.tutor-migration-history tbody')
                $tbody.empty();

                history.forEach((item) => {
                    const $row = $(`
                        <tr class="tutor-wc-migration-history-row">
                            <td>
                                <div class="tutor-migration-history-time tutor-fs-7 tutor-pl-24 tutor-fw-normal">
                                    ${item.title}
                                </div>
                            </td>
                            <td>
                                <div class="tutor-migration-history-time tutor-fs-7 tutor-pl-24 tutor-fw-normal">
                                    ${item.started_at}
                                </div>
                            </td>
                            <td>
                                <div class="tutor-btn tutor-btn-outline-primary tutor-btn-sm tutor-mr-4 tutor-wc-history-delete-btn"
                                    data-wc-option-id="${item.id}">
                                    Delete
                                </div>
                            </td>
                        </tr>
                    `);
                    $tbody.append($row);
                });
            },
            error: function (xhr, status, error) {
                console.error("Failed to get WooCommerce migration history", { status, error, response: xhr.responseText });
                throw new Error("Failed to get WooCommerce migration history");
            }
        });
    }

    // WooCommerce Migration Event Handlers
    $wooMigrationPage.find(WOO_CONFIG.SELECTORS.navLink).on('click', function (e) {
        e.preventDefault();
        toggleWooMigrateBtn();
    });

    $(WOO_CONFIG.WOO_REPORT_DETAILS_TOGGLE).on('click', function (e) {
        e.preventDefault();
        $(WOO_CONFIG.WOO_REPORT_DETAILS).toggleClass('tutor-d-none');
    });

    $wooCheckboxes.on('change', toggleWooMigrateBtn);

    $(document).on('submit', WOO_CONFIG.SELECTORS.form, function (event) {
        event.preventDefault();

        const formData = prepareWooFormData(this);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: handleWooMigrationStart,
            success: function (data) {
                const activeTab = getWooActiveTab();
                const response = data.data;
                if (response && response.job_id && response.progress < 100) {
                    pollWooMigrationProgress(response.job_id);
                    return;
                }

                handleWooMigrationSuccess(response);
            },
            error: handleWooMigrationError,
        });
    });

    // Delete history
    $(document).on('click', '.tutor-wc-history-delete-btn', function (event) {
        event.preventDefault();

        const $btn = $(this);
        const optionId = $btn.data('wc-option-id');
        if (!optionId) return;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tlmt_delete_sales_data_history',
                option_id: optionId,
            },
            beforeSend: function () {
                $btn.addClass('is-loading');
                $btn.prop('disabled', true);
                $btn.text('');
            },
            success: function (data) {
                if (data.status_code === 200) {
                    $btn.closest('tr').remove();
                } else {
                    $btn.removeClass('is-loading').prop('disabled', false).text('Delete');
                }
                getWooMigrationHistory();
            },
            error: function (xhr, status, error) {
                console.error('Failed to delete history', { status, error, response: xhr.responseText });
                $btn.removeClass('is-loading').prop('disabled', false).text('Delete');
            },
        });
    });


    // Initialize WooCommerce migration
    toggleWooMigrateBtn();

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
