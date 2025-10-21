(function () {
    const { wp } = window;

    if (window.EstateOfficeSettings && window.EstateOfficeSettings.noMedia) {
        window.estateOfficeNoMedia = window.EstateOfficeSettings.noMedia;
    }

    if (!wp || !wp.media) {
        return;
    }

    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('.estate-office-dynamic-add');
        if (addButton) {
            event.preventDefault();
            addDynamicFieldRow(addButton.closest('.estate-office-dynamic-fields'));
        }

        const removeButton = event.target.closest('.estate-office-dynamic-remove');
        if (removeButton) {
            event.preventDefault();
            const row = removeButton.closest('.estate-office-dynamic-row');
            if (row) {
                row.remove();
            }
        }

        const mediaUpload = event.target.closest('.estate-office-media-upload');
        if (mediaUpload) {
            event.preventDefault();
            handleMediaUpload(mediaUpload);
        }

        const mediaRemove = event.target.closest('.estate-office-media-remove');
        if (mediaRemove) {
            event.preventDefault();
            handleMediaRemove(mediaRemove);
        }
    });

    function addDynamicFieldRow(container) {
        if (!container) {
            return;
        }

        const template = container.querySelector('.estate-office-dynamic-template');
        const target = container.querySelector('.estate-office-dynamic-container');
        const name = container.dataset.name;

        if (!template || !target || !name) {
            return;
        }

        const index = target.querySelectorAll('.estate-office-dynamic-row').length;
        const html = template.innerHTML
            .replace(/__NAME__/g, name)
            .replace(/__INDEX__/g, index.toString());

        target.insertAdjacentHTML('beforeend', html);
    }

    function handleMediaUpload(button) {
        const container = button.closest('.estate-office-media-field');
        if (!container) {
            return;
        }

        const fileFrame = wp.media({
            title: button.dataset.label,
            button: { text: button.dataset.label },
            multiple: false,
        });

        fileFrame.on('select', () => {
            const attachment = fileFrame.state().get('selection').first().toJSON();
            const preview = container.querySelector('.estate-office-media-preview');
            const input = container.querySelector('input[type="hidden"]');

            const imageUrl = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) ? attachment.sizes.medium.url : attachment.url;

            if (preview) {
                preview.innerHTML = `<img src="${imageUrl}" alt="" />`;
            }

            if (input) {
                input.value = attachment.id;
            }
        });

        fileFrame.open();
    }

    function handleMediaRemove(button) {
        const container = button.closest('.estate-office-media-field');
        if (!container) {
            return;
        }

        const preview = container.querySelector('.estate-office-media-preview');
        const input = container.querySelector('input[type="hidden"]');

        if (preview) {
            preview.innerHTML = '<span class="description">' + (window.estateOfficeNoMedia || 'Brak wybranego pliku') + '</span>';
        }

        if (input) {
            input.value = '';
        }
    }
})();
