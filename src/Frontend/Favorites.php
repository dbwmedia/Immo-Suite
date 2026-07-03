<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Client-side favorites ("Merkliste") stored in localStorage.
 * No accounts, no cookies, no PII — GDPR-neutral by design.
 */
class Favorites
{
    const MAX_IDS = 60;

    public function init()
    {
        add_action('wp_ajax_dbw_immo_favorites', array($this, 'ajax_render_favorites'));
        add_action('wp_ajax_nopriv_dbw_immo_favorites', array($this, 'ajax_render_favorites'));
    }

    /**
     * Check if the favorites feature is enabled.
     */
    public static function is_enabled()
    {
        return (bool) get_theme_mod('dbw_immo_archive_show_favorites', true);
    }

    /**
     * Heart button rendered on every property card (called from CardRenderer)
     * and, with the --single modifier, in the gallery toolbar of the detail page.
     */
    public static function render_card_button($post_id, $extra_class = '')
    {
        if (!self::is_enabled()) {
            return;
        }
        ?>
        <button type="button"
                class="dbw-fav-btn<?php echo $extra_class ? ' ' . esc_attr($extra_class) : ''; ?>"
                data-dbw-fav="<?php echo (int) $post_id; ?>"
                aria-pressed="false"
                aria-label="<?php esc_attr_e('Zur Merkliste hinzufuegen', 'dbw-immo-suite'); ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </button>
        <?php
    }

    /**
     * Toolbar toggle button for the archive header (called from Filter).
     */
    public static function render_toolbar_button()
    {
        if (!self::is_enabled()) {
            return;
        }
        ?>
        <button type="button" id="dbw-fav-toggle" class="dbw-fav-toggle" aria-pressed="false" hidden>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span class="dbw-fav-toggle__label"><?php esc_html_e('Merkliste', 'dbw-immo-suite'); ?></span>
            <span class="dbw-fav-count" data-dbw-fav-count>0</span>
        </button>
        <?php
    }

    /**
     * AJAX: render favorite cards for a list of IDs.
     * Read-only, published properties only — no nonce needed (public data, cache-safe).
     */
    public function ajax_render_favorites()
    {
        $raw = isset($_POST['ids']) ? sanitize_text_field(wp_unslash($_POST['ids'])) : '';
        $ids = array_filter(array_map('absint', explode(',', $raw)));
        $ids = array_slice(array_values(array_unique($ids)), 0, self::MAX_IDS);

        if (empty($ids)) {
            wp_send_json_success(array('html' => '', 'ids' => array()));
        }

        $query = new \WP_Query(array(
            'post_type'      => 'immobilie',
            'post_status'    => 'publish',
            'post__in'       => $ids,
            'orderby'        => 'post__in',
            'posts_per_page' => self::MAX_IDS,
            'no_found_rows'  => true,
        ));

        ob_start();
        $found = array();
        while ($query->have_posts()) {
            $query->the_post();
            $found[] = get_the_ID();
            CardRenderer::render();
        }
        wp_reset_postdata();

        wp_send_json_success(array(
            'html' => ob_get_clean(),
            'ids'  => $found, // lets the client prune deleted/unpublished IDs
        ));
    }
}
