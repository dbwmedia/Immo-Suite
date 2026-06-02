<?php

if (!defined('ABSPATH')) { exit; }

namespace DBW\ImmoSuite\blocks;

/**
 * References Block Handler
 */
class ReferencesBlock
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
        register_block_type_from_metadata(DBW_IMMO_SUITE_PATH . 'build/blocks/immo-references', array(
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

        $status = isset($attributes['status']) ? $attributes['status'] : array('verkauft', 'referenz');
        $posts_per_page = isset($attributes['postsPerPage']) ? intval($attributes['postsPerPage']) : 12;
        // Fall back to plugin settings if block attributes use defaults
        $settings = get_option('dbw_immo_suite_settings');
        $hide_price = isset($attributes['hidePrice']) ? $attributes['hidePrice'] : !empty($settings['hide_price_sold']);
        $show_date = isset($attributes['showDate']) ? $attributes['showDate'] : !empty($settings['show_sold_date']);
        $location_filter = isset($attributes['location']) ? $attributes['location'] : '';
        $columns = isset($attributes['columns']) ? intval($attributes['columns']) : 3;

        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

        $args = array(
            'post_type'      => 'immobilie',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'meta_query'     => array(
                array(
                    'key'     => '_dbw_immo_status',
                    'value'   => $status,
                    'compare' => 'IN'
                )
            )
        );

        // Location filter (Geo-Landing-Pages)
        if (!empty($location_filter)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'ort',
                    'field'    => 'slug',
                    'terms'    => $location_filter,
                )
            );
        }

        // Sorting (Newest sales first)
        $args['orderby'] = 'meta_value';
        $args['meta_key'] = '_dbw_immo_sales_date';
        $args['order'] = 'DESC';

        $query = new \WP_Query($args);

        ob_start();
        
        if ($query->have_posts()) {
            $grid_style = ($columns !== 3) ? ' style="grid-template-columns: repeat(' . $columns . ', 1fr);"' : '';

            echo '<div id="dbw-immo-suite">';
            echo '<div class="dbw-immo-references-block">';
            echo '<div class="dbw-property-grid"' . $grid_style . '>';
            
            while ($query->have_posts()) {
                $query->the_post();
                \DBW\ImmoSuite\Frontend\CardRenderer::render(array(
                    'hide_price'    => $hide_price,
                    'show_date'     => $show_date,
                    'is_reference'  => true,
                ));
            }

            echo '</div>';

            if ($query->max_num_pages > 1) {
                echo '<div class="dbw-pagination">';
                echo paginate_links(array(
                    'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                    'format' => '?paged=%#%',
                    'current' => max(1, get_query_var('paged')),
                    'total' => $query->max_num_pages,
                    'prev_text' => '&larr;',
                    'next_text' => '&rarr;',
                ));
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';

        } else {
            if (is_admin()) {
                echo '<p>' . __('Keine Referenzen gefunden (Vorschau).', 'dbw-immo-suite') . '</p>';
            }
        }

        wp_reset_postdata();

        return ob_get_clean();
    }
}
