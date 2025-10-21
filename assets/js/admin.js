(function($){
    'use strict';

    const EstateOfficeAdmin = {
        init() {
            this.setupMediaFields();
            this.setupDynamicFields();
            this.setupContractForm();
            this.setupPropertyForm();
            this.setupClientForm();
            this.setupAddressToggle();
            this.setupStageHistory();
        },

        setupMediaFields() {
            const frameCache = {};

            $(document).on('click', '.estate-office-media-select', function(e){
                e.preventDefault();
                const $field = $(this).closest('.estate-office-media-field');
                const target = $field.data('target');
                const $input = $field.find('input[type="hidden"][name="' + target + '"]');
                const frameKey = 'estate-office-' + target;

                if ( frameCache[frameKey] ) {
                    frameCache[frameKey].open();
                    return;
                }

                const frame = wp.media({
                    title: EstateOfficeData ? EstateOfficeData.mediaTitle || 'Wybierz plik' : 'Wybierz plik',
                    button: { text: EstateOfficeData ? EstateOfficeData.mediaButton || 'Użyj pliku' : 'Użyj pliku' },
                    multiple: false
                });

                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.id).trigger('change');
                    $field.find('.estate-office-media-preview-wrap').html('<img src="' + attachment.sizes.thumbnail.url + '" alt="" />');
                    $field.find('.estate-office-media-remove').prop('disabled', false);
                });

                frameCache[frameKey] = frame;
                frame.open();
            });

            $(document).on('click', '.estate-office-media-remove', function(e){
                e.preventDefault();
                const $field = $(this).closest('.estate-office-media-field');
                const target = $field.data('target');
                $field.find('input[name="' + target + '"]').val('');
                $field.find('.estate-office-media-preview-wrap').html('<span class="placeholder">' + ($(this).data('placeholder') || 'Brak podglądu') + '</span>');
                $(this).prop('disabled', true);
            });
        },

        setupDynamicFields() {
            $('.estate-office-add-field').on('click', function(){
                const group = $(this).data('group');
                const $table = $('.estate-office-dynamic-group[data-group="' + group + '"]').find('tbody');
                const template = wp.template('estate-office-dynamic-row');
                const index = $table.find('tr').length;
                const html = template({ index: index, group: group });
                if ( $table.find('.no-items').length ) {
                    $table.empty();
                }
                $table.append(html);
            });

            $(document).on('click', '.estate-office-remove-field', function(){
                const $table = $(this).closest('tbody');
                $(this).closest('tr').remove();
                if ( ! $table.find('tr').length ) {
                    $table.html('<tr class="no-items"><td colspan="6">' + (EstateOfficeData.emptyFields || 'Brak zdefiniowanych pól.') + '</td></tr>');
                }
            });
        },

        setupContractForm() {
            const $form = $('.estate-office-contract-form');
            if ( ! $form.length ) {
                return;
            }

            const toggleSections = () => {
                const type = $('#transaction_type').val();
                const propertyRequired = ['SPRZEDAŻ', 'WYNAJEM'].includes(type);
                const searchRequired = ['KUPNO', 'NAJEM'].includes(type);

                $('.estate-office-property').toggle(propertyRequired);
                $('.estate-office-search').toggle(searchRequired);
            };

            $('#transaction_type').on('change', toggleSections);
            toggleSections();

            const toggleEndDate = () => {
                const indefinite = $('input[name="indefinite"]').is(':checked');
                const $endDate = $('#end_date');
                if ( indefinite ) {
                    $endDate.prop('disabled', true).val('');
                } else {
                    $endDate.prop('disabled', false);
                }
            };
            $('input[name="indefinite"]').on('change', toggleEndDate);
            toggleEndDate();

            $form.on('submit', function(){
                const stageData = [];
                $('.estate-office-stage-table tbody tr').each(function(){
                    const $row = $(this);
                    const stage = $row.find('select').val();
                    const date = $row.find('input[type="date"]').val();
                    if ( stage ) {
                        stageData.push({ stage: stage, date: date });
                    }
                });
                $form.find('.estate-office-stage-history-json').val(JSON.stringify(stageData));
            });
        },

        setupStageHistory() {
            $(document).on('click', '.estate-office-add-stage', function(){
                const $table = $('.estate-office-stage-table tbody');
                const template = wp.template($(this).data('template'));
                const index = $table.find('tr').length;
                const html = template({ index: index });
                if ( $table.find('.no-items').length ) {
                    $table.empty();
                }
                $table.append(html);
            });

            $(document).on('click', '.estate-office-remove-row', function(){
                const $table = $(this).closest('tbody');
                $(this).closest('tr').remove();
                if ( ! $table.find('tr').length ) {
                    $table.html('<tr class="no-items"><td colspan="3">' + (EstateOfficeData.emptyStages || 'Brak historii etapów.') + '</td></tr>');
                }
            });
        },

        setupPropertyForm() {
            const toggleByType = (selector, type) => {
                const value = type || $(selector).val();
                $('[data-property-types]').each(function(){
                    const allowed = ($(this).data('property-types') + '').split(',');
                    const show = allowed.includes(value) || allowed.includes(value?.toUpperCase());
                    $(this).toggle(show);
                });
            };

            const $propertyType = $('#property_type');
            const $propertyTypeBasic = $('#property_type_basic');
            if ( $propertyType.length ) {
                $propertyType.on('change', function(){
                    toggleByType('#property_type', $(this).val());
                });
                toggleByType('#property_type', $propertyType.val());
            }
            if ( $propertyTypeBasic.length ) {
                $propertyTypeBasic.on('change', function(){
                    toggleByType('#property_type_basic', $(this).val());
                });
                toggleByType('#property_type_basic', $propertyTypeBasic.val());
            }

            const computePriceM2 = (priceSelector, areaSelector, targetSelector) => {
                const price = parseFloat($(priceSelector).val());
                const area = parseFloat($(areaSelector).val());
                if ( price > 0 && area > 0 ) {
                    $(targetSelector).val((price / area).toFixed(2));
                }
            };

            $('#property_price, #property_area').on('input', () => computePriceM2('#property_price', '#property_area', '#property_price_m2'));
            $('#property_price_basic, #property_area_basic').on('input', () => computePriceM2('#property_price_basic', '#property_area_basic', '#property_price_basic_m2'));

            $('#property_plot_shape').on('change', function(){
                const shape = $(this).val();
                $('[data-plot-shape]').each(function(){
                    const allowed = $(this).data('plot-shape');
                    $(this).toggle(allowed === shape);
                });
            }).trigger('change');
        },

        setupClientForm() {
            const $form = $('.estate-office-client-form');
            if ( ! $form.length ) {
                return;
            }
            const toggleClientSections = () => {
                const type = $form.find('input[name="client_type"]:checked').val();
                $form.find('[data-section]').each(function(){
                    const section = $(this).data('section');
                    const show = ! section || section === type;
                    $(this).toggle(show);
                });
            };
            $form.on('change', 'input[name="client_type"]', toggleClientSections);
            toggleClientSections();
        },

        setupAddressToggle() {
            const selector = '.estate-office-address input[type="checkbox"][name$="[same]"]';
            $(document).on('change', selector, function(){
                const $fieldset = $(this).closest('.estate-office-address');
                const checked = $(this).is(':checked');
                $fieldset.find('input[type="text"]').not(this).prop('disabled', checked);
            });

            $(selector).each(function(){
                const $checkbox = $(this);
                const event = $.Event('change');
                $checkbox.trigger(event);
            });
        }
    };

    $(document).ready(() => {
        EstateOfficeAdmin.init();
    });
})(jQuery);
