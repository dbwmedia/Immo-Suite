<?php

namespace DBW\ImmoSuite\Frontend;

/**
 * Handles AJAX contact form submissions (both legacy inline and new multi-step modal).
 */
class ContactForm
{

    public function init()
    {
        add_action('wp_ajax_dbw_immo_contact', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_dbw_immo_contact', array($this, 'handle_submission'));
    }

    /**
     * Handle AJAX form submission with intent-based lead qualification.
     */
    public function handle_submission()
    {
        check_ajax_referer('dbw_immo_contact_nonce', 'nonce');

        // Rate limiting — 1 submission per email per 2 minutes
        $rate_key = 'dbw_contact_' . md5(sanitize_email($_POST['email'] ?? '') . $_SERVER['REMOTE_ADDR']);
        if (get_transient($rate_key)) {
            wp_send_json_error(\DBW\ImmoSuite\dbw_anrede(
                __('Bitte warten Sie einen Moment, bevor Sie erneut absenden.', 'dbw-immo-suite'),
                __('Bitte warte einen Moment, bevor du erneut absendest.', 'dbw-immo-suite')
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
        $name      = sanitize_text_field($_POST['name'] ?? '');
        $email     = sanitize_email($_POST['email'] ?? '');
        $phone     = sanitize_text_field($_POST['phone'] ?? '');
        $message   = sanitize_textarea_field($_POST['message'] ?? '');
        $intent    = sanitize_key($_POST['intent'] ?? '');
        $preferred = sanitize_key($_POST['preferred'] ?? 'email');

        if (!$post_id || !$name || !$email) {
            wp_send_json_error(__('Bitte alle Pflichtfelder ausfuellen.', 'dbw-immo-suite'));
        }

        if (!is_email($email)) {
            wp_send_json_error(__('Bitte eine gueltige E-Mail-Adresse eingeben.', 'dbw-immo-suite'));
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
            'Reply-To: ' . $name . ' <' . $email . '>',
        );

        $sent = wp_mail($to, $subject, $body, $headers);

        // Set rate limit after successful processing (even if mail fails)
        set_transient($rate_key, 1, 120);

        if ($sent) {
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
