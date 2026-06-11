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
        add_action('wp_ajax_dbw_immo_filter', array($this, 'ajax_filter'));
        add_action('wp_ajax_nopriv_dbw_immo_filter', array($this, 'ajax_filter'));
    }

    /**
     * Parse and sanitize filter parameters from a request array.
     */
    public static function get_filter_params($src = null)
    {
        if ($src === null) {
            $src = $_GET;
        }
        $num = function ($key) use ($src) {
            return (isset($src[$key]) && $src[$key] !== '') ? floatval($src[$key]) : null;
        };
        return array(
            'location'  => isset($src['location']) ? sanitize_text_field(wp_unslash($src['location'])) : '',
            'marketing' => isset($src['marketing']) ? sanitize_title(wp_unslash($src['marketing'])) : '',
            'type'      => isset($src['type']) ? sanitize_title(wp_unslash($src['type'])) : '',
            'price_min' => $num('price_min'),
            'price_max' => $num('price_max'),
            'area_min'  => $num('area_min'),
            'rooms_min' => $num('rooms_min'),
            'sort'      => isset($src['sort']) ? sanitize_text_field(wp_unslash($src['sort'])) : '',
        );
    }

    /**
     * Build the meta_query for a set of filter params.
     */
    public static function build_filter_meta_query($p)
    {
        $meta_query = array();

        if (!empty($p['location'])) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'ort',
                    'value'   => $p['location'],
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => 'plz',
                    'value'   => $p['location'],
                    'compare' => 'LIKE',
                ),
            );
        }

        if ($p['price_min'] !== null || $p['price_max'] !== null) {
            $min = $p['price_min'] !== null ? $p['price_min'] : 0;
            $max = $p['price_max'] !== null ? $p['price_max'] : 999999999;
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'kaufpreis',
                    'value'   => array($min, $max),
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN',
                ),
                array(
                    'key'     => 'kaltmiete',
                    'value'   => array($min, $max),
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN',
                ),
            );
        }

        if ($p['area_min'] !== null && $p['area_min'] > 0) {
            $meta_query[] = array(
                'key'     => 'wohnflaeche',
                'value'   => $p['area_min'],
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        if ($p['rooms_min'] !== null && $p['rooms_min'] > 0) {
            $meta_query[] = array(
                'key'     => 'anzahl_zimmer',
                'value'   => $p['rooms_min'],
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        return $meta_query;
    }

    /**
     * Build the tax_query for a set of filter params.
     */
    public static function build_filter_tax_query($p)
    {
        $tax_query = array();

        if (!empty($p['marketing'])) {
            $tax_query[] = array(
                'taxonomy' => 'vermarktungsart',
                'field'    => 'slug',
                'terms'    => $p['marketing'],
            );
        }

        if (!empty($p['type'])) {
            $tax_query[] = array(
                'taxonomy' => 'objektart',
                'field'    => 'slug',
                'terms'    => $p['type'],
            );
        }

        return $tax_query;
    }

    /**
     * posts_clauses closure for the unified price sort (kaufpreis + kaltmiete).
     */
    private static function price_sort_clauses($safe_order)
    {
        return function ($clauses, $q) use ($safe_order) {
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
        };
    }

    /**
     * AJAX: filter properties without a page reload.
     * Returns rendered cards, count, pagination, chips and map markers.
     */
    public function ajax_filter()
    {
        $p = self::get_filter_params($_POST);
        $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;

        // Optional taxonomy archive context (filtering on /objektart/haus/ etc.)
        $ctx_tax  = isset($_POST['ctx_tax']) ? sanitize_key($_POST['ctx_tax']) : '';
        $ctx_term = isset($_POST['ctx_term']) ? sanitize_title($_POST['ctx_term']) : '';

        $args = array(
            'post_type'      => 'immobilie',
            'post_status'    => 'publish',
            'paged'          => $paged,
            'posts_per_page' => (int) get_theme_mod('dbw_immo_archive_per_page', 9),
        );

        $meta_query = self::build_filter_meta_query($p);

        $settings = get_option('dbw_immo_suite_settings');
        if (!empty($settings['filter_sold_from_main'])) {
            $meta_query[] = CardRenderer::get_exclude_sold_meta_query();
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $tax_query = self::build_filter_tax_query($p);
        if ($ctx_tax && $ctx_term && in_array($ctx_tax, array('objektart', 'vermarktungsart', 'ort'), true)) {
            $tax_query[] = array(
                'taxonomy' => $ctx_tax,
                'field'    => 'slug',
                'terms'    => $ctx_term,
            );
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        // Sorting
        switch ($p['sort']) {
            case 'size_desc':
                $args['meta_key'] = 'wohnflaeche';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'date_asc':
                $args['orderby'] = 'date';
                $args['order']   = 'ASC';
                break;
            case 'price_asc':
            case 'price_desc':
                break; // handled via posts_clauses below
            default:
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
        }

        $clauses_fn = null;
        if ($p['sort'] === 'price_asc' || $p['sort'] === 'price_desc') {
            $clauses_fn = self::price_sort_clauses($p['sort'] === 'price_asc' ? 'ASC' : 'DESC');
            add_filter('posts_clauses', $clauses_fn, 10, 2);
        }

        $query = new \WP_Query($args);

        if ($clauses_fn) {
            remove_filter('posts_clauses', $clauses_fn, 10);
        }

        // Lightweight mode for live result-count previews
        if (!empty($_POST['count_only'])) {
            wp_send_json_success(array('count' => (int) $query->found_posts));
        }

        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            CardRenderer::render();
        }
        wp_reset_postdata();
        $html = ob_get_clean();

        $markers = array();
        if (class_exists('\DBW\ImmoSuite\Frontend\ArchiveMap') && ArchiveMap::is_enabled()) {
            $markers = ArchiveMap::collect_markers($args);
        }

        wp_send_json_success(array(
            'count'      => (int) $query->found_posts,
            'html'       => $html,
            'pagination' => self::render_ajax_pagination((int) $query->max_num_pages, $paged),
            'chips'      => self::render_chips($p),
            'markers'    => $markers,
        ));
    }

    /**
     * Pagination markup for AJAX responses (buttons with data-page,
     * same classes as the server-rendered pagination).
     */
    private static function render_ajax_pagination($total, $current)
    {
        if ($total <= 1) {
            return '';
        }

        $item = function ($content) {
            return '<li class="dbw-page-item">' . $content . '</li>';
        };
        $btn = function ($page, $label = null, $extra_class = '') use ($item) {
            $label = $label !== null ? $label : $page;
            return $item('<button type="button" class="page-numbers ' . esc_attr($extra_class) . '" data-page="' . (int) $page . '">' . $label . '</button>');
        };

        $out = '<div class="dbw-pagination"><ul class="dbw-page-list">';

        if ($current > 1) {
            $out .= $btn($current - 1, '&larr;', 'prev');
        }

        $window = array(1, $total, $current - 1, $current, $current + 1);
        $pages = array();
        foreach ($window as $pg) {
            if ($pg >= 1 && $pg <= $total) {
                $pages[$pg] = true;
            }
        }
        $pages = array_keys($pages);
        sort($pages);

        $prev_pg = 0;
        foreach ($pages as $pg) {
            if ($pg - $prev_pg > 1) {
                $out .= $item('<span class="page-numbers dots">&hellip;</span>');
            }
            if ($pg === $current) {
                $out .= $item('<span class="page-numbers current">' . (int) $pg . '</span>');
            } else {
                $out .= $btn($pg);
            }
            $prev_pg = $pg;
        }

        if ($current < $total) {
            $out .= $btn($current + 1, '&rarr;', 'next');
        }

        $out .= '</ul></div>';
        return $out;
    }

    /**
     * Active-filter chips (removable). Returns HTML.
     */
    public static function render_chips($p = null)
    {
        if ($p === null) {
            $p = self::get_filter_params();
        }

        $chips = array();

        if (!empty($p['type'])) {
            $term = get_term_by('slug', $p['type'], 'objektart');
            $chips[] = array('param' => 'type', 'label' => $term ? $term->name : $p['type']);
        }
        if (!empty($p['marketing'])) {
            $term = get_term_by('slug', $p['marketing'], 'vermarktungsart');
            $chips[] = array('param' => 'marketing', 'label' => $term ? $term->name : $p['marketing']);
        }
        if (!empty($p['location'])) {
            $chips[] = array('param' => 'location', 'label' => $p['location']);
        }
        if ($p['price_min'] !== null || $p['price_max'] !== null) {
            if ($p['price_min'] !== null && $p['price_max'] !== null) {
                $label = \DBW\ImmoSuite\dbw_format_number($p['price_min'], 'preis') . ' – ' . \DBW\ImmoSuite\dbw_format_number($p['price_max'], 'preis') . ' €';
            } elseif ($p['price_max'] !== null) {
                $label = sprintf(__('bis %s €', 'dbw-immo-suite'), \DBW\ImmoSuite\dbw_format_number($p['price_max'], 'preis'));
            } else {
                $label = sprintf(__('ab %s €', 'dbw-immo-suite'), \DBW\ImmoSuite\dbw_format_number($p['price_min'], 'preis'));
            }
            $chips[] = array('param' => 'price_min,price_max', 'label' => $label);
        }
        if ($p['area_min'] !== null && $p['area_min'] > 0) {
            $chips[] = array('param' => 'area_min', 'label' => sprintf(__('ab %s m²', 'dbw-immo-suite'), \DBW\ImmoSuite\dbw_format_number($p['area_min'], 'flaeche')));
        }
        if ($p['rooms_min'] !== null && $p['rooms_min'] > 0) {
            $chips[] = array('param' => 'rooms_min', 'label' => sprintf(__('%s+ Zimmer', 'dbw-immo-suite'), \DBW\ImmoSuite\dbw_format_number($p['rooms_min'], 'zimmer')));
        }

        if (empty($chips)) {
            return '';
        }

        $html = '';
        foreach ($chips as $chip) {
            $keys = explode(',', $chip['param']);
            $href = remove_query_arg(array_merge($keys, array('paged')));
            $html .= '<a href="' . esc_url($href) . '" class="dbw-chip" data-dbw-chip="' . esc_attr($chip['param']) . '">'
                . '<span>' . esc_html($chip['label']) . '</span>'
                . '<span class="dbw-chip__x" aria-hidden="true">&times;</span>'
                . '</a>';
        }

        if (count($chips) >= 2) {
            $reset = remove_query_arg(array('type', 'marketing', 'location', 'price_min', 'price_max', 'area_min', 'rooms_min', 'paged'));
            $html .= '<a href="' . esc_url($reset) . '" class="dbw-chip dbw-chip--reset" data-dbw-chip="*">'
                . esc_html__('Alle zurücksetzen', 'dbw-immo-suite')
                . '</a>';
        }

        return $html;
    }

    /**
     * Price distribution (20 buckets, normalized 0-100) for the slider histogram.
     * Cached for 6 hours.
     */
    public static function get_price_histogram()
    {
        $cached = get_transient('dbw_immo_price_histogram');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $data = array();

        foreach (array('kauf' => 'kaufpreis', 'miete' => 'kaltmiete') as $key => $meta_key) {
            $prices = array_map('floatval', $wpdb->get_col($wpdb->prepare(
                "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s AND p.post_type = 'immobilie' AND p.post_status = 'publish'
                 AND pm.meta_value != '' AND pm.meta_value + 0 > 0",
                $meta_key
            )));

            $buckets = array_fill(0, 20, 0);
            $max = 0;

            if (!empty($prices)) {
                $max = max($prices);
                // Round the scale end up to a "nice" value (e.g. 743000 → 800000)
                $step = pow(10, floor(log10($max)));
                $max = (float) (ceil($max / ($step / 2)) * ($step / 2));

                foreach ($prices as $price) {
                    $i = min(19, (int) floor($price / $max * 20));
                    $buckets[$i]++;
                }
                $bucket_max = max($buckets);
                if ($bucket_max > 0) {
                    $buckets = array_map(function ($c) use ($bucket_max) {
                        return (int) round($c / $bucket_max * 100);
                    }, $buckets);
                }
            }

            $data[$key] = array('max' => $max, 'buckets' => $buckets, 'count' => count($prices));
        }

        set_transient('dbw_immo_price_histogram', $data, 6 * HOUR_IN_SECONDS);
        return $data;
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

        $params = self::get_filter_params();
        $meta_query = self::build_filter_meta_query($params);
        $tax_query = self::build_filter_tax_query($params);

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
                    // Use both kaufpreis and kaltmiete for unified price sort
                    add_filter('posts_clauses', self::price_sort_clauses($sort === 'price_asc' ? 'ASC' : 'DESC'), 10, 2);
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
        $price_min = isset($_GET['price_min']) ? sanitize_text_field($_GET['price_min']) : '';
        $price_max = isset($_GET['price_max']) ? sanitize_text_field($_GET['price_max']) : '';
        $area = isset($_GET['area_min']) ? sanitize_text_field($_GET['area_min']) : '';
        $rooms = isset($_GET['rooms_min']) ? sanitize_text_field($_GET['rooms_min']) : '';
        
        // Auto-expand only if advanced filters are active (not just basic search)
        $has_advanced = $marketing || $type || $price_min || $price_max || $area || $rooms;
        $expanded = $has_advanced; 

        // Get Terms
        $marketing_terms = get_terms(array('taxonomy' => 'vermarktungsart', 'hide_empty' => false));
        $type_terms = get_terms(array('taxonomy' => 'objektart', 'hide_empty' => true));
        $ort_terms = get_terms(array('taxonomy' => 'ort', 'hide_empty' => true));

        // Taxonomy archive context (so AJAX filtering keeps the term restriction)
        $ctx_tax = '';
        $ctx_term = '';
        if (is_tax(array('objektart', 'vermarktungsart', 'ort'))) {
            $qo = get_queried_object();
            if ($qo && !empty($qo->taxonomy)) {
                $ctx_tax = $qo->taxonomy;
                $ctx_term = $qo->slug;
            }
        }

        $histogram = self::get_price_histogram();
        ?>
        <div class="dbw-filter-container <?php echo $expanded ? 'is-expanded' : ''; ?>" id="dbw-filter-container"
             data-ctx-tax="<?php echo esc_attr($ctx_tax); ?>" data-ctx-term="<?php echo esc_attr($ctx_term); ?>">
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
                            <input type="text" name="location" class="dbw-main-search-input" list="dbw-ort-list"
                                   placeholder="<?php _e('Ort, PLZ...', 'dbw-immo-suite'); ?>"
                                   value="<?php echo esc_attr($location); ?>">
                            <datalist id="dbw-ort-list">
                                <?php if (!is_wp_error($ort_terms)) : foreach ($ort_terms as $ort_term) : ?>
                                    <option value="<?php echo esc_attr($ort_term->name); ?>"></option>
                                <?php endforeach; endif; ?>
                            </datalist>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="dbw-toolbar-actions">
                        <button type="button" class="dbw-filter-toggle-btn" id="dbw-filter-toggle" aria-label="<?php esc_attr_e('Erweiterte Filter', 'dbw-immo-suite'); ?>" aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>" aria-controls="dbw-filter-content">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        
                        <button type="submit" class="dbw-main-search-submit">
                            <span data-dbw-search-label><?php _e('Suchen', 'dbw-immo-suite'); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Collapsible Content (Advanced Filters) -->
                <div class="dbw-filter-content" id="dbw-filter-content">
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
                        <div class="dbw-filter-group dbw-filter-double dbw-price-filter"
                             data-histogram="<?php echo esc_attr(wp_json_encode($histogram)); ?>">
                            <label><?php _e('Preis (€)', 'dbw-immo-suite'); ?></label>
                            <div class="dbw-js-only">
                                <div class="dbw-price-histogram" aria-hidden="true"></div>
                                <div class="dbw-range-slider">
                                    <div class="dbw-range-track" aria-hidden="true"></div>
                                    <div class="dbw-range-fill" aria-hidden="true"></div>
                                    <input type="range" class="dbw-range-min" min="0" max="100" step="1" value="0"
                                           aria-label="<?php esc_attr_e('Preis von', 'dbw-immo-suite'); ?>">
                                    <input type="range" class="dbw-range-max" min="0" max="100" step="1" value="100"
                                           aria-label="<?php esc_attr_e('Preis bis', 'dbw-immo-suite'); ?>">
                                </div>
                            </div>
                            <div class="dbw-input-group">
                                <input type="number" name="price_min" placeholder="von" value="<?php echo esc_attr($price_min); ?>" step="1000" min="0">
                                <input type="number" name="price_max" placeholder="bis" value="<?php echo esc_attr($price_max); ?>" step="1000" min="0">
                            </div>
                        </div>

                        <!-- Fläche -->
                        <div class="dbw-filter-group">
                            <label><?php _e('Fläche (m²)', 'dbw-immo-suite'); ?></label>
                            <input type="number" name="area_min" placeholder="ab" value="<?php echo esc_attr($area); ?>" min="0">
                        </div>

                        <!-- Zimmer -->
                        <div class="dbw-filter-group">
                            <label id="dbw-rooms-label"><?php _e('Zimmer', 'dbw-immo-suite'); ?></label>
                            <div class="dbw-room-pills" role="radiogroup" aria-labelledby="dbw-rooms-label">
                                <label class="dbw-room-pill">
                                    <input type="radio" name="rooms_min" value="" <?php checked($rooms === '' || (float) $rooms <= 0); ?>>
                                    <span><?php _e('Alle', 'dbw-immo-suite'); ?></span>
                                </label>
                                <?php for ($r = 1; $r <= 5; $r++) : ?>
                                    <label class="dbw-room-pill">
                                        <input type="radio" name="rooms_min" value="<?php echo (int) $r; ?>" <?php checked((float) $rooms === (float) $r); ?>>
                                        <span><?php echo (int) $r; ?>+</span>
                                    </label>
                                <?php endfor; ?>
                            </div>
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
        <div class="dbw-filter-chips" data-dbw-chips><?php echo self::render_chips(); // phpcs:ignore -- escaped in render_chips ?></div>

        <div class="dbw-archive-header-bar">
            <div class="dbw-result-count">
                <strong data-dbw-count><?php echo esc_html($count); ?></strong> <?php _e('Immobilien gefunden', 'dbw-immo-suite'); ?>
            </div>

            <div class="dbw-start-content-right" style="display: flex; align-items: center; gap: 15px;">
                <?php Favorites::render_toolbar_button(); ?>

                <!-- View Switcher -->
                <div class="dbw-view-switcher">
                    <button type="button" id="dbw-view-grid" class="dbw-view-btn active" aria-label="<?php esc_attr_e('Kachelansicht', 'dbw-immo-suite'); ?>" aria-pressed="true">
                        <span class="dashicons dashicons-grid-view"></span>
                    </button>
                    <button type="button" id="dbw-view-list" class="dbw-view-btn" aria-label="<?php esc_attr_e('Listenansicht', 'dbw-immo-suite'); ?>" aria-pressed="false">
                        <span class="dashicons dashicons-list-view"></span>
                    </button>
                    <?php if (ArchiveMap::is_enabled()): ?>
                    <button type="button" id="dbw-view-map" class="dbw-view-btn" aria-label="<?php esc_attr_e('Kartenansicht', 'dbw-immo-suite'); ?>" aria-pressed="false">
                        <span class="dashicons dashicons-location-alt"></span>
                    </button>
                    <?php endif; ?>
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
                ? $settings['reference_badge_text'] : __('Referenz', 'dbw-immo-suite');
            $style_class = 'dbw-tag-reference';
            return array('label' => $status_label, 'class' => $style_class);
        }
        
        if ($immo_status === 'verkauft') {
            $status_label = isset($settings['sold_badge_text']) && !empty($settings['sold_badge_text']) 
                ? $settings['sold_badge_text'] : __('Verkauft', 'dbw-immo-suite');
            $style_class = 'dbw-tag-sold';
            return array('label' => $status_label, 'class' => $style_class);
        }
        
        if ($immo_status === 'reserviert') {
            $status_label = __('Reserviert', 'dbw-immo-suite');
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
