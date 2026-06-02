<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Outputs Open Graph and basic SEO meta tags for property pages.
 * Compatible with Yoast/RankMath — they override these if active.
 */
class SeoMeta
{

    public function init()
    {
        add_action('wp_head', array($this, 'output_meta_tags'), 1);
        add_action('wp_head', array($this, 'output_archive_meta_tags'), 1);
        add_action('wp_head', array($this, 'output_canonical_archive'), 1);
        add_action('wp_head', array($this, 'output_robots_noindex'), 1);
        add_filter('document_title_parts', array($this, 'filter_document_title'));
        add_filter('wp_sitemaps_posts_query_args', array($this, 'filter_sitemap_entries'), 10, 2);
    }

    public function output_meta_tags()
    {
        if (!is_singular('immobilie')) {
            return;
        }

        // Don't output if a major SEO plugin is handling this
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('FLAVOR_SEO_VERSION')) {
            return;
        }

        $id = get_the_ID();
        $title = get_the_title($id);
        $url = get_permalink($id);

        // Build description from property data
        $city = get_post_meta($id, 'ort', true);
        $area = get_post_meta($id, 'wohnflaeche', true);
        $rooms = get_post_meta($id, 'anzahl_zimmer', true);
        $kaufpreis = get_post_meta($id, 'kaufpreis', true);
        $kaltmiete = get_post_meta($id, 'kaltmiete', true);

        $parts = array();
        if ($city) {
            $parts[] = $city;
        }
        if ($area) {
            $parts[] = \DBW\ImmoSuite\dbw_format_number($area, 'flaeche') . ' m²';
        }
        if ($rooms) {
            $parts[] = \DBW\ImmoSuite\dbw_format_number($rooms, 'zimmer') . ' Zimmer';
        }
        if ($kaufpreis && (float) $kaufpreis > 0) {
            $parts[] = \DBW\ImmoSuite\dbw_format_number($kaufpreis, 'preis') . ' €';
        } elseif ($kaltmiete && (float) $kaltmiete > 0) {
            $parts[] = \DBW\ImmoSuite\dbw_format_number($kaltmiete, 'preis') . ' € Miete';
        }

        $description = $title;
        if (!empty($parts)) {
            $description .= ' — ' . implode(' | ', $parts);
        }
        $description = mb_substr($description, 0, 160);

        // Image
        $image = get_the_post_thumbnail_url($id, 'large');
        if (!$image) {
            $attachments = get_attached_media('image', $id);
            if (!empty($attachments)) {
                $first = reset($attachments);
                $image = wp_get_attachment_image_url($first->ID, 'large');
            }
        }

        // Objektart
        $type_terms = get_the_terms($id, 'objektart');
        $property_type = ($type_terms && !is_wp_error($type_terms)) ? $type_terms[0]->name : 'Immobilie';

        // Output
        echo "\n<!-- DBW Immo Suite SEO -->\n";
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";

        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
            $img_id = get_post_thumbnail_id($id);
            if ($img_id) {
                $img_meta = wp_get_attachment_metadata($img_id);
                if (!empty($img_meta['width'])) echo '<meta property="og:image:width" content="' . esc_attr($img_meta['width']) . '">' . "\n";
                if (!empty($img_meta['height'])) echo '<meta property="og:image:height" content="' . esc_attr($img_meta['height']) . '">' . "\n";
            }
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        } else {
            echo '<meta name="twitter:card" content="summary">' . "\n";
        }

        echo '<meta property="og:locale" content="de_DE">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        echo "<!-- /DBW Immo Suite SEO -->\n\n";
    }

    /**
     * Output noindex for sold/reference properties to avoid indexing stale listings.
     */
    public function output_robots_noindex()
    {
        if (!is_singular('immobilie')) {
            return;
        }

        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('FLAVOR_SEO_VERSION')) {
            return;
        }

        $status = get_post_meta(get_the_ID(), '_dbw_immo_status', true);
        if (in_array($status, array('verkauft', 'referenz'), true)) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }

    /**
     * Output meta tags for archive and taxonomy pages.
     */
    public function output_archive_meta_tags()
    {
        if (!is_post_type_archive('immobilie') && !is_tax(array('objektart', 'vermarktungsart', 'ort'))) {
            return;
        }

        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('FLAVOR_SEO_VERSION')) {
            return;
        }

        $title = '';
        $description = '';

        if (is_tax()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $title = sprintf(__('Immobilien: %s', 'dbw-immo-suite'), $term->name);
                $description = $term->description ?: sprintf(__('Alle Immobilien in der Kategorie %s.', 'dbw-immo-suite'), $term->name);
            }
        } else {
            $settings = get_option('dbw_immo_suite_settings', array());
            $org_name = !empty($settings['org_name']) ? $settings['org_name'] : get_bloginfo('name');
            $title = sprintf(__('Immobilien — %s', 'dbw-immo-suite'), $org_name);
            $description = sprintf(__('Aktuelle Immobilienangebote von %s.', 'dbw-immo-suite'), $org_name);
        }

        if (!$title) return;

        echo "\n<!-- DBW Immo Suite Archive SEO -->\n";
        echo '<meta name="description" content="' . esc_attr(mb_substr($description, 0, 160)) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr(mb_substr($description, 0, 160)) . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:locale" content="de_DE">' . "\n";
        echo '<meta name="twitter:card" content="summary">' . "\n";
        echo "<!-- /DBW Immo Suite Archive SEO -->\n\n";
    }

    /**
     * Output canonical on filtered archive pages to prevent duplicate content.
     */
    public function output_canonical_archive()
    {
        if (!is_post_type_archive('immobilie')) {
            return;
        }

        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('FLAVOR_SEO_VERSION')) {
            return;
        }

        if (!empty($_GET)) {
            echo '<link rel="canonical" href="' . esc_url(get_post_type_archive_link('immobilie')) . '" />' . "\n";
        }
    }

    /**
     * Custom SEO title for property single and archive pages.
     */
    public function filter_document_title($title_parts)
    {
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('FLAVOR_SEO_VERSION')) {
            return $title_parts;
        }

        if (is_singular('immobilie')) {
            $id = get_the_ID();
            $city = get_post_meta($id, 'ort', true);
            $type_terms = get_the_terms($id, 'objektart');
            $objektart = ($type_terms && !is_wp_error($type_terms)) ? $type_terms[0]->name : '';

            if ($city && $objektart) {
                $title_parts['title'] = get_the_title($id) . ' — ' . $objektart . ' in ' . $city;
            }
        }

        return $title_parts;
    }

    /**
     * Exclude sold/reference properties from WordPress sitemap.
     */
    public function filter_sitemap_entries($args, $post_type)
    {
        if ($post_type !== 'immobilie') {
            return $args;
        }

        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key'     => '_dbw_immo_status',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_dbw_immo_status',
                'value'   => array('verkauft', 'referenz'),
                'compare' => 'NOT IN',
            ),
        );

        return $args;
    }
}
