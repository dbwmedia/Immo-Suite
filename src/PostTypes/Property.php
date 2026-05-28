<?php

namespace DBW\ImmoSuite\PostTypes;

/**
 * Property (Immobilie) Custom Post Type
 */
class Property
{

    const POST_TYPE = 'immobilie';

    /**
     * Register the custom post type.
     */
    public function register_post_type()
    {
        $settings = get_option('dbw_immo_suite_settings');
        $cpt_slug = !empty($settings['cpt_slug']) ? $settings['cpt_slug'] : 'immobilien';

        $labels = array(
            'name' => _x('Immobilien', 'Post Type General Name', 'dbw-immo-suite'),
            'singular_name' => _x('Immobilie', 'Post Type Singular Name', 'dbw-immo-suite'),
            'menu_name' => __('ImmoSuite', 'dbw-immo-suite'),
            'name_admin_bar' => __('Immobilie', 'dbw-immo-suite'),
            'archives' => __('Immobilien Archiv', 'dbw-immo-suite'),
            'attributes' => __('Immobilien Attribute', 'dbw-immo-suite'),
            'parent_item_colon' => __('Eltern-Immobilie:', 'dbw-immo-suite'),
            'all_items' => __('Alle Immobilien', 'dbw-immo-suite'),
            'add_new_item' => __('Neue Immobilie hinzufügen', 'dbw-immo-suite'),
            'add_new' => __('Neu hinzufügen', 'dbw-immo-suite'),
            'new_item' => __('Neue Immobilie', 'dbw-immo-suite'),
            'edit_item' => __('Immobilie bearbeiten', 'dbw-immo-suite'),
            'update_item' => __('Immobilie aktualisieren', 'dbw-immo-suite'),
            'view_item' => __('Immobilie ansehen', 'dbw-immo-suite'),
            'view_items' => __('Immobilien ansehen', 'dbw-immo-suite'),
            'search_items' => __('Immobilie suchen', 'dbw-immo-suite'),
            'not_found' => __('Nicht gefunden', 'dbw-immo-suite'),
            'not_found_in_trash' => __('Nicht im Papierkorb gefunden', 'dbw-immo-suite'),
            'featured_image' => __('Titelbild', 'dbw-immo-suite'),
            'set_featured_image' => __('Titelbild festlegen', 'dbw-immo-suite'),
            'remove_featured_image' => __('Titelbild entfernen', 'dbw-immo-suite'),
            'use_featured_image' => __('Als Titelbild verwenden', 'dbw-immo-suite'),
            'insert_into_item' => __('In Immobilie einfügen', 'dbw-immo-suite'),
            'uploaded_to_this_item' => __('Zu dieser Immobilie hochgeladen', 'dbw-immo-suite'),
            'items_list' => __('Immobilien Liste', 'dbw-immo-suite'),
            'items_list_navigation' => __('Immobilien Liste Navigation', 'dbw-immo-suite'),
            'filter_items_list' => __('Immobilien Liste filtern', 'dbw-immo-suite'),
        );
        $args = array(
            'label' => __('Immobilie', 'dbw-immo-suite'),
            'description' => __('Immobilienverwaltung für DBW ImmoSuite', 'dbw-immo-suite'),
            'labels' => $labels,
            'supports' => array('title', 'thumbnail', 'revisions'), // Removed 'editor' and 'custom-fields' (handled by PropertyDetails)
            'taxonomies' => array('objektart', 'vermarktungsart', 'ort'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-admin-home',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => $cpt_slug,
            'rewrite' => array('slug' => $cpt_slug, 'with_front' => false),
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'show_in_rest' => false, // Disable Gutenberg
        );
        register_post_type(self::POST_TYPE, $args);
    }
}