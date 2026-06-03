(function () {
    'use strict';

    var section = document.getElementById('dbw-infra-score');
    if (!section) return;

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Ring animation
    var ring = section.querySelector('.dbw-infra-ring-progress');
    var bars = section.querySelectorAll('.dbw-infra-cat-bar');

    function animate() {
        if (ring) {
            var target = parseFloat(ring.getAttribute('data-target'));
            ring.style.strokeDashoffset = target;
        }
        bars.forEach(function (bar) {
            bar.style.width = bar.getAttribute('data-width') + '%';
        });
    }

    if (reducedMotion) {
        // Show immediately without transition
        if (ring) {
            ring.style.transition = 'none';
        }
        bars.forEach(function (bar) {
            bar.style.transition = 'none';
        });
        animate();
    } else if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animate();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2 });

        observer.observe(section);
    } else {
        animate();
    }

    // Detail toggle
    var toggles = section.querySelectorAll('.dbw-infra-cat-header');
    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var details = btn.nextElementSibling;
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', !expanded);
            details.hidden = expanded;
        });
    });
})();
