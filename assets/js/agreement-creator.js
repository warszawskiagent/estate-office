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
    const clientTypeSelect = clientForm?.querySelector('[data-eo-client-type]');
    const clientScopeElements = clientForm ? Array.from(clientForm.querySelectorAll('[data-eo-client-scope]')) : [];
    const clientRequiredElements = clientForm ? Array.from(clientForm.querySelectorAll('[data-eo-client-required-for]')) : [];
    const correspondenceToggle = clientForm?.querySelector('[data-eo-client-correspondence-toggle]');
    const correspondenceSections = clientForm ? Array.from(clientForm.querySelectorAll('[data-eo-client-correspondence-fields]')) : [];
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
    const propertyTypeSelect = propertyForm?.querySelector('[data-eo-property-type]');
    const propertyScopedElements = propertyForm ? Array.from(propertyForm.querySelectorAll('[data-eo-property-scope]')) : [];
    const propertyConditionalElements = propertyForm ? Array.from(propertyForm.querySelectorAll('[data-eo-required-for]')) : [];
    const plotShapeSelect = propertyForm?.querySelector('[data-eo-plot-shape]');
    const plotDimensionFields = propertyForm ? Array.from(propertyForm.querySelectorAll('[data-eo-plot-dimension]')) : [];
    const propertyParkingToggle = propertyForm?.querySelector('[data-eo-property-parking]');
    const propertyParkingFields = propertyForm?.querySelector('[data-eo-property-parking-fields]');
    const surfaceToggleInputs = propertyForm ? Array.from(propertyForm.querySelectorAll('[data-eo-surface-toggle]')) : [];
    const propertyPriceInput = propertyForm?.querySelector('[data-eo-property-price]');
    const propertyAreaInput = propertyForm?.querySelector('[data-eo-property-area]');
    const propertyPricePerSqmInput = propertyForm?.querySelector('[data-eo-property-price-sqm]');
    const mapConfig = typeof config.maps === 'object' && config.maps !== null ? config.maps : {};
    const propertyMapToggle = propertyForm?.querySelector('[data-eo-property-map-toggle]');
    const propertyMapPanel = propertyForm?.querySelector('[data-eo-property-map]');
    const propertyMapSearch = propertyForm?.querySelector('[data-eo-property-map-search]');
    const propertyMapCanvas = propertyForm?.querySelector('[data-eo-property-map-canvas]');
    const propertyMapAddressLabel = propertyForm?.querySelector('[data-eo-property-map-address]');
    const propertyMapStatus = propertyForm?.querySelector('[data-eo-property-map-status]');
    const propertyMapClear = propertyForm?.querySelector('[data-eo-property-map-clear]');
    const propertyMapLatInput = propertyForm?.querySelector('[data-eo-property-map-lat]');
    const propertyMapLngInput = propertyForm?.querySelector('[data-eo-property-map-lng]');
    const propertyMapAddressInput = propertyForm?.querySelector('[data-eo-property-map-address-input]');
    const propertyMapPlaceInput = propertyForm?.querySelector('[data-eo-property-map-place]');
    const searchForm = overlay.querySelector('[data-eo-agreement-search]');
    const searchTransactionInput = overlay.querySelector('[data-eo-agreement-search-transaction]');
    const searchMessage = overlay.querySelector('[data-eo-agreement-search-message]');
    const searchSearchInput = overlay.querySelector('[data-eo-agreement-search-search]');
    const searchResults = overlay.querySelector('[data-eo-agreement-search-results]');
    const step3Heading = overlay.querySelector('[data-eo-agreement-step3-heading]');
    const step3Description = overlay.querySelector('[data-eo-agreement-step3-description]');
    const prevButton = overlay.querySelector('[data-eo-agreement-prev]');
    const clientRoleOptions = (typeof config.clientRoles === 'object' && config.clientRoles !== null) ? config.clientRoles : {};
    const clientRoleEntries = Object.entries(clientRoleOptions);
    const clientRoleLabel = config.labels?.clientRole || '';
    const clientRolePlaceholder = config.labels?.clientRolePlaceholder || '';

    const mapDefaults = Object.assign(
        {
            lat: 52.2296756,
            lng: 21.0122287,
            zoom: 12,
            activeZoom: 16,
        },
        typeof mapConfig.defaults === 'object' && mapConfig.defaults !== null ? mapConfig.defaults : {},
    );

    const mapStrings = Object.assign(
        {
            noApiKey: '',
            loadError: '',
            searchPlaceholder: '',
            applyLocation: '',
            cleared: '',
            geocodeError: '',
            noLocation: '',
        },
        typeof mapConfig.i18n === 'object' && mapConfig.i18n !== null ? mapConfig.i18n : {},
    );

    let googleMapsPromise = null;
    let propertyMapInitPromise = null;
    let propertyMapInstance = null;

    let updateParkingVisibility;
    const surfaceVisibilityUpdaters = [];

    const state = {
        agreementId: 0,
        clients: [],
        redirect: '',
        transactionType: '',
        record: null,
    };

    function parsePropertyList(value) {
        if (!value) {
            return [];
        }

        return value
            .split(',')
            .map((item) => item.trim())
            .filter((item) => item.length > 0);
    }

    function collectFields(element) {
        if (!element) {
            return [];
        }

        if (element.matches('input, select, textarea')) {
            return [element];
        }

        return Array.from(element.querySelectorAll('input, select, textarea'));
    }

    function setElementVisibility(element, visible, options = {}) {
        if (!element) {
            return;
        }

        const { resetOnHide = true } = options;
        const fields = collectFields(element);

        if (visible) {
            element.classList.remove('is-hidden');
            element.removeAttribute('hidden');
            element.removeAttribute('aria-hidden');

            fields.forEach((field) => {
                if (field.dataset.eoHiddenDisabled === '1') {
                    field.removeAttribute('disabled');
                    delete field.dataset.eoHiddenDisabled;
                }
            });

            return;
        }

        element.classList.add('is-hidden');
        element.setAttribute('hidden', 'hidden');
        element.setAttribute('aria-hidden', 'true');

        fields.forEach((field) => {
            if (!field.disabled) {
                field.dataset.eoHiddenDisabled = '1';
                field.setAttribute('disabled', 'disabled');
            }

            if (resetOnHide) {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = false;
                } else if (field.tagName === 'SELECT') {
                    field.selectedIndex = 0;
                } else if ('value' in field) {
                    field.value = '';
                }
            }

            field.removeAttribute('required');
        });
    }

    function updateMapStatus(message) {
        if (propertyMapStatus) {
            propertyMapStatus.textContent = message || '';
        }
    }

    function updateMapAddress(text) {
        if (propertyMapAddressLabel) {
            const label = text && text.length > 0 ? text : mapStrings.noLocation || '';
            propertyMapAddressLabel.textContent = label;
        }
    }

    function setMapPanelVisibility(visible) {
        if (!propertyMapPanel) {
            return;
        }

        if (visible) {
            propertyMapPanel.classList.remove('is-hidden');
            propertyMapPanel.removeAttribute('hidden');
        } else {
            propertyMapPanel.classList.add('is-hidden');
            propertyMapPanel.setAttribute('hidden', 'hidden');
        }
    }

    function setMapClearEnabled(enabled) {
        if (!propertyMapClear) {
            return;
        }

        if (enabled) {
            propertyMapClear.removeAttribute('disabled');
        } else {
            propertyMapClear.setAttribute('disabled', 'disabled');
        }
    }

    function loadGoogleMapsScript() {
        if (window.google && window.google.maps) {
            return Promise.resolve();
        }

        if (!mapConfig.enabled || !mapConfig.apiUrl) {
            return Promise.reject(new Error('maps-disabled'));
        }

        if (!googleMapsPromise) {
            googleMapsPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = mapConfig.apiUrl;
                script.async = true;
                script.defer = true;
                script.onload = () => resolve();
                script.onerror = () => {
                    googleMapsPromise = null;
                    reject(new Error('maps-load-error'));
                };
                document.head.appendChild(script);
            });
        }

        return googleMapsPromise.catch((error) => {
            googleMapsPromise = null;
            throw error;
        });
    }

    function geocodeLatLng(geocoder, latLng) {
        if (!geocoder || !latLng) {
            return Promise.resolve('');
        }

        return geocoder
            .geocode({ location: latLng })
            .then((result) => {
                if (!result || !Array.isArray(result.results) || result.results.length === 0) {
                    return '';
                }

                return result.results[0].formatted_address || '';
            })
            .catch(() => '');
    }

    function applyPropertyLocation(latLng, address, placeId) {
        if (!propertyMapInstance || !latLng) {
            return;
        }

        const { map, marker } = propertyMapInstance;
        const target = typeof latLng.lat === 'function' && typeof latLng.lng === 'function'
            ? { lat: latLng.lat(), lng: latLng.lng() }
            : latLng;

        marker.setPosition(target);
        marker.setVisible(true);
        map.panTo(target);
        map.setZoom(mapDefaults.activeZoom || 16);

        if (propertyMapLatInput) {
            propertyMapLatInput.value = Number.isFinite(target.lat) ? target.lat.toFixed(8) : '';
        }
        if (propertyMapLngInput) {
            propertyMapLngInput.value = Number.isFinite(target.lng) ? target.lng.toFixed(8) : '';
        }
        if (propertyMapAddressInput) {
            propertyMapAddressInput.value = address || '';
        }
        if (propertyMapPlaceInput) {
            propertyMapPlaceInput.value = placeId || '';
        }

        updateMapAddress(address || '');
        updateMapStatus(mapStrings.applyLocation || '');
        setMapClearEnabled(true);
    }

    function ensurePropertyMap() {
        if (!propertyMapPanel || !propertyMapCanvas) {
            return Promise.reject(new Error('maps-missing-elements'));
        }

        if (propertyMapInstance) {
            return Promise.resolve(propertyMapInstance);
        }

        if (propertyMapInitPromise) {
            return propertyMapInitPromise;
        }

        propertyMapInitPromise = loadGoogleMapsScript()
            .then(() => {
                if (!window.google || !window.google.maps) {
                    throw new Error('maps-not-available');
                }

                const storedLat = propertyMapLatInput ? parseFloat(propertyMapLatInput.value) : NaN;
                const storedLng = propertyMapLngInput ? parseFloat(propertyMapLngInput.value) : NaN;
                const hasStored = Number.isFinite(storedLat) && Number.isFinite(storedLng);
                const initialPosition = hasStored
                    ? { lat: storedLat, lng: storedLng }
                    : { lat: Number(mapDefaults.lat) || 52.2296756, lng: Number(mapDefaults.lng) || 21.0122287 };
                const defaultZoom = Number(mapDefaults.zoom) || 12;
                const activeZoom = Number(mapDefaults.activeZoom) || 16;

                const map = new window.google.maps.Map(propertyMapCanvas, {
                    center: initialPosition,
                    zoom: hasStored ? activeZoom : defaultZoom,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true,
                });

                const marker = new window.google.maps.Marker({
                    map,
                    position: initialPosition,
                    draggable: true,
                    visible: hasStored,
                    title: mapStrings.applyLocation || '',
                });

                const geocoder = new window.google.maps.Geocoder();

                if (propertyMapSearch) {
                    if (mapStrings.searchPlaceholder) {
                        propertyMapSearch.placeholder = mapStrings.searchPlaceholder;
                    }

                    const autocomplete = new window.google.maps.places.Autocomplete(propertyMapSearch, {
                        fields: ['geometry', 'formatted_address', 'place_id'],
                    });

                    autocomplete.addListener('place_changed', () => {
                        const place = autocomplete.getPlace();
                        if (!place || !place.geometry || !place.geometry.location) {
                            updateMapStatus(mapStrings.geocodeError || '');
                            return;
                        }

                        applyPropertyLocation(place.geometry.location, place.formatted_address || '', place.place_id || '');
                    });

                    propertyMapInstance = { map, marker, geocoder, autocomplete };
                } else {
                    propertyMapInstance = { map, marker, geocoder };
                }

                map.addListener('click', (event) => {
                    const latLng = event?.latLng;
                    if (!latLng) {
                        return;
                    }

                    geocodeLatLng(geocoder, latLng)
                        .then((address) => {
                            applyPropertyLocation(latLng, address || '', '');
                        })
                        .catch(() => {
                            updateMapStatus(mapStrings.geocodeError || '');
                        });
                });

                marker.addListener('dragend', (event) => {
                    const latLng = event?.latLng;
                    if (!latLng) {
                        return;
                    }

                    geocodeLatLng(geocoder, latLng)
                        .then((address) => {
                            const placeId = propertyMapPlaceInput?.value || '';
                            applyPropertyLocation(latLng, address || '', placeId);
                        })
                        .catch(() => {
                            updateMapStatus(mapStrings.geocodeError || '');
                        });
                });

                if (hasStored) {
                    updateMapAddress(propertyMapAddressInput?.value || '');
                    setMapClearEnabled(true);
                } else {
                    updateMapAddress('');
                    setMapClearEnabled(false);
                }

                updateMapStatus('');

                return propertyMapInstance;
            })
            .catch((error) => {
                propertyMapInitPromise = null;
                if (error && error.message === 'maps-disabled') {
                    updateMapStatus(mapStrings.noApiKey || '');
                } else {
                    updateMapStatus(mapStrings.loadError || '');
                }
                throw error;
            });

        return propertyMapInitPromise;
    }

    function clearPropertyLocation(options = {}) {
        const { silent = false } = options;

        if (propertyMapLatInput) {
            propertyMapLatInput.value = '';
        }
        if (propertyMapLngInput) {
            propertyMapLngInput.value = '';
        }
        if (propertyMapAddressInput) {
            propertyMapAddressInput.value = '';
        }
        if (propertyMapPlaceInput) {
            propertyMapPlaceInput.value = '';
        }

        if (propertyMapInstance?.marker) {
            propertyMapInstance.marker.setVisible(false);
        }

        updateMapAddress('');
        setMapClearEnabled(false);

        if (!silent) {
            updateMapStatus(mapStrings.cleared || '');
        } else {
            updateMapStatus('');
        }
    }

    function resetPropertyMapUI() {
        if (propertyMapSearch) {
            propertyMapSearch.value = '';
        }

        if (propertyMapToggle) {
            propertyMapToggle.removeAttribute('aria-expanded');
        }

        clearPropertyLocation({ silent: true });
        setMapPanelVisibility(false);
    }

    function getClientType() {
        const value = clientTypeSelect?.value || '';

        return value === '' ? 'person' : value;
    }

    function isElementVisible(element) {
        return !!element && !element.hasAttribute('hidden') && !element.classList.contains('is-hidden');
    }

    function isCorrespondenceVisible() {
        return correspondenceSections.some((element) => isElementVisible(element));
    }

    function updateClientScope() {
        if (!clientScopeElements.length) {
            return;
        }

        const clientType = getClientType();

        clientScopeElements.forEach((element) => {
            const scopeAttr = element.getAttribute('data-eo-client-scope') || '';
            const scopes = parsePropertyList(scopeAttr);
            const shouldShow = scopes.length === 0 || scopes.includes(clientType);

            setElementVisibility(element, shouldShow, { resetOnHide: true });
        });
    }

    function updateClientRequirements() {
        if (!clientRequiredElements.length) {
            return;
        }

        const clientType = getClientType();
        const correspondenceVisible = isCorrespondenceVisible();

        clientRequiredElements.forEach((element) => {
            const requiredAttr = element.getAttribute('data-eo-client-required-for') || '';
            const contexts = parsePropertyList(requiredAttr);
            let shouldRequire = contexts.length === 0;

            if (contexts.length > 0) {
                shouldRequire = contexts.includes(clientType);

                if (!shouldRequire && contexts.includes('correspondence')) {
                    shouldRequire = correspondenceVisible;
                }
            }

            const fields = collectFields(element);

            fields.forEach((field) => {
                if (shouldRequire && !field.disabled) {
                    field.setAttribute('required', 'required');
                } else {
                    field.removeAttribute('required');
                }
            });

            const indicator = element.querySelector('[data-eo-required-indicator]');
            if (indicator) {
                if (shouldRequire && isElementVisible(element)) {
                    indicator.removeAttribute('hidden');
                } else {
                    indicator.setAttribute('hidden', 'hidden');
                }
            }
        });
    }

    function updateCorrespondenceVisibility() {
        if (!correspondenceSections.length) {
            updateClientRequirements();
            return;
        }

        const useMainAddress = correspondenceToggle ? correspondenceToggle.checked : true;

        correspondenceSections.forEach((element) => {
            setElementVisibility(element, !useMainAddress, { resetOnHide: true });
        });

        updateClientRequirements();
    }

    function updateClientFormState() {
        updateClientScope();
        updateCorrespondenceVisibility();
    }

    function updatePropertyScope() {
        const propertyType = propertyTypeSelect?.value || '';

        propertyScopedElements.forEach((element) => {
            const scopeAttr = element.getAttribute('data-eo-property-scope') || '';
            const scopes = parsePropertyList(scopeAttr);
            const shouldShow = scopes.length === 0 ? true : (propertyType !== '' && scopes.includes(propertyType));

            setElementVisibility(element, shouldShow, { resetOnHide: true });
        });
    }

    function updatePropertyRequirements() {
        const propertyType = propertyTypeSelect?.value || '';

        propertyConditionalElements.forEach((element) => {
            const requiredAttr = element.getAttribute('data-eo-required-for') || '';
            const requiredTypes = parsePropertyList(requiredAttr);
            const shouldRequire = propertyType !== '' && (requiredTypes.length === 0 || requiredTypes.includes(propertyType));
            const fields = collectFields(element);

            fields.forEach((field) => {
                if (shouldRequire && !field.disabled) {
                    field.setAttribute('required', 'required');
                } else {
                    field.removeAttribute('required');
                }
            });

            const indicator = element.querySelector('[data-eo-required-indicator]');
            if (indicator) {
                if (shouldRequire && !element.hasAttribute('hidden')) {
                    indicator.removeAttribute('hidden');
                } else {
                    indicator.setAttribute('hidden', 'hidden');
                }
            }
        });
    }

    function updatePlotDimensions() {
        const propertyType = propertyTypeSelect?.value || '';
        const plotShape = plotShapeSelect?.value || '';
        const isScopedType = propertyType === 'land' || propertyType === 'house';

        plotDimensionFields.forEach((element) => {
            const dimension = element.getAttribute('data-eo-plot-dimension') || '';
            const shouldShow = isScopedType && plotShape !== '' && dimension === plotShape;

            setElementVisibility(element, shouldShow, { resetOnHide: true });
        });
    }

    function updatePricePerSqm() {
        if (!propertyPricePerSqmInput) {
            return;
        }

        const price = parseFloat(propertyPriceInput?.value || '');
        const area = parseFloat(propertyAreaInput?.value || '');

        if (Number.isFinite(price) && Number.isFinite(area) && area > 0) {
            propertyPricePerSqmInput.value = (price / area).toFixed(2);
        } else {
            propertyPricePerSqmInput.value = '';
        }
    }

    function refreshSurfaceVisibility() {
        surfaceVisibilityUpdaters.forEach((update) => {
            update();
        });
    }

    function refreshPropertyFormState() {
        updatePropertyScope();
        updatePropertyRequirements();
        updatePlotDimensions();

        if (typeof updateParkingVisibility === 'function') {
            updateParkingVisibility();
        }

        refreshSurfaceVisibility();
        updatePricePerSqm();
    }

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

    if (propertyParkingToggle && propertyParkingFields) {
        updateParkingVisibility = () => {
            const parentHidden = propertyParkingToggle.closest('[hidden]') !== null || propertyParkingToggle.disabled;
            const shouldShow = propertyParkingToggle.checked && !parentHidden;

            setElementVisibility(propertyParkingFields, shouldShow, { resetOnHide: true });
        };

        updateParkingVisibility();
        propertyParkingToggle.addEventListener('change', updateParkingVisibility);
    }

    surfaceToggleInputs.forEach((toggle) => {
        const key = toggle.getAttribute('data-eo-surface-toggle');
        if (!key) {
            return;
        }

        const target = propertyForm?.querySelector('[data-eo-surface-fields="' + key + '"]');
        if (!target) {
            return;
        }

        const update = () => {
            const parentHidden = toggle.closest('[hidden]') !== null || toggle.disabled;
            const shouldShow = toggle.checked && !parentHidden;

            setElementVisibility(target, shouldShow, { resetOnHide: true });
        };

        surfaceVisibilityUpdaters.push(update);
        update();
        toggle.addEventListener('change', update);
    });

    if (propertyTypeSelect) {
        propertyTypeSelect.addEventListener('change', () => {
            refreshPropertyFormState();
        });
    }

    if (plotShapeSelect) {
        plotShapeSelect.addEventListener('change', () => {
            updatePlotDimensions();
        });
    }

    if (propertyPriceInput) {
        propertyPriceInput.addEventListener('input', updatePricePerSqm);
        propertyPriceInput.addEventListener('blur', updatePricePerSqm);
    }

    if (propertyAreaInput) {
        propertyAreaInput.addEventListener('input', updatePricePerSqm);
        propertyAreaInput.addEventListener('blur', updatePricePerSqm);
    }

    refreshPropertyFormState();

    if (clientTypeSelect) {
        clientTypeSelect.addEventListener('change', () => {
            updateClientFormState();
        });
    }

    if (correspondenceToggle) {
        correspondenceToggle.addEventListener('change', () => {
            updateCorrespondenceVisibility();
        });
    }

    updateClientFormState();

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
        updateClientFormState();
        if (propertyForm) {
            propertyForm.reset();
            propertyForm.setAttribute('hidden', 'hidden');
            resetPropertyMapUI();
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
        refreshPropertyFormState();
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

            const header = document.createElement('div');
            header.className = 'estate-office-agreement-creator__client-pill-header';

            const content = document.createElement('div');
            content.className = 'estate-office-agreement-creator__client-pill-info';
            content.innerHTML = '<strong>' + (client.name || '') + '</strong>'
                + (client.email ? '<br><span>' + client.email + '</span>' : '')
                + (client.phone ? '<br><span>' + client.phone + '</span>' : '');

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'estate-office-agreement-creator__client-remove';
            removeButton.textContent = '×';
            removeButton.setAttribute('aria-label', 'Usuń');
            removeButton.addEventListener('click', () => detachClient(client.id));

            header.appendChild(content);
            header.appendChild(removeButton);
            pill.appendChild(header);

            const roleWrapper = document.createElement('label');
            roleWrapper.className = 'estate-office-agreement-creator__client-role';
            if (clientRoleLabel) {
                const roleText = document.createElement('span');
                roleText.textContent = clientRoleLabel;
                roleWrapper.appendChild(roleText);
            }

            const roleSelect = document.createElement('select');
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = clientRolePlaceholder || '';
            roleSelect.appendChild(placeholderOption);

            clientRoleEntries.forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                roleSelect.appendChild(option);
            });

            const initialRole = client.role || '';
            roleSelect.value = initialRole;
            roleSelect.dataset.previousValue = initialRole;
            roleSelect.addEventListener('change', (event) => {
                const value = event.target.value || '';
                const previous = event.target.dataset.previousValue || '';
                if (value === previous) {
                    return;
                }
                updateClientRole(client.id, value, roleSelect, previous);
            });

            roleWrapper.appendChild(roleSelect);
            pill.appendChild(roleWrapper);

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
                    resetPropertyMapUI();
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

    const updateClientRole = (clientId, role, selectElement, fallback) => {
        if (!state.agreementId || !clientId) {
            return;
        }

        const message = overlay.querySelector('[data-eo-agreement-step="2"] [data-eo-agreement-message]');
        setMessage(message, '', '');

        if (selectElement) {
            selectElement.setAttribute('disabled', 'disabled');
        }

        const payload = new FormData();
        payload.append('action', 'estate_office_update_client_role');
        payload.append('nonce', config.nonce);
        payload.append('agreement_id', String(state.agreementId));
        payload.append('client_id', String(clientId));
        payload.append('role', role || '');

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, body: data })))
            .then(({ ok, body }) => {
                if (!ok || !body?.success) {
                    setMessage(message, 'error', body?.data?.message || config.messages?.roleUpdateError || config.messages?.genericError || '');
                    if (selectElement) {
                        selectElement.removeAttribute('disabled');
                        selectElement.value = fallback;
                        selectElement.dataset.previousValue = fallback;
                    }
                    return;
                }

                state.clients = body.data?.clients || [];
                renderClients();
                renderSummary();
                setMessage(message, 'success', config.messages?.roleUpdated || '');
            })
            .catch(() => {
                setMessage(message, 'error', config.messages?.roleUpdateError || config.messages?.genericError || '');
                if (selectElement) {
                    selectElement.removeAttribute('disabled');
                    selectElement.value = fallback;
                    selectElement.dataset.previousValue = fallback;
                }
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
                    updateClientFormState();
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
                        resetPropertyMapUI();
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

        propertyForm.addEventListener('reset', () => {
            resetPropertyMapUI();
        });
    }

    if (propertyMapToggle) {
        propertyMapToggle.addEventListener('click', (event) => {
            event.preventDefault();
            setMapPanelVisibility(true);
            propertyMapToggle.setAttribute('aria-expanded', 'true');

            if (!mapConfig.enabled || !mapConfig.apiUrl) {
                updateMapStatus(mapStrings.noApiKey || '');
                return;
            }

            ensurePropertyMap()
                .then(() => {
                    updateMapStatus('');
                    if (propertyMapSearch) {
                        propertyMapSearch.focus();
                        if (typeof propertyMapSearch.select === 'function') {
                            propertyMapSearch.select();
                        }
                    }
                })
                .catch((error) => {
                    if (error && error.message === 'maps-disabled') {
                        updateMapStatus(mapStrings.noApiKey || '');
                    } else {
                        updateMapStatus(mapStrings.loadError || '');
                    }
                });
        });
    }

    if (propertyMapClear) {
        propertyMapClear.addEventListener('click', (event) => {
            event.preventDefault();
            clearPropertyLocation();
        });
    }

    if (propertyMapPanel) {
        setMapPanelVisibility(false);
    }

    if (propertyMapAddressInput && propertyMapLatInput && propertyMapLngInput) {
        const hasInitialLocation = propertyMapLatInput.value !== '' && propertyMapLngInput.value !== '';
        updateMapAddress(propertyMapAddressInput.value || '');
        setMapClearEnabled(hasInitialLocation);
    } else {
        updateMapAddress('');
        setMapClearEnabled(false);
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
            resetPropertyMapUI();
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

        refreshPropertyFormState();
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
            refreshPropertyFormState();
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
                updateClientFormState();
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

