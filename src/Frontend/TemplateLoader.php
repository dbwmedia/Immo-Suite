<?php

if (!defined('ABSPATH')) { exit; }

namespace DBW\ImmoSuite\Frontend;

/**
 * Loads the correct template files for the plugin.
 */
class TemplateLoader
{

    /**
     * Register the filters.
     */
    public function init()
    {
        add_filter('template_include', array($this, 'template_include'));
    }

    /**
     * Load custom templates for Custom Post Type.
     *
     * @param string $template Path to the template.
     * @return string Path to the template.
     */
    public function template_include($template)
    {
        if (is_singular('immobilie')) {
            $new_template = $this->locate_template('single-immobilie.php');
            if ('' !== $new_template) {
                return $new_template;
            }
        }

        if (is_post_type_archive('immobilie') || is_tax('objektart') || is_tax('vermarktungsart') || is_tax('ort')) {
            $new_template = $this->locate_template('archive-immobilie.php');
            if ('' !== $new_template) {
                return $new_template;
            }
        }

        return $template;
    }

    /**
     * Locate template in theme or plugin.
     *
     * @param string $template_name Template filename.
     * @return string Path to template.
     */
    private function locate_template($template_name)
    {
        // Check theme folder first
        $theme_template = locate_template(array('dbw-immo-suite/' . $template_name));
        if ($theme_template) {
            return $theme_template;
        }

        // Check plugin templates folder
        $plugin_template = DBW_IMMO_SUITE_PATH . 'templates/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return '';
    }
}