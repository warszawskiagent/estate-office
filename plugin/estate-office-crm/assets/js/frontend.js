(function () {
    'use strict';

    function normalize(value) {
        return (value || '').toString().trim().toLowerCase();
    }

    function normalizeForSearch(value) {
        var normalized = (value || '').toString().toLowerCase().trim();

        if (typeof normalized.normalize === 'function') {
            normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        return normalized.replace(/\s+/g, ' ');
    }

    function trimText(value) {
        return (value || '').toString().trim();
    }

    function parseDecimal(value) {
        var raw = (value || '').toString().replace(',', '.').trim();
        var num = parseFloat(raw);
        return isNaN(num) ? null : num;
    }

    function getFrontendConfig() {
        if (typeof window.eocrmFrontendConfig === 'object' && window.eocrmFrontendConfig) {
            return window.eocrmFrontendConfig;
        }

        return {};
    }

    function applyCombinedRowVisibility(row) {
        if (!row) {
            return;
        }

        var textMatch = row.getAttribute('data-eocrm-text-filter-match');
        var propertyMatch = row.getAttribute('data-eocrm-property-filter-match');
        var tableMatch = row.getAttribute('data-eocrm-table-filter-match');
        var shouldShow = textMatch !== '0' && propertyMatch !== '0' && tableMatch !== '0';

        row.style.display = shouldShow ? '' : 'none';
    }

    function bindLiveFilters() {
        var inputs = document.querySelectorAll('[data-live-filter]');
        if (!inputs.length) {
            return;
        }

        inputs.forEach(function (input) {
            var targetId = input.getAttribute('data-target-table');
            if (!targetId) {
                return;
            }

            var table = document.getElementById(targetId);
            if (!table) {
                return;
            }

            var rows = table.querySelectorAll('tbody tr');
            if (!rows.length) {
                return;
            }

            var applyFilter = function () {
                var query = normalize(input.value);

                rows.forEach(function (row) {
                    var text = normalize(row.textContent);
                    row.setAttribute('data-eocrm-text-filter-match', query === '' || text.indexOf(query) !== -1 ? '1' : '0');
                    applyCombinedRowVisibility(row);
                });
            };

            input.addEventListener('input', applyFilter);
            applyFilter();
        });
    }

    function bindCrmPropertyTableFilters() {
        var filters = document.querySelectorAll('[data-eocrm-property-filter]');
        var table = document.getElementById('eocrm-properties-table');
        if (!filters.length || !table) {
            return;
        }

        var rows = table.querySelectorAll('[data-eocrm-property-row]');
        if (!rows.length) {
            return;
        }

        var selectedValues = function (filterType) {
            var values = [];
            filters.forEach(function (filter) {
                if (filter.getAttribute('data-eocrm-property-filter') === filterType && filter.checked) {
                    values.push((filter.value || '').toString());
                }
            });
            return values;
        };

        var applyFilters = function () {
            var transactions = selectedValues('transaction');
            var requireActiveAgreement = selectedValues('active_agreement').length > 0;
            var requireWww = selectedValues('export_www').length > 0;
            var requirePortals = selectedValues('export_portals').length > 0;
            var requireSoldRented = selectedValues('sold_rented').length > 0;

            rows.forEach(function (row) {
                var match = true;
                var rowTransaction = (row.getAttribute('data-transaction') || '').toString();

                if (transactions.length && transactions.indexOf(rowTransaction) === -1) {
                    match = false;
                }
                if (requireActiveAgreement && row.getAttribute('data-active-agreement') !== '1') {
                    match = false;
                }
                if (requireWww && row.getAttribute('data-export-www') !== '1') {
                    match = false;
                }
                if (requirePortals && row.getAttribute('data-export-portals') !== '1') {
                    match = false;
                }
                if (requireSoldRented && row.getAttribute('data-sold-rented') !== '1') {
                    match = false;
                }

                row.setAttribute('data-eocrm-property-filter-match', match ? '1' : '0');
                applyCombinedRowVisibility(row);
            });
        };

        filters.forEach(function (filter) {
            filter.addEventListener('change', applyFilters);
        });
        applyFilters();
    }

    function bindCrmTransactionTableFilters() {
        var groups = document.querySelectorAll('[data-eocrm-transaction-filter-group]');
        if (!groups.length) {
            return;
        }

        groups.forEach(function (group) {
            var targetId = group.getAttribute('data-target-table');
            if (!targetId) {
                return;
            }

            var table = document.getElementById(targetId);
            if (!table) {
                return;
            }

            var filters = group.querySelectorAll('[data-eocrm-transaction-filter], [data-eocrm-status-filter]');
            var rows = table.querySelectorAll('[data-eocrm-transaction-row]');
            if (!filters.length || !rows.length) {
                return;
            }

            var applyFilters = function () {
                var transactions = [];
                var requireActiveAgreement = false;

                filters.forEach(function (filter) {
                    if (!filter.checked) {
                        return;
                    }

                    if (filter.getAttribute('data-eocrm-status-filter') === 'active_agreement') {
                        requireActiveAgreement = true;
                        return;
                    }

                    if (filter.hasAttribute('data-eocrm-transaction-filter')) {
                        transactions.push((filter.value || '').toString());
                    }
                });

                rows.forEach(function (row) {
                    var rowTransaction = (row.getAttribute('data-transaction') || '').toString();
                    var match = !transactions.length || transactions.indexOf(rowTransaction) !== -1;

                    if (requireActiveAgreement && row.getAttribute('data-active-agreement') !== '1') {
                        match = false;
                    }

                    row.setAttribute('data-eocrm-table-filter-match', match ? '1' : '0');
                    applyCombinedRowVisibility(row);
                });
            };

            filters.forEach(function (filter) {
                filter.addEventListener('change', applyFilters);
            });
            applyFilters();
        });
    }

    function bindCrmClientTableFilters() {
        var groups = document.querySelectorAll('[data-eocrm-client-filter-group]');
        if (!groups.length) {
            return;
        }

        groups.forEach(function (group) {
            var targetId = group.getAttribute('data-target-table');
            if (!targetId) {
                return;
            }

            var table = document.getElementById(targetId);
            if (!table) {
                return;
            }

            var filters = group.querySelectorAll('[data-eocrm-client-filter]');
            var rows = table.querySelectorAll('[data-eocrm-client-row]');
            if (!filters.length || !rows.length) {
                return;
            }

            var applyFilters = function () {
                var types = [];

                filters.forEach(function (filter) {
                    if (filter.checked && filter.getAttribute('data-eocrm-client-filter') === 'type') {
                        types.push((filter.value || '').toString());
                    }
                });

                rows.forEach(function (row) {
                    var rowType = (row.getAttribute('data-client-type') || '').toString();
                    var match = !types.length || types.indexOf(rowType) !== -1;

                    row.setAttribute('data-eocrm-table-filter-match', match ? '1' : '0');
                    applyCombinedRowVisibility(row);
                });
            };

            filters.forEach(function (filter) {
                filter.addEventListener('change', applyFilters);
            });
            applyFilters();
        });
    }

    function bindDynamicToggles() {
        var controls = document.querySelectorAll('[data-toggle-control]');

        controls.forEach(function (control) {
            var targetSelector = control.getAttribute('data-toggle-target');
            var showValue = normalize(control.getAttribute('data-show-value'));
            var hideClass = control.getAttribute('data-hide-class') || 'is-hidden';
            if (!targetSelector) {
                return;
            }

            var targets = document.querySelectorAll(targetSelector);
            if (!targets.length) {
                return;
            }

            var refresh = function () {
                var value = '';

                if (control.type === 'checkbox') {
                    value = control.checked ? '1' : '0';
                } else {
                    value = normalize(control.value);
                }

                var shouldShow = showValue === '' ? !!value : value === showValue;

                targets.forEach(function (target) {
                    target.classList.toggle(hideClass, !shouldShow);
                });
            };

            control.addEventListener('change', refresh);
            refresh();
        });
    }

    function bindAgreementFormDynamics() {
        var forms = document.querySelectorAll('[data-eocrm-agreement-form]');
        if (!forms.length) {
            return;
        }

        forms.forEach(function (form) {
            var refs = {
                indefiniteCheckbox: form.querySelector('[data-eocrm-indefinite]'),
                dateEndInput: form.querySelector('[data-eocrm-date-end]'),
                agreementNumberInput: form.querySelector('[data-eocrm-agreement-number]'),
                transactionTypeSelect: form.querySelector('[data-eocrm-agreement-transaction-type]'),
                addNewClientCheckbox: form.querySelector('[data-eocrm-agreement-add-client]'),
                newClientWrap: form.querySelector('.eocrm-agreement-new-client-wrap'),
                newClientType: form.querySelector('[data-eocrm-agreement-client-type]'),
                personFields: form.querySelectorAll('.eocrm-agreement-client-person'),
                companyFields: form.querySelectorAll('.eocrm-agreement-client-company'),
                requiredPerson: form.querySelectorAll('[data-eocrm-agreement-required-person]'),
                requiredCompany: form.querySelectorAll('[data-eocrm-agreement-required-company]'),
                requiredMain: form.querySelectorAll('[data-eocrm-agreement-required-main]'),
                corrSame: form.querySelector('[data-eocrm-agreement-corr-same]'),
                corrWrap: form.querySelector('.eocrm-agreement-client-corr'),
                corrRequired: form.querySelectorAll('[data-eocrm-agreement-corr-field]'),
                clientSearchInput: form.querySelector('[data-eocrm-agreement-client-search]'),
                clientOptions: form.querySelectorAll('[data-eocrm-client-option]'),
                clientEmpty: form.querySelector('[data-eocrm-agreement-client-empty]'),
                additionalClientList: form.querySelector('[data-eocrm-additional-new-clients]'),
                additionalClientTemplate: form.querySelector('[data-eocrm-additional-new-client-template]'),
                additionalClientAdd: form.querySelector('[data-eocrm-additional-new-client-add]')
            };

            var agreementNumberDefaults = {};
            if (refs.agreementNumberInput) {
                var rawDefaults = refs.agreementNumberInput.getAttribute('data-eocrm-agreement-number-defaults');
                if (typeof rawDefaults === 'string' && rawDefaults.trim() !== '') {
                    try {
                        var parsedDefaults = JSON.parse(rawDefaults);
                        if (parsedDefaults && typeof parsedDefaults === 'object') {
                            Object.keys(parsedDefaults).forEach(function (key) {
                                var normalizedKey = String(key || '').toUpperCase();
                                if (!normalizedKey) {
                                    return;
                                }
                                agreementNumberDefaults[normalizedKey] = String(parsedDefaults[key] || '');
                            });
                        }
                    } catch (error) {
                        agreementNumberDefaults = {};
                    }
                }
            }

            var activeAgreementDefault = refs.agreementNumberInput ? String(refs.agreementNumberInput.value || '') : '';

            var clearControl = function (control) {
                if (!control) {
                    return;
                }

                if (control.type === 'checkbox' || control.type === 'radio') {
                    control.checked = false;
                } else {
                    control.value = '';
                }
                control.setCustomValidity('');
            };

            var setGroupState = function (nodes, isVisible) {
                nodes.forEach(function (node) {
                    node.classList.toggle('is-hidden', !isVisible);

                    var controls = node.querySelectorAll('input, select, textarea');
                    controls.forEach(function (control) {
                        control.disabled = !isVisible;
                        if (!isVisible) {
                            clearControl(control);
                        }
                    });
                });
            };

            var refreshIndefinite = function () {
                if (!refs.indefiniteCheckbox || !refs.dateEndInput) {
                    return;
                }

                var isIndefinite = !!refs.indefiniteCheckbox.checked;
                refs.dateEndInput.disabled = isIndefinite;
                refs.dateEndInput.required = !isIndefinite;

                if (isIndefinite) {
                    refs.dateEndInput.value = '';
                }
            };

            var refreshAgreementDefaultNumber = function () {
                if (!refs.agreementNumberInput) {
                    return;
                }

                var transactionType = refs.transactionTypeSelect ? String(refs.transactionTypeSelect.value || '').toUpperCase() : '';
                var transactionDefault = transactionType && Object.prototype.hasOwnProperty.call(agreementNumberDefaults, transactionType)
                    ? String(agreementNumberDefaults[transactionType] || '')
                    : '';

                var currentValue = String(refs.agreementNumberInput.value || '');
                var shouldReplace = currentValue === '' || currentValue === activeAgreementDefault;

                if (shouldReplace && transactionDefault !== '') {
                    refs.agreementNumberInput.value = transactionDefault;
                } else if (shouldReplace && transactionDefault === '' && transactionType) {
                    refs.agreementNumberInput.value = '';
                }

                if (transactionType) {
                    activeAgreementDefault = transactionDefault;
                }
            };

            var refreshNewClientType = function () {
                if (!refs.addNewClientCheckbox || !refs.addNewClientCheckbox.checked) {
                    setGroupState(refs.personFields, false);
                    setGroupState(refs.companyFields, false);
                    refs.requiredPerson.forEach(function (input) {
                        input.required = false;
                    });
                    refs.requiredCompany.forEach(function (input) {
                        input.required = false;
                    });
                    return;
                }

                var clientType = refs.newClientType ? normalize(refs.newClientType.value) : 'person';
                var isCompany = clientType === 'company';

                setGroupState(refs.personFields, !isCompany);
                setGroupState(refs.companyFields, isCompany);

                refs.requiredPerson.forEach(function (input) {
                    input.required = !isCompany;
                });
                refs.requiredCompany.forEach(function (input) {
                    input.required = isCompany;
                });
            };

            var refreshNewClientCorrespondence = function () {
                if (!refs.corrWrap) {
                    return;
                }

                var enabled = refs.addNewClientCheckbox && refs.addNewClientCheckbox.checked;
                var isSame = refs.corrSame ? refs.corrSame.checked : true;
                var shouldShow = enabled && !isSame;

                refs.corrWrap.classList.toggle('is-hidden', !shouldShow);

                var corrControls = refs.corrWrap.querySelectorAll('input, select, textarea');
                corrControls.forEach(function (control) {
                    control.disabled = !shouldShow;
                    if (!shouldShow) {
                        clearControl(control);
                    }
                });

                refs.corrRequired.forEach(function (input) {
                    input.required = shouldShow;
                });
            };

            var bindAdditionalClientCard = function (card) {
                if (!card || card.getAttribute('data-eocrm-additional-client-bound') === '1') {
                    return;
                }

                card.setAttribute('data-eocrm-additional-client-bound', '1');

                var typeSelect = card.querySelector('[data-eocrm-additional-client-type]');
                var personFields = card.querySelectorAll('.eocrm-agreement-additional-client-person');
                var companyFields = card.querySelectorAll('.eocrm-agreement-additional-client-company');
                var requiredPerson = card.querySelectorAll('[data-eocrm-additional-required-person]');
                var requiredCompany = card.querySelectorAll('[data-eocrm-additional-required-company]');
                var requiredMain = card.querySelectorAll('[data-eocrm-additional-required-main]');
                var corrSame = card.querySelector('[data-eocrm-additional-corr-same]');
                var corrWrap = card.querySelector('.eocrm-agreement-additional-client-corr');
                var corrRequired = card.querySelectorAll('[data-eocrm-additional-corr-field]');
                var removeButton = card.querySelector('[data-eocrm-additional-new-client-remove]');

                var refreshType = function () {
                    var enabled = refs.addNewClientCheckbox && refs.addNewClientCheckbox.checked;
                    var clientType = typeSelect ? normalize(typeSelect.value) : 'person';
                    var isCompany = clientType === 'company';

                    setGroupState(personFields, enabled && !isCompany);
                    setGroupState(companyFields, enabled && isCompany);

                    requiredPerson.forEach(function (input) {
                        input.required = enabled && !isCompany;
                    });
                    requiredCompany.forEach(function (input) {
                        input.required = enabled && isCompany;
                    });
                    requiredMain.forEach(function (input) {
                        input.required = enabled;
                    });
                };

                var refreshCorrespondence = function () {
                    if (!corrWrap) {
                        return;
                    }

                    var enabled = refs.addNewClientCheckbox && refs.addNewClientCheckbox.checked;
                    var same = corrSame ? corrSame.checked : true;
                    var shouldShow = enabled && !same;

                    corrWrap.classList.toggle('is-hidden', !shouldShow);
                    corrWrap.querySelectorAll('input, select, textarea').forEach(function (control) {
                        control.disabled = !shouldShow;
                        if (!shouldShow) {
                            clearControl(control);
                        }
                    });
                    corrRequired.forEach(function (input) {
                        input.required = shouldShow;
                    });
                };

                if (typeSelect) {
                    typeSelect.addEventListener('change', refreshType);
                }
                if (corrSame) {
                    corrSame.addEventListener('change', refreshCorrespondence);
                }
                if (removeButton) {
                    removeButton.addEventListener('click', function () {
                        card.remove();
                    });
                }

                refreshType();
                refreshCorrespondence();
            };

            var refreshAdditionalClients = function () {
                if (!refs.additionalClientList) {
                    return;
                }

                refs.additionalClientList.querySelectorAll('[data-eocrm-additional-new-client]').forEach(function (card) {
                    bindAdditionalClientCard(card);
                });
            };

            var addAdditionalClientCard = function () {
                if (!refs.additionalClientList || !refs.additionalClientTemplate) {
                    return;
                }

                var nextIndex = parseInt(refs.additionalClientList.getAttribute('data-next-index') || '0', 10);
                if (!(nextIndex >= 0)) {
                    nextIndex = 0;
                }

                var html = refs.additionalClientTemplate.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                refs.additionalClientList.insertAdjacentHTML('beforeend', html);
                refs.additionalClientList.setAttribute('data-next-index', String(nextIndex + 1));
                refreshAdditionalClients();
            };

            var refreshNewClient = function () {
                if (!refs.addNewClientCheckbox || !refs.newClientWrap) {
                    return;
                }

                var enabled = !!refs.addNewClientCheckbox.checked;
                refs.newClientWrap.classList.toggle('is-hidden', !enabled);

                var controls = refs.newClientWrap.querySelectorAll('input, select, textarea');
                controls.forEach(function (control) {
                    control.disabled = !enabled;
                    if (!enabled) {
                        clearControl(control);
                    }
                });

                if (!enabled && refs.corrSame) {
                    refs.corrSame.checked = true;
                }

                refs.requiredMain.forEach(function (input) {
                    input.required = enabled;
                });

                refreshNewClientType();
                refreshNewClientCorrespondence();
                refreshAdditionalClients();
            };

            var refreshClientSearch = function () {
                if (!refs.clientOptions || !refs.clientOptions.length) {
                    if (refs.clientEmpty) {
                        refs.clientEmpty.classList.add('is-hidden');
                    }
                    return;
                }

                var query = refs.clientSearchInput ? normalizeForSearch(refs.clientSearchInput.value) : '';
                var queryTokens = query === '' ? [] : query.split(' ').filter(function (token) {
                    return token !== '';
                });
                var visibleCount = 0;

                refs.clientOptions.forEach(function (option) {
                    var label = normalizeForSearch(option.getAttribute('data-eocrm-client-label') || option.textContent || '');
                    var checkbox = option.querySelector('input[type="checkbox"]');
                    var isChecked = checkbox ? !!checkbox.checked : false;
                    var shouldShow = false;

                    if (query === '') {
                        shouldShow = isChecked;
                    } else {
                        shouldShow = queryTokens.every(function (token) {
                            return label.indexOf(token) !== -1;
                        });
                    }

                    option.classList.toggle('is-hidden', !shouldShow);
                    if (shouldShow) {
                        visibleCount++;
                    }
                });

                if (refs.clientEmpty) {
                    var showEmpty = query !== '' && visibleCount === 0;
                    refs.clientEmpty.classList.toggle('is-hidden', !showEmpty);
                }
            };

            if (refs.indefiniteCheckbox) {
                refs.indefiniteCheckbox.addEventListener('change', refreshIndefinite);
            }

            if (refs.transactionTypeSelect) {
                refs.transactionTypeSelect.addEventListener('change', refreshAgreementDefaultNumber);
            }

            if (refs.addNewClientCheckbox) {
                refs.addNewClientCheckbox.addEventListener('change', refreshNewClient);
            }

            if (refs.additionalClientAdd) {
                refs.additionalClientAdd.addEventListener('click', addAdditionalClientCard);
            }

            if (refs.newClientType) {
                refs.newClientType.addEventListener('change', refreshNewClientType);
            }

            if (refs.corrSame) {
                refs.corrSame.addEventListener('change', refreshNewClientCorrespondence);
            }

            if (refs.clientSearchInput) {
                refs.clientSearchInput.addEventListener('input', refreshClientSearch);
                refs.clientSearchInput.addEventListener('change', refreshClientSearch);
                refs.clientSearchInput.addEventListener('keyup', refreshClientSearch);
            }

            if (refs.clientOptions && refs.clientOptions.length) {
                refs.clientOptions.forEach(function (option) {
                    var checkbox = option.querySelector('input[type="checkbox"]');
                    if (!checkbox) {
                        return;
                    }
                    checkbox.addEventListener('change', refreshClientSearch);
                });
            }

            refreshIndefinite();
            refreshAgreementDefaultNumber();
            refreshAdditionalClients();
            refreshNewClient();
            refreshClientSearch();
        });
    }

    function bindInputButtonStates() {
        var selector = [
            '.eocrm-wrap .eocrm-form-field-checkbox label',
            '.eocrm-wrap label.eocrm-checkbox-item',
            '.eocrm-wrap .eocrm-inline-checkboxes label',
            '.eocrm-wrap .eocrm-checkbox-item-stack > label'
        ].join(',');
        var labels = document.querySelectorAll(selector);

        labels.forEach(function (label) {
            var input = label.querySelector('input[type="checkbox"], input[type="radio"]');
            if (!input) {
                return;
            }

            var refreshGroup = function () {
                if (input.type === 'radio' && input.name) {
                    labels.forEach(function (groupLabel) {
                        var radio = groupLabel.querySelector('input[type="radio"]');
                        if (!radio || radio.name !== input.name || radio.form !== input.form) {
                            return;
                        }
                        groupLabel.classList.toggle('is-eocrm-checked', radio.checked);
                    });
                    return;
                }

                label.classList.toggle('is-eocrm-checked', input.checked);
            };

            input.addEventListener('change', refreshGroup);
            refreshGroup();
        });
    }

    function parseAttachmentIds(rawValue) {
        if (typeof rawValue !== 'string' || rawValue.trim() === '') {
            return [];
        }

        var ids = [];
        rawValue.split(',').forEach(function (part) {
            var id = parseInt(part, 10);
            if (!(id > 0) || ids.indexOf(id) !== -1) {
                return;
            }

            ids.push(id);
        });

        return ids;
    }

    function extractAttachmentPreviewUrl(data) {
        if (!data || typeof data !== 'object') {
            return '';
        }

        if (data.sizes && data.sizes.thumbnail && data.sizes.thumbnail.url) {
            return String(data.sizes.thumbnail.url);
        }
        if (data.sizes && data.sizes.medium && data.sizes.medium.url) {
            return String(data.sizes.medium.url);
        }
        if (data.url) {
            return String(data.url);
        }

        return '';
    }

    function createGalleryItem(id, imageUrl) {
        var item = document.createElement('span');
        item.className = 'eocrm-gallery-item';
        item.setAttribute('data-eocrm-gallery-id', String(id));
        item.setAttribute('draggable', 'true');

        if (imageUrl) {
            var image = document.createElement('img');
            image.className = 'eocrm-thumb';
            image.src = imageUrl;
            image.alt = 'Zdjecie galerii';
            item.appendChild(image);
        } else {
            var placeholder = document.createElement('span');
            placeholder.className = 'eocrm-gallery-placeholder';
            placeholder.textContent = 'Zdjecie #' + String(id);
            item.appendChild(placeholder);
        }

        var removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'eocrm-gallery-remove';
        removeButton.setAttribute('data-eocrm-gallery-remove', '1');
        removeButton.setAttribute('aria-label', 'Usun zdjecie');
        removeButton.textContent = 'x';
        item.appendChild(removeButton);

        return item;
    }

    function syncGalleryInputFromPreview(input, preview) {
        if (!input || !preview) {
            return [];
        }

        var ids = [];
        var items = preview.querySelectorAll('.eocrm-gallery-item[data-eocrm-gallery-id]');

        items.forEach(function (item) {
            var id = parseInt(item.getAttribute('data-eocrm-gallery-id') || '', 10);
            if (!(id > 0) || ids.indexOf(id) !== -1) {
                return;
            }
            ids.push(id);
        });

        input.value = ids.join(',');
        return ids;
    }

    function normalizeGalleryPreviewMarkup(input, preview) {
        if (!input || !preview) {
            return;
        }

        var idsFromInput = parseAttachmentIds(input.value);
        var previewState = function () {
            var state = {
                ids: [],
                urls: {}
            };

            var items = preview.querySelectorAll('.eocrm-gallery-item[data-eocrm-gallery-id]');
            items.forEach(function (item) {
                var id = parseInt(item.getAttribute('data-eocrm-gallery-id') || '', 10);
                if (!(id > 0) || state.ids.indexOf(id) !== -1) {
                    return;
                }

                state.ids.push(id);
                var image = item.querySelector('img.eocrm-thumb');
                if (image && image.getAttribute('src')) {
                    state.urls[id] = image.getAttribute('src');
                }
            });

            return state;
        };

        var rebuildPreviewFromInput = function () {
            if (!idsFromInput.length) {
                return false;
            }

            var state = previewState();
            var sameOrder = state.ids.length === idsFromInput.length;
            if (sameOrder) {
                idsFromInput.forEach(function (id, index) {
                    if (state.ids[index] !== id) {
                        sameOrder = false;
                    }
                });
            }

            if (sameOrder) {
                return false;
            }

            renderGalleryPreview(preview, idsFromInput, state.urls);
            syncGalleryInputFromPreview(input, preview);
            return true;
        };

        var structuredItems = preview.querySelectorAll('.eocrm-gallery-item[data-eocrm-gallery-id]');
        if (!structuredItems.length) {
            var legacyThumbs = preview.querySelectorAll('img.eocrm-thumb');
            if (legacyThumbs.length) {
                var rebuiltItems = [];

                legacyThumbs.forEach(function (thumb, index) {
                    var id = idsFromInput[index] || 0;
                    var url = thumb.getAttribute('src') || '';
                    if (!(id > 0) || url === '') {
                        return;
                    }

                    rebuiltItems.push(createGalleryItem(id, url));
                });

                if (rebuiltItems.length) {
                    preview.innerHTML = '';
                    rebuiltItems.forEach(function (item) {
                        preview.appendChild(item);
                    });
                }
            }
        } else {
            structuredItems.forEach(function (item) {
                var id = parseInt(item.getAttribute('data-eocrm-gallery-id') || '', 10);
                if (!(id > 0)) {
                    item.remove();
                    return;
                }

                item.setAttribute('draggable', 'true');
                if (!item.querySelector('[data-eocrm-gallery-remove]')) {
                    var removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'eocrm-gallery-remove';
                    removeButton.setAttribute('data-eocrm-gallery-remove', '1');
                    removeButton.setAttribute('aria-label', 'Usun zdjecie');
                    removeButton.textContent = 'x';
                    item.appendChild(removeButton);
                }
            });
        }

        if (rebuildPreviewFromInput()) {
            return;
        }

        syncGalleryInputFromPreview(input, preview);
    }

    function collectGalleryPreviewState(input, preview) {
        var orderedIds = [];
        var urlById = {};

        if (!preview) {
            return {
                ids: parseAttachmentIds(input ? input.value : ''),
                urls: urlById
            };
        }

        var items = preview.querySelectorAll('.eocrm-gallery-item[data-eocrm-gallery-id]');
        items.forEach(function (item) {
            var id = parseInt(item.getAttribute('data-eocrm-gallery-id') || '', 10);
            if (!(id > 0) || orderedIds.indexOf(id) !== -1) {
                return;
            }

            orderedIds.push(id);

            var image = item.querySelector('img.eocrm-thumb');
            if (image && image.getAttribute('src')) {
                urlById[id] = image.getAttribute('src');
            }
        });

        parseAttachmentIds(input ? input.value : '').forEach(function (id) {
            if (orderedIds.indexOf(id) === -1) {
                orderedIds.push(id);
            }
        });

        return {
            ids: orderedIds,
            urls: urlById
        };
    }

    function renderGalleryPreview(preview, ids, urlById) {
        if (!preview) {
            return;
        }

        preview.innerHTML = '';
        ids.forEach(function (id) {
            if (!(id > 0)) {
                return;
            }

            var imageUrl = urlById[id] || '';
            preview.appendChild(createGalleryItem(id, imageUrl));
        });
    }

    function clearGalleryDragState(preview) {
        if (!preview) {
            return;
        }

        var items = preview.querySelectorAll('.eocrm-gallery-item');
        items.forEach(function (item) {
            item.classList.remove('is-dragging');
            item.classList.remove('is-drag-target');
        });
    }

    function bindGalleryPreviewInteractions(input, preview) {
        if (!input || !preview || preview.getAttribute('data-eocrm-gallery-bound') === '1') {
            return;
        }

        preview.setAttribute('data-eocrm-gallery-bound', '1');

        preview.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            var removeButton = target.closest('[data-eocrm-gallery-remove]');
            if (!removeButton) {
                return;
            }

            var item = removeButton.closest('.eocrm-gallery-item');
            if (!item) {
                return;
            }

            item.remove();
            syncGalleryInputFromPreview(input, preview);
        });

        preview.addEventListener('dragstart', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            var item = target.closest('.eocrm-gallery-item');
            if (!item) {
                return;
            }

            clearGalleryDragState(preview);
            item.classList.add('is-dragging');

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.getAttribute('data-eocrm-gallery-id') || '');
            }
        });

        preview.addEventListener('dragover', function (event) {
            var dragging = preview.querySelector('.eocrm-gallery-item.is-dragging');
            if (!dragging) {
                return;
            }

            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }

            var target = event.target;
            if (!(target instanceof Element)) {
                clearGalleryDragState(preview);
                dragging.classList.add('is-dragging');
                return;
            }

            var targetItem = target.closest('.eocrm-gallery-item');
            clearGalleryDragState(preview);
            dragging.classList.add('is-dragging');

            if (targetItem && targetItem !== dragging) {
                targetItem.classList.add('is-drag-target');
            }
        });

        preview.addEventListener('drop', function (event) {
            var dragging = preview.querySelector('.eocrm-gallery-item.is-dragging');
            if (!dragging) {
                return;
            }

            event.preventDefault();

            var target = event.target;
            var targetItem = target instanceof Element ? target.closest('.eocrm-gallery-item') : null;

            if (targetItem && targetItem !== dragging) {
                var rect = targetItem.getBoundingClientRect();
                var beforeTarget = event.clientX < rect.left + rect.width / 2;
                if (beforeTarget) {
                    preview.insertBefore(dragging, targetItem);
                } else {
                    preview.insertBefore(dragging, targetItem.nextSibling);
                }
            } else if (!targetItem) {
                preview.appendChild(dragging);
            }

            clearGalleryDragState(preview);
            syncGalleryInputFromPreview(input, preview);
        });

        preview.addEventListener('dragend', function () {
            clearGalleryDragState(preview);
            syncGalleryInputFromPreview(input, preview);
        });
    }

    function bindMediaPickerGallery(button, input, preview) {
        if (!button || !input || !preview) {
            return;
        }

        normalizeGalleryPreviewMarkup(input, preview);
        bindGalleryPreviewInteractions(input, preview);

        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }

        button.addEventListener('click', function () {
            var frame = wp.media({
                title: 'Wybierz zdjecia',
                button: { text: 'Uzyj zdjec' },
                library: { type: 'image' },
                multiple: true
            });

            frame.on('select', function () {
                var selection = frame.state().get('selection');
                if (!selection) {
                    return;
                }

                var current = collectGalleryPreviewState(input, preview);
                var ids = current.ids.slice();
                var urlById = Object.assign({}, current.urls);

                selection.each(function (attachment) {
                    var data = attachment.toJSON();
                    var id = data && data.id ? parseInt(data.id, 10) : 0;
                    if (!(id > 0)) {
                        return;
                    }

                    if (ids.indexOf(id) === -1) {
                        ids.push(id);
                    }

                    var url = extractAttachmentPreviewUrl(data);
                    if (url !== '') {
                        urlById[id] = url;
                    }
                });

                renderGalleryPreview(preview, ids, urlById);
                syncGalleryInputFromPreview(input, preview);
            });

            frame.open();
        });
    }

    function renderSingleMediaPreview(preview, imageUrl, altText, removeLabel) {
        if (!preview) {
            return;
        }

        if (!imageUrl) {
            preview.innerHTML = '';
            return;
        }

        var item = document.createElement('span');
        item.className = 'eocrm-single-media-item';
        item.setAttribute('data-eocrm-single-item', '1');

        var image = document.createElement('img');
        image.className = 'eocrm-thumb';
        image.src = imageUrl;
        image.alt = altText || 'Podglad';
        item.appendChild(image);

        var removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'eocrm-gallery-remove';
        removeButton.setAttribute('data-eocrm-single-remove', '1');
        removeButton.setAttribute('aria-label', removeLabel || 'Usun plik');
        removeButton.textContent = 'x';
        item.appendChild(removeButton);

        preview.innerHTML = '';
        preview.appendChild(item);
    }

    function normalizeSingleMediaPreview(preview) {
        if (!preview) {
            return;
        }

        if (preview.querySelector('[data-eocrm-single-remove]')) {
            return;
        }

        var legacyThumb = preview.querySelector('img.eocrm-thumb');
        if (!legacyThumb) {
            return;
        }

        renderSingleMediaPreview(
            preview,
            legacyThumb.getAttribute('src') || '',
            legacyThumb.getAttribute('alt') || 'Podglad',
            'Usun plik'
        );
    }

    function bindSingleMediaPreviewInteractions(input, preview) {
        if (!input || !preview || preview.getAttribute('data-eocrm-single-bound') === '1') {
            return;
        }

        preview.setAttribute('data-eocrm-single-bound', '1');
        preview.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            var removeButton = target.closest('[data-eocrm-single-remove]');
            if (!removeButton) {
                return;
            }

            input.value = '';
            preview.innerHTML = '';
        });
    }

    function bindMediaPickerSingle(button, input, preview) {
        if (!button || !input || !preview) {
            return;
        }

        normalizeSingleMediaPreview(preview);
        bindSingleMediaPreviewInteractions(input, preview);

        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }

        button.addEventListener('click', function () {
            var frame = wp.media({
                title: 'Wybierz plik',
                button: { text: 'Uzyj pliku' },
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first();
                if (!attachment) {
                    return;
                }

                var data = attachment.toJSON();
                if (!data || !data.id) {
                    return;
                }

                input.value = data.id;
                renderSingleMediaPreview(
                    preview,
                    extractAttachmentPreviewUrl(data),
                    'Podglad',
                    'Usun plik'
                );
            });

            frame.open();
        });
    }

    function normalizeFloorPlanItems(rawItems) {
        if (!Array.isArray(rawItems)) {
            return [];
        }

        var normalized = [];
        var usedIds = {};

        rawItems.forEach(function (item) {
            if (!item || typeof item !== 'object') {
                return;
            }

            var attachmentId = parseInt(item.attachment_id || item.attachmentId || '', 10);
            if (!(attachmentId > 0)) {
                attachmentId = 0;
            }

            if (attachmentId > 0 && usedIds[attachmentId]) {
                return;
            }

            if (attachmentId > 0) {
                usedIds[attachmentId] = true;
            }

            var label = trimText(item.label || '');
            if (label === '') {
                label = 'Poziom ' + String(normalized.length + 1);
            }

            var previewUrl = typeof item.preview_url === 'string' ? item.preview_url : '';
            normalized.push({
                attachment_id: attachmentId,
                label: label,
                preview_url: previewUrl
            });
        });

        return normalized;
    }

    function parseFloorPlanItemsFromInput(input) {
        if (!input) {
            return [];
        }

        var raw = (input.value || '').trim();
        if (raw === '') {
            return [];
        }

        try {
            return normalizeFloorPlanItems(JSON.parse(raw));
        } catch (error) {
            return [];
        }
    }

    function getFloorPlanPreviewUrl(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }

        if (typeof item.preview_url === 'string' && item.preview_url !== '') {
            return item.preview_url;
        }

        return '';
    }

    function syncFloorPlanInput(input, items) {
        if (!input) {
            return;
        }

        var payload = [];
        normalizeFloorPlanItems(items).forEach(function (item, index) {
            if (!(item.attachment_id > 0)) {
                return;
            }

            payload.push({
                attachment_id: item.attachment_id,
                label: trimText(item.label || '') || ('Poziom ' + String(index + 1)),
                position: payload.length
            });
        });

        input.value = payload.length ? JSON.stringify(payload) : '';
    }

    function renderFloorPlanBuilderState(builder, input, items) {
        if (!builder || !input) {
            return;
        }

        var list = builder.querySelector('[data-eocrm-floor-plan-list]');
        if (!list) {
            return;
        }

        list.innerHTML = '';
        if (!items.length) {
            var emptyState = document.createElement('p');
            emptyState.className = 'eocrm-muted eocrm-floor-plan-empty';
            emptyState.textContent = 'Brak dodanych rzutow poziomow.';
            list.appendChild(emptyState);
            syncFloorPlanInput(input, items);
            return;
        }

        items.forEach(function (item, index) {
            var row = document.createElement('div');
            row.className = 'eocrm-floor-plan-row';
            row.setAttribute('data-eocrm-floor-plan-row-index', String(index));

            var thumb = document.createElement('span');
            thumb.className = 'eocrm-floor-plan-thumb';
            var previewUrl = getFloorPlanPreviewUrl(item);
            if (previewUrl !== '') {
                var image = document.createElement('img');
                image.className = 'eocrm-thumb';
                image.src = previewUrl;
                image.alt = item.label || ('Rzut poziomu ' + String(index + 1));
                thumb.appendChild(image);
            } else {
                var placeholder = document.createElement('span');
                placeholder.className = 'eocrm-gallery-placeholder';
                placeholder.textContent = 'Brak podgladu';
                thumb.appendChild(placeholder);
            }

            var fields = document.createElement('span');
            fields.className = 'eocrm-floor-plan-fields';
            var labelCaption = document.createElement('label');
            labelCaption.textContent = 'Nazwa poziomu';
            var labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.className = 'eocrm-floor-plan-label-input';
            labelInput.value = item.label || ('Poziom ' + String(index + 1));
            labelInput.setAttribute('data-eocrm-floor-plan-label', String(index));
            fields.appendChild(labelCaption);
            fields.appendChild(labelInput);

            var controls = document.createElement('span');
            controls.className = 'eocrm-floor-plan-actions';
            var pickButton = document.createElement('button');
            pickButton.type = 'button';
            pickButton.className = 'eocrm-btn';
            pickButton.textContent = item.attachment_id > 0 ? 'Zmien rzut' : 'Wybierz rzut';
            pickButton.setAttribute('data-eocrm-floor-plan-pick', String(index));
            controls.appendChild(pickButton);

            var removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'eocrm-btn';
            removeButton.textContent = 'Usun';
            removeButton.setAttribute('data-eocrm-floor-plan-remove', String(index));
            controls.appendChild(removeButton);

            var moveUpButton = document.createElement('button');
            moveUpButton.type = 'button';
            moveUpButton.className = 'eocrm-btn';
            moveUpButton.textContent = 'W gore';
            moveUpButton.setAttribute('data-eocrm-floor-plan-move-up', String(index));
            controls.appendChild(moveUpButton);

            var moveDownButton = document.createElement('button');
            moveDownButton.type = 'button';
            moveDownButton.className = 'eocrm-btn';
            moveDownButton.textContent = 'W dol';
            moveDownButton.setAttribute('data-eocrm-floor-plan-move-down', String(index));
            controls.appendChild(moveDownButton);

            row.appendChild(thumb);
            row.appendChild(fields);
            row.appendChild(controls);
            list.appendChild(row);
        });

        syncFloorPlanInput(input, items);
    }

    function bindFloorPlanBuilder(builder) {
        if (!builder || builder.getAttribute('data-eocrm-floor-plan-bound') === '1') {
            return;
        }

        var inputId = builder.getAttribute('data-target-input') || '';
        if (!inputId) {
            return;
        }

        var form = builder.closest('form');
        if (!form) {
            return;
        }

        var input = form.querySelector('#' + inputId);
        if (!input) {
            return;
        }

        builder.setAttribute('data-eocrm-floor-plan-bound', '1');

        var items = parseFloorPlanItemsFromInput(input);
        renderFloorPlanBuilderState(builder, input, items);

        builder.addEventListener('input', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            var labelInput = target.closest('[data-eocrm-floor-plan-label]');
            if (!labelInput) {
                return;
            }

            var index = parseInt(labelInput.getAttribute('data-eocrm-floor-plan-label') || '', 10);
            if (!(index >= 0) || !items[index]) {
                return;
            }

            items[index].label = trimText(labelInput.value || '');
            syncFloorPlanInput(input, items);
        });

        builder.addEventListener('click', function (event) {
            var rawTarget = event.target;
            var target = rawTarget instanceof Element
                ? rawTarget
                : (rawTarget && rawTarget.parentElement ? rawTarget.parentElement : null);
            if (!target) {
                return;
            }

            var addButton = target.closest('[data-eocrm-floor-plan-add]');
            if (addButton) {
                event.preventDefault();
                event.stopPropagation();
                items.push({
                    attachment_id: 0,
                    label: 'Poziom ' + String(items.length + 1),
                    preview_url: ''
                });
                renderFloorPlanBuilderState(builder, input, items);
                return;
            }

            var removeButton = target.closest('[data-eocrm-floor-plan-remove]');
            if (removeButton) {
                event.preventDefault();
                event.stopPropagation();
                var removeIndex = parseInt(removeButton.getAttribute('data-eocrm-floor-plan-remove') || '', 10);
                if (!(removeIndex >= 0) || !items[removeIndex]) {
                    return;
                }

                items.splice(removeIndex, 1);
                renderFloorPlanBuilderState(builder, input, items);
                return;
            }

            var moveUpButton = target.closest('[data-eocrm-floor-plan-move-up]');
            if (moveUpButton) {
                event.preventDefault();
                event.stopPropagation();
                var moveUpIndex = parseInt(moveUpButton.getAttribute('data-eocrm-floor-plan-move-up') || '', 10);
                if (!(moveUpIndex >= 0) || !items[moveUpIndex]) {
                    return;
                }

                if (moveUpIndex > 0) {
                    var previousItem = items[moveUpIndex - 1];
                    items[moveUpIndex - 1] = items[moveUpIndex];
                    items[moveUpIndex] = previousItem;
                } else if (items.length > 1) {
                    var firstItem = items.shift();
                    if (firstItem) {
                        items.push(firstItem);
                    }
                }

                renderFloorPlanBuilderState(builder, input, items);
                return;
            }

            var moveDownButton = target.closest('[data-eocrm-floor-plan-move-down]');
            if (moveDownButton) {
                event.preventDefault();
                event.stopPropagation();
                var moveDownIndex = parseInt(moveDownButton.getAttribute('data-eocrm-floor-plan-move-down') || '', 10);
                if (!(moveDownIndex >= 0) || !items[moveDownIndex]) {
                    return;
                }

                if (moveDownIndex < items.length - 1) {
                    var nextItem = items[moveDownIndex + 1];
                    items[moveDownIndex + 1] = items[moveDownIndex];
                    items[moveDownIndex] = nextItem;
                } else if (items.length > 1) {
                    var lastItem = items.pop();
                    if (lastItem) {
                        items.unshift(lastItem);
                    }
                }

                renderFloorPlanBuilderState(builder, input, items);
                return;
            }

            var pickButton = target.closest('[data-eocrm-floor-plan-pick]');
            if (!pickButton || typeof wp === 'undefined' || !wp.media) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            var pickIndex = parseInt(pickButton.getAttribute('data-eocrm-floor-plan-pick') || '', 10);
            if (!(pickIndex >= 0) || !items[pickIndex]) {
                return;
            }

            var frame = wp.media({
                title: 'Wybierz rzut poziomu',
                button: { text: 'Uzyj rzutu' },
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first();
                if (!attachment) {
                    return;
                }

                var data = attachment.toJSON();
                var id = data && data.id ? parseInt(data.id, 10) : 0;
                if (!(id > 0)) {
                    return;
                }

                items[pickIndex].attachment_id = id;
                items[pickIndex].preview_url = extractAttachmentPreviewUrl(data);
                if (!items[pickIndex].label) {
                    items[pickIndex].label = 'Poziom ' + String(pickIndex + 1);
                }

                renderFloorPlanBuilderState(builder, input, items);
            });

            frame.open();
        });
    }

    function bindMediaClearButtons(form) {
        var clearButtons = form.querySelectorAll('[data-eocrm-media-clear]');
        if (!clearButtons.length) {
            return;
        }

        clearButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var inputId = button.getAttribute('data-target-input');
                var previewId = button.getAttribute('data-target-preview');
                if (!inputId || !previewId) {
                    return;
                }

                var input = form.querySelector('#' + inputId);
                var preview = form.querySelector('#' + previewId);
                if (input) {
                    input.value = '';
                }
                if (preview) {
                    preview.innerHTML = '';
                }
            });
        });
    }

    function createGoogleMapContext(form, refs) {
        var mapCanvas = refs.mapCanvas;
        if (!mapCanvas) {
            return null;
        }

        if (typeof window.google === 'undefined' || !window.google.maps) {
            mapCanvas.classList.add('eocrm-map-disabled');
            mapCanvas.innerHTML = '<p>Mapa Google nie jest dostepna. Uzyj przycisku "Otworz Google Maps" lub dodaj klucz API w ustawieniach.</p>';
            return null;
        }

        var config = getFrontendConfig();
        var defaultLat = parseFloat(config.mapsDefaultLat || '52.229676');
        var defaultLng = parseFloat(config.mapsDefaultLng || '21.012229');

        if (isNaN(defaultLat)) {
            defaultLat = 52.229676;
        }
        if (isNaN(defaultLng)) {
            defaultLng = 21.012229;
        }

        var latFromInput = parseDecimal(refs.latInput ? refs.latInput.value : '');
        var lngFromInput = parseDecimal(refs.lngInput ? refs.lngInput.value : '');

        var center = {
            lat: latFromInput !== null ? latFromInput : defaultLat,
            lng: lngFromInput !== null ? lngFromInput : defaultLng
        };

        var map = new window.google.maps.Map(mapCanvas, {
            center: center,
            zoom: latFromInput !== null && lngFromInput !== null ? 15 : 11,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true
        });

        var marker = new window.google.maps.Marker({
            position: center,
            map: map,
            draggable: true
        });

        var geocoder = new window.google.maps.Geocoder();

        var setInputsFromLatLng = function (latLng) {
            if (refs.latInput) {
                refs.latInput.value = latLng.lat().toFixed(7);
            }
            if (refs.lngInput) {
                refs.lngInput.value = latLng.lng().toFixed(7);
            }
        };

        var reverseGeocode = function (latLng) {
            if (!refs.addressInput || !geocoder) {
                return;
            }

            geocoder.geocode({ location: latLng }, function (results, status) {
                if (status === 'OK' && results && results.length) {
                    refs.addressInput.value = results[0].formatted_address;
                }
            });
        };

        var placeMarker = function (latLng, panTo, doReverseGeocode) {
            marker.setPosition(latLng);
            if (panTo) {
                map.panTo(latLng);
            }
            setInputsFromLatLng(latLng);
            if (doReverseGeocode) {
                reverseGeocode(latLng);
            }
        };

        map.addListener('click', function (event) {
            if (!event || !event.latLng) {
                return;
            }

            placeMarker(event.latLng, true, true);
        });

        marker.addListener('dragend', function (event) {
            if (!event || !event.latLng) {
                return;
            }

            placeMarker(event.latLng, false, true);
        });

        return {
            map: map,
            marker: marker,
            geocoder: geocoder,
            placeMarker: placeMarker,
            reverseGeocode: reverseGeocode
        };
    }

    function bindPropertyFormDynamics() {
        var forms = document.querySelectorAll('[data-eocrm-property-form]');
        if (!forms.length) {
            return;
        }

        forms.forEach(function (form) {
            var applyPropertyFormDraft = function () {
                var rawDraft = form.getAttribute('data-eocrm-form-draft');
                if (!rawDraft || rawDraft === '{}' || rawDraft === '[]') {
                    return;
                }

                var draft = null;
                try {
                    draft = JSON.parse(rawDraft);
                } catch (e) {
                    draft = null;
                }

                if (!draft || typeof draft !== 'object') {
                    return;
                }

                var ignoredFields = {
                    eocrm_action: true,
                    eocrm_nonce: true,
                    eocrm_property_update_nonce: true,
                    eocrm_property_delete_nonce: true,
                    gallery_attachment_ids: true,
                    floor_plan_items_json: true,
                    _wp_http_referer: true
                };

                var resolveFieldNodes = function (fieldName) {
                    var escapedName = String(fieldName).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                    var nodes = form.querySelectorAll('[name="' + escapedName + '"]');
                    if ((!nodes || !nodes.length) && escapedName.slice(-2) !== '[]') {
                        nodes = form.querySelectorAll('[name="' + escapedName + '[]"]');
                    }

                    return nodes;
                };

                var normalizeArrayValues = function (value) {
                    if (!Array.isArray(value)) {
                        return [];
                    }

                    return value.map(function (item) {
                        return String(item);
                    });
                };

                Object.keys(draft).forEach(function (fieldName) {
                    if (ignoredFields[fieldName]) {
                        return;
                    }

                    var fields = resolveFieldNodes(fieldName);
                    if (!fields || !fields.length) {
                        return;
                    }

                    var value = draft[fieldName];

                    if (Array.isArray(value)) {
                        var values = normalizeArrayValues(value);
                        fields.forEach(function (field) {
                            var tagName = (field.tagName || '').toLowerCase();
                            var fieldType = (field.type || '').toLowerCase();

                            if (fieldType === 'checkbox' || fieldType === 'radio') {
                                field.checked = values.indexOf(String(field.value)) !== -1;
                                return;
                            }

                            if (tagName === 'select' && field.multiple) {
                                Array.prototype.forEach.call(field.options, function (option) {
                                    option.selected = values.indexOf(String(option.value)) !== -1;
                                });
                                return;
                            }

                            if (values.length) {
                                field.value = values[0];
                            }
                        });

                        return;
                    }

                    var scalarValue = value === null || typeof value === 'undefined' ? '' : String(value);
                    fields.forEach(function (field) {
                        var tagName = (field.tagName || '').toLowerCase();
                        var fieldType = (field.type || '').toLowerCase();

                        if (fieldType === 'checkbox') {
                            var normalized = scalarValue.toLowerCase();
                            if (field.value && field.value !== 'on') {
                                field.checked = field.value === scalarValue;
                            } else {
                                field.checked = normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
                            }
                            return;
                        }

                        if (fieldType === 'radio') {
                            field.checked = field.value === scalarValue;
                            return;
                        }

                        if (tagName === 'select') {
                            field.value = scalarValue;
                            return;
                        }

                        field.value = scalarValue;
                    });
                });
            };

            var refs = {
                propertyType: form.querySelector('[data-eocrm-property-type]'),
                houseWrap: form.querySelector('.eocrm-house-type-wrap'),
                notPlotFields: form.querySelectorAll('.eocrm-not-plot'),
                notHouseFields: form.querySelectorAll('.eocrm-not-house'),
                domOnlyFields: form.querySelectorAll('.eocrm-dom-only'),
                plotHouseFields: form.querySelectorAll('.eocrm-plot-house'),
                plotShape: form.querySelector('[data-eocrm-plot-shape]'),
                plotSideA: form.querySelectorAll('.eocrm-plot-shape-side-a'),
                plotSideB: form.querySelectorAll('.eocrm-plot-shape-side-b'),
                plotSideC: form.querySelectorAll('.eocrm-plot-shape-side-c'),
                plotWrap: form.querySelector('.eocrm-plot-wrap'),
                noKw: form.querySelector('[data-eocrm-no-kw]'),
                kwInput: form.querySelector('[data-eocrm-kw-input]'),
                agreementSelect: form.querySelector('[data-eocrm-agreement-select]'),
                transactionDisplay: form.querySelector('[data-eocrm-transaction-display]'),
                tagSold: form.querySelector('[data-eocrm-tag-sold]'),
                tagRented: form.querySelector('[data-eocrm-tag-rented]'),
                priceHint: form.querySelector('[data-eocrm-price-hint]'),
                hasParking: form.querySelector('[data-eocrm-has-parking]'),
                parkingWrap: form.querySelector('.eocrm-parking-types'),
                parkingTypeToggles: form.querySelectorAll('[data-eocrm-parking-type-toggle]'),
                parkingTypeCounts: form.querySelectorAll('[data-eocrm-parking-count]'),
                extraToggles: form.querySelectorAll('[data-eocrm-extra-toggle]'),
                priceInput: form.querySelector('[data-eocrm-price]'),
                areaInput: form.querySelector('[data-eocrm-area]'),
                plotAreaInput: form.querySelector('[data-eocrm-plot-area]'),
                areaLabel: form.querySelector('[data-eocrm-area-label]'),
                ppmInput: form.querySelector('[data-eocrm-price-per-m2]'),
                mapAddress: form.querySelector('[data-eocrm-map-address]'),
                mapButton: form.querySelector('[data-eocrm-open-map]'),
                mapGeocodeButton: form.querySelector('[data-eocrm-map-geocode]'),
                mapCanvas: form.querySelector('[data-eocrm-map-canvas]'),
                latInput: form.querySelector('[data-eocrm-latitude]'),
                lngInput: form.querySelector('[data-eocrm-longitude]'),
                streetInput: form.querySelector('[data-eocrm-addr-street]'),
                buildingInput: form.querySelector('[data-eocrm-addr-building]'),
                postalInput: form.querySelector('[data-eocrm-addr-postal]'),
                cityInput: form.querySelector('[data-eocrm-addr-city]')
            };

            applyPropertyFormDraft();

            var galleryButton = form.querySelector('[data-eocrm-media-gallery]');
            var galleryInput = null;
            var galleryPreview = null;
            if (galleryButton) {
                var galleryInputId = galleryButton.getAttribute('data-target-input') || '';
                var galleryPreviewId = galleryButton.getAttribute('data-target-preview') || '';
                galleryInput = galleryInputId ? form.querySelector('#' + galleryInputId) : null;
                galleryPreview = galleryPreviewId ? form.querySelector('#' + galleryPreviewId) : null;
            }

            bindMediaPickerGallery(galleryButton, galleryInput, galleryPreview);
            var floorPlanBuilders = form.querySelectorAll('[data-eocrm-floor-plan-builder]');
            floorPlanBuilders.forEach(function (builder) {
                bindFloorPlanBuilder(builder);
            });
            bindMediaClearButtons(form);

            var mapCtx = createGoogleMapContext(form, {
                mapCanvas: refs.mapCanvas,
                addressInput: refs.mapAddress,
                latInput: refs.latInput,
                lngInput: refs.lngInput
            });

            var refreshPropertyType = function () {
                if (!refs.propertyType) {
                    return;
                }

                var type = normalize(refs.propertyType.value);
                var isDom = type === 'dom';
                var isPlot = type === 'dzialka';
                var isDomOrPlot = isDom || isPlot;

                if (refs.houseWrap) {
                    refs.houseWrap.classList.toggle('is-hidden', !isDom);
                    var houseSelect = refs.houseWrap.querySelector('select');
                    if (houseSelect) {
                        houseSelect.required = isDom;
                    }
                }

                refs.notPlotFields.forEach(function (field) {
                    field.classList.toggle('is-hidden', isPlot);
                });

                if (refs.plotWrap) {
                    refs.plotWrap.classList.toggle('is-hidden', !isDomOrPlot);
                    if (!isDomOrPlot && refs.plotShape) {
                        refs.plotShape.value = '';
                    }
                }

                refs.notHouseFields.forEach(function (field) {
                    field.classList.toggle('is-hidden', isDom);
                });

                refs.domOnlyFields.forEach(function (field) {
                    field.classList.toggle('is-hidden', !isDom);
                });

                refs.plotHouseFields.forEach(function (field) {
                    field.classList.toggle('is-hidden', !isDomOrPlot);
                    var input = field.querySelector('input, select, textarea');
                    if (!input) {
                        return;
                    }

                    if (!isDomOrPlot) {
                        input.value = '';
                    }
                });

                if (refs.streetInput) {
                    refs.streetInput.required = !isPlot;
                }
                if (refs.buildingInput) {
                    refs.buildingInput.required = !isPlot;
                }
                if (refs.postalInput) {
                    refs.postalInput.required = !isPlot;
                }
                if (refs.cityInput) {
                    refs.cityInput.required = true;
                }
                if (refs.areaLabel) {
                    refs.areaLabel.textContent = isPlot ? 'Wielkosc dzialki (m2)' : 'Metraz (m2)';
                }

                if (refs.plotAreaInput) {
                    refs.plotAreaInput.required = isDom;
                    if (!isDom) {
                        refs.plotAreaInput.value = '';
                    }
                }

                if (isPlot) {
                    refs.extraToggles.forEach(function (toggle) {
                        toggle.checked = false;
                    });
                    refreshExtraAreas();
                }

                refreshPlotShape();
            };

            var refreshPlotShape = function () {
                if (!refs.plotShape) {
                    return;
                }

                var shape = normalize(refs.plotShape.value);
                var showSideA = shape === 'kwadrat' || shape === 'prostokat' || shape === 'trojkat';
                var showSideB = shape === 'prostokat' || shape === 'trojkat';
                var showSideC = shape === 'trojkat';

                refs.plotSideA.forEach(function (el) {
                    el.classList.toggle('is-hidden', !showSideA);
                    var input = el.querySelector('input');
                    if (input) {
                        input.required = showSideA;
                        if (!showSideA) {
                            input.value = '';
                        }
                    }
                });

                refs.plotSideB.forEach(function (el) {
                    el.classList.toggle('is-hidden', !showSideB);
                    var input = el.querySelector('input');
                    if (input) {
                        input.required = showSideB;
                        if (!showSideB) {
                            input.value = '';
                        }
                    }
                });

                refs.plotSideC.forEach(function (el) {
                    el.classList.toggle('is-hidden', !showSideC);
                    var input = el.querySelector('input');
                    if (input) {
                        input.required = showSideC;
                        if (!showSideC) {
                            input.value = '';
                        }
                    }
                });
            };

            var refreshKw = function () {
                if (!refs.noKw || !refs.kwInput) {
                    return;
                }

                refs.kwInput.disabled = refs.noKw.checked;
                refs.kwInput.required = !refs.noKw.checked;
                if (refs.noKw.checked) {
                    refs.kwInput.value = '';
                }
            };

            var refreshTransactionFromAgreement = function () {
                if (!refs.agreementSelect || !refs.transactionDisplay) {
                    return;
                }

                var selectedOption = refs.agreementSelect.options[refs.agreementSelect.selectedIndex];
                var tx = selectedOption ? (selectedOption.getAttribute('data-transaction') || '') : '';
                refs.transactionDisplay.value = tx;

                if (refs.tagSold && refs.tagRented) {
                    refs.tagSold.disabled = tx !== 'SPRZEDAZ';
                    refs.tagRented.disabled = tx !== 'WYNAJEM';

                    if (tx !== 'SPRZEDAZ') {
                        refs.tagSold.checked = false;
                    }
                    if (tx !== 'WYNAJEM') {
                        refs.tagRented.checked = false;
                    }
                }

                if (refs.priceHint) {
                    refs.priceHint.textContent = tx === 'WYNAJEM' ? '(miesiecznie)' : '';
                }
            };

            var refreshParking = function () {
                var parkingActive = refs.hasParking && refs.hasParking.checked;

                if (refs.parkingWrap) {
                    refs.parkingWrap.classList.toggle('is-hidden', !parkingActive);
                }

                refs.parkingTypeToggles.forEach(function (checkbox) {
                    var type = checkbox.getAttribute('data-eocrm-parking-type-toggle');
                    if (!type) {
                        return;
                    }

                    refs.parkingTypeCounts.forEach(function (input) {
                        var inputType = input.getAttribute('data-eocrm-parking-count');
                        if (inputType !== type) {
                            return;
                        }

                        var shouldShow = parkingActive && checkbox.checked;
                        input.classList.toggle('is-hidden', !shouldShow);
                        input.required = shouldShow;
                        if (!shouldShow) {
                            input.value = '';
                        }
                    });
                });
            };

            var refreshExtraAreas = function () {
                refs.extraToggles.forEach(function (toggle) {
                    var key = toggle.getAttribute('data-eocrm-extra-toggle');
                    if (!key) {
                        return;
                    }

                    var targets = form.querySelectorAll('.eocrm-extra-' + key);
                    targets.forEach(function (target) {
                        target.classList.toggle('is-hidden', !toggle.checked);
                        var input = target.querySelector('input');
                        if (input) {
                            input.required = false;
                            if (!toggle.checked) {
                                input.value = '';
                            }
                        }
                    });
                });
            };

            var refreshPricePerM2 = function () {
                if (!refs.priceInput || !refs.areaInput || !refs.ppmInput) {
                    return;
                }

                var price = parseDecimal(refs.priceInput.value);
                var area = parseDecimal(refs.areaInput.value);

                if (price === null || area === null || area <= 0) {
                    refs.ppmInput.value = '';
                    return;
                }

                refs.ppmInput.value = (price / area).toFixed(2);
            };

            var openMap = function () {
                var query = '';
                if (refs.mapAddress && refs.mapAddress.value.trim() !== '') {
                    query = refs.mapAddress.value.trim();
                } else {
                    var parts = [];
                    if (refs.streetInput && refs.streetInput.value.trim() !== '') {
                        parts.push(refs.streetInput.value.trim());
                    }
                    if (refs.buildingInput && refs.buildingInput.value.trim() !== '') {
                        parts.push(refs.buildingInput.value.trim());
                    }
                    if (refs.postalInput && refs.postalInput.value.trim() !== '') {
                        parts.push(refs.postalInput.value.trim());
                    }
                    if (refs.cityInput && refs.cityInput.value.trim() !== '') {
                        parts.push(refs.cityInput.value.trim());
                    }
                    query = parts.join(' ');
                }

                if (query === '' && refs.latInput && refs.lngInput && refs.latInput.value && refs.lngInput.value) {
                    query = refs.latInput.value + ',' + refs.lngInput.value;
                }

                if (query === '') {
                    return;
                }

                window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query), '_blank', 'noopener');
            };

            var geocodeMapAddress = function () {
                if (!mapCtx || !mapCtx.geocoder || !refs.mapAddress) {
                    return;
                }

                var address = refs.mapAddress.value.trim();
                if (address === '') {
                    return;
                }

                mapCtx.geocoder.geocode({ address: address }, function (results, status) {
                    if (status !== 'OK' || !results || !results.length) {
                        return;
                    }

                    var result = results[0];
                    if (!result || !result.geometry || !result.geometry.location) {
                        return;
                    }

                    mapCtx.placeMarker(result.geometry.location, true, false);
                    refs.mapAddress.value = result.formatted_address || address;
                    if (result.geometry.viewport) {
                        mapCtx.map.fitBounds(result.geometry.viewport);
                    } else {
                        mapCtx.map.setZoom(15);
                    }
                });
            };

            var syncMapFromCoords = function () {
                if (!mapCtx || !refs.latInput || !refs.lngInput) {
                    return;
                }

                var lat = parseDecimal(refs.latInput.value);
                var lng = parseDecimal(refs.lngInput.value);
                if (lat === null || lng === null) {
                    return;
                }

                var latLng = new window.google.maps.LatLng(lat, lng);
                mapCtx.placeMarker(latLng, true, false);
            };

            if (refs.propertyType) {
                refs.propertyType.addEventListener('change', refreshPropertyType);
            }
            if (refs.plotShape) {
                refs.plotShape.addEventListener('change', refreshPlotShape);
            }
            if (refs.noKw) {
                refs.noKw.addEventListener('change', refreshKw);
            }
            if (refs.agreementSelect) {
                refs.agreementSelect.addEventListener('change', refreshTransactionFromAgreement);
            }
            if (refs.hasParking) {
                refs.hasParking.addEventListener('change', refreshParking);
            }
            refs.parkingTypeToggles.forEach(function (checkbox) {
                checkbox.addEventListener('change', refreshParking);
            });
            refs.extraToggles.forEach(function (checkbox) {
                checkbox.addEventListener('change', refreshExtraAreas);
            });
            if (refs.priceInput) {
                refs.priceInput.addEventListener('input', refreshPricePerM2);
            }
            if (refs.areaInput) {
                refs.areaInput.addEventListener('input', refreshPricePerM2);
            }
            if (refs.mapButton) {
                refs.mapButton.addEventListener('click', openMap);
            }
            if (refs.mapGeocodeButton) {
                refs.mapGeocodeButton.addEventListener('click', geocodeMapAddress);
            }
            if (refs.latInput) {
                refs.latInput.addEventListener('change', syncMapFromCoords);
            }
            if (refs.lngInput) {
                refs.lngInput.addEventListener('change', syncMapFromCoords);
            }

            refreshPropertyType();
            refreshPlotShape();
            refreshKw();
            refreshTransactionFromAgreement();
            refreshParking();
            refreshExtraAreas();
            refreshPricePerM2();
            syncMapFromCoords();
        });
    }


    function bindSearchFormDynamics() {
        var forms = document.querySelectorAll('[data-eocrm-search-form]');
        if (!forms.length) {
            return;
        }

        var setGroupVisibility = function (elements, isVisible) {
            elements.forEach(function (element) {
                element.classList.toggle('is-hidden', !isVisible);

                var controls = element.querySelectorAll('input, select, textarea');
                controls.forEach(function (control) {
                    control.disabled = !isVisible;
                    if (isVisible) {
                        control.setCustomValidity('');
                        return;
                    }

                    if (control.type === 'checkbox' || control.type === 'radio') {
                        control.checked = false;
                    } else {
                        control.value = '';
                    }
                    control.setCustomValidity('');
                });
            });
        };

            forms.forEach(function (form) {
                var refs = {
                    agreementSelect: form.querySelector('[data-eocrm-search-agreement-select]'),
                    transactionDisplay: form.querySelector('[data-eocrm-search-transaction-display]'),
                    propertyType: form.querySelector('[data-eocrm-search-property-type]'),
                    propertyTypes: form.querySelectorAll('[data-eocrm-search-property-type]'),
                    notPlotFields: form.querySelectorAll('.eocrm-search-not-plot'),
                    notHouseFields: form.querySelectorAll('.eocrm-search-not-house'),
                    extraSelects: form.querySelectorAll('[data-eocrm-search-extra-select]'),
                    amenityToggles: form.querySelectorAll('[data-eocrm-search-amenity-toggle]')
                };

            var refreshTransactionFromAgreement = function () {
                if (!refs.agreementSelect || !refs.transactionDisplay) {
                    return;
                }

                var option = refs.agreementSelect.options[refs.agreementSelect.selectedIndex];
                var transaction = option ? (option.getAttribute('data-transaction') || '') : '';
                refs.transactionDisplay.value = transaction;
            };

            var refreshPropertyType = function () {
                if (!refs.propertyTypes || !refs.propertyTypes.length) {
                    return;
                }

                var selectedTypes = [];
                refs.propertyTypes.forEach(function (control) {
                    var value = normalize(control.value);
                    if (!value) {
                        return;
                    }

                    if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) {
                        return;
                    }

                    if (selectedTypes.indexOf(value) === -1) {
                        selectedTypes.push(value);
                    }
                });

                var isSingleType = selectedTypes.length === 1;
                var isPlot = isSingleType && selectedTypes[0] === 'dzialka';
                var isHouse = isSingleType && selectedTypes[0] === 'dom';

                if (refs.propertyTypes.length > 1) {
                    refs.propertyTypes.forEach(function (control, index) {
                        control.required = selectedTypes.length === 0 && index === 0;
                    });
                }

                setGroupVisibility(refs.notPlotFields, !isPlot);
                setGroupVisibility(refs.notHouseFields, !isPlot && !isHouse);
                refs.amenityToggles.forEach(function (toggle) {
                    refreshAmenityCount(toggle);
                });
            };

            var refreshAmenityCount = function (toggle) {
                var key = toggle.getAttribute('data-eocrm-search-amenity-toggle');
                if (!key) {
                    return;
                }

                var targets = form.querySelectorAll('[data-eocrm-search-amenity-count="' + key + '"]');
                var shouldShow = !toggle.disabled && !!toggle.checked;

                targets.forEach(function (target) {
                    target.classList.toggle('is-hidden', !shouldShow);

                    var controls = target.querySelectorAll('input, select, textarea');
                    controls.forEach(function (control) {
                        control.disabled = !shouldShow;
                        control.required = false;
                        if (!shouldShow) {
                            control.value = '';
                            control.setCustomValidity('');
                        }
                    });
                });
            };

            var refreshExtraAreaField = function (select) {
                var key = select.getAttribute('data-eocrm-search-extra-key');
                if (!key) {
                    return;
                }

                var shouldShow = !select.disabled && normalize(select.value) === '1';
                var targets = form.querySelectorAll('.eocrm-search-extra-' + key);

                targets.forEach(function (target) {
                    target.classList.toggle('is-hidden', !shouldShow);

                    var controls = target.querySelectorAll('input, select, textarea');
                    controls.forEach(function (control) {
                        control.disabled = !shouldShow;
                        control.required = false;
                        if (!shouldShow) {
                            control.value = '';
                            control.setCustomValidity('');
                        }
                    });
                });
            };

            var bindRangeValidation = function (key, label) {
                var fromInput = form.querySelector('[data-eocrm-range-from="' + key + '"]');
                var toInput = form.querySelector('[data-eocrm-range-to="' + key + '"]');
                if (!fromInput || !toInput) {
                    return;
                }

                var refreshRange = function () {
                    var from = parseDecimal(fromInput.value);
                    var to = parseDecimal(toInput.value);
                    var message = '';

                    if (from !== null && to !== null && from > to) {
                        message = label + ': wartosc "od" nie moze byc wieksza niz "do".';
                    }

                    fromInput.setCustomValidity(message);
                    toInput.setCustomValidity(message);
                };

                fromInput.addEventListener('input', refreshRange);
                toInput.addEventListener('input', refreshRange);
                fromInput.addEventListener('change', refreshRange);
                toInput.addEventListener('change', refreshRange);
                refreshRange();
            };

            if (refs.agreementSelect) {
                refs.agreementSelect.addEventListener('change', refreshTransactionFromAgreement);
            }

            refs.propertyTypes.forEach(function (control) {
                control.addEventListener('change', refreshPropertyType);
            });

            refs.extraSelects.forEach(function (select) {
                select.addEventListener('change', function () {
                    refreshExtraAreaField(select);
                });
            });

            refs.amenityToggles.forEach(function (toggle) {
                toggle.addEventListener('change', function () {
                    refreshAmenityCount(toggle);
                });
            });

            bindRangeValidation('budget', 'Budzet');
            bindRangeValidation('area', 'Metraz');
            bindRangeValidation('rooms', 'Liczba pokoi');
            bindRangeValidation('floor', 'Pietro');

            refreshTransactionFromAgreement();
            refreshPropertyType();
            refs.extraSelects.forEach(function (select) {
                refreshExtraAreaField(select);
            });
            refs.amenityToggles.forEach(function (toggle) {
                refreshAmenityCount(toggle);
            });
        });
    }

    function bindClientFormDynamics() {
        var forms = document.querySelectorAll('[data-eocrm-client-form]');
        if (!forms.length) {
            return;
        }

        forms.forEach(function (form) {
            var typeSelect = form.querySelector('[data-eocrm-client-type]');
            var sameAddressCheckbox = form.querySelector('[data-eocrm-correspondence-same]');
            var personFields = form.querySelectorAll('.eocrm-client-person');
            var companyFields = form.querySelectorAll('.eocrm-client-company');
            var corrSection = form.querySelector('.eocrm-client-correspondence');
            var personRequired = form.querySelectorAll('[data-eocrm-required-person]');
            var companyRequired = form.querySelectorAll('[data-eocrm-required-company]');
            var corrRequired = form.querySelectorAll('[data-eocrm-corr-field]');

            var refreshClientType = function () {
                if (!typeSelect) {
                    return;
                }

                var clientType = normalize(typeSelect.value);
                var isCompany = clientType === 'company';

                personFields.forEach(function (el) {
                    el.classList.toggle('is-hidden', isCompany);
                });

                companyFields.forEach(function (el) {
                    el.classList.toggle('is-hidden', !isCompany);
                });

                personRequired.forEach(function (input) {
                    input.required = !isCompany;
                });

                companyRequired.forEach(function (input) {
                    input.required = isCompany;
                });
            };

            var refreshCorrespondence = function () {
                if (!sameAddressCheckbox || !corrSection) {
                    return;
                }

                var isSame = sameAddressCheckbox.checked;
                corrSection.classList.toggle('is-hidden', isSame);

                corrRequired.forEach(function (input) {
                    input.required = !isSame;
                });
            };

            if (typeSelect) {
                typeSelect.addEventListener('change', refreshClientType);
            }

            if (sameAddressCheckbox) {
                sameAddressCheckbox.addEventListener('change', refreshCorrespondence);
            }

            refreshClientType();
            refreshCorrespondence();
        });
    }

    function bindPublicOffersFilters() {
        var forms = document.querySelectorAll('[data-eocrm-offers-filters-form]');
        if (!forms.length) {
            return;
        }

        forms.forEach(function (form) {
            if (form.getAttribute('data-eocrm-live-bound') === '1') {
                return;
            }
            form.setAttribute('data-eocrm-live-bound', '1');

            var wrap = form.closest('.eocrm-offers-wrap');
            if (!wrap) {
                return;
            }
            var transactionKey = trimText(wrap.getAttribute('data-eocrm-offers-transaction') || '').toUpperCase();

            var filterNames = ['property_type', 'city', 'district', 'price_min', 'price_max', 'area_min', 'area_max', 'rooms_min', 'rooms_max'];
            var rangePairs = [
                { min: 'price_min', max: 'price_max', integer: false },
                { min: 'area_min', max: 'area_max', integer: false },
                { min: 'rooms_min', max: 'rooms_max', integer: true }
            ];
            var delay = parseInt(form.getAttribute('data-eocrm-auto-submit-delay') || '380', 10);
            if (!(delay > 0)) {
                delay = 380;
            }

            var timer = null;
            var statusTarget = form.querySelector('[data-eocrm-live-status]');
            var liveRequestId = 0;

            var getFilterControls = function () {
                return Array.prototype.slice.call(form.querySelectorAll('input[name], select[name]'));
            };

            var getBaseUrl = function () {
                var actionUrl = trimText(form.getAttribute('action') || '');
                var rawBase = actionUrl !== '' ? actionUrl : window.location.href;
                var base = new URL(rawBase, window.location.origin);

                base.searchParams.delete('eocrm_page');
                filterNames.forEach(function (name) {
                    base.searchParams.delete(name);
                });
                base.hash = '';

                return base;
            };

            var setStatus = function (text, isLoading) {
                if (!statusTarget) {
                    return;
                }
                statusTarget.textContent = text;
                statusTarget.classList.toggle('is-loading', !!isLoading);
            };

            var normalizeRangeInputs = function () {
                rangePairs.forEach(function (pair) {
                    var minControl = form.querySelector('[name="' + pair.min + '"]');
                    var maxControl = form.querySelector('[name="' + pair.max + '"]');
                    if (!minControl || !maxControl) {
                        return;
                    }

                    var minValue = trimText(minControl.value);
                    var maxValue = trimText(maxControl.value);
                    if (minValue === '' || maxValue === '') {
                        return;
                    }

                    var minNumber = pair.integer ? parseInt(minValue, 10) : parseDecimal(minValue);
                    var maxNumber = pair.integer ? parseInt(maxValue, 10) : parseDecimal(maxValue);
                    if (minNumber === null || maxNumber === null || isNaN(minNumber) || isNaN(maxNumber)) {
                        return;
                    }

                    if (minNumber > maxNumber) {
                        minControl.value = String(maxNumber);
                        maxControl.value = String(minNumber);
                    }
                });
            };

            var buildTargetUrl = function (pageNo) {
                normalizeRangeInputs();
                var target = getBaseUrl();
                var controls = getFilterControls();

                controls.forEach(function (control) {
                    var name = String(control.name || '');
                    if (filterNames.indexOf(name) === -1) {
                        return;
                    }

                    var value = trimText(control.value);
                    if (value !== '') {
                        target.searchParams.set(name, value);
                    }
                });

                var resolvedPage = parseInt(String(pageNo || '1'), 10);
                if (!(resolvedPage > 0)) {
                    resolvedPage = 1;
                }
                target.searchParams.set('eocrm_page', String(resolvedPage));

                return target.toString();
            };

            var replaceWrapContent = function (nextWrap) {
                if (!nextWrap) {
                    return false;
                }

                var currentLayout = wrap.querySelector('.eocrm-offers-layout');
                var nextLayout = nextWrap.querySelector('.eocrm-offers-layout');
                if (!currentLayout || !nextLayout) {
                    return false;
                }

                currentLayout.replaceWith(nextLayout);

                var currentPagination = wrap.querySelector('.eocrm-pagination');
                var nextPagination = nextWrap.querySelector('.eocrm-pagination');
                if (currentPagination && nextPagination) {
                    currentPagination.replaceWith(nextPagination);
                } else if (currentPagination && !nextPagination) {
                    currentPagination.remove();
                } else if (!currentPagination && nextPagination) {
                    wrap.appendChild(nextPagination);
                }

                return true;
            };

            var performLiveReload = function (targetUrl, pageNo) {
                liveRequestId += 1;
                var requestId = liveRequestId;
                wrap.classList.add('is-live-loading');
                setStatus('Aktualizowanie ofert...', true);

                fetch(targetUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: 'no-store'
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Offers filter request failed');
                    }
                    return response.text();
                }).then(function (html) {
                    if (requestId !== liveRequestId) {
                        return;
                    }

                    var parser = new window.DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var nextWrap = null;
                    if (transactionKey !== '') {
                        nextWrap = doc.querySelector('.eocrm-offers-wrap[data-eocrm-offers-transaction="' + transactionKey + '"]');
                    }
                    if (!nextWrap) {
                        nextWrap = doc.querySelector('.eocrm-offers-wrap');
                    }
                    if (!replaceWrapContent(nextWrap)) {
                        throw new Error('Offers container missing in response');
                    }

                    var resolvedPage = parseInt(String(pageNo || '1'), 10);
                    if (!(resolvedPage > 0)) {
                        resolvedPage = 1;
                    }
                    var pageInput = form.querySelector('input[name="eocrm_page"]');
                    if (pageInput) {
                        pageInput.value = String(resolvedPage);
                    }

                    window.history.replaceState({}, '', targetUrl);
                    bindPublicOffersMap();
                    setStatus('Filtrowanie na zywo', false);
                }).catch(function () {
                    if (requestId !== liveRequestId) {
                        return;
                    }
                    window.location.assign(targetUrl);
                }).finally(function () {
                    if (requestId !== liveRequestId) {
                        return;
                    }
                    wrap.classList.remove('is-live-loading');
                });
            };

            var scheduleLiveReload = function () {
                if (timer) {
                    window.clearTimeout(timer);
                }
                normalizeRangeInputs();
                setStatus('Filtrowanie...', true);
                timer = window.setTimeout(function () {
                    var targetUrl = buildTargetUrl(1);
                    performLiveReload(targetUrl, 1);
                }, delay);
            };

            var controls = getFilterControls();
            controls.forEach(function (control) {
                var name = String(control.name || '');
                if (filterNames.indexOf(name) === -1) {
                    return;
                }

                var tagName = String(control.tagName || '').toLowerCase();
                var inputType = String(control.type || '').toLowerCase();
                if (tagName === 'select' || inputType === 'number') {
                    control.addEventListener('change', scheduleLiveReload);
                }
                control.addEventListener('input', scheduleLiveReload);
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (timer) {
                    window.clearTimeout(timer);
                }
                var targetUrl = buildTargetUrl(1);
                performLiveReload(targetUrl, 1);
            });

            var clearButton = form.querySelector('[data-eocrm-filter-clear]');
            if (clearButton) {
                clearButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (timer) {
                        window.clearTimeout(timer);
                    }

                    controls.forEach(function (control) {
                        var name = String(control.name || '');
                        if (filterNames.indexOf(name) === -1) {
                            return;
                        }
                        if (String(control.tagName || '').toLowerCase() === 'select') {
                            control.selectedIndex = 0;
                        } else {
                            control.value = '';
                        }
                    });

                    var targetUrl = buildTargetUrl(1);
                    performLiveReload(targetUrl, 1);
                });
            }

            wrap.addEventListener('click', function (event) {
                var link = event.target && event.target.closest
                    ? event.target.closest('.eocrm-pagination a.eocrm-page-link')
                    : null;
                if (!link || !wrap.contains(link)) {
                    return;
                }

                event.preventDefault();
                if (timer) {
                    window.clearTimeout(timer);
                }

                var href = link.getAttribute('href') || '';
                if (href === '') {
                    return;
                }

                var targetUrl = new URL(href, window.location.origin);
                var pageNo = parseInt(targetUrl.searchParams.get('eocrm_page') || '1', 10);
                if (!(pageNo > 0)) {
                    pageNo = 1;
                }
                performLiveReload(targetUrl.toString(), pageNo);
            });
        });
    }

    function bindPublicOfferSlider() {
        var sliders = document.querySelectorAll('[data-eocrm-gallery-slider]');
        if (!sliders.length) {
            return;
        }

        sliders.forEach(function (slider) {
            var slides = slider.querySelectorAll('[data-eocrm-gallery-slide]');
            if (!slides.length) {
                return;
            }

            var dots = slider.querySelectorAll('[data-eocrm-gallery-dot]');
            var prevButton = slider.querySelector('[data-eocrm-gallery-prev]');
            var nextButton = slider.querySelector('[data-eocrm-gallery-next]');
            var activeIndex = 0;
            var autoplayEnabled = slider.getAttribute('data-eocrm-gallery-autoplay') === '1';
            var autoplayInterval = parseInt(slider.getAttribute('data-eocrm-gallery-autoplay-interval') || '5200', 10);
            if (!(autoplayInterval >= 2000)) {
                autoplayInterval = 5200;
            }
            var autoplayTimer = null;

            var setActiveSlide = function (index) {
                if (!slides.length) {
                    return;
                }

                if (index < 0) {
                    index = slides.length - 1;
                }
                if (index >= slides.length) {
                    index = 0;
                }

                activeIndex = index;

                slides.forEach(function (slide, slideIndex) {
                    slide.classList.toggle('is-active', slideIndex === activeIndex);
                });

                dots.forEach(function (dot, dotIndex) {
                    dot.classList.toggle('is-active', dotIndex === activeIndex);
                });
            };

            var stopAutoplay = function () {
                if (autoplayTimer) {
                    window.clearInterval(autoplayTimer);
                    autoplayTimer = null;
                }
            };

            var startAutoplay = function () {
                if (!autoplayEnabled || slides.length < 2) {
                    return;
                }
                stopAutoplay();
                autoplayTimer = window.setInterval(function () {
                    setActiveSlide(activeIndex + 1);
                }, autoplayInterval);
            };

            dots.forEach(function (dot) {
                dot.addEventListener('click', function () {
                    var index = parseInt(dot.getAttribute('data-eocrm-gallery-dot') || '', 10);
                    if (!(index >= 0)) {
                        return;
                    }
                    setActiveSlide(index);
                    startAutoplay();
                });
            });

            if (prevButton) {
                prevButton.addEventListener('click', function () {
                    setActiveSlide(activeIndex - 1);
                    startAutoplay();
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', function () {
                    setActiveSlide(activeIndex + 1);
                    startAutoplay();
                });
            }

            if (autoplayEnabled && slides.length > 1) {
                slider.addEventListener('mouseenter', stopAutoplay);
                slider.addEventListener('mouseleave', startAutoplay);
                slider.addEventListener('focusin', stopAutoplay);
                slider.addEventListener('focusout', function () {
                    if (!slider.contains(document.activeElement)) {
                        startAutoplay();
                    }
                });
                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        stopAutoplay();
                    } else {
                        startAutoplay();
                    }
                });
            }

            setActiveSlide(0);
            startAutoplay();
        });
    }

    function bindPublicOffersMap() {
        var mapCanvases = document.querySelectorAll('[data-eocrm-offers-map]');
        if (!mapCanvases.length) {
            return;
        }

        var escapeHtml = function (value) {
            var text = value === null || typeof value === 'undefined' ? '' : String(value);
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        mapCanvases.forEach(function (canvas) {
            var rawMarkers = canvas.getAttribute('data-eocrm-map-markers') || '[]';
            var statusTarget = null;
            var panel = canvas.closest('.eocrm-offers-map-panel');
            if (panel) {
                statusTarget = panel.querySelector('[data-eocrm-offers-map-status]');
            }

            var parsedMarkers = [];
            try {
                var decoded = JSON.parse(rawMarkers);
                if (Array.isArray(decoded)) {
                    parsedMarkers = decoded;
                }
            } catch (err) {
                parsedMarkers = [];
            }

            var markers = parsedMarkers.filter(function (row) {
                if (!row || typeof row !== 'object') {
                    return false;
                }

                var lat = parseFloat(row.lat);
                var lng = parseFloat(row.lng);
                if (isNaN(lat) || isNaN(lng)) {
                    return false;
                }

                if (lat === 0 && lng === 0) {
                    return false;
                }

                row.lat = lat;
                row.lng = lng;
                return true;
            });

            if (!markers.length) {
                canvas.classList.add('eocrm-map-disabled');
                canvas.innerHTML = '<p>Brak wspolrzednych GPS w ofertach na tej stronie.</p>';
                if (statusTarget) {
                    statusTarget.textContent = '';
                }
                return;
            }

            if (typeof window.google === 'undefined' || !window.google.maps) {
                canvas.classList.add('eocrm-map-disabled');
                canvas.innerHTML = '<p>Mapa Google nie jest dostepna. Uzupelnij klucz API w ustawieniach.</p>';
                if (statusTarget) {
                    statusTarget.textContent = '';
                }
                return;
            }

            if (statusTarget) {
                statusTarget.textContent = 'Kliknij znacznik, aby zobaczyc podsumowanie oferty.';
            }

            var config = getFrontendConfig();
            var defaultLat = parseFloat(config.mapsDefaultLat || '52.229676');
            var defaultLng = parseFloat(config.mapsDefaultLng || '21.012229');
            if (isNaN(defaultLat)) {
                defaultLat = 52.229676;
            }
            if (isNaN(defaultLng)) {
                defaultLng = 21.012229;
            }

            var map = new window.google.maps.Map(canvas, {
                center: {
                    lat: markers[0] ? markers[0].lat : defaultLat,
                    lng: markers[0] ? markers[0].lng : defaultLng
                },
                zoom: markers.length > 1 ? 11 : 14,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true
            });

            var infoWindow = new window.google.maps.InfoWindow();
            var bounds = new window.google.maps.LatLngBounds();

            var markerPositionCounter = {};

            markers.forEach(function (markerRow, markerIndex) {
                var markerKey = String(markerRow.lat.toFixed(6)) + ',' + String(markerRow.lng.toFixed(6));
                var duplicateIndex = markerPositionCounter[markerKey] || 0;
                markerPositionCounter[markerKey] = duplicateIndex + 1;

                var latOffset = duplicateIndex > 0 ? (0.00006 * duplicateIndex) : 0;
                var lngOffset = duplicateIndex > 0 ? (0.00006 * duplicateIndex) : 0;
                var markerLat = markerRow.lat + latOffset;
                var markerLng = markerRow.lng + lngOffset;

                var marker = new window.google.maps.Marker({
                    position: { lat: markerLat, lng: markerLng },
                    map: map,
                    title: markerRow.address || markerRow.offer_number || 'Oferta',
                    label: {
                        text: String(markerIndex + 1),
                        color: '#ffffff',
                        fontWeight: '700',
                        fontSize: '11px'
                    }
                });

                bounds.extend(marker.getPosition());

                marker.addListener('click', function () {
                    var photoHtml = markerRow.preview_photo_url
                        ? '<div class="eocrm-map-info-thumb"><img src="' + escapeHtml(markerRow.preview_photo_url) + '" alt="Miniatura oferty"></div>'
                        : '<div class="eocrm-map-info-thumb eocrm-map-info-thumb-empty"><span>Brak zdjecia</span></div>';

                    var infoHtml = ''
                        + '<div class="eocrm-map-info-window">'
                        + photoHtml
                        + '<div class="eocrm-map-info-content">'
                        + '<p class="eocrm-map-info-address">' + escapeHtml(markerRow.address || '-') + '</p>'
                        + '<p class="eocrm-map-info-line">' + escapeHtml(markerRow.location || '-') + '</p>'
                        + '<p class="eocrm-map-info-line">Rodzaj: ' + escapeHtml(markerRow.property_type || '-') + '</p>'
                        + '<p class="eocrm-map-info-line">Cena: ' + escapeHtml(markerRow.price || '-') + '</p>'
                        + '<p class="eocrm-map-info-line">Numer: ' + escapeHtml(markerRow.offer_number || '-') + '</p>';

                    if (markerRow.detail_url) {
                        var linkAttrs = '';
                        if (String(markerRow.open_new_window || '') === '1') {
                            linkAttrs = ' target="_blank" rel="noopener noreferrer"';
                        }
                        infoHtml += '<p class="eocrm-map-info-link-wrap"><a class="eocrm-map-info-link" href="' + escapeHtml(markerRow.detail_url) + '"' + linkAttrs + '>Przejdz do oferty</a></p>';
                    }

                    infoHtml += '</div></div>';

                    infoWindow.setContent(infoHtml);
                    infoWindow.open({
                        map: map,
                        anchor: marker
                    });
                });
            });

            if (markers.length > 1) {
                map.fitBounds(bounds, 60);
            }
        });
    }

    function bindPublicOfferFloorPlans() {
        var switchers = document.querySelectorAll('[data-eocrm-floor-plan-switcher]');
        if (!switchers.length) {
            return;
        }

        switchers.forEach(function (switcher) {
            var tabs = switcher.querySelectorAll('[data-eocrm-floor-tab]');
            var panels = switcher.querySelectorAll('[data-eocrm-floor-panel]');
            if (!tabs.length || !panels.length) {
                return;
            }

            var setActivePanel = function (targetIndex) {
                tabs.forEach(function (tab, tabIndex) {
                    var isActive = tabIndex === targetIndex;
                    tab.classList.toggle('is-active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panels.forEach(function (panel, panelIndex) {
                    panel.classList.toggle('is-active', panelIndex === targetIndex);
                });
            };

            tabs.forEach(function (tab, tabIndex) {
                tab.addEventListener('click', function () {
                    setActivePanel(tabIndex);
                });
            });

            setActivePanel(0);
        });
    }

    function bindPublicOfferLightbox() {
        var lightbox = document.querySelector('[data-eocrm-lightbox]');
        if (!lightbox) {
            return;
        }

        var lightboxMedia = lightbox.querySelector('[data-eocrm-lightbox-media]');
        var lightboxImage = lightbox.querySelector('[data-eocrm-lightbox-image]');
        var lightboxFrame = lightbox.querySelector('[data-eocrm-lightbox-frame]');
        var lightboxWatermark = lightbox.querySelector('[data-eocrm-lightbox-watermark]');
        if (!lightboxImage && !lightboxFrame) {
            return;
        }

        var closeButton = lightbox.querySelector('[data-eocrm-lightbox-close]');
        var prevButton = lightbox.querySelector('[data-eocrm-lightbox-prev]');
        var nextButton = lightbox.querySelector('[data-eocrm-lightbox-next]');
        var zoomableItems = document.querySelectorAll('[data-eocrm-zoom-src]');
        var galleryTriggers = document.querySelectorAll('[data-eocrm-gallery-zoom-index]');
        var galleryItems = [];
        var activeGalleryIndex = -1;
        var activeTrigger = null;

        if (!zoomableItems.length) {
            return;
        }

        galleryTriggers.forEach(function (item) {
            var index = parseInt(item.getAttribute('data-eocrm-gallery-zoom-index') || '', 10);
            if (!(index >= 0)) {
                return;
            }

            galleryItems[index] = item;
        });

        galleryItems = galleryItems.filter(function (item) {
            return !!item;
        });

        var updateGalleryNav = function () {
            var shouldShow = !lightbox.hidden && activeGalleryIndex >= 0 && galleryItems.length > 1;
            if (prevButton) {
                prevButton.hidden = !shouldShow;
            }
            if (nextButton) {
                nextButton.hidden = !shouldShow;
            }
        };

        var closeLightbox = function () {
            lightbox.hidden = true;
            if (lightboxMedia) {
                lightboxMedia.hidden = true;
            }
            if (lightboxImage) {
                lightboxImage.setAttribute('src', '');
                lightboxImage.hidden = true;
            }
            if (lightboxFrame) {
                lightboxFrame.setAttribute('src', '');
                lightboxFrame.hidden = true;
            }
            if (lightboxWatermark) {
                lightboxWatermark.hidden = true;
            }
            document.body.classList.remove('eocrm-lightbox-open');
            activeGalleryIndex = -1;
            updateGalleryNav();

            if (activeTrigger && typeof activeTrigger.focus === 'function') {
                activeTrigger.focus();
            }

            activeTrigger = null;
        };

        closeLightbox();

        var openLightbox = function (trigger) {
            if (!(trigger instanceof Element)) {
                return;
            }

            var source = trigger.getAttribute('data-eocrm-zoom-src') || '';
            if (source === '') {
                return;
            }

            var zoomKind = (trigger.getAttribute('data-eocrm-zoom-kind') || 'image').toLowerCase();
            var titleText = trigger.getAttribute('data-eocrm-zoom-title') || trigger.getAttribute('alt') || 'Powiekszony podglad oferty';
            var galleryIndex = parseInt(trigger.getAttribute('data-eocrm-gallery-zoom-index') || '', 10);
            activeGalleryIndex = (!isNaN(galleryIndex) && galleryIndex >= 0 && zoomKind !== 'iframe') ? galleryIndex : -1;

            if (zoomKind === 'iframe' && lightboxFrame) {
                lightboxFrame.setAttribute('src', source);
                lightboxFrame.setAttribute('title', titleText);
                lightboxFrame.hidden = false;
                if (lightboxMedia) {
                    lightboxMedia.hidden = true;
                }

                if (lightboxImage) {
                    lightboxImage.setAttribute('src', '');
                    lightboxImage.hidden = true;
                }
                if (lightboxWatermark) {
                    lightboxWatermark.hidden = true;
                }
            } else if (lightboxImage) {
                lightboxImage.setAttribute('src', source);
                lightboxImage.setAttribute('alt', titleText);
                lightboxImage.hidden = false;
                if (lightboxMedia) {
                    lightboxMedia.hidden = false;
                }
                if (lightboxWatermark) {
                    lightboxWatermark.hidden = false;
                }

                if (lightboxFrame) {
                    lightboxFrame.setAttribute('src', '');
                    lightboxFrame.hidden = true;
                }
            } else {
                return;
            }

            lightbox.hidden = false;
            document.body.classList.add('eocrm-lightbox-open');
            activeTrigger = trigger;
            updateGalleryNav();
        };

        var openGalleryByIndex = function (index) {
            if (!galleryItems.length) {
                return;
            }

            if (index < 0) {
                index = galleryItems.length - 1;
            }
            if (index >= galleryItems.length) {
                index = 0;
            }

            var target = galleryItems[index];
            if (!(target instanceof Element)) {
                return;
            }

            openLightbox(target);
        };

        zoomableItems.forEach(function (item) {
            var isButton = item.tagName.toLowerCase() === 'button';

            if (!item.hasAttribute('tabindex') && item.tagName.toLowerCase() !== 'button') {
                item.setAttribute('tabindex', '0');
            }

            item.addEventListener('click', function () {
                openLightbox(item);
            });

            if (!isButton) {
                item.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }

                    event.preventDefault();
                    openLightbox(item);
                });
            }
        });

        if (closeButton) {
            closeButton.addEventListener('click', closeLightbox);
        }

        if (prevButton) {
            prevButton.addEventListener('click', function () {
                if (activeGalleryIndex >= 0) {
                    openGalleryByIndex(activeGalleryIndex - 1);
                }
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                if (activeGalleryIndex >= 0) {
                    openGalleryByIndex(activeGalleryIndex + 1);
                }
            });
        }

        lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox) {
                closeLightbox();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (lightbox.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                closeLightbox();
                return;
            }

            if (activeGalleryIndex < 0) {
                return;
            }

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                openGalleryByIndex(activeGalleryIndex - 1);
                return;
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                openGalleryByIndex(activeGalleryIndex + 1);
            }
        });

        var autoOpenGalleryFromQuery = function () {
            if (!galleryItems.length || typeof window === 'undefined' || !window.location || !window.location.search) {
                return;
            }

            if (typeof URLSearchParams !== 'function') {
                return;
            }

            var params = new URLSearchParams(window.location.search);
            var autoOpen = (params.get('eocrm_gallery') || '').toString().toLowerCase();
            if (autoOpen !== '1' && autoOpen !== 'true' && autoOpen !== 'yes') {
                return;
            }

            var rawIndex = params.get('eocrm_gallery_index');
            var index = parseInt(rawIndex || '0', 10);
            if (isNaN(index)) {
                index = 0;
            }

            openGalleryByIndex(index);
        };

        autoOpenGalleryFromQuery();
    }

    function bindPropertyPdfChoiceModal() {
        var modals = document.querySelectorAll('[data-eocrm-pdf-choice-modal]');
        if (!modals.length) {
            return;
        }

        var updateBodyLock = function () {
            var hasOpenModal = !!document.querySelector('[data-eocrm-pdf-choice-modal]:not([hidden])');
            document.body.classList.toggle('eocrm-modal-open', hasOpenModal);
        };

        modals.forEach(function (modal) {
            var scope = modal.closest('[data-eocrm-prop-profile]') || modal.closest('.eocrm-wrap') || modal.parentElement || document;
            var openButton = scope.querySelector('[data-eocrm-pdf-choice-open]') || document.querySelector('[data-eocrm-pdf-choice-open]');
            var closeButtons = modal.querySelectorAll('[data-eocrm-pdf-choice-close]');

            if (!openButton) {
                return;
            }

            var openModal = function () {
                modal.hidden = false;
                updateBodyLock();
            };

            var closeModal = function () {
                modal.hidden = true;
                updateBodyLock();
            };

            openButton.addEventListener('click', function (event) {
                if (event) {
                    event.preventDefault();
                }
                openModal();
            });

            closeButtons.forEach(function (closeButton) {
                closeButton.addEventListener('click', function () {
                    closeModal();
                });
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            modals.forEach(function (modal) {
                if (!modal.hidden) {
                    modal.hidden = true;
                }
            });
            updateBodyLock();
        });
    }

    function bindMortgageCalculator() {
        var calculators = document.querySelectorAll('[data-eocrm-mortgage-calculator]');
        if (!calculators.length) {
            return;
        }

        var config = getFrontendConfig();

        var formatMoney = function (value) {
            var numericValue = typeof value === 'number' && isFinite(value) ? value : 0;
            return numericValue.toLocaleString('pl-PL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' PLN';
        };

        var formatPercent = function (value) {
            var numericValue = typeof value === 'number' && isFinite(value) ? value : 0;
            return numericValue.toLocaleString('pl-PL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + '%';
        };

        calculators.forEach(function (calculator) {
            var refs = {
                price: calculator.querySelector('[data-eocrm-calc-price]'),
                downPayment: calculator.querySelector('[data-eocrm-calc-down-payment]'),
                downPaymentPercent: calculator.querySelector('[data-eocrm-calc-down-payment-percent]'),
                years: calculator.querySelector('[data-eocrm-calc-years]'),
                tenor: calculator.querySelector('[data-eocrm-calc-wibor-tenor]'),
                tenorButtons: calculator.querySelectorAll('[data-eocrm-calc-wibor-btn]'),
                wiborRate: calculator.querySelector('[data-eocrm-calc-wibor-rate]'),
                margin: calculator.querySelector('[data-eocrm-calc-margin]'),
                commission: calculator.querySelector('[data-eocrm-calc-commission]'),
                installmentType: calculator.querySelector('[data-eocrm-calc-installment-type]'),
                monthlyFees: calculator.querySelector('[data-eocrm-calc-monthly-fees]'),
                financeCommission: calculator.querySelector('[data-eocrm-calc-finance-commission]'),
                resultLoan: calculator.querySelector('[data-eocrm-calc-result-loan]'),
                resultRate: calculator.querySelector('[data-eocrm-calc-result-rate]'),
                resultMonthly: calculator.querySelector('[data-eocrm-calc-result-monthly]'),
                resultFirst: calculator.querySelector('[data-eocrm-calc-result-first]'),
                resultLast: calculator.querySelector('[data-eocrm-calc-result-last]'),
                resultInterest: calculator.querySelector('[data-eocrm-calc-result-interest]'),
                resultCommission: calculator.querySelector('[data-eocrm-calc-result-commission]'),
                resultTotal: calculator.querySelector('[data-eocrm-calc-result-total]'),
                meta: calculator.querySelector('[data-eocrm-calc-wibor-meta]'),
                exportFields: calculator.querySelectorAll('[data-eocrm-calc-export-field]')
            };

            if (!refs.price || !refs.downPayment || !refs.downPaymentPercent || !refs.tenor || !refs.margin || !refs.commission || !refs.years || !refs.installmentType) {
                return;
            }

            var normalizeTenorValue = function (rawValue) {
                var value = String(rawValue || '3M').toUpperCase();
                return (value === '1M' || value === '3M' || value === '6M') ? value : '3M';
            };

            var syncTenorButtons = function () {
                if (!refs.tenorButtons || !refs.tenorButtons.length) {
                    return;
                }

                var selectedTenor = normalizeTenorValue(refs.tenor ? refs.tenor.value : '3M');
                refs.tenorButtons.forEach(function (button) {
                    var buttonTenor = normalizeTenorValue(button.getAttribute('data-eocrm-calc-wibor-btn') || '');
                    button.classList.toggle('is-active', buttonTenor === selectedTenor);
                });
            };

            var setTenor = function (nextTenor) {
                if (!refs.tenor) {
                    return;
                }

                refs.tenor.value = normalizeTenorValue(nextTenor);
                syncTenorButtons();
            };

            var ratesPayload = (config && typeof config.wiborRates === 'object' && config.wiborRates) ? config.wiborRates : {};
            var normalizeRates = function (rawRates) {
                var defaults = {
                    '1M': 3.84,
                    '3M': 3.84,
                    '6M': 3.88
                };

                if (!rawRates || typeof rawRates !== 'object') {
                    return defaults;
                }

                ['1M', '3M', '6M'].forEach(function (tenorKey) {
                    var hasTenor = Object.prototype.hasOwnProperty.call(rawRates, tenorKey);
                    var rawValue = hasTenor ? rawRates[tenorKey] : null;
                    var parsedValue = parseDecimal(rawValue);

                    if (parsedValue !== null && parsedValue > 0) {
                        defaults[tenorKey] = parsedValue;
                    }
                });

                return defaults;
            };

            var rates = normalizeRates((ratesPayload && typeof ratesPayload.rates === 'object' && ratesPayload.rates) ? ratesPayload.rates : {
                '1M': 3.84,
                '3M': 3.84,
                '6M': 3.88
            });

            var updateMetaLabel = function (payload) {
                if (!refs.meta) {
                    return;
                }

                var source = payload && payload.sourceLabel ? String(payload.sourceLabel) : (payload && payload.source ? String(payload.source) : 'fallback');
                var updatedAt = payload && payload.updatedAt ? String(payload.updatedAt) : '';
                var quoteDate = payload && payload.quoteDate ? String(payload.quoteDate) : '';
                refs.meta.textContent = 'Źródło WIBOR: ' + source +
                    (quoteDate !== '' ? ' | Data notowań: ' + quoteDate : '') +
                    (updatedAt !== '' ? ' | Aktualizacja cache: ' + updatedAt : '');
            };

            var getRateForTenor = function () {
                var tenor = normalizeTenorValue(refs.tenor ? refs.tenor.value : '3M');
                var rawValue = rates && Object.prototype.hasOwnProperty.call(rates, tenor) ? rates[tenor] : null;
                var parsed = parseDecimal(rawValue);
                return parsed === null ? 0 : parsed;
            };

            var syncDownPaymentFromAmount = function () {
                var price = parseDecimal(refs.price.value);
                var downAmount = parseDecimal(refs.downPayment.value);

                price = price === null || price < 0 ? 0 : price;
                downAmount = downAmount === null || downAmount < 0 ? 0 : downAmount;

                if (downAmount > price) {
                    downAmount = price;
                    refs.downPayment.value = downAmount.toFixed(2);
                }

                var percent = price > 0 ? (downAmount / price) * 100 : 0;
                refs.downPaymentPercent.value = String(Math.round(percent));
            };

            var syncDownPaymentFromPercent = function () {
                var price = parseDecimal(refs.price.value);
                var percent = parseDecimal(refs.downPaymentPercent.value);

                price = price === null || price < 0 ? 0 : price;
                percent = percent === null || percent < 0 ? 0 : percent;
                if (percent > 100) {
                    percent = 100;
                    refs.downPaymentPercent.value = '100';
                }

                var downAmount = price * (percent / 100);
                refs.downPayment.value = downAmount.toFixed(2);
            };

            var renderResult = function (target, value, formatter) {
                if (!target) {
                    return;
                }

                if (typeof formatter === 'function') {
                    target.textContent = formatter(value);
                    return;
                }

                target.textContent = String(value);
            };

            var syncExportFields = function () {
                if (!refs.exportFields || !refs.exportFields.length) {
                    return;
                }

                var installmentType = refs.installmentType ? String(refs.installmentType.value || 'equal') : 'equal';
                var exportMap = {
                    loan: refs.resultLoan ? trimText(refs.resultLoan.textContent) : '',
                    rate: refs.resultRate ? trimText(refs.resultRate.textContent) : '',
                    monthly: refs.resultMonthly ? trimText(refs.resultMonthly.textContent) : '',
                    first: refs.resultFirst ? trimText(refs.resultFirst.textContent) : '',
                    last: refs.resultLast ? trimText(refs.resultLast.textContent) : '',
                    interest: refs.resultInterest ? trimText(refs.resultInterest.textContent) : '',
                    commission: refs.resultCommission ? trimText(refs.resultCommission.textContent) : '',
                    total: refs.resultTotal ? trimText(refs.resultTotal.textContent) : '',
                    price: refs.price ? trimText(refs.price.value) : '',
                    down_payment: refs.downPayment ? trimText(refs.downPayment.value) : '',
                    down_payment_percent: refs.downPaymentPercent ? trimText(refs.downPaymentPercent.value) : '',
                    years: refs.years ? trimText(refs.years.value) : '',
                    tenor: refs.tenor ? normalizeTenorValue(refs.tenor.value) : '',
                    wibor_rate: refs.wiborRate ? trimText(refs.wiborRate.value) : '',
                    margin: refs.margin ? trimText(refs.margin.value) : '',
                    commission_percent: refs.commission ? trimText(refs.commission.value) : '',
                    installment_type: installmentType,
                    monthly_fees: refs.monthlyFees ? trimText(refs.monthlyFees.value) : '',
                    finance_commission: refs.financeCommission && refs.financeCommission.checked ? 'Tak' : 'Nie'
                };

                refs.exportFields.forEach(function (field) {
                    var key = field.getAttribute('data-eocrm-calc-export-field') || '';
                    if (key === '' || !Object.prototype.hasOwnProperty.call(exportMap, key)) {
                        return;
                    }

                    field.value = String(exportMap[key] || '');
                });
            };

            var calculate = function () {
                var price = parseDecimal(refs.price.value);
                var downPayment = parseDecimal(refs.downPayment.value);
                var years = parseDecimal(refs.years.value);
                var margin = parseDecimal(refs.margin.value);
                var commissionPct = parseDecimal(refs.commission.value);
                var monthlyFees = parseDecimal(refs.monthlyFees ? refs.monthlyFees.value : '0');
                var wibor = getRateForTenor();
                var installmentType = refs.installmentType ? String(refs.installmentType.value || 'equal') : 'equal';
                var financeCommission = !!(refs.financeCommission && refs.financeCommission.checked);

                price = price === null || price < 0 ? 0 : price;
                downPayment = downPayment === null || downPayment < 0 ? 0 : downPayment;
                years = years === null || years <= 0 ? 30 : years;
                margin = margin === null || margin < 0 ? 0 : margin;
                commissionPct = commissionPct === null || commissionPct < 0 ? 0 : commissionPct;
                monthlyFees = monthlyFees === null || monthlyFees < 0 ? 0 : monthlyFees;

                if (downPayment > price) {
                    downPayment = price;
                    refs.downPayment.value = downPayment.toFixed(2);
                    syncDownPaymentFromAmount();
                }

                var baseLoan = Math.max(price - downPayment, 0);
                var commissionAmount = baseLoan * (commissionPct / 100);
                var principal = financeCommission ? baseLoan + commissionAmount : baseLoan;
                var annualRate = Math.max(wibor + margin, 0);
                var monthlyRate = annualRate / 100 / 12;
                var months = Math.max(1, Math.round(years * 12));
                var monthlyPayment = 0;
                var firstPayment = 0;
                var lastPayment = 0;
                var interestTotal = 0;

                if (principal <= 0) {
                    monthlyPayment = 0;
                    firstPayment = 0;
                    lastPayment = 0;
                    interestTotal = 0;
                } else if (installmentType === 'decreasing') {
                    var principalPart = principal / months;
                    firstPayment = principalPart + principal * monthlyRate;
                    lastPayment = principalPart + principalPart * monthlyRate;
                    monthlyPayment = (firstPayment + lastPayment) / 2;
                    interestTotal = monthlyRate > 0 ? principal * monthlyRate * ((months + 1) / 2) : 0;
                } else if (monthlyRate <= 0) {
                    monthlyPayment = principal / months;
                    firstPayment = monthlyPayment;
                    lastPayment = monthlyPayment;
                    interestTotal = 0;
                } else {
                    var factor = Math.pow(1 + monthlyRate, months);
                    monthlyPayment = principal * monthlyRate * factor / (factor - 1);
                    firstPayment = monthlyPayment;
                    lastPayment = monthlyPayment;
                    interestTotal = (monthlyPayment * months) - principal;
                }

                var monthlyFeesTotal = monthlyFees * months;
                var totalCost = interestTotal + commissionAmount + monthlyFeesTotal;

                renderResult(refs.resultLoan, principal, formatMoney);
                renderResult(refs.resultRate, annualRate, formatPercent);
                renderResult(refs.resultMonthly, monthlyPayment + monthlyFees, formatMoney);
                renderResult(refs.resultFirst, firstPayment + monthlyFees, formatMoney);
                renderResult(refs.resultLast, lastPayment + monthlyFees, formatMoney);
                renderResult(refs.resultInterest, interestTotal, formatMoney);
                renderResult(refs.resultCommission, commissionAmount, formatMoney);
                renderResult(refs.resultTotal, totalCost, formatMoney);

                if (refs.wiborRate) {
                    refs.wiborRate.value = wibor.toFixed(2);
                }

                syncExportFields();
            };

            var bindInput = function (element, handler) {
                if (!element) {
                    return;
                }
                element.addEventListener('input', handler);
                element.addEventListener('change', handler);
            };

            bindInput(refs.downPayment, function () {
                syncDownPaymentFromAmount();
                calculate();
            });
            bindInput(refs.downPaymentPercent, function () {
                syncDownPaymentFromPercent();
                calculate();
            });
            bindInput(refs.tenor, calculate);
            bindInput(refs.margin, calculate);
            bindInput(refs.commission, calculate);
            bindInput(refs.years, calculate);
            bindInput(refs.installmentType, calculate);
            bindInput(refs.monthlyFees, calculate);
            if (refs.financeCommission) {
                refs.financeCommission.addEventListener('change', calculate);
            }

            if (refs.tenorButtons && refs.tenorButtons.length) {
                refs.tenorButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        var buttonTenor = button.getAttribute('data-eocrm-calc-wibor-btn') || '';
                        setTenor(buttonTenor);
                        calculate();
                    });
                });
            }

            setTenor(refs.tenor.value);
            updateMetaLabel(ratesPayload);
            syncDownPaymentFromAmount();
            calculate();

            var ajaxUrl = config && typeof config.ajaxUrl === 'string' ? config.ajaxUrl : '';
            var action = config && typeof config.wiborAction === 'string' ? config.wiborAction : '';
            if (ajaxUrl === '' || action === '') {
                return;
            }

            var ajaxRequestUrl = ajaxUrl + '?action=' + encodeURIComponent(action) + '&_=' + encodeURIComponent(String(Date.now()));
            fetch(ajaxRequestUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store'
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('WIBOR response error');
                }
                return response.json();
            }).then(function (json) {
                if (!json || !json.success || !json.data || typeof json.data !== 'object') {
                    return;
                }

                if (json.data.rates && typeof json.data.rates === 'object') {
                    rates = normalizeRates(json.data.rates);
                }

                updateMetaLabel(json.data);
                calculate();
            }).catch(function () {
                updateMetaLabel({
                    source: 'fallback',
                    updatedAt: ''
                });
            });
        });
    }

    function normalizeStageName(value) {
        var text = String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
        if (typeof text.normalize === 'function') {
            text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return text;
    }

    function bindAgreementCommissionStages() {
        var builders = document.querySelectorAll('[data-eocrm-commission-stages-builder]');

        builders.forEach(function (builder) {
            if (builder.getAttribute('data-eocrm-commission-bound') === '1') {
                return;
            }
            builder.setAttribute('data-eocrm-commission-bound', '1');

            var form = builder.closest('form');
            var panel = builder.querySelector('[data-eocrm-commission-stages-panel]');
            var list = builder.querySelector('[data-eocrm-commission-stages-list]');
            var template = builder.querySelector('[data-eocrm-commission-stage-template]');
            var addButton = builder.querySelector('[data-eocrm-commission-stage-add]');
            var toggles = builder.querySelectorAll('[data-eocrm-commission-split-toggle]');
            var stageMap = {};

            try {
                stageMap = JSON.parse(builder.getAttribute('data-stage-options-by-type') || '{}') || {};
            } catch (error) {
                stageMap = {};
            }

            var getTransactionType = function () {
                var select = form ? form.querySelector('select[name="transaction_type"]') : null;
                if (select) {
                    return String(select.value || '').toUpperCase();
                }

                return String(builder.getAttribute('data-current-transaction-type') || '').toUpperCase();
            };

            var getStageOptions = function () {
                var transactionType = getTransactionType();
                if (transactionType && Array.isArray(stageMap[transactionType]) && stageMap[transactionType].length) {
                    return stageMap[transactionType];
                }

                var merged = [];
                Object.keys(stageMap).forEach(function (key) {
                    if (!Array.isArray(stageMap[key])) {
                        return;
                    }
                    stageMap[key].forEach(function (stage) {
                        if (stage && merged.indexOf(stage) === -1) {
                            merged.push(stage);
                        }
                    });
                });

                return merged;
            };

            var refreshStageSelect = function (select) {
                if (!select) {
                    return;
                }

                var current = select.value;
                var options = getStageOptions();
                select.innerHTML = '<option value="">Wybierz etap</option>';
                options.forEach(function (stage) {
                    var option = document.createElement('option');
                    option.value = stage;
                    option.textContent = stage;
                    if (stage === current) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
                if (current && select.value !== current) {
                    var custom = document.createElement('option');
                    custom.value = current;
                    custom.textContent = current;
                    custom.selected = true;
                    select.appendChild(custom);
                }
            };

            var reindexRows = function () {
                if (!list) {
                    return;
                }

                var rows = list.querySelectorAll('[data-eocrm-commission-stage-row]');
                rows.forEach(function (row, index) {
                    row.querySelectorAll('[name]').forEach(function (field) {
                        field.name = field.name.replace(/commission_stages\[[^\]]+\]/, 'commission_stages[' + index + ']');
                    });
                    refreshStageSelect(row.querySelector('[data-eocrm-commission-stage-select]'));
                });
            };

            var addRow = function () {
                if (!list || !template) {
                    return;
                }

                var wrapper = document.createElement('div');
                wrapper.innerHTML = template.innerHTML.replace(/__index__/g, String(list.querySelectorAll('[data-eocrm-commission-stage-row]').length));
                var row = wrapper.firstElementChild;
                if (!row) {
                    return;
                }
                list.appendChild(row);
                reindexRows();
            };

            var isEnabled = function () {
                var enabled = builder.querySelector('[data-eocrm-commission-split-toggle][value="1"]');
                return !!(enabled && enabled.checked);
            };

            var updateVisibility = function () {
                var enabled = isEnabled();
                if (panel) {
                    panel.hidden = !enabled;
                }
                if (enabled && list && list.querySelectorAll('[data-eocrm-commission-stage-row]').length === 0) {
                    addRow();
                }
            };

            toggles.forEach(function (toggle) {
                toggle.addEventListener('change', updateVisibility);
            });

            if (addButton) {
                addButton.addEventListener('click', function () {
                    addRow();
                });
            }

            if (list) {
                list.addEventListener('click', function (event) {
                    var removeButton = event.target.closest('[data-eocrm-commission-stage-remove]');
                    if (!removeButton) {
                        return;
                    }
                    var row = removeButton.closest('[data-eocrm-commission-stage-row]');
                    if (row) {
                        row.parentNode.removeChild(row);
                        reindexRows();
                    }
                });
            }

            var transactionTypeSelect = form ? form.querySelector('select[name="transaction_type"]') : null;
            if (transactionTypeSelect) {
                transactionTypeSelect.addEventListener('change', reindexRows);
            }

            reindexRows();
            updateVisibility();
        });
    }

    function bindAgreementStageTransactionPrompt() {
        var forms = document.querySelectorAll('[data-eocrm-agreement-stage-form]');
        var modal = document.querySelector('[data-eocrm-stage-transaction-modal]');
        var pendingForm = null;

        var updateBodyLock = function () {
            var hasOpenModal = !!document.querySelector('.eocrm-modal:not([hidden])');
            document.body.classList.toggle('eocrm-modal-open', hasOpenModal);
        };

        var closeModal = function () {
            if (modal) {
                modal.hidden = true;
            }
            pendingForm = null;
            updateBodyLock();
        };

        var submitPendingForm = function (createTransaction) {
            if (!pendingForm) {
                closeModal();
                return;
            }

            var form = pendingForm;
            var existing = form.querySelector('input[name="create_transaction_after_stage"]');
            if (existing) {
                existing.parentNode.removeChild(existing);
            }

            if (createTransaction) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'create_transaction_after_stage';
                input.value = '1';
                form.appendChild(input);
            }

            form.setAttribute('data-eocrm-stage-transaction-confirmed', '1');
            closeModal();

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        };

        if (modal && modal.getAttribute('data-eocrm-stage-transaction-bound') !== '1') {
            modal.setAttribute('data-eocrm-stage-transaction-bound', '1');

            var yesButton = modal.querySelector('[data-eocrm-stage-transaction-yes]');
            var noButton = modal.querySelector('[data-eocrm-stage-transaction-no]');
            var closeButtons = modal.querySelectorAll('[data-eocrm-stage-transaction-close]');

            if (yesButton) {
                yesButton.addEventListener('click', function () {
                    submitPendingForm(true);
                });
            }

            if (noButton) {
                noButton.addEventListener('click', function () {
                    submitPendingForm(false);
                });
            }

            closeButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    closeModal();
                });
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            });
        }

        forms.forEach(function (form) {
            if (form.getAttribute('data-eocrm-stage-transaction-bound') === '1') {
                return;
            }
            form.setAttribute('data-eocrm-stage-transaction-bound', '1');

            form.addEventListener('submit', function (event) {
                if (form.getAttribute('data-eocrm-stage-transaction-confirmed') === '1') {
                    form.removeAttribute('data-eocrm-stage-transaction-confirmed');
                    return;
                }

                var select = form.querySelector('select[name="stage_name"]');
                var selectedStage = select ? select.value : '';
                var normalizedStage = normalizeStageName(selectedStage);
                var isFinishedStage = normalizedStage.indexOf('umowa zakonczona') === 0;
                var commissionStages = [];
                try {
                    commissionStages = JSON.parse(form.getAttribute('data-eocrm-commission-transaction-stages') || '[]') || [];
                } catch (error) {
                    commissionStages = [];
                }
                var commissionHasPlan = form.getAttribute('data-eocrm-commission-has-plan') === '1';
                var commissionAllPaid = form.getAttribute('data-eocrm-commission-all-paid') === '1';
                var isCommissionStage = commissionStages.some(function (stage) {
                    return normalizeStageName(stage) === normalizedStage;
                });
                if (isFinishedStage && commissionHasPlan && commissionAllPaid) {
                    return;
                }

                if (!isFinishedStage && !isCommissionStage) {
                    return;
                }

                var existing = form.querySelector('input[name="create_transaction_after_stage"]');
                if (existing) {
                    existing.parentNode.removeChild(existing);
                }

                if (modal) {
                    event.preventDefault();
                    pendingForm = form;
                    var message = modal.querySelector('[data-eocrm-stage-transaction-message]');
                    if (message) {
                        message.textContent = isCommissionStage
                            ? 'Ten etap ma przypisana czesc prowizji. Mozesz od razu przejsc do formularza transakcji prowizyjnej dla etapu: ' + selectedStage + '.'
                            : (commissionHasPlan
                                ? 'Umowa zostanie zakonczona. CRM dopisze brakujace etapy prowizji do istniejacej transakcji czesciowej albo utworzy transakcje, jezeli nie istnieje.'
                                : 'Etap umowy zostanie zmieniony na Umowa zakonczona. Mozesz od razu przejsc do formularza transakcji powiazanej z ta umowa.');
                    }
                    modal.hidden = false;
                    updateBodyLock();
                    return;
                }

                if (window.confirm('Czy utworzy\u0107 Transakcj\u0119?')) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'create_transaction_after_stage';
                    input.value = '1';
                    form.appendChild(input);
                }
            });
        });
    }

    function bindAgreementTransactionChoiceModal() {
        var triggers = document.querySelectorAll('[data-eocrm-transaction-choice-trigger]');
        var modal = document.querySelector('[data-eocrm-transaction-choice-modal]');
        if (!triggers.length || !modal) {
            return;
        }

        if (modal.getAttribute('data-eocrm-transaction-choice-bound') === '1') {
            return;
        }
        modal.setAttribute('data-eocrm-transaction-choice-bound', '1');

        var activeExistingUrl = '';
        var activeNewUrl = '';
        var existingButton = modal.querySelector('[data-eocrm-transaction-choice-existing]');
        var newButton = modal.querySelector('[data-eocrm-transaction-choice-new]');
        var closeButtons = modal.querySelectorAll('[data-eocrm-transaction-choice-close]');

        var updateBodyLock = function () {
            var hasOpenModal = !!document.querySelector('.eocrm-modal:not([hidden])');
            document.body.classList.toggle('eocrm-modal-open', hasOpenModal);
        };

        var closeModal = function () {
            modal.hidden = true;
            activeExistingUrl = '';
            activeNewUrl = '';
            updateBodyLock();
        };

        var openModal = function (existingUrl, newUrl) {
            activeExistingUrl = existingUrl || '';
            activeNewUrl = newUrl || '';
            modal.hidden = false;
            updateBodyLock();
        };

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                var existingUrl = trigger.getAttribute('data-existing-url') || '';
                var newUrl = trigger.getAttribute('data-new-url') || trigger.getAttribute('href') || '';
                if (!existingUrl || !newUrl) {
                    return;
                }

                event.preventDefault();
                openModal(existingUrl, newUrl);
            });
        });

        if (existingButton) {
            existingButton.addEventListener('click', function () {
                if (activeExistingUrl) {
                    window.location.href = activeExistingUrl;
                }
            });
        }

        if (newButton) {
            newButton.addEventListener('click', function () {
                if (activeNewUrl) {
                    window.location.href = activeNewUrl;
                }
            });
        }

        closeButtons.forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    }

    function formatTransactionMoney(value, currency) {
        var number = parseFloat(String(value || '').replace(/\s/g, '').replace(',', '.'));
        var normalizedCurrency = String(currency || 'PLN').toUpperCase();
        if (['PLN', 'EUR', 'USD', 'GBP'].indexOf(normalizedCurrency) === -1) {
            normalizedCurrency = 'PLN';
        }
        if (!isFinite(number)) {
            return '-';
        }

        return number.toLocaleString('pl-PL', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }) + ' ' + normalizedCurrency;
    }

    function bindTransactionCommissionStageSelector() {
        var forms = document.querySelectorAll('[data-eocrm-transaction-form]');
        forms.forEach(function (form) {
            if (form.getAttribute('data-eocrm-transaction-commission-bound') === '1') {
                return;
            }
            form.setAttribute('data-eocrm-transaction-commission-bound', '1');

            var select = form.querySelector('[data-eocrm-transaction-commission-stage]');
            var amountInput = form.querySelector('[data-eocrm-transaction-commission-amount]');
            var unitSelect = form.querySelector('[data-eocrm-transaction-commission-unit]');
            var dateField = form.querySelector('[data-eocrm-transaction-date-field]');
            var priceInput = form.querySelector('[data-eocrm-transaction-price]');
            var priceHint = form.querySelector('[data-eocrm-transaction-price-hint]');
            var submitButton = form.querySelector('[data-eocrm-transaction-submit]');
            if (!select || !amountInput || !unitSelect) {
                return;
            }

            var refreshSubmitLabel = function (isPartial) {
                if (!submitButton) {
                    return;
                }
                var label = isPartial
                    ? submitButton.getAttribute('data-partial-label')
                    : submitButton.getAttribute('data-default-label');
                if (label) {
                    submitButton.textContent = label;
                }
            };

            var refreshPriceRequirement = function () {
                if (!priceInput) {
                    return;
                }

                var option = select.options[select.selectedIndex];
                var hasStage = !!(option && option.value);
                var requiresDate = option && option.getAttribute('data-requires-date') === '1';
                var unit = (unitSelect.value || '').toString().toUpperCase();
                var requiresPrice = !hasStage || requiresDate || unit === '%';
                priceInput.required = requiresPrice;
                if (priceHint) {
                    priceHint.hidden = requiresPrice;
                }
            };

            var refresh = function () {
                var option = select.options[select.selectedIndex];
                var hasStage = !!(option && option.value);
                if (!hasStage) {
                    refreshSubmitLabel(false);
                    if (dateField) {
                        dateField.hidden = false;
                        var dateInputDefault = dateField.querySelector('input[name="transaction_date"]');
                        if (dateInputDefault) {
                            dateInputDefault.required = true;
                        }
                    }
                    refreshPriceRequirement();
                    return;
                }

                var amount = option.getAttribute('data-commission-amount') || '';
                var unit = option.getAttribute('data-commission-unit') || '';
                var requiresDate = option.getAttribute('data-requires-date') === '1';
                refreshSubmitLabel(!requiresDate);
                if (amount !== '') {
                    amountInput.value = String(amount).replace(/\.00$/, '');
                }
                if (unit !== '') {
                    unitSelect.value = unit;
                }
                if (dateField) {
                    dateField.hidden = !requiresDate;
                    var dateInput = dateField.querySelector('input[name="transaction_date"]');
                    if (dateInput) {
                        dateInput.required = requiresDate;
                    }
                }
                refreshPriceRequirement();
            };

            select.addEventListener('change', refresh);
            unitSelect.addEventListener('change', refreshPriceRequirement);
            refresh();
        });
    }

    function bindTransactionPropertySelector() {
        var forms = document.querySelectorAll('[data-eocrm-transaction-form]');
        forms.forEach(function (form) {
            if (form.getAttribute('data-eocrm-transaction-bound') === '1') {
                return;
            }
            form.setAttribute('data-eocrm-transaction-bound', '1');

            var select = form.querySelector('[data-eocrm-transaction-property-select]');
            var offerPriceInput = form.querySelector('[data-eocrm-transaction-offer-price]');
            if (!select || !offerPriceInput) {
                return;
            }

            var refresh = function () {
                var option = select.options[select.selectedIndex];
                if (!option) {
                    offerPriceInput.value = '-';
                    return;
                }

                offerPriceInput.value = formatTransactionMoney(
                    option.getAttribute('data-offer-price') || '',
                    option.getAttribute('data-offer-currency') || 'PLN'
                );
            };

            select.addEventListener('change', refresh);
            refresh();
        });
    }

    function bindTransactionCooperationFields() {
        var forms = document.querySelectorAll('[data-eocrm-transaction-cooperation-form]');
        forms.forEach(function (form) {
            if (form.getAttribute('data-eocrm-transaction-cooperation-bound') === '1') {
                return;
            }
            form.setAttribute('data-eocrm-transaction-cooperation-bound', '1');

            var details = form.querySelector('[data-eocrm-cooperation-details]');
            var ownFields = form.querySelectorAll('[data-eocrm-cooperation-own]');
            var otherFields = form.querySelectorAll('[data-eocrm-cooperation-other]');
            var cooperationRadios = form.querySelectorAll('input[name="cooperation_agent"]');
            var typeRadios = form.querySelectorAll('input[name="cooperation_type"]');

            var getCheckedValue = function (name, fallback) {
                var checked = form.querySelector('input[name="' + name + '"]:checked');
                return checked ? checked.value : fallback;
            };

            var setGroupVisible = function (nodes, visible) {
                nodes.forEach(function (node) {
                    if (visible) {
                        node.removeAttribute('hidden');
                    } else {
                        node.setAttribute('hidden', '');
                    }
                });
            };

            var refresh = function () {
                var enabled = getCheckedValue('cooperation_agent', '0') === '1';
                var type = getCheckedValue('cooperation_type', 'own_office');

                if (details) {
                    if (enabled) {
                        details.removeAttribute('hidden');
                    } else {
                        details.setAttribute('hidden', '');
                    }
                }

                setGroupVisible(ownFields, enabled && type === 'own_office');
                setGroupVisible(otherFields, enabled && type === 'other_office');
            };

            cooperationRadios.forEach(function (radio) {
                radio.addEventListener('change', refresh);
            });
            typeRadios.forEach(function (radio) {
                radio.addEventListener('change', refresh);
            });
            refresh();
        });
    }

    function bindDashboardRemunerationTabs() {
        var widgets = document.querySelectorAll('[data-eocrm-dash-remuneration]');
        widgets.forEach(function (widget) {
            if (widget.getAttribute('data-eocrm-dash-remuneration-bound') === '1') {
                return;
            }
            widget.setAttribute('data-eocrm-dash-remuneration-bound', '1');

            var tabs = widget.querySelectorAll('[data-eocrm-dash-rem-tab]');
            var panels = widget.querySelectorAll('[data-eocrm-dash-rem-panel]');

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var key = tab.getAttribute('data-eocrm-dash-rem-tab') || '';

                    tabs.forEach(function (item) {
                        var active = item === tab;
                        item.classList.toggle('is-active', active);
                        item.setAttribute('aria-selected', active ? 'true' : 'false');
                    });

                    panels.forEach(function (panel) {
                        var active = panel.getAttribute('data-eocrm-dash-rem-panel') === key;
                        panel.classList.toggle('is-active', active);
                        if (active) {
                            panel.removeAttribute('hidden');
                        } else {
                            panel.setAttribute('hidden', '');
                        }
                    });
                });
            });
        });
    }

    function bindCrmSimpleMediaPickers() {
        var buttons = document.querySelectorAll('[data-eocrm-crm-media]');
        if (!buttons.length || typeof wp === 'undefined' || !wp.media) {
            return;
        }

        var escapeAttr = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        };

        buttons.forEach(function (button) {
            if (button.getAttribute('data-eocrm-crm-media-bound') === '1') {
                return;
            }
            button.setAttribute('data-eocrm-crm-media-bound', '1');

            button.addEventListener('click', function () {
                var inputId = button.getAttribute('data-eocrm-crm-media') || '';
                var previewId = button.getAttribute('data-eocrm-preview') || '';
                var input = inputId ? document.getElementById(inputId) : null;
                var preview = previewId ? document.getElementById(previewId) : null;
                if (!input) {
                    return;
                }

                var frame = wp.media({
                    title: 'Wybierz obraz',
                    button: { text: 'Uzyj obrazu' },
                    library: { type: 'image' },
                    multiple: false
                });

                frame.on('select', function () {
                    var selection = frame.state().get('selection').first();
                    if (!selection) {
                        return;
                    }

                    var data = selection.toJSON();
                    if (!data || !data.id) {
                        return;
                    }

                    input.value = String(data.id);
                    if (preview) {
                        var sizes = data.sizes || {};
                        var previewUrl = '';
                        if (sizes.medium && sizes.medium.url) {
                            previewUrl = sizes.medium.url;
                        } else if (sizes.thumbnail && sizes.thumbnail.url) {
                            previewUrl = sizes.thumbnail.url;
                        } else {
                            previewUrl = data.url || '';
                        }

                        preview.innerHTML = previewUrl
                            ? '<img src="' + escapeAttr(previewUrl) + '" alt="Podglad">'
                            : '';
                    }
                });

                frame.open();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindLiveFilters();
        bindCrmPropertyTableFilters();
        bindCrmTransactionTableFilters();
        bindCrmClientTableFilters();
        bindDynamicToggles();
        bindInputButtonStates();
        bindAgreementFormDynamics();
        bindAgreementCommissionStages();
        bindPropertyFormDynamics();
        bindSearchFormDynamics();
        bindClientFormDynamics();
        bindPublicOffersFilters();
        bindPublicOffersMap();
        bindPublicOfferSlider();
        bindPublicOfferFloorPlans();
        bindPublicOfferLightbox();
        bindPropertyPdfChoiceModal();
        bindMortgageCalculator();
        bindAgreementStageTransactionPrompt();
        bindAgreementTransactionChoiceModal();
        bindTransactionCommissionStageSelector();
        bindTransactionPropertySelector();
        bindTransactionCooperationFields();
        bindDashboardRemunerationTabs();
        bindCrmSimpleMediaPickers();
    });
})();
