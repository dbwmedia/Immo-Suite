<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Renders a standalone expose view for printing/PDF export.
 * Triggered by ?expose=1 on single immobilie pages.
 */
class PdfExpose
{
    public function init()
    {
        add_action('template_redirect', array($this, 'maybe_render_expose'));
    }

    /**
     * Intercept ?expose=1 on single immobilie pages.
     */
    public function maybe_render_expose()
    {
        if (!is_singular('immobilie') || empty($_GET['expose'])) {
            return;
        }

        if (!get_theme_mod('dbw_immo_single_show_print', true)) {
            wp_redirect(get_permalink());
            exit;
        }

        // Verify signature for bot protection. A non-expiring HMAC instead of a
        // nonce: cached pages outlive the 12-24h nonce lifetime, which silently
        // broke the PDF button on sites with page caching.
        $post_id = get_queried_object_id();
        $valid = false;
        if (!empty($_GET['sig'])) {
            $valid = hash_equals(self::get_signature($post_id), sanitize_text_field($_GET['sig']));
        } elseif (!empty($_GET['_wpnonce'])) {
            // Backward compat: nonce links rendered before v2.2.0 may live on in page caches
            $valid = (bool) wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'dbw_immo_expose_' . $post_id);
        }
        if (!$valid) {
            wp_die(__('Ungueltiger Link.', 'dbw-immo-suite'), 403);
        }

        $this->render_expose($post_id);
        exit;
    }

    /**
     * Render the standalone expose page.
     */
    private function render_expose($post_id)
    {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            wp_die(__('Immobilie nicht gefunden.', 'dbw-immo-suite'), 404);
        }

        // Collect all data
        $data = $this->collect_data($post_id, $post);

        // Render template
        include DBW_IMMO_SUITE_PATH . 'templates/expose.php';
    }

    /**
     * Collect all property data for the expose.
     */
    private function collect_data($post_id, $post)
    {
        $all_meta = get_post_custom($post_id);
        $m = function($key) use ($all_meta) {
            return isset($all_meta[$key][0]) ? $all_meta[$key][0] : '';
        };

        $settings = get_option('dbw_immo_suite_settings', array());

        // Organization
        $org = array(
            'name'  => !empty($settings['org_name']) ? $settings['org_name'] : get_bloginfo('name'),
            'logo'  => !empty($settings['org_logo_url']) ? $settings['org_logo_url'] : '',
            'phone' => !empty($settings['org_phone']) ? $settings['org_phone'] : '',
            'email' => !empty($settings['org_email']) ? $settings['org_email'] : '',
            'url'   => !empty($settings['org_url']) ? $settings['org_url'] : home_url(),
            'street' => !empty($settings['org_street']) ? $settings['org_street'] : '',
            'zip'    => !empty($settings['org_zip']) ? $settings['org_zip'] : '',
            'city'   => !empty($settings['org_city']) ? $settings['org_city'] : '',
        );

        // Pricing
        $pricing = array(
            'kaufpreis'       => $m('kaufpreis'),
            'kaltmiete'       => $m('kaltmiete'),
            'warmmiete'       => $m('warmmiete'),
            'hausgeld'        => $m('hausgeld'),
            'nebenkosten'     => $m('nebenkosten'),
            'provision'       => $m('provision_kaeufer'),
        );

        // Areas
        $areas = array(
            'wohnflaeche'       => $m('wohnflaeche'),
            'nutzflaeche'       => $m('nutzflaeche'),
            'grundstueck'       => $m('grundstuecksflaeche'),
            'zimmer'            => $m('anzahl_zimmer'),
            'schlafzimmer'      => $m('anzahl_schlafzimmer'),
            'badezimmer'        => $m('anzahl_badezimmer'),
            'stellplaetze'      => $m('anzahl_stellplaetze'),
        );

        // Address — respect the "hide address" toggle: keep city-level info,
        // but never expose the street in the public expose.
        $show_address = (bool) get_theme_mod('dbw_immo_single_show_address', true);
        $address = array(
            'strasse'    => $show_address ? $m('strasse') : '',
            'hausnummer' => $show_address ? $m('hausnummer') : '',
            'plz'        => $m('plz'),
            'ort'        => $m('ort'),
        );

        // Energy
        $energy = array(
            'art'       => $m('energiepass_art'),
            'endenergie'=> $m('energiepass_endenergie'),
            'klasse'    => $m('energiepass_wertklasse'),
            'traeger'   => $m('energiepass_traeger'),
            'gueltig'   => $m('energiepass_gueltig_bis'),
            'baujahr'   => $m('energiepass_baujahr'),
        );

        // Contact
        $contact = array(
            'vorname' => $m('kontaktperson_vorname'),
            'name'    => $m('kontaktperson_name'),
            'email'   => $m('kontaktperson_email'),
            'tel'     => $m('kontaktperson_tel'),
            'bild'    => $m('kontaktperson_bild_url'),
        );

        // Texts
        $texts = array(
            'beschreibung' => $post->post_content,
            'lage'         => $m('text_lage'),
            'ausstattung'  => $m('text_ausstattung'),
            'sonstiges'    => $m('text_sonstiges'),
        );

        // Features
        $features = get_post_meta($post_id, '_dbw_immo_features', true);
        if (!is_array($features)) {
            $features = array();
        }

        // Images
        $images = $this->collect_images($post_id, $m);

        // Distances
        $distances = array();
        foreach ($all_meta as $key => $val) {
            if (strpos($key, 'distanz_') === 0 && !empty($val[0])) {
                $label = ucfirst(str_replace('distanz_', '', $key));
                $distances[$label] = $val[0];
            }
        }

        // Taxonomies
        $objektart_terms = wp_get_post_terms($post_id, 'objektart', array('fields' => 'names'));
        $vermarktung_terms = wp_get_post_terms($post_id, 'vermarktungsart', array('fields' => 'names'));
        $objektart = (!is_wp_error($objektart_terms) && !empty($objektart_terms)) ? $objektart_terms[0] : '';
        $vermarktung = (!is_wp_error($vermarktung_terms) && !empty($vermarktung_terms)) ? $vermarktung_terms[0] : '';

        return array(
            'title'       => get_the_title($post_id),
            'org'         => $org,
            'pricing'     => $pricing,
            'areas'       => $areas,
            'address'     => $address,
            'energy'      => $energy,
            'contact'     => $contact,
            'texts'       => $texts,
            'features'    => $features,
            'images'      => $images,
            'distances'   => $distances,
            'objektart'   => $objektart,
            'vermarktung' => $vermarktung,
        );
    }

    /**
     * Collect gallery and floor plan images.
     */
    private function collect_images($post_id, $m)
    {
        $raw = get_attached_media('image', $post_id);
        $contact_img_id = $m('kontaktperson_bild_id');
        $contact_img_url = $m('kontaktperson_bild_url');

        $gallery = array();
        $floorplans = array();
        $seen = array();

        $att_ids = array_keys($raw);
        if (!empty($att_ids)) {
            update_meta_cache('post', $att_ids);
        }

        foreach ($raw as $att_id => $att_post) {
            if ($contact_img_id && (int) $att_id === (int) $contact_img_id) {
                continue;
            }

            $url = wp_get_attachment_image_url($att_id, 'large');
            if (!$url) continue;

            if ($contact_img_url && $url === $contact_img_url) {
                continue;
            }

            if (isset($seen[$url])) continue;
            $seen[$url] = true;

            $group = get_post_meta($att_id, '_openimmo_gruppe', true);
            $alt = get_post_meta($att_id, '_wp_attachment_image_alt', true);

            $item = array(
                'url' => $url,
                'alt' => $alt ?: get_the_title($post_id),
            );

            if ($group === 'GRUNDRISS') {
                $floorplans[] = $item;
            } else {
                $gallery[] = $item;
            }
        }

        return array(
            'gallery'    => $gallery,
            'floorplans' => $floorplans,
        );
    }

    /**
     * Non-expiring HMAC signature for the expose URL (bot protection, cache-safe).
     */
    private static function get_signature($post_id)
    {
        return substr(hash_hmac('sha256', 'dbw_immo_expose_' . (int) $post_id, wp_salt('nonce')), 0, 16);
    }

    /**
     * Generate a signature-protected expose URL for a property.
     */
    public static function get_expose_url($post_id)
    {
        return add_query_arg(
            array('expose' => '1', 'sig' => self::get_signature($post_id)),
            get_permalink($post_id)
        );
    }
}
