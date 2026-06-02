<?php

namespace DBW\ImmoSuite\Admin;

/**
 * Handles Custom Meta Boxes for Property Details
 */
class PropertyDetails
{

    public function init()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    public function enqueue_admin_styles()
    {
        // Simple internal CSS for tabs (can be moved to admin.css later)
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'immobilie') {
            wp_add_inline_style('common', "
                .dbw-tabs { border-bottom: 1px solid #ccc; margin-bottom: 15px; }
                .dbw-tab-link { display: inline-block; padding: 10px 15px; text-decoration: none; border: 1px solid transparent; border-bottom: none; margin-bottom: -1px; color: #0073aa; font-weight: 600; cursor: pointer; }
                .dbw-tab-link.active { border-color: #ccc; border-bottom-color: #fff; color: #000; background: #fff; }
                .dbw-tab-content { display: none; }
                .dbw-tab-content.active { display: block; }
                .dbw-field-row { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
                .dbw-field-row label { display: block; font-weight: 600; margin-bottom: 5px; }
                .dbw-field-row input[type='text'], .dbw-field-row input[type='number'] { width: 100%; max-width: 400px; }
            ");
            
            // Simple JS for tabs
            wp_add_inline_script('common', "
                jQuery(document).ready(function($) {
                    $('.dbw-tab-link').click(function(e) {
                        e.preventDefault();
                        var target = $(this).data('tab');
                        $('.dbw-tab-link').removeClass('active');
                        $(this).addClass('active');
                        $('.dbw-tab-content').removeClass('active');
                        $('#' + target).addClass('active');
                    });
                });
            ");
        }
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'dbw_property_details',
            __('Immobiliendaten', 'dbw-immo-suite'),
            array($this, 'render_details_box'),
            'immobilie',
            'normal',
            'high'
        );
        
        // Remove standard Custom Fields box to avoid clutter
        remove_meta_box('postcustom', 'immobilie', 'normal');
    }

    public function render_details_box($post)
    {
        // Add nonce for security
        wp_nonce_field('dbw_save_property_details', 'dbw_property_nonce');

        // Retrieve existing values
        $meta = get_post_meta($post->ID);
        
        // Helper to safely get value
        $val = function($key) use ($meta) {
            return isset($meta[$key][0]) ? esc_attr($meta[$key][0]) : '';
        };

        ?>
        <div class="dbw-property-editor">
            <!-- Tabs Navigation -->
            <div class="dbw-tabs">
                <a class="dbw-tab-link active" data-tab="tab-basis"><?php _e('Basisdaten', 'dbw-immo-suite'); ?></a>
                <a class="dbw-tab-link" data-tab="tab-prices"><?php _e('Preise', 'dbw-immo-suite'); ?></a>
                <a class="dbw-tab-link" data-tab="tab-areas"><?php _e('Flächen & Zimmer', 'dbw-immo-suite'); ?></a>
                <a class="dbw-tab-link" data-tab="tab-features"><?php _e('Ausstattung', 'dbw-immo-suite'); ?></a>
                <a class="dbw-tab-link" data-tab="tab-tech"><?php _e('Technik & Zustand', 'dbw-immo-suite'); ?></a>
                <a class="dbw-tab-link" data-tab="tab-contact"><?php _e('Kontakt / Anbieter', 'dbw-immo-suite'); ?></a>
                <a class="dbw-tab-link" data-tab="tab-import"><?php _e('Import Info', 'dbw-immo-suite'); ?></a>
            </div>

            <!-- TAB: Basis -->
            <div id="tab-basis" class="dbw-tab-content active">
                <!-- Status Control -->
                <div class="dbw-field-row" style="background-color: #fef9e7; padding: 15px; border-radius: 5px; border-left: 4px solid #f39c12; margin-bottom: 20px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 8px;"><?php _e('Objektstatus', 'dbw-immo-suite'); ?></label>
                    <?php $current_status = $val('_dbw_immo_status') ?: 'aktiv'; ?>
                    <select name="_dbw_immo_status" style="min-width: 200px;">
                        <option value="aktiv" <?php selected($current_status, 'aktiv'); ?>><?php _e('Aktiv', 'dbw-immo-suite'); ?></option>
                        <option value="reserviert" <?php selected($current_status, 'reserviert'); ?>><?php _e('Reserviert', 'dbw-immo-suite'); ?></option>
                        <option value="verkauft" <?php selected($current_status, 'verkauft'); ?>><?php _e('Verkauft', 'dbw-immo-suite'); ?></option>
                        <option value="referenz" <?php selected($current_status, 'referenz'); ?>><?php _e('Referenz', 'dbw-immo-suite'); ?></option>
                    </select>
                    <br><br>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="_dbw_immo_manual_status_override" value="1" <?php checked('1', $val('_dbw_immo_manual_status_override')); ?>>
                        <strong><?php _e('Status sperren (nicht vom Import ueberschreiben)', 'dbw-immo-suite'); ?></strong>
                    </label>
                    <p class="description" style="margin-top: 5px; margin-left: 24px;"><?php _e('Wenn aktiviert, wird der Status bei einem OpenImmo-Import nicht mehr automatisch aktualisiert.', 'dbw-immo-suite'); ?></p>
                </div>

                <!-- Highlight -->
                <div class="dbw-field-row" style="background-color: #f0f7ff; padding: 15px; border-radius: 5px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="_dbw_immo_is_highlight" value="1" <?php checked('1', $val('_dbw_immo_is_highlight')); ?>>
                        <strong><?php _e('Als Highlight markieren', 'dbw-immo-suite'); ?></strong>
                    </label>
                    <p class="description" style="margin-top: 5px; margin-left: 24px; font-weight: normal;"><?php _e('Diese Immobilie im "Immobilien Grid" Block als Highlight priorisiert hervorheben.', 'dbw-immo-suite'); ?></p>
                </div>

                <div class="dbw-field-row">
                    <label><?php _e('OpenImmo ID (Objekt-Nr.)', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="openimmo_id" value="<?php echo $val('openimmo_id'); ?>" readonly style="background:#f9f9f9; color:#777;">
                    <p class="description"><?php _e('Eindeutige ID aus der Maklersoftware. Nicht änderbar.', 'dbw-immo-suite'); ?></p>
                </div>
                <div class="dbw-field-row">
                    <label><?php _e('Straße & Hausnummer', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="strasse" value="<?php echo $val('strasse'); ?>" placeholder="Musterstraße">
                    <input type="text" name="hausnummer" value="<?php echo $val('hausnummer'); ?>" placeholder="1" style="width: 80px;">
                </div>
                <div class="dbw-field-row">
                    <label><?php _e('PLZ & Ort', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="plz" value="<?php echo $val('plz'); ?>" placeholder="12345" style="width: 100px;">
                    <input type="text" name="ort" value="<?php echo $val('ort'); ?>" placeholder="Musterstadt">
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Geokoordinaten', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="geo_breite" value="<?php echo $val('geo_breite'); ?>" placeholder="Lat" style="width: 150px;">
                    <input type="text" name="geo_laenge" value="<?php echo $val('geo_laenge'); ?>" placeholder="Lng" style="width: 150px;">
                </div>
            </div>

            <!-- TAB: Prices -->
            <div id="tab-prices" class="dbw-tab-content">
                <div class="dbw-field-row">
                    <label><?php _e('Kaufpreis', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="0.01" name="kaufpreis" value="<?php echo $val('kaufpreis'); ?>"> €
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Kaltmiete', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="0.01" name="kaltmiete" value="<?php echo $val('kaltmiete'); ?>"> €
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Warmmiete', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="0.01" name="warmmiete" value="<?php echo $val('warmmiete'); ?>"> €
                </div>
                <div class="dbw-field-row">
                    <label><?php _e('Hausgeld', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="0.01" name="hausgeld" value="<?php echo $val('hausgeld'); ?>"> €
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Nebenkosten', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="0.01" name="nebenkosten" value="<?php echo $val('nebenkosten'); ?>"> €
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Käuferprovision (inkl. Text)', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="provision_kaeufer" value="<?php echo $val('provision_kaeufer'); ?>" placeholder="z.B. 3,57% inkl. MwSt.">
                </div>
            </div>

            <!-- TAB: Areas -->
            <div id="tab-areas" class="dbw-tab-content">
                 <div class="dbw-field-row">
                    <label><?php _e('Wohnfläche', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="0.01" name="wohnflaeche" value="<?php echo $val('wohnflaeche'); ?>"> m²
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Nutzfläche', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="0.01" name="nutzflaeche" value="<?php echo $val('nutzflaeche'); ?>"> m²
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Grundstücksfläche', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="0.01" name="grundstuecksflaeche" value="<?php echo $val('grundstuecksflaeche'); ?>"> m²
                </div>
                <div class="dbw-field-row">
                    <label><?php _e('Anzahl Zimmer', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="0.5" name="anzahl_zimmer" value="<?php echo $val('anzahl_zimmer'); ?>">
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Anzahl Schlafzimmer', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="1" name="anzahl_schlafzimmer" value="<?php echo $val('anzahl_schlafzimmer'); ?>">
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Anzahl Badezimmer', 'dbw-immo-suite'); ?></label>
                    <input type="number" step="1" name="anzahl_badezimmer" value="<?php echo $val('anzahl_badezimmer'); ?>">
                </div>
            </div>

            <!-- TAB: Ausstattung -->
            <div id="tab-features" class="dbw-tab-content">
                <?php
                $features = get_post_meta($post->ID, '_dbw_immo_features', true);
                if (!is_array($features)) $features = array();
                ?>
                <div class="dbw-field-row">
                    <label><?php _e('Ausstattungsmerkmale', 'dbw-immo-suite'); ?></label>
                    <p class="description"><?php _e('Kommagetrennte Liste. Wird automatisch vom OpenImmo-Import befuellt.', 'dbw-immo-suite'); ?></p>
                    <textarea name="_dbw_immo_features_text" rows="4" style="width:100%; max-width:600px;"><?php echo esc_textarea(implode(', ', $features)); ?></textarea>
                </div>
                <?php if (!empty($features)): ?>
                <div class="dbw-field-row">
                    <label><?php _e('Vorschau', 'dbw-immo-suite'); ?></label>
                    <div style="display:flex; flex-wrap:wrap; gap:6px;">
                        <?php foreach ($features as $f): ?>
                            <span style="background:#f0f2f5; padding:4px 12px; border-radius:20px; font-size:0.85rem;"><?php echo esc_html($f); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: Tech -->
            <div id="tab-tech" class="dbw-tab-content">
                <div class="dbw-field-row">
                    <label><?php _e('Baujahr', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="energiepass_baujahr" value="<?php echo $val('energiepass_baujahr'); ?>">
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Zustand', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="zustand_art" value="<?php echo $val('zustand_art'); ?>">
                </div>
                <div class="dbw-field-row">
                    <label><?php _e('Energieausweis Art', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="energiepass_art" value="<?php echo $val('energiepass_art'); ?>">
                </div>
                <div class="dbw-field-row">
                    <label><?php _e('Endenergiebedarf', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="energiepass_endenergie" value="<?php echo $val('energiepass_endenergie'); ?>"> kWh/(m²*a)
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Energieeffizienzklasse', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="energiepass_wertklasse" value="<?php echo $val('energiepass_wertklasse'); ?>" style="width: 50px; text-align: center; font-weight: bold;">
                </div>
            </div>

            <!-- TAB: Contact -->
            <div id="tab-contact" class="dbw-tab-content">
                 <div class="dbw-field-row">
                    <label><?php _e('Ansprechpartner', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="kontaktperson_vorname" value="<?php echo $val('kontaktperson_vorname'); ?>" placeholder="Vorname">
                    <input type="text" name="kontaktperson_name" value="<?php echo $val('kontaktperson_name'); ?>" placeholder="Nachname">
                </div>
                 <div class="dbw-field-row">
                    <label><?php _e('Firma', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="kontaktperson_firma" value="<?php echo $val('kontaktperson_firma'); ?>">
                </div>
                <div class="dbw-field-row">
                    <label><?php _e('E-Mail', 'dbw-immo-suite'); ?></label>
                    <input type="email" name="kontaktperson_email" value="<?php echo $val('kontaktperson_email'); ?>">
                </div>
                <div class="dbw-field-row">
                    <label><?php _e('Telefon', 'dbw-immo-suite'); ?></label>
                    <input type="text" name="kontaktperson_tel" value="<?php echo $val('kontaktperson_tel'); ?>">
                </div>
            </div>

             <!-- TAB: Import Info -->
            <div id="tab-import" class="dbw-tab-content">
                <div class="dbw-field-row">
                    <label><?php _e('Importiert am', 'dbw-immo-suite'); ?></label>
                    <span><?php echo get_the_date('d.m.Y H:i', $post->ID); ?></span>
                </div>
                <div class="dbw-field-row">
                    <label><?php _e('Debug: Alle Metadaten', 'dbw-immo-suite'); ?></label>
                    <textarea rows="10" readonly style="width:100%; font-family:monospace; font-size:11px;"><?php
                        // Show all non-hidden meta data for debug
                        foreach ($meta as $key => $values) {
                            if (substr($key, 0, 1) === '_' && $key !== '_openimmo_id') continue;
                            echo esc_html($key . ': ' . $values[0]) . "\n";
                        }
                    ?></textarea>
                </div>
            </div>

        </div>
        <?php
    }

    public function save_meta_box_data($post_id)
    {
        if (!isset($_POST['dbw_property_nonce']) || !wp_verify_nonce($_POST['dbw_property_nonce'], 'dbw_save_property_details')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // List of allowed fields to save
        $fields = [
            'openimmo_id', 'strasse', 'hausnummer', 'plz', 'ort', 'geo_breite', 'geo_laenge',
            'kaufpreis', 'kaltmiete', 'warmmiete', 'hausgeld', 'nebenkosten', 'provision_kaeufer',
            'wohnflaeche', 'nutzflaeche', 'grundstuecksflaeche', 'anzahl_zimmer', 'anzahl_schlafzimmer', 'anzahl_badezimmer',
            'energiepass_baujahr', 'zustand_art', 'energiepass_art', 'energiepass_endenergie', 'energiepass_wertklasse',
            'kontaktperson_vorname', 'kontaktperson_name', 'kontaktperson_firma', 'kontaktperson_email', 'kontaktperson_tel'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }

        // Status field
        if (isset($_POST['_dbw_immo_status'])) {
            $allowed_statuses = array('aktiv', 'reserviert', 'verkauft', 'referenz');
            $new_status = sanitize_text_field($_POST['_dbw_immo_status']);
            if (in_array($new_status, $allowed_statuses)) {
                $old_status = get_post_meta($post_id, '_dbw_immo_status', true);
                update_post_meta($post_id, '_dbw_immo_status', $new_status);

                // Set sales date when status changes to verkauft
                if ($new_status === 'verkauft' && $old_status !== 'verkauft') {
                    update_post_meta($post_id, '_dbw_immo_sales_date', current_time('mysql'));
                }
            }
        }

        // Manual status override lock
        if (isset($_POST['_dbw_immo_manual_status_override'])) {
            update_post_meta($post_id, '_dbw_immo_manual_status_override', '1');
        } else {
            delete_post_meta($post_id, '_dbw_immo_manual_status_override');
        }

        // Ausstattung features (from textarea)
        if (isset($_POST['_dbw_immo_features_text'])) {
            $raw = sanitize_textarea_field($_POST['_dbw_immo_features_text']);
            $features = array_filter(array_map('trim', explode(',', $raw)));
            update_post_meta($post_id, '_dbw_immo_features', $features);
        }

        // Highlight checkbox
        if (isset($_POST['_dbw_immo_is_highlight'])) {
            update_post_meta($post_id, '_dbw_immo_is_highlight', '1');
        } else {
            delete_post_meta($post_id, '_dbw_immo_is_highlight');
        }
    }
}
