<?php

namespace DBW\ImmoSuite\Taxonomies;

if (!defined('ABSPATH')) { exit; }

/**
 * Taxonomy: Vermarktungsart (Kauf, Miete, etc.)
 */
class MarketingType
{

    const TAXONOMY = 'vermarktungsart';

    public function register_taxonomy()
    {
        $labels = array(
            'name' => _x('Vermarktungsarten', 'taxonomy general name', 'dbw-immo-suite'),
            'singular_name' => _x('Vermarktungsart', 'taxonomy singular name', 'dbw-immo-suite'),
            'search_items' => __('Vermarktungsarten suchen', 'dbw-immo-suite'),
            'all_items' => __('Alle Vermarktungsarten', 'dbw-immo-suite'),
            'parent_item' => __('Übergeordnete Vermarktungsart', 'dbw-immo-suite'),
            'parent_item_colon' => __('Übergeordnete Vermarktungsart:', 'dbw-immo-suite'),
            'edit_item' => __('Vermarktungsart bearbeiten', 'dbw-immo-suite'),
            'update_item' => __('Vermarktungsart aktualisieren', 'dbw-immo-suite'),
            'add_new_item' => __('Neue Vermarktungsart hinzufügen', 'dbw-immo-suite'),
            'new_item_name' => __('Neuer Vermarktungsart Name', 'dbw-immo-suite'),
            'menu_name' => __('Vermarktungsart', 'dbw-immo-suite'),
        );

        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'vermarktungsart'),
            'show_in_rest' => true,
        );

        register_taxonomy(self::TAXONOMY, array('immobilie'), $args);
    }
}
