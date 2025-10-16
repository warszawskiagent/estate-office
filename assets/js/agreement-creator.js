(function () {
    'use strict';

    const config = window.EstateOfficeAgreementCreator || {};
    const overlay = document.querySelector('[data-eo-agreement-creator]');
    if (!overlay || !config.ajaxUrl || !config.nonce) {
        return;
    }

    const steps = Array.from(overlay.querySelectorAll('[data-eo-agreement-step]'));
    const stepIndicators = Array.from(overlay.querySelectorAll('[data-eo-agreement-steps] div'));
    const openButtons = document.querySelectorAll('[data-eo-agreement-open]');
    const closeButtons = overlay.querySelectorAll('[data-eo-agreement-close]');
    const backdrop = overlay.querySelector('.estate-office-agreement-creator__backdrop');
    const agreementForm = overlay.querySelector('form[data-eo-agreement-step="1"]');
    const clientSearchInput = overlay.querySelector('[data-eo-agreement-client-search]');
    const clientResults = overlay.querySelector('[data-eo-agreement-search-results]');
    const selectedClients = overlay.querySelector('[data-eo-agreement-selected]');
    const clientForm = overlay.querySelector('form[data-eo-agreement-new-client]');
    const clientPrompt = overlay.querySelector('[data-eo-agreement-client-prompt]');
    const clientPromptYes = overlay.querySelector('[data-eo-agreement-client-yes]');
    const clientPromptNo = overlay.querySelector('[data-eo-agreement-client-no]');
    const newClientDetails = overlay.querySelector('[data-eo-agreement-new-client-details]');
    const nextButton = overlay.querySelector('[data-eo-agreement-next]');
    const backButton = overlay.querySelector('[data-eo-agreement-back]');
    const finishButton = overlay.querySelector('[data-eo-agreement-finish]');
    const summaryBox = overlay.querySelector('[data-eo-agreement-summary]');
    const propertyForm = overlay.querySelector('[data-eo-agreement-property]');
    const propertyTransactionInput = overlay.querySelector('[data-eo-agreement-property-transaction]');
    const propertyMessage = overlay.querySelector('[data-eo-agreement-property-message]');
    const propertySearchInput = overlay.querySelector('[data-eo-agreement-property-search]');
    const propertyResults = overlay.querySelector('[data-eo-agreement-property-results]');
    const searchForm = overlay.querySelector('[data-eo-agreement-search]');
    const searchTransactionInput = overlay.querySelector('[data-eo-agreement-search-transaction]');
    const searchMessage = overlay.querySelector('[data-eo-agreement-search-message]');
    const searchSearchInput = overlay.querySelector('[data-eo-agreement-search-search]');
    const searchResults = overlay.querySelector('[data-eo-agreement-search-results]');
    const step3Heading = overlay.querySelector('[data-eo-agreement-step3-heading]');
    const step3Description = overlay.querySelector('[data-eo-agreement-step3-description]');
    const prevButton = overlay.querySelector('[data-eo-agreement-prev]');

    const state = {
        agreementId: 0,
        clients: [],
        redirect: '',
        transactionType: '',
        record: null,
    };

    const hideClientPrompt = () => {
        if (!clientPrompt) {
            return;
        }

        clientPrompt.classList.remove('is-visible');
        clientPrompt.setAttribute('hidden', 'hidden');
    };

    const showClientPrompt = () => {
        if (!clientPrompt || state.clients.length < 1) {
            return;
        }

        clientPrompt.classList.add('is-visible');
        clientPrompt.removeAttribute('hidden');

        window.requestAnimationFrame(() => {
            clientPrompt.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    };

    if (propertySearchInput && config.placeholders?.propertySearch) {
        propertySearchInput.setAttribute('placeholder', config.placeholders.propertySearch);
    }

    if (searchSearchInput && config.placeholders?.searchSearch) {
        searchSearchInput.setAttribute('placeholder', config.placeholders.searchSearch);
    }

    const propertyTransactions = Array.isArray(config.propertyTransactions) ? config.propertyTransactions : [];
    const searchTransactions = Array.isArray(config.searchTransactions) ? config.searchTransactions : [];

    const setMessage = (container, type, text) => {
        if (!container) {
            return;
        }

        container.textContent = text || '';
        container.classList.remove('is-success', 'is-error');
        if (!text) {
            return;
        }

        container.classList.add(type === 'success' ? 'is-success' : 'is-error');
    };

    const switchStep = (index) => {
        steps.forEach((step) => {
            if (parseInt(step.getAttribute('data-eo-agreement-step'), 10) === index) {
                step.classList.add('is-active');
                step.removeAttribute('hidden');
            } else {
                step.classList.remove('is-active');
                step.setAttribute('hidden', 'hidden');
            }
        });

        stepIndicators.forEach((indicator) => {
            if (parseInt(indicator.getAttribute('data-step'), 10) === index) {
                indicator.classList.add('is-active');
            } else {
                indicator.classList.remove('is-active');
            }
        });
    };

    const openOverlay = () => {
        overlay.removeAttribute('hidden');
        overlay.classList.add('is-visible');
        switchStep(1);
        overlay.scrollTop = 0;
    };

    const resetState = () => {
        state.agreementId = 0;
        state.clients = [];
        state.redirect = '';
        state.transactionType = '';
        state.record = null;
        if (agreementForm) {
            agreementForm.reset();
        }
        if (clientForm) {
            clientForm.reset();
        }
        if (propertyForm) {
            propertyForm.reset();
            propertyForm.setAttribute('hidden', 'hidden');
        }
        if (searchForm) {
            searchForm.reset();
            searchForm.setAttribute('hidden', 'hidden');
        }
        if (propertySearchInput) {
            propertySearchInput.value = '';
        }
        if (searchSearchInput) {
            searchSearchInput.value = '';
        }
        if (propertyResults) {
            propertyResults.innerHTML = '';
        }
        if (searchResults) {
            searchResults.innerHTML = '';
        }
        if (finishButton) {
            finishButton.setAttribute('disabled', 'disabled');
        }
        setMessage(propertyMessage, '', '');
        setMessage(searchMessage, '', '');
        renderClients();
        renderSummary();
        if (clientResults) {
            clientResults.innerHTML = '';
        }
        hideClientPrompt();
    };

    const closeOverlay = () => {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('hidden', 'hidden');
        resetState();
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openOverlay();
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            closeOverlay();
        });
    });

    overlay.addEventListener('click', (event) => {
        if (backdrop && event.target === backdrop) {
            closeOverlay();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && overlay.classList.contains('is-visible')) {
            closeOverlay();
        }
    });

    const serializeAgreement = () => {
        const formData = new FormData(agreementForm);
        const payload = new FormData();
        payload.append('action', 'estate_office_create_agreement');
        payload.append('nonce', config.nonce);

        const transactionValue = formData.get('estate_agreement_transaction_type');
        state.transactionType = typeof transactionValue === 'string' ? transactionValue : '';

        formData.forEach((value, key) => {
            payload.append(key, value);
        });

        if (state.agreementId > 0) {
            payload.append('agreement_id', String(state.agreementId));
        }

        return payload;
    };

    const renderClients = () => {
        if (!selectedClients) {
            return;
        }

        hideClientPrompt();

        if (!state.clients.length) {
            selectedClients.innerHTML = '<p>' + (config.messages?.emptyClients || '') + '</p>';
            return;
        }

        selectedClients.innerHTML = '';
        state.clients.forEach((client) => {
            const pill = document.createElement('div');
            pill.className = 'estate-office-agreement-creator__client-pill';
            const content = document.createElement('div');
            content.innerHTML = '<strong>' + (client.name || '') + '</strong>'
                + (client.email ? '<br><span>' + client.email + '</span>' : '')
                + (client.phone ? '<br><span>' + client.phone + '</span>' : '');
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.textContent = '×';
            removeButton.setAttribute('aria-label', 'Usuń');
            removeButton.addEventListener('click', () => detachClient(client.id));
            pill.appendChild(content);
            pill.appendChild(removeButton);
            selectedClients.appendChild(pill);
        });
    };

    const renderSummary = () => {
        if (!summaryBox) {
            return;
        }

        summaryBox.innerHTML = '';

        if (!state.agreementId) {
            return;
        }

        const appendParagraph = (text, className) => {
            if (!text) {
                return;
            }
            const paragraph = document.createElement('p');
            if (className) {
                paragraph.className = className;
            }
            paragraph.textContent = text;
            summaryBox.appendChild(paragraph);
        };

        const clientsCount = state.clients.length;
        const singularTemplate = config.messages?.clientsSummarySingular || '%d klient przypisany do umowy.';
        const pluralTemplate = config.messages?.clientsSummaryPlural || '%d klientów przypisanych do umowy.';
        const template = clientsCount === 1 ? singularTemplate : pluralTemplate;
        appendParagraph(template.replace('%d', String(clientsCount)));

        if (state.record) {
            const recordLabel = state.record.type === 'search'
                ? (config.messages?.recordLabelSearch || 'Poszukiwanie')
                : (config.messages?.recordLabelProperty || 'Nieruchomość');

            const infoParagraph = document.createElement('p');
            const strong = document.createElement('strong');
            strong.textContent = recordLabel + ':';
            infoParagraph.appendChild(strong);
            if (state.record.title) {
                infoParagraph.appendChild(document.createTextNode(' ' + state.record.title));
            }
            summaryBox.appendChild(infoParagraph);

            appendParagraph(state.record.reference || '');
            appendParagraph(state.record.propertyType || '');
            appendParagraph(state.record.location || '');
            appendParagraph(state.record.manager || '');

            if (state.record.url) {
                const linkParagraph = document.createElement('p');
                const link = document.createElement('a');
                link.href = state.record.url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = config.messages?.viewRecord || 'Otwórz rekord w CRM';
                link.className = 'estate-office-agreement-creator__link';
                linkParagraph.appendChild(link);
                summaryBox.appendChild(linkParagraph);
            }

            const changeParagraph = document.createElement('p');
            const changeButton = document.createElement('button');
            changeButton.type = 'button';
            changeButton.className = 'estate-office-agreement-creator__ghost';
            changeButton.dataset.eoAgreementClearRecord = '1';
            changeButton.textContent = config.messages?.recordChange || 'Wybierz inny rekord';
            changeParagraph.appendChild(changeButton);
            summaryBox.appendChild(changeParagraph);
        } else {
            const propertyTransactions = Array.isArray(config.propertyTransactions) ? config.propertyTransactions : [];
            const searchTransactions = Array.isArray(config.searchTransactions) ? config.searchTransactions : [];
            let pendingMessage = '';

            if (propertyTransactions.includes(state.transactionType)) {
                pendingMessage = config.messages?.recordMissingProperty || '';
            } else if (searchTransactions.includes(state.transactionType)) {
                pendingMessage = config.messages?.recordMissingSearch || '';
            }

            appendParagraph(pendingMessage);
        }

        if (state.redirect) {
            const linkParagraph = document.createElement('p');
            const link = document.createElement('a');
            link.className = 'estate-office-agreement-creator__primary';
            link.href = state.redirect;
            link.textContent = config.messages?.viewAgreement || 'Przejdź do szczegółów umowy';
            linkParagraph.appendChild(link);
            summaryBox.appendChild(linkParagraph);
        }
    };

    const handleAgreementSubmit = (event) => {
        event.preventDefault();
        const message = agreementForm?.querySelector('[data-eo-agreement-message]');
        setMessage(message, '', '');

        const payload = serializeAgreement();

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                if (!ok || !body?.success) {
                    const errorMessage = body?.data?.message || config.messages?.step1Error || '';
                    setMessage(message, 'error', errorMessage);
                    return;
                }

                state.agreementId = parseInt(body.data?.agreementId || 0, 10) || 0;
                state.record = null;
                state.redirect = '';
                setMessage(message, 'success', config.messages?.step1Success || '');
                switchStep(2);
                renderSummary();
            })
            .catch(() => {
                setMessage(message, 'error', config.messages?.step1Error || '');
            });
    };

    if (agreementForm) {
        agreementForm.addEventListener('submit', handleAgreementSubmit);
    }

    const debounce = (callback, wait) => {
        let timeout;
        return (...args) => {
            window.clearTimeout(timeout);
            timeout = window.setTimeout(() => callback.apply(null, args), wait);
        };
    };

    const renderSearchResults = (clients) => {
        if (!clientResults) {
            return;
        }

        if (!clients || !clients.length) {
            clientResults.innerHTML = '<p>' + (config.messages?.noResults || '') + '</p>';
            return;
        }

        clientResults.innerHTML = '';
        clients.forEach((client) => {
            const row = document.createElement('div');
            row.className = 'estate-office-agreement-creator__search-item';
            const info = document.createElement('div');
            info.innerHTML = '<strong>' + (client.name || '') + '</strong>'
                + (client.email ? '<span>' + client.email + '</span>' : '')
                + (client.phone ? '<span>' + client.phone + '</span>' : '');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Dodaj';
            button.addEventListener('click', () => attachClient(client.id));
            row.appendChild(info);
            row.appendChild(button);
            clientResults.appendChild(row);
        });
    };

    const searchClients = debounce((term) => {
        if (!state.agreementId) {
            renderSearchResults([]);
            return;
        }

        const params = new URLSearchParams({
            action: 'estate_office_search_clients',
            nonce: config.nonce,
            term: term || '',
        });

        fetch(config.ajaxUrl + '?' + params.toString(), {
            credentials: 'same-origin',
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                if (!ok || !body?.success) {
                    renderSearchResults([]);
                    return;
                }
                renderSearchResults(body.data?.clients || []);
            })
            .catch(() => renderSearchResults([]));
    }, 250);

    if (clientSearchInput) {
        clientSearchInput.addEventListener('input', (event) => {
            const value = event.target.value || '';
            searchClients(value);
        });
    }

    const attachPropertyRecord = (propertyId) => {
        if (!state.agreementId || !propertyId) {
            return;
        }

        setMessage(propertyMessage, '', '');

        const payload = new FormData();
        payload.append('action', 'estate_office_attach_property');
        payload.append('nonce', config.nonce);
        payload.append('agreement_id', String(state.agreementId));
        payload.append('property_id', String(propertyId));

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                if (!ok || !body?.success) {
                    setMessage(propertyMessage, 'error', body?.data?.message || config.messages?.propertyError || config.messages?.genericError || '');
                    return;
                }

                state.record = body.data?.record || null;
                state.redirect = body.data?.redirect || state.redirect;

                if (propertyForm) {
                    propertyForm.setAttribute('hidden', 'hidden');
                }
                if (propertyResults) {
                    propertyResults.innerHTML = '';
                }
                if (propertySearchInput) {
                    propertySearchInput.value = '';
                }
                if (step3Heading) {
                    step3Heading.textContent = config.messages?.finalizeSuccess || step3Heading.textContent;
                }
                if (step3Description) {
                    step3Description.textContent = '';
                }

                setMessage(propertyMessage, 'success', config.messages?.propertyAttached || config.messages?.propertySuccess || '');

                if (!state.redirect) {
                    fetchFinalizeRedirect()
                        .then((redirect) => {
                            state.redirect = redirect;
                            if (finishButton && state.redirect) {
                                finishButton.removeAttribute('disabled');
                            }
                            renderSummary();
                        })
                        .catch(() => {
                            setMessage(propertyMessage, 'error', config.messages?.genericError || '');
                        });
                } else if (finishButton) {
                    finishButton.removeAttribute('disabled');
                }

                renderSummary();
            })
            .catch(() => {
                setMessage(propertyMessage, 'error', config.messages?.propertyError || config.messages?.genericError || '');
            });
    };

    const renderPropertyResults = (records) => {
        if (!propertyResults) {
            return;
        }

        propertyResults.innerHTML = '';

        if (!records || !records.length) {
            if ((propertySearchInput?.value || '').trim() !== '') {
                const empty = document.createElement('p');
                empty.textContent = config.messages?.noResults || '';
                propertyResults.appendChild(empty);
            }
            return;
        }

        records.forEach((record) => {
            if (!record || typeof record.id === 'undefined') {
                return;
            }

            const row = document.createElement('div');
            row.className = 'estate-office-agreement-creator__search-item';

            const info = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = record.title || '';
            info.appendChild(title);

            if (record.reference) {
                const reference = document.createElement('span');
                reference.textContent = record.reference;
                info.appendChild(reference);
            }

            if (record.location) {
                const location = document.createElement('span');
                location.textContent = record.location;
                info.appendChild(location);
            }

            if (record.manager) {
                const manager = document.createElement('span');
                manager.textContent = record.manager;
                info.appendChild(manager);
            }

            row.appendChild(info);

            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = config.messages?.recordSelect || 'Wybierz';
            button.addEventListener('click', () => attachPropertyRecord(record.id));
            row.appendChild(button);

            propertyResults.appendChild(row);
        });
    };

    const searchProperties = debounce((term) => {
        if (!state.agreementId || !propertyTransactions.includes(state.transactionType || '')) {
            if (propertyResults) {
                propertyResults.innerHTML = '';
            }
            return;
        }

        const trimmed = (term || '').trim();
        if (trimmed.length < 2) {
            if (propertyResults) {
                propertyResults.innerHTML = '';
            }
            return;
        }

        const params = new URLSearchParams({
            action: 'estate_office_search_properties',
            nonce: config.nonce,
            agreement_id: String(state.agreementId),
            transaction_type: state.transactionType || '',
            term: trimmed,
        });

        fetch(config.ajaxUrl + '?' + params.toString(), {
            credentials: 'same-origin',
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                if (!ok || !body?.success) {
                    renderPropertyResults([]);
                    return;
                }

                renderPropertyResults(body.data?.records || []);
            })
            .catch(() => {
                renderPropertyResults([]);
            });
    }, 250);

    if (propertySearchInput) {
        propertySearchInput.addEventListener('input', (event) => {
            const value = event.target.value || '';
            searchProperties(value);
        });
    }

    const attachSearchRecord = (searchId) => {
        if (!state.agreementId || !searchId) {
            return;
        }

        setMessage(searchMessage, '', '');

        const payload = new FormData();
        payload.append('action', 'estate_office_attach_search');
        payload.append('nonce', config.nonce);
        payload.append('agreement_id', String(state.agreementId));
        payload.append('search_id', String(searchId));

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                if (!ok || !body?.success) {
                    setMessage(searchMessage, 'error', body?.data?.message || config.messages?.searchError || config.messages?.genericError || '');
                    return;
                }

                state.record = body.data?.record || null;
                state.redirect = body.data?.redirect || state.redirect;

                if (searchForm) {
                    searchForm.setAttribute('hidden', 'hidden');
                }
                if (searchResults) {
                    searchResults.innerHTML = '';
                }
                if (searchSearchInput) {
                    searchSearchInput.value = '';
                }
                if (step3Heading) {
                    step3Heading.textContent = config.messages?.finalizeSuccess || step3Heading.textContent;
                }
                if (step3Description) {
                    step3Description.textContent = '';
                }

                setMessage(searchMessage, 'success', config.messages?.searchAttached || config.messages?.searchSuccess || '');

                if (!state.redirect) {
                    fetchFinalizeRedirect()
                        .then((redirect) => {
                            state.redirect = redirect;
                            if (finishButton && state.redirect) {
                                finishButton.removeAttribute('disabled');
                            }
                            renderSummary();
                        })
                        .catch(() => {
                            setMessage(searchMessage, 'error', config.messages?.genericError || '');
                        });
                } else if (finishButton) {
                    finishButton.removeAttribute('disabled');
                }

                renderSummary();
            })
            .catch(() => {
                setMessage(searchMessage, 'error', config.messages?.searchError || config.messages?.genericError || '');
            });
    };

    const renderSearchRecords = (records) => {
        if (!searchResults) {
            return;
        }

        searchResults.innerHTML = '';

        if (!records || !records.length) {
            if ((searchSearchInput?.value || '').trim() !== '') {
                const empty = document.createElement('p');
                empty.textContent = config.messages?.noResults || '';
                searchResults.appendChild(empty);
            }
            return;
        }

        records.forEach((record) => {
            if (!record || typeof record.id === 'undefined') {
                return;
            }

            const row = document.createElement('div');
            row.className = 'estate-office-agreement-creator__search-item';

            const info = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = record.title || '';
            info.appendChild(title);

            if (record.reference) {
                const reference = document.createElement('span');
                reference.textContent = record.reference;
                info.appendChild(reference);
            }

            if (record.location) {
                const location = document.createElement('span');
                location.textContent = record.location;
                info.appendChild(location);
            }

            if (record.propertyType) {
                const type = document.createElement('span');
                type.textContent = record.propertyType;
                info.appendChild(type);
            }

            if (record.manager) {
                const manager = document.createElement('span');
                manager.textContent = record.manager;
                info.appendChild(manager);
            }

            row.appendChild(info);

            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = config.messages?.recordSelect || 'Wybierz';
            button.addEventListener('click', () => attachSearchRecord(record.id));
            row.appendChild(button);

            searchResults.appendChild(row);
        });
    };

    const searchSearchRecords = debounce((term) => {
        if (!state.agreementId || !searchTransactions.includes(state.transactionType || '')) {
            if (searchResults) {
                searchResults.innerHTML = '';
            }
            return;
        }

        const trimmed = (term || '').trim();
        if (trimmed.length < 2) {
            if (searchResults) {
                searchResults.innerHTML = '';
            }
            return;
        }

        const params = new URLSearchParams({
            action: 'estate_office_search_searches',
            nonce: config.nonce,
            agreement_id: String(state.agreementId),
            transaction_type: state.transactionType || '',
            term: trimmed,
        });

        fetch(config.ajaxUrl + '?' + params.toString(), {
            credentials: 'same-origin',
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                if (!ok || !body?.success) {
                    renderSearchRecords([]);
                    return;
                }

                renderSearchRecords(body.data?.records || []);
            })
            .catch(() => {
                renderSearchRecords([]);
            });
    }, 250);

    if (searchSearchInput) {
        searchSearchInput.addEventListener('input', (event) => {
            const value = event.target.value || '';
            searchSearchRecords(value);
        });
    }

    const attachClient = (clientId) => {
        if (!state.agreementId || !clientId) {
            return;
        }

        const payload = new FormData();
        payload.append('action', 'estate_office_attach_client');
        payload.append('nonce', config.nonce);
        payload.append('agreement_id', String(state.agreementId));
        payload.append('client_id', String(clientId));

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                const message = overlay.querySelector('[data-eo-agreement-step="2"] [data-eo-agreement-message]');
                if (!ok || !body?.success) {
                    setMessage(message, 'error', body?.data?.message || config.messages?.genericError || '');
                    return;
                }

                state.clients = body.data?.clients || [];
                renderClients();
                setMessage(message, 'success', config.messages?.clientAdded || '');
                renderSummary();
                showClientPrompt();
                if (clientResults) {
                    clientResults.innerHTML = '';
                }
            })
            .catch(() => {
                const message = overlay.querySelector('[data-eo-agreement-step="2"] [data-eo-agreement-message]');
                setMessage(message, 'error', config.messages?.genericError || '');
            });
    };

    const detachClient = (clientId) => {
        if (!state.agreementId || !clientId) {
            return;
        }

        const payload = new FormData();
        payload.append('action', 'estate_office_detach_client');
        payload.append('nonce', config.nonce);
        payload.append('agreement_id', String(state.agreementId));
        payload.append('client_id', String(clientId));

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                const message = overlay.querySelector('[data-eo-agreement-step="2"] [data-eo-agreement-message]');
                if (!ok || !body?.success) {
                    setMessage(message, 'error', body?.data?.message || config.messages?.genericError || '');
                    return;
                }

                state.clients = body.data?.clients || [];
                renderClients();
                setMessage(message, 'success', config.messages?.clientRemoved || '');
                renderSummary();
                if (!state.clients.length) {
                    hideClientPrompt();
                }
            })
            .catch(() => {
                const message = overlay.querySelector('[data-eo-agreement-step="2"] [data-eo-agreement-message]');
                setMessage(message, 'error', config.messages?.genericError || '');
            });
    };

    if (clientForm) {
        clientForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!state.agreementId) {
                return;
            }

            const message = clientForm.querySelector('[data-eo-agreement-client-message]');
            setMessage(message, '', '');

            const formData = new FormData(clientForm);
            const payload = new FormData();
            payload.append('action', 'estate_office_create_client');
            payload.append('nonce', config.nonce);
            payload.append('agreement_id', String(state.agreementId));

            formData.forEach((value, key) => {
                if (typeof key === 'string' && key.startsWith('dynamic[')) {
                    payload.append('client[dynamic]' + key.substring('dynamic'.length), value);
                    return;
                }

                payload.append('client[' + key + ']', value);
            });

            fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payload,
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
                .then(({ ok, body }) => {
                    if (!ok || !body?.success) {
                        setMessage(message, 'error', body?.data?.message || config.messages?.genericError || '');
                        return;
                    }

                    state.clients = body.data?.clients || [];
                    clientForm.reset();
                    renderClients();
                    renderSummary();
                    setMessage(message, 'success', config.messages?.clientCreated || '');
                    showClientPrompt();
                })
                .catch(() => {
                    setMessage(message, 'error', config.messages?.genericError || '');
                });
        });
    }

    if (propertyForm) {
        propertyForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!state.agreementId) {
                return;
            }

            setMessage(propertyMessage, '', '');

            const payload = new FormData(propertyForm);
            payload.append('action', 'estate_office_create_property');
            payload.append('nonce', config.nonce);
            payload.append('agreement_id', String(state.agreementId));

            fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payload,
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
                .then(({ ok, body }) => {
                    if (!ok || !body?.success) {
                        setMessage(propertyMessage, 'error', body?.data?.message || config.messages?.propertyError || config.messages?.genericError || '');
                        return;
                    }

                    state.record = body.data?.record || null;
                    state.redirect = body.data?.redirect || state.redirect;
                    setMessage(propertyMessage, 'success', config.messages?.propertySuccess || '');
                    if (propertyForm) {
                        propertyForm.setAttribute('hidden', 'hidden');
                    }
                    if (step3Heading) {
                        step3Heading.textContent = config.messages?.finalizeSuccess || step3Heading.textContent;
                    }
                    if (step3Description) {
                        step3Description.textContent = '';
                    }
                    if (finishButton && state.redirect) {
                        finishButton.removeAttribute('disabled');
                    }
                    renderSummary();
                })
                .catch(() => {
                    setMessage(propertyMessage, 'error', config.messages?.propertyError || config.messages?.genericError || '');
                });
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!state.agreementId) {
                return;
            }

            setMessage(searchMessage, '', '');

            const payload = new FormData(searchForm);
            payload.append('action', 'estate_office_create_search');
            payload.append('nonce', config.nonce);
            payload.append('agreement_id', String(state.agreementId));

            fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payload,
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
                .then(({ ok, body }) => {
                    if (!ok || !body?.success) {
                        setMessage(searchMessage, 'error', body?.data?.message || config.messages?.searchError || config.messages?.genericError || '');
                        return;
                    }

                    state.record = body.data?.record || null;
                    state.redirect = body.data?.redirect || state.redirect;
                    setMessage(searchMessage, 'success', config.messages?.searchSuccess || '');
                    if (searchForm) {
                        searchForm.setAttribute('hidden', 'hidden');
                    }
                    if (step3Heading) {
                        step3Heading.textContent = config.messages?.finalizeSuccess || step3Heading.textContent;
                    }
                    if (step3Description) {
                        step3Description.textContent = '';
                    }
                    if (finishButton && state.redirect) {
                        finishButton.removeAttribute('disabled');
                    }
                    renderSummary();
                })
                .catch(() => {
                    setMessage(searchMessage, 'error', config.messages?.searchError || config.messages?.genericError || '');
                });
        });
    }

    const detachRecord = () => {
        if (!state.agreementId || !state.record) {
            return;
        }

        const currentType = state.record.type === 'search' ? 'search' : 'property';
        const payload = new FormData();
        payload.append('action', 'estate_office_detach_record');
        payload.append('nonce', config.nonce);
        payload.append('agreement_id', String(state.agreementId));
        payload.append('record_id', String(state.record.id || 0));
        payload.append('record_type', currentType);

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                if (!ok || !body?.success) {
                    const targetMessage = currentType === 'search' ? searchMessage : propertyMessage;
                    setMessage(targetMessage, 'error', body?.data?.message || config.messages?.genericError || '');
                    return;
                }

                state.record = null;
                state.redirect = '';

                if (finishButton) {
                    finishButton.setAttribute('disabled', 'disabled');
                }

                prepareStepThree();

                const successText = currentType === 'search'
                    ? (config.messages?.searchDetached || '')
                    : (config.messages?.propertyDetached || '');

                const targetMessage = currentType === 'search' ? searchMessage : propertyMessage;
                if (successText) {
                    setMessage(targetMessage, 'success', successText);
                }
            })
            .catch(() => {
                const targetMessage = currentType === 'search' ? searchMessage : propertyMessage;
                setMessage(targetMessage, 'error', config.messages?.genericError || '');
            });
    };

    const fetchFinalizeRedirect = () => {
        const payload = new FormData();
        payload.append('action', 'estate_office_finalize_agreement');
        payload.append('nonce', config.nonce);
        payload.append('agreement_id', String(state.agreementId));

        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                if (!ok || !body?.success) {
                    throw new Error(body?.data?.message || config.messages?.genericError || '');
                }

                return body.data?.redirect || '';
            });
    };

    const prepareStepThree = () => {
        if (!state.record) {
            state.redirect = '';
            if (finishButton) {
                finishButton.setAttribute('disabled', 'disabled');
            }
        } else if (finishButton && state.redirect) {
            finishButton.removeAttribute('disabled');
        }
        setMessage(propertyMessage, '', '');
        setMessage(searchMessage, '', '');

        if (propertyForm) {
            propertyForm.reset();
            propertyForm.setAttribute('hidden', 'hidden');
        }

        if (searchForm) {
            searchForm.reset();
            searchForm.setAttribute('hidden', 'hidden');
        }

        if (propertyResults) {
            propertyResults.innerHTML = '';
        }

        if (searchResults) {
            searchResults.innerHTML = '';
        }

        if (propertySearchInput) {
            propertySearchInput.value = '';
        }

        if (searchSearchInput) {
            searchSearchInput.value = '';
        }

        renderSummary();

        const type = state.transactionType;

        if (propertyTransactions.includes(type) && propertyForm && !state.record) {
            propertyForm.removeAttribute('hidden');
            if (propertyTransactionInput) {
                propertyTransactionInput.value = type;
            }
            if (step3Heading) {
                step3Heading.textContent = config.step3?.propertyTitle || step3Heading.textContent;
            }
            if (step3Description) {
                step3Description.textContent = config.step3?.propertyDescription || '';
            }
            return;
        }

        if (searchTransactions.includes(type) && searchForm && !state.record) {
            searchForm.removeAttribute('hidden');
            if (searchTransactionInput) {
                searchTransactionInput.value = type;
            }
            if (step3Heading) {
                step3Heading.textContent = config.step3?.searchTitle || step3Heading.textContent;
            }
            if (step3Description) {
                step3Description.textContent = config.step3?.searchDescription || '';
            }
            return;
        }

        if (step3Heading) {
            step3Heading.textContent = config.messages?.finalizeSuccess || step3Heading.textContent;
        }
        if (step3Description) {
            step3Description.textContent = '';
        }

        if (!state.record) {
            fetchFinalizeRedirect()
                .then((redirect) => {
                    state.redirect = redirect;
                    renderSummary();
                    if (finishButton && state.redirect) {
                        finishButton.removeAttribute('disabled');
                    }
                })
                .catch((error) => {
                    setMessage(propertyMessage || searchMessage, 'error', error.message || config.messages?.genericError || '');
                });
        } else {
            renderSummary();
        }
    };

    if (summaryBox) {
        summaryBox.addEventListener('click', (event) => {
            const target = event.target;
            if (target && target.matches('[data-eo-agreement-clear-record]')) {
                event.preventDefault();
                detachRecord();
            }
        });
    }

    const goToStepThree = () => {
        const message = overlay.querySelector('[data-eo-agreement-step="2"] [data-eo-agreement-message]');
        setMessage(message, '', '');

        if (!state.clients.length) {
            setMessage(message, 'error', config.messages?.missingClients || '');
            return;
        }

        hideClientPrompt();
        switchStep(3);
        prepareStepThree();
    };

    if (clientPromptYes) {
        clientPromptYes.addEventListener('click', (event) => {
            event.preventDefault();
            hideClientPrompt();
            if (newClientDetails) {
                newClientDetails.setAttribute('open', 'open');
            }
            if (clientForm) {
                clientForm.reset();
                const focusTarget = clientForm.querySelector('input:not([type="hidden"]), select, textarea');
                if (focusTarget) {
                    focusTarget.focus();
                }
            }
        });
    }

    if (clientPromptNo) {
        clientPromptNo.addEventListener('click', (event) => {
            event.preventDefault();
            hideClientPrompt();
            goToStepThree();
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', goToStepThree);
    }

    if (backButton) {
        backButton.addEventListener('click', () => {
            switchStep(1);
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            switchStep(2);
        });
    }

    if (finishButton) {
        finishButton.addEventListener('click', () => {
            if (state.redirect) {
                window.location.href = state.redirect;
            } else if (propertyForm && !propertyForm.hasAttribute('hidden')) {
                setMessage(propertyMessage, 'error', config.messages?.propertyError || config.messages?.genericError || '');
            } else if (searchForm && !searchForm.hasAttribute('hidden')) {
                setMessage(searchMessage, 'error', config.messages?.searchError || config.messages?.genericError || '');
            }
        });
    }

    const indefiniteCheckbox = agreementForm?.querySelector('input[name="estate_agreement_is_indefinite"]');
    const endDateField = agreementForm?.querySelector('input[name="estate_agreement_end_date"]');
    if (indefiniteCheckbox && endDateField) {
        const toggleEndDate = () => {
            if (indefiniteCheckbox.checked) {
                endDateField.value = '';
                endDateField.setAttribute('disabled', 'disabled');
            } else {
                endDateField.removeAttribute('disabled');
            }
        };
        indefiniteCheckbox.addEventListener('change', toggleEndDate);
        toggleEndDate();
    }
})();

