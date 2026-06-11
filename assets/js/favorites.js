(function () {
    'use strict';

    var STORAGE_KEY = 'dbw_immo_favorites';
    var MAX_IDS = 60; // must match Favorites::MAX_IDS on the server
    var cfg = window.dbwFavorites || {};
    var i18n = cfg.i18n || {};

    function getFavorites() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            var ids = raw ? JSON.parse(raw) : [];
            return Array.isArray(ids) ? ids : [];
        } catch (e) {
            return [];
        }
    }

    function saveFavorites(ids) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(ids)); } catch (e) {}
    }

    function isFavorite(id) {
        return getFavorites().indexOf(id) !== -1;
    }

    function toggleFavorite(id) {
        var ids = getFavorites();
        var idx = ids.indexOf(id);
        if (idx === -1) {
            ids.push(id);
            // Cap at the server limit — drop the oldest entry instead of
            // silently losing IDs the server would never echo back
            while (ids.length > MAX_IDS) {
                ids.shift();
            }
        } else {
            ids.splice(idx, 1);
        }
        saveFavorites(ids);
        return idx === -1;
    }

    // --- Heart buttons on cards (event delegation: works for AJAX-loaded cards too) ---
    function syncButton(btn) {
        var id = parseInt(btn.dataset.dbwFav, 10);
        var active = isFavorite(id);
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.setAttribute('aria-label', active
            ? (i18n.remove || 'Von der Merkliste entfernen')
            : (i18n.add || 'Zur Merkliste hinzufuegen'));
    }

    function syncAllButtons() {
        document.querySelectorAll('[data-dbw-fav]').forEach(syncButton);
    }

    function updateCount() {
        var count = getFavorites().length;
        document.querySelectorAll('[data-dbw-fav-count]').forEach(function (el) {
            el.textContent = count;
        });
        var toggle = document.getElementById('dbw-fav-toggle');
        if (toggle) {
            toggle.hidden = false;
            toggle.classList.toggle('has-items', count > 0);
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dbw-fav]');
        if (!btn) return;
        e.preventDefault();

        var id = parseInt(btn.dataset.dbwFav, 10);
        toggleFavorite(id);
        syncButton(btn);
        updateCount();

        // In favorites view: remove the card when unfavorited
        if (favMode && !isFavorite(id)) {
            var card = btn.closest('.dbw-property-card');
            if (card) card.remove();
            if (getFavorites().length === 0) {
                renderEmptyState();
            }
        }
    });

    // --- Favorites view (archive toolbar toggle) ---
    var favMode = false;
    var originalGridHtml = null;

    function getGrid() {
        var container = document.getElementById('dbw-immo-suite');
        return container ? container.querySelector('.dbw-property-grid') : null;
    }

    function renderEmptyState() {
        var grid = getGrid();
        if (!grid) return;
        grid.innerHTML = '<p class="dbw-fav-empty">' + (i18n.empty || 'Noch keine Objekte gemerkt. Klicke das Herz auf einer Immobilie, um sie hier zu sammeln.') + '</p>';
    }

    function enterFavMode() {
        var grid = getGrid();
        var toggle = document.getElementById('dbw-fav-toggle');
        if (!grid || !toggle) return;

        favMode = true;
        toggle.classList.add('is-active');
        toggle.setAttribute('aria-pressed', 'true');
        document.querySelectorAll('.dbw-pagination').forEach(function (el) { el.hidden = true; });
        if (originalGridHtml === null) {
            originalGridHtml = grid.innerHTML;
        }

        var ids = getFavorites();
        if (!ids.length) {
            renderEmptyState();
            return;
        }

        grid.classList.add('is-loading');
        var data = new FormData();
        data.append('action', 'dbw_immo_favorites');
        data.append('ids', ids.join(','));

        fetch(cfg.ajaxurl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                grid.classList.remove('is-loading');
                if (!favMode) return; // user switched back while loading
                if (j.success && j.data.html) {
                    grid.innerHTML = j.data.html;
                    // AJAX-loaded cards bypass the entrance-animation observer
                    // in frontend.js — show them immediately
                    grid.querySelectorAll('.dbw-property-card').forEach(function (c) {
                        c.classList.add('is-visible');
                    });
                    // Prune IDs that no longer exist / are unpublished
                    // (safe: the request never exceeds MAX_IDS, see toggleFavorite)
                    if (Array.isArray(j.data.ids) && ids.length <= MAX_IDS) {
                        saveFavorites(getFavorites().filter(function (id) {
                            return j.data.ids.indexOf(id) !== -1;
                        }));
                        updateCount();
                    }
                    syncAllButtons();
                } else {
                    renderEmptyState();
                }
            })
            .catch(function () {
                grid.classList.remove('is-loading');
                if (favMode) renderEmptyState();
            });
    }

    function exitFavMode() {
        var grid = getGrid();
        var toggle = document.getElementById('dbw-fav-toggle');
        favMode = false;
        if (toggle) {
            toggle.classList.remove('is-active');
            toggle.setAttribute('aria-pressed', 'false');
        }
        // Keep pagination hidden when the map view is (or just became) visible
        var mapWrapper = document.getElementById('dbw-archive-map-wrapper');
        var mapVisible = mapWrapper && !mapWrapper.hidden;
        document.querySelectorAll('.dbw-pagination').forEach(function (el) { el.hidden = mapVisible; });
        if (grid && originalGridHtml !== null) {
            grid.innerHTML = originalGridHtml;
            originalGridHtml = null;
            syncAllButtons();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncAllButtons();
        updateCount();

        var toggle = document.getElementById('dbw-fav-toggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                if (favMode) {
                    exitFavMode();
                } else {
                    // Leave map view first so the grid is visible
                    document.dispatchEvent(new CustomEvent('dbw:leave-map-view'));
                    enterFavMode();
                }
            });
        }

        // Leave favorites view when the user switches to the map
        document.addEventListener('dbw:enter-map-view', function () {
            if (favMode) exitFavMode();
        });

        // AJAX filtering replaced the grid: sync hearts on the new cards and
        // drop the favorites-view state (its stored grid HTML is stale now)
        document.addEventListener('dbw:grid-updated', function () {
            if (favMode) {
                favMode = false;
                originalGridHtml = null;
                var t = document.getElementById('dbw-fav-toggle');
                if (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-pressed', 'false');
                }
            }
            syncAllButtons();
        });
    });
})();
