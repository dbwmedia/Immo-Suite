/**
 * Map consent bridge.
 *
 * The map placeholder is a self-contained two-click solution: it never loads
 * OSM tiles until the visitor asks for them. This bridge adds the optional
 * shortcut — when the site runs a consent tool and the visitor already agreed
 * to the map service there, the placeholder is skipped.
 *
 * Consent tools are detected at runtime, so the plugin stays tool-agnostic.
 * A site can plug in any other tool via window.dbwImmoMapHasConsent.
 *
 * Timing note: optimizers like WP Rocket / AccelerateWP delay consent scripts
 * until the first user interaction, so a single check at load time is not
 * enough. We poll for a while and restart polling on the first interaction.
 */
(function () {
    'use strict';

    var cfg = window.dbwImmoMapConsentCfg || {};
    var serviceIds = (cfg.serviceIds && cfg.serviceIds.length) ? cfg.serviceIds : ['openstreetmap'];

    var granted = false;
    var callbacks = [];
    var pollTimer = null;
    var pollUntil = 0;
    var POLL_INTERVAL = 500;
    var POLL_WINDOW = 30000;

    /**
     * Find the OSM service in the consent tool's own service list, whatever it is named.
     * Saves the site from having to match a hardcoded service id.
     *
     * Deliberately strict: a generic maps service (Borlabs ships "maps" for Google Maps)
     * is NOT accepted, consent for another provider is not consent for OpenStreetMap.
     *
     * @return {Array} matching service ids
     */
    function discoverBorlabsServiceIds(bc) {
        var found = [];
        try {
            var all = bc.Services && bc.Services.services;
            if (!all) return found;
            Object.keys(all).forEach(function (key) {
                var s = all[key] || {};
                var haystack = [
                    s.id || key,
                    s.name || '',
                    s.providerId || '',
                    (s.hosts || []).join(' ')
                ].join(' ').toLowerCase();
                if (/openstreetmap|open street map|(^|[^a-z])osm([^a-z]|$)/.test(haystack)) {
                    found.push(s.id || key);
                }
            });
        } catch (e) { /* unknown structure, fall back to the configured ids */ }
        return found;
    }

    function borlabsCheckIds(bc, ids) {
        for (var i = 0; i < ids.length; i++) {
            try {
                if (bc.Consents.hasConsent(ids[i])) return true;
            } catch (e) { /* unknown service id, try the next one */ }
        }
        return false;
    }

    /**
     * @return {boolean|null} true/false when a tool answered, null when none is present yet.
     */
    function askConsentTool() {
        // Custom hook — lets any consent tool plug in without a plugin update
        if (typeof window.dbwImmoMapHasConsent === 'function') {
            try {
                return !!window.dbwImmoMapHasConsent(serviceIds);
            } catch (e) { /* fall through to the built-in checks */ }
        }

        var bc = window.BorlabsCookie;
        if (bc) {
            // Borlabs Cookie 3.x
            if (bc.Consents && typeof bc.Consents.hasConsent === 'function') {
                if (borlabsCheckIds(bc, serviceIds)) return true;
                // Configured id did not match — ask the tool which service is the OSM one
                if (cfg.autoDetect !== false) {
                    var discovered = discoverBorlabsServiceIds(bc).filter(function (id) {
                        return serviceIds.indexOf(id) === -1;
                    });
                    if (discovered.length && borlabsCheckIds(bc, discovered)) return true;
                }
                return false;
            }
            // Borlabs Cookie 2.x
            if (typeof bc.checkCookieConsent === 'function') {
                for (var j = 0; j < serviceIds.length; j++) {
                    try {
                        if (bc.checkCookieConsent(serviceIds[j])) return true;
                    } catch (e) { /* unknown service id, try the next one */ }
                }
                return false;
            }
        }

        // Real Cookie Banner / Complianz via the WP Consent API
        if (typeof window.wp_has_consent === 'function' && cfg.consentApiCategory) {
            try {
                return !!window.wp_has_consent(cfg.consentApiCategory);
            } catch (e) { /* fall through */ }
        }

        return null;
    }

    function grant() {
        if (granted) return;
        granted = true;
        stopPolling();
        var queue = callbacks;
        callbacks = [];
        queue.forEach(function (cb) {
            try { cb(); } catch (e) { /* one broken callback must not block the others */ }
        });
    }

    function check() {
        if (granted) return true;
        if (askConsentTool() === true) {
            grant();
            return true;
        }
        return false;
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function startPolling() {
        if (granted) return;
        pollUntil = Date.now() + POLL_WINDOW;
        if (pollTimer) return;
        pollTimer = setInterval(function () {
            if (check() || Date.now() > pollUntil) stopPolling();
        }, POLL_INTERVAL);
    }

    // Consent given after page load
    document.addEventListener('borlabs-cookie-consent-saved', check);
    window.addEventListener('borlabs-cookie-consent-saved', check);
    // Generic hook for any other tool: document.dispatchEvent(new Event('dbw-immo-consent-changed'))
    document.addEventListener('dbw-immo-consent-changed', check);

    // Delayed consent scripts only boot on the first interaction — poll again then
    ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (type) {
        window.addEventListener(type, startPolling, { once: true, passive: true });
    });

    // Scripts that need the map may run BEFORE this bridge: aggressive optimizers
    // (AccelerateWP, WP Rocket) rewrite inline scripts into deferred data: URIs and
    // lose the wp_add_inline_script ordering guarantee. They push into this queue.
    var queued = window.dbwImmoMapConsentQueue;

    window.dbwImmoMapConsent = {
        /** Run cb as soon as consent is available (immediately if it already is). */
        onGrant: function (cb) {
            if (typeof cb !== 'function') return;
            if (granted) { cb(); return; }
            callbacks.push(cb);
        },
        isGranted: function () { return granted; },
        /** Explicit opt-in, e.g. the visitor pressed the "load map" button. */
        grant: grant
    };

    // Drain callbacks queued before the bridge existed, then keep the queue usable
    // for anything that still loads later
    if (queued && typeof queued.forEach === 'function') {
        queued.forEach(function (cb) { window.dbwImmoMapConsent.onGrant(cb); });
    }
    window.dbwImmoMapConsentQueue = {
        push: function (cb) { window.dbwImmoMapConsent.onGrant(cb); }
    };

    check();
    startPolling();
})();
