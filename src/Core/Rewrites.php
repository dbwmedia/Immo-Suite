<?php

namespace DBW\ImmoSuite\Core;

/**
 * Handles URL Rewrites and Query Vars
 */
class Rewrites
{

    public function init()
    {
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('page_link', array($this, 'filter_reference_page_link'), 10, 2);
        add_action('template_redirect', array($this, 'redirect_reference_page'));
    }

    /**
     * Register custom query var (if still needed elsewhere)
     */
    public function register_query_vars($vars)
    {
        $vars[] = 'dbw_immo_view';
        return $vars;
    }

    /**
     * Add Rewrite Rules
     */
    public function add_rewrite_rules()
    {
        $settings = get_option('dbw_immo_suite_settings');

        // Only proceed if reference system is active and page ID exists
        if (empty($settings['enable_references']) || empty($settings['reference_page_id'])) {
            return;
        }

        // Validate that the reference page still exists
        $page_id = $settings['reference_page_id'];
        if (get_post_status($page_id) === false) {
            // Page was deleted — trigger re-creation
            do_action('dbw_immo_references_enabled', $settings);
            $settings = get_option('dbw_immo_suite_settings');
            if (empty($settings['reference_page_id'])) {
                return;
            }
        }

        $cpt_slug = !empty($settings['cpt_slug']) ? $settings['cpt_slug'] : 'immobilien';
        $ref_slug = !empty($settings['reference_slug']) ? $settings['reference_slug'] : 'referenzen';
        $page_id = $settings['reference_page_id'];

        // Map /immobilien/referenzen/ directly to the created page ID
        // This ensures the Gutenberg Block on that page is rendered correctly as the main query
        add_rewrite_rule(
            '^' . $cpt_slug . '/' . $ref_slug . '/?$',
            'index.php?page_id=' . $page_id,
            'top'
        );

        // Also support pagination /immobilien/referenzen/page/2/
        add_rewrite_rule(
            '^' . $cpt_slug . '/' . $ref_slug . '/page/([0-9]{1,})/?$',
            'index.php?page_id=' . $page_id . '&paged=$matches[1]',
            'top'
        );
    }

    /**
     * Force the permalink of the reference page to use the nested URL structure
     */
    public function filter_reference_page_link($link, $post_id)
    {
        $settings = get_option('dbw_immo_suite_settings');

        if (!empty($settings['enable_references']) && !empty($settings['reference_page_id']) && $post_id == $settings['reference_page_id']) {
            $cpt_slug = !empty($settings['cpt_slug']) ? $settings['cpt_slug'] : 'immobilien';
            $ref_slug = !empty($settings['reference_slug']) ? $settings['reference_slug'] : 'referenzen';

            return home_url(user_trailingslashit($cpt_slug . '/' . $ref_slug));
        }

        return $link;
    }

    /**
     * Redirect direct access of the isolated page to the new nested URL
     */
    public function redirect_reference_page()
    {
        $settings = get_option('dbw_immo_suite_settings');

        if (empty($settings['enable_references']) || empty($settings['reference_page_id'])) {
            return;
        }

        $page_id = $settings['reference_page_id'];

        // If we are viewing the reference page but the requested URL is NOT the desired nested slug
        if (is_page($page_id)) {
            $cpt_slug = !empty($settings['cpt_slug']) ? $settings['cpt_slug'] : 'immobilien';
            $ref_slug = !empty($settings['reference_slug']) ? $settings['reference_slug'] : 'referenzen';
            $target_path = '/' . $cpt_slug . '/' . $ref_slug . '/';

            // Check if the current request URI matches the desired target path
            $current_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            // If they don't match (e.g., accessed via /referenzen/), redirect to the correct nested URL
            if (strpos($current_url, $target_path) === false) {
                // Determine if there is pagination in the query string or path
                $paged = get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1);

                $redirect_url = home_url(user_trailingslashit($cpt_slug . '/' . $ref_slug));
                if ($paged > 1) {
                    $redirect_url = user_trailingslashit($redirect_url . 'page/' . $paged);
                }

                wp_safe_redirect($redirect_url, 301);
                exit;
            }
        }
    }
}