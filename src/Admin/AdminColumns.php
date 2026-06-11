<?php

namespace DBW\ImmoSuite\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * Admin list columns for the immobilie CPT + a WP dashboard widget.
 * Gives Makler a real overview instead of the bare title/date list.
 */
class AdminColumns
{
    public function init()
    {
        add_filter('manage_immobilie_posts_columns', array($this, 'columns'));
        add_action('manage_immobilie_posts_custom_column', array($this, 'render_column'), 10, 2);
        add_filter('manage_edit-immobilie_sortable_columns', array($this, 'sortable_columns'));
        add_action('pre_get_posts', array($this, 'handle_sorting'));
        add_action('admin_head', array($this, 'column_styles'));
        add_action('wp_dashboard_setup', array($this, 'register_dashboard_widget'));
    }

    public function columns($columns)
    {
        $new = array();
        $new['cb'] = isset($columns['cb']) ? $columns['cb'] : '';
        $new['dbw_thumb'] = __('Bild', 'dbw-immo-suite');
        $new['title'] = __('Titel', 'dbw-immo-suite');
        $new['dbw_status'] = __('Status', 'dbw-immo-suite');
        $new['dbw_price'] = __('Preis', 'dbw-immo-suite');
        $new['dbw_ort'] = __('Ort', 'dbw-immo-suite');
        $new['taxonomy-objektart'] = __('Objektart', 'dbw-immo-suite');
        $new['dbw_health'] = __('Vollständigkeit', 'dbw-immo-suite');
        $new['date'] = isset($columns['date']) ? $columns['date'] : __('Datum', 'dbw-immo-suite');
        return $new;
    }

    public function render_column($column, $post_id)
    {
        switch ($column) {
            case 'dbw_thumb':
                $thumb = get_the_post_thumbnail($post_id, array(60, 45), array('style' => 'border-radius:4px;object-fit:cover;'));
                if ($thumb) {
                    echo '<a href="' . esc_url(get_edit_post_link($post_id)) . '">' . $thumb . '</a>';
                } else {
                    echo '<span class="dbw-admin-thumb-placeholder dashicons dashicons-camera" title="' . esc_attr__('Kein Bild', 'dbw-immo-suite') . '"></span>';
                }
                break;

            case 'dbw_status':
                $status = get_post_meta($post_id, '_dbw_immo_status', true) ?: 'aktiv';
                $labels = array(
                    'aktiv'      => __('Aktiv', 'dbw-immo-suite'),
                    'reserviert' => __('Reserviert', 'dbw-immo-suite'),
                    'verkauft'   => __('Verkauft', 'dbw-immo-suite'),
                    'referenz'   => __('Referenz', 'dbw-immo-suite'),
                );
                $label = isset($labels[$status]) ? $labels[$status] : $status;
                echo '<span class="dbw-admin-pill dbw-admin-pill--' . esc_attr($status) . '">' . esc_html($label) . '</span>';
                break;

            case 'dbw_price':
                $kauf = get_post_meta($post_id, 'kaufpreis', true);
                $miete = get_post_meta($post_id, 'kaltmiete', true);
                if ($kauf > 0) {
                    echo '<strong>' . esc_html(\DBW\ImmoSuite\dbw_format_number($kauf, 'preis')) . ' €</strong>';
                } elseif ($miete > 0) {
                    echo '<strong>' . esc_html(\DBW\ImmoSuite\dbw_format_number($miete, 'preis')) . ' €</strong> <span style="color:#787c82;">/ ' . esc_html__('mtl.', 'dbw-immo-suite') . '</span>';
                } else {
                    echo '<span style="color:#787c82;">—</span>';
                }
                break;

            case 'dbw_ort':
                $plz = get_post_meta($post_id, 'plz', true);
                $ort = get_post_meta($post_id, 'ort', true);
                echo esc_html(trim($plz . ' ' . $ort)) ?: '—';
                break;

            case 'dbw_health':
                $issues = array();
                if (!has_post_thumbnail($post_id)) {
                    $issues[] = __('Kein Bild', 'dbw-immo-suite');
                }
                if (!get_post_meta($post_id, 'geo_breite', true) || !get_post_meta($post_id, 'geo_laenge', true)) {
                    $issues[] = __('Keine Geo-Daten (Karte/Infra-Score)', 'dbw-immo-suite');
                }
                if (!get_post_meta($post_id, 'energiepass_wertklasse', true) && !get_post_meta($post_id, 'energiepass_endenergie', true)) {
                    $issues[] = __('Kein Energieausweis (Pflichtangabe!)', 'dbw-immo-suite');
                }

                if (empty($issues)) {
                    echo '<span class="dashicons dashicons-yes-alt" style="color:#00a32a;" title="' . esc_attr__('Vollständig', 'dbw-immo-suite') . '"></span>';
                } else {
                    echo '<span class="dashicons dashicons-warning" style="color:#dba617;"></span> ';
                    echo '<span class="dbw-admin-issues" title="' . esc_attr(implode(' · ', $issues)) . '">' . (int) count($issues) . ' ' . esc_html(_n('Hinweis', 'Hinweise', count($issues), 'dbw-immo-suite')) . '</span>';
                }
                break;
        }
    }

    public function sortable_columns($columns)
    {
        $columns['dbw_price'] = 'dbw_price';
        $columns['dbw_ort'] = 'dbw_ort';
        return $columns;
    }

    public function handle_sorting($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('post_type') !== 'immobilie') {
            return;
        }

        $orderby = $query->get('orderby');
        if ($orderby === 'dbw_price') {
            // OR + NOT EXISTS keeps objects without the meta row in the list
            $query->set('meta_query', array(
                'relation'  => 'OR',
                'dbw_price' => array('key' => 'kaufpreis', 'compare' => 'EXISTS', 'type' => 'NUMERIC'),
                'no_price'  => array('key' => 'kaufpreis', 'compare' => 'NOT EXISTS'),
            ));
            $query->set('orderby', 'dbw_price');
        } elseif ($orderby === 'dbw_ort') {
            $query->set('meta_query', array(
                'relation' => 'OR',
                'dbw_ort'  => array('key' => 'ort', 'compare' => 'EXISTS'),
                'no_ort'   => array('key' => 'ort', 'compare' => 'NOT EXISTS'),
            ));
            $query->set('orderby', 'dbw_ort');
        }
    }

    public function column_styles()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_list = $screen && $screen->id === 'edit-immobilie';
        $is_dashboard = $screen && $screen->id === 'dashboard';
        if (!$is_list && !$is_dashboard) {
            return;
        }
        ?>
        <style>
            .column-dbw_thumb { width: 70px; }
            .column-dbw_status { width: 105px; }
            .column-dbw_health { width: 130px; }
            .dbw-admin-thumb-placeholder { color: #c3c4c7; font-size: 24px; }
            .dbw-admin-pill {
                display: inline-block; padding: 2px 10px; border-radius: 999px;
                font-size: 11px; font-weight: 600; line-height: 1.8;
            }
            .dbw-admin-pill--aktiv { background: #d4edda; color: #1e7e34; }
            .dbw-admin-pill--reserviert { background: #fff3cd; color: #856404; }
            .dbw-admin-pill--verkauft { background: #e2e3e5; color: #41464b; }
            .dbw-admin-pill--referenz { background: #cfe2ff; color: #084298; }
            .dbw-admin-issues { color: #856404; cursor: help; }
            .dbw-dash-stats { display: flex; gap: 10px; margin-bottom: 14px; }
            .dbw-dash-stat {
                flex: 1; text-align: center; background: #f6f7f7;
                border-radius: 8px; padding: 12px 6px;
            }
            .dbw-dash-stat strong { display: block; font-size: 22px; line-height: 1.2; }
            .dbw-dash-stat span { color: #787c82; font-size: 11px; }
            .dbw-dash-import { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
            .dbw-dash-dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; }
        </style>
        <?php
    }

    /**
     * Dashboard widget: portfolio counts + last import status.
     */
    public function register_dashboard_widget()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }
        wp_add_dashboard_widget('dbw_immo_dashboard', __('Immo Suite', 'dbw-immo-suite'), array($this, 'render_dashboard_widget'));
    }

    public function render_dashboard_widget()
    {
        // Portfolio counts by internal status
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT COALESCE(NULLIF(pm.meta_value, ''), 'aktiv') AS status, COUNT(*) AS cnt
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_dbw_immo_status')
             WHERE p.post_type = 'immobilie' AND p.post_status = 'publish'
             GROUP BY status",
            OBJECT_K
        );
        $count = function ($key) use ($rows) {
            return isset($rows[$key]) ? (int) $rows[$key]->cnt : 0;
        };
        ?>
        <div class="dbw-dash-stats">
            <div class="dbw-dash-stat"><strong><?php echo $count('aktiv'); ?></strong><span><?php esc_html_e('Aktiv', 'dbw-immo-suite'); ?></span></div>
            <div class="dbw-dash-stat"><strong><?php echo $count('reserviert'); ?></strong><span><?php esc_html_e('Reserviert', 'dbw-immo-suite'); ?></span></div>
            <div class="dbw-dash-stat"><strong><?php echo $count('verkauft') + $count('referenz'); ?></strong><span><?php esc_html_e('Verkauft/Referenz', 'dbw-immo-suite'); ?></span></div>
        </div>
        <?php
        // Last import
        $history = get_option('dbw_immo_import_history', array());
        $last = !empty($history) ? end($history) : null;
        if ($last) {
            $ok = ($last['status'] === 'success' || empty($last['errors']));
            $when = human_time_diff(strtotime($last['date']), current_time('timestamp'));
            ?>
            <div class="dbw-dash-import">
                <span class="dbw-dash-dot" style="background: <?php echo $ok ? '#00a32a' : '#d63638'; ?>;"></span>
                <span>
                    <?php printf(
                        esc_html__('Letzter Import: vor %1$s — %2$d neu, %3$d aktualisiert%4$s', 'dbw-immo-suite'),
                        esc_html($when),
                        (int) $last['created'],
                        (int) $last['updated'],
                        $last['errors'] ? ', ' . sprintf(esc_html__('%d Fehler', 'dbw-immo-suite'), (int) $last['errors']) : ''
                    ); ?>
                </span>
            </div>
            <?php
        } else {
            echo '<div class="dbw-dash-import"><span class="dbw-dash-dot" style="background:#c3c4c7;"></span><span>' . esc_html__('Noch kein Import gelaufen.', 'dbw-immo-suite') . '</span></div>';
        }
        ?>
        <p>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=immobilie&page=dbw-immo-import')); ?>"><?php esc_html_e('Import', 'dbw-immo-suite'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=immobilie')); ?>"><?php esc_html_e('Alle Objekte', 'dbw-immo-suite'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=immobilie&page=dbw-immo-suite-settings')); ?>"><?php esc_html_e('Einstellungen', 'dbw-immo-suite'); ?></a>
        </p>
        <?php
    }
}
