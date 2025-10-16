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
    const nextButton = overlay.querySelector('[data-eo-agreement-next]');
    const backButton = overlay.querySelector('[data-eo-agreement-back]');
    const finishButton = overlay.querySelector('[data-eo-agreement-finish]');
    const summaryBox = overlay.querySelector('[data-eo-agreement-summary]');

    const state = {
        agreementId: 0,
        clients: [],
        redirect: '',
    };

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
        if (agreementForm) {
            agreementForm.reset();
        }
        if (clientForm) {
            clientForm.reset();
        }
        renderClients();
        renderSummary();
        if (clientResults) {
            clientResults.innerHTML = '';
        }
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

        if (!state.agreementId) {
            summaryBox.innerHTML = '';
            return;
        }

        const clientsCount = state.clients.length;
        let summaryHtml = '';
        summaryHtml += '<p><strong>' + clientsCount + '</strong> ' + (clientsCount === 1 ? 'klient przypisany do umowy.' : 'klientów przypisanych do umowy.') + '</p>';
        if (state.redirect) {
            const label = config.messages?.viewAgreement || 'Przejdź do szczegółów';
            summaryHtml += '<p><a class="estate-office-agreement-creator__primary" href="' + state.redirect + '">' + label + '</a></p>';
        }
        summaryBox.innerHTML = summaryHtml;
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
                })
                .catch(() => {
                    setMessage(message, 'error', config.messages?.genericError || '');
                });
        });
    }

    const finalizeAgreement = () => {
        const message = overlay.querySelector('[data-eo-agreement-step="2"] [data-eo-agreement-message]');
        setMessage(message, '', '');

        if (!state.clients.length) {
            setMessage(message, 'error', config.messages?.missingClients || '');
            return;
        }

        const payload = new FormData();
        payload.append('action', 'estate_office_finalize_agreement');
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
                    setMessage(message, 'error', body?.data?.message || config.messages?.genericError || '');
                    return;
                }

                state.redirect = body.data?.redirect || '';
                setMessage(message, 'success', config.messages?.finalizeSuccess || '');
                switchStep(3);
                renderSummary();
            })
            .catch(() => {
                setMessage(message, 'error', config.messages?.genericError || '');
            });
    };

    if (nextButton) {
        nextButton.addEventListener('click', finalizeAgreement);
    }

    if (backButton) {
        backButton.addEventListener('click', () => {
            switchStep(1);
        });
    }

    if (finishButton) {
        finishButton.addEventListener('click', () => {
            if (state.redirect) {
                window.location.href = state.redirect;
            } else {
                closeOverlay();
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

