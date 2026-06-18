<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Shortcode Handler
 *
 * Available Shortcodes:
 *
 * [dbw_immo_grid]
 *   Displays active property listings in a grid.
 *   Attributes:
 *     count     - Number of properties (default: 6)
 *     columns   - Grid columns 1-4 (default: 3)
 *     marketing - Filter by Vermarktungsart slug (e.g. "kauf", "miete")
 *     type      - Filter by Objektart slug (e.g. "haus", "wohnung")
 *     location  - Filter by Ort slug (e.g. "muenchen", "berlin") or "current" for auto-resolve
 *     highlights - "yes" to show only highlighted properties
 *     hide_price - "yes" to hide prices
 *     show_date  - "yes" to show listing date
 *
 * [dbw_immo_references]
 *   Displays sold/reference properties.
 *   Attributes:
 *     count     - Number of properties (default: 12)
 *     columns   - Grid columns 1-4 (default: 3)
 *     location  - Filter by Ort slug or "current" for auto-resolve from page context
 *     status    - Comma-separated statuses (default: "verkauft,referenz")
 *     hide_price - "yes"/"no" (default: from settings)
 *     show_date  - "yes"/"no" (default: from settings)
 */
class Shortcode
{

    public function init()
    {
        add_shortcode('dbw_immo_references', array($this, 'render_references'));
        add_shortcode('dbw_immo_grid', array($this, 'render_grid'));
    }

    /**
     * Render the Grid Shortcode.
     * Usage: [dbw_immo_grid count="6" columns="3" marketing="kauf" type="haus" location="muenchen" highlights="yes"]
     */
    public function render_grid($atts)
    {
        wp_enqueue_style('dbw-immo-frontend');
        wp_enqueue_script('dbw-immo-frontend-js');
        wp_enqueue_script('dbw-immo-view-switch-js');

        $atts = shortcode_atts(array(
            'count'      => 6,
            'columns'    => 3,
            'marketing'  => '',
            'type'       => '',
            'location'   => '',
            'highlights' => 'no',
            'hide_price' => 'no',
            'show_date'  => 'no',
            'cta'        => 'no',
            'cta_text'   => '',
            'cta_url'    => '',
        ), $atts, 'dbw_immo_grid');

        $posts_per_page = intval($atts['count']);
        $columns = max(1, min(4, intval($atts['columns'])));
        $hide_price = ($atts['hide_price'] === 'yes');
        $show_date = ($atts['show_date'] === 'yes');
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

        $args = array(
            'post_type'      => 'immobilie',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'order'          => 'DESC',
            'orderby'        => 'date',
        );

        // Tax Queries
        $tax_query = array();

        if (!empty($atts['marketing'])) {
            $tax_query[] = array(
                'taxonomy' => 'vermarktungsart',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($atts['marketing']),
            );
        }

        if (!empty($atts['type'])) {
            $tax_query[] = array(
                'taxonomy' => 'objektart',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($atts['type']),
            );
        }

        // Resolve location: "current" = dynamic from page context
        $location_value = $atts['location'];
        $is_dynamic_location = ($location_value === 'current');
        if ($is_dynamic_location) {
            $location_value = LocationResolver::resolve();
        }

        if (!empty($location_value)) {
            $tax_query[] = array(
                'taxonomy' => 'ort',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($location_value),
            );
        }

        if (!empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        // Meta Query
        $meta_query = array('relation' => 'AND');

        // Exclude sold from grid
        $settings = get_option('dbw_immo_suite_settings');
        if (isset($settings['filter_sold_from_main']) && $settings['filter_sold_from_main']) {
            $meta_query[] = array(
                'relation' => 'OR',
                array('key' => '_dbw_immo_status', 'compare' => 'NOT EXISTS'),
                array('key' => '_dbw_immo_status', 'value' => array('verkauft', 'referenz'), 'compare' => 'NOT IN'),
            );
        }

        if ($atts['highlights'] === 'yes') {
            $meta_query[] = array(
                'key'     => '_dbw_immo_is_highlight',
                'value'   => '1',
                'compare' => '=',
            );
        }

        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        $query = new \WP_Query($args);
        $has_filter = class_exists('\DBW\ImmoSuite\Frontend\Filter');

        ob_start();

        if ($query->have_posts()) {
            $grid_style = ($columns !== 3) ? ' style="grid-template-columns: repeat(' . $columns . ', 1fr);"' : '';

            echo '<div id="dbw-immo-suite">';
            echo '<div class="dbw-immo-suite-block dbw-immo-grid-block">';
            echo '<div class="dbw-property-grid"' . $grid_style . '>';

            while ($query->have_posts()) {
                $query->the_post();
                CardRenderer::render(array(
                    'hide_price' => $hide_price,
                    'show_date'  => $show_date,
                ));
            }

            echo '</div>';
            $this->render_pagination($query);

            // CTA Button
            if ($atts['cta'] === 'yes') {
                $cta_text = !empty($atts['cta_text']) ? $atts['cta_text'] : __('Zu allen Immobilien', 'dbw-immo-suite');
                $cta_url = !empty($atts['cta_url']) ? $atts['cta_url'] : get_post_type_archive_link('immobilie');
                echo '<div class="dbw-grid-cta">';
                echo '<a href="' . esc_url($cta_url) . '" class="dbw-btn dbw-btn--outline">' . esc_html($cta_text) . ' &rarr;</a>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
        } else {
            echo '<div id="dbw-immo-suite">';
            echo '<div class="dbw-immo-suite-block dbw-immo-grid-block">';
            echo '<p class="dbw-no-results">' . __('Aktuell sind keine Immobilien verfuegbar.', 'dbw-immo-suite') . '</p>';
            echo '</div>';
            echo '</div>';
        }

        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Render the References Shortcode.
     * Usage: [dbw_immo_references count="12" columns="3" location="muenchen" status="verkauft,referenz"]
     */
    public function render_references($atts)
    {
        wp_enqueue_style('dbw-immo-frontend');
        wp_enqueue_script('dbw-immo-frontend-js');

        $atts = shortcode_atts(array(
            'count'      => 12,
            'columns'    => 3,
            'location'   => '',
            'status'     => 'verkauft,referenz',
            'hide_price' => '',
            'show_date'  => '',
        ), $atts, 'dbw_immo_references');

        $settings = get_option('dbw_immo_suite_settings');
        $posts_per_page = intval($atts['count']);
        $columns = max(1, min(4, intval($atts['columns'])));
        $statuses = array_map('trim', explode(',', sanitize_text_field($atts['status'])));

        // Use shortcode attr if provided, otherwise fall back to settings
        $hide_price = ($atts['hide_price'] !== '')
            ? ($atts['hide_price'] === 'yes')
            : (isset($settings['hide_price_sold']) && $settings['hide_price_sold']);
        $show_date = ($atts['show_date'] !== '')
            ? ($atts['show_date'] === 'yes')
            : (isset($settings['show_sold_date']) && $settings['show_sold_date']);

        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

        $args = array(
            'post_type'      => 'immobilie',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'meta_query'     => array(
                array(
                    'key'     => '_dbw_immo_status',
                    'value'   => $statuses,
                    'compare' => 'IN',
                )
            ),
            'orderby'  => 'meta_value',
            'meta_key' => '_dbw_immo_sales_date',
            'order'    => 'DESC',
        );

        // Resolve location: "current" = dynamic from page context
        $location_value = $atts['location'];
        $is_dynamic_location = ($location_value === 'current');
        if ($is_dynamic_location) {
            $location_value = LocationResolver::resolve();
        }

        if (!empty($location_value)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'ort',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($location_value),
                )
            );
        }

        $query = new \WP_Query($args);

        ob_start();

        if ($query->have_posts()) {
            $grid_style = ($columns !== 3) ? ' style="grid-template-columns: repeat(' . $columns . ', 1fr);"' : '';

            echo '<div id="dbw-immo-suite">';
            echo '<div class="dbw-immo-references-container">';
            echo '<div class="dbw-property-grid"' . $grid_style . '>';

            while ($query->have_posts()) {
                $query->the_post();
                CardRenderer::render(array(
                    'hide_price'   => $hide_price,
                    'show_date'    => $show_date,
                    'is_reference' => true,
                ));
            }

            echo '</div>';
            $this->render_pagination($query);
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div id="dbw-immo-suite">';
            echo '<div class="dbw-immo-references-container">';
            echo '<p class="dbw-no-results">' . __('Aktuell sind keine Referenzen verfuegbar.', 'dbw-immo-suite') . '</p>';
            echo '</div>';
            echo '</div>';
        }

        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Render pagination for a custom query.
     */
    private function render_pagination($query)
    {
        if ($query->max_num_pages <= 1) {
            return;
        }

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
}
