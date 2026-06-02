document.addEventListener('DOMContentLoaded', function () {

    // ── Filter Toggle (smooth CSS transition via class) ──
    var toggleBtn = document.getElementById('dbw-filter-toggle');
    var container = document.getElementById('dbw-filter-container');

    if (toggleBtn && container) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            container.classList.toggle('is-expanded');
        });
    }

    // ── Entrance Animations (IntersectionObserver) ──
    if (!('IntersectionObserver' in window)) {
        // Fallback: show everything immediately
        document.querySelectorAll('.dbw-property-card, .dbw-section').forEach(function (el) {
            el.classList.add('is-visible');
        });
        return;
    }

    // Cards: staggered entrance
    var cards = document.querySelectorAll('#dbw-immo-suite .dbw-property-card');
    if (cards.length) {
        var cardObserver = new IntersectionObserver(function (entries) {
            var batch = [];
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    batch.push(entry.target);
                    cardObserver.unobserve(entry.target);
                }
            });
            batch.forEach(function (card, i) {
                card.style.setProperty('--stagger', (i * 80) + 'ms');
                card.classList.add('is-visible');
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

        cards.forEach(function (card) { cardObserver.observe(card); });
    }

    // Sections on single page: fade-up on scroll
    var sections = document.querySelectorAll('#dbw-immo-suite .dbw-section');
    if (sections.length) {
        var sectionObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    sectionObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -60px 0px' });

        sections.forEach(function (s) { sectionObserver.observe(s); });
    }
});
