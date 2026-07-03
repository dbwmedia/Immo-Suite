<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Expose request feature: dedicated button + compact modal with legal provision acknowledgment.
 * Makler can gate detailed information behind this form to collect qualified leads.
 */
class ExposeRequest
{
    public function init()
    {
        add_action('wp_footer', array($this, 'render_modal'));
        add_action('wp_ajax_dbw_immo_expose_request', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_dbw_immo_expose_request', array($this, 'handle_submission'));
    }

    /**
     * Check if the expose request feature is enabled.
     */
    public static function is_enabled()
    {
        return (bool) get_theme_mod('dbw_immo_single_show_expose_request', false);
    }

    /**
     * Get the configured provision text.
     */
    private static function get_provision_text()
    {
        $settings = get_option('dbw_immo_suite_settings');
        $text = isset($settings['expose_provision_text']) ? $settings['expose_provision_text'] : '';

        if (empty($text)) {
            $text = __('Ich nehme zur Kenntnis, dass bei Zustandekommen eines Kaufvertrages eine Maklerprovision in der im Expose genannten Hoehe anfaellt. Die Provisionshoehe entnehme ich dem Expose.', 'dbw-immo-suite');
        }

        return $text;
    }

    /**
     * Render the CTA button for the expose request (called from ContactModal::render_cta_buttons).
     */
    public static function render_cta_button($post_id)
    {
        if (!self::is_enabled()) {
            return;
        }
        ?>
        <button type="button"
                class="dbw-cta dbw-cta--outline"
                data-dbw-open-expose="<?php echo esc_attr($post_id); ?>">
            <svg class="dbw-cta__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span class="dbw-cta__text"><?php esc_html_e('Expose anfordern', 'dbw-immo-suite'); ?></span>
        </button>
        <?php
    }

    /**
     * Render the expose request modal dialog in wp_footer.
     */
    public function render_modal()
    {
        if (!is_singular('immobilie') || !self::is_enabled()) {
            return;
        }

        global $post;
        $post_id = $post->ID;
        $thumb   = get_the_post_thumbnail_url($post_id, 'thumbnail');
        $title   = get_the_title($post_id);
        $provision_text = self::get_provision_text();
        ?>
        <dialog id="dbw-expose-modal" class="dbw-modal dbw-modal--compact" aria-labelledby="dbw-expose-title">
            <form id="dbw-expose-form" class="dbw-modal__form">

                <!-- Header -->
                <header class="dbw-modal__header">
                    <?php if ($thumb): ?>
                        <img class="dbw-modal__thumb" src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy">
                    <?php endif; ?>
                    <div class="dbw-modal__header-text">
                        <p class="dbw-modal__eyebrow"><?php esc_html_e('Expose anfordern', 'dbw-immo-suite'); ?></p>
                        <h2 id="dbw-expose-title" class="dbw-modal__title"><?php echo esc_html($title); ?></h2>
                    </div>
                    <button type="button" class="dbw-modal__close" data-close-expose aria-label="<?php esc_attr_e('Schliessen', 'dbw-immo-suite'); ?>">&times;</button>
                </header>

                <!-- Form body -->
                <div class="dbw-modal__body" data-expose-step="form">
                    <p class="dbw-expose__intro"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                        __('Hinterlassen Sie Ihre Kontaktdaten und wir senden Ihnen das ausfuehrliche Expose zu.', 'dbw-immo-suite'),
                        __('Hinterlasse deine Kontaktdaten und wir senden dir das ausfuehrliche Expose zu.', 'dbw-immo-suite')
                    )); ?></p>

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

                    <!-- Provision acknowledgment (legal requirement) -->
                    <label class="dbw-legal">
                        <input type="checkbox" name="provision_ack" required>
                        <span><?php echo esc_html($provision_text); ?></span>
                    </label>

                    <!-- Privacy -->
                    <label class="dbw-privacy">
                        <input type="checkbox" name="privacy" required>
                        <span><?php
                            $privacy_url = get_privacy_policy_url();
                            if ($privacy_url) {
                                echo sprintf(
                                    __('Ich stimme der %1$sDatenschutzerklaerung%2$s zu.', 'dbw-immo-suite'),
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
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('dbw_immo_expose_nonce')); ?>">
                    <input type="text" name="website" tabindex="-1" autocomplete="off" class="dbw-honeypot" aria-hidden="true">
                </div>

                <!-- Submit -->
                <div class="dbw-modal__submit" data-expose-step="form">
                    <button type="submit" class="dbw-btn dbw-btn--primary">
                        <?php esc_html_e('Expose anfordern', 'dbw-immo-suite'); ?>
                    </button>
                </div>

                <!-- Success view -->
                <div class="dbw-modal__body" data-expose-step="success" hidden>
                    <div class="dbw-success">
                        <div class="dbw-success__check" aria-hidden="true">
                            <svg viewBox="0 0 52 52" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="26" cy="26" r="25" fill="none" stroke="#28a745" stroke-width="2"/>
                                <path d="M14 27 l8 8 l16 -16" fill="none" stroke="#28a745" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3 class="dbw-success__title">
                            <?php esc_html_e('Vielen Dank', 'dbw-immo-suite'); ?><span data-expose-name>!</span>
                        </h3>
                        <p class="dbw-success__msg">
                            <?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                                __('Ihre Expose-Anfrage ist eingegangen. Wir senden Ihnen das Expose schnellstmoeglich zu.', 'dbw-immo-suite'),
                                __('Deine Expose-Anfrage ist eingegangen. Wir senden dir das Expose schnellstmoeglich zu.', 'dbw-immo-suite')
                            )); ?>
                        </p>
                        <button type="button" class="dbw-btn dbw-btn--ghost" data-close-expose>
                            <?php esc_html_e('Schliessen', 'dbw-immo-suite'); ?>
                        </button>
                    </div>
                </div>

            </form>
        </dialog>
        <?php
    }

    /**
     * Handle AJAX expose request submission.
     */
    public function handle_submission()
    {
        if (!check_ajax_referer('dbw_immo_expose_nonce', 'nonce', false)) {
            wp_send_json_error(\DBW\ImmoSuite\dbw_anrede(
                __('Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.', 'dbw-immo-suite'),
                __('Die Sitzung ist abgelaufen. Bitte lade die Seite neu und versuch es erneut.', 'dbw-immo-suite')
            ));
        }

        // Rate limiting (keyed by IP only — email would be attacker-controlled)
        $rate_key = 'dbw_expose_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        if (get_transient($rate_key)) {
            wp_send_json_error(\DBW\ImmoSuite\dbw_anrede(
                __('Bitte warten Sie einen Moment, bevor Sie erneut absenden.', 'dbw-immo-suite'),
                __('Bitte warte einen Moment, bevor du erneut absendest.', 'dbw-immo-suite')
            ));
        }
        set_transient($rate_key, 1, 120);

        // Honeypot
        if (!empty($_POST['website'])) {
            wp_send_json_success(\DBW\ImmoSuite\dbw_anrede(
                __('Ihre Anfrage wurde erfolgreich versendet.', 'dbw-immo-suite'),
                __('Deine Anfrage wurde erfolgreich versendet.', 'dbw-immo-suite')
            ));
        }

        // Validation
        if (empty($_POST['privacy'])) {
            wp_send_json_error(__('Bitte Datenschutzerklaerung akzeptieren.', 'dbw-immo-suite'));
        }
        if (empty($_POST['provision_ack'])) {
            wp_send_json_error(__('Bitte den Provisionshinweis bestaetigen.', 'dbw-immo-suite'));
        }

        $post_id = intval($_POST['property_id'] ?? 0);
        $name    = str_replace(array("\n", "\r", "\t"), '', sanitize_text_field($_POST['name'] ?? ''));
        $email   = sanitize_email($_POST['email'] ?? '');
        $phone   = sanitize_text_field($_POST['phone'] ?? '');

        if (!$post_id || !$name || !$email) {
            wp_send_json_error(__('Bitte alle Pflichtfelder ausfuellen.', 'dbw-immo-suite'));
        }

        $property = get_post($post_id);
        if (!$property || $property->post_type !== 'immobilie' || $property->post_status !== 'publish') {
            wp_send_json_error(__('Immobilie nicht gefunden.', 'dbw-immo-suite'));
        }

        if (!is_email($email)) {
            wp_send_json_error(__('Bitte eine gueltige E-Mail-Adresse eingeben.', 'dbw-immo-suite'));
        }

        $property_title = get_the_title($post_id);
        $property_url   = get_permalink($post_id);

        // Build email body
        $body  = "Neue Expose-Anfrage ueber die Website:\n\n";
        $body .= "Immobilie: " . $property_title . "\n";
        $body .= "Link: " . $property_url . "\n\n";
        $body .= "INTENT: EXPOSE-ANFRAGE\n\n";
        $body .= "Name:      " . $name . "\n";
        $body .= "E-Mail:    " . $email . "\n";
        $body .= "Telefon:   " . ($phone ?: '-') . "\n\n";
        $body .= "Provisionshinweis akzeptiert: Ja\n";

        // Recipient
        $contact_email = get_post_meta($post_id, 'kontaktperson_email', true);
        $to = is_email($contact_email) ? $contact_email : get_option('admin_email');

        $subject = sprintf('[EXPOSE] Anfrage: %s', $property_title);

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: "' . str_replace('"', '', $name) . '" <' . $email . '>',
        );

        // Optional CC
        $settings = get_option('dbw_immo_suite_settings');
        $cc_email = isset($settings['contact_cc_email']) ? sanitize_email($settings['contact_cc_email']) : '';
        if ($cc_email && is_email($cc_email) && $cc_email !== $to) {
            $headers[] = 'Cc: ' . $cc_email;
        }

        $sent = wp_mail($to, $subject, $body, $headers);

        if ($sent) {
            wp_send_json_success(\DBW\ImmoSuite\dbw_anrede(
                __('Ihre Expose-Anfrage wurde erfolgreich versendet.', 'dbw-immo-suite'),
                __('Deine Expose-Anfrage wurde erfolgreich versendet.', 'dbw-immo-suite')
            ));
        } else {
            wp_send_json_error(\DBW\ImmoSuite\dbw_anrede(
                __('Beim Versand ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.', 'dbw-immo-suite'),
                __('Beim Versand ist ein Fehler aufgetreten. Bitte versuch es erneut.', 'dbw-immo-suite')
            ));
        }
    }
}
