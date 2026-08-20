<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Lightweight expose view counter.
 *
 * Counts detail page views per property in two post meta fields: an all-time
 * total and a rolling weekly counter that the weekly report resets after
 * sending. Server-side only - no cookies, no personal data, no consent needed.
 */
class Stats
{
    const META_TOTAL = '_dbw_immo_views';
    const META_WEEK  = '_dbw_immo_views_week';

    public function init()
    {
        add_action('template_redirect', array($this, 'maybe_count_view'), 20);
    }

    /**
     * Count one view of a property detail page.
     *
     * Skipped for editors/admins (they would inflate their own numbers) and
     * for obvious crawlers. Deliberately no per-visitor dedupe: that would
     * need a cookie or IP storage, and "page impressions" is honest enough
     * for a trend line.
     */
    public function maybe_count_view()
    {
        if (!is_singular('immobilie') || is_preview() || is_admin()) {
            return;
        }

        if (is_user_logged_in() && current_user_can('edit_posts')) {
            return;
        }

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|scan|curl|wget|lighthouse|monitor/i', $ua)) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        self::increment($post_id, self::META_TOTAL);
        self::increment($post_id, self::META_WEEK);
    }

    /**
     * Atomic-ish increment (single UPDATE, INSERT on first view).
     */
    private static function increment($post_id, $meta_key)
    {
        global $wpdb;

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1
             WHERE post_id = %d AND meta_key = %s",
            $post_id,
            $meta_key
        ));

        if (!$updated) {
            add_post_meta($post_id, $meta_key, 1, true);
        }

        wp_cache_delete($post_id, 'post_meta');
    }

    public static function get_views($post_id)
    {
        return (int) get_post_meta($post_id, self::META_TOTAL, true);
    }

    public static function get_week_views($post_id)
    {
        return (int) get_post_meta($post_id, self::META_WEEK, true);
    }

    /**
     * Top viewed properties of the current week.
     *
     * @return array[] [ ['id' => int, 'views' => int], ... ]
     */
    public static function top_week($limit = 3)
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id, CAST(pm.meta_value AS UNSIGNED) AS views
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND p.post_type = 'immobilie' AND p.post_status = 'publish'
               AND CAST(pm.meta_value AS UNSIGNED) > 0
             ORDER BY views DESC
             LIMIT %d",
            self::META_WEEK,
            (int) $limit
        ));

        $top = array();
        foreach ((array) $rows as $row) {
            $top[] = array('id' => (int) $row->post_id, 'views' => (int) $row->views);
        }

        return $top;
    }

    /**
     * Reset the weekly counters (called after the weekly report went out).
     */
    public static function reset_week()
    {
        global $wpdb;

        $wpdb->delete($wpdb->postmeta, array('meta_key' => self::META_WEEK));
    }
}
