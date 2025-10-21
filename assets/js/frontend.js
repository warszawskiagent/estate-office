(function ($) {
    'use strict';

    const apiConfig = window.estateOfficeFrontend || {};

    function buildApiUrl(path, params) {
        if (!apiConfig.apiUrl) {
            throw new Error('API unavailable');
        }

        let url = apiConfig.apiUrl + path;
        if (params && Object.keys(params).length) {
            const query = new URLSearchParams(params).toString();
            url += '?' + query;
        }

        return url;
    }

    function request(path, options) {
        const settings = options || {};
        const headers = Object.assign({}, settings.headers || {});
        headers['X-WP-Nonce'] = apiConfig.nonce;
        if (!settings.body && settings.method !== 'GET') {
            headers['Content-Type'] = 'application/json';
        }

        return fetch(buildApiUrl(path, settings.params || {}), Object.assign({}, settings, { headers }))
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = data && data.message ? data.message : response.statusText;
                    const error = new Error(message || 'Request failed');
                    error.response = data;
                    throw error;
                }

                return data;
            });
    }

    const api = {
        post(path, payload) {
            return request(path, {
                method: 'POST',
                body: JSON.stringify(payload || {}),
            });
        },
        get(path, params) {
            return request(path, {
                method: 'GET',
                params: params || {},
            });
        },
    };

    function serializeForm($form) {
        const form = $form[0];
        const formData = new FormData(form);
        const data = {};

        formData.forEach((value, key) => {
            addValue(data, key, value);
        });

        return data;
    }

    function addValue(target, key, value) {
        if (key.endsWith('[]')) {
            const base = key.slice(0, -2);
            if (!Array.isArray(target[base])) {
                target[base] = [];
            }
            target[base].push(value);
            return;
        }

        if (key.indexOf('[') === -1) {
            target[key] = value;
            return;
        }

        const parts = key.replace(/]/g, '').split('[');
        let ref = target;
        parts.forEach((part, index) => {
            if (index === parts.length - 1) {
                ref[part] = value;
                return;
            }

            if (!ref[part] || typeof ref[part] !== 'object') {
                ref[part] = {};
            }
            ref = ref[part];
        });
    }

    function togglePanels(container, target) {
        container.find('.estate-office-crm-tab').removeClass('is-active');
        container.find('.estate-office-crm-panel').removeClass('is-active');
        container.find('.estate-office-crm-tab[data-tab="' + target + '"]').addClass('is-active');
        container.find('.estate-office-crm-panel[data-panel="' + target + '"]').addClass('is-active');
    }

    function initTabs(container) {
        const tabs = container.find('.estate-office-crm-tab');
        tabs.on('click', function (event) {
            event.preventDefault();
            const tab = $(this);
            togglePanels(container, tab.data('tab'));
        });
    }

    function initSearch(container) {
        const input = container.find('#estate-office-crm-search-field');
        const tables = container.find('.estate-office-crm-table');

        input.on('input', function () {
            const query = $(this).val().toLowerCase();

            tables.each(function () {
                const table = $(this);
                const rows = table.find('tbody tr');
                let hasVisible = false;

                rows.each(function () {
                    const row = $(this);
                    if (row.hasClass('estate-office-empty')) {
                        return;
                    }

                    const text = row.text().toLowerCase();
                    const match = text.indexOf(query) !== -1;
                    row.toggle(match);
                    if (match) {
                        hasVisible = true;
                    }
                });

                table.find('.estate-office-empty').toggle(!hasVisible);
            });
        });
    }

    function initWizard(container) {
        const modal = $('.estate-office-modal');
        if (!modal.length) {
            return;
        }

        const wizard = modal.find('.estate-office-wizard');
        const steps = wizard.find('.estate-office-wizard__step');
        const progress = wizard.find('.estate-office-wizard__progress li');
        const selectedList = modal.find('.estate-office-selected-clients__list');
        const clientCta = modal.find('.estate-office-wizard__cta');
        const propertyForm = modal.find('.estate-office-property-form');
        const searchForm = modal.find('.estate-office-search-form');
        const summaryBox = modal.find('.estate-office-wizard__summary');
        const summaryMessage = summaryBox.find('.estate-office-wizard__success');
        const summaryLinks = summaryBox.find('.estate-office-wizard__links');

        const state = {
            contractId: null,
            transactionType: null,
            propertyType: null,
            selectedClients: [],
            contractEditLink: null,
        };

        function openModal() {
            modal.attr('aria-hidden', 'false');
            modal.addClass('is-visible');
            resetWizard();
        }

        function closeModal() {
            modal.attr('aria-hidden', 'true');
            modal.removeClass('is-visible');
        }

        function resetWizard() {
            state.contractId = null;
            state.transactionType = null;
            state.propertyType = null;
            state.selectedClients = [];
            state.contractEditLink = null;

            steps.removeClass('is-active').attr('aria-hidden', 'true');
            progress.removeClass('is-active');
            steps.first().addClass('is-active').attr('aria-hidden', 'false');
            progress.first().addClass('is-active');
            wizard.attr('data-step', '1');
            selectedList.empty();
            clientCta.attr('hidden', 'hidden');
            summaryBox.attr('hidden', 'hidden');
            propertyForm.attr('hidden', 'hidden');
            searchForm.attr('hidden', 'hidden');
            propertyForm[0].reset();
            searchForm[0].reset();
            modal.find('.estate-office-wizard__form').each(function () {
                this.reset();
                $(this).find('.estate-office-wizard__error').text('');
            });
        }

        function goToStep(step) {
            wizard.attr('data-step', step);
            steps.removeClass('is-active').attr('aria-hidden', 'true');
            steps.filter('[data-step="' + step + '"]').addClass('is-active').attr('aria-hidden', 'false');
            progress.removeClass('is-active');
            progress.filter('[data-step="' + step + '"]').addClass('is-active');
        }

        function setError($form, message) {
            $form.find('.estate-office-wizard__error').text(message || '');
        }

        function updateSelectedClientsList() {
            selectedList.empty();
            state.selectedClients.forEach((client) => {
                const item = $('<li />');
                item.text(client.title);
                selectedList.append(item);
            });

            if (state.selectedClients.length) {
                clientCta.removeAttr('hidden');
            } else {
                clientCta.attr('hidden', 'hidden');
            }
        }

        function assignClient(clientId, clientTitle) {
            if (!state.contractId) {
                return Promise.reject(new Error('Brak utworzonej umowy.'));
            }

            return api
                .post('/contracts/' + state.contractId + '/clients', { client_id: clientId })
                .then(() => {
                    const exists = state.selectedClients.some((client) => client.id === clientId);
                    if (!exists) {
                        state.selectedClients.push({ id: clientId, title: clientTitle });
                    }
                    updateSelectedClientsList();
                });
        }

        function handleContractSubmit(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            setError($form, '');

            const payload = serializeForm($form);
            return api
                .post('/contracts', payload)
                .then((response) => {
                    state.contractId = response.id;
                    state.contractEditLink = response.edit_link || null;
                    state.transactionType = response.transaction_type;
                    state.propertyType = response.property_type;

                    const propertyTypeField = propertyForm.find('select[name="property_type"]');
                    if (state.propertyType && propertyTypeField.length) {
                        propertyTypeField.val(state.propertyType);
                    }

                    const transactionField = propertyForm.find('select[name="transaction_type"]');
                    if (transactionField.length) {
                        if (response.transaction_type === 'sprzedaz' || response.transaction_type === 'wynajem') {
                            transactionField.val(response.transaction_type).trigger('change');
                        } else {
                            transactionField.trigger('change');
                        }
                    }

                    const searchTransaction = searchForm.find('select[name="transaction_type"]');
                    if (searchTransaction.length) {
                        const mapped = response.transaction_type === 'kupno' || response.transaction_type === 'najem'
                            ? response.transaction_type
                            : 'kupno';
                        searchTransaction.val(mapped);
                    }

                    const propertyContract = propertyForm.find('input[name="contract_id"]');
                    const searchContract = searchForm.find('input[name="contract_id"]');
                    propertyContract.val(response.id);
                    searchContract.val(response.id);

                    goToStep(2);
                })
                .catch((error) => {
                    setError($form, error.message || 'Nie udało się utworzyć umowy.');
                });
        }

        function handleClientSearch(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            const $results = modal.find('.estate-office-client-results');
            $results.empty();

            const payload = serializeForm($form);
            api
                .get('/clients', payload)
                .then((response) => {
                    const items = response.items || [];
                    if (!items.length) {
                        $results.append($('<li />').text(apiConfig.strings.noResults || 'Brak wyników.'));
                        return;
                    }

                    items.forEach((item) => {
                        const button = $('<button type="button" class="button-link" />');
                        button.text(item.title + (item.email ? ' (' + item.email + ')' : ''));
                        button.on('click', () => {
                            assignClient(item.id, item.title).catch((error) => {
                                alert(error.message || 'Nie udało się przypisać klienta.');
                            });
                        });

                        const listItem = $('<li />').append(button);
                        $results.append(listItem);
                    });
                })
                .catch(() => {
                    $results.append($('<li />').text('Błąd wyszukiwania.'));
                });
        }

        function toggleClientFields() {
            const type = modal.find('#estate-office-client-type').val();
            modal.find('.estate-office-client-form [data-visible]').each(function () {
                const element = $(this);
                const allowed = String(element.data('visible')).split(',');
                if (allowed.indexOf(type) !== -1) {
                    element.show();
                } else {
                    element.hide();
                }
            });
        }

        function toggleCorrespondence() {
            const checkbox = modal.find('input[name="same_correspondence"]');
            const box = modal.find('.estate-office-correspondence');
            if (checkbox.is(':checked')) {
                box.attr('hidden', 'hidden');
            } else {
                box.removeAttr('hidden');
            }
        }

        function handleClientSubmit(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            setError($form, '');

            const payload = serializeForm($form);
            payload.same_correspondence = $form.find('input[name="same_correspondence"]').is(':checked') ? 1 : 0;

            api
                .post('/clients', payload)
                .then((response) => assignClient(response.id, response.title))
                .then(() => {
                    $form[0].reset();
                    toggleClientFields();
                    toggleCorrespondence();
                })
                .catch((error) => {
                    setError($form, error.message || 'Nie udało się zapisać klienta.');
                });
        }

        function ensureStepThreeForms() {
            const isSeller = state.transactionType === 'sprzedaz' || state.transactionType === 'wynajem';
            if (isSeller) {
                propertyForm.removeAttr('hidden');
                searchForm.attr('hidden', 'hidden');
                propertyForm.find('select[name="transaction_type"]').val(state.transactionType).trigger('change');
                propertyForm.find('input[name="contract_id"]').val(state.contractId);
                if (state.propertyType) {
                    propertyForm.find('select[name="property_type"]').val(state.propertyType).trigger('change');
                }
            } else {
                searchForm.removeAttr('hidden');
                propertyForm.attr('hidden', 'hidden');
                const mapped = state.transactionType === 'najem' ? 'najem' : 'kupno';
                searchForm.find('select[name="transaction_type"]').val(mapped);
                searchForm.find('input[name="contract_id"]').val(state.contractId);
                if (state.propertyType) {
                    searchForm.find('select[name="property_type"]').val(state.propertyType);
                }
            }
        }

        function handleNextClient(answer) {
            if (answer === 'yes') {
                modal.find('.estate-office-client-form')[0].reset();
                toggleClientFields();
                toggleCorrespondence();
                return;
            }

            if (!state.selectedClients.length) {
                alert(apiConfig.strings.clientRequired || 'Dodaj co najmniej jednego klienta.');
                return;
            }

            ensureStepThreeForms();
            goToStep(3);
        }

        function togglePropertyFields() {
            const propertyType = propertyForm.find('select[name="property_type"]').val();
            propertyForm.find('[data-property-visible]').each(function () {
                const element = $(this);
                const allowed = element.data('property-visible');
                if (allowed === undefined || allowed === '') {
                    element.show();
                    return;
                }

                const values = String(allowed).split(',');
                if (values.indexOf(propertyType) !== -1) {
                    element.show();
                } else {
                    element.hide();
                }
            });
        }

        function toggleLotDetails() {
            const lotShape = propertyForm.find('select[name="lot_shape"]').val();
            propertyForm.find('[data-lot-shape]').each(function () {
                const element = $(this);
                const value = element.data('lot-shape');
                if (!value || value === lotShape) {
                    element.removeAttr('hidden');
                } else {
                    element.attr('hidden', 'hidden');
                }
            });
        }

        function toggleTransactionFields() {
            const transaction = propertyForm.find('select[name="transaction_type"]').val();
            propertyForm.find('[data-transaction-visible]').each(function () {
                const element = $(this);
                const allowed = element.data('transaction-visible');
                if (!allowed) {
                    element.show();
                    return;
                }

                const values = String(allowed).split(',');
                if (values.indexOf(transaction) !== -1) {
                    element.show();
                } else {
                    element.hide();
                }
            });
        }

        function toggleFlagsAvailability() {
            const transaction = propertyForm.find('select[name="transaction_type"]').val();
            propertyForm.find('.estate-office-flag-list label').each(function () {
                const label = $(this);
                const required = label.data('transaction');
                const checkbox = label.find('input[type="checkbox"]');
                if (!required || required === transaction) {
                    checkbox.prop('disabled', false);
                } else {
                    checkbox.prop('disabled', true).prop('checked', false);
                }
            });
        }

        function handleContractBack(event) {
            event.preventDefault();
            const current = parseInt(wizard.attr('data-step'), 10) || 1;
            if (current > 1) {
                goToStep(current - 1);
            }
        }

        function handlePropertySubmit(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            setError($form, '');

            const payload = serializeForm($form);
            payload.no_lmr = $form.find('input[name="no_lmr"]').is(':checked') ? 1 : 0;

            api
                .post('/properties', payload)
                .then((response) => {
                    finishWizard(response.edit_link || null);
                })
                .catch((error) => {
                    setError($form, error.message || 'Nie udało się zapisać nieruchomości.');
                });
        }

        function handleSearchSubmit(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            setError($form, '');

            const payload = serializeForm($form);

            api
                .post('/searches', payload)
                .then((response) => {
                    finishWizard(response.edit_link || null);
                })
                .catch((error) => {
                    setError($form, error.message || 'Nie udało się zapisać poszukiwania.');
                });
        }

        function finishWizard(extraLink) {
            propertyForm.attr('hidden', 'hidden');
            searchForm.attr('hidden', 'hidden');
            summaryLinks.empty();

            const links = [];
            if (state.contractEditLink) {
                links.push({ url: state.contractEditLink, label: apiConfig.strings.contractEdit || 'Przejdź do umowy' });
            }
            if (extraLink) {
                links.push({ url: extraLink, label: apiConfig.strings.offerEdit || 'Otwórz rekord' });
            }

            links.forEach((link) => {
                const anchor = $('<a class="button" />');
                anchor.attr('href', link.url).text(link.label);
                summaryLinks.append(anchor);
            });

            summaryMessage.text(apiConfig.strings.success || 'Rekord został zapisany.');
            summaryBox.removeAttr('hidden');
        }

        function initDisableToggles() {
            modal.find('[data-disabled-by]').each(function () {
                const field = $(this);
                const toggleName = field.data('disabled-by');
                const toggle = modal.find('[data-toggle="' + toggleName + '"]');
                if (!toggle.length) {
                    return;
                }

                const update = () => {
                    if (toggle.is(':checked')) {
                        field.prop('disabled', true).val('');
                    } else {
                        field.prop('disabled', false);
                    }
                };

                toggle.on('change', update);
                update();
            });
        }

        container.on('click', '.estate-office-new-contract', function (event) {
            event.preventDefault();
            openModal();
        });

        modal.on('click', '.estate-office-modal__close, .estate-office-wizard__close', function (event) {
            event.preventDefault();
            closeModal();
        });

        modal.on('click', function (event) {
            if ($(event.target).is('.estate-office-modal')) {
                closeModal();
            }
        });

        modal.find('form[data-action="create-contract"]').on('submit', handleContractSubmit);
        modal.find('form[data-action="search-clients"]').on('submit', handleClientSearch);
        modal.find('form[data-action="create-client"]').on('submit', handleClientSubmit);
        propertyForm.on('submit', handlePropertySubmit);
        searchForm.on('submit', handleSearchSubmit);

        modal.on('click', '.estate-office-wizard__back', handleContractBack);
        modal.on('click', '.estate-office-add-next-client', function (event) {
            event.preventDefault();
            const answer = $(this).data('answer');
            handleNextClient(answer);
        });

        modal.find('#estate-office-client-type').on('change', toggleClientFields);
        modal.find('input[name="same_correspondence"]').on('change', toggleCorrespondence);
        propertyForm.find('select[name="property_type"]').on('change', togglePropertyFields);
        propertyForm.find('select[name="lot_shape"]').on('change', toggleLotDetails);
        propertyForm.find('select[name="transaction_type"]').on('change', function () {
            toggleFlagsAvailability();
            toggleTransactionFields();
        });

        toggleClientFields();
        toggleCorrespondence();
        togglePropertyFields();
        toggleLotDetails();
        toggleFlagsAvailability();
        toggleTransactionFields();
        initDisableToggles();
    }

    $(document).ready(function () {
        const crmContainer = $('.estate-office-crm');
        if (!crmContainer.length) {
            return;
        }

        initTabs(crmContainer);
        initSearch(crmContainer);
        initWizard(crmContainer);
    });
})(jQuery);
