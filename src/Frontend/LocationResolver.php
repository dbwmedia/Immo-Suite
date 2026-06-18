<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Resolves the current location slug from the page context.
 *
 * Resolution order (first match wins):
 * 1. Taxonomy archive: is_tax('ort') → term slug
 * 2. Singular + meta/ACF field (default key: ort_name) → sanitize_title()
 * 3. Singular + assigned 'ort' taxonomy term → first term slug
 * 4. Empty string (no location resolved)
 *
 * Filters:
 * - dbw_immo_location_meta_key: Change the meta field key (default: ort_name)
 * - dbw_immo_resolved_location: Override the resolved slug
 */
class LocationResolver
{

    /**
     * Resolve the current location slug from page context.
     *
     * @return string Location term slug or empty string.
     */
    public static function resolve()
    {
        $slug = '';

        // 1. Taxonomy archive: ort term archive
        if (is_tax('ort')) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $slug = $term->slug;
            }
        }

        // 2. Singular: read meta field (ACF or post_meta)
        if (empty($slug) && is_singular()) {
            $post_id  = get_queried_object_id();
            $meta_key = apply_filters('dbw_immo_location_meta_key', 'ort_name');

            $raw = '';
            if (function_exists('get_field')) {
                $raw = get_field($meta_key, $post_id);
            }
            if (empty($raw)) {
                $raw = get_post_meta($post_id, $meta_key, true);
            }

            if (!empty($raw) && is_string($raw)) {
                $slug = sanitize_title($raw);
            }
        }

        // 3. Singular: assigned ort taxonomy term
        if (empty($slug) && is_singular()) {
            $post_id = get_queried_object_id();
            $terms   = get_the_terms($post_id, 'ort');
            if (!empty($terms) && !is_wp_error($terms)) {
                $slug = $terms[0]->slug;
            }
        }

        return apply_filters('dbw_immo_resolved_location', $slug, get_queried_object());
    }
}
