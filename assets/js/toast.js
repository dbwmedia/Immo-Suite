(function () {
    'use strict';

    var container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.className = 'dbw-toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Show a small toast notification at the bottom of the screen.
     *
     * @param {string} message Plain-text message.
     * @param {Object} [opts]  { actionLabel, actionHref, duration }
     */
    window.dbwToast = function (message, opts) {
        opts = opts || {};
        var toast = document.createElement('div');
        toast.className = 'dbw-toast';

        var text = document.createElement('span');
        text.textContent = message;
        toast.appendChild(text);

        if (opts.actionLabel && opts.actionHref) {
            var action = document.createElement('a');
            action.className = 'dbw-toast__action';
            action.href = opts.actionHref;
            action.textContent = opts.actionLabel;
            toast.appendChild(action);
        }

        getContainer().appendChild(toast);

        requestAnimationFrame(function () {
            requestAnimationFrame(function () { toast.classList.add('is-visible'); });
        });

        setTimeout(function () {
            toast.classList.remove('is-visible');
            setTimeout(function () { toast.remove(); }, 350);
        }, opts.duration || 3000);
    };

    // Create the aria-live region up front — screen readers often miss the
    // first announcement when the live region is injected together with it
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', getContainer);
    } else {
        getContainer();
    }

    // Share buttons: Web Share API with clipboard fallback (no alert())
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dbw-share]');
        if (!btn) return;
        e.preventDefault();

        var url = window.location.href;
        var i18n = window.dbwToastI18n || {};

        if (navigator.share) {
            navigator.share({ title: document.title, url: url }).catch(function () {});
            return;
        }

        var copied = function () { window.dbwToast(i18n.copied || 'Link kopiert'); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(copied).catch(function () {
                window.prompt(i18n.copyManual || 'Link kopieren:', url);
            });
        } else {
            window.prompt(i18n.copyManual || 'Link kopieren:', url);
        }
    });
})();
