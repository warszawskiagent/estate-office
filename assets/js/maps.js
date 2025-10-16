(function (window, document) {
    'use strict';

    const config = window.EstateOfficeMaps || {};
    const i18n = config.i18n || {};
    const selector = '.estate-office-map';

    const toFloat = (value) => {
        const parsed = parseFloat(String(value));
        return Number.isFinite(parsed) ? parsed : null;
    };

    const escapeHtml = (value) => {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    const isHttpUrl = (value) => /^https?:/i.test(String(value || ''));

    const createInfoContent = (data) => {
        const parts = [];

        if (data.title) {
            parts.push('<strong>' + escapeHtml(data.title) + '</strong>');
        }

        if (data.address) {
            parts.push('<span>' + escapeHtml(data.address) + '</span>');
        }

        if (data.url && isHttpUrl(data.url)) {
            parts.push('<a href="' + escapeHtml(data.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(i18n.viewOnMap || 'Zobacz na mapie') + '</a>');
        }

        return parts.length > 0 ? '<div class="estate-office-map__info">' + parts.join('') + '</div>' : '';
    };

    const parseMarkers = (element) => {
        const raw = element.getAttribute('data-markers');

        if (!raw) {
            return [];
        }

        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.warn('EstateOfficeMaps: failed to parse markers', error);
            return [];
        }
    };

    const renderMarkers = (map, markers) => {
        const bounds = new google.maps.LatLngBounds();
        const infoWindow = new google.maps.InfoWindow();

        markers.forEach((data) => {
            const lat = toFloat(data.lat);
            const lng = toFloat(data.lng);

            if (lat === null || lng === null) {
                return;
            }

            const position = { lat, lng };
            const marker = new google.maps.Marker({
                map,
                position,
                title: data.title || i18n.markerTitle || '',
            });

            const content = createInfoContent({
                title: data.title,
                address: data.address,
                url: data.url,
            });

            if (content) {
                marker.addListener('click', () => {
                    infoWindow.setContent(content);
                    infoWindow.open({ anchor: marker, map });
                });
            }

            bounds.extend(position);
        });

        if (!bounds.isEmpty()) {
            if (markers.length === 1) {
                map.setZoom(toFloat(markers[0].zoom) || parseInt(map.getZoom(), 10) || 15);
                map.setCenter(bounds.getCenter());
            } else {
                map.fitBounds(bounds, 32);
            }
        }
    };

    const renderSingleMarker = (map, element) => {
        const lat = toFloat(element.getAttribute('data-lat'));
        const lng = toFloat(element.getAttribute('data-lng'));

        if (lat === null || lng === null) {
            element.textContent = i18n.noCoordinates || '';
            element.classList.add('estate-office-map--empty');
            return;
        }

        const zoomAttr = parseInt(element.getAttribute('data-zoom') || '15', 10);
        const zoom = Number.isFinite(zoomAttr) && zoomAttr > 0 ? zoomAttr : 15;

        map.setCenter({ lat, lng });
        map.setZoom(zoom);

        const marker = new google.maps.Marker({
            map,
            position: { lat, lng },
            title: element.getAttribute('data-title') || i18n.markerTitle || '',
        });

        const address = element.getAttribute('data-address') || '';
        const url = element.getAttribute('data-url') || '';
        const infoContent = createInfoContent({
            title: element.getAttribute('data-title') || '',
            address,
            url,
        });

        if (infoContent) {
            const infoWindow = new google.maps.InfoWindow({
                content: infoContent,
            });

            marker.addListener('click', () => {
                infoWindow.open({ anchor: marker, map });
            });
        }
    };

    const initMap = (element) => {
        if (!window.google || !window.google.maps || element.dataset.initialized === '1') {
            return;
        }

        element.dataset.initialized = '1';

        const map = new google.maps.Map(element, {
            center: { lat: 52.2297, lng: 21.0122 },
            zoom: 6,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
        });

        const markers = parseMarkers(element);

        if (markers.length > 0) {
            renderMarkers(map, markers);
            return;
        }

        renderSingleMarker(map, element);
    };

    const scheduleInit = (context) => {
        const start = () => {
            const scope = context instanceof HTMLElement ? context : document;
            const elements = scope.querySelectorAll(selector);

            elements.forEach((element) => {
                initMap(element);
            });
        };

        const waitForGoogle = () => {
            if (window.google && window.google.maps) {
                start();
            } else {
                window.setTimeout(waitForGoogle, 150);
            }
        };

        waitForGoogle();
    };

    window.EstateOfficeMaps = window.EstateOfficeMaps || {};
    window.EstateOfficeMaps.init = scheduleInit;

    const autoInit = () => {
        scheduleInit(document);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }
})(window, document);
