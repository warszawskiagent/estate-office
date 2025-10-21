(function () {
    const { wp } = window;
    const wizardConfig = window.EstateOfficeWizard || {};
    let googleMapsPromise = null;

    function toggleIndefiniteContract() {
        var checkbox = document.querySelector('[data-contract-indefinite]');
        var endField = document.querySelector('[data-contract-end]');

        if (!checkbox || !endField) {
            return;
        }

        var update = function () {
            var checked = checkbox.checked;
            endField.disabled = checked;
            if (checked) {
                endField.value = '';
            }
        };

        checkbox.addEventListener('change', update);
        update();
    }

    function toggleClientForms() {
        var typeSelect = document.querySelector('[data-client-type]');
        if (!typeSelect) {
            return;
        }

        var individualFields = document.querySelectorAll('[data-client-individual]');
        var companyFields = document.querySelectorAll('[data-client-company]');
        var toggleMailing = document.querySelector('[data-client-mailing-toggle]');
        var mailingFields = document.querySelector('[data-client-mailing]');

        var refresh = function () {
            var isCompany = typeSelect.value === 'firma';
            individualFields.forEach(function (field) {
                field.classList.toggle('hidden', isCompany);
            });
            companyFields.forEach(function (field) {
                field.classList.toggle('hidden', !isCompany);
            });
        };

        typeSelect.addEventListener('change', refresh);
        refresh();

        if (toggleMailing && mailingFields) {
            var updateMailing = function () {
                mailingFields.classList.toggle('hidden', toggleMailing.checked);
            };
            toggleMailing.addEventListener('change', updateMailing);
            updateMailing();
        }
    }

    function loadGoogleMaps() {
        var config = wizardConfig.googleMaps || {};

        if (googleMapsPromise) {
            return googleMapsPromise;
        }

        if (window.google && window.google.maps) {
            googleMapsPromise = Promise.resolve(window.google);
            return googleMapsPromise;
        }

        if (!config || !config.key) {
            return Promise.reject(new Error('missing-key'));
        }

        googleMapsPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            var params = ['key=' + encodeURIComponent(config.key), 'libraries=places'];

            if (config.language) {
                params.push('language=' + encodeURIComponent(config.language));
            }

            script.src = 'https://maps.googleapis.com/maps/api/js?' + params.join('&');
            script.async = true;
            script.defer = true;
            script.onload = function () {
                if (window.google && window.google.maps) {
                    resolve(window.google);
                } else {
                    reject(new Error('google-maps-unavailable'));
                }
            };
            script.onerror = function () {
                reject(new Error('google-maps-error'));
            };
            document.head.appendChild(script);
        });

        return googleMapsPromise;
    }

    function setupPropertyForm() {
        var form = document.querySelector('[data-property-form]');
        if (!form) {
            return;
        }

        var priceField = form.querySelector('[data-property-price]');
        var areaField = form.querySelector('[data-property-area]');
        var pricePerField = form.querySelector('[data-property-price-m2]');

        var updatePricePer = function () {
            if (!priceField || !areaField || !pricePerField) {
                return;
            }

            var price = parseFloat(priceField.value.replace(',', '.')) || 0;
            var area = parseFloat(areaField.value.replace(',', '.')) || 0;
            pricePerField.value = area > 0 ? (price / area).toFixed(2) : '';
        };

        if (priceField && areaField && pricePerField) {
            priceField.addEventListener('input', updatePricePer);
            areaField.addEventListener('input', updatePricePer);
            updatePricePer();
        }

        var kindSelect = form.querySelector('[data-property-kind]');
        if (kindSelect) {
            var updateKind = function () {
                var current = kindSelect.value;
                form.querySelectorAll('[data-property-not]').forEach(function (element) {
                    var attr = element.getAttribute('data-property-not');
                    if (!attr) {
                        return;
                    }
                    var list = attr.split('|');
                    element.classList.toggle('hidden', list.indexOf(current) !== -1);
                });
                form.querySelectorAll('[data-property-only]').forEach(function (element) {
                    var attr = element.getAttribute('data-property-only');
                    element.classList.toggle('hidden', attr !== current);
                });
            };
            kindSelect.addEventListener('change', updateKind);
            updateKind();
        }

        var plotShape = form.querySelector('[data-property-plot-shape]');
        var plotRegular = form.querySelector('[data-property-plot-regular]');
        var plotIrregular = form.querySelector('[data-property-plot-irregular]');
        if (plotShape) {
            var updatePlot = function () {
                var regular = plotShape.value !== 'nieregularny';
                if (plotRegular) {
                    plotRegular.classList.toggle('hidden', !regular);
                }
                if (plotIrregular) {
                    plotIrregular.classList.toggle('hidden', regular);
                }
            };
            plotShape.addEventListener('change', updatePlot);
            updatePlot();
        }

        var parkingToggle = form.querySelector('[data-property-parking]');
        var parkingOptions = form.querySelector('[data-property-parking-options]');
        if (parkingToggle && parkingOptions) {
            var updateParking = function () {
                parkingOptions.classList.toggle('hidden', !parkingToggle.checked);
            };
            parkingToggle.addEventListener('change', updateParking);
            updateParking();
        }

        var kwToggle = form.querySelector('[data-property-no-kw]');
        var kwField = form.querySelector('[data-property-kw]');
        if (kwToggle && kwField) {
            var updateKw = function () {
                kwField.disabled = kwToggle.checked;
                if (kwToggle.checked) {
                    kwField.value = '';
                }
            };
            kwToggle.addEventListener('change', updateKw);
            updateKw();
        }

        form.querySelectorAll('[data-extra-space]').forEach(function (container) {
            var toggle = container.querySelector('[data-extra-space-toggle]');
            var fields = container.querySelector('[data-extra-space-fields]');
            if (!toggle || !fields) {
                return;
            }
            var update = function () {
                fields.classList.toggle('hidden', !toggle.checked);
            };
            toggle.addEventListener('change', update);
            update();
        });

        setupPropertyMap(form);
        setupPropertyGallery(form);
    }

    function setupPropertyMap(form) {
        var container = form.querySelector('[data-property-map]');
        if (!container) {
            return;
        }

        var config = wizardConfig.googleMaps || {};
        var toggle = container.querySelector('[data-map-toggle]');
        var clearButton = container.querySelector('[data-map-clear]');
        var searchInput = container.querySelector('[data-map-search]');
        var canvas = container.querySelector('[data-map-canvas]');
        var summary = container.querySelector('[data-map-summary]');
        var latField = container.querySelector('[data-map-lat]');
        var lngField = container.querySelector('[data-map-lng]');
        var placeField = container.querySelector('[data-map-place]');
        var addressField = container.querySelector('[data-map-address]');
        var enabled = container.getAttribute('data-map-enabled') === '1' && !!config.enabled;

        var addressFields = {
            street: form.querySelector('input[name="property[address][street]"]'),
            number: form.querySelector('input[name="property[address][number]"]'),
            postal: form.querySelector('input[name="property[address][postal_code]"]'),
            district: form.querySelector('input[name="property[address][district]"]'),
            city: form.querySelector('input[name="property[address][city]"]'),
            county: form.querySelector('input[name="property[address][county]"]'),
            region: form.querySelector('input[name="property[address][region]"]'),
            country: form.querySelector('input[name="property[address][country]"]'),
        };

        if (summary && config.i18n && config.i18n.summaryEmpty) {
            summary.textContent = config.i18n.summaryEmpty;
        }

        if (searchInput && config.i18n && config.i18n.searchPlaceholder) {
            searchInput.placeholder = config.i18n.searchPlaceholder;
        }

        if (clearButton && config.i18n && config.i18n.clearLocation) {
            clearButton.textContent = config.i18n.clearLocation;
        }

        if (toggle && toggle.dataset && toggle.dataset.labelSelect) {
            toggle.textContent = toggle.dataset.labelSelect;
        }

        if (!enabled && toggle) {
            toggle.disabled = true;
        }

        var map;
        var marker;
        var geocoder;
        var autocomplete;

        function updateSummary(lat, lng, address) {
            if (!summary) {
                return;
            }

            if (!lat && !lng) {
                summary.textContent = config.i18n && config.i18n.summaryEmpty ? config.i18n.summaryEmpty : '';
                return;
            }

            var label = address || (lat && lng ? lat.toFixed(6) + ', ' + lng.toFixed(6) : '');

            if (config.i18n && config.i18n.summaryValue) {
                summary.textContent = config.i18n.summaryValue.replace('%s', label);
            } else {
                summary.textContent = label;
            }
        }

        function setLocation(lat, lng, placeId, formattedAddress) {
            if (latField) {
                latField.value = lat ? lat.toFixed(6) : '';
            }
            if (lngField) {
                lngField.value = lng ? lng.toFixed(6) : '';
            }
            if (placeField) {
                placeField.value = placeId || '';
            }
            if (addressField) {
                addressField.value = formattedAddress || '';
            }
            updateSummary(lat, lng, formattedAddress);

            if (clearButton) {
                clearButton.classList.toggle('hidden', !(lat && lng));
            }
        }

        function collectComponents(place) {
            var components = {};
            if (!place || !place.address_components) {
                return components;
            }

            place.address_components.forEach(function (component) {
                component.types.forEach(function (type) {
                    components[type] = component.long_name;
                });
            });

            return components;
        }

        function applyAddress(place) {
            var components = collectComponents(place);

            if (addressFields.street && components.route) {
                addressFields.street.value = components.route;
            }
            if (addressFields.number && components.street_number) {
                addressFields.number.value = components.street_number;
            }
            if (addressFields.postal && components.postal_code) {
                addressFields.postal.value = components.postal_code;
            }
            if (addressFields.city && (components.locality || components.administrative_area_level_2)) {
                addressFields.city.value = components.locality || components.administrative_area_level_2;
            }
            if (addressFields.district && (components.sublocality_level_1 || components.administrative_area_level_3)) {
                addressFields.district.value = components.sublocality_level_1 || components.administrative_area_level_3;
            }
            if (addressFields.county && components.administrative_area_level_2) {
                addressFields.county.value = components.administrative_area_level_2;
            }
            if (addressFields.region && components.administrative_area_level_1) {
                addressFields.region.value = components.administrative_area_level_1;
            }
            if (addressFields.country && components.country) {
                addressFields.country.value = components.country;
            }
        }

        function placeMarker(position, formattedAddress, placeId) {
            if (!map || !position) {
                return;
            }

            if (!marker && window.google && window.google.maps) {
                marker = new window.google.maps.Marker({ map: map });
            }

            if (!marker) {
                return;
            }

            marker.setPosition(position);
            map.panTo(position);
            setLocation(position.lat, position.lng, placeId, formattedAddress);
        }

        function handleMapClick(event) {
            if (!event || !event.latLng) {
                return;
            }

            var lat = event.latLng.lat();
            var lng = event.latLng.lng();

            if (geocoder) {
                geocoder.geocode({ location: event.latLng }, function (results) {
                    if (results && results[0]) {
                        applyAddress(results[0]);
                        placeMarker({ lat: lat, lng: lng }, results[0].formatted_address, results[0].place_id);
                    } else {
                        placeMarker({ lat: lat, lng: lng }, '', '');
                    }
                });
            } else {
                placeMarker({ lat: lat, lng: lng }, '', '');
            }
        }

        function initialiseAutocomplete() {
            if (!searchInput || !window.google || !google.maps || !google.maps.places) {
                return;
            }

            autocomplete = new google.maps.places.Autocomplete(searchInput, {
                fields: ['geometry', 'formatted_address', 'place_id', 'address_components'],
            });

            autocomplete.addListener('place_changed', function () {
                var place = autocomplete.getPlace();
                if (!place || !place.geometry || !place.geometry.location) {
                    return;
                }

                var location = place.geometry.location;
                applyAddress(place);
                placeMarker({ lat: location.lat(), lng: location.lng() }, place.formatted_address, place.place_id);
            });
        }

        function showMap() {
            if (!enabled) {
                return;
            }

            loadGoogleMaps()
                .then(function (google) {
                    if (!canvas) {
                        return;
                    }

                    canvas.classList.remove('hidden');
                    if (searchInput) {
                        searchInput.classList.remove('hidden');
                    }

                    var defaults = config.default || {};
                    var currentLat = latField && latField.value ? parseFloat(latField.value) : null;
                    var currentLng = lngField && lngField.value ? parseFloat(lngField.value) : null;

                    var center = {
                        lat: currentLat || defaults.lat || 52.2297,
                        lng: currentLng || defaults.lng || 21.0122,
                    };

                    var zoom = defaults.zoom || 12;
                    if (currentLat && currentLng) {
                        zoom = 15;
                    }

                    map = new google.maps.Map(canvas, {
                        center: center,
                        zoom: zoom,
                    });
                    geocoder = new google.maps.Geocoder();

                    if (toggle && toggle.dataset && toggle.dataset.labelChange) {
                        toggle.textContent = toggle.dataset.labelChange;
                    }

                    google.maps.event.addListener(map, 'click', handleMapClick);

                    if (currentLat && currentLng) {
                        placeMarker({ lat: currentLat, lng: currentLng }, addressField ? addressField.value : '', placeField ? placeField.value : '');
                    }

                    initialiseAutocomplete();

                    if (config.i18n && config.i18n.selectPrompt) {
                        canvas.setAttribute('data-map-instructions', config.i18n.selectPrompt);
                    }
                })
                .catch(function () {
                    if (summary && config.i18n && config.i18n.missingKey) {
                        summary.textContent = config.i18n.missingKey;
                    }
                });
        }

        if (toggle) {
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                showMap();
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', function (event) {
                event.preventDefault();
                if (marker) {
                    marker.setMap(null);
                    marker = null;
                }
                setLocation(0, 0, '', '');
                if (toggle && toggle.dataset && toggle.dataset.labelSelect) {
                    toggle.textContent = toggle.dataset.labelSelect;
                }
                if (searchInput) {
                    searchInput.value = '';
                }
            });
        }

        if (latField && latField.value && lngField && lngField.value) {
            setLocation(parseFloat(latField.value), parseFloat(lngField.value), placeField ? placeField.value : '', addressField ? addressField.value : '');
        }
    }

    function setupPropertyGallery(form) {
        if (!wp || !wp.media) {
            return;
        }

        var container = form.querySelector('[data-property-gallery]');
        if (!container) {
            return;
        }

        var list = container.querySelector('[data-gallery-list]');
        var emptyState = container.querySelector('[data-gallery-empty]');
        var addButton = container.querySelector('[data-gallery-add]');
        if (!list || !addButton) {
            return;
        }

        var frame;
        var removeLabel = container.getAttribute('data-remove-label') || 'Usuń zdjęcie';

        var refreshEmpty = function () {
            if (!emptyState) {
                return;
            }
            emptyState.classList.toggle('hidden', list.children.length > 0);
        };

        var addAttachment = function (attachment) {
            if (!attachment || !attachment.id) {
                return;
            }

            if (list.querySelector('[data-gallery-id="' + attachment.id + '"]')) {
                return;
            }

            var item = document.createElement('li');
            item.className = 'estate-office-gallery__item';
            item.setAttribute('data-gallery-id', attachment.id);

            var figure = document.createElement('figure');
            figure.className = 'estate-office-gallery__thumb';

            var img = document.createElement('img');
            var sizes = attachment.sizes || {};
            var preferred = sizes.medium || sizes.large || sizes.thumbnail;
            img.src = preferred ? preferred.url : attachment.url;
            img.alt = attachment.alt || attachment.title || '';
            figure.appendChild(img);

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'property[gallery][]';
            input.value = attachment.id;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'estate-office-gallery__remove';
            remove.setAttribute('data-gallery-remove', '1');
            remove.setAttribute('aria-label', removeLabel);
            remove.innerHTML = '×';

            item.appendChild(figure);
            item.appendChild(input);
            item.appendChild(remove);
            list.appendChild(item);
            refreshEmpty();
        };

        addButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (!frame) {
                frame = wp.media({
                    title: addButton.dataset.label || addButton.textContent,
                    button: { text: addButton.dataset.label || addButton.textContent },
                    library: { type: 'image' },
                    multiple: true,
                });

                frame.on('select', function () {
                    var selection = frame.state().get('selection');
                    if (!selection) {
                        return;
                    }

                    selection.each(function (item) {
                        addAttachment(item.toJSON());
                    });
                });
            }

            frame.open();
        });

        list.addEventListener('click', function (event) {
            var removeButton = event.target.closest('[data-gallery-remove]');
            if (!removeButton) {
                return;
            }

            event.preventDefault();
            var item = removeButton.closest('.estate-office-gallery__item');
            if (item) {
                item.remove();
                refreshEmpty();
            }
        });

        refreshEmpty();
    }

    function setupPropertyMapView() {
        var containers = document.querySelectorAll('[data-property-map-view]');
        if (!containers.length) {
            return;
        }

        var config = wizardConfig.googleMaps || {};

        function showFallback(container) {
            var parent = container.parentNode;
            if (!parent) {
                return;
            }

            var fallback = parent.querySelector('[data-map-fallback]');
            if (!fallback) {
                return;
            }

            fallback.classList.remove('hidden');

            if (config.i18n && config.i18n.viewMissingKey) {
                fallback.textContent = config.i18n.viewMissingKey;
            }
        }

        if (!config.enabled || !config.key) {
            containers.forEach(function (container) {
                showFallback(container);
            });
            return;
        }

        loadGoogleMaps()
            .then(function (google) {
                containers.forEach(function (container) {
                    var lat = parseFloat(container.getAttribute('data-lat')) || 0;
                    var lng = parseFloat(container.getAttribute('data-lng')) || 0;

                    if (!lat && !lng) {
                        showFallback(container);
                        return;
                    }

                    var defaults = config.default || {};
                    var zoom = defaults.zoom || 14;

                    var map = new google.maps.Map(container, {
                        center: { lat: lat, lng: lng },
                        zoom: zoom,
                    });

                    new google.maps.Marker({
                        position: { lat: lat, lng: lng },
                        map: map,
                    });
                });
            })
            .catch(function () {
                containers.forEach(function (container) {
                    showFallback(container);
                });
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleIndefiniteContract();
        toggleClientForms();
        setupPropertyForm();
        setupPropertyMapView();
    });
})();
