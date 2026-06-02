<?php

namespace DBW\ImmoSuite\Admin;

/**
 * Dedicated Dashboard for Import Management
 */
class ImportDashboard
{

    public function init()
    {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('wp_ajax_dbw_immo_refresh_history', array($this, 'ajax_refresh_history'));
    }

    public function ajax_refresh_history()
    {
        check_ajax_referer('dbw_immo_import_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
        }

        $history = get_option('dbw_immo_import_history', array());
        $history = array_reverse($history);

        ob_start();
        $this->render_history_table($history);
        wp_send_json_success(ob_get_clean());
    }

    public function add_menu_page()
    {
        add_submenu_page(
            'edit.php?post_type=immobilie',
            __('Import Zentrale', 'dbw-immo-suite'),
            __('Import Dashboard', 'dbw-immo-suite'),
            'manage_options',
            'dbw-immo-import',
            array($this, 'render_dashboard')
        );
    }

    public function render_dashboard()
    {
        $history = get_option('dbw_immo_import_history', array());
        $history = array_reverse($history);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('OpenImmo Import Zentrale', 'dbw-immo-suite'); ?></h1>
            <hr class="wp-header-end">

            <!-- Status Card -->
            <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
                <h2 class="title"><?php _e('Import', 'dbw-immo-suite'); ?></h2>

                <div style="margin-top: 10px;">
                    <button id="dbw-immo-trigger-import" type="button" class="button button-primary button-hero">
                        <?php _e('Import jetzt starten', 'dbw-immo-suite'); ?>
                    </button>
                    <p class="description" style="margin-top: 10px;">
                        <?php _e('Verarbeitet alle ZIP/XML-Dateien im konfigurierten Verzeichnis.', 'dbw-immo-suite'); ?>
                    </p>
                </div>

                <!-- Progress Panel (hidden by default) -->
                <div id="dbw-immo-progress-panel" style="display: none; margin-top: 20px; padding: 20px; background: #f0f0f1; border-radius: 6px; border-left: 4px solid var(--dbw-progress-color, #2271b1);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="spinner is-active" id="dbw-immo-progress-spinner" style="float: none; margin: 0;"></span>
                            <strong id="dbw-immo-progress-title">Initialisiere...</strong>
                        </div>
                        <span id="dbw-immo-progress-counter" style="color: #50575e; font-size: 13px;"></span>
                    </div>

                    <!-- Progress Bar -->
                    <div style="background: #dcdcde; border-radius: 4px; height: 24px; overflow: hidden; position: relative;">
                        <div id="dbw-immo-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.4s ease; border-radius: 4px;"></div>
                        <span id="dbw-immo-progress-pct" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #1d2327;"></span>
                    </div>

                    <!-- Current File -->
                    <div id="dbw-immo-progress-file" style="margin-top: 10px; font-size: 12px; color: #50575e;"></div>

                    <!-- Live Stats -->
                    <div id="dbw-immo-progress-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 14px;">
                        <div style="text-align: center; padding: 10px; background: #fff; border-radius: 4px;">
                            <div style="font-size: 22px; font-weight: 700; color: #00a32a;" id="dbw-stat-created">0</div>
                            <div style="font-size: 11px; color: #50575e; text-transform: uppercase;"><?php _e('Erstellt', 'dbw-immo-suite'); ?></div>
                        </div>
                        <div style="text-align: center; padding: 10px; background: #fff; border-radius: 4px;">
                            <div style="font-size: 22px; font-weight: 700; color: #2271b1;" id="dbw-stat-updated">0</div>
                            <div style="font-size: 11px; color: #50575e; text-transform: uppercase;"><?php _e('Aktualisiert', 'dbw-immo-suite'); ?></div>
                        </div>
                        <div style="text-align: center; padding: 10px; background: #fff; border-radius: 4px;">
                            <div style="font-size: 22px; font-weight: 700; color: #d63638;" id="dbw-stat-errors">0</div>
                            <div style="font-size: 11px; color: #50575e; text-transform: uppercase;"><?php _e('Fehler', 'dbw-immo-suite'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Legacy status div (used for error messages) -->
                <div id="dbw-immo-import-status" style="margin-top: 20px; display: none;"></div>
            </div>

            <!-- History Table -->
            <h2 style="margin-top: 30px;"><?php _e('Import Historie (Letzte 20)', 'dbw-immo-suite'); ?></h2>
            <div id="dbw-immo-history-wrapper">
                <?php $this->render_history_table($history); ?>
            </div>

        </div>
        <?php
    }

    /**
     * Render the history table markup (reusable for AJAX refresh).
     */
    private function render_history_table($history)
    {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php _e('Datum', 'dbw-immo-suite'); ?></th>
                    <th><?php _e('Datei(en)', 'dbw-immo-suite'); ?></th>
                    <th style="width: 100px;"><?php _e('Erstellt', 'dbw-immo-suite'); ?></th>
                    <th style="width: 100px;"><?php _e('Aktualisiert', 'dbw-immo-suite'); ?></th>
                    <th style="width: 100px;"><?php _e('Fehler', 'dbw-immo-suite'); ?></th>
                    <th style="width: 100px;"><?php _e('Status', 'dbw-immo-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)) : ?>
                    <tr>
                        <td colspan="6"><?php _e('Keine Import-Historie gefunden.', 'dbw-immo-suite'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php
                    $count = 0;
                    foreach ($history as $entry) :
                        if ($count >= 20) break;
                        $count++;
                        $status_map = array(
                            'success' => array('#46b450', 'OK'),
                            'skipped' => array('#2271b1', __('Übersprungen', 'dbw-immo-suite')),
                        );
                        $s = isset($status_map[$entry['status']]) ? $status_map[$entry['status']] : array('#dc3232', __('Fehler', 'dbw-immo-suite'));
                    ?>
                    <tr>
                        <td><?php echo esc_html($entry['date']); ?></td>
                        <td><?php echo esc_html(basename($entry['file'])); ?></td>
                        <td><?php echo esc_html($entry['created']); ?></td>
                        <td><?php echo esc_html($entry['updated']); ?></td>
                        <td><?php echo esc_html($entry['errors']); ?></td>
                        <td style="color: <?php echo esc_attr($s[0]); ?>; font-weight: bold;"><?php echo esc_html($s[1]); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}
