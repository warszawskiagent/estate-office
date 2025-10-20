(function (window, document) {
    'use strict';

    const settings = window.EstateOfficePropertyMap || {};
    const DEFAULT_POSITION = { lat: 52.2296756, lng: 21.0122287 };
    const DEFAULT_ZOOM = 12;
    const ACTIVE_ZOOM = 16;

    const i18n = Object.assign(
        {
            noApiKey: '',
            markerTitle: '',
            searchPlaceholder: '',
            applyLocation: '',
            cleared: '',
            geocodeError: '',
        },
        settings.i18n || {}
    );

    const mediaStrings = Object.assign(
        {
            galleryTitle: '',
            galleryButton: '',
            singleTitle: '',
            singleButton: '',
            remove: '',
            emptyGallery: '',
            dragHint: '',
            downloadsTitle: '',
            downloadsButton: '',
            downloadsEmpty: '',
            primaryLabel: '',
            captionLabel: '',
            captionPlaceholder: '',
        },
        settings.media || {}
    );

    function stripTags(value) {
        if (!value) {
            return '';
        }

        const tmp = document.createElement('div');
        tmp.innerHTML = value;
        return (tmp.textContent || tmp.innerText || '').trim();
    }

    function resolveDefaultCaption(attachment) {
        if (!attachment) {
            return '';
        }

        if (attachment.caption) {
            if (typeof attachment.caption === 'string') {
                return stripTags(attachment.caption);
            }

            if (typeof attachment.caption === 'object') {
                if (attachment.caption.raw) {
                    return stripTags(attachment.caption.raw);
                }

                if (attachment.caption.rendered) {
                    return stripTags(attachment.caption.rendered);
                }
            }
        }

        if (attachment.title) {
            if (typeof attachment.title === 'string') {
                return stripTags(attachment.title);
            }

            if (typeof attachment.title === 'object' && attachment.title.rendered) {
                return stripTags(attachment.title.rendered);
            }
        }

        return '';
    }

    function updateStatus(wrapper, message) {
        const status = wrapper.querySelector('.estate-office-map-status');
        if (!status) {
            return;
        }
        status.textContent = message || '';
    }

    function getEmptyLabel(wrapper) {
        return wrapper.dataset.emptyLabel || '';
    }

    function updateAddress(wrapper, text) {
        const label = wrapper.querySelector('.estate-office-map-address');
        if (!label) {
            return;
        }
        label.textContent = text || getEmptyLabel(wrapper);
    }

    function toggleClearButton(wrapper, disabled) {
        const button = wrapper.querySelector('.estate-office-map-clear');
        if (!button) {
            return;
        }
        if (disabled) {
            button.setAttribute('disabled', 'disabled');
        } else {
            button.removeAttribute('disabled');
        }
    }

    function setInputs(latInput, lngInput, addressInput, placeInput, latLng, address, placeId) {
        if (latInput) {
            latInput.value = latLng ? latLng.lat.toFixed(8) : '';
        }
        if (lngInput) {
            lngInput.value = latLng ? latLng.lng.toFixed(8) : '';
        }
        if (addressInput) {
            addressInput.value = address || '';
        }
        if (placeInput) {
            placeInput.value = placeId || '';
        }
    }

    function geocodePosition(geocoder, latLng) {
        return geocoder
            .geocode({ location: latLng })
            .then(function (result) {
                if (!result || !Array.isArray(result.results) || result.results.length === 0) {
                    return '';
                }
                return result.results[0].formatted_address || '';
            })
            .catch(function () {
                return '';
            });
    }

    function initMap(wrapper) {
        if (!window.google || !window.google.maps) {
            updateStatus(wrapper, i18n.geocodeError);
            return;
        }

        const latInput = document.getElementById('estate_property_latitude');
        const lngInput = document.getElementById('estate_property_longitude');
        const addressInput = document.getElementById('estate_property_map_address');
        const placeInput = document.getElementById('estate_property_map_place_id');
        const canvas = wrapper.querySelector('.estate-office-map-canvas');
        const search = wrapper.querySelector('.estate-office-map-search');

        if (!canvas || !latInput || !lngInput || !addressInput || !placeInput) {
            return;
        }

        if (search && i18n.searchPlaceholder) {
            search.placeholder = i18n.searchPlaceholder;
        }

        const storedLat = parseFloat(latInput.value);
        const storedLng = parseFloat(lngInput.value);
        const hasStored = Number.isFinite(storedLat) && Number.isFinite(storedLng);

        const initialPosition = hasStored
            ? { lat: storedLat, lng: storedLng }
            : DEFAULT_POSITION;

        const map = new window.google.maps.Map(canvas, {
            center: initialPosition,
            zoom: hasStored ? ACTIVE_ZOOM : DEFAULT_ZOOM,
            mapTypeControl: false,
            fullscreenControl: true,
            streetViewControl: false,
        });

        const marker = new window.google.maps.Marker({
            map: map,
            position: initialPosition,
            draggable: true,
            visible: hasStored,
            title: i18n.markerTitle,
        });

        const geocoder = new window.google.maps.Geocoder();

        function applyLocation(latLng, address, placeId) {
            marker.setPosition(latLng);
            marker.setVisible(true);
            map.panTo(latLng);
            map.setZoom(ACTIVE_ZOOM);
            setInputs(latInput, lngInput, addressInput, placeInput, latLng, address, placeId);
            updateAddress(wrapper, address || '');
            toggleClearButton(wrapper, false);
            updateStatus(wrapper, i18n.applyLocation);
        }

        if (hasStored) {
            updateAddress(wrapper, addressInput.value || '');
            toggleClearButton(wrapper, false);
        } else {
            updateAddress(wrapper, addressInput.value || '');
            toggleClearButton(wrapper, true);
        }

        if (search) {
            const autocomplete = new window.google.maps.places.Autocomplete(search, {
                fields: ['geometry', 'formatted_address', 'place_id'],
                types: ['geocode'],
            });

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place || !place.geometry || !place.geometry.location) {
                    return;
                }

                const location = {
                    lat: place.geometry.location.lat(),
                    lng: place.geometry.location.lng(),
                };

                applyLocation(location, place.formatted_address || '', place.place_id || '');
            });
        }

        map.addListener('click', function (event) {
            const latLng = event.latLng;
            if (!latLng) {
                return;
            }

            const coords = { lat: latLng.lat(), lng: latLng.lng() };

            geocodePosition(geocoder, coords).then(function (address) {
                if (!address) {
                    updateStatus(wrapper, i18n.geocodeError);
                }
                applyLocation(coords, address || addressInput.value || '', '');
            });
        });

        marker.addListener('dragend', function (event) {
            const latLng = event.latLng;
            if (!latLng) {
                return;
            }

            const coords = { lat: latLng.lat(), lng: latLng.lng() };

            geocodePosition(geocoder, coords).then(function (address) {
                if (!address) {
                    updateStatus(wrapper, i18n.geocodeError);
                }
                applyLocation(coords, address || addressInput.value || '', placeInput.value || '');
            });
        });

        const clearButton = wrapper.querySelector('.estate-office-map-clear');
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                marker.setVisible(false);
                map.panTo(DEFAULT_POSITION);
                map.setZoom(DEFAULT_ZOOM);
                setInputs(latInput, lngInput, addressInput, placeInput, null, '', '');
                updateAddress(wrapper, '');
                toggleClearButton(wrapper, true);
                updateStatus(wrapper, i18n.cleared);
            });
        }
    }

    function getAttachmentPreview(attachment) {
        if (!attachment) {
            return '';
        }

        if (attachment.sizes) {
            if (attachment.sizes.medium && attachment.sizes.medium.url) {
                return attachment.sizes.medium.url;
            }

            if (attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
                return attachment.sizes.thumbnail.url;
            }
        }

        return attachment.url || attachment.icon || '';
    }

    function updateGalleryEmptyState(wrapper) {
        const empty = wrapper.querySelector('.estate-office-gallery-empty');
        const list = wrapper.querySelector('.estate-office-gallery-list');

        if (!empty || !list) {
            return;
        }

        if (list.children.length === 0) {
            empty.classList.remove('hidden');
            empty.removeAttribute('hidden');
        } else {
            empty.classList.add('hidden');
            empty.setAttribute('hidden', 'hidden');
        }
    }

    function refreshPrimaryStyles(wrapper) {
        const items = wrapper.querySelectorAll('.estate-office-gallery-item');

        items.forEach(function (item) {
            const radio = item.querySelector('.estate-office-gallery-primary input[type="radio"]');

            if (radio && radio.checked) {
                item.classList.add('is-primary');
            } else {
                item.classList.remove('is-primary');
            }
        });
    }

    function ensurePrimarySelection(wrapper) {
        const radios = wrapper.querySelectorAll('.estate-office-gallery-primary input[type="radio"]');

        if (radios.length === 0) {
            return;
        }

        const hasChecked = Array.from(radios).some(function (radio) {
            return radio.checked;
        });

        if (!hasChecked) {
            radios[0].checked = true;
        }

        refreshPrimaryStyles(wrapper);
    }

    function bindPrimaryRadio(radio, wrapper) {
        if (!radio) {
            return;
        }

        radio.addEventListener('change', function () {
            if (radio.checked) {
                refreshPrimaryStyles(wrapper);
            }
        });
    }

    function getDragAfterElement(list, y) {
        const items = Array.from(list.querySelectorAll('.estate-office-gallery-item:not(.is-dragging)'));

        return items.reduce(
            function (closest, child) {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;

                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                }

                return closest;
            },
            { offset: Number.NEGATIVE_INFINITY, element: null }
        ).element;
    }

    function enableDragForItem(item, list) {
        if (!item || !list) {
            return;
        }

        item.addEventListener('dragstart', function (event) {
            item.classList.add('is-dragging');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.dataset.id || '');
            }
        });

        item.addEventListener('dragend', function () {
            item.classList.remove('is-dragging');
        });
    }

    function addGalleryAttachment(wrapper, attachment) {
        const list = wrapper.querySelector('.estate-office-gallery-list');

        if (!list || !attachment || !attachment.id) {
            return;
        }

        const id = attachment.id;
        if (list.querySelector('[data-id="' + id + '"]')) {
            return;
        }

        const primaryInputName = wrapper.dataset.primaryInput || 'estate_property_gallery_primary';
        const captionInputName = wrapper.dataset.captionInput || 'estate_property_gallery_captions';
        const primaryLabelText = wrapper.dataset.primaryLabel || mediaStrings.primaryLabel || '';
        const captionLabelText = wrapper.dataset.captionLabel || mediaStrings.captionLabel || '';
        const captionPlaceholder = wrapper.dataset.captionPlaceholder || mediaStrings.captionPlaceholder || '';

        const item = document.createElement('li');
        item.className = 'estate-office-gallery-item';
        item.dataset.id = id;
        item.setAttribute('draggable', 'true');

        const thumb = document.createElement('div');
        thumb.className = 'estate-office-gallery-thumb';
        const previewUrl = getAttachmentPreview(attachment);

        if (previewUrl) {
            const img = document.createElement('img');
            img.src = previewUrl;
            img.alt = '';
            thumb.appendChild(img);
        } else {
            thumb.classList.add('is-placeholder');
            const placeholder = document.createElement('span');
            placeholder.textContent = mediaStrings.emptyGallery || '';
            thumb.appendChild(placeholder);
        }

        item.appendChild(thumb);

        const meta = document.createElement('div');
        meta.className = 'estate-office-gallery-meta';

        const primaryLabel = document.createElement('label');
        primaryLabel.className = 'estate-office-gallery-primary';
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = primaryInputName;
        radio.value = id;
        primaryLabel.appendChild(radio);

        if (primaryLabelText) {
            const radioText = document.createElement('span');
            radioText.textContent = primaryLabelText;
            primaryLabel.appendChild(radioText);
        }

        meta.appendChild(primaryLabel);

        const captionWrapper = document.createElement('label');
        captionWrapper.className = 'estate-office-gallery-caption';

        if (captionLabelText) {
            const captionLabel = document.createElement('span');
            captionLabel.textContent = captionLabelText;
            captionWrapper.appendChild(captionLabel);
        }

        const captionInput = document.createElement('input');
        captionInput.type = 'text';
        captionInput.name = captionInputName + '[' + id + ']';
        captionInput.className = 'regular-text';
        captionInput.setAttribute('maxlength', '200');
        captionInput.setAttribute('autocomplete', 'off');

        if (captionPlaceholder) {
            captionInput.setAttribute('placeholder', captionPlaceholder);
        }

        const defaultCaption = resolveDefaultCaption(attachment);
        if (defaultCaption) {
            captionInput.value = defaultCaption.substring(0, 200);
        }

        captionWrapper.appendChild(captionInput);
        meta.appendChild(captionWrapper);

        item.appendChild(meta);

        const actions = document.createElement('div');
        actions.className = 'estate-office-gallery-actions';
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'button-link estate-office-gallery-remove';
        removeButton.textContent = mediaStrings.remove || '';
        removeButton.addEventListener('click', function () {
            item.remove();
            updateGalleryEmptyState(wrapper);
            ensurePrimarySelection(wrapper);
        });
        actions.appendChild(removeButton);
        item.appendChild(actions);

        const input = document.createElement('input');
        input.type = 'hidden';
        const inputName = wrapper.dataset.input ? wrapper.dataset.input + '[]' : 'estate_property_gallery[]';
        input.name = inputName;
        input.value = id;
        item.appendChild(input);

        list.appendChild(item);
        enableDragForItem(item, list);
        bindPrimaryRadio(radio, wrapper);
        updateGalleryEmptyState(wrapper);
        ensurePrimarySelection(wrapper);
    }

    function initGallery(wrapper) {
        const list = wrapper.querySelector('.estate-office-gallery-list');
        const addButton = wrapper.querySelector('.estate-office-gallery-add');

        if (!list || !addButton || !window.wp || !wp.media) {
            return;
        }

        const reorderHint = wrapper.querySelector('.description.reorder');
        if (reorderHint && mediaStrings.dragHint) {
            reorderHint.textContent = mediaStrings.dragHint;
        }

        Array.from(list.querySelectorAll('.estate-office-gallery-item')).forEach(function (item) {
            enableDragForItem(item, list);
            const removeButton = item.querySelector('.estate-office-gallery-remove');
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    item.remove();
                    updateGalleryEmptyState(wrapper);
                    ensurePrimarySelection(wrapper);
                });
            }

            const radio = item.querySelector('.estate-office-gallery-primary input[type="radio"]');
            bindPrimaryRadio(radio, wrapper);
        });

        list.addEventListener('dragover', function (event) {
            event.preventDefault();
            const dragging = list.querySelector('.estate-office-gallery-item.is-dragging');
            if (!dragging) {
                return;
            }

            const afterElement = getDragAfterElement(list, event.clientY);

            if (!afterElement) {
                list.appendChild(dragging);
            } else {
                list.insertBefore(dragging, afterElement);
            }
        });

        let frame;

        addButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (!frame) {
                frame = wp.media({
                    title: wrapper.dataset.frameTitle || mediaStrings.galleryTitle || '',
                    button: {
                        text: wrapper.dataset.frameButton || mediaStrings.galleryButton || '',
                    },
                    multiple: true,
                    library: {
                        type: 'image',
                    },
                });

                frame.on('select', function () {
                    const selection = frame.state().get('selection');
                    if (!selection) {
                        return;
                    }

                    selection.each(function (model) {
                        addGalleryAttachment(wrapper, model.toJSON());
                    });
                });
            }

            frame.open();
        });

        updateGalleryEmptyState(wrapper);
        ensurePrimarySelection(wrapper);
    }

    function updateDownloadsEmptyState(wrapper) {
        const empty = wrapper.querySelector('.estate-office-downloads-empty');
        const list = wrapper.querySelector('.estate-office-downloads-list');

        if (!empty || !list) {
            return;
        }

        if (list.children.length === 0) {
            empty.classList.remove('hidden');
            empty.removeAttribute('hidden');
        } else {
            empty.classList.add('hidden');
            empty.setAttribute('hidden', 'hidden');
        }
    }

    function addDownloadAttachment(wrapper, attachment) {
        const list = wrapper.querySelector('.estate-office-downloads-list');

        if (!list || !attachment || !attachment.id) {
            return;
        }

        const id = attachment.id;

        if (list.querySelector('[data-id="' + id + '"]')) {
            return;
        }

        const item = document.createElement('li');
        item.className = 'estate-office-downloads-item';
        item.dataset.id = id;

        const icon = document.createElement('span');
        icon.className = 'estate-office-downloads-icon dashicons dashicons-media-default';
        icon.setAttribute('aria-hidden', 'true');
        item.appendChild(icon);

        const title = document.createElement('span');
        title.className = 'estate-office-downloads-title';
        title.textContent = attachment.title || attachment.filename || '#' + id;
        item.appendChild(title);

        if (attachment.filename) {
            const meta = document.createElement('span');
            meta.className = 'estate-office-downloads-meta';
            meta.textContent = attachment.filename;
            item.appendChild(meta);
        }

        const actions = document.createElement('div');
        actions.className = 'estate-office-downloads-actions';
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'button-link estate-office-downloads-remove';
        removeButton.textContent = wrapper.dataset.removeLabel || mediaStrings.remove || '';
        removeButton.addEventListener('click', function () {
            item.remove();
            updateDownloadsEmptyState(wrapper);
        });
        actions.appendChild(removeButton);
        item.appendChild(actions);

        const input = document.createElement('input');
        input.type = 'hidden';
        const inputName = wrapper.dataset.input ? wrapper.dataset.input + '[]' : 'estate_property_materials[]';
        input.name = inputName;
        input.value = id;
        item.appendChild(input);

        list.appendChild(item);
        updateDownloadsEmptyState(wrapper);
    }

    function initDownloads(wrapper) {
        const list = wrapper.querySelector('.estate-office-downloads-list');
        const addButton = wrapper.querySelector('.estate-office-downloads-add');

        if (!list || !addButton || !window.wp || !wp.media) {
            return;
        }

        Array.from(list.querySelectorAll('.estate-office-downloads-remove')).forEach(function (button) {
            button.addEventListener('click', function () {
                const item = button.closest('.estate-office-downloads-item');
                if (item) {
                    item.remove();
                    updateDownloadsEmptyState(wrapper);
                }
            });
        });

        let frame;

        addButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (!frame) {
                frame = wp.media({
                    title: wrapper.dataset.frameTitle || mediaStrings.downloadsTitle || '',
                    button: {
                        text: wrapper.dataset.frameButton || mediaStrings.downloadsButton || '',
                    },
                    multiple: true,
                });

                frame.on('select', function () {
                    const selection = frame.state().get('selection');
                    if (!selection) {
                        return;
                    }

                    selection.each(function (model) {
                        addDownloadAttachment(wrapper, model.toJSON());
                    });
                });
            }

            frame.open();
        });

        updateDownloadsEmptyState(wrapper);
    }

    function renderPlaceholder(preview, text) {
        if (!preview) {
            return;
        }

        preview.innerHTML = '';
        const span = document.createElement('span');
        span.className = 'placeholder';
        span.textContent = text || '';
        preview.appendChild(span);
    }

    function initSingleMedia(wrapper) {
        const input = wrapper.querySelector('input[type="hidden"]');
        const addButton = wrapper.querySelector('.estate-office-single-add');
        const removeButton = wrapper.querySelector('.estate-office-single-remove');
        const preview = wrapper.querySelector('.estate-office-single-preview');
        const placeholderText = wrapper.dataset.placeholder || mediaStrings.emptyGallery || '';

        if (removeButton) {
            removeButton.addEventListener('click', function (event) {
                event.preventDefault();
                if (input) {
                    input.value = '';
                }
                renderPlaceholder(preview, placeholderText);
                removeButton.setAttribute('disabled', 'disabled');
            });
        }

        if (!addButton || !window.wp || !wp.media) {
            if (!input || !input.value) {
                renderPlaceholder(preview, placeholderText);
            }
            return;
        }

        let frame;

        addButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (!frame) {
                frame = wp.media({
                    title: wrapper.dataset.frameTitle || mediaStrings.singleTitle || '',
                    button: {
                        text: wrapper.dataset.frameButton || mediaStrings.singleButton || '',
                    },
                    multiple: false,
                    library: {
                        type: 'image',
                    },
                });

                frame.on('select', function () {
                    const selection = frame.state().get('selection');
                    if (!selection) {
                        return;
                    }

                    const attachment = selection.first();
                    if (!attachment) {
                        return;
                    }

                    const data = attachment.toJSON();

                    if (input) {
                        input.value = data.id || '';
                    }

                    if (preview) {
                        const url = getAttachmentPreview(data);
                        if (url) {
                            preview.innerHTML = '';
                            const img = document.createElement('img');
                            img.src = url;
                            img.alt = '';
                            preview.appendChild(img);
                        } else {
                            renderPlaceholder(preview, placeholderText);
                        }
                    }

                    if (removeButton) {
                        removeButton.removeAttribute('disabled');
                    }
                });
            }

            frame.open();
        });

        if (!input || !input.value) {
            renderPlaceholder(preview, placeholderText);
        }
    }

    function initMapField() {
        const wrapper = document.querySelector('.estate-office-map-field');
        if (!wrapper) {
            return;
        }

        if (!settings.apiKey) {
            wrapper.classList.add('is-disabled');
            updateStatus(wrapper, i18n.noApiKey);
            const search = wrapper.querySelector('.estate-office-map-search');
            if (search) {
                search.setAttribute('disabled', 'disabled');
            }
            return;
        }

        wrapper.classList.remove('is-disabled');
        initMap(wrapper);
    }

    function initGalleries() {
        const wrappers = document.querySelectorAll('.estate-office-gallery');
        if (!wrappers.length) {
            return;
        }

        wrappers.forEach(function (wrapper) {
            initGallery(wrapper);
        });
    }

    function initDownloadFields() {
        const wrappers = document.querySelectorAll('.estate-office-downloads');
        if (!wrappers.length) {
            return;
        }

        wrappers.forEach(function (wrapper) {
            initDownloads(wrapper);
        });
    }

    function initSingleMediaFields() {
        const wrappers = document.querySelectorAll('.estate-office-single-media');
        if (!wrappers.length) {
            return;
        }

        wrappers.forEach(function (wrapper) {
            initSingleMedia(wrapper);
        });
    }

    function setToggleState(target, visible) {
        if (!target) {
            return;
        }

        if (visible) {
            target.classList.add('is-visible');
            target.removeAttribute('hidden');
        } else {
            target.classList.remove('is-visible');
            target.setAttribute('hidden', 'hidden');
        }

        const fields = target.querySelectorAll('input, select, textarea');
        fields.forEach(function (field) {
            if (visible) {
                if (field.dataset.eoToggleDisabled === '1') {
                    field.removeAttribute('disabled');
                    delete field.dataset.eoToggleDisabled;
                }
            } else if (!field.disabled) {
                field.dataset.eoToggleDisabled = '1';
                field.setAttribute('disabled', 'disabled');
            }
        });
    }

    function initToggleFields() {
        const toggles = document.querySelectorAll('[data-eo-toggle]');
        if (!toggles.length) {
            return;
        }

        toggles.forEach(function (toggle) {
            const selector = toggle.getAttribute('data-eo-toggle');
            if (!selector) {
                return;
            }

            const target = document.querySelector(selector);
            if (!target) {
                return;
            }

            const update = function () {
                const visible = toggle.type === 'checkbox' ? toggle.checked : !toggle.hasAttribute('disabled');
                setToggleState(target, visible);
            };

            update();
            toggle.addEventListener('change', update);
        });
    }

    function initLandRegisterToggle() {
        const checkbox = document.getElementById('estate_property_no_land_register');
        const input = document.getElementById('estate_property_land_register_number');

        if (!checkbox || !input) {
            return;
        }

        const update = function () {
            if (checkbox.checked) {
                input.value = '';
                input.setAttribute('disabled', 'disabled');
            } else {
                input.removeAttribute('disabled');
            }
        };

        update();
        checkbox.addEventListener('change', update);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initMapField();
        initGalleries();
        initDownloadFields();
        initSingleMediaFields();
        initToggleFields();
        initLandRegisterToggle();
    });
})(window, document);
