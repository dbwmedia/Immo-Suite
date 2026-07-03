(function () {
    'use strict';

    var cfg = window.dbwContactModal || {};

    // Cached pages can carry an expired nonce (nonce lifetime 12-24h vs. page
    // cache lifespans). Fetch fresh nonces when a modal opens so submissions
    // keep working; the server-rendered nonce stays as fallback.
    var refreshNonces = function () {
        if (!cfg.ajaxurl || !window.fetch) return;
        var data = new FormData();
        data.append('action', 'dbw_immo_get_nonce');
        fetch(cfg.ajaxurl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success || !j.data) return;
                var setNonce = function (formEl, value) {
                    if (!formEl || !value) return;
                    var field = formEl.querySelector('[name="nonce"]');
                    if (field) {
                        field.value = value;
                        // Keep the attribute in sync so form.reset() cannot revert to a stale nonce
                        field.setAttribute('value', value);
                    }
                };
                setNonce(document.getElementById('dbw-contact-form'), j.data.contact);
                setNonce(document.getElementById('dbw-expose-form'), j.data.expose);
            })
            .catch(function () { /* keep server-rendered nonce */ });
    };

    // --- Contact Modal (multi-step) ---
    var modal = document.getElementById('dbw-contact-modal');
    var form = modal ? modal.querySelector('#dbw-contact-form') : null;

    if (modal && form) {
        var steps      = modal.querySelectorAll('.dbw-modal__step');
        var progressBar = modal.querySelector('.dbw-modal__progress-bar');
        var btnClose   = modal.querySelector('.dbw-modal__close');
        var submitBtn  = form.querySelector('button[type="submit"]');
        var originalText = submitBtn ? submitBtn.textContent.trim() : '';
        var currentStep = 1;

        // --- Step Navigation ---
        var goToStep = function (step) {
            currentStep = step;
            steps.forEach(function (s) {
                var sStep = s.dataset.step;
                s.classList.remove('is-active', 'is-exit-left', 'is-enter-right');

                if (sStep === String(step) || sStep === step) {
                    s.classList.add('is-active');
                    // Focus first input on step 2
                    if (step === 2) {
                        var firstInput = s.querySelector('input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"])');
                        if (firstInput) setTimeout(function() { firstInput.focus(); }, 350);
                    }
                }
            });

            // Update progress bar
            if (progressBar) {
                if (step === 1) progressBar.dataset.step = '1';
                else if (step === 2) progressBar.dataset.step = '2';
                else progressBar.dataset.step = '3';
            }
        };

        // --- Intent Selection (auto-advance to step 2) ---
        var intents = modal.querySelectorAll('.dbw-intent');
        intents.forEach(function (intent) {
            intent.addEventListener('click', function () {
                var radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;

                // Visual feedback
                intents.forEach(function (i) { i.classList.remove('is-selected'); });
                this.classList.add('is-selected');

                // Show context fields for this intent
                var val = radio.value;
                modal.querySelectorAll('[data-context]').forEach(function (el) {
                    el.hidden = el.dataset.context !== val;
                });

                // Auto-advance after brief delay
                setTimeout(function () { goToStep(2); }, 300);
            });
        });

        // --- Back Button ---
        modal.querySelectorAll('[data-goto-step]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                goToStep(parseInt(this.dataset.gotoStep));
            });
        });

        // --- Open ---
        document.querySelectorAll('[data-dbw-open-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                // Reset to step 1
                form.reset();
                intents.forEach(function (i) { i.classList.remove('is-selected'); });
                modal.querySelectorAll('[data-context]').forEach(function (el) { el.hidden = true; });
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                var err = form.querySelector('.dbw-modal__error');
                if (err) err.remove();

                refreshNonces();

                // Intent preselect (e.g. finance calculator opens with "preis")
                var preset = btn.dataset.dbwIntent || '';
                var presetRadio = preset ? form.querySelector('input[name="intent"][value="' + preset + '"]') : null;
                if (presetRadio) {
                    presetRadio.checked = true;
                    intents.forEach(function (i) {
                        i.classList.toggle('is-selected', i.dataset.intent === preset);
                    });
                    modal.querySelectorAll('[data-context]').forEach(function (el) {
                        el.hidden = el.dataset.context !== preset;
                    });
                    goToStep(2);
                } else {
                    goToStep(1);
                }
                modal.showModal();
            });
        });

        // --- Close ---
        if (btnClose) {
            btnClose.addEventListener('click', function () { modal.close(); });
        }
        modal.querySelectorAll('[data-close-modal]').forEach(function (b) {
            b.addEventListener('click', function () { modal.close(); });
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.close();
        });

        // --- Submit (AJAX) ---
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (form.website && form.website.value) return; // honeypot

            submitBtn.disabled = true;
            submitBtn.textContent = (cfg.i18n && cfg.i18n.sending) || 'Senden…';

            var data = new FormData(form);
            data.append('action', 'dbw_immo_contact');

            fetch(cfg.ajaxurl, {
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

                goToStep('success');
            })
            .catch(function (err) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                var msg = err.message || (cfg.i18n && cfg.i18n.network_error) || 'Netzwerkfehler';
                var existing = form.querySelector('.dbw-modal__error');
                if (existing) existing.remove();
                var errEl = document.createElement('div');
                errEl.className = 'dbw-modal__error';
                errEl.setAttribute('role', 'alert');
                errEl.textContent = msg;
                submitBtn.parentNode.insertBefore(errEl, submitBtn);
            });
        });
    }

    // --- Expose Request Modal (independent of the contact modal) ---
    var exposeModal = document.getElementById('dbw-expose-modal');
    if (exposeModal) {
        var exposeForm = exposeModal.querySelector('#dbw-expose-form');
        var exposeSubmitBtn = exposeForm ? exposeForm.querySelector('button[type="submit"]') : null;
        var exposeOriginalText = exposeSubmitBtn ? exposeSubmitBtn.textContent.trim() : '';

        // Open
        document.querySelectorAll('[data-dbw-open-expose]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!exposeForm) return;
                exposeForm.reset();
                if (exposeSubmitBtn) {
                    exposeSubmitBtn.disabled = false;
                    exposeSubmitBtn.textContent = exposeOriginalText;
                }
                var err = exposeForm.querySelector('.dbw-modal__error');
                if (err) err.remove();

                // Show form, hide success
                exposeModal.querySelectorAll('[data-expose-step="form"]').forEach(function (el) { el.hidden = false; });
                exposeModal.querySelectorAll('[data-expose-step="success"]').forEach(function (el) { el.hidden = true; });

                refreshNonces();
                exposeModal.showModal();
            });
        });

        // Close
        exposeModal.querySelectorAll('[data-close-expose]').forEach(function (b) {
            b.addEventListener('click', function () { exposeModal.close(); });
        });
        exposeModal.addEventListener('click', function (e) {
            if (e.target === exposeModal) exposeModal.close();
        });

        // Submit
        if (exposeForm) {
            exposeForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (exposeForm.website && exposeForm.website.value) return;

                exposeSubmitBtn.disabled = true;
                exposeSubmitBtn.textContent = (cfg.i18n && cfg.i18n.sending) || 'Senden…';

                var data = new FormData(exposeForm);
                data.append('action', 'dbw_immo_expose_request');

                fetch(cfg.ajaxurl, {
                    method: 'POST',
                    body: data
                })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j.success) throw new Error(j.data || 'Fehler');

                    // Personalize success
                    var userName = (exposeForm.querySelector('[name="name"]').value || '').trim();
                    var firstName = userName.split(' ')[0];
                    var nameEl = exposeModal.querySelector('[data-expose-name]');
                    if (nameEl) {
                        nameEl.textContent = firstName ? ', ' + firstName + '!' : '!';
                    }

                    // Show success, hide form
                    exposeModal.querySelectorAll('[data-expose-step="form"]').forEach(function (el) { el.hidden = true; });
                    exposeModal.querySelectorAll('[data-expose-step="success"]').forEach(function (el) { el.hidden = false; });
                })
                .catch(function (err) {
                    exposeSubmitBtn.disabled = false;
                    exposeSubmitBtn.textContent = exposeOriginalText;
                    var msg = err.message || (cfg.i18n && cfg.i18n.network_error) || 'Netzwerkfehler';
                    var existing = exposeForm.querySelector('.dbw-modal__error');
                    if (existing) existing.remove();
                    var errEl = document.createElement('div');
                    errEl.className = 'dbw-modal__error';
                    errEl.setAttribute('role', 'alert');
                    errEl.textContent = msg;
                    exposeSubmitBtn.parentNode.insertBefore(errEl, exposeSubmitBtn);
                });
            });
        }
    }

    // --- Mobile sticky CTA bar ---
    var stickyBar = document.querySelector('.dbw-sticky-cta-bar');
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
