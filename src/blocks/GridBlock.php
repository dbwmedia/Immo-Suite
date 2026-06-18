<?php

namespace DBW\ImmoSuite\blocks;

if (!defined('ABSPATH')) { exit; }

/**
 * Grid Block Handler
 */
class GridBlock
{

    public function init()
    {
        $this->register_block();
    }

    /**
     * Register the block.
     */
    public function register_block()
    {
        // Enqueue block script
        register_block_type_from_metadata(DBW_IMMO_SUITE_PATH . 'build/blocks/immo-grid', array(
            'render_callback' => array($this, 'render_block')
        ));
    }

    /**
     * Render the block on the front end.
     *
     * @param array $attributes Block attributes.
     * @param string $content Block content.
     * @return string Rendered block content.
     */
    public function render_block($attributes, $content)
    {
        wp_enqueue_style('dbw-immo-frontend');
        wp_enqueue_script('dbw-immo-frontend-js');
        wp_enqueue_script('dbw-immo-view-switch-js');

        $posts_per_page = isset($attributes['postsPerPage']) ? intval($attributes['postsPerPage']) : 3;
        $marketing = isset($attributes['marketing']) ? $attributes['marketing'] : '';
        $property_type = isset($attributes['propertyType']) ? $attributes['propertyType'] : '';
        $hide_price = isset($attributes['hidePrice']) ? $attributes['hidePrice'] : false;
        $show_date = isset($attributes['showDate']) ? $attributes['showDate'] : false;
        $columns = isset($attributes['columns']) ? intval($attributes['columns']) : 3;

        // Resolve location: dynamic from current page or manual selection
        // Resolve location: dynamic from current page or manual selection
        $location_source = isset($attributes['locationSource']) ? $attributes['locationSource'] : 'manual';
        $is_dynamic_location = ($location_source === 'current');
        if ($is_dynamic_location) {
            $location_filter = \DBW\ImmoSuite\Frontend\LocationResolver::resolve();
        } else {
            $location_filter = isset($attributes['location']) ? $attributes['location'] : '';
        }

        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

        $args = array(
            'post_type'      => 'immobilie',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'order'          => 'DESC',
            'orderby'        => 'date', // Default: Newest first
        );

        // Tax Queries based on editor selection
        $tax_query = array();

        if (!empty($marketing)) {
            $tax_query[] = array(
                'taxonomy' => 'vermarktungsart',
                'field'    => 'slug',
                'terms'    => $marketing,
            );
        }

        if (!empty($property_type)) {
            $tax_query[] = array(
                'taxonomy' => 'objektart',
                'field'    => 'slug',
                'terms'    => $property_type,
            );
        }

        if (!empty($location_filter)) {
            $tax_query[] = array(
                'taxonomy' => 'ort',
                'field'    => 'slug',
                'terms'    => $location_filter,
            );
        }

        if (!empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        // Meta Query to exclude sold items by default from regular grid?
        // Let's exclude "referenz" and "verkauft" using the global setting if necessary,
        // or just outright from the active listing block.
        $meta_query = array('relation' => 'AND'); // Default to AND if we add multiple constraints

        $settings = get_option('dbw_immo_suite_settings');
        if (isset($settings['filter_sold_from_main']) && $settings['filter_sold_from_main']) {
            $meta_query[] = \DBW\ImmoSuite\Frontend\CardRenderer::get_exclude_sold_meta_query();
        }

        if (isset($attributes['onlyHighlights']) && $attributes['onlyHighlights']) {
             $meta_query[] = array(
                'key'     => '_dbw_immo_is_highlight',
                'value'   => '1',
                'compare' => '='
             );
        }

        if (count($meta_query) > 1) { // More than just 'relation' => 'AND'
             $args['meta_query'] = $meta_query;
        }

        $query = new \WP_Query($args);

        ob_start();

        if ($query->have_posts()) {
            $grid_style = ($columns !== 3) ? ' style="grid-template-columns: repeat(' . $columns . ', 1fr);"' : '';

            echo '<div id="dbw-immo-suite">';
            echo '<div class="dbw-immo-suite-block dbw-immo-grid-block">';
            echo '<div class="dbw-property-grid"' . $grid_style . '>';

            while ($query->have_posts()) {
                $query->the_post();
                \DBW\ImmoSuite\Frontend\CardRenderer::render(array(
                    'hide_price' => $hide_price,
                    'show_date'  => $show_date,
                ));
            }

            echo '</div>';

            // Pagination
            if ($query->max_num_pages > 1) {
                $big = 999999999;
                $pages = paginate_links(array(
                    'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                    'format'    => '?paged=%#%',
                    'current'   => max(1, get_query_var('paged')),
                    'total'     => $query->max_num_pages,
                    'type'      => 'array',
                    'prev_text' => '&larr;',
                    'next_text' => '&rarr;',
                ));
                if (is_array($pages)) {
                    echo '<div class="dbw-pagination"><ul class="dbw-page-list">';
                    foreach ($pages as $page) {
                        echo '<li class="dbw-page-item">' . $page . '</li>';
                    }
                    echo '</ul></div>';
                }
            }

            // CTA Button
            $show_cta = isset($attributes['showCtaButton']) ? $attributes['showCtaButton'] : false;
            if ($show_cta) {
                $cta_text = !empty($attributes['ctaButtonText']) ? $attributes['ctaButtonText'] : __('Zu allen Immobilien', 'dbw-immo-suite');
                $cta_url = !empty($attributes['ctaButtonUrl']) ? $attributes['ctaButtonUrl'] : get_post_type_archive_link('immobilie');
                echo '<div class="dbw-grid-cta">';
                echo '<a href="' . esc_url($cta_url) . '" class="dbw-btn dbw-btn--outline">' . esc_html($cta_text) . ' &rarr;</a>';
                echo '</div>';
            }

            echo '</div>'; // block container
            echo '</div>'; // #dbw-immo-suite

        } else {
            if (is_admin()) {
                echo '<p>' . __('Keine Immobilien für diesen Filter gefunden (Vorschau).', 'dbw-immo-suite') . '</p>';
            } elseif ($is_dynamic_location || !empty($location_filter)) {
                wp_enqueue_style('dbw-immo-frontend');
                echo '<div id="dbw-immo-suite">';
                echo '<div class="dbw-immo-suite-block dbw-immo-grid-block">';
                echo self::render_empty_state(__('Keine Immobilien verfuegbar', 'dbw-immo-suite'), __('Aktuell sind fuer diesen Standort keine Angebote vorhanden. Schauen Sie bald wieder vorbei.', 'dbw-immo-suite'));
                echo '</div>';
                echo '</div>';
            }
        }

        wp_reset_postdata();

        return ob_get_clean();
    }
}
