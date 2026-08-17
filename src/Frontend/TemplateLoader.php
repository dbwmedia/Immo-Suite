<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

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
        add_action('template_redirect', array($this, 'maybe_redirect_sold_single'));
    }

    /**
     * Whether a property must not have its own detail page.
     *
     * Sold and reference properties are dead ends: no meaningful price, no
     * point in an inquiry form. Site owners can switch their detail pages off
     * and keep them as cards only.
     *
     * @param int $post_id Property ID.
     * @return bool
     */
    public static function single_disabled($post_id)
    {
        $settings = get_option('dbw_immo_suite_settings', array());

        if (empty($settings['disable_single_sold'])) {
            return false;
        }

        $status = get_post_meta($post_id, '_dbw_immo_status', true);

        return in_array($status, array('verkauft', 'referenz'), true);
    }

    /**
     * Where visitors land instead of a disabled detail page:
     * reference page if set up, otherwise the property archive.
     *
     * @return string
     */
    public static function fallback_url()
    {
        $settings = get_option('dbw_immo_suite_settings', array());

        if (!empty($settings['enable_references']) && !empty($settings['reference_page_id'])) {
            $url = get_permalink((int) $settings['reference_page_id']);
            if ($url) {
                return $url;
            }
        }

        $archive = get_post_type_archive_link('immobilie');

        return $archive ? $archive : home_url('/');
    }

    /**
     * Send visitors of a disabled detail page to the reference page.
     *
     * 302 on purpose: the setting can be switched back off, and a permanent
     * redirect would stay cached in browsers long after that.
     */
    public function maybe_redirect_sold_single()
    {
        if (!is_singular('immobilie') || is_preview()) {
            return;
        }

        $post_id = get_queried_object_id();

        if (!$post_id || !self::single_disabled($post_id)) {
            return;
        }

        wp_safe_redirect(self::fallback_url(), 302);
        exit;
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
