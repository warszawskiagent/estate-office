(function () {
    'use strict';

    const config = window.EstateOfficeQuickCreate || {};
    const overlay = document.querySelector('[data-eo-quick-create]');

    if (!overlay || !config.ajaxUrl || !config.nonce || !config.types) {
        return;
    }

    const openButtons = document.querySelectorAll('[data-eo-quick-open]');
    const closeButtons = overlay.querySelectorAll('[data-eo-quick-close]');
    const backdrop = overlay.querySelector('.estate-office-quick-create__backdrop');
    const tabs = Array.from(overlay.querySelectorAll('[data-eo-quick-tab]'));
    const panels = Array.from(overlay.querySelectorAll('[data-eo-quick-panel]'));
    const forms = Array.from(overlay.querySelectorAll('form[data-eo-quick-form]'));

    let activeType = null;

    const setActive = (type) => {
        activeType = type;
        tabs.forEach((tab) => {
            const isActive = tab.dataset.eoQuickTab === type;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', String(isActive));
        });

        panels.forEach((panel) => {
            panel.hidden = panel.dataset.eoQuickPanel !== type;
        });

        const activePanel = panels.find((panel) => panel.dataset.eoQuickPanel === type);
        if (activePanel) {
            const firstField = activePanel.querySelector('input, select, textarea');
            if (firstField) {
                setTimeout(() => firstField.focus(), 100);
            }
        }
    };

    const openOverlay = (type) => {
        if (!config.types[type]) {
            return;
        }

        overlay.hidden = false;
        document.body.classList.add('estate-office-quick-create-open');
        setActive(type);
    };

    const closeOverlay = () => {
        overlay.hidden = true;
        document.body.classList.remove('estate-office-quick-create-open');
        const messages = overlay.querySelectorAll('[data-eo-quick-message]');
        messages.forEach((message) => {
            message.classList.remove('is-error', 'is-success');
            message.textContent = '';
        });
        forms.forEach((form) => {
            form.reset();
        });
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const type = button.dataset.eoQuickOpen;
            openOverlay(type);
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            closeOverlay();
        });
    });

    overlay.addEventListener('click', (event) => {
        if (backdrop && event.target === backdrop) {
            closeOverlay();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !overlay.hidden) {
            closeOverlay();
        }
    });

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const type = tab.dataset.eoQuickTab;
            if (type) {
                setActive(type);
            }
        });
    });

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const type = form.dataset.eoQuickForm;
            const submit = form.querySelector('button[type="submit"]');
            const message = form.querySelector('[data-eo-quick-message]');
            const formData = new FormData(form);

            if (!type || !config.types[type]) {
                return;
            }

            formData.set('nonce', config.nonce);

            if (submit) {
                submit.disabled = true;
            }

            if (message) {
                message.classList.remove('is-error', 'is-success');
                message.textContent = '';
            }

            fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (submit) {
                        submit.disabled = false;
                    }

                    if (!message) {
                        if (data && data.success && data.data && data.data.redirect) {
                            window.location.href = data.data.redirect;
                        }
                        return;
                    }

                    if (!data || !data.success) {
                        message.classList.add('is-error');
                        message.textContent = (data && data.data && data.data.message) || config.messages.error;
                        return;
                    }

                    message.classList.add('is-success');
                    message.textContent = config.messages.success;

                    if (data.data && data.data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.data.redirect;
                        }, 800);
                    } else {
                        setTimeout(() => {
                            closeOverlay();
                        }, 800);
                    }
                })
                .catch(() => {
                    if (submit) {
                        submit.disabled = false;
                    }
                    if (message) {
                        message.classList.add('is-error');
                        message.textContent = config.messages.error;
                    }
                });
        });
    });
})();
