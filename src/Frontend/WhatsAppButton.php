<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

class WhatsAppButton
{
    public function init()
    {
        add_action('wp_footer', array($this, 'render_floating_button'));
    }

    /**
     * Build the WhatsApp URL for a given property.
     *
     * @param int $post_id
     * @return string|false URL or false if no number available.
     */
    public static function get_whatsapp_url($post_id)
    {
        $settings = get_option('dbw_immo_suite_settings', array());

        if (empty($settings['whatsapp_enabled'])) {
            return false;
        }

        // Determine phone number: override > property contact
        $tel = '';
        if (!empty($settings['whatsapp_number_override'])) {
            $tel = $settings['whatsapp_number_override'];
        } else {
            $tel = get_post_meta($post_id, 'kontaktperson_tel', true);
        }

        if (empty($tel)) {
            return false;
        }

        // Normalize: strip everything except digits
        $number = preg_replace('/[^0-9]/', '', $tel);

        // Build message
        $contact_firstname = get_post_meta($post_id, 'kontaktperson_vorname', true);
        $title = get_the_title($post_id);
        $url = get_permalink($post_id);

        $template = !empty($settings['whatsapp_message_template'])
            ? $settings['whatsapp_message_template']
            : self::get_default_message_template();

        $message = str_replace(
            array('{ansprechpartner}', '{titel}', '{url}', '{name}'),
            array($contact_firstname, $title, $url, $contact_firstname),
            $template
        );

        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }

    /**
     * Get the default message template respecting Du/Sie.
     */
    public static function get_default_message_template()
    {
        return \DBW\ImmoSuite\dbw_anrede(
            "Hallo {ansprechpartner},\n\nich interessiere mich fuer diese Immobilie:\n{titel}\n{url}\n\nKoennten Sie mir weitere Informationen zukommen lassen?",
            "Hallo {ansprechpartner},\n\nich interessiere mich fuer diese Immobilie:\n{titel}\n{url}\n\nKoenntest du mir weitere Informationen zukommen lassen?"
        );
    }

    /**
     * Get the CTA button text from settings.
     */
    public static function get_cta_text()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        return !empty($settings['whatsapp_cta_text'])
            ? $settings['whatsapp_cta_text']
            : __('Per WhatsApp anfragen', 'dbw-immo-suite');
    }

    /**
     * Render the WhatsApp CTA button for the sidebar stack.
     * Called from ContactModal::render_cta_buttons().
     */
    public static function render_cta_button($post_id)
    {
        if (!get_theme_mod('dbw_immo_single_show_whatsapp', true)) {
            return;
        }

        $url = self::get_whatsapp_url($post_id);
        if (!$url) {
            return;
        }
        ?>
        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="dbw-cta dbw-cta--whatsapp">
            <svg class="dbw-cta__icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.025.504 3.94 1.396 5.617L.052 23.7a.5.5 0 00.606.607l5.985-1.321A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-1.875 0-3.654-.509-5.197-1.396l-.372-.22-3.857.852.874-3.793-.242-.384A9.71 9.71 0 012.25 12 9.75 9.75 0 0112 2.25 9.75 9.75 0 0121.75 12 9.75 9.75 0 0112 21.75z"/>
            </svg>
            <span class="dbw-cta__text"><?php echo esc_html(self::get_cta_text()); ?></span>
        </a>
        <?php
    }

    /**
     * Render the floating WhatsApp button in wp_footer.
     */
    public function render_floating_button()
    {
        if (!is_singular('immobilie')) {
            return;
        }

        $settings = get_option('dbw_immo_suite_settings', array());
        if (empty($settings['whatsapp_enabled']) || empty($settings['whatsapp_floating'])) {
            return;
        }

        if (!get_theme_mod('dbw_immo_whatsapp_floating', false)) {
            return;
        }

        global $post;
        $url = self::get_whatsapp_url($post->ID);
        if (!$url) {
            return;
        }
        ?>
        <a href="<?php echo esc_url($url); ?>"
           target="_blank" rel="noopener"
           class="dbw-whatsapp-floating"
           aria-label="<?php esc_attr_e('WhatsApp Chat öffnen', 'dbw-immo-suite'); ?>"
           title="<?php echo esc_attr(self::get_cta_text()); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.025.504 3.94 1.396 5.617L.052 23.7a.5.5 0 00.606.607l5.985-1.321A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-1.875 0-3.654-.509-5.197-1.396l-.372-.22-3.857.852.874-3.793-.242-.384A9.71 9.71 0 012.25 12 9.75 9.75 0 0112 2.25 9.75 9.75 0 0121.75 12 9.75 9.75 0 0112 21.75z"/>
            </svg>
        </a>
        <?php
    }

    /**
     * Render WhatsApp link for the modal success screen.
     */
    public static function render_success_link($post_id)
    {
        $url = self::get_whatsapp_url($post_id);
        if (!$url) {
            return;
        }
        ?>
        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="dbw-whatsapp-success-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.025.504 3.94 1.396 5.617L.052 23.7a.5.5 0 00.606.607l5.985-1.321A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-1.875 0-3.654-.509-5.197-1.396l-.372-.22-3.857.852.874-3.793-.242-.384A9.71 9.71 0 012.25 12 9.75 9.75 0 0112 2.25 9.75 9.75 0 0121.75 12 9.75 9.75 0 0112 21.75z"/>
            </svg>
            <?php esc_html_e('Oder direkt per WhatsApp schreiben', 'dbw-immo-suite'); ?>
        </a>
        <?php
    }
}
