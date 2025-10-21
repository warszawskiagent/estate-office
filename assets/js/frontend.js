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

    function initProfiles(container) {
        const drawer = $('.estate-office-crm-drawer');
        if (!drawer.length) {
            return;
        }

        const profileStrings = apiConfig.profile || {};
        const titleEl = drawer.find('.estate-office-crm-drawer__title');
        const subtitleEl = drawer.find('.estate-office-crm-drawer__subtitle');
        const badgesEl = drawer.find('.estate-office-crm-badges');
        const summaryEl = drawer.find('.estate-office-crm-summary');
        const sectionsEl = drawer.find('.estate-office-crm-sections');
        const descriptionEl = drawer.find('.estate-office-crm-description');
        const timelineEl = drawer.find('.estate-office-crm-timeline');
        const relationsEl = drawer.find('.estate-office-crm-relations');
        const actionsEl = drawer.find('.estate-office-crm-actions');
        const stageEl = drawer.find('.estate-office-crm-stage');
        const loadingEl = drawer.find('.estate-office-crm-drawer__loading');
        const errorEl = drawer.find('.estate-office-crm-drawer__error');
        const stageStrings = profileStrings.stage || {};

        function resetDrawer() {
            titleEl.text('');
            subtitleEl.text('');
            badgesEl.empty();
            summaryEl.empty().removeAttr('hidden');
            sectionsEl.empty();
            descriptionEl.empty();
            timelineEl.empty();
            relationsEl.empty();
            actionsEl.empty();
            stageEl.empty().attr('hidden', 'hidden');
            loadingEl.attr('hidden', 'hidden');
            errorEl.attr('hidden', 'hidden').text('');
        }

        function setVisible(isVisible) {
            if (isVisible) {
                drawer.addClass('is-visible').attr('aria-hidden', 'false');
            } else {
                drawer.removeClass('is-visible').attr('aria-hidden', 'true');
            }
        }

        function closeDrawer() {
            setVisible(false);
            resetDrawer();
        }

        function setLoading(isLoading) {
            if (isLoading) {
                loadingEl.removeAttr('hidden');
            } else {
                loadingEl.attr('hidden', 'hidden');
            }
        }

        function showError(message, fallbackLink) {
            errorEl.text(message || profileStrings.error || 'Nie udało się pobrać danych.');
            errorEl.removeAttr('hidden');

            actionsEl.empty();
            if (fallbackLink) {
                const button = $('<a class="button" />');
                button.attr('href', fallbackLink);
                button.text(profileStrings.openAdmin || 'Otwórz w kokpicie');
                actionsEl.append(button);
            }
        }

        function renderBadges(badges) {
            badgesEl.empty();
            if (!badges || !badges.length) {
                badgesEl.attr('hidden', 'hidden');
                return;
            }

            badgesEl.removeAttr('hidden');
            badges.forEach((badge) => {
                const item = $('<li />');
                item.text(badge);
                badgesEl.append(item);
            });
        }

        function renderSummary(items) {
            summaryEl.empty();
            if (!items || !items.length) {
                summaryEl.attr('hidden', 'hidden');
                return;
            }

            summaryEl.removeAttr('hidden');
            items.forEach((item) => {
                const dt = $('<dt />').text(item.label || '');
                const dd = $('<dd />').text(item.value || '');
                summaryEl.append(dt, dd);
            });
        }

        function renderSections(sections) {
            sectionsEl.empty();
            if (!sections || !sections.length) {
                sectionsEl.attr('hidden', 'hidden');
                return;
            }

            sectionsEl.removeAttr('hidden');
            sections.forEach((section) => {
                const wrapper = $('<section class="estate-office-crm-section" />');
                if (section.title) {
                    wrapper.append($('<h4 />').text(section.title));
                }

                if (section.items && section.items.length) {
                    const list = $('<dl />');
                    section.items.forEach((item) => {
                        list.append($('<dt />').text(item.label || ''));
                        list.append($('<dd />').text(item.value || ''));
                    });
                    wrapper.append(list);
                } else {
                    wrapper.append($('<p />').text(profileStrings.emptySection || 'Brak danych.'));
                }

                sectionsEl.append(wrapper);
            });
        }

        function renderDescription(content) {
            if (content) {
                descriptionEl.html(content).removeAttr('hidden');
            } else {
                descriptionEl.empty().attr('hidden', 'hidden');
            }
        }

        function renderTimeline(timeline, title) {
            timelineEl.empty();
            if (!timeline || !timeline.length) {
                timelineEl.attr('hidden', 'hidden');
                return;
            }

            timelineEl.removeAttr('hidden');
            if (title) {
                timelineEl.append($('<h4 />').text(title));
            }

            const list = $('<ol />');
            timeline.forEach((entry) => {
                const item = $('<li />');
                const date = $('<span class="estate-office-crm-timeline__date" />').text(entry.date || '');
                const label = $('<span class="estate-office-crm-timeline__label" />').text(entry.label || '');
                item.append(date).append(label);
                if (entry.notes) {
                    item.append($('<span class="estate-office-crm-timeline__notes" />').text(entry.notes));
                }
                list.append(item);
            });
            timelineEl.append(list);
        }

        function createRelationLink(item) {
            const button = $('<a href="#" class="estate-office-profile-link" />');
            button.attr('data-entity', item.type || '');
            button.attr('data-entity-id', item.id || '');
            if (item.edit_link) {
                button.attr('data-edit-link', item.edit_link);
            }
            button.text(item.label || '');
            return button;
        }

        function renderRelations(relations) {
            relationsEl.empty();
            if (!relations || !relations.length) {
                relationsEl.attr('hidden', 'hidden');
                return;
            }

            relationsEl.removeAttr('hidden');
            relations.forEach((relation) => {
                const wrapper = $('<section class="estate-office-crm-section" />');
                if (relation.title) {
                    wrapper.append($('<h4 />').text(relation.title));
                }

                const list = $('<ul class="estate-office-crm-relations__list" />');
                if (relation.items && relation.items.length) {
                    relation.items.forEach((item) => {
                        const listItem = $('<li />');
                        listItem.append(createRelationLink(item));
                        if (item.subtitle) {
                            listItem.append($('<span class="estate-office-crm-relations__subtitle" />').text(item.subtitle));
                        }
                        list.append(listItem);
                    });
                } else {
                    const empty = $('<li class="estate-office-crm-relations__empty" />');
                    empty.text(profileStrings.noRelations || 'Brak powiązań.');
                    list.append(empty);
                }

                wrapper.append(list);
                relationsEl.append(wrapper);
            });
        }

        function renderActions(actions) {
            actionsEl.empty();
            if (!actions || !actions.length) {
                actionsEl.attr('hidden', 'hidden');
                return;
            }

            actionsEl.removeAttr('hidden');
            actions.forEach((action) => {
                if (!action.url) {
                    return;
                }
                const link = $('<a class="button" target="_blank" rel="noopener" />');
                link.attr('href', action.url);
                link.text(action.label || 'Zobacz w kokpicie');
                if (action.style === 'secondary') {
                    link.addClass('button-secondary');
                }
                actionsEl.append(link);
            });
        }

        function renderStage(stage, contractId, message, isError) {
            stageEl.empty();

            if (!stage || stage.enabled === false || !contractId) {
                stageEl.attr('hidden', 'hidden');
                return;
            }

            stageEl.removeAttr('hidden');

            const title = $('<h4 class="estate-office-crm-stage__title" />').text(stageStrings.title || 'Aktualizuj etap umowy');
            stageEl.append(title);

            const form = $('<form class="estate-office-crm-stage__form" />');
            form.attr('data-contract-id', contractId);

            const selectId = 'estate-office-stage-' + contractId;
            const stageGroup = $('<div class="estate-office-crm-stage__group" />');
            stageGroup.append($('<label />').attr('for', selectId).text(stageStrings.stage || 'Etap umowy'));
            const select = $('<select name="stage" />').attr('id', selectId);
            (stage.options || []).forEach((option) => {
                const optionEl = $('<option />');
                optionEl.attr('value', option.value || '');
                optionEl.text(option.label || '');
                if (option.value === stage.current) {
                    optionEl.attr('selected', 'selected');
                }
                select.append(optionEl);
            });
            stageGroup.append(select);
            form.append(stageGroup);

            const dateId = 'estate-office-stage-date-' + contractId;
            const dateGroup = $('<div class="estate-office-crm-stage__group" />');
            dateGroup.append($('<label />').attr('for', dateId).text(stageStrings.date || 'Data etapu'));
            const dateInput = $('<input type="date" name="stage_date" />').attr('id', dateId);
            if (stage.date) {
                dateInput.val(stage.date);
            }
            dateGroup.append(dateInput);
            form.append(dateGroup);

            const submit = $('<button type="submit" class="button button-primary" />');
            submit.text(stageStrings.submit || 'Aktualizuj etap');
            form.append(submit);

            const status = $('<p class="estate-office-crm-stage__status" data-stage-status hidden></p>');
            if (message) {
                status.text(message);
                status.toggleClass('is-error', !!isError);
                status.removeAttr('hidden');
            }
            form.append(status);

            stageEl.append(form);
        }

        function renderProfile(data, fallbackLink) {
            titleEl.text(data.title || '');
            subtitleEl.text(data.subtitle || '');
            renderBadges(data.badges || []);
            renderSummary(data.summary || []);
            renderSections(data.sections || []);
            renderDescription(data.description || '');
            renderTimeline(data.timeline || [], data.timeline_title || '');
            renderRelations(data.relations || []);
            renderStage(data.stage || null, data.id || null);

            const actions = data.actions && data.actions.length ? data.actions : [];
            if (!actions.length && fallbackLink) {
                actions.push({ url: fallbackLink, label: profileStrings.openAdmin || 'Otwórz w kokpicie' });
            }
            renderActions(actions);
        }

        function fetchProfile(type, id) {
            const routes = {
                contract: '/contracts/' + id,
                property: '/properties/' + id,
                client: '/clients/' + id,
                search: '/searches/' + id,
            };

            const path = routes[type];
            if (!path) {
                return Promise.reject(new Error('Nieobsługiwany typ.'));
            }

            return api.get(path);
        }

        container.on('click', '.estate-office-profile-link', function (event) {
            event.preventDefault();
            const trigger = $(this);
            const type = trigger.data('entity');
            const id = trigger.data('entity-id');
            if (!type || !id) {
                return;
            }

            const fallback = trigger.data('edit-link');
            resetDrawer();
            setVisible(true);
            setLoading(true);

            fetchProfile(type, id)
                .then((response) => {
                    setLoading(false);
                    if (!response || !response.data) {
                        showError(profileStrings.error || 'Nie udało się pobrać danych.', fallback);
                        return;
                    }
                    renderProfile(response.data, fallback);
                })
                .catch((error) => {
                    setLoading(false);
                    showError(error && error.message ? error.message : null, fallback);
                });
        });

        drawer.on('submit', '.estate-office-crm-stage__form', function (event) {
            event.preventDefault();
            const form = $(this);
            const contractId = form.data('contractId');
            if (!contractId) {
                return;
            }

            const submit = form.find('button[type="submit"]');
            const status = form.find('[data-stage-status]');
            status.attr('hidden', 'hidden').removeClass('is-error');
            submit.prop('disabled', true);

            const payload = serializeForm(form);

            api
                .post('/contracts/' + contractId + '/stage', payload)
                .then((response) => {
                    submit.prop('disabled', false);
                    renderSummary(response.summary || []);
                    renderSections(response.sections || []);
                    renderTimeline(response.timeline || [], response.timeline_title || '');
                    renderStage(response.stage || null, contractId, response.message || stageStrings.success || 'Etap umowy został zaktualizowany.', false);
                })
                .catch((error) => {
                    submit.prop('disabled', false);
                    const message = error && error.message ? error.message : stageStrings.error || 'Nie udało się zapisać etapu umowy.';
                    status.text(message).addClass('is-error').removeAttr('hidden');
                });
        });

        drawer.on('click', '[data-action="close-profile"]', function (event) {
            event.preventDefault();
            closeDrawer();
        });

        drawer.on('click', function (event) {
            if ($(event.target).is('.estate-office-crm-drawer')) {
                closeDrawer();
            }
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
        initProfiles(crmContainer);
        initWizard(crmContainer);
    });
})(jQuery);
