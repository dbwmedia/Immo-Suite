<?php

namespace DBW\ImmoSuite\Admin;

use DBW\ImmoSuite\Core\License;

if (!defined('ABSPATH')) { exit; }

/**
 * Guided setup checklist: license → import path → first import → design.
 * Shown on the import dashboard (and as a pointer notice on the property
 * list) until every step is done or the user dismisses it.
 */
class Onboarding
{
    const DISMISS_OPTION = 'dbw_immo_onboarding_dismissed';

    public function init()
    {
        add_action('admin_notices', array($this, 'pointer_notice'));
        add_action('wp_ajax_dbw_immo_dismiss_onboarding', array($this, 'ajax_dismiss'));
    }

    /**
     * Setup steps with live completion state.
     */
    public static function get_steps()
    {
        $settings = get_option('dbw_immo_suite_settings', array());

        // "Design angepasst" = any Immo-Suite Customizer setting was saved
        $mods = get_theme_mods();
        $design_touched = false;
        if (is_array($mods)) {
            foreach ($mods as $key => $value) {
                if (strpos((string) $key, 'dbw_immo_') === 0) {
                    $design_touched = true;
                    break;
                }
            }
        }

        return array(
            array(
                'label' => __('Lizenz aktivieren', 'dbw-immo-suite'),
                'done'  => class_exists('DBW\ImmoSuite\Core\License') && License::is_valid(),
                'url'   => admin_url('edit.php?post_type=immobilie&page=dbw-immo-suite-settings#tab-license'),
            ),
            array(
                'label' => __('Import-Pfad pruefen (FTP-Ziel der Maklersoftware)', 'dbw-immo-suite'),
                'done'  => !empty($settings['xml_path']),
                'url'   => admin_url('edit.php?post_type=immobilie&page=dbw-immo-suite-settings#tab-import'),
            ),
            array(
                'label' => __('Ersten Import ausfuehren', 'dbw-immo-suite'),
                'done'  => (bool) get_option('dbw_immo_import_history'),
                'url'   => admin_url('edit.php?post_type=immobilie&page=dbw-immo-import'),
            ),
            array(
                'label' => __('Design im Customizer anpassen (Farben, Eckenradius)', 'dbw-immo-suite'),
                'done'  => $design_touched,
                'url'   => admin_url('customize.php?autofocus[panel]=dbw_immo_panel'),
            ),
        );
    }

    public static function is_complete()
    {
        foreach (self::get_steps() as $step) {
            if (!$step['done']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checklist panel (rendered inside the import dashboard).
     */
    public static function render_panel()
    {
        if (get_option(self::DISMISS_OPTION) || self::is_complete()) {
            return;
        }
        $steps = self::get_steps();
        $done  = count(array_filter(array_column($steps, 'done')));
        $nonce = wp_create_nonce('dbw_immo_dismiss_onboarding');
        ?>
        <div class="card dbw-onboarding" id="dbw-onboarding" style="max-width: 100%; margin-top: 20px; padding: 20px; border-left: 4px solid #2271b1;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 12px;">
                <h2 class="title" style="margin: 0;">
                    <?php printf(esc_html__('Einrichtung (%1$d/%2$d)', 'dbw-immo-suite'), (int) $done, count($steps)); ?>
                </h2>
                <button type="button" class="button-link" id="dbw-onboarding-dismiss" style="color: #787c82;">
                    <?php esc_html_e('Ausblenden', 'dbw-immo-suite'); ?>
                </button>
            </div>
            <ol style="margin: 14px 0 0; list-style: none; padding: 0;">
                <?php foreach ($steps as $step) : ?>
                    <li style="display: flex; align-items: center; gap: 10px; padding: 7px 0; <?php echo $step['done'] ? 'color:#00a32a;' : ''; ?>">
                        <span aria-hidden="true" style="display:inline-flex; width:22px; height:22px; border-radius:50%; align-items:center; justify-content:center; font-weight:700; flex-shrink:0; <?php echo $step['done']
                            ? 'background:#00a32a; color:#fff;'
                            : 'background:#f0f0f1; color:#787c82; border:1px solid #dcdcde;'; ?>">
                            <?php echo $step['done'] ? '&#10003;' : ''; ?>
                        </span>
                        <?php if ($step['done']) : ?>
                            <span style="text-decoration: line-through; opacity: 0.7;"><?php echo esc_html($step['label']); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url($step['url']); ?>"><?php echo esc_html($step['label']); ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <script>
        (function() {
            var btn = document.getElementById('dbw-onboarding-dismiss');
            if (!btn) return;
            btn.addEventListener('click', function() {
                var data = new FormData();
                data.append('action', 'dbw_immo_dismiss_onboarding');
                data.append('nonce', '<?php echo esc_js($nonce); ?>');
                fetch(ajaxurl, { method: 'POST', body: data });
                var panel = document.getElementById('dbw-onboarding');
                if (panel) panel.remove();
            });
        })();
        </script>
        <?php
    }

    /**
     * Small pointer on the property list while setup is incomplete.
     */
    public function pointer_notice()
    {
        if (get_option(self::DISMISS_OPTION) || !current_user_can('manage_options')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-immobilie' || self::is_complete()) {
            return;
        }
        printf(
            '<div class="notice notice-info"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
            esc_html__('Immo Suite:', 'dbw-immo-suite'),
            esc_html__('Die Einrichtung ist noch nicht abgeschlossen.', 'dbw-immo-suite'),
            esc_url(admin_url('edit.php?post_type=immobilie&page=dbw-immo-import')),
            esc_html__('Zur Einrichtungs-Checkliste', 'dbw-immo-suite')
        );
    }

    public function ajax_dismiss()
    {
        check_ajax_referer('dbw_immo_dismiss_onboarding', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        update_option(self::DISMISS_OPTION, 1, false);
        wp_send_json_success();
    }
}
