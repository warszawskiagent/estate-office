(function () {
    'use strict';

    const filterTable = (table, query) => {
        if (!table) {
            return;
        }

        const rows = table.querySelectorAll('tbody tr');
        const needle = query.trim().toLowerCase();

        rows.forEach((row) => {
            if (needle.length === 0) {
                row.style.display = '';
                return;
            }

            const text = row.textContent || '';
            if (text.toLowerCase().indexOf(needle) !== -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    };

    document.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        const tableId = target.getAttribute('data-eo-crm-search');
        if (!tableId) {
            return;
        }

        const table = document.getElementById(tableId);
        filterTable(table, target.value);
    });

    const crmConfig = window.EstateOfficeCRM || {};
    const leadActions = window.EstateOfficeLeadActions || {};

    const showFormMessage = (element, type, text) => {
        if (!element) {
            return;
        }

        element.textContent = text;
        element.classList.remove('is-success', 'is-error');

        if (type === 'success') {
            element.classList.add('is-success');
        } else if (type === 'error') {
            element.classList.add('is-error');
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!leadActions.ajaxUrl) {
            return;
        }

        if (form.matches('[data-eo-agreement-stage-form]')) {
            if (!crmConfig.ajaxUrl) {
                return;
            }

            event.preventDefault();

            const messageElement = form.querySelector('[data-eo-agreement-stage-message]');
            showFormMessage(messageElement, '', '');

            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            form.classList.add('is-loading');

            const formData = new FormData(form);
            formData.append('action', 'estate_office_update_agreement_stage');

            if (!formData.has('nonce') && typeof crmConfig.stageNonce === 'string') {
                formData.append('nonce', crmConfig.stageNonce);
            }

            fetch(crmConfig.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, status: response.status, body: data })))
                .then(({ ok, body }) => {
                    if (ok && body && body.success) {
                        const successMessage = (crmConfig.stageMessages && crmConfig.stageMessages.success)
                            || (body.data && body.data.message)
                            || '';
                        showFormMessage(messageElement, 'success', successMessage);
                        setTimeout(() => {
                            window.location.reload();
                        }, 600);

                        return;
                    }

                    const errorMessage = (body && body.data && body.data.message)
                        || (crmConfig.stageMessages && crmConfig.stageMessages.error)
                        || '';
                    showFormMessage(messageElement, 'error', errorMessage);
                })
                .catch(() => {
                    const errorMessage = (crmConfig.stageMessages && crmConfig.stageMessages.error) || '';
                    showFormMessage(messageElement, 'error', errorMessage);
                })
                .finally(() => {
                    form.classList.remove('is-loading');
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });

            return;
        }

        if (form.matches('[data-eo-lead-status-form]')) {
            event.preventDefault();

            const messageElement = form.querySelector('[data-eo-lead-status-message]');
            showFormMessage(messageElement, '', '');

            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            form.classList.add('is-loading');

            const formData = new FormData(form);
            formData.append('action', 'estate_office_update_lead_status');

            if (!formData.has('nonce') && typeof leadActions.nonce === 'string') {
                formData.append('nonce', leadActions.nonce);
            }

            fetch(leadActions.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, status: response.status, body: data })))
                .then(({ ok, body }) => {
                    if (ok && body && body.success) {
                        const successMessage = (leadActions.messages && leadActions.messages.success)
                            || (body.data && body.data.message)
                            || '';
                        showFormMessage(messageElement, 'success', successMessage);
                        setTimeout(() => {
                            window.location.reload();
                        }, 600);

                        return;
                    }

                    const errorMessage = (body && body.data && body.data.message)
                        || (leadActions.messages && leadActions.messages.error)
                        || '';
                    showFormMessage(messageElement, 'error', errorMessage);
                })
                .catch(() => {
                    const errorMessage = (leadActions.messages && leadActions.messages.error) || '';
                    showFormMessage(messageElement, 'error', errorMessage);
                })
                .finally(() => {
                    form.classList.remove('is-loading');
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });

            return;
        }

        if (form.matches('[data-eo-lead-note-form]')) {
            event.preventDefault();

            const messageElement = form.querySelector('[data-eo-lead-note-message]');
            showFormMessage(messageElement, '', '');

            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            form.classList.add('is-loading');

            const formData = new FormData(form);
            formData.append('action', 'estate_office_add_lead_note');

            if (!formData.has('nonce') && typeof leadActions.noteNonce === 'string') {
                formData.append('nonce', leadActions.noteNonce);
            }

            fetch(leadActions.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, status: response.status, body: data })))
                .then(({ ok, body }) => {
                    if (ok && body && body.success) {
                        const successMessage = (leadActions.noteMessages && leadActions.noteMessages.success)
                            || (body.data && body.data.message)
                            || '';
                        showFormMessage(messageElement, 'success', successMessage);
                        setTimeout(() => {
                            window.location.reload();
                        }, 600);

                        return;
                    }

                    const errorMessage = (body && body.data && body.data.message)
                        || (leadActions.noteMessages && leadActions.noteMessages.error)
                        || '';
                    showFormMessage(messageElement, 'error', errorMessage);
                })
                .catch(() => {
                    const errorMessage = (leadActions.noteMessages && leadActions.noteMessages.error) || '';
                    showFormMessage(messageElement, 'error', errorMessage);
                })
                .finally(() => {
                    form.classList.remove('is-loading');
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
        }
    });
})();
