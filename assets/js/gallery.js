(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var slider = document.getElementById('dbwGallerySlider');
        if (!slider) return;

        var wrapper = slider.closest('.dbw-gallery-wrapper') || slider.parentElement;
        var slides = slider.querySelectorAll('.dbw-gallery-slide');
        var thumbs = document.querySelectorAll('.dbw-gallery-thumb');
        var counterEl = document.querySelector('[data-dbw-gal-current]');
        var current = 0;

        if (slides.length < 2) return;

        function nearestIndex() {
            var pos = slider.scrollLeft;
            var best = 0;
            var bestDist = Infinity;
            slides.forEach(function (slide, i) {
                var dist = Math.abs(slide.offsetLeft - pos);
                if (dist < bestDist) { bestDist = dist; best = i; }
            });
            return best;
        }

        function update() {
            var idx = nearestIndex();
            if (idx === current) return;
            current = idx;
            if (counterEl) counterEl.textContent = current + 1;
            thumbs.forEach(function (thumb, i) {
                var active = i === current;
                thumb.classList.toggle('is-active', active);
                if (active && thumb.scrollIntoView) {
                    thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
                }
            });
        }

        function goTo(idx) {
            idx = Math.max(0, Math.min(slides.length - 1, idx));
            slider.scrollTo({ left: slides[idx].offsetLeft, behavior: 'smooth' });
        }

        // Track scroll position (native swipe included) via rAF throttle
        var ticking = false;
        slider.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () {
                ticking = false;
                update();
            });
        }, { passive: true });

        // Prev/Next buttons
        var prevBtn = wrapper.querySelector('.dbw-gallery-nav--prev');
        var nextBtn = wrapper.querySelector('.dbw-gallery-nav--next');
        if (prevBtn) prevBtn.addEventListener('click', function () { goTo(nearestIndex() - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goTo(nearestIndex() + 1); });

        // Thumbnails
        thumbs.forEach(function (thumb, i) {
            thumb.addEventListener('click', function () { goTo(i); });
        });

        // Keyboard navigation while the gallery has focus
        wrapper.addEventListener('keydown', function (e) {
            // skip when the lightbox is open — it has its own handler
            var lb = document.getElementById('dbwLightboxOverlay');
            if (lb && lb.style.display === 'flex') return;
            if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(nearestIndex() - 1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); goTo(nearestIndex() + 1); }
        });

        // Initial state
        if (thumbs.length) thumbs[0].classList.add('is-active');
    });
})();
