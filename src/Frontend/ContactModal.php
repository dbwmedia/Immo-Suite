<?php

namespace DBW\ImmoSuite\Frontend;

/**
 * Single-step contact modal with intent-based lead qualification.
 * Renders CTA buttons in sidebar + native <dialog> modal in wp_footer.
 */
class ContactModal
{
    public function init()
    {
        add_action('wp_footer', array($this, 'render_modal'));
    }

    /**
     * Render CTA buttons for the sidebar (called from template).
     *
     * @param int $post_id Property post ID.
     */
    public static function render_cta_buttons($post_id)
    {
        $contact_tel = get_post_meta($post_id, 'kontaktperson_tel', true);
        ?>
        <div class="dbw-cta-stack">
            <button type="button"
                    class="dbw-cta dbw-cta--primary"
                    data-dbw-open-modal="<?php echo esc_attr($post_id); ?>">
                <span class="dbw-cta__icon" aria-hidden="true">&#x1F4C5;</span>
                <span class="dbw-cta__text"><?php esc_html_e('Immobilie anfragen', 'dbw-immo-suite'); ?></span>
            </button>
            <?php if ($contact_tel): ?>
                <a href="tel:<?php echo esc_attr(preg_replace('/[^+0-9]/', '', $contact_tel)); ?>"
                   class="dbw-cta-phone">
                    <?php esc_html_e('oder direkt anrufen', 'dbw-immo-suite'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the modal dialog in wp_footer (once per page).
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

        // Quick facts for header
        $area  = (float) get_post_meta($post_id, 'wohnflaeche', true);
        $rooms = (float) get_post_meta($post_id, 'anzahl_zimmer', true);
        $price = (float) get_post_meta($post_id, 'kaufpreis', true) ?: (float) get_post_meta($post_id, 'kaltmiete', true);

        // Contact person data for success view
        $contact_name    = trim(get_post_meta($post_id, 'kontaktperson_vorname', true) . ' ' . get_post_meta($post_id, 'kontaktperson_name', true));
        $contact_tel     = get_post_meta($post_id, 'kontaktperson_tel', true);
        $contact_email   = get_post_meta($post_id, 'kontaktperson_email', true);
        $contact_img_url = get_post_meta($post_id, 'kontaktperson_bild_url', true);
        ?>
        <dialog id="dbw-contact-modal" class="dbw-modal" aria-labelledby="dbw-modal-title">
            <form id="dbw-contact-form" class="dbw-modal__form" data-property-id="<?php echo esc_attr($post_id); ?>">

                <!-- Header -->
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
                                if ($area) $parts[]  = number_format_i18n($area, 0) . ' m&sup2;';
                                if ($rooms) $parts[] = $rooms . ' Zi.';
                                if ($price) $parts[] = number_format_i18n($price, 0) . ' &euro;';
                                echo implode(' &middot; ', $parts);
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="dbw-modal__close" aria-label="<?php esc_attr_e('Schliessen', 'dbw-immo-suite'); ?>">&times;</button>
                </header>

                <!-- Form View -->
                <div class="dbw-modal__body" data-view="form">

                    <!-- Intent -->
                    <fieldset class="dbw-field dbw-field--intent">
                        <legend><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                            __('Wie koennen wir Ihnen helfen?', 'dbw-immo-suite'),
                            __('Wie koennen wir dir helfen?', 'dbw-immo-suite')
                        )); ?></legend>
                        <div class="dbw-intent-grid">
                            <label class="dbw-intent">
                                <input type="radio" name="intent" value="besichtigung" required>
                                <span class="dbw-intent__icon" aria-hidden="true">&#x1F4C5;</span>
                                <span class="dbw-intent__label"><?php esc_html_e('Besichtigung', 'dbw-immo-suite'); ?></span>
                            </label>
                            <label class="dbw-intent">
                                <input type="radio" name="intent" value="info">
                                <span class="dbw-intent__icon" aria-hidden="true">&#x1F4DD;</span>
                                <span class="dbw-intent__label"><?php esc_html_e('Mehr Infos', 'dbw-immo-suite'); ?></span>
                            </label>
                            <label class="dbw-intent">
                                <input type="radio" name="intent" value="preis">
                                <span class="dbw-intent__icon" aria-hidden="true">&#x1F4B0;</span>
                                <span class="dbw-intent__label"><?php echo 'Preis &amp; Finanzierung'; ?></span>
                            </label>
                            <label class="dbw-intent">
                                <input type="radio" name="intent" value="rueckruf">
                                <span class="dbw-intent__icon" aria-hidden="true">&#x1F4DE;</span>
                                <span class="dbw-intent__label"><?php esc_html_e('Rueckruf', 'dbw-immo-suite'); ?></span>
                            </label>
                        </div>
                    </fieldset>

                    <!-- Contact details -->
                    <fieldset class="dbw-field">
                        <legend><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                            __('Ihre Kontaktdaten', 'dbw-immo-suite'),
                            __('Deine Kontaktdaten', 'dbw-immo-suite')
                        )); ?></legend>
                        <label class="dbw-field__label">
                            <span><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                __('Ihr Name', 'dbw-immo-suite'),
                                __('Dein Name', 'dbw-immo-suite')
                            )); ?> *</span>
                            <input type="text" name="name" required autocomplete="name">
                        </label>
                        <label class="dbw-field__label">
                            <span><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                __('Ihre E-Mail', 'dbw-immo-suite'),
                                __('Deine E-Mail', 'dbw-immo-suite')
                            )); ?> *</span>
                            <input type="email" name="email" required autocomplete="email">
                        </label>
                        <label class="dbw-field__label">
                            <span><?php esc_html_e('Telefon (optional)', 'dbw-immo-suite'); ?></span>
                            <input type="tel" name="phone" autocomplete="tel">
                        </label>
                    </fieldset>

                    <!-- Context field: Besichtigung -->
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

                    <!-- Context field: Rueckruf -->
                    <fieldset class="dbw-field dbw-field--context" data-context="rueckruf" hidden>
                        <legend><?php esc_html_e('Wann sollen wir anrufen?', 'dbw-immo-suite'); ?></legend>
                        <select name="callback_time">
                            <option value="morning"><?php esc_html_e('Vormittag (9-12 Uhr)', 'dbw-immo-suite'); ?></option>
                            <option value="afternoon"><?php esc_html_e('Nachmittag (12-17 Uhr)', 'dbw-immo-suite'); ?></option>
                            <option value="evening"><?php esc_html_e('Abend (17-19 Uhr)', 'dbw-immo-suite'); ?></option>
                        </select>
                    </fieldset>

                    <!-- Context field: Preis -->
                    <fieldset class="dbw-field dbw-field--context" data-context="preis" hidden>
                        <legend><?php esc_html_e('Zur Finanzierung', 'dbw-immo-suite'); ?></legend>
                        <label class="dbw-inline-radio">
                            <input type="radio" name="financing" value="yes"> <?php esc_html_e('Geklaert', 'dbw-immo-suite'); ?>
                        </label>
                        <label class="dbw-inline-radio">
                            <input type="radio" name="financing" value="partial"> <?php esc_html_e('Teilweise', 'dbw-immo-suite'); ?>
                        </label>
                        <label class="dbw-inline-radio">
                            <input type="radio" name="financing" value="no"> <?php esc_html_e('Noch nicht', 'dbw-immo-suite'); ?>
                        </label>
                    </fieldset>

                    <!-- Message -->
                    <label class="dbw-field dbw-field__label">
                        <span><?php esc_html_e('Nachricht (optional)', 'dbw-immo-suite'); ?></span>
                        <textarea name="message" rows="3" placeholder="<?php echo esc_attr(\DBW\ImmoSuite\dbw_anrede(
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

                    <!-- Honeypot + Hidden -->
                    <input type="hidden" name="property_id" value="<?php echo esc_attr($post_id); ?>">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('dbw_immo_contact_nonce')); ?>">
                    <input type="text" name="website" tabindex="-1" autocomplete="off" class="dbw-honeypot" aria-hidden="true">

                    <!-- Submit -->
                    <div class="dbw-modal__submit">
                        <button type="submit" class="dbw-btn dbw-btn--primary">
                            <?php esc_html_e('Anfrage absenden', 'dbw-immo-suite'); ?>
                        </button>
                        <?php if ($contact_tel): ?>
                            <a href="tel:<?php echo esc_attr(preg_replace('/[^+0-9]/', '', $contact_tel)); ?>" class="dbw-phone-fallback">
                                <?php esc_html_e('oder direkt anrufen', 'dbw-immo-suite'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Success View (hidden initially) -->
                <div class="dbw-modal__body" data-view="success" hidden>
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
                                        <?php if ($contact_tel): ?>
                                            <a href="tel:<?php echo esc_attr(preg_replace('/[^+0-9]/', '', $contact_tel)); ?>">&#x1F4DE; <?php echo esc_html($contact_tel); ?></a>
                                        <?php endif; ?>
                                        <?php if ($contact_email): ?>
                                            <a href="mailto:<?php echo esc_attr($contact_email); ?>">&#x1F4E7; <?php echo esc_html($contact_email); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="dbw-success__next">
                            <p class="dbw-success__next-title"><?php esc_html_e('Was passiert jetzt?', 'dbw-immo-suite'); ?></p>
                            <ol class="dbw-success__steps">
                                <li><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                    __('Sie erhalten in den naechsten Minuten eine Bestaetigungs-E-Mail.', 'dbw-immo-suite'),
                                    __('Du erhaeltst in den naechsten Minuten eine Bestaetigungs-E-Mail.', 'dbw-immo-suite')
                                )); ?></li>
                                <li><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                    __('Wir pruefen Ihre Anfrage persoenlich.', 'dbw-immo-suite'),
                                    __('Wir pruefen deine Anfrage persoenlich.', 'dbw-immo-suite')
                                )); ?></li>
                                <li><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                    __('Spaetestens am naechsten Werktag melden wir uns bei Ihnen.', 'dbw-immo-suite'),
                                    __('Spaetestens am naechsten Werktag melden wir uns bei dir.', 'dbw-immo-suite')
                                )); ?></li>
                            </ol>
                        </div>

                        <button type="button" class="dbw-btn dbw-btn--ghost" data-close-modal>
                            <?php esc_html_e('Schliessen', 'dbw-immo-suite'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </dialog>

        <!-- Mobile sticky CTA bar -->
        <div class="dbw-sticky-cta-bar" hidden>
            <?php
            $price_kauf = get_post_meta($post_id, 'kaufpreis', true);
            $price_miete = get_post_meta($post_id, 'kaltmiete', true);
            $price_label = '';
            if ($price_kauf > 0) {
                $price_label = number_format_i18n((float) $price_kauf, 0) . ' &euro;';
            } elseif ($price_miete > 0) {
                $price_label = number_format_i18n((float) $price_miete, 0) . ' &euro;/mtl.';
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
