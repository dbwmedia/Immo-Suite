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
delete_option('dbw_immo_xml_hashes');

// Remove legacy per-ZIP hash options (pre v2.2.0)
global $wpdb;
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'dbw\_immo\_last\_xml\_hash\_%'));

// Remove transients
delete_transient('dbw_immo_import_lock');
delete_transient('dbw_immo_batch_zips');
delete_transient('dbw_immo_batch_processed_ids');
delete_transient('dbw_immo_import_progress');
delete_transient('dbw_immo_price_histogram');

// Remove dynamic-key transients (price/sqm comparison cache, rate limits) incl. timeout rows
$transient_patterns = array(
    '\_transient\_dbw\_avg\_sqm\_%',
    '\_transient\_timeout\_dbw\_avg\_sqm\_%',
    '\_transient\_dbw\_contact\_%',
    '\_transient\_timeout\_dbw\_contact\_%',
    '\_transient\_dbw\_expose\_%',
    '\_transient\_timeout\_dbw\_expose\_%',
);
foreach ($transient_patterns as $pattern) {
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern));
}

// Remove license options
delete_option('dbw_immo_license_key');
delete_option('dbw_immo_license_status');

// Remove scheduled cron
wp_clear_scheduled_hook('dbw_immo_cron_hook');
wp_clear_scheduled_hook('dbw_immo_inquiry_cleanup');
wp_clear_scheduled_hook('dbw_immo_monitor_check');

// Monitoring/onboarding state
delete_option('dbw_immo_import_alert');
delete_option('dbw_immo_onboarding_dismissed');

// Delete stored inquiries incl. meta (PII — must not survive an uninstall)
$inquiry_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'immo_anfrage'");
foreach ($inquiry_ids as $inquiry_id) {
    wp_delete_post((int) $inquiry_id, true);
}

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
