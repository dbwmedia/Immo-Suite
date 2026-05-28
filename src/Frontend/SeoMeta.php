<?php

namespace DBW\ImmoSuite\Frontend;

/**
 * Outputs Open Graph and basic SEO meta tags for property pages.
 * Compatible with Yoast/RankMath — they override these if active.
 */
class SeoMeta
{

    public function init()
    {
        add_action('wp_head', array($this, 'output_meta_tags'), 1);
        add_action('wp_head', array($this, 'output_canonical_archive'), 1);
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
            $parts[] = $area . ' m²';
        }
        if ($rooms) {
            $parts[] = $rooms . ' Zimmer';
        }
        if ($kaufpreis && (float) $kaufpreis > 0) {
            $parts[] = number_format_i18n((float) $kaufpreis, 0) . ' €';
        } elseif ($kaltmiete && (float) $kaltmiete > 0) {
            $parts[] = number_format_i18n((float) $kaltmiete, 0) . ' € Miete';
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
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        }

        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        echo "<!-- /DBW Immo Suite SEO -->\n\n";
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

        // Only output canonical when query params are present (filtered view)
        if (!empty($_GET)) {
            echo '<link rel="canonical" href="' . esc_url(get_post_type_archive_link('immobilie')) . '" />' . "\n";
        }
    }
}
