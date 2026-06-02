<?php

namespace DBW\ImmoSuite\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * Handles all Customizer settings for the plugin.
 */
class Customizer
{
    /**
     * Initialize the Customizer.
     */
    public function init()
    {
        add_action('customize_register', array($this, 'register_settings'));
        add_action('wp_head', array($this, 'output_custom_css'));
        add_filter('pre_get_posts', array($this, 'modify_archive_query'));
    }

    /**
     * Register panels, sections, and controls.
     *
     * @param \WP_Customize_Manager $wp_customize
     */
    public function register_settings($wp_customize)
    {
        // 1. Panel: Immobilien Suite
        $wp_customize->add_panel('dbw_immo_panel', array(
            'title' => __('Immobilien Suite', 'dbw-immo-suite'),
            'description' => __('Einstellungen für das Immobilien Plugin', 'dbw-immo-suite'),
            'priority' => 20,
        ));

        // 2. Section: Design System (Global)
        $wp_customize->add_section('dbw_immo_design_section', array(
            'title' => __('Design System', 'dbw-immo-suite'),
            'panel' => 'dbw_immo_panel',
            'priority' => 10,
            'description' => __('Globale Design-Einstellungen für alle Immobilien-Elemente.', 'dbw-immo-suite'),
        ));

        $this->add_color_setting($wp_customize, 'dbw_immo_color_primary', '#2c3e50', __('Hauptfarbe (Primary)', 'dbw-immo-suite'), 'dbw_immo_design_section');
        $this->add_color_setting($wp_customize, 'dbw_immo_color_secondary', '#34495e', __('Sekundärfarbe', 'dbw-immo-suite'), 'dbw_immo_design_section');
        $this->add_color_setting($wp_customize, 'dbw_immo_color_accent', '#3498db', __('Akzentfarbe (Buttons/Links)', 'dbw-immo-suite'), 'dbw_immo_design_section');
        $this->add_color_setting($wp_customize, 'dbw_immo_color_light', '#ecf0f1', __('Hintergrund Hell', 'dbw-immo-suite'), 'dbw_immo_design_section');

        // Border Radius
        $wp_customize->add_setting('dbw_immo_border_radius', array(
            'default' => 8,
            'sanitize_callback' => 'absint',
        ));
        $wp_customize->add_control('dbw_immo_border_radius', array(
            'label' => __('Eckenradius (px)', 'dbw-immo-suite'),
            'section' => 'dbw_immo_design_section',
            'type' => 'number',
            'input_attrs' => array('min' => 0, 'max' => 50),
        ));

        // 3. Section: Archiv & Suche
        $wp_customize->add_section('dbw_immo_archive_section', array(
            'title' => __('Archiv & Suche', 'dbw-immo-suite'),
            'panel' => 'dbw_immo_panel',
            'priority' => 20,
        ));

        // Layout Settings
        $wp_customize->add_setting('dbw_immo_archive_per_page', array(
            'default' => 9,
            'sanitize_callback' => 'absint',
        ));
        $wp_customize->add_control('dbw_immo_archive_per_page', array(
            'label' => __('Objekte pro Seite', 'dbw-immo-suite'),
            'section' => 'dbw_immo_archive_section',
            'type' => 'number',
            'input_attrs' => array('min' => 1, 'max' => 100),
        ));

        $wp_customize->add_setting('dbw_immo_archive_columns', array(
            'default' => 3,
            'sanitize_callback' => 'absint',
        ));
        $wp_customize->add_control('dbw_immo_archive_columns', array(
            'label' => __('Spalten (Desktop Grid)', 'dbw-immo-suite'),
            'section' => 'dbw_immo_archive_section',
            'type' => 'select',
            'choices' => array(
                2 => '2 Spalten',
                3 => '3 Spalten',
                4 => '4 Spalten',
            ),
        ));

        // Toggles
        $this->add_toggle_setting($wp_customize, 'dbw_immo_archive_show_year', true, __('Baujahr anzeigen', 'dbw-immo-suite'), 'dbw_immo_archive_section');
        $this->add_toggle_setting($wp_customize, 'dbw_immo_archive_show_area', true, __('Wohnfläche anzeigen', 'dbw-immo-suite'), 'dbw_immo_archive_section');
        $this->add_toggle_setting($wp_customize, 'dbw_immo_archive_show_rooms', true, __('Zimmer anzeigen', 'dbw-immo-suite'), 'dbw_immo_archive_section');
        $this->add_toggle_setting($wp_customize, 'dbw_immo_archive_show_price', true, __('Preis anzeigen', 'dbw-immo-suite'), 'dbw_immo_archive_section');
        $this->add_toggle_setting($wp_customize, 'dbw_immo_archive_show_energy_class', true, __('Energieklasse (Flag) anzeigen', 'dbw-immo-suite'), 'dbw_immo_archive_section');


        // 4. Section: Detailseite (Single)
        $wp_customize->add_section('dbw_immo_single_section', array(
            'title' => __('Detailansicht', 'dbw-immo-suite'),
            'panel' => 'dbw_immo_panel',
            'priority' => 30,
        ));

        $this->add_toggle_setting($wp_customize, 'dbw_immo_single_show_map', true, __('Lage / Karte anzeigen', 'dbw-immo-suite'), 'dbw_immo_single_section');
        $this->add_toggle_setting($wp_customize, 'dbw_immo_single_show_energy', true, __('Energieausweis anzeigen', 'dbw-immo-suite'), 'dbw_immo_single_section');
        $this->add_toggle_setting($wp_customize, 'dbw_immo_single_show_gallery', false, __('Galerie-Modul anzeigen (Beta)', 'dbw-immo-suite'), 'dbw_immo_single_section');
        $this->add_toggle_setting($wp_customize, 'dbw_immo_single_show_contact', true, __('Kontaktbox anzeigen', 'dbw-immo-suite'), 'dbw_immo_single_section');
        
        $this->add_toggle_setting($wp_customize, 'dbw_immo_single_show_share', true, __('Teilen-Button im Slider anzeigen', 'dbw-immo-suite'), 'dbw_immo_single_section');
        $this->add_toggle_setting($wp_customize, 'dbw_immo_single_show_print', true, __('Drucken-Button im Slider anzeigen', 'dbw-immo-suite'), 'dbw_immo_single_section');
        $this->add_toggle_setting($wp_customize, 'dbw_immo_single_show_similar', true, __('Ähnliche Objekte anzeigen', 'dbw-immo-suite'), 'dbw_immo_single_section');

        // Highlights Box
        $wp_customize->add_setting('dbw_immo_highlights_bg_style', array(
            'default' => 'primary',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('dbw_immo_highlights_bg_style', array(
            'label' => __('Highlights-Box Hintergrund', 'dbw-immo-suite'),
            'section' => 'dbw_immo_single_section',
            'type' => 'select',
            'choices' => array(
                'primary' => __('Primärfarbe', 'dbw-immo-suite'),
                'accent' => __('Akzentfarbe', 'dbw-immo-suite')
            ),
        ));

        $wp_customize->add_setting('dbw_immo_highlights_text_color', array(
            'default' => '#ffffff',
            'sanitize_callback' => 'sanitize_hex_color',
        ));
        $wp_customize->add_control(new \WP_Customize_Color_Control($wp_customize, 'dbw_immo_highlights_text_color', array(
            'label' => __('Highlights-Box Textfarbe', 'dbw-immo-suite'),
            'section' => 'dbw_immo_single_section',
        )));

        // Text Labels
        $wp_customize->add_setting('dbw_immo_expose_btn_text', array(
            'default' => 'Zum Exposé',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('dbw_immo_expose_btn_text', array(
            'label' => __('Button Text "Zum Exposé"', 'dbw-immo-suite'),
            'section' => 'dbw_immo_design_section', // Kept in Design as requested
            'type' => 'text',
        ));
    }

    /**
     * Helper to add color settings.
     */
    private function add_color_setting($wp_customize, $id, $default, $label, $section)
    {
        $wp_customize->add_setting($id, array(
            'default' => $default,
            'sanitize_callback' => 'sanitize_hex_color',
        ));
        $wp_customize->add_control(new \WP_Customize_Color_Control($wp_customize, $id, array(
            'label' => $label,
            'section' => $section,
        )));
    }

    /**
     * Helper to add toggle (checkbox) settings.
     */
    private function add_toggle_setting($wp_customize, $id, $default, $label, $section)
    {
        $wp_customize->add_setting($id, array(
            'default' => $default,
            'sanitize_callback' => array($this, 'sanitize_checkbox'), // FIXED: Use class method
        ));
        $wp_customize->add_control($id, array(
            'label' => $label,
            'section' => $section,
            'type' => 'checkbox',
        ));
    }

    /**
     * Sanitize checkbox helper
     */
    public function sanitize_checkbox($checked)
    {
        return (isset($checked) && $checked == true) ? true : false;
    }

    /**
     * Output Dynamic CSS to wp_head.
     */
    public function output_custom_css()
    {
        $primary = get_theme_mod('dbw_immo_color_primary', '#2c3e50');
        $secondary = get_theme_mod('dbw_immo_color_secondary', '#34495e');
        $accent = get_theme_mod('dbw_immo_color_accent', '#3498db');
        $light = get_theme_mod('dbw_immo_color_light', '#ecf0f1');
        $radius = get_theme_mod('dbw_immo_border_radius', 8);

        // Columns logic
        $cols = get_theme_mod('dbw_immo_archive_columns', 3);
        $grid_style = "";

        if ($cols != 3) {
            $grid_style = "
            @media (min-width: 1025px) {
                #dbw-immo-suite .dbw-property-grid:not(.is-list-view) {
                    grid-template-columns: repeat({$cols}, 1fr) !important;
                }
            }
            ";
        }

        echo "<style type='text/css'>
            :root {
                --dbw-primary: {$primary};
                --dbw-secondary: {$secondary};
                --dbw-accent: {$accent};
                --dbw-light: {$light};
                --dbw-radius: {$radius}px;
            }
            {$grid_style}
        </style>";
    }

    /**
     * Modify the main query for archives to respect 'posts_per_page'.
     */
    public function modify_archive_query($query)
    {
        if (!is_admin() && $query->is_main_query() && (is_post_type_archive('immobilie') || is_tax('objektart') || is_tax('vermarktungsart') || is_tax('ort'))) {
            $per_page = get_theme_mod('dbw_immo_archive_per_page', 9);
            $query->set('posts_per_page', $per_page);
        }
    }
}

/**
 * Sanitize checkbox helper (global fallback to prevent fatal errors)
 */
function dbw_immo_sanitize_checkbox($checked)
{
    return (isset($checked) && $checked == true) ? true : false;
}
