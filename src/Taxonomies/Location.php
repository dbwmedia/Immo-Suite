<?php

namespace DBW\ImmoSuite\Taxonomies;

if (!defined('ABSPATH')) { exit; }

/**
 * Taxonomy: Ort / Region
 */
class Location
{

    const TAXONOMY = 'ort';

    public function register_taxonomy()
    {
        $labels = array(
            'name' => _x('Orte', 'taxonomy general name', 'dbw-immo-suite'),
            'singular_name' => _x('Ort', 'taxonomy singular name', 'dbw-immo-suite'),
            'search_items' => __('Orte suchen', 'dbw-immo-suite'),
            'all_items' => __('Alle Orte', 'dbw-immo-suite'),
            'parent_item' => __('Übergeordneter Ort', 'dbw-immo-suite'),
            'parent_item_colon' => __('Übergeordneter Ort:', 'dbw-immo-suite'),
            'edit_item' => __('Ort bearbeiten', 'dbw-immo-suite'),
            'update_item' => __('Ort aktualisieren', 'dbw-immo-suite'),
            'add_new_item' => __('Neuen Ort hinzufügen', 'dbw-immo-suite'),
            'new_item_name' => __('Neuer Ort Name', 'dbw-immo-suite'),
            'menu_name' => __('Ort', 'dbw-immo-suite'),
        );

        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'ort'),
            'show_in_rest' => true,
        );

        register_taxonomy(self::TAXONOMY, array('immobilie'), $args);
    }
}
