<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Single source of truth for every legal text the plugin shows to visitors.
 *
 * Consent wording, the commission acknowledgment and the map consent notice
 * used to live inline in three templates. A lawyer asking for one changed
 * sentence meant hunting through markup and risking that one spot got missed.
 * Everything visitor-facing and legally relevant now comes from here, with a
 * filter per text so a site can adjust the wording without touching the plugin.
 *
 * Consent wording follows the review by Kontentiert Legal (02.09.2026):
 * purpose named, privacy policy linked, explicit declaration of consent.
 */
class Legal
{
    /**
     * URL of the privacy policy.
     *
     * Own setting first (some sites keep the page outside WordPress), then the
     * page assigned under Settings > Privacy.
     */
    public static function privacy_url()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        $url = isset($settings['privacy_url']) ? trim((string) $settings['privacy_url']) : '';

        if ($url === '') {
            $url = (string) get_privacy_policy_url();
        }

        return esc_url_raw(apply_filters('dbw_immo_legal_privacy_url', $url));
    }

    /**
     * Consent wording without markup — used for the email trail and the
     * stored proof of consent (Art. 7 (1) GDPR: what exactly was agreed to).
     *
     * @param string $context 'contact' or 'expose'
     */
    public static function consent_text($context = 'contact')
    {
        if ($context === 'expose') {
            $text = __('Ich bin mit der Verarbeitung meiner personenbezogenen Daten zum Zwecke der Zusendung des Exposés und der Kontaktaufnahme entsprechend der Datenschutzerklärung ausdrücklich einverstanden.', 'dbw-immo-suite');
        } else {
            $text = __('Ich bin mit der Verarbeitung meiner personenbezogenen Daten zum Zwecke der Kontaktaufnahme entsprechend der Datenschutzerklärung ausdrücklich einverstanden.', 'dbw-immo-suite');
        }

        return (string) apply_filters('dbw_immo_legal_consent_text', $text, $context);
    }

    /**
     * Consent wording as HTML with the privacy policy linked.
     *
     * The link is the part the lawyer insisted on, so it is built here rather
     * than left to each template. Without a configured privacy page the plain
     * wording is shown and the admin gets a notice (see maybe_admin_notice()).
     *
     * @param string $context 'contact' or 'expose'
     */
    public static function consent_html($context = 'contact')
    {
        $text = self::consent_text($context);
        $url  = self::privacy_url();

        if ($url !== '') {
            $link = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">'
                . esc_html__('Datenschutzerklärung', 'dbw-immo-suite') . '</a>';

            // Replace the plain word with the linked one (first occurrence only)
            $needle = __('Datenschutzerklärung', 'dbw-immo-suite');
            $pos    = strpos($text, $needle);

            $html = $pos !== false
                ? esc_html(substr($text, 0, $pos)) . $link . esc_html(substr($text, $pos + strlen($needle)))
                : esc_html($text) . ' ' . $link;
        } else {
            $html = esc_html($text);
        }

        return (string) apply_filters('dbw_immo_legal_consent_html', $html, $context, $url);
    }

    /**
     * Render the privacy consent checkbox used by every form.
     *
     * @param string $context 'contact' or 'expose'
     */
    public static function consent_checkbox($context = 'contact')
    {
        ?>
        <label class="dbw-privacy">
            <input type="checkbox" name="privacy" required>
            <span><?php echo self::consent_html($context); // Already escaped in consent_html() ?></span>
        </label>
        <?php
    }

    /**
     * Commission acknowledgment for the expose request (site setting wins).
     */
    public static function provision_text()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        $text = isset($settings['expose_provision_text']) ? trim((string) $settings['expose_provision_text']) : '';

        if ($text === '') {
            $text = self::provision_default();
        }

        return (string) apply_filters('dbw_immo_legal_provision_text', $text);
    }

    /**
     * Default commission wording, also used as the placeholder in the settings.
     */
    public static function provision_default()
    {
        return __('Ich nehme zur Kenntnis, dass bei Zustandekommen eines Kaufvertrages eine Maklerprovision in der im Exposé genannten Höhe anfällt. Die Provisionshöhe entnehme ich dem Exposé.', 'dbw-immo-suite');
    }

    /**
     * Notice below the map placeholder (two-click solution).
     */
    public static function map_notice()
    {
        return (string) apply_filters(
            'dbw_immo_legal_map_notice',
            __('Dabei wird Ihre IP-Adresse an die OpenStreetMap Foundation übertragen.', 'dbw-immo-suite')
        );
    }

    /**
     * Prompt above the "load map" button.
     */
    public static function map_prompt()
    {
        return (string) apply_filters(
            'dbw_immo_legal_map_prompt',
            \DBW\ImmoSuite\dbw_anrede(
                __('Klicken Sie, um die Karte zu laden.', 'dbw-immo-suite'),
                __('Klicke, um die Karte zu laden.', 'dbw-immo-suite')
            )
        );
    }

    /**
     * Warn in the admin when forms run without a linked privacy policy.
     *
     * Consent without a reachable policy is the one failure mode that makes
     * every stored consent worthless, so it is worth a visible notice.
     */
    public function init()
    {
        add_action('admin_notices', array($this, 'maybe_admin_notice'));
    }

    public function maybe_admin_notice()
    {
        if (!current_user_can('manage_options') || self::privacy_url() !== '') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || strpos((string) $screen->id, 'immobilie') === false && strpos((string) $screen->id, 'dbw-') === false) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('Immo Suite: Datenschutzerklärung nicht verlinkt', 'dbw-immo-suite'); ?></strong><br>
                <?php esc_html_e('Die Kontakt- und Exposé-Formulare zeigen den Einwilligungstext ohne Link zur Datenschutzerklärung. Bitte unter Einstellungen > Datenschutz eine Datenschutzseite auswählen oder in den Immo-Suite-Einstellungen eine URL hinterlegen.', 'dbw-immo-suite'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('options-privacy.php')); ?>" class="button"><?php esc_html_e('Datenschutzseite wählen', 'dbw-immo-suite'); ?></a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=immobilie&page=dbw-immo-suite-settings#tab-privacy')); ?>" class="button"><?php esc_html_e('Immo-Suite-Einstellungen', 'dbw-immo-suite'); ?></a>
            </p>
        </div>
        <?php
    }
}
