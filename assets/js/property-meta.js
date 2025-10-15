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

    document.addEventListener('DOMContentLoaded', function () {
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
    });
})(window, document);
