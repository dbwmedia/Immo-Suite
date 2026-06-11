(function () {
    'use strict';

    var cfg = window.dbwSectionNav || {};
    var POSITION = cfg.position || 'top'; // 'top' | 'bottom' | 'off'

    if (POSITION === 'off') return;

    document.addEventListener('DOMContentLoaded', function () {
        var suite = document.getElementById('dbw-immo-suite');
        if (!suite || !suite.classList.contains('dbw-single-property-container')) return;

        var detailsGrid = suite.querySelector('.dbw-details-grid');
        if (!detailsGrid) return;

        // Collect sections that have a visible title
        var sections = [];
        suite.querySelectorAll('.dbw-main-col .dbw-section').forEach(function (section, i) {
            var titleEl = section.querySelector('.dbw-section-title');
            var title = titleEl ? titleEl.textContent.trim() : '';
            if (!title) return;
            if (!section.id) section.id = 'dbw-sec-' + i;
            sections.push({ el: section, id: section.id, title: title });
        });

        if (sections.length < 2) return;

        // Build the nav
        var nav = document.createElement('nav');
        nav.className = 'dbw-section-nav' + (POSITION === 'bottom' ? ' dbw-section-nav--bottom' : '');
        nav.setAttribute('aria-label', 'Abschnitte');

        var inner = document.createElement('div');
        inner.className = 'dbw-section-nav__inner';

        var links = [];
        sections.forEach(function (s) {
            var link = document.createElement('a');
            link.href = '#' + s.id;
            link.className = 'dbw-section-nav__link';
            link.textContent = s.title;
            link.addEventListener('click', function (e) {
                e.preventDefault();
                s.el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                try { history.replaceState(null, '', '#' + s.id); } catch (err) {}
            });
            inner.appendChild(link);
            links.push(link);
        });

        nav.appendChild(inner);

        if (POSITION === 'top') {
            var progress = document.createElement('div');
            progress.className = 'dbw-section-nav__progress';
            var progressBar = document.createElement('div');
            progressBar.className = 'dbw-section-nav__progress-bar';
            progress.appendChild(progressBar);
            nav.appendChild(progress);
            suite.classList.add('dbw-has-nav-top');
        } else {
            suite.classList.add('dbw-has-nav-bottom');
        }

        detailsGrid.parentNode.insertBefore(nav, detailsGrid);

        // ── Auto-measure the height of fixed/sticky theme chrome at the top
        //    (admin bar + theme header, stacked) and expose it as a CSS var ──
        var headerOffset = 0;

        function measureHeaderOffset() {
            var offset = 0;
            var y = 2;
            var vw = window.innerWidth;

            // walk down: each pass finds the next fixed/sticky bar below the previous one
            for (var pass = 0; pass < 4; pass++) {
                var hit = null;
                var els = document.elementsFromPoint(Math.floor(vw / 2), y) || [];
                for (var i = 0; i < els.length; i++) {
                    var el = els[i];
                    if (el === nav || nav.contains(el) || el === document.documentElement || el === document.body) continue;
                    if (suite.contains(el)) continue;
                    var cs = getComputedStyle(el);
                    if (cs.position !== 'fixed' && cs.position !== 'sticky') continue;
                    if (cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0) continue;
                    var r = el.getBoundingClientRect();
                    // full-ish width bars only (headers, admin bar) — not FABs/overlays
                    if (r.width < vw * 0.5 || r.height > window.innerHeight * 0.5) continue;
                    if (r.top > y || r.bottom <= y) continue;
                    hit = r.bottom;
                    break;
                }
                if (hit === null || hit <= offset) break;
                offset = hit;
                y = Math.ceil(offset) + 2;
            }
            return Math.max(0, Math.round(offset));
        }

        function applyOffset() {
            var measured = measureHeaderOffset();
            if (measured !== headerOffset) {
                headerOffset = measured;
                suite.style.setProperty('--dbw-nav-offset', headerOffset + 'px');
            }
        }

        // ── Scroll handling: spy, progress, offset re-measurement ──
        function setActive(idx) {
            links.forEach(function (l, i) {
                l.classList.toggle('is-active', i === idx);
            });
            var active = links[idx];
            if (active && active.scrollIntoView) {
                active.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
            }
        }

        var ticking = false;
        var lastMeasure = 0;

        function onScroll() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () {
                ticking = false;

                // re-measure occasionally — sticky headers appear/shrink on scroll
                var now = Date.now();
                if (now - lastMeasure > 400) {
                    lastMeasure = now;
                    applyOffset();
                }

                // Active section: the last one whose top passed the docking edge
                var edge = (POSITION === 'top')
                    ? nav.getBoundingClientRect().bottom + 24
                    : headerOffset + 80;
                var activeIdx = 0;
                sections.forEach(function (s, i) {
                    if (s.el.getBoundingClientRect().top <= edge) activeIdx = i;
                });
                setActive(activeIdx);

                // Reading progress across the details area (top variant only)
                if (POSITION === 'top') {
                    var rect = detailsGrid.getBoundingClientRect();
                    var total = rect.height - window.innerHeight;
                    var done = total > 0 ? Math.min(1, Math.max(0, -rect.top / total)) : 1;
                    nav.querySelector('.dbw-section-nav__progress-bar').style.width = (done * 100) + '%';
                }
            });
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', function () { lastMeasure = 0; onScroll(); }, { passive: true });
        applyOffset();
        onScroll();
    });
})();
