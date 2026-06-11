(function () {
    'use strict';

    // Cross-document view transition: the clicked card image morphs into
    // the hero image on the property page (and back). Only ONE element per
    // page may carry the view-transition-name, so it is assigned on demand.

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var NAME = 'dbw-hero';

    function clearNames() {
        document.querySelectorAll('.dbw-card-img').forEach(function (img) {
            if (img.style.viewTransitionName) img.style.viewTransitionName = '';
        });
        // The single-page hero carries the name via CSS — neutralize it when
        // a card (e.g. "Aehnliche Objekte") is clicked, names must be unique
        document.querySelectorAll('.dbw-gallery-slide img').forEach(function (img) {
            img.style.viewTransitionName = 'none';
        });
    }

    // Outgoing: clicking a card link tags its image
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.dbw-property-card a[href]');
        if (!link) return;
        var card = link.closest('.dbw-property-card');
        var img = card ? card.querySelector('.dbw-card-img') : null;
        if (!img) return;

        clearNames();
        img.style.viewTransitionName = NAME;
        try { sessionStorage.setItem('dbwVtCard', card.id || ''); } catch (err) {}
    });

    // Incoming (back navigation to a list): tag the card that was left from
    // before the reveal snapshot is taken
    window.addEventListener('pagereveal', function () {
        var cardId = '';
        try {
            cardId = sessionStorage.getItem('dbwVtCard') || '';
            sessionStorage.removeItem('dbwVtCard'); // one-shot — a stale ID would morph the wrong card later
        } catch (err) {}
        if (!cardId) return;
        var img = document.querySelector('#' + CSS.escape(cardId) + ' .dbw-card-img');
        if (img) {
            clearNames();
            img.style.viewTransitionName = NAME;
        }
    });
})();
