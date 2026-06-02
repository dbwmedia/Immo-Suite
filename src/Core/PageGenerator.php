<?php

if (!defined('ABSPATH')) { exit; }

namespace DBW\ImmoSuite\Core;

/**
 * Handles automatic page generation
 */
class PageGenerator
{

    public function init()
    {
        add_action('dbw_immo_references_enabled', array($this, 'create_reference_page'));
    }

    /**
     * Create the reference page if it doesn't exist.
     *
     * @param array $settings
     */
    public function create_reference_page($settings)
    {
        $slug = !empty($settings['reference_slug']) ? $settings['reference_slug'] : 'referenzen';
        $title = !empty($settings['reference_badge_text']) ? $settings['reference_badge_text'] : 'Referenzen';

        // Check if page exists by slug
        $existing_page = get_page_by_path($slug);

        $page_id = 0;

        if ($existing_page && $existing_page->post_status !== 'trash') {
            $page_id = $existing_page->ID;
        } else {
            $page_data = array(
                'post_title'   => $title,
                'post_content' => '<!-- wp:dbw/immo-references {"status":["verkauft","referenz"],"postsPerPage":12} /-->',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $slug,
            );

            $page_id = wp_insert_post($page_data);
        }

        if ($page_id && !is_wp_error($page_id)) {
            $current_options = get_option('dbw_immo_suite_settings');
            if (empty($current_options['reference_page_id']) || $current_options['reference_page_id'] != $page_id) {
                $current_options['reference_page_id'] = $page_id;
                update_option('dbw_immo_suite_settings', $current_options);
            }
        } else {
            // Page creation failed — show admin notice
            set_transient('dbw_immo_reference_page_error', true, 60);
            add_action('admin_notices', function () {
                if (get_transient('dbw_immo_reference_page_error')) {
                    echo '<div class="notice notice-error"><p><strong>DBW Immo Suite:</strong> '
                        . esc_html__('Die Referenz-Seite konnte nicht automatisch erstellt werden. Bitte erstellen Sie manuell eine Seite mit dem Referenzen-Block.', 'dbw-immo-suite')
                        . '</p></div>';
                    delete_transient('dbw_immo_reference_page_error');
                }
            });
        }

        flush_rewrite_rules();
    }
}
