(function($){
    'use strict';

    const EstateOfficeAdmin = {
        init() {
            this.handleMultiSteps();
            this.handleEndDateToggle();
            this.handlePricePerSqm();
            this.handleDynamicFields();
            this.handleTabs();
            this.handleStageUpdate();
            this.handleSearchFilters();
            this.handleClientTypeSwitch();
            this.handlePropertyTypeVisibility();
            this.handleExistingClientToggle();
        },

        handleMultiSteps() {
            $('.estate-office-form').each(function(){
                const $form = $(this);
                const $steps = $form.find('.estate-office-form-step');
                const $indicators = $form.find('.estate-office-step');

                function showStep(index) {
                    $steps.removeClass('is-active').eq(index).addClass('is-active');
                    $indicators.removeClass('is-active').eq(index).addClass('is-active');
                    $form.attr('data-current-step', index);
                }

                $form.on('click', '[data-action="next-step"]', function(e){
                    e.preventDefault();
                    const current = parseInt($form.attr('data-current-step') || 0, 10);
                    if (current < $steps.length - 1) {
                        showStep(current + 1);
                    } else {
                        $form.trigger('submit');
                    }
                });

                $form.on('click', '[data-action="prev-step"]', function(e){
                    e.preventDefault();
                    const current = parseInt($form.attr('data-current-step') || 0, 10);
                    if (current > 0) {
                        showStep(current - 1);
                    }
                });

                showStep(0);
            });
        },

        handleEndDateToggle() {
            $(document).on('change', '[data-toggle="open-ended"]', function(){
                const checked = $(this).is(':checked');
                const selector = $(this).data('target');
                if (selector) {
                    $(selector).prop('disabled', checked).toggleClass('is-disabled', checked);
                }
            });
        },

        handlePricePerSqm() {
            $(document).on('input', '[data-calc="price"], [data-calc="area"]', function(){
                const $form = $(this).closest('form');
                const price = parseFloat($form.find('[data-calc="price"]').val().replace(',', '.')) || 0;
                const area  = parseFloat($form.find('[data-calc="area"]').val().replace(',', '.')) || 0;
                const $target = $form.find('[data-calc="price-per-sqm"]');

                if ($target.length) {
                    const value = area > 0 ? (price / area).toFixed(2) : '';
                    $target.val(value);
                }
            });
        },

        handleDynamicFields() {
            $('.estate-office-dynamic-fields').each(function(){
                const $container = $(this);
                const template = $container.find('template')[0];

                $container.on('click', '[data-action="add-field"]', function(e){
                    e.preventDefault();
                    if (!template) {
                        return;
                    }
                    const clone = document.importNode(template.content, true);
                    $container.find('.estate-office-dynamic-field-list').append(clone);
                });

                $container.on('click', '[data-action="remove-field"]', function(e){
                    e.preventDefault();
                    const $field = $(this).closest('.estate-office-dynamic-field');
                    if (window.confirm(estateOfficeSettings.i18n.confirmRemove)) {
                        $field.remove();
                    }
                });

                $container.closest('form').on('submit', function(){
                    const fields = [];
                    $container.find('.estate-office-dynamic-field').each(function(){
                        const $field = $(this);
                        const key = $field.find('[data-field="key"]').val();
                        const label = $field.find('[data-field="label"]').val();
                        const type = $field.find('[data-field="type"]').val();
                        if (key && label) {
                            fields.push({ key, label, type });
                        }
                    });
                    $container.find('input[type="hidden"]').val(JSON.stringify(fields));
                });
            });
        },

        handleTabs() {
            $(document).on('click', '.estate-office-crm-tab', function(){
                const target = $(this).data('target');
                $(this).addClass('is-active').siblings().removeClass('is-active');
                $('.estate-office-crm-panel').removeClass('is-active');
                $(target).addClass('is-active');
            });
        },

        handleStageUpdate() {
            $(document).on('submit', '.estate-office-stage-form', function(e){
                e.preventDefault();
                const $form = $(this);
                const agreementId = $form.data('agreement-id');
                const stage = $form.find('[name="stage"]').val();
                const date = $form.find('[name="stage_date"]').val();
                const $history = $form.closest('.estate-office-stage').find('.estate-office-stage-history ul');

                if (!agreementId) {
                    return;
                }

                wp.apiFetch({
                    path: `estate-office/v1/agreements/${agreementId}/stages`,
                    method: 'POST',
                    data: { stage, date },
                    headers: { 'X-WP-Nonce': estateOfficeSettings.nonce }
                }).then((response) => {
                    if (response && response.history) {
                        $history.empty();
                        response.history.forEach(item => {
                            const li = `<li><strong>${item.stage}</strong> — ${item.date}</li>`;
                            $history.append(li);
                        });
                    }
                }).catch((error) => {
                    window.console.error('EstateOffice stage update error', error);
                });
            });
        },

        handleSearchFilters() {
            $(document).on('input', '[data-action="filter-table"]', function(){
                const value = $(this).val().toLowerCase();
                const target = $(this).data('target');
                const $rows = $(target).find('tbody tr');

                $rows.each(function(){
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(value) !== -1);
                });
            });
        },

        handleClientTypeSwitch() {
            $(document).on('change', '[data-client-type]', function(){
                const type = $(this).val();
                $('[data-client-fields]').hide();
                $(`[data-client-fields="${type}"]`).show();
            });
            $('[data-client-type]').trigger('change');
        },

        handleExistingClientToggle() {
            $(document).on('change', '#existing_client_id', function(){
                const hasExisting = parseInt($(this).val(), 10) > 0;
                const $form = $(this).closest('form');
                const $fields = $form.find('[name="first_name"], [name="last_name"], [name="company_name"], [name="representative"], [name="phone"], [name="email"], [name="website"], textarea');
                $fields.prop('disabled', hasExisting);
                $fields.toggleClass('is-disabled', hasExisting);
            });
            $('#existing_client_id').trigger('change');
        },

        handlePropertyTypeVisibility() {
            $(document).on('change', '[data-property-type]', function(){
                const value = $(this).val();
                $('[data-property-type-visible]').each(function(){
                    const $field = $(this);
                    const allowed = ($field.data('property-type-visible') || '').split(',');
                    if (! $field.data('property-type-visible')) {
                        return;
                    }
                    if (allowed.includes(value)) {
                        $field.show();
                    } else {
                        $field.hide();
                    }
                });
            });
            $('[data-property-type]').trigger('change');
        }
    };

    $(function(){
        EstateOfficeAdmin.init();
    });

})(jQuery);
