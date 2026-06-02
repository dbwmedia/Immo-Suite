<?php

namespace DBW\ImmoSuite\Taxonomies;

if (!defined('ABSPATH')) { exit; }

/**
 * Taxonomy: Objektart
 */
class PropertyType
{

    const TAXONOMY = 'objektart';

    public function register_taxonomy()
    {
        $labels = array(
            'name' => _x('Objektarten', 'taxonomy general name', 'dbw-immo-suite'),
            'singular_name' => _x('Objektart', 'taxonomy singular name', 'dbw-immo-suite'),
            'search_items' => __('Objektarten suchen', 'dbw-immo-suite'),
            'all_items' => __('Alle Objektarten', 'dbw-immo-suite'),
            'parent_item' => __('Übergeordnete Objektart', 'dbw-immo-suite'),
            'parent_item_colon' => __('Übergeordnete Objektart:', 'dbw-immo-suite'),
            'edit_item' => __('Objektart bearbeiten', 'dbw-immo-suite'),
            'update_item' => __('Objektart aktualisieren', 'dbw-immo-suite'),
            'add_new_item' => __('Neue Objektart hinzufügen', 'dbw-immo-suite'),
            'new_item_name' => __('Neuer Objektart Name', 'dbw-immo-suite'),
            'menu_name' => __('Objektart', 'dbw-immo-suite'),
        );

        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'objektart'),
            'show_in_rest' => true,
        );

        register_taxonomy(self::TAXONOMY, array('immobilie'), $args);
    }
}
