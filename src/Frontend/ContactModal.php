<?php

if (!defined('ABSPATH')) { exit; }

namespace DBW\ImmoSuite\Frontend;

/**
 * Multi-step contact modal with Typeform-style UX.
 * Step 1: Intent selection (animated cards)
 * Step 2: Contact details + intent-specific fields
 * Success: Animated confirmation with agent card
 */
class ContactModal
{
    public function init()
    {
        add_action('wp_footer', array($this, 'render_modal'));
    }

    /**
     * Render CTA buttons for the sidebar (called from template).
     */
    public static function render_cta_buttons($post_id)
    {
        $contact_tel = get_post_meta($post_id, 'kontaktperson_tel', true);
        ?>
        <div class="dbw-cta-stack">
            <button type="button"
                    class="dbw-cta dbw-cta--primary"
                    data-dbw-open-modal="<?php echo esc_attr($post_id); ?>">
                <svg class="dbw-cta__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span class="dbw-cta__text"><?php esc_html_e('Immobilie anfragen', 'dbw-immo-suite'); ?></span>
            </button>
            <?php if ($contact_tel):
                $phone = \DBW\ImmoSuite\dbw_format_phone($contact_tel);
            ?>
                <a href="tel:<?php echo esc_attr($phone['tel']); ?>" class="dbw-phone-link dbw-cta-phone">
                    <?php echo esc_html($phone['display']); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the multi-step modal dialog in wp_footer.
     */
    public function render_modal()
    {
        if (!is_singular('immobilie')) {
            return;
        }

        global $post;
        $post_id = $post->ID;
        $thumb   = get_the_post_thumbnail_url($post_id, 'thumbnail');
        $title   = get_the_title($post_id);

        $area  = (float) get_post_meta($post_id, 'wohnflaeche', true);
        $rooms = (float) get_post_meta($post_id, 'anzahl_zimmer', true);
        $price = (float) get_post_meta($post_id, 'kaufpreis', true) ?: (float) get_post_meta($post_id, 'kaltmiete', true);

        $contact_name    = trim(get_post_meta($post_id, 'kontaktperson_vorname', true) . ' ' . get_post_meta($post_id, 'kontaktperson_name', true));
        $contact_tel     = get_post_meta($post_id, 'kontaktperson_tel', true);
        $contact_email   = get_post_meta($post_id, 'kontaktperson_email', true);
        $contact_img_url = get_post_meta($post_id, 'kontaktperson_bild_url', true);
        ?>
        <dialog id="dbw-contact-modal" class="dbw-modal" aria-labelledby="dbw-modal-title">
            <form id="dbw-contact-form" class="dbw-modal__form" data-property-id="<?php echo esc_attr($post_id); ?>">

                <!-- Header (always visible) -->
                <header class="dbw-modal__header">
                    <?php if ($thumb): ?>
                        <img class="dbw-modal__thumb" src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy">
                    <?php endif; ?>
                    <div class="dbw-modal__header-text">
                        <p class="dbw-modal__eyebrow"><?php esc_html_e('Anfrage zu', 'dbw-immo-suite'); ?></p>
                        <h2 id="dbw-modal-title" class="dbw-modal__title"><?php echo esc_html($title); ?></h2>
                        <?php if ($area || $rooms || $price): ?>
                            <p class="dbw-modal__quickfacts">
                                <?php
                                $parts = array();
                                if ($area) $parts[]  = \DBW\ImmoSuite\dbw_format_number($area, 'flaeche') . ' m&sup2;';
                                if ($rooms) $parts[] = \DBW\ImmoSuite\dbw_format_number($rooms, 'zimmer') . ' Zi.';
                                if ($price) $parts[] = \DBW\ImmoSuite\dbw_format_number($price, 'preis') . ' &euro;';
                                echo implode(' &middot; ', $parts);
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="dbw-modal__close" aria-label="<?php esc_attr_e('Schliessen', 'dbw-immo-suite'); ?>">&times;</button>
                </header>

                <!-- Progress Bar -->
                <div class="dbw-modal__progress">
                    <div class="dbw-modal__progress-bar" data-step="1"></div>
                </div>

                <!-- Steps Container (slides horizontally) -->
                <div class="dbw-modal__steps">

                    <!-- ═══ STEP 1: Intent Selection ═══ -->
                    <div class="dbw-modal__step is-active" data-step="1">
                        <div class="dbw-step__content">
                            <h3 class="dbw-step__title"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                __('Wie koennen wir Ihnen helfen?', 'dbw-immo-suite'),
                                __('Wie koennen wir dir helfen?', 'dbw-immo-suite')
                            )); ?></h3>
                            <p class="dbw-step__subtitle"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                __('Waehlen Sie Ihr Anliegen — wir kuemmern uns darum.', 'dbw-immo-suite'),
                                __('Waehl dein Anliegen — wir kuemmern uns darum.', 'dbw-immo-suite')
                            )); ?></p>

                            <div class="dbw-intent-grid">
                                <label class="dbw-intent" data-intent="besichtigung">
                                    <input type="radio" name="intent" value="besichtigung" required>
                                    <span class="dbw-intent__icon" aria-hidden="true">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
                                    </span>
                                    <span class="dbw-intent__label"><?php esc_html_e('Besichtigung', 'dbw-immo-suite'); ?></span>
                                    <span class="dbw-intent__desc"><?php esc_html_e('Termin vereinbaren', 'dbw-immo-suite'); ?></span>
                                </label>
                                <label class="dbw-intent" data-intent="info">
                                    <input type="radio" name="intent" value="info">
                                    <span class="dbw-intent__icon" aria-hidden="true">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    </span>
                                    <span class="dbw-intent__label"><?php esc_html_e('Mehr Infos', 'dbw-immo-suite'); ?></span>
                                    <span class="dbw-intent__desc"><?php esc_html_e('Expose & Details', 'dbw-immo-suite'); ?></span>
                                </label>
                                <label class="dbw-intent" data-intent="preis">
                                    <input type="radio" name="intent" value="preis">
                                    <span class="dbw-intent__icon" aria-hidden="true">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    </span>
                                    <span class="dbw-intent__label"><?php echo 'Preis &amp; Finanzierung'; ?></span>
                                    <span class="dbw-intent__desc"><?php esc_html_e('Konditionen anfragen', 'dbw-immo-suite'); ?></span>
                                </label>
                                <label class="dbw-intent" data-intent="rueckruf">
                                    <input type="radio" name="intent" value="rueckruf">
                                    <span class="dbw-intent__icon" aria-hidden="true">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    </span>
                                    <span class="dbw-intent__label"><?php esc_html_e('Rueckruf', 'dbw-immo-suite'); ?></span>
                                    <span class="dbw-intent__desc"><?php esc_html_e('Wir rufen an', 'dbw-immo-suite'); ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ STEP 2: Contact Details ═══ -->
                    <div class="dbw-modal__step" data-step="2">
                        <div class="dbw-step__content">
                            <button type="button" class="dbw-step__back" data-goto-step="1" aria-label="<?php esc_attr_e('Zurueck', 'dbw-immo-suite'); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                                <?php esc_html_e('Zurueck', 'dbw-immo-suite'); ?>
                            </button>

                            <h3 class="dbw-step__title"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                __('Ihre Kontaktdaten', 'dbw-immo-suite'),
                                __('Deine Kontaktdaten', 'dbw-immo-suite')
                            )); ?></h3>

                            <!-- Intent-specific context (shown based on selection) -->
                            <fieldset class="dbw-field dbw-field--context" data-context="besichtigung" hidden>
                                <legend><?php esc_html_e('Wunschtermin', 'dbw-immo-suite'); ?></legend>
                                <div class="dbw-context-row">
                                    <input type="date" name="appointment_date" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>">
                                    <select name="appointment_time">
                                        <option value="morning"><?php esc_html_e('Vormittag', 'dbw-immo-suite'); ?></option>
                                        <option value="afternoon"><?php esc_html_e('Nachmittag', 'dbw-immo-suite'); ?></option>
                                        <option value="evening"><?php esc_html_e('Abend', 'dbw-immo-suite'); ?></option>
                                    </select>
                                </div>
                            </fieldset>

                            <fieldset class="dbw-field dbw-field--context" data-context="rueckruf" hidden>
                                <legend><?php esc_html_e('Wann sollen wir anrufen?', 'dbw-immo-suite'); ?></legend>
                                <select name="callback_time">
                                    <option value="morning"><?php esc_html_e('Vormittag (9-12 Uhr)', 'dbw-immo-suite'); ?></option>
                                    <option value="afternoon"><?php esc_html_e('Nachmittag (12-17 Uhr)', 'dbw-immo-suite'); ?></option>
                                    <option value="evening"><?php esc_html_e('Abend (17-19 Uhr)', 'dbw-immo-suite'); ?></option>
                                </select>
                            </fieldset>

                            <fieldset class="dbw-field dbw-field--context" data-context="preis" hidden>
                                <legend><?php esc_html_e('Finanzierung bereits geklaert?', 'dbw-immo-suite'); ?></legend>
                                <div class="dbw-pill-group">
                                    <label class="dbw-pill"><input type="radio" name="financing" value="yes"> <?php esc_html_e('Ja', 'dbw-immo-suite'); ?></label>
                                    <label class="dbw-pill"><input type="radio" name="financing" value="partial"> <?php esc_html_e('Teilweise', 'dbw-immo-suite'); ?></label>
                                    <label class="dbw-pill"><input type="radio" name="financing" value="no"> <?php esc_html_e('Noch nicht', 'dbw-immo-suite'); ?></label>
                                </div>
                            </fieldset>

                            <!-- Contact fields -->
                            <label class="dbw-field__label">
                                <span><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                    __('Ihr Name', 'dbw-immo-suite'),
                                    __('Dein Name', 'dbw-immo-suite')
                                )); ?> *</span>
                                <input type="text" name="name" required autocomplete="name" class="dbw-input">
                            </label>
                            <label class="dbw-field__label">
                                <span><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                    __('Ihre E-Mail', 'dbw-immo-suite'),
                                    __('Deine E-Mail', 'dbw-immo-suite')
                                )); ?> *</span>
                                <input type="email" name="email" required autocomplete="email" class="dbw-input">
                            </label>
                            <label class="dbw-field__label">
                                <span><?php esc_html_e('Telefon (optional)', 'dbw-immo-suite'); ?></span>
                                <input type="tel" name="phone" autocomplete="tel" class="dbw-input">
                            </label>
                            <label class="dbw-field__label">
                                <span><?php esc_html_e('Nachricht (optional)', 'dbw-immo-suite'); ?></span>
                                <textarea name="message" rows="2" class="dbw-input" placeholder="<?php echo esc_attr(\DBW\ImmoSuite\dbw_anrede(
                                    __('Anmerkungen oder konkrete Fragen...', 'dbw-immo-suite'),
                                    __('Anmerkungen oder konkrete Fragen...', 'dbw-immo-suite')
                                )); ?>"></textarea>
                            </label>

                            <!-- Privacy -->
                            <label class="dbw-privacy">
                                <input type="checkbox" name="privacy" required>
                                <span><?php
                                    $privacy_url = get_privacy_policy_url();
                                    if ($privacy_url) {
                                        printf(
                                            esc_html__('Ich stimme der %sDatenschutzerklaerung%s zu.', 'dbw-immo-suite'),
                                            '<a href="' . esc_url($privacy_url) . '" target="_blank" rel="noopener">',
                                            '</a>'
                                        );
                                    } else {
                                        esc_html_e('Ich stimme der Datenschutzerklaerung zu.', 'dbw-immo-suite');
                                    }
                                ?></span>
                            </label>

                            <!-- Hidden fields -->
                            <input type="hidden" name="property_id" value="<?php echo esc_attr($post_id); ?>">
                            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('dbw_immo_contact_nonce')); ?>">
                            <input type="text" name="website" tabindex="-1" autocomplete="off" class="dbw-honeypot" aria-hidden="true">
                        </div>

                        <!-- Submit (sticky bottom) -->
                        <div class="dbw-modal__submit">
                            <button type="submit" class="dbw-btn dbw-btn--primary">
                                <?php esc_html_e('Anfrage absenden', 'dbw-immo-suite'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- ═══ SUCCESS VIEW ═══ -->
                    <div class="dbw-modal__step" data-step="success">
                        <div class="dbw-step__content">
                            <div class="dbw-success">
                                <div class="dbw-success__check" aria-hidden="true">
                                    <svg viewBox="0 0 52 52" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="26" cy="26" r="25" fill="none" stroke="#28a745" stroke-width="2"/>
                                        <path d="M14 27 l8 8 l16 -16" fill="none" stroke="#28a745" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>

                                <h3 class="dbw-success__title">
                                    <?php esc_html_e('Vielen Dank', 'dbw-immo-suite'); ?><span data-success-name>!</span>
                                </h3>

                                <p class="dbw-success__msg">
                                    <?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                        __('Ihre Anfrage ist bei uns eingegangen. Wir melden uns innerhalb von 24 Stunden bei Ihnen.', 'dbw-immo-suite'),
                                        __('Deine Anfrage ist bei uns eingegangen. Wir melden uns innerhalb von 24 Stunden bei dir.', 'dbw-immo-suite')
                                    )); ?>
                                </p>

                                <?php if ($contact_name): ?>
                                    <div class="dbw-success__agent">
                                        <p class="dbw-success__agent-hint"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                            __('Sie moechten direkt sprechen?', 'dbw-immo-suite'),
                                            __('Du moechtest direkt sprechen?', 'dbw-immo-suite')
                                        )); ?></p>
                                        <div class="dbw-success__agent-card">
                                            <?php if ($contact_img_url): ?>
                                                <img src="<?php echo esc_url($contact_img_url); ?>" alt="">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo esc_html($contact_name); ?></strong>
                                                <?php if ($contact_tel):
                                                    $phone_success = \DBW\ImmoSuite\dbw_format_phone($contact_tel);
                                                ?>
                                                    <a href="tel:<?php echo esc_attr($phone_success['tel']); ?>" class="dbw-phone-link">&#x1F4DE; <?php echo esc_html($phone_success['display']); ?></a>
                                                <?php endif; ?>
                                                <?php if ($contact_email): ?>
                                                    <a href="mailto:<?php echo esc_attr($contact_email); ?>">&#x1F4E7; <?php echo esc_html($contact_email); ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <button type="button" class="dbw-btn dbw-btn--ghost" data-close-modal>
                                    <?php esc_html_e('Schliessen', 'dbw-immo-suite'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                </div><!-- /.dbw-modal__steps -->
            </form>
        </dialog>

        <!-- Mobile sticky CTA bar -->
        <div class="dbw-sticky-cta-bar" hidden>
            <?php
            $price_kauf = get_post_meta($post_id, 'kaufpreis', true);
            $price_miete = get_post_meta($post_id, 'kaltmiete', true);
            $price_label = '';
            if ($price_kauf > 0) {
                $price_label = \DBW\ImmoSuite\dbw_format_number($price_kauf, 'preis') . ' &euro;';
            } elseif ($price_miete > 0) {
                $price_label = \DBW\ImmoSuite\dbw_format_number($price_miete, 'preis') . ' &euro;/mtl.';
            }
            ?>
            <?php if ($price_label): ?>
                <span class="dbw-sticky-cta-bar__price"><?php echo $price_label; ?></span>
            <?php endif; ?>
            <button type="button"
                    class="dbw-cta dbw-cta--primary dbw-cta--compact"
                    data-dbw-open-modal="<?php echo esc_attr($post_id); ?>">
                <?php esc_html_e('Anfragen', 'dbw-immo-suite'); ?>
            </button>
        </div>
        <?php
    }
}
