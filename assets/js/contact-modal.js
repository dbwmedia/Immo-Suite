(function () {
    'use strict';

    var modal = document.getElementById('dbw-contact-modal');
    if (!modal) return;

    var form      = modal.querySelector('#dbw-contact-form');
    var viewForm  = modal.querySelector('[data-view="form"]');
    var viewOk    = modal.querySelector('[data-view="success"]');
    var btnClose  = modal.querySelector('.dbw-modal__close');
    var submitBtn = form.querySelector('button[type="submit"]');
    var stickyBar = document.querySelector('.dbw-sticky-cta-bar');
    var originalText = submitBtn.textContent.trim();

    // --- Open ---
    document.querySelectorAll('[data-dbw-open-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            viewForm.hidden = false;
            viewOk.hidden = true;
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            // Clear any previous error
            var err = form.querySelector('.dbw-modal__error');
            if (err) err.remove();
            modal.showModal();
        });
    });

    // --- Close ---
    btnClose.addEventListener('click', function () { modal.close(); });
    modal.querySelectorAll('[data-close-modal]').forEach(function (b) {
        b.addEventListener('click', function () { modal.close(); });
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.close();
    });

    // --- Intent change → reveal context fieldset ---
    form.addEventListener('change', function (e) {
        if (e.target.name !== 'intent') return;
        var val = e.target.value;
        modal.querySelectorAll('[data-context]').forEach(function (el) {
            el.hidden = el.dataset.context !== val;
        });
    });

    // --- Submit (AJAX) ---
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (form.website.value) return; // honeypot

        submitBtn.disabled = true;
        submitBtn.textContent = (window.dbwContactModal.i18n && window.dbwContactModal.i18n.sending) || 'Senden\u2026';

        var data = new FormData(form);
        data.append('action', 'dbw_immo_contact');

        fetch(window.dbwContactModal.ajaxurl, {
            method: 'POST',
            body: data
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j.success) throw new Error(j.data || 'Fehler');

            // Personalize success
            var userName = (form.querySelector('[name="name"]').value || '').trim();
            var firstName = userName.split(' ')[0];
            var nameEl = modal.querySelector('[data-success-name]');
            if (nameEl) {
                nameEl.textContent = firstName ? ', ' + firstName + '!' : '!';
            }

            // Switch views
            viewForm.hidden = true;
            viewOk.hidden = false;
        })
        .catch(function (err) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            var msg = err.message || (window.dbwContactModal.i18n && window.dbwContactModal.i18n.network_error) || 'Netzwerkfehler';
            var existing = form.querySelector('.dbw-modal__error');
            if (existing) existing.remove();
            var errEl = document.createElement('div');
            errEl.className = 'dbw-modal__error';
            errEl.setAttribute('role', 'alert');
            errEl.textContent = msg;
            submitBtn.parentNode.insertBefore(errEl, submitBtn);
        });
    });

    // --- Mobile sticky CTA bar ---
    if (stickyBar) {
        stickyBar.hidden = false;
        var sidebar = document.querySelector('.dbw-sidebar');

        if (sidebar && 'IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                stickyBar.classList.toggle('is-visible', !entries[0].isIntersecting);
            }, { threshold: 0 });
            observer.observe(sidebar);
        } else {
            stickyBar.classList.add('is-visible');
        }
    }
})();
