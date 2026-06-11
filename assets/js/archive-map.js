(function () {
    'use strict';

    var map = null;
    var initialized = false;
    var markerLayer = null;
    var markersOverride = null;

    function getMarkers() {
        if (Array.isArray(markersOverride)) return markersOverride;
        var dataEl = document.getElementById('dbw-archive-map-data');
        if (!dataEl) return [];
        try {
            var markers = JSON.parse(dataEl.textContent);
            return Array.isArray(markers) ? markers : [];
        } catch (e) {
            return [];
        }
    }

    function buildPopup(m) {
        var wrap = document.createElement('div');
        wrap.className = 'dbw-map-popup';

        if (m.img) {
            var link = document.createElement('a');
            link.href = m.url;
            var img = document.createElement('img');
            img.src = m.img;
            img.alt = '';
            img.loading = 'lazy';
            img.className = 'dbw-map-popup__img';
            link.appendChild(img);
            wrap.appendChild(link);
        }

        var title = document.createElement('a');
        title.href = m.url;
        title.className = 'dbw-map-popup__title';
        title.textContent = m.title;
        wrap.appendChild(title);

        var price = document.createElement('div');
        price.className = 'dbw-map-popup__price';
        price.textContent = m.price;
        wrap.appendChild(price);

        return wrap;
    }

    function renderMarkers(markers) {
        var mapEl = document.getElementById('dbw-archive-map');
        var emptyEl = document.querySelector('[data-dbw-map-empty]');
        if (!map || !mapEl) return;

        if (markerLayer) {
            markerLayer.clearLayers();
        } else {
            markerLayer = L.layerGroup().addTo(map);
        }

        if (!markers.length) {
            mapEl.style.display = 'none';
            if (emptyEl) emptyEl.hidden = false;
            return;
        }
        mapEl.style.display = 'block';
        if (emptyEl) emptyEl.hidden = true;

        var bounds = [];
        markers.forEach(function (m) {
            var marker = L.marker([m.lat, m.lng]);
            marker.bindPopup(buildPopup(m), { minWidth: 200 });
            markerLayer.addLayer(marker);
            bounds.push([m.lat, m.lng]);
        });

        if (bounds.length === 1) {
            map.setView(bounds[0], 14);
        } else {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
        }
    }

    function initMap() {
        if (initialized || typeof L === 'undefined') return;

        var mapEl = document.getElementById('dbw-archive-map');
        var consentEl = document.getElementById('dbw-archive-map-consent');
        var emptyEl = document.querySelector('[data-dbw-map-empty]');
        if (!mapEl) return;

        initialized = true;
        if (consentEl) consentEl.style.display = 'none';
        mapEl.style.display = 'block';

        var markers = getMarkers();
        if (!markers.length) {
            mapEl.style.display = 'none';
            if (emptyEl) emptyEl.hidden = false;
            return;
        }

        map = L.map('dbw-archive-map', { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 18
        }).addTo(map);

        renderMarkers(markers);
    }

    // Public API: AJAX filtering swaps the marker set
    window.dbwArchiveMap = {
        refresh: function (markers) {
            markersOverride = Array.isArray(markers) ? markers : [];
            if (map) {
                renderMarkers(markersOverride);
            } else if (initialized) {
                // Map container was initialized but skipped (no markers before)
                initialized = false;
                initMap();
            }
        }
    };

    function hasConsent() {
        var consentEl = document.getElementById('dbw-archive-map-consent');
        if (!consentEl) return true; // consent mode disabled
        if (initialized) return true;
        if (window.BorlabsCookie && window.BorlabsCookie.checkCookieConsent && window.BorlabsCookie.checkCookieConsent('openstreetmap')) {
            return true;
        }
        return false;
    }

    function activate() {
        if (hasConsent()) {
            initMap();
        }
        // Leaflet needs a size recalculation when the container becomes visible
        if (map) {
            setTimeout(function () { map.invalidateSize(); }, 50);
        }
    }

    // Registered at top level so the initial setView('map') from view-switch.js
    // (restored from localStorage) is never missed
    document.addEventListener('dbw:enter-map-view', activate);

    document.addEventListener('DOMContentLoaded', function () {
        var loadBtn = document.getElementById('dbw-archive-map-load');
        if (loadBtn) {
            loadBtn.addEventListener('click', initMap);
        }

        document.addEventListener('borlabs-cookie-consent-saved', function () {
            var wrapper = document.getElementById('dbw-archive-map-wrapper');
            if (wrapper && !wrapper.hidden && hasConsent()) {
                initMap();
            }
        });
    });
})();
