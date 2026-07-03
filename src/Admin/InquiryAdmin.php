<?php

namespace DBW\ImmoSuite\Admin;

use DBW\ImmoSuite\PostTypes\Inquiry;

if (!defined('ABSPATH')) { exit; }

/**
 * Admin UI for the inquiry inbox: list columns with inline status switch,
 * status filter, read-only detail meta box and a "new" bubble in the menu.
 */
class InquiryAdmin
{
    public function init()
    {
        add_filter('manage_' . Inquiry::POST_TYPE . '_posts_columns', array($this, 'columns'));
        add_action('manage_' . Inquiry::POST_TYPE . '_posts_custom_column', array($this, 'render_column'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'status_filter_dropdown'));
        add_action('pre_get_posts', array($this, 'apply_status_filter'));
        add_action('add_meta_boxes_' . Inquiry::POST_TYPE, array($this, 'add_meta_box'));
        add_action('save_post_' . Inquiry::POST_TYPE, array($this, 'save_status_meta_box'), 10, 2);
        add_action('wp_ajax_dbw_immo_inquiry_status', array($this, 'ajax_update_status'));
        add_action('admin_menu', array($this, 'menu_bubble'), 99);
        add_action('admin_footer', array($this, 'list_screen_assets'));
    }

    // ── List table ─────────────────────────────────────────────

    public function columns($columns)
    {
        return array(
            'cb'           => isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />',
            'title'        => __('Anfrage', 'dbw-immo-suite'),
            'dbw_contact'  => __('Kontakt', 'dbw-immo-suite'),
            'dbw_intent'   => __('Anliegen', 'dbw-immo-suite'),
            'dbw_property' => __('Objekt', 'dbw-immo-suite'),
            'dbw_status'   => __('Status', 'dbw-immo-suite'),
            'date'         => __('Eingegangen', 'dbw-immo-suite'),
        );
    }

    public function render_column($column, $post_id)
    {
        switch ($column) {
            case 'dbw_contact':
                $email = get_post_meta($post_id, '_dbw_anfrage_email', true);
                $phone = get_post_meta($post_id, '_dbw_anfrage_phone', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                }
                if ($phone) {
                    echo '<br><span style="color:#646970;">' . esc_html($phone) . '</span>';
                }
                break;

            case 'dbw_intent':
                $intent = get_post_meta($post_id, '_dbw_anfrage_intent', true);
                $labels = Inquiry::get_intent_labels();
                if (isset($labels[$intent])) {
                    echo '<span class="dbw-inquiry-intent dbw-inquiry-intent--' . esc_attr($intent) . '">' . esc_html($labels[$intent]) . '</span>';
                } else {
                    echo '&mdash;';
                }
                break;

            case 'dbw_property':
                $property_id = (int) get_post_meta($post_id, '_dbw_anfrage_property', true);
                if ($property_id && get_post($property_id)) {
                    echo '<a href="' . esc_url(get_edit_post_link($property_id)) . '">' . esc_html(get_the_title($property_id)) . '</a>';
                } else {
                    echo '&mdash;';
                }
                break;

            case 'dbw_status':
                $status = get_post_meta($post_id, '_dbw_anfrage_status', true) ?: 'neu';
                echo '<select class="dbw-inquiry-status" data-inquiry="' . (int) $post_id . '" data-current="' . esc_attr($status) . '">';
                foreach (Inquiry::get_statuses() as $key => $label) {
                    echo '<option value="' . esc_attr($key) . '"' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                break;
        }
    }

    public function status_filter_dropdown($post_type)
    {
        if ($post_type !== Inquiry::POST_TYPE) {
            return;
        }
        $current = isset($_GET['dbw_status']) ? sanitize_key($_GET['dbw_status']) : '';
        echo '<select name="dbw_status">';
        echo '<option value="">' . esc_html__('Alle Status', 'dbw-immo-suite') . '</option>';
        foreach (Inquiry::get_statuses() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($current, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function apply_status_filter($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('post_type') !== Inquiry::POST_TYPE || empty($_GET['dbw_status'])) {
            return;
        }
        $status = sanitize_key($_GET['dbw_status']);
        if (isset(Inquiry::get_statuses()[$status])) {
            $query->set('meta_key', '_dbw_anfrage_status');
            $query->set('meta_value', $status);
        }
    }

    // ── Inline status switch (AJAX) ────────────────────────────

    public function ajax_update_status()
    {
        check_ajax_referer('dbw_immo_inquiry_status', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Keine Berechtigung');
        }

        $post_id = isset($_POST['inquiry']) ? absint($_POST['inquiry']) : 0;
        $status  = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if (!$post_id || get_post_type($post_id) !== Inquiry::POST_TYPE || !isset(Inquiry::get_statuses()[$status])) {
            wp_send_json_error(__('Ungueltige Anfrage.', 'dbw-immo-suite'));
        }

        update_post_meta($post_id, '_dbw_anfrage_status', $status);
        wp_send_json_success(array('status' => $status, 'new_count' => Inquiry::count_new()));
    }

    public function list_screen_assets()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-' . Inquiry::POST_TYPE) {
            return;
        }
        $nonce = wp_create_nonce('dbw_immo_inquiry_status');
        ?>
        <style>
            .dbw-inquiry-intent { display:inline-block; padding:2px 10px; border-radius:12px; font-size:12px; font-weight:600; background:#f0f0f1; color:#3c434a; }
            .dbw-inquiry-intent--besichtigung { background:#e5f1f8; color:#135e96; }
            .dbw-inquiry-intent--preis { background:#edf7ed; color:#1e8449; }
            .dbw-inquiry-intent--rueckruf { background:#fcf0e4; color:#a4650e; }
            .dbw-inquiry-intent--expose { background:#f1eafb; color:#6c3fb5; }
            select.dbw-inquiry-status[data-current="neu"] { border-color:#d63638; box-shadow:0 0 0 1px #d63638; }
        </style>
        <script>
        (function() {
            document.querySelectorAll('select.dbw-inquiry-status').forEach(function(sel) {
                sel.addEventListener('change', function() {
                    var data = new FormData();
                    data.append('action', 'dbw_immo_inquiry_status');
                    data.append('nonce', '<?php echo esc_js($nonce); ?>');
                    data.append('inquiry', sel.dataset.inquiry);
                    data.append('status', sel.value);
                    fetch(ajaxurl, { method: 'POST', body: data })
                        .then(function(r) { return r.json(); })
                        .then(function(j) {
                            if (!j.success) { alert(j.data || 'Fehler'); return; }
                            sel.dataset.current = j.data.status;
                            var bubble = document.querySelector('#menu-posts-immobilie .dbw-inquiry-bubble');
                            if (bubble) {
                                bubble.textContent = j.data.new_count;
                                bubble.style.display = j.data.new_count > 0 ? '' : 'none';
                            }
                        });
                });
            });
        })();
        </script>
        <?php
    }

    // ── Detail view ────────────────────────────────────────────

    public function add_meta_box()
    {
        add_meta_box(
            'dbw-inquiry-details',
            __('Anfrage-Details', 'dbw-immo-suite'),
            array($this, 'render_meta_box'),
            Inquiry::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        $m = function ($key) use ($post) {
            return get_post_meta($post->ID, '_dbw_anfrage_' . $key, true);
        };
        $intent_labels = Inquiry::get_intent_labels();
        $intent        = $m('intent');
        $property_id   = (int) $m('property');
        $status        = $m('status') ?: 'neu';
        $pref_labels   = array('email' => 'E-Mail', 'phone' => 'Telefon', 'whatsapp' => 'WhatsApp');
        $preferred     = $m('preferred');

        wp_nonce_field('dbw_inquiry_status_box', 'dbw_inquiry_status_nonce');

        $row = function ($label, $value_html) {
            if ($value_html === '') { return; }
            echo '<tr><th style="width:180px; text-align:left; padding:8px 12px 8px 0; vertical-align:top;">' . esc_html($label) . '</th><td style="padding:8px 0;">' . $value_html . '</td></tr>'; // phpcs:ignore -- value escaped by caller
        };
        ?>
        <table style="width:100%; border-collapse:collapse;">
            <?php
            $row(__('Status', 'dbw-immo-suite'), (function () use ($status) {
                $out = '<select name="dbw_anfrage_status">';
                foreach (Inquiry::get_statuses() as $key => $label) {
                    $out .= '<option value="' . esc_attr($key) . '"' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
                }
                return $out . '</select>';
            })());
            $row(__('Name', 'dbw-immo-suite'), esc_html($m('name')));
            $row(__('E-Mail', 'dbw-immo-suite'), $m('email') ? '<a href="mailto:' . esc_attr($m('email')) . '">' . esc_html($m('email')) . '</a>' : '');
            $row(__('Telefon', 'dbw-immo-suite'), esc_html($m('phone')));
            $row(__('Bevorzugter Kontaktweg', 'dbw-immo-suite'), esc_html(isset($pref_labels[$preferred]) ? $pref_labels[$preferred] : ''));
            $row(__('Anliegen', 'dbw-immo-suite'), esc_html(isset($intent_labels[$intent]) ? $intent_labels[$intent] : $intent));
            $row(__('Details', 'dbw-immo-suite'), nl2br(esc_html($m('details'))));
            $row(__('Nachricht', 'dbw-immo-suite'), nl2br(esc_html($m('message'))));
            if ($property_id && get_post($property_id)) {
                $row(__('Objekt', 'dbw-immo-suite'),
                    '<a href="' . esc_url(get_edit_post_link($property_id)) . '">' . esc_html(get_the_title($property_id)) . '</a>'
                    . ' &middot; <a href="' . esc_url(get_permalink($property_id)) . '" target="_blank" rel="noopener">' . esc_html__('Ansehen', 'dbw-immo-suite') . '</a>');
            }
            $row(__('Quelle', 'dbw-immo-suite'), esc_html($m('source') === 'expose' ? __('Expose-Anfrage', 'dbw-immo-suite') : __('Kontaktformular', 'dbw-immo-suite')));
            $row(__('Datenschutz-Zustimmung', 'dbw-immo-suite'), esc_html($m('consent')));
            ?>
        </table>
        <p class="description" style="margin-top:12px;">
            <?php
            $days = Inquiry::get_retention_days();
            if ($days > 0) {
                printf(esc_html__('Diese Anfrage wird nach %d Tagen automatisch geloescht (einstellbar unter Einstellungen > Darstellung > Kontaktanfragen).', 'dbw-immo-suite'), (int) $days);
            } else {
                esc_html_e('Automatische Loeschung ist deaktiviert. Bitte erledigte Anfragen manuell loeschen (Datenminimierung).', 'dbw-immo-suite');
            }
            ?>
        </p>
        <?php
    }

    public function save_status_meta_box($post_id, $post)
    {
        if (!isset($_POST['dbw_inquiry_status_nonce'])
            || !wp_verify_nonce(sanitize_text_field($_POST['dbw_inquiry_status_nonce']), 'dbw_inquiry_status_box')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $status = isset($_POST['dbw_anfrage_status']) ? sanitize_key($_POST['dbw_anfrage_status']) : '';
        if (isset(Inquiry::get_statuses()[$status])) {
            update_post_meta($post_id, '_dbw_anfrage_status', $status);
        }
    }

    // ── Menu bubble ────────────────────────────────────────────

    public function menu_bubble()
    {
        $count = Inquiry::count_new();
        if ($count < 1) {
            return;
        }
        global $submenu;
        if (empty($submenu['edit.php?post_type=immobilie'])) {
            return;
        }
        foreach ($submenu['edit.php?post_type=immobilie'] as $i => $item) {
            if (strpos($item[2], 'post_type=' . Inquiry::POST_TYPE) !== false) {
                $submenu['edit.php?post_type=immobilie'][$i][0] .= // phpcs:ignore WordPress.WP.GlobalVariablesOverride
                    ' <span class="awaiting-mod dbw-inquiry-bubble"><span class="pending-count">' . (int) $count . '</span></span>';
                break;
            }
        }
    }
}
