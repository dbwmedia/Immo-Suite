<?php
/**
 * Uninstall routine for DBW Immo Suite.
 * Fired when the plugin is deleted via the WordPress admin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('dbw_immo_suite_settings');
delete_option('dbw_immo_import_history');

// Remove all hash options for ZIP files
global $wpdb;
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'dbw\_immo\_last\_xml\_hash\_%'));

// Remove transients
delete_transient('dbw_immo_import_lock');
delete_transient('dbw_immo_batch_zips');
delete_transient('dbw_immo_batch_processed_ids');
delete_transient('dbw_immo_import_progress');

// Remove license options
delete_option('dbw_immo_license_key');
delete_option('dbw_immo_license_status');

// Remove scheduled cron
wp_clear_scheduled_hook('dbw_immo_cron_hook');

// Remove Customizer theme_mods
$mods_to_remove = array(
    'dbw_immo_color_primary', 'dbw_immo_color_secondary', 'dbw_immo_color_accent', 'dbw_immo_color_light',
    'dbw_immo_border_radius', 'dbw_immo_archive_per_page', 'dbw_immo_archive_columns',
    'dbw_immo_archive_show_year', 'dbw_immo_archive_show_area', 'dbw_immo_archive_show_rooms',
    'dbw_immo_archive_show_price', 'dbw_immo_archive_show_energy_class',
    'dbw_immo_single_show_map', 'dbw_immo_single_map_consent', 'dbw_immo_single_show_energy', 'dbw_immo_single_show_gallery',
    'dbw_immo_single_show_contact', 'dbw_immo_single_show_share', 'dbw_immo_single_show_print',
    'dbw_immo_single_show_similar', 'dbw_immo_single_show_calculator', 'dbw_immo_single_show_infra_score',
    'dbw_immo_single_show_price_sqm', 'dbw_immo_single_show_whatsapp', 'dbw_immo_whatsapp_floating',
    'dbw_immo_highlights_bg_style', 'dbw_immo_highlights_text_color',
    'dbw_immo_expose_btn_text',
    'dbw_immo_archive_top_spacing', 'dbw_immo_single_top_spacing',
    'dbw_immo_archive_show_price_sqm',
    'dbw_immo_single_show_address', 'dbw_immo_single_show_expose_request',
    'dbw_immo_archive_show_favorites', 'dbw_immo_archive_show_map_view',
    'dbw_immo_single_section_nav',
);

foreach ($mods_to_remove as $mod) {
    remove_theme_mod($mod);
}

// Note: We intentionally do NOT delete the CPT posts, taxonomies, or meta data
// to prevent accidental data loss. Users can delete posts manually if needed.
