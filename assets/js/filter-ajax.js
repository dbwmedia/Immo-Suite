(function () {
    'use strict';

    var cfg = window.dbwImmoFilter || {};
    var i18n = cfg.i18n || {};

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('dbw-filter-container');
        var suite = document.getElementById('dbw-immo-suite');
        if (!container || !suite || !cfg.ajaxurl) return;

        var form = container.querySelector('form');
        var grid = suite.querySelector('.dbw-property-grid');
        if (!form || !grid) return;

        container.classList.add('js-enabled');

        var countEl = suite.querySelector('[data-dbw-count]');
        var chipsEl = suite.querySelector('[data-dbw-chips]');
        var searchLabel = container.querySelector('[data-dbw-search-label]');
        var sortSelect = suite.querySelector('.dbw-sort-select');
        var requestId = 0;
        var currentPage = 1;

        // ── Helpers ─────────────────────────────────────────────

        function getParams() {
            var data = new FormData(form);
            var params = {};
            ['location', 'marketing', 'type', 'price_min', 'price_max', 'area_min', 'rooms_min'].forEach(function (key) {
                var val = (data.get(key) || '').toString().trim();
                if (val !== '') params[key] = val;
            });
            if (sortSelect && sortSelect.value && sortSelect.value !== 'date_desc') {
                params.sort = sortSelect.value;
            }
            return params;
        }

        function setFormFromParams(params) {
            var fields = ['location', 'price_min', 'price_max', 'area_min'];
            fields.forEach(function (key) {
                var el = form.querySelector('[name="' + key + '"]');
                if (el) el.value = params[key] || '';
            });
            ['marketing', 'type'].forEach(function (key) {
                var el = form.querySelector('select[name="' + key + '"]');
                if (el) el.value = params[key] || '';
            });
            var roomsVal = params.rooms_min || '';
            form.querySelectorAll('input[name="rooms_min"]').forEach(function (radio) {
                radio.checked = (radio.value === String(roomsVal)) || (roomsVal === '' && radio.value === '');
            });
            if (sortSelect) sortSelect.value = params.sort || 'date_desc';
            syncSliderFromInputs();
        }

        function skeleton(count) {
            var html = '';
            for (var i = 0; i < count; i++) {
                html += '<div class="dbw-skeleton-card" aria-hidden="true">'
                    + '<div class="dbw-skeleton dbw-skeleton--img"></div>'
                    + '<div class="dbw-skeleton-body">'
                    + '<div class="dbw-skeleton dbw-skeleton--line" style="width:75%"></div>'
                    + '<div class="dbw-skeleton dbw-skeleton--line" style="width:45%"></div>'
                    + '<div class="dbw-skeleton dbw-skeleton--line dbw-skeleton--tall"></div>'
                    + '</div></div>';
            }
            return html;
        }

        function revealCards() {
            grid.querySelectorAll('.dbw-property-card').forEach(function (card, i) {
                card.style.setProperty('--stagger', (Math.min(i, 8) * 60) + 'ms');
                // next frame so the transition actually runs
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () { card.classList.add('is-visible'); });
                });
            });
        }

        function updatePagination(html) {
            var existing = suite.querySelector('.dbw-pagination');
            if (existing) {
                if (html) {
                    existing.outerHTML = html;
                } else {
                    existing.remove();
                }
            } else if (html) {
                grid.insertAdjacentHTML('afterend', html);
            }
        }

        function buildQueryString(params, paged) {
            var qs = new URLSearchParams();
            Object.keys(params).forEach(function (k) { qs.set(k, params[k]); });
            if (paged > 1) qs.set('paged', paged);
            var str = qs.toString();
            return str ? '?' + str : '';
        }

        function pushUrl(params, paged) {
            // strip a pretty /page/N/ segment — paged moves into the query string
            var path = location.pathname.replace(/\/page\/\d+\/?$/, '/');
            try {
                history.pushState({ dbwFilter: params, paged: paged }, '', path + buildQueryString(params, paged));
            } catch (e) {}
        }

        // ── Fetch ───────────────────────────────────────────────

        function fetchResults(paged, push) {
            var params = getParams();
            var id = ++requestId;
            currentPage = paged;

            grid.innerHTML = skeleton(Math.min(6, Math.max(3, grid.children.length || 6)));
            grid.classList.remove('is-loading');

            var data = new FormData();
            data.append('action', 'dbw_immo_filter');
            data.append('paged', paged);
            Object.keys(params).forEach(function (k) { data.append(k, params[k]); });
            if (container.dataset.ctxTax) data.append('ctx_tax', container.dataset.ctxTax);
            if (container.dataset.ctxTerm) data.append('ctx_term', container.dataset.ctxTerm);

            fetch(cfg.ajaxurl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (id !== requestId || !j.success) return;
                    var d = j.data;

                    grid.innerHTML = d.html || '<p class="dbw-no-results">' + (i18n.noResults || 'Keine Immobilien gefunden.') + '</p>';
                    revealCards();
                    if (countEl) countEl.textContent = d.count;
                    if (searchLabel) {
                        searchLabel.textContent = (i18n.showResults || '%d Objekte anzeigen').replace('%d', d.count);
                    }
                    if (chipsEl) chipsEl.innerHTML = d.chips || '';
                    updatePagination(d.pagination || '');
                    if (window.dbwArchiveMap && Array.isArray(d.markers)) {
                        window.dbwArchiveMap.refresh(d.markers);
                    }
                    if (push !== false) pushUrl(params, paged);
                    document.dispatchEvent(new CustomEvent('dbw:grid-updated'));
                })
                .catch(function () {
                    if (id === requestId) form.submit(); // graceful fallback: full reload
                });
        }

        var debounceTimer = null;
        function debouncedFetch(delay) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () { fetchResults(1, true); }, delay);
        }

        // ── Price slider with histogram ─────────────────────────

        var priceGroup = container.querySelector('.dbw-price-filter');
        var histogramData = null;
        var sliderMin = null, sliderMax = null, sliderFill = null, histEl = null;
        var inputMin = form.querySelector('input[name="price_min"]');
        var inputMax = form.querySelector('input[name="price_max"]');
        var scaleMax = 0;
        var scaleStep = 1000;

        function activeDataset() {
            if (!histogramData) return null;
            var marketing = (form.querySelector('select[name="marketing"]') || {}).value || '';
            if (marketing.indexOf('miete') !== -1) return histogramData.miete;
            if (marketing.indexOf('kauf') !== -1) return histogramData.kauf;
            // no marketing filter: use the dataset with more objects
            return (histogramData.miete.count > histogramData.kauf.count) ? histogramData.miete : histogramData.kauf;
        }

        function renderHistogram() {
            var ds = activeDataset();
            if (!ds || !histEl) return;
            scaleMax = ds.max || 0;
            scaleStep = scaleMax > 50000 ? 1000 : (scaleMax > 5000 ? 100 : 10);
            histEl.innerHTML = '';
            ds.buckets.forEach(function (h) {
                var bar = document.createElement('div');
                bar.className = 'dbw-hist-bar';
                bar.style.height = Math.max(4, h) + '%';
                histEl.appendChild(bar);
            });
            updateSliderUi();
        }

        function pctToPrice(pct) {
            var raw = scaleMax * pct / 100;
            return Math.round(raw / scaleStep) * scaleStep;
        }

        function priceToPct(price) {
            if (!scaleMax) return 0;
            return Math.max(0, Math.min(100, Math.round(price / scaleMax * 100)));
        }

        function updateSliderUi() {
            if (!sliderMin || !sliderFill || !histEl) return;
            var lo = parseInt(sliderMin.value, 10);
            var hi = parseInt(sliderMax.value, 10);
            sliderFill.style.left = lo + '%';
            sliderFill.style.width = Math.max(0, hi - lo) + '%';
            Array.prototype.forEach.call(histEl.children, function (bar, i) {
                var barPct = (i + 0.5) / histEl.children.length * 100;
                bar.classList.toggle('is-out', barPct < lo || barPct > hi);
            });
        }

        function syncInputsFromSlider() {
            var lo = parseInt(sliderMin.value, 10);
            var hi = parseInt(sliderMax.value, 10);
            if (inputMin) inputMin.value = lo > 0 ? pctToPrice(lo) : '';
            if (inputMax) inputMax.value = hi < 100 ? pctToPrice(hi) : '';
            updateSliderUi();
        }

        function syncSliderFromInputs() {
            if (!sliderMin || !scaleMax) return;
            var lo = inputMin && inputMin.value ? priceToPct(parseFloat(inputMin.value)) : 0;
            var hi = inputMax && inputMax.value ? priceToPct(parseFloat(inputMax.value)) : 100;
            sliderMin.value = Math.min(lo, hi);
            sliderMax.value = Math.max(lo, hi);
            updateSliderUi();
        }

        if (priceGroup) {
            try { histogramData = JSON.parse(priceGroup.dataset.histogram || 'null'); } catch (e) {}
            sliderMin = priceGroup.querySelector('.dbw-range-min');
            sliderMax = priceGroup.querySelector('.dbw-range-max');
            sliderFill = priceGroup.querySelector('.dbw-range-fill');
            histEl = priceGroup.querySelector('.dbw-price-histogram');

            if (histogramData && sliderMin && sliderMax) {
                renderHistogram();
                syncSliderFromInputs();

                [sliderMin, sliderMax].forEach(function (slider) {
                    slider.addEventListener('input', function () {
                        // keep handles from crossing
                        var lo = parseInt(sliderMin.value, 10);
                        var hi = parseInt(sliderMax.value, 10);
                        if (lo > hi) {
                            if (slider === sliderMin) sliderMin.value = hi; else sliderMax.value = lo;
                        }
                        syncInputsFromSlider();
                    });
                    slider.addEventListener('change', function () { debouncedFetch(150); });
                });
            }
        }

        // ── Events ──────────────────────────────────────────────

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            fetchResults(1, true);
            var bar = suite.querySelector('.dbw-archive-header-bar');
            if (bar) bar.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        form.querySelectorAll('select[name="type"], select[name="marketing"]').forEach(function (sel) {
            sel.addEventListener('change', function () {
                if (sel.name === 'marketing' && histogramData) {
                    renderHistogram();
                    syncSliderFromInputs();
                }
                fetchResults(1, true);
            });
        });

        form.querySelectorAll('input[name="rooms_min"]').forEach(function (radio) {
            radio.addEventListener('change', function () { fetchResults(1, true); });
        });

        ['location', 'price_min', 'price_max', 'area_min'].forEach(function (key) {
            var el = form.querySelector('[name="' + key + '"]');
            if (!el) return;
            el.addEventListener('input', function () {
                if (key === 'price_min' || key === 'price_max') syncSliderFromInputs();
                debouncedFetch(500);
            });
        });

        // Reset link
        container.addEventListener('click', function (e) {
            var reset = e.target.closest('.dbw-filter-reset');
            if (!reset) return;
            e.preventDefault();
            form.reset();
            form.querySelectorAll('input[name="rooms_min"]').forEach(function (r) { r.checked = r.value === ''; });
            syncSliderFromInputs();
            fetchResults(1, true);
        });

        // Sort select: AJAX instead of full form submit
        if (sortSelect) {
            sortSelect.removeAttribute('onchange');
            sortSelect.addEventListener('change', function () { fetchResults(1, true); });
        }

        // Chips (delegated — chips are re-rendered by AJAX)
        if (chipsEl) {
            chipsEl.addEventListener('click', function (e) {
                var chip = e.target.closest('[data-dbw-chip]');
                if (!chip) return;
                e.preventDefault();
                var keys = chip.dataset.dbwChip;
                if (keys === '*') {
                    form.reset();
                    form.querySelectorAll('input[name="rooms_min"]').forEach(function (r) { r.checked = r.value === ''; });
                } else {
                    keys.split(',').forEach(function (key) {
                        var el = form.querySelector('[name="' + key + '"]');
                        if (el && el.type !== 'radio') el.value = '';
                        form.querySelectorAll('input[type="radio"][name="' + key + '"]').forEach(function (r) {
                            r.checked = r.value === '';
                        });
                    });
                }
                syncSliderFromInputs();
                fetchResults(1, true);
            });
        }

        // Pagination (delegated: AJAX buttons and server-rendered links)
        suite.addEventListener('click', function (e) {
            var pageBtn = e.target.closest('.dbw-pagination [data-page]');
            var pageLink = e.target.closest('.dbw-pagination a.page-numbers');
            var page = null;
            if (pageBtn) {
                page = parseInt(pageBtn.dataset.page, 10);
            } else if (pageLink) {
                var m = pageLink.href.match(/[?&]paged=(\d+)/) || pageLink.href.match(/\/page\/(\d+)/);
                page = m ? parseInt(m[1], 10) : 1;
            } else {
                return;
            }
            e.preventDefault();
            fetchResults(page, true);
            var bar = suite.querySelector('.dbw-archive-header-bar');
            if (bar) bar.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        // Back/forward navigation
        window.addEventListener('popstate', function () {
            var qs = new URLSearchParams(location.search);
            var params = {};
            qs.forEach(function (v, k) { params[k] = v; });
            setFormFromParams(params);
            fetchResults(parseInt(params.paged || '1', 10), false);
        });
    });
})();
