<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Handles Frontend Filtering, Sorting, and Archive UI.
 */
class Filter
{

    /**
     * Initialize Hooks.
     */
    public function init()
    {
        add_action('pre_get_posts', array($this, 'modify_query'));
    }

    /**
     * Modify the main query based on GET parameters.
     *
     * @param \WP_Query $query
     */
    public function modify_query($query)
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if (!is_post_type_archive('immobilie') && !is_tax('objektart') && !is_tax('vermarktungsart') && !is_tax('ort')) {
            return;
        }

        $meta_query = array();
        $tax_query = array();

        // 0. General Search (Location / Text)
        if (!empty($_GET['location'])) {
            $search_term = sanitize_text_field($_GET['location']);
            // Search in Title OR Meta (Ort/PLZ)
            // WP_Query 's' searches title/content. We want specific meta fields too.
            // Simplified: We search 's' OR we add meta query for Ort/PLZ. 
            // For this suite, let's treat it as a Location Filter (Meta Key 'ort' or 'plz')
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'ort',
                    'value'   => $search_term,
                    'compare' => 'LIKE'
                ),
                array(
                    'key'     => 'plz',
                    'value'   => $search_term,
                    'compare' => 'LIKE'
                )
            );
        }

        // 1. Marketing Type (Kauf/Miete)
        if (!empty($_GET['marketing'])) {
            $tax_query[] = array(
                'taxonomy' => 'vermarktungsart',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_GET['marketing']),
            );
        }

        // 2. Property Type (Haus/Wohnung)
        if (!empty($_GET['type'])) {
            $tax_query[] = array(
                'taxonomy' => 'objektart',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_GET['type']),
            );
        }

        // 3. Price Range
        if (!empty($_GET['price_min']) || !empty($_GET['price_max'])) {
            $min = !empty($_GET['price_min']) ? floatval($_GET['price_min']) : 0;
            $max = !empty($_GET['price_max']) ? floatval($_GET['price_max']) : 999999999;

            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'kaufpreis',
                    'value'   => array($min, $max),
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN'
                ),
                array(
                    'key'     => 'kaltmiete',
                    'value'   => array($min, $max),
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN'
                )
            );
        }

        // 4. Area
        if (!empty($_GET['area_min'])) {
            $meta_query[] = array(
                'key'     => 'wohnflaeche',
                'value'   => floatval($_GET['area_min']),
                'type'    => 'NUMERIC',
                'compare' => '>='
            );
        }

        // 5. Rooms
        if (!empty($_GET['rooms_min'])) {
            $meta_query[] = array(
                'key'     => 'anzahl_zimmer',
                'value'   => floatval($_GET['rooms_min']),
                'type'    => 'NUMERIC',
                'compare' => '>='
            );
        }

        // Apply Meta Query
        if (!empty($meta_query)) {
            $current_meta = $query->get('meta_query');
            if (!empty($current_meta)) {
                $meta_query = array_merge(array('relation' => 'AND'), array($current_meta), $meta_query);
            }
            $query->set('meta_query', $meta_query);
        }

        // Apply Tax Query
        if (!empty($tax_query)) {
            $current_tax = $query->get('tax_query');
            if (!empty($current_tax)) {
                $tax_query = array_merge(array('relation' => 'AND'), array($current_tax), $tax_query);
            }
            $query->set('tax_query', $tax_query);
        }

        // Sorting
        if (!empty($_GET['sort'])) {
            $sort = sanitize_text_field($_GET['sort']);
            switch ($sort) {
                case 'price_asc':
                case 'price_desc':
                    $safe_order = ($sort === 'price_asc') ? 'ASC' : 'DESC';
                    // Use both kaufpreis and kaltmiete for unified price sort
                    add_filter('posts_clauses', function ($clauses, $q) use ($safe_order) {
                        global $wpdb;
                        $clauses['join'] .= $wpdb->prepare(
                            " LEFT JOIN {$wpdb->postmeta} AS pm_kauf ON ({$wpdb->posts}.ID = pm_kauf.post_id AND pm_kauf.meta_key = %s)",
                            'kaufpreis'
                        );
                        $clauses['join'] .= $wpdb->prepare(
                            " LEFT JOIN {$wpdb->postmeta} AS pm_miete ON ({$wpdb->posts}.ID = pm_miete.post_id AND pm_miete.meta_key = %s)",
                            'kaltmiete'
                        );
                        $clauses['orderby'] = "CAST(COALESCE(NULLIF(pm_kauf.meta_value, ''), NULLIF(pm_miete.meta_value, ''), '0') AS DECIMAL(12,2)) " . $safe_order;
                        return $clauses;
                    }, 10, 2);
                    break;
                case 'size_desc':
                    $query->set('meta_key', 'wohnflaeche');
                    $query->set('orderby', 'meta_value_num');
                    $query->set('order', 'DESC');
                    break;
                case 'date_asc':
                    $query->set('orderby', 'date');
                    $query->set('order', 'ASC');
                    break;
                default:
                    $query->set('orderby', 'date');
                    $query->set('order', 'DESC');
            }
        }

        // Filter Sold Items (Global Setting)
        $settings = get_option('dbw_immo_suite_settings');
        if (isset($settings['filter_sold_from_main']) && $settings['filter_sold_from_main']) {
            $current_meta = $query->get('meta_query');
            if (!is_array($current_meta)) $current_meta = array();
            $current_meta[] = CardRenderer::get_exclude_sold_meta_query();
            $query->set('meta_query', $current_meta);
        }
    }

    /**
     * Render the Filter Bar HTML.
     */
    public static function render_filter_bar()
    {
        $location = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '';
        $marketing = isset($_GET['marketing']) ? sanitize_title($_GET['marketing']) : '';
        $type = isset($_GET['type']) ? sanitize_title($_GET['type']) : '';
        $price_min = isset($_GET['price_min']) ? esc_attr($_GET['price_min']) : '';
        $price_max = isset($_GET['price_max']) ? esc_attr($_GET['price_max']) : '';
        $area = isset($_GET['area_min']) ? esc_attr($_GET['area_min']) : '';
        $rooms = isset($_GET['rooms_min']) ? esc_attr($_GET['rooms_min']) : '';
        
        // Auto-expand only if advanced filters are active (not just basic search)
        $has_advanced = $marketing || $type || $price_min || $price_max || $area || $rooms;
        $expanded = $has_advanced; 

        // Get Terms
        $marketing_terms = get_terms(array('taxonomy' => 'vermarktungsart', 'hide_empty' => false));
        $type_terms = get_terms(array('taxonomy' => 'objektart', 'hide_empty' => true));
        ?>
        <div class="dbw-filter-container <?php echo $expanded ? 'is-expanded' : ''; ?>" id="dbw-filter-container">
            <form method="GET" action="<?php echo esc_url(get_post_type_archive_link('immobilie')); ?>">
                
                <!-- Main Toolbar (Always Visible) -->
                <div class="dbw-filter-toolbar">
                    
                    <!-- 1. Property Type (Haus/Wohnung) -->
                    <div class="dbw-toolbar-item dbw-toolbar-select-wrapper">
                        <label class="dbw-toolbar-label"><?php _e('Objekttyp', 'dbw-immo-suite'); ?></label>
                        <select name="type" class="dbw-toolbar-select">
                            <option value=""><?php _e('Alle', 'dbw-immo-suite'); ?></option>
                            <?php foreach ($type_terms as $term): ?>
                                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($type, $term->slug); ?>>
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dbw-toolbar-divider"></div>

                    <!-- 2. Location Input -->
                    <div class="dbw-toolbar-item dbw-search-input-wrapper">
                        <label class="dbw-toolbar-label"><?php _e('Standort', 'dbw-immo-suite'); ?></label>
                        <div class="dbw-input-inner">
                            <span class="dashicons dashicons-location"></span>
                            <input type="text" name="location" class="dbw-main-search-input" 
                                   placeholder="<?php _e('Ort, PLZ...', 'dbw-immo-suite'); ?>" 
                                   value="<?php echo esc_attr($location); ?>">
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="dbw-toolbar-actions">
                        <button type="button" class="dbw-filter-toggle-btn" id="dbw-filter-toggle" title="<?php _e('Erweiterte Filter', 'dbw-immo-suite'); ?>">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        
                        <button type="submit" class="dbw-main-search-submit">
                            <?php _e('Suchen', 'dbw-immo-suite'); ?>
                        </button>
                    </div>
                </div>

                <!-- Collapsible Content (Advanced Filters) -->
                <div class="dbw-filter-content">
                    <div class="dbw-filter-grid">
                        
                        <!-- Vermarktung (Moved back here) -->
                        <div class="dbw-filter-group">
                            <label><?php _e('Vermarktung', 'dbw-immo-suite'); ?></label>
                            <select name="marketing" class="dbw-filter-select">
                                <option value=""><?php _e('Alle', 'dbw-immo-suite'); ?></option>
                                <?php foreach ($marketing_terms as $term): ?>
                                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($marketing, $term->slug); ?>>
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Preis -->
                        <div class="dbw-filter-group dbw-filter-double">
                            <label><?php _e('Preis (€)', 'dbw-immo-suite'); ?></label>
                            <div class="dbw-input-group">
                                <input type="number" name="price_min" placeholder="von" value="<?php echo $price_min; ?>" step="1000">
                                <input type="number" name="price_max" placeholder="bis" value="<?php echo $price_max; ?>" step="1000">
                            </div>
                        </div>

                        <!-- Fläche -->
                        <div class="dbw-filter-group">
                            <label><?php _e('Fläche (m²)', 'dbw-immo-suite'); ?></label>
                            <input type="number" name="area_min" placeholder="ab" value="<?php echo $area; ?>">
                        </div>

                        <!-- Zimmer -->
                        <div class="dbw-filter-group">
                            <label><?php _e('Zimmer', 'dbw-immo-suite'); ?></label>
                            <input type="number" name="rooms_min" placeholder="ab" value="<?php echo $rooms; ?>">
                        </div>
                    </div>

                    <!-- Secondary Reset Link -->
                    <div class="dbw-filter-footer">
                        <a href="<?php echo esc_url(get_post_type_archive_link('immobilie')); ?>" class="dbw-filter-reset">
                            <span class="dashicons dashicons-image-rotate"></span>
                            <?php _e('Filter zurücksetzen', 'dbw-immo-suite'); ?>
                        </a>
                    </div>
                </div>

                <!-- Preserve Sort if set -->
                <?php if (isset($_GET['sort'])): ?>
                    <input type="hidden" name="sort" value="<?php echo esc_attr($_GET['sort']); ?>">
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the Header (Count & Sort).
     */
    public static function render_archive_header()
    {
        global $wp_query;
        $count = $wp_query->found_posts;
        $sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_desc';
        ?>
        <div class="dbw-archive-header-bar">
            <div class="dbw-result-count">
                <strong><?php echo esc_html($count); ?></strong> <?php _e('Immobilien gefunden', 'dbw-immo-suite'); ?>
            </div>

            <div class="dbw-start-content-right" style="display: flex; align-items: center; gap: 15px;">
                <!-- View Switcher -->
                <div class="dbw-view-switcher">
                    <button type="button" id="dbw-view-grid" class="dbw-view-btn active" title="<?php _e('Kachelansicht', 'dbw-immo-suite'); ?>">
                        <span class="dashicons dashicons-grid-view"></span>
                    </button>
                    <button type="button" id="dbw-view-list" class="dbw-view-btn" title="<?php _e('Listenansicht', 'dbw-immo-suite'); ?>">
                        <span class="dashicons dashicons-list-view"></span>
                    </button>
                </div>
                
                <div class="dbw-sort-control">
                <form method="GET" class="dbw-sort-form">
                    <!-- Preserve existing filters -->
                    <?php 
                    foreach ($_GET as $key => $val) {
                        if ($key === 'sort') continue;
                        if (is_array($val)) continue; // Simplified
                        echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '">';
                    }
                    ?>
                    <select name="sort" onchange="this.form.submit()" class="dbw-sort-select">
                        <option value="date_desc" <?php selected($sort, 'date_desc'); ?>><?php _e('Neueste zuerst', 'dbw-immo-suite'); ?></option>
                        <option value="date_asc" <?php selected($sort, 'date_asc'); ?>><?php _e('Älteste zuerst', 'dbw-immo-suite'); ?></option>
                        <option value="price_asc" <?php selected($sort, 'price_asc'); ?>><?php _e('Preis aufsteigend', 'dbw-immo-suite'); ?></option>
                        <option value="price_desc" <?php selected($sort, 'price_desc'); ?>><?php _e('Preis absteigend', 'dbw-immo-suite'); ?></option>
                        <option value="size_desc" <?php selected($sort, 'size_desc'); ?>><?php _e('Größte zuerst', 'dbw-immo-suite'); ?></option>
                    </select>
                </form>
            </div>
            </div> <!-- End dbw-start-content-right -->
        </div>
        <?php
    }

    /**
     * Helper: Get Status Label for Tag.
     * Generates dynamic tag: "[Object Type] [Marketing Type]" e.g. "Haus zum Kauf"
     */
    public static function get_status_label($post_id)
    {
        $status_label = '';
        $style_class = 'dbw-tag-default';

        // 0. Check Internal Status (Sold/Reference)
        $immo_status = get_post_meta($post_id, '_dbw_immo_status', true);
        $settings = get_option('dbw_immo_suite_settings');

        if ($immo_status === 'referenz') {
            $status_label = isset($settings['reference_badge_text']) && !empty($settings['reference_badge_text']) 
                ? $settings['reference_badge_text'] : 'Referenz';
            $style_class = 'dbw-tag-reference';
            return array('label' => $status_label, 'class' => $style_class);
        }
        
        if ($immo_status === 'verkauft') {
            $status_label = isset($settings['sold_badge_text']) && !empty($settings['sold_badge_text']) 
                ? $settings['sold_badge_text'] : 'Verkauft';
            $style_class = 'dbw-tag-sold';
            return array('label' => $status_label, 'class' => $style_class);
        }
        
        if ($immo_status === 'reserviert') {
            $status_label = 'Reserviert';
            $style_class = 'dbw-tag-reserved'; // Need CSS for this
            return array('label' => $status_label, 'class' => $style_class);
        }

        // 1. Object Type
        $objektart_terms = get_the_terms($post_id, 'objektart');
        $objektart = ($objektart_terms && !is_wp_error($objektart_terms)) ? $objektart_terms[0]->name : __('Immobilie', 'dbw-immo-suite');

        // 2. Marketing Type
        $marketing_terms = get_the_terms($post_id, 'vermarktungsart');
        $marketing = '';
        
        if ($marketing_terms && !is_wp_error($marketing_terms)) {
            $term_slug = $marketing_terms[0]->slug;
            $term_name = $marketing_terms[0]->name;

            if (strpos($term_slug, 'kauf') !== false) {
                $marketing = __('zum Kauf', 'dbw-immo-suite');
                $style_class = 'dbw-tag-buy';
            } elseif (strpos($term_slug, 'miete') !== false) {
                $marketing = __('zur Miete', 'dbw-immo-suite');
                $style_class = 'dbw-tag-rent';
            } else {
                $marketing = $term_name;
            }
        } 
        // Fallback checks if taxonomy missing
        else {
            $kaufpreis = get_post_meta($post_id, 'kaufpreis', true);
            $miete = get_post_meta($post_id, 'kaltmiete', true);
            
            if ($kaufpreis) {
                $marketing = __('zum Kauf', 'dbw-immo-suite');
                $style_class = 'dbw-tag-buy';
            } elseif ($miete) {
                $marketing = __('zur Miete', 'dbw-immo-suite');
                $style_class = 'dbw-tag-rent';
            }
        }

        // Combine
        if ($marketing) {
            $status_label = $objektart . ' ' . $marketing;
        } else {
            $status_label = $objektart; // Fallback just object type
        }

        return array('label' => $status_label, 'class' => $style_class);
    }

    /**
     * Custom Pagination.
     */
    public static function pagination() {
        global $wp_query;

        if ($wp_query->max_num_pages <= 1) return;

        $big = 999999999;
        $pages = paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => max(1, get_query_var('paged')),
            'total' => $wp_query->max_num_pages,
            'type'  => 'array',
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
