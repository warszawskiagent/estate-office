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
            this.setupAgentMirrors();
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

            const self = this;
            const $transactionType = $form.find('#transaction_type');
            const $transactionStage = $form.find('#contract_stage');
            const $stageTableBody = $form.find('.estate-office-stage-table tbody');
            const $stageHistoryInput = $form.find('.estate-office-stage-history-json');
            const $startDate = $form.find('#start_date');
            const contractExists = $form.find('input[name="contract_id"]').length > 0;
            const $stepperItems = $form.find('.estate-office-stepper li');
            const steps = [];
            $form.find('.estate-office-step').each(function(){
                const value = parseFloat($(this).data('step'));
                if ( ! Number.isNaN(value) && ! steps.includes(value) ) {
                    steps.push(value);
                }
            });
            steps.sort((a, b) => a - b);
            let currentStepIndex = 0;

            const getStepValue = (index) => steps[Math.max(0, Math.min(index, steps.length - 1))];

            const updateStepperState = (activeValue) => {
                $stepperItems.each(function(){
                    const $item = $(this);
                    const stepValue = parseFloat($item.data('step'));
                    $item.toggleClass('active', stepValue === activeValue);
                    $item.toggleClass('completed', stepValue < activeValue);
                });
            };

            const refreshStageHistoryJson = () => {
                if ( ! $stageHistoryInput.length ) {
                    return;
                }
                const stageData = [];
                $stageTableBody.find('tr').each(function(){
                    const $row = $(this);
                    if ( $row.hasClass('no-items') ) {
                        return;
                    }
                    const stage = $row.find('select').val();
                    const date = $row.find('input[type="date"]').val();
                    if ( stage ) {
                        stageData.push({ stage: stage, date: date || '' });
                    }
                });
                $stageHistoryInput.val(JSON.stringify(stageData));
            };

            const updateStageRemoveState = () => {
                if ( ! $stageTableBody.length ) {
                    return;
                }
                const $rows = $stageTableBody.find('tr').not('.no-items');
                const allowRemoval = $rows.length > 1;
                $rows.each(function(index){
                    const $button = $(this).find('.estate-office-remove-row');
                    if ( ! $button.length ) {
                        return;
                    }
                    const shouldDisable = ! allowRemoval && index === 0;
                    $button.prop('disabled', shouldDisable);
                    if ( shouldDisable ) {
                        $button.attr('aria-disabled', 'true');
                    } else {
                        $button.removeAttr('aria-disabled');
                    }
                });
            };

            const ensureStageBaseline = () => {
                if ( ! $stageTableBody.length ) {
                    return;
                }
                let $rows = $stageTableBody.find('tr').not('.no-items');
                if ( ! $rows.length ) {
                    const template = wp.template('estate-office-stage-row');
                    $stageTableBody.html(template({ index: 0 }));
                    $rows = $stageTableBody.find('tr').not('.no-items');
                }
                if ( ! $rows.length ) {
                    return;
                }
                if ( ! contractExists ) {
                    const stageValue = $transactionStage.val();
                    if ( stageValue ) {
                        $rows.first().find('select').val(stageValue);
                    }
                }
                const startValue = $startDate.val();
                if ( startValue ) {
                    $rows.first().find('input[type="date"]').val(startValue);
                }
                updateStageRemoveState();
                refreshStageHistoryJson();
            };

            const appendStageHistoryEntry = (stageValue) => {
                if ( ! contractExists || ! $stageTableBody.length ) {
                    return;
                }
                const normalized = (stageValue || '').toString();
                if ( ! normalized.length ) {
                    return;
                }
                let $rows = $stageTableBody.find('tr').not('.no-items');
                if ( ! $rows.length ) {
                    ensureStageBaseline();
                    $rows = $stageTableBody.find('tr').not('.no-items');
                }
                if ( $rows.length ) {
                    const lastStage = $rows.last().find('select').val();
                    if ( lastStage === normalized ) {
                        return;
                    }
                }
                if ( $stageTableBody.find('.no-items').length ) {
                    $stageTableBody.empty();
                }
                const template = wp.template('estate-office-stage-row');
                const index = $stageTableBody.find('tr').length;
                $stageTableBody.append(template({ index: index }));
                const $newRow = $stageTableBody.find('tr').not('.no-items').last();
                $newRow.find('select').val(normalized);
                const now = new Date();
                const formatted = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
                $newRow.find('input[type="date"]').val(formatted);
                updateStageRemoveState();
                refreshStageHistoryJson();
            };

            self.refreshStageHistoryJson = refreshStageHistoryJson;
            self.updateStageRemoveState = updateStageRemoveState;
            self.ensureStageBaseline = ensureStageBaseline;

            const showStep = (index) => {
                const stepValue = getStepValue(index);
                $form.find('.estate-office-step').each(function(){
                    const $section = $(this);
                    const sectionValue = parseFloat($section.data('step'));
                    if ( sectionValue === stepValue ) {
                        const target = $section.data('transaction-target');
                        if ( (target === 'property' && ! propertyRequiredState) || (target === 'search' && ! searchRequiredState) ) {
                            $section.hide();
                        } else {
                            $section.show();
                        }
                    } else {
                        $section.hide();
                    }
                });
                currentStepIndex = steps.indexOf(stepValue);
                updateStepperState(stepValue);
                $form.find('.estate-office-prev-step').prop('disabled', currentStepIndex === 0);
                const $next = $form.find('.estate-office-next-step');
                const $submit = $form.find('.estate-office-submit-button');
                if ( currentStepIndex < steps.length - 1 ) {
                    $next.show();
                    $submit.hide();
                } else {
                    $next.hide();
                    $submit.show();
                }
            };

            const updatePriceLabel = (type) => {
                const $label = $form.find('label[for="property_price"]');
                if ( ! $label.length ) {
                    return;
                }
                const defaultLabel = $label.data('defaultLabel') || $label.data('default-label') || $label.text();
                const rentLabel = $label.data('rentLabel') || $label.data('rent-label') || defaultLabel;
                if ( type === 'WYNAJEM' ) {
                    $label.text(rentLabel);
                } else {
                    $label.text(defaultLabel);
                }
            };

            const mirrorEmbeddedTransaction = (type) => {
                const normalized = (type || '').toString().toUpperCase();
                $form.find('input[name="property[transaction_type]"]').val(normalized);
                $form.find('input[name="search[transaction_type]"]').val(normalized);
                $form.find('.estate-office-transaction-display').val(normalized);
                $form.find('#property_transaction_type').trigger('transaction:update', [normalized]);
                $form.find('#search_transaction_type').trigger('transaction:update', [normalized]);
                updatePriceLabel(normalized);
            };

            let propertyRequiredState = false;
            let searchRequiredState = false;

            const updateStepThreeLabel = (propertyRequired, searchRequired) => {
                const $step = $form.find('.estate-office-stepper [data-step="3"]');
                if ( ! $step.length ) {
                    return;
                }
                let label = $step.data('label-default');
                if ( propertyRequired ) {
                    label = $step.data('label-property');
                } else if ( searchRequired ) {
                    label = $step.data('label-search');
                }
                $step.find('.label-text').text(label);
            };

            const toggleSections = () => {
                const type = $transactionType.val();
                propertyRequiredState = ['SPRZEDAŻ', 'WYNAJEM'].includes(type);
                searchRequiredState = ['KUPNO', 'NAJEM'].includes(type);

                $form.find('[data-transaction-target="property"]').toggle(propertyRequiredState);
                $form.find('[data-transaction-target="search"]').toggle(searchRequiredState);
                updateStepThreeLabel(propertyRequiredState, searchRequiredState);
                mirrorEmbeddedTransaction(type);
            };

            const filterClients = () => {
                const filters = {};
                $form.find('.estate-office-client-filter input').each(function(){
                    const $input = $(this);
                    const key = $input.data('filter');
                    const value = ($input.val() || '').toString().trim().toLowerCase();
                    if ( value.length ) {
                        filters[key] = value;
                    }
                });

                const $rows = $form.find('.estate-office-clients-table tbody tr');
                $rows.each(function(){
                    const $row = $(this);
                    if ( $row.hasClass('no-items') ) {
                        return;
                    }
                    let visible = true;
                    Object.keys(filters).forEach((key) => {
                        const haystack = ($row.data(key) || '').toString();
                        if ( haystack.indexOf(filters[key]) === -1 ) {
                            visible = false;
                        }
                    });
                    $row.toggle(visible);
                });
            };

            const $selectedList = $form.find('.estate-office-selected-clients ul');
            const selectedEmptyText = $selectedList.data('empty') || '';
            const ensureSelectedPlaceholder = () => {
                if ( ! $selectedList.find('li[data-client-id]').length ) {
                    $selectedList.html('<li class="empty">' + selectedEmptyText + '</li>');
                }
            };

            const addSelectedClient = (id, label) => {
                if ( ! id || $selectedList.find('li[data-client-id="' + id + '"]').length ) {
                    return;
                }
                if ( $selectedList.find('.empty').length ) {
                    $selectedList.empty();
                }
                const $item = $('<li/>', { 'data-client-id': id });
                $item.append($('<span/>', { 'class': 'label', text: label }));
                $item.append($('<button/>', {
                    'type': 'button',
                    'class': 'button-link estate-office-remove-selected',
                    'aria-label': EstateOfficeData.removeClientLabel ? EstateOfficeData.removeClientLabel.replace('%s', label) : label
                }).text('×'));
                $item.append($('<input/>', { type: 'hidden', name: 'contract_clients[]', value: id }));
                $selectedList.append($item);
            };

            const $newClientsList = $form.find('.estate-office-new-client-list');
            const newClientsEmptyText = $newClientsList.data('empty') || '';

            const ensureNewClientsPlaceholder = () => {
                if ( ! $newClientsList.find('.estate-office-new-client-card').length ) {
                    $newClientsList.html('<p class="empty">' + newClientsEmptyText + '</p>');
                }
            };

            const updateNewClientCard = ($card) => {
                const type = $card.find('input[name$="[client_type]"]:checked').val() || 'individual';
                $card.find('[data-section]').each(function(){
                    const $section = $(this);
                    const section = $section.data('section');
                    const show = ! section || section === type;
                    $section.toggle(show);
                    $section.find('[data-required-for]').each(function(){
                        const $field = $(this);
                        const requiredFor = ($field.data('required-for') || '').toString();
                        $field.prop('required', requiredFor === type);
                    });
                });
            };

            const addNewClientCard = () => {
                const template = wp.template('estate-office-new-client-template');
                const index = $newClientsList.find('.estate-office-new-client-card').length;
                const html = template({ index: index });
                if ( $newClientsList.find('.empty').length ) {
                    $newClientsList.empty();
                }
                const $card = $(html);
                $newClientsList.append($card);
                updateNewClientCard($card);
            };

            const validateStep = (index) => {
                const stepValue = getStepValue(index);
                let isValid = true;
                $form.find('.estate-office-step[data-step="' + stepValue + '"]').find('[required]').each(function(){
                    const element = this;
                    if ( element.offsetParent !== null && ! element.checkValidity() ) {
                        element.reportValidity();
                        isValid = false;
                        return false;
                    }
                    return true;
                });
                if ( isValid && stepValue === 2 ) {
                    if ( ! $selectedList.find('li[data-client-id]').length && ! $newClientsList.find('.estate-office-new-client-card').length ) {
                        window.alert(EstateOfficeData.clientsRequired || 'Dodaj co najmniej jednego klienta do umowy.');
                        isValid = false;
                    }
                }
                return isValid;
            };

            $transactionType.on('change', () => {
                toggleSections();
                showStep(currentStepIndex);
            });
            toggleSections();

            $form.find('input[name="indefinite"]').on('change', function(){
                const indefinite = $(this).is(':checked');
                const $endDate = $form.find('#end_date');
                if ( indefinite ) {
                    $endDate.prop('disabled', true).val('');
                } else {
                    $endDate.prop('disabled', false);
                }
            }).trigger('change');

            $form.find('.estate-office-next-step').on('click', function(e){
                e.preventDefault();
                if ( validateStep(currentStepIndex) && currentStepIndex < steps.length - 1 ) {
                    showStep(currentStepIndex + 1);
                }
            });

            $form.find('.estate-office-prev-step').on('click', function(e){
                e.preventDefault();
                if ( currentStepIndex > 0 ) {
                    showStep(currentStepIndex - 1);
                }
            });

            $form.on('input', '.estate-office-client-filter input', filterClients);
            filterClients();

            $form.on('click', '.estate-office-client-add', function(){
                const $button = $(this);
                if ( $button.is(':disabled') ) {
                    return;
                }
                const id = $button.data('client-id');
                const label = $button.closest('tr').find('td').first().text();
                addSelectedClient(id, label);
                $button.prop('disabled', true);
            });

            $form.on('click', '.estate-office-remove-selected', function(){
                const $item = $(this).closest('li');
                const id = $item.data('client-id');
                $item.remove();
                $form.find('.estate-office-client-add[data-client-id="' + id + '"]').prop('disabled', false);
                ensureSelectedPlaceholder();
            });

            $form.on('click', '.estate-office-add-new-client', function(){
                addNewClientCard();
            });

            $form.on('change', 'input[name="add_more_clients"]', function(){
                if ( $(this).val() === 'yes' ) {
                    addNewClientCard();
                    $form.find('input[name="add_more_clients"][value="no"]').prop('checked', true);
                }
            });

            $form.on('change', '.estate-office-new-client-card input[name$="[client_type]"]', function(){
                const $card = $(this).closest('.estate-office-new-client-card');
                updateNewClientCard($card);
            });

            $form.on('click', '.estate-office-remove-new-client', function(){
                $(this).closest('.estate-office-new-client-card').remove();
                ensureNewClientsPlaceholder();
            });

            ensureSelectedPlaceholder();
            $selectedList.find('li[data-client-id]').each(function(){
                const id = $(this).data('client-id');
                if ( id ) {
                    $form.find('.estate-office-client-add[data-client-id="' + id + '"]').prop('disabled', true);
                }
            });
            ensureNewClientsPlaceholder();

            ensureStageBaseline();
            showStep(0);

            $form.on('submit', function(){
                ensureStageBaseline();
                refreshStageHistoryJson();
            });

            $form.on('change', '.estate-office-stage-table select, .estate-office-stage-table input[type="date"]', function(){
                refreshStageHistoryJson();
            });

            $startDate.on('change', function(){
                const value = $(this).val();
                const $primaryRow = $stageTableBody.find('tr').not('.no-items').first();
                if ( $primaryRow.length ) {
                    $primaryRow.find('input[type="date"]').val(value);
                }
                refreshStageHistoryJson();
            });

            $transactionStage.on('change', function(){
                const stageValue = $(this).val();
                if ( ! contractExists ) {
                    const $primaryRow = $stageTableBody.find('tr').not('.no-items').first();
                    if ( $primaryRow.length ) {
                        $primaryRow.find('select').val(stageValue);
                    }
                    refreshStageHistoryJson();
                    return;
                }
                appendStageHistoryEntry(stageValue);
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
                if ( typeof EstateOfficeAdmin.updateStageRemoveState === 'function' ) {
                    EstateOfficeAdmin.updateStageRemoveState();
                }
                if ( typeof EstateOfficeAdmin.refreshStageHistoryJson === 'function' ) {
                    EstateOfficeAdmin.refreshStageHistoryJson();
                }
            });

            $(document).on('click', '.estate-office-remove-row', function(){
                const $table = $(this).closest('tbody');
                const $rows = $table.find('tr').not('.no-items');
                if ( $rows.length <= 1 ) {
                    const message = EstateOfficeData.stageGuard || 'Nie możesz usunąć ostatniego etapu umowy.';
                    window.alert(message);
                    return;
                }
                $(this).closest('tr').remove();
                if ( ! $table.find('tr').length ) {
                    $table.html('<tr class="no-items"><td colspan="3">' + (EstateOfficeData.emptyStages || 'Brak historii etapów.') + '</td></tr>');
                }
                if ( typeof EstateOfficeAdmin.updateStageRemoveState === 'function' ) {
                    EstateOfficeAdmin.updateStageRemoveState();
                }
                if ( typeof EstateOfficeAdmin.refreshStageHistoryJson === 'function' ) {
                    EstateOfficeAdmin.refreshStageHistoryJson();
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
            const $wizardTransaction = $('#property_transaction_type');
            if ( $wizardTransaction.length ) {
                $wizardTransaction.on('transaction:update', function( event, value ){
                    toggleByTransaction(value);
                });
                toggleByTransaction($wizardTransaction.val());
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

        setupAgentMirrors() {
            const markManual = ($element) => {
                $element.data('manual', true);
            };

            const prepareAgentField = ($agent) => {
                if ( ! $agent.length ) {
                    return;
                }
                $agent.on('change', function(){
                    if ( $agent.data('suppressManual') ) {
                        $agent.data('suppressManual', false);
                        return;
                    }
                    markManual($agent);
                });
            };

            const setAgentValue = ($agent, value) => {
                if ( ! $agent.length ) {
                    return;
                }
                $agent.data('suppressManual', true);
                if ( typeof value === 'undefined' || value === null ) {
                    value = '';
                }
                $agent.val(value ? String(value) : '');
                $agent.trigger('change');
                if ( '' === value ) {
                    const fallback = $agent.data('fallback');
                    if ( fallback ) {
                        $agent.data('suppressManual', true);
                        $agent.val(String(fallback)).trigger('change');
                    }
                }
                $agent.data('manual', false);
            };

            const bindContractAgent = (contractSelector, agentSelector) => {
                const $contract = $(contractSelector);
                const $agent    = $(agentSelector);
                if ( ! $contract.length || ! $agent.length ) {
                    return;
                }

                prepareAgentField( $agent );

                const updateFromContract = () => {
                    const selected = $contract.find('option:selected');
                    if ( ! $agent.data('manual') ) {
                        const agentId = selected.data('agent');
                        if ( typeof agentId !== 'undefined' ) {
                            setAgentValue( $agent, agentId ? agentId : '' );
                        }
                    }
                };

                $contract.on('change', () => {
                    $agent.data('manual', false);
                    updateFromContract();
                });

                updateFromContract();
            };

            const synchronizeFromSource = (sourceSelector, targetSelectors) => {
                const $source = $(sourceSelector);
                if ( ! $source.length ) {
                    return;
                }
                const $targets = targetSelectors.map((selector) => {
                    const $target = $(selector);
                    prepareAgentField( $target );
                    return $target;
                });

                const apply = () => {
                    const value = $source.val();
                    $targets.forEach(($target) => {
                        if ( ! $target.length ) {
                            return;
                        }
                        if ( $target.data('manual') ) {
                            return;
                        }
                        setAgentValue( $target, value );
                    });
                };

                $source.on('change', () => {
                    $targets.forEach(($target) => $target.data('manual', false));
                    apply();
                });

                apply();
            };

            bindContractAgent('#property_contract', '#property_agent');
            bindContractAgent('#search_contract', '#search_agent');
            synchronizeFromSource('#contract_agent', ['#contract_property_agent', '#contract_search_agent']);
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
