<?php

if (!defined('ABSPATH')) { exit; }

namespace DBW\ImmoSuite\Frontend;

/**
 * Outputs JSON-LD structured data (Schema.org) for SEO and AI visibility.
 *
 * - RealEstateListing on single property pages
 * - BreadcrumbList on archive + single
 * - RealEstateAgent sitewide
 */
class SchemaOutput
{
    public function init()
    {
        add_action('wp_head', array($this, 'render_schema'), 5);
    }

    public function render_schema()
    {
        if (is_singular('immobilie')) {
            $this->render_real_estate_listing();
            $this->render_breadcrumbs_single();
        } elseif (is_post_type_archive('immobilie') || is_tax(array('objektart', 'vermarktungsart', 'ort'))) {
            $this->render_breadcrumbs_archive();
        }

        $this->render_real_estate_agent();
    }

    private function render_real_estate_listing()
    {
        global $post;
        $id = $post->ID;

        // Use cached meta (already primed by single-immobilie.php)
        $all_meta = get_post_custom($id);
        $m = function($key) use ($all_meta) {
            return isset($all_meta[$key][0]) ? $all_meta[$key][0] : '';
        };

        $price_kauf  = (float) $m('kaufpreis');
        $price_miete = (float) $m('kaltmiete');
        $area        = (float) $m('wohnflaeche');
        $rooms       = (float) $m('anzahl_zimmer');
        $bedrooms    = (int) $m('anzahl_schlafzimmer');
        $bathrooms   = (int) $m('anzahl_badezimmer');
        $year_built  = $m('energiepass_baujahr');
        $energy_class = $m('energiepass_wertklasse');
        $energy_value = (float) $m('energiepass_endenergie');
        $street      = $m('strasse');
        $house_num   = $m('hausnummer');
        $zip         = $m('plz');
        $city        = $m('ort');
        $lat         = (float) $m('geo_breite');
        $lng         = (float) $m('geo_laenge');
        $status      = $m('_dbw_immo_status') ?: 'aktiv';
        $features    = get_post_meta($id, '_dbw_immo_features', true);
        if (!is_array($features)) {
            $features = array();
        }

        // Contact person for agent property
        $contact_name = trim($m('kontaktperson_vorname') . ' ' . $m('kontaktperson_name'));
        $contact_email = $m('kontaktperson_email');
        $contact_tel   = $m('kontaktperson_tel');

        // Determine rent vs. buy from taxonomy
        $vermarktung_terms = wp_get_post_terms($id, 'vermarktungsart', array('fields' => 'names'));
        $is_rent = (is_array($vermarktung_terms) && in_array('Miete', $vermarktung_terms, true));

        $price = $is_rent ? $price_miete : $price_kauf;

        // Availability mapping
        $availability_map = array(
            'aktiv'      => 'https://schema.org/InStock',
            'reserviert' => 'https://schema.org/LimitedAvailability',
            'verkauft'   => 'https://schema.org/SoldOut',
            'referenz'   => 'https://schema.org/SoldOut',
        );

        // Images
        $images = array();
        if (has_post_thumbnail($id)) {
            $images[] = get_the_post_thumbnail_url($id, 'full');
        }
        $raw_attachments = get_attached_media('image', $id);
        $contact_img_id = get_post_meta($id, 'kontaktperson_bild_id', true);
        foreach ($raw_attachments as $att_id => $att_post) {
            if ($contact_img_id && (int) $att_id === (int) $contact_img_id) {
                continue;
            }
            $url = wp_get_attachment_image_url($att_id, 'full');
            if ($url && !in_array($url, $images, true)) {
                $images[] = $url;
            }
        }

        // Build schema
        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'RealEstateListing',
            'url'         => get_permalink($id),
            'name'        => get_the_title($id),
            'description' => wp_strip_all_tags(get_the_excerpt($id) ?: wp_trim_words(get_post_field('post_content', $id), 50, '...')),
            'datePosted'   => get_the_date('c', $id),
            'dateModified' => get_the_modified_date('c', $id),
        );

        if (!empty($images)) {
            $schema['image'] = $images;
        }

        if ($lat && $lng) {
            $schema['geo'] = array(
                '@type'     => 'GeoCoordinates',
                'latitude'  => $lat,
                'longitude' => $lng,
            );
        }

        $full_street = trim(($street ?: '') . ' ' . ($house_num ?: ''));
        if ($full_street || $zip || $city) {
            $schema['address'] = array_filter(array(
                '@type'           => 'PostalAddress',
                'streetAddress'   => $full_street ?: null,
                'postalCode'      => $zip ?: null,
                'addressLocality' => $city ?: null,
                'addressCountry'  => 'DE',
            ));
        }

        if ($price > 0) {
            $offer = array(
                '@type'            => 'Offer',
                'price'            => $price,
                'priceCurrency'    => 'EUR',
                'availability'     => isset($availability_map[$status]) ? $availability_map[$status] : 'https://schema.org/InStock',
                'url'              => get_permalink($id),
                'businessFunction' => $is_rent ? 'https://schema.org/LeaseOut' : 'https://schema.org/Sell',
            );
            if ($is_rent) {
                $offer['priceSpecification'] = array(
                    '@type'       => 'UnitPriceSpecification',
                    'price'       => $price,
                    'priceCurrency' => 'EUR',
                    'unitText'    => 'MONTH',
                    'referenceQuantity' => array(
                        '@type' => 'QuantitativeValue',
                        'value' => 1,
                        'unitCode' => 'MON',
                    ),
                );
            }
            $schema['offers'] = $offer;
        }

        // Accommodation details via "about"
        $accommodation_type = $this->map_object_type($id);
        $accommodation = array_filter(array(
            '@type'                  => $accommodation_type,
            'numberOfRooms'          => $rooms ?: null,
            'numberOfBedrooms'       => $bedrooms ?: null,
            'numberOfBathroomsTotal' => $bathrooms ?: null,
            'yearBuilt'              => $year_built ?: null,
        ));

        if ($area > 0) {
            $accommodation['floorSize'] = array(
                '@type'    => 'QuantitativeValue',
                'value'    => $area,
                'unitCode' => 'MTK',
            );
        }

        if (!empty($features)) {
            $accommodation['amenityFeature'] = array_map(function ($f) {
                return array(
                    '@type' => 'LocationFeatureSpecification',
                    'name'  => $f,
                    'value' => true,
                );
            }, $features);
        }

        if ($energy_class) {
            $accommodation['additionalProperty'][] = array(
                '@type' => 'PropertyValue',
                'name'  => 'energyEfficiencyClass',
                'value' => $energy_class,
            );
        }
        if ($energy_value > 0) {
            $accommodation['additionalProperty'][] = array(
                '@type'    => 'PropertyValue',
                'name'     => 'energyConsumption',
                'value'    => $energy_value,
                'unitText' => 'kWh/(m²·a)',
            );
        }

        $schema['about'] = $accommodation;

        // Add agent (contact person or org fallback)
        if ($contact_name) {
            $agent = array_filter(array(
                '@type'     => 'RealEstateAgent',
                'name'      => $contact_name,
                'email'     => $contact_email ?: null,
                'telephone' => $contact_tel ?: null,
            ));
            $schema['agent'] = $agent;
        } else {
            // Fall back to org-level agent
            $settings = get_option('dbw_immo_suite_settings', array());
            if (!empty($settings['org_name'])) {
                $schema['agent'] = array_filter(array(
                    '@type'     => 'RealEstateAgent',
                    'name'      => $settings['org_name'],
                    'url'       => !empty($settings['org_url']) ? $settings['org_url'] : home_url('/'),
                    'telephone' => !empty($settings['org_phone']) ? $settings['org_phone'] : null,
                ));
            }
        }

        $this->output_json_ld($schema);
    }

    private function map_object_type($id)
    {
        $type_terms = get_the_terms($id, 'objektart');
        $objektart = ($type_terms && !is_wp_error($type_terms)) ? strtolower($type_terms[0]->name) : '';

        $map = array(
            'haus'              => 'House',
            'einfamilienhaus'   => 'SingleFamilyResidence',
            'doppelhaushälfte'  => 'House',
            'reihenhaus'        => 'House',
            'mehrfamilienhaus'  => 'ApartmentComplex',
            'wohnung'           => 'Apartment',
            'eigentumswohnung'  => 'Apartment',
            'grundstück'        => 'Residence',
            'grundstueck'       => 'Residence',
            'büro'              => 'Place',
            'buero'             => 'Place',
            'gewerbe'           => 'Place',
        );

        foreach ($map as $needle => $type) {
            if (strpos($objektart, $needle) !== false) {
                return $type;
            }
        }

        return 'Residence';
    }

    private function render_breadcrumbs_single()
    {
        global $post;
        $items = array(
            array('name' => 'Start',       'url' => home_url('/')),
            array('name' => 'Immobilien',  'url' => get_post_type_archive_link('immobilie')),
            array('name' => get_the_title($post->ID), 'url' => get_permalink($post->ID)),
        );
        $this->output_breadcrumb_list($items);
    }

    private function render_breadcrumbs_archive()
    {
        $items = array(
            array('name' => 'Start',      'url' => home_url('/')),
            array('name' => 'Immobilien', 'url' => get_post_type_archive_link('immobilie')),
        );

        if (is_tax()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $items[] = array('name' => $term->name, 'url' => get_term_link($term));
            }
        }

        $this->output_breadcrumb_list($items);
    }

    private function output_breadcrumb_list($items)
    {
        $list = array();
        foreach ($items as $i => $item) {
            $list[] = array(
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            );
        }
        $this->output_json_ld(array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list,
        ));
    }

    private function render_real_estate_agent()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        $name     = !empty($settings['org_name'])     ? $settings['org_name']     : wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $url      = !empty($settings['org_url'])      ? $settings['org_url']      : home_url('/');
        $logo     = !empty($settings['org_logo_url']) ? $settings['org_logo_url'] : '';
        $phone    = !empty($settings['org_phone'])    ? $settings['org_phone']    : '';
        $email    = !empty($settings['org_email'])    ? $settings['org_email']    : '';
        $street   = !empty($settings['org_street'])   ? $settings['org_street']   : '';
        $zip      = !empty($settings['org_zip'])      ? $settings['org_zip']      : '';
        $city     = !empty($settings['org_city'])     ? $settings['org_city']     : '';

        if (!$name) {
            return;
        }

        $schema = array_filter(array(
            '@context'  => 'https://schema.org',
            '@type'     => 'RealEstateAgent',
            'name'      => $name,
            'url'       => $url,
            'logo'      => $logo ?: null,
            'telephone' => $phone ?: null,
            'email'     => $email ?: null,
        ));

        if ($street || $zip || $city) {
            $schema['address'] = array_filter(array(
                '@type'           => 'PostalAddress',
                'streetAddress'   => $street ?: null,
                'postalCode'      => $zip ?: null,
                'addressLocality' => $city ?: null,
                'addressCountry'  => 'DE',
            ));
        }

        $this->output_json_ld($schema);
    }

    private function output_json_ld($schema)
    {
        echo '<script type="application/ld+json">' . "\n"
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n</script>\n";
    }
}
