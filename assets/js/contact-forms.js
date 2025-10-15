(function () {
    const settings = window.estateOfficeContactForms || {};
    const ajaxUrl = settings.ajaxUrl || (window.ajaxurl || '');
    const generalError = settings.error || 'Wystąpił błąd. Spróbuj ponownie.';

    function setMessage(form, message, state) {
        const notice = form.querySelector('[data-estate-office-contact-message]');
        if (notice) {
            notice.textContent = message || '';
        }

        form.classList.remove('is-success', 'is-error');
        if (state) {
            form.classList.add(state);
        }
    }

    function setLoading(form, isLoading) {
        if (isLoading) {
            form.classList.add('is-loading');
        } else {
            form.classList.remove('is-loading');
        }

        const submit = form.querySelector('button[type="submit"]');
        if (submit) {
            submit.disabled = Boolean(isLoading);
            if (isLoading && settings.processing) {
                submit.dataset.originalLabel = submit.dataset.originalLabel || submit.textContent;
                submit.textContent = settings.processing;
            } else if (submit.dataset.originalLabel) {
                submit.textContent = submit.dataset.originalLabel;
            }
        }
    }

    function handleSubmit(event) {
        const form = event.target;
        if (!form.matches('[data-estate-office-contact-form]')) {
            return;
        }

        event.preventDefault();
        if (form.dataset.pending === '1') {
            return;
        }

        form.dataset.pending = '1';
        setLoading(form, true);
        setMessage(form, '', '');

        const formData = new FormData(form);
        if (!formData.get('action')) {
            formData.append('action', 'estate_office_contact');
        }

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                return response.json();
            })
            .then((payload) => {
                if (payload && payload.success) {
                    form.reset();
                    setMessage(form, form.dataset.success || '', 'is-success');
                } else {
                    const message = payload && payload.data && payload.data.message ? payload.data.message : generalError;
                    setMessage(form, message, 'is-error');
                }
            })
            .catch(() => {
                setMessage(form, generalError, 'is-error');
            })
            .finally(() => {
                setLoading(form, false);
                form.dataset.pending = '0';
            });
    }

    document.addEventListener('submit', handleSubmit);
})();
