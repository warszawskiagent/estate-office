(function () {
    var config = window.EstateOfficeOffers || {};
    var mapConfig = config.googleMaps || {};
    var containers = document.querySelectorAll('[data-offer-map]');

    if (!containers.length) {
        return;
    }

    function showFallback(container) {
        var parent = container.parentNode;
        if (!parent) {
            return;
        }

        var fallback = parent.querySelector('[data-offer-map-fallback]');
        if (!fallback) {
            return;
        }

        fallback.classList.remove('hidden');

        if (mapConfig.i18n && mapConfig.i18n.missingKey) {
            fallback.textContent = mapConfig.i18n.missingKey;
        }
    }

    if (!mapConfig.enabled || !mapConfig.key) {
        containers.forEach(function (container) {
            showFallback(container);
        });
        return;
    }

    var googleMapsPromise;

    function loadGoogleMaps() {
        if (googleMapsPromise) {
            return googleMapsPromise;
        }

        if (window.google && window.google.maps) {
            googleMapsPromise = Promise.resolve(window.google);
            return googleMapsPromise;
        }

        googleMapsPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            var params = ['key=' + encodeURIComponent(mapConfig.key), 'libraries=places'];

            if (mapConfig.language) {
                params.push('language=' + encodeURIComponent(mapConfig.language));
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

    function initialiseMap(container, google) {
        var lat = parseFloat(container.getAttribute('data-lat')) || 0;
        var lng = parseFloat(container.getAttribute('data-lng')) || 0;

        if (!lat && !lng) {
            showFallback(container);
            return;
        }

        var defaults = mapConfig.default || {};
        var zoom = defaults.zoom || 15;

        var map = new google.maps.Map(container, {
            center: { lat: lat, lng: lng },
            zoom: zoom,
        });

        var marker = new google.maps.Marker({
            position: { lat: lat, lng: lng },
            map: map,
        });

        var address = container.getAttribute('data-address');
        if (address) {
            var infoWindow = new google.maps.InfoWindow({ content: address });
            marker.addListener('click', function () {
                infoWindow.open(map, marker);
            });
        }
    }

    loadGoogleMaps()
        .then(function (google) {
            containers.forEach(function (container) {
                initialiseMap(container, google);
            });
        })
        .catch(function () {
            containers.forEach(function (container) {
                showFallback(container);
            });
        });
})();
