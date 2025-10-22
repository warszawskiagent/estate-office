(function($){
    'use strict';

    const EstateOfficeAdmin = {
        init() {
            this.setupMediaFields();
            this.setupGalleryFields();
            this.setupDynamicFields();
            this.setupContractForm();
            this.setupPropertyForm();
            this.setupTransactionMirrors();
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
                    let preview = attachment.url;
                    if ( attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) {
                        preview = attachment.sizes.thumbnail.url;
                    }
                    $field.find('.estate-office-media-preview-wrap').html('<img src="' + preview + '" alt="" />');
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

        setupGalleryFields() {
            const cache = {};

            $(document).on('click', '.estate-office-gallery-select', function(e){
                e.preventDefault();
                const $container = $(this).closest('.estate-office-gallery');
                const target = $container.data('target');
                const frameKey = 'estate-office-gallery-' + target;

                if ( cache[frameKey] ) {
                    cache[frameKey].open();
                    return;
                }

                const frame = wp.media({
                    title: EstateOfficeData ? EstateOfficeData.galleryTitle || 'Wybierz zdjęcia' : 'Wybierz zdjęcia',
                    button: { text: EstateOfficeData ? EstateOfficeData.galleryButton || 'Dodaj zdjęcia' : 'Dodaj zdjęcia' },
                    multiple: true
                });

                const appendAttachment = (attachment) => {
                    const $list = $container.find('.estate-office-gallery-list');
                    let preview = attachment.url;
                    if ( attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) {
                        preview = attachment.sizes.thumbnail.url;
                    }
                    const html = '<li>' +
                        '<div class="estate-office-gallery-thumb"><img src="' + preview + '" alt="" /></div>' +
                        '<input type="hidden" name="' + target + '[]" value="' + attachment.id + '" />' +
                        '<button type="button" class="button-link estate-office-gallery-remove">' + ((EstateOfficeData && EstateOfficeData.removeImage) || 'Usuń') + '</button>' +
                    '</li>';
                    $list.append(html);
                    $container.find('.estate-office-gallery-empty').hide();
                };

                frame.on('select', () => {
                    const selection = frame.state().get('selection');
                    selection.each((model) => appendAttachment(model.toJSON()));
                });

                cache[frameKey] = frame;
                frame.open();
            });

            $(document).on('click', '.estate-office-gallery-remove', function(e){
                e.preventDefault();
                const $item = $(this).closest('li');
                const $list = $item.closest('.estate-office-gallery-list');
                $item.remove();
                if ( ! $list.find('li').length ) {
                    $list.closest('.estate-office-gallery').find('.estate-office-gallery-empty').show();
                }
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

            const mirrorEmbeddedTransaction = (type) => {
                $form.find('input[name="property[transaction_type]"]').val(type);
                $form.find('input[name="search[transaction_type]"]').val(type);
                $form.find('.estate-office-transaction-display').val(type);
            };

            const toggleSections = () => {
                const type = $('#transaction_type').val();
                const propertyRequired = ['SPRZEDAŻ', 'WYNAJEM'].includes(type);
                const searchRequired = ['KUPNO', 'NAJEM'].includes(type);

                $('.estate-office-property').toggle(propertyRequired);
                $('.estate-office-search').toggle(searchRequired);
                mirrorEmbeddedTransaction(type);
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
                const value = (type || $(selector).val() || '').toUpperCase();
                $('[data-property-types]').each(function(){
                    const allowed = ($(this).data('property-types') + '').split(',').map((item) => item.trim().toUpperCase());
                    const show = allowed.includes(value) || allowed.includes('*');
                    $(this).toggle(show);
                });
            };

            const toggleByTransaction = (value) => {
                const type = (value || '').toUpperCase();
                $('[data-transaction-types]').each(function(){
                    const allowed = ($(this).data('transaction-types') + '').split(',').map((item) => item.trim().toUpperCase());
                    const show = allowed.includes(type) || allowed.includes('*');
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
            const $searchPropertyType = $('#search_property_type');
            if ( $searchPropertyType.length ) {
                $searchPropertyType.on('change', function(){
                    toggleByType('#search_property_type', $(this).val());
                });
                toggleByType('#search_property_type', $searchPropertyType.val());
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
            const $transactionHidden = $('#property_transaction');
            if ( $transactionHidden.length ) {
                $transactionHidden.on('transaction:update', function( event, value ){
                    toggleByTransaction(value);
                });
                toggleByTransaction($transactionHidden.val());
            }

            const $legalToggle = $('#property_legal_no_kw');
            if ( $legalToggle.length ) {
                const $kwField = $('#property_legal_kw');
                const toggleKw = () => {
                    const disabled = $legalToggle.is(':checked');
                    $kwField.prop('disabled', disabled);
                    if ( disabled ) {
                        $kwField.val('');
                    }
                };
                $legalToggle.on('change', toggleKw);
                toggleKw();
            }

            $('[data-toggle-target]').each(function(){
                const $checkbox = $(this);
                const targetSelector = $checkbox.data('toggle-target');
                const $target = $(targetSelector);
                if ( ! $target.length ) {
                    return;
                }
                const update = () => {
                    const checked = $checkbox.is(':checked');
                    $target.toggle(checked);
                    $target.find('input, select, textarea').prop('disabled', ! checked);
                };
                $checkbox.on('change', update);
                update();
            });

            const $plotShape = $('#property_plot_shape');
            if ( $plotShape.length ) {
                $plotShape.on('change', function(){
                    const shape = $(this).val();
                    $('[data-plot-shape]').each(function(){
                        const allowed = ($(this).data('plot-shape') + '').split(',');
                        $(this).toggle(allowed.includes(shape));
                    });
                }).trigger('change');
            }

            const mapContainer = document.getElementById('estate-office-map');
            if ( mapContainer && typeof google !== 'undefined' && google.maps ) {
                const latInput = document.getElementById('property_map_lat');
                const lngInput = document.getElementById('property_map_lng');
                const output = document.getElementById('estate-office-map-output');
                const defaultLat = parseFloat(mapContainer.dataset.lat) || 52.2297;
                const defaultLng = parseFloat(mapContainer.dataset.lng) || 21.0122;
                const hasCoords = mapContainer.dataset.lat && mapContainer.dataset.lng;
                const map = new google.maps.Map(mapContainer, {
                    center: { lat: defaultLat, lng: defaultLng },
                    zoom: hasCoords ? 15 : 6,
                    mapTypeId: 'roadmap'
                });
                let marker = null;
                if ( hasCoords ) {
                    marker = new google.maps.Marker({
                        map,
                        position: { lat: parseFloat(mapContainer.dataset.lat), lng: parseFloat(mapContainer.dataset.lng) }
                    });
                }

                const updateCoords = (lat, lng) => {
                    if ( ! latInput || ! lngInput ) {
                        return;
                    }
                    latInput.value = lat.toFixed(6);
                    lngInput.value = lng.toFixed(6);
                    if ( output ) {
                        output.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
                    }
                };

                map.addListener('click', (event) => {
                    const position = event.latLng;
                    if ( marker ) {
                        marker.setPosition(position);
                    } else {
                        marker = new google.maps.Marker({ map, position });
                    }
                    updateCoords(position.lat(), position.lng());
                });

                const toggleButton = document.getElementById('property_map_trigger');
                if ( toggleButton ) {
                    toggleButton.addEventListener('click', () => {
                        mapContainer.classList.toggle('is-visible');
                        if ( mapContainer.classList.contains('is-visible') ) {
                            google.maps.event.trigger(map, 'resize');
                            map.setCenter(marker ? marker.getPosition() : { lat: defaultLat, lng: defaultLng });
                        }
                    });
                }
            }
        },

        setupTransactionMirrors() {
            const bindMirror = (selectSelector, hiddenSelector, displaySelector) => {
                const $select = $(selectSelector);
                const $hidden = $(hiddenSelector);
                if ( ! $select.length || ! $hidden.length ) {
                    return;
                }

                const $display = displaySelector ? $(displaySelector) : $();
                let fallback = $hidden.data('fallback') || '';

                const update = () => {
                    const $option = $select.find('option:selected');
                    let value = $option.data('transaction');
                    if ( typeof value === 'undefined' || value === '' ) {
                        value = fallback;
                    }

                    if ( value ) {
                        $hidden.val(value);
                        if ( $display.length ) {
                            $display.val(value);
                        }
                        fallback = value;
                        $hidden.data('fallback', value);
                        $hidden.trigger('transaction:update', [value]);
                    } else {
                        $hidden.val('');
                        if ( $display.length ) {
                            $display.val('');
                        }
                        $hidden.trigger('transaction:update', ['']);
                    }
                };

                $select.on('change', update);
                update();
            };

            bindMirror('#property_contract', '#property_transaction', '#property_transaction_display');
            bindMirror('#search_contract', '#search_transaction', '#search_transaction_display');
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
