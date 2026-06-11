<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Map view for the property archive (Leaflet, local vendor copy).
 * Shows all properties matching the current filter as markers.
 */
class ArchiveMap
{
    const MAX_MARKERS = 200;

    /**
     * Map view is disabled when addresses are hidden — exact markers
     * would leak the location the Makler wants to keep private.
     */
    public static function is_enabled()
    {
        return (bool) get_theme_mod('dbw_immo_archive_show_map_view', true)
            && (bool) get_theme_mod('dbw_immo_single_show_address', true);
    }

    /**
     * Render the (initially hidden) map container + marker data JSON.
     * Called from the archive template after the grid.
     */
    public static function render()
    {
        if (!self::is_enabled()) {
            return;
        }

        $markers = self::collect_markers();
        $map_consent = get_theme_mod('dbw_immo_single_map_consent', true);
        ?>
        <div class="dbw-archive-map-wrapper" id="dbw-archive-map-wrapper" hidden>
            <?php if ($map_consent): ?>
            <div id="dbw-archive-map-consent" class="dbw-map-placeholder">
                <div class="dbw-map-placeholder__inner">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <p class="dbw-map-placeholder__text"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
                        __('Klicken Sie, um die Karte zu laden.', 'dbw-immo-suite'),
                        __('Klicke, um die Karte zu laden.', 'dbw-immo-suite')
                    )); ?></p>
                    <button type="button" class="dbw-btn dbw-btn--ghost dbw-map-placeholder__btn" id="dbw-archive-map-load">
                        <?php esc_html_e('Karte laden', 'dbw-immo-suite'); ?>
                    </button>
                    <p class="dbw-map-placeholder__hint"><?php esc_html_e('Dabei werden Daten an OpenStreetMap uebertragen.', 'dbw-immo-suite'); ?></p>
                </div>
            </div>
            <?php endif; ?>
            <div id="dbw-archive-map" style="<?php echo $map_consent ? 'display:none;' : ''; ?>"></div>
            <p class="dbw-archive-map-empty" data-dbw-map-empty hidden><?php esc_html_e('Keine Objekte mit Kartendaten gefunden.', 'dbw-immo-suite'); ?></p>
        </div>
        <script type="application/json" id="dbw-archive-map-data"><?php
            echo wp_json_encode($markers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?></script>
        <?php
    }

    /**
     * Collect markers for all properties matching the given query args,
     * or the current (filtered) archive query if none are passed.
     */
    public static function collect_markers($override_args = null)
    {
        if (is_array($override_args)) {
            $vars = $override_args;
        } else {
            global $wp_query;
            $vars = $wp_query->query_vars;
        }
        $vars['posts_per_page'] = self::MAX_MARKERS;
        $vars['paged']          = 1;
        $vars['fields']         = 'ids';
        $vars['no_found_rows']  = true;

        $query = new \WP_Query($vars);

        $markers = array();
        if (!empty($query->posts)) {
            // Prime post + meta caches in bulk — get_permalink()/thumbnail
            // would otherwise fire one query per marker
            _prime_post_caches($query->posts, false, true);
            $thumb_ids = array();
            foreach ($query->posts as $post_id) {
                $thumb_id = get_post_thumbnail_id($post_id);
                if ($thumb_id) {
                    $thumb_ids[] = $thumb_id;
                }
            }
            if (!empty($thumb_ids)) {
                _prime_post_caches($thumb_ids, false, true);
            }
        }

        foreach ($query->posts as $post_id) {
            $lat = get_post_meta($post_id, 'geo_breite', true);
            $lng = get_post_meta($post_id, 'geo_laenge', true);
            if (!$lat || !$lng) {
                continue;
            }

            $kaufpreis = get_post_meta($post_id, 'kaufpreis', true);
            $kaltmiete = get_post_meta($post_id, 'kaltmiete', true);
            $price = $kaufpreis ?: $kaltmiete;
            $price_label = $price
                ? \DBW\ImmoSuite\dbw_format_number($price, 'preis') . ' €'
                : __('Auf Anfrage', 'dbw-immo-suite');

            $markers[] = array(
                'id'    => $post_id,
                'lat'   => (float) $lat,
                'lng'   => (float) $lng,
                'title' => CardRenderer::get_display_title($post_id),
                'url'   => get_permalink($post_id),
                'price' => $price_label,
                'img'   => get_the_post_thumbnail_url($post_id, 'medium') ?: '',
            );
        }

        return $markers;
    }
}
