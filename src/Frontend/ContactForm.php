<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Handles AJAX contact form submissions (both legacy inline and new multi-step modal).
 */
class ContactForm
{

    public function init()
    {
        add_action('wp_ajax_dbw_immo_contact', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_dbw_immo_contact', array($this, 'handle_submission'));
        add_action('wp_ajax_dbw_immo_get_nonce', array($this, 'ajax_get_nonce'));
        add_action('wp_ajax_nopriv_dbw_immo_get_nonce', array($this, 'ajax_get_nonce'));
    }

    /**
     * Return fresh form nonces. Pages are often served from a page cache for
     * longer than a nonce lives (12-24h) — the modals fetch a fresh nonce on
     * open so submissions keep working on cached pages.
     */
    public function ajax_get_nonce()
    {
        wp_send_json_success(array(
            'contact' => wp_create_nonce('dbw_immo_contact_nonce'),
            'expose'  => wp_create_nonce('dbw_immo_expose_nonce'),
        ));
    }

    /* ---------------------------------------------------------------------
     * Rate limiting (shared with ExposeRequest)
     *
     * Two layers instead of one blunt per-IP block:
     * 1. Per IP AND property: 120s. Blocks double-submits, but a visitor
     *    who inquires about several different objects in a row is a GOOD
     *    lead, not a spammer.
     * 2. Per IP across everything: max 10 submissions per hour. Catches
     *    bots that spray the whole portfolio.
     * ------------------------------------------------------------------ */

    const RATE_HOURLY_MAX = 10;

    /**
     * @return bool True if the request may proceed.
     */
    public static function rate_limit_ok($prefix, $post_id)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (get_transient('dbw_' . $prefix . '_' . md5($ip . '|' . $post_id))) {
            return false;
        }

        if ((int) get_transient('dbw_rl_hour_' . md5($ip)) >= self::RATE_HOURLY_MAX) {
            return false;
        }

        return true;
    }

    /**
     * Record a processed submission (called after successful handling).
     */
    public static function rate_limit_hit($prefix, $post_id)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        set_transient('dbw_' . $prefix . '_' . md5($ip . '|' . $post_id), 1, 120);

        $hour_key = 'dbw_rl_hour_' . md5($ip);
        $count = (int) get_transient($hour_key);
        set_transient($hour_key, $count + 1, HOUR_IN_SECONDS);
    }

    /* ---------------------------------------------------------------------
     * Confirmation mail to the visitor (shared with ExposeRequest)
     * ------------------------------------------------------------------ */

    public static function confirmation_enabled()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        return !isset($settings['confirmation_email_enabled']) || (int) $settings['confirmation_email_enabled'] === 1;
    }

    /**
     * Friendly confirmation to the person who submitted the form.
     * Reply-To is the property's contact person, so a simple reply reaches
     * the broker even when the site sends via a no-reply SMTP address.
     */
    public static function send_visitor_confirmation($post_id, $name, $email)
    {
        if (!self::confirmation_enabled() || !is_email($email)) {
            return;
        }

        $settings = get_option('dbw_immo_suite_settings', array());
        $firm = !empty($settings['org_name']) ? $settings['org_name'] : get_bloginfo('name');

        $property_title = get_the_title($post_id);
        $property_url   = get_permalink($post_id);

        $kp_name  = trim(get_post_meta($post_id, 'kontaktperson_vorname', true) . ' ' . get_post_meta($post_id, 'kontaktperson_name', true));
        $kp_tel   = get_post_meta($post_id, 'kontaktperson_tel', true);
        $kp_email = get_post_meta($post_id, 'kontaktperson_email', true);
        if (!is_email($kp_email)) {
            $kp_email = get_option('admin_email');
        }

        $subject = sprintf(__('Ihre Anfrage: %s', 'dbw-immo-suite'), $property_title);
        $subject = \DBW\ImmoSuite\dbw_anrede($subject, sprintf(__('Deine Anfrage: %s', 'dbw-immo-suite'), $property_title));

        $greeting = \DBW\ImmoSuite\dbw_anrede(
            sprintf(__('Guten Tag %s,', 'dbw-immo-suite'), $name),
            sprintf(__('Hallo %s,', 'dbw-immo-suite'), $name)
        );
        $thanks = \DBW\ImmoSuite\dbw_anrede(
            __('vielen Dank fuer Ihre Anfrage zu folgendem Objekt:', 'dbw-immo-suite'),
            __('vielen Dank fuer deine Anfrage zu folgendem Objekt:', 'dbw-immo-suite')
        );
        $followup = \DBW\ImmoSuite\dbw_anrede(
            __('Wir haben Ihre Nachricht erhalten und melden uns schnellstmoeglich bei Ihnen.', 'dbw-immo-suite'),
            __('Wir haben deine Nachricht erhalten und melden uns schnellstmoeglich bei dir.', 'dbw-immo-suite')
        );

        $body  = $greeting . "\n\n";
        $body .= $thanks . "\n\n";
        $body .= $property_title . "\n";
        $body .= $property_url . "\n\n";
        $body .= $followup . "\n\n";

        if ($kp_name || $kp_tel) {
            $body .= __('Ihr direkter Ansprechpartner:', 'dbw-immo-suite') . "\n";
            if ($kp_name) {
                $body .= $kp_name . "\n";
            }
            if ($kp_tel) {
                $body .= __('Telefon:', 'dbw-immo-suite') . ' ' . $kp_tel . "\n";
            }
            $body .= __('E-Mail:', 'dbw-immo-suite') . ' ' . $kp_email . "\n\n";
        }

        $body .= __('Viele Gruesse', 'dbw-immo-suite') . "\n";
        $body .= $firm . "\n";

        // Reply-To: the broker, not the (possibly no-reply) sender address
        $reply_name = $kp_name ?: $firm;
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: "' . str_replace('"', '', $reply_name) . '" <' . $kp_email . '>',
        );

        wp_mail($email, $subject, $body, $headers);
    }

    /**
     * Handle AJAX form submission with intent-based lead qualification.
     */
    public function handle_submission()
    {
        if (!check_ajax_referer('dbw_immo_contact_nonce', 'nonce', false)) {
            wp_send_json_error(\DBW\ImmoSuite\dbw_anrede(
                __('Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.', 'dbw-immo-suite'),
                __('Die Sitzung ist abgelaufen. Bitte lade die Seite neu und versuch es erneut.', 'dbw-immo-suite')
            ));
        }

        // Honeypot check — silently succeed to not reveal detection
        if (!empty($_POST['website'])) {
            wp_send_json_success(\DBW\ImmoSuite\dbw_anrede(
                __('Ihre Anfrage wurde erfolgreich versendet.', 'dbw-immo-suite'),
                __('Deine Anfrage wurde erfolgreich versendet.', 'dbw-immo-suite')
            ));
        }

        // Privacy consent check
        if (empty($_POST['privacy'])) {
            wp_send_json_error(__('Bitte Datenschutzerklaerung akzeptieren.', 'dbw-immo-suite'));
        }

        $post_id   = intval($_POST['property_id'] ?? 0);
        $name      = str_replace(array("\n", "\r", "\t"), '', sanitize_text_field($_POST['name'] ?? ''));
        $email     = sanitize_email($_POST['email'] ?? '');
        $phone     = sanitize_text_field($_POST['phone'] ?? '');
        $message   = sanitize_textarea_field($_POST['message'] ?? '');
        $intent    = sanitize_key($_POST['intent'] ?? '');
        $preferred = sanitize_key($_POST['preferred'] ?? 'email');

        if (!$post_id || !$name || !$email) {
            wp_send_json_error(__('Bitte alle Pflichtfelder ausfuellen.', 'dbw-immo-suite'));
        }

        // Verify property exists and is public (blocks enumeration of drafts/private posts)
        $property = get_post($post_id);
        if (!$property || $property->post_type !== 'immobilie' || $property->post_status !== 'publish') {
            wp_send_json_error(__('Immobilie nicht gefunden.', 'dbw-immo-suite'));
        }

        if (!is_email($email)) {
            wp_send_json_error(__('Bitte eine gueltige E-Mail-Adresse eingeben.', 'dbw-immo-suite'));
        }

        // Rate limiting: per IP+property (120s) + hourly per-IP cap.
        // Inquiring about several DIFFERENT objects in a row stays possible.
        if (!self::rate_limit_ok('contact', $post_id)) {
            wp_send_json_error(\DBW\ImmoSuite\dbw_anrede(
                __('Bitte warten Sie einen Moment, bevor Sie erneut absenden.', 'dbw-immo-suite'),
                __('Bitte warte einen Moment, bevor du erneut absendest.', 'dbw-immo-suite')
            ));
        }

        $property_title = get_the_title($post_id);
        $property_url   = get_permalink($post_id);

        // Build intent-specific data
        $intent_lines = array();
        $intent_labels = array(
            'besichtigung' => 'BESICHTIGUNG',
            'info'         => 'MEHR INFOS',
            'preis'        => 'PREIS/FINANZIERUNG',
            'rueckruf'     => 'RUECKRUF',
        );

        if ($intent === 'besichtigung') {
            $date = sanitize_text_field($_POST['appointment_date'] ?? '');
            $time = sanitize_key($_POST['appointment_time'] ?? '');
            $time_labels = array('morning' => 'Vormittag', 'afternoon' => 'Nachmittag', 'evening' => 'Abend');
            if ($date) {
                $intent_lines[] = 'Wunschtermin: ' . $date;
            }
            if (isset($time_labels[$time])) {
                $intent_lines[] = 'Tageszeit: ' . $time_labels[$time];
            }
        } elseif ($intent === 'info') {
            $needs = array_map('sanitize_key', $_POST['needs'] ?? array());
            if (!empty($needs)) {
                $intent_lines[] = 'Benoetigt: ' . implode(', ', $needs);
            }
        } elseif ($intent === 'preis') {
            $financing = sanitize_key($_POST['financing'] ?? '');
            $fin_labels = array('yes' => 'Ja', 'no' => 'Nein', 'partial' => 'Teilweise');
            if (isset($fin_labels[$financing])) {
                $intent_lines[] = 'Finanzierung geklaert: ' . $fin_labels[$financing];
            }
        } elseif ($intent === 'rueckruf') {
            $callback = sanitize_key($_POST['callback_time'] ?? '');
            $cb_labels = array('morning' => 'Vormittag', 'afternoon' => 'Nachmittag', 'evening' => 'Abend');
            if (isset($cb_labels[$callback])) {
                $intent_lines[] = 'Rueckruf-Zeitpunkt: ' . $cb_labels[$callback];
            }
        }

        // Build email body
        $body  = "Neue Anfrage ueber die Website:\n\n";
        $body .= "Immobilie: " . $property_title . "\n";
        $body .= "Link: " . $property_url . "\n\n";

        if ($intent && isset($intent_labels[$intent])) {
            $body .= "INTENT: " . $intent_labels[$intent] . "\n\n";
        }

        $body .= "Name:      " . $name . "\n";
        $body .= "E-Mail:    " . $email . "\n";
        $body .= "Telefon:   " . ($phone ?: '-') . "\n";

        if ($preferred) {
            $pref_labels = array('email' => 'E-Mail', 'phone' => 'Telefon', 'whatsapp' => 'WhatsApp');
            $body .= "Bevorzugt: " . ($pref_labels[$preferred] ?? $preferred) . "\n";
        }

        if (!empty($intent_lines)) {
            $body .= "\n" . implode("\n", $intent_lines) . "\n";
        }

        if ($message) {
            $body .= "\nNachricht:\n" . $message . "\n";
        }

        // Consent record (Art. 7 Abs. 1 DSGVO): the mail in the broker's inbox
        // doubles as proof of the privacy-checkbox consent
        $body .= "\nDatenschutzerklaerung akzeptiert: Ja (" . wp_date('d.m.Y H:i') . " Uhr, Kontaktformular Objektseite)\n";

        // Store in the inbox first — a failed/spam-filtered mail must not lose the lead
        if (class_exists('DBW\ImmoSuite\PostTypes\Inquiry')) {
            \DBW\ImmoSuite\PostTypes\Inquiry::save(array(
                'name'           => $name,
                'email'          => $email,
                'phone'          => $phone,
                'message'        => $message,
                'intent'         => $intent,
                'intent_details' => implode("\n", $intent_lines),
                'property_id'    => $post_id,
                'source'         => 'kontakt',
                'preferred'      => $preferred,
            ));
        }

        // Determine recipient
        $contact_email = get_post_meta($post_id, 'kontaktperson_email', true);
        $to = is_email($contact_email) ? $contact_email : get_option('admin_email');

        // Subject with intent prefix
        if ($intent && isset($intent_labels[$intent])) {
            $subject = sprintf('[%s] Anfrage: %s', $intent_labels[$intent], $property_title);
        } else {
            $subject = sprintf(__('Anfrage: %s', 'dbw-immo-suite'), $property_title);
        }

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: "' . str_replace('"', '', $name) . '" <' . $email . '>',
        );

        // Optional CC from settings
        $settings = get_option('dbw_immo_suite_settings');
        $cc_email = isset($settings['contact_cc_email']) ? sanitize_email($settings['contact_cc_email']) : '';
        if ($cc_email && is_email($cc_email) && $cc_email !== $to) {
            $headers[] = 'Cc: ' . $cc_email;
        }

        $sent = wp_mail($to, $subject, $body, $headers);

        // Set rate limit after successful processing (even if mail fails)
        self::rate_limit_hit('contact', $post_id);

        if ($sent) {
            self::send_visitor_confirmation($post_id, $name, $email);

            wp_send_json_success(\DBW\ImmoSuite\dbw_anrede(
                __('Ihre Anfrage wurde erfolgreich versendet. Wir melden uns bei Ihnen.', 'dbw-immo-suite'),
                __('Deine Anfrage wurde erfolgreich versendet. Wir melden uns bei dir.', 'dbw-immo-suite')
            ));
        } else {
            wp_send_json_error(\DBW\ImmoSuite\dbw_anrede(
                __('Beim Versand ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.', 'dbw-immo-suite'),
                __('Beim Versand ist ein Fehler aufgetreten. Bitte versuch es erneut.', 'dbw-immo-suite')
            ));
        }
    }
}
