<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

class PriceComparison
{
    public function init()
    {
        add_action('save_post_immobilie', array($this, 'invalidate_cache'), 20);
        add_action('trashed_post', array($this, 'invalidate_cache_on_trash'));
    }

    /**
     * Calculate price per sqm for a single property.
     *
     * @return array|null { price_sqm, type ('kauf'|'miete'), price, area }
     */
    public static function calculate($post_id)
    {
        $kaufpreis   = (float) get_post_meta($post_id, 'kaufpreis', true);
        $kaltmiete   = (float) get_post_meta($post_id, 'kaltmiete', true);
        $wohnflaeche = (float) get_post_meta($post_id, 'wohnflaeche', true);

        if ($wohnflaeche <= 0) {
            return null;
        }

        if ($kaufpreis > 0) {
            return array(
                'price_sqm' => $kaufpreis / $wohnflaeche,
                'type'      => 'kauf',
                'price'     => $kaufpreis,
                'area'      => $wohnflaeche,
            );
        }

        if ($kaltmiete > 0) {
            return array(
                'price_sqm' => $kaltmiete / $wohnflaeche,
                'type'      => 'miete',
                'price'     => $kaltmiete,
                'area'      => $wohnflaeche,
            );
        }

        return null;
    }

    /**
     * Get average price/sqm for a location + type, with min/max and count.
     *
     * @return array|null { avg, min, max, count }
     */
    public static function get_area_average($ort, $type = 'kauf')
    {
        $settings    = get_option('dbw_immo_suite_settings', array());
        $cache_hours = isset($settings['price_per_sqm_cache_hours']) ? (int) $settings['price_per_sqm_cache_hours'] : 24;
        $cache_key   = 'dbw_avg_sqm_' . sanitize_key($ort) . '_' . $type;

        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = self::calculate_average($ort, $type);

        $ttl = max(1, $cache_hours) * HOUR_IN_SECONDS;
        set_transient($cache_key, $result, $ttl);

        return $result;
    }

    /**
     * Direct DB query for average, min, max and count.
     */
    private static function calculate_average($ort, $type = 'kauf')
    {
        global $wpdb;

        $price_key = ($type === 'miete') ? 'kaltmiete' : 'kaufpreis';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                AVG(CAST(pm_price.meta_value AS DECIMAL(12,2)) / CAST(pm_area.meta_value AS DECIMAL(10,2))) AS avg_sqm,
                MIN(CAST(pm_price.meta_value AS DECIMAL(12,2)) / CAST(pm_area.meta_value AS DECIMAL(10,2))) AS min_sqm,
                MAX(CAST(pm_price.meta_value AS DECIMAL(12,2)) / CAST(pm_area.meta_value AS DECIMAL(10,2))) AS max_sqm,
                COUNT(*) AS total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_area ON p.ID = pm_area.post_id AND pm_area.meta_key = 'wohnflaeche'
            INNER JOIN {$wpdb->postmeta} pm_ort ON p.ID = pm_ort.post_id AND pm_ort.meta_key = 'ort'
            WHERE p.post_type = 'immobilie'
            AND p.post_status = 'publish'
            AND pm_ort.meta_value = %s
            AND CAST(pm_price.meta_value AS DECIMAL(12,2)) > 0
            AND CAST(pm_area.meta_value AS DECIMAL(10,2)) > 0",
            $price_key,
            $ort
        ));

        if (!$row || (int) $row->total === 0) {
            return null;
        }

        return array(
            'avg'   => (float) $row->avg_sqm,
            'min'   => (float) $row->min_sqm,
            'max'   => (float) $row->max_sqm,
            'count' => (int) $row->total,
        );
    }

    /**
     * Render the price/sqm comparison widget on the single property page.
     */
    public static function render_single($post_id)
    {
        $settings = get_option('dbw_immo_suite_settings', array());

        if (empty($settings['show_price_per_sqm']) && !self::setting_default_on('show_price_per_sqm', $settings)) {
            return;
        }

        $calc = self::calculate($post_id);
        if (!$calc) {
            return;
        }

        $price_sqm = $calc['price_sqm'];
        $type      = $calc['type'];
        $ort       = get_post_meta($post_id, 'ort', true);

        $is_rent     = ($type === 'miete');
        $label       = $is_rent
            ? __('Miete pro m²', 'dbw-immo-suite')
            : __('Preis pro m²', 'dbw-immo-suite');
        $unit_suffix = '/m²';

        $formatted_price = number_format($price_sqm, $is_rent ? 2 : 0, ',', '.') . ' €' . $unit_suffix;

        // Comparison data
        $show_comparison = !empty($settings['show_price_per_sqm_comparison']) || self::setting_default_on('show_price_per_sqm_comparison', $settings);
        $comparison      = null;
        $min_comparables = isset($settings['price_per_sqm_min_comparables']) ? (int) $settings['price_per_sqm_min_comparables'] : 3;

        if ($show_comparison && $ort) {
            $avg_data = self::get_area_average($ort, $type);
            if ($avg_data && $avg_data['count'] >= $min_comparables && $avg_data['avg'] > 0) {
                $deviation = (($price_sqm - $avg_data['avg']) / $avg_data['avg']) * 100;
                $comparison = array(
                    'avg'       => $avg_data['avg'],
                    'min'       => $avg_data['min'],
                    'max'       => $avg_data['max'],
                    'count'     => $avg_data['count'],
                    'deviation' => $deviation,
                    'ort'       => $ort,
                );
            }
        }

        ?>
        <div class="dbw-price-sqm-card">
            <div class="dbw-price-sqm-header">
                <span class="dbw-price-sqm-label"><?php echo esc_html($label); ?></span>
                <span class="dbw-price-sqm-value"><?php echo esc_html($formatted_price); ?></span>
            </div>

            <?php if ($comparison): ?>
                <?php
                $dev = $comparison['deviation'];
                if ($dev <= -5) {
                    $badge_class = 'dbw-price-sqm-below';
                    $arrow = '▼';
                    $badge_text = sprintf(
                        __('%s%% unter dem Schnitt in %s', 'dbw-immo-suite'),
                        number_format(abs($dev), 0, ',', '.'),
                        $comparison['ort']
                    );
                } elseif ($dev >= 5) {
                    $badge_class = 'dbw-price-sqm-above';
                    $arrow = '▲';
                    $badge_text = sprintf(
                        __('%s%% über dem Schnitt in %s', 'dbw-immo-suite'),
                        number_format(abs($dev), 0, ',', '.'),
                        $comparison['ort']
                    );
                } else {
                    $badge_class = 'dbw-price-sqm-neutral';
                    $arrow = '●';
                    $badge_text = sprintf(
                        __('Im Durchschnitt in %s', 'dbw-immo-suite'),
                        $comparison['ort']
                    );
                }

                // Bar calculation
                $range_min = $comparison['min'];
                $range_max = $comparison['max'];
                $range     = $range_max - $range_min;

                // Prevent division by zero when all properties have the same price/sqm
                if ($range > 0) {
                    $current_pct = max(0, min(100, (($price_sqm - $range_min) / $range) * 100));
                    $avg_pct     = max(0, min(100, (($comparison['avg'] - $range_min) / $range) * 100));
                } else {
                    $current_pct = 50;
                    $avg_pct     = 50;
                }

                $avg_formatted = number_format($comparison['avg'], $is_rent ? 2 : 0, ',', '.');
                ?>
                <div class="dbw-price-sqm-badge <?php echo esc_attr($badge_class); ?>">
                    <span class="dbw-price-sqm-arrow"><?php echo $arrow; ?></span>
                    <?php echo esc_html($badge_text); ?>
                </div>

                <div class="dbw-price-sqm-bar-wrap">
                    <div class="dbw-price-sqm-bar">
                        <div class="dbw-price-sqm-bar-fill" style="width: <?php echo esc_attr($current_pct); ?>%;"></div>
                        <div class="dbw-price-sqm-bar-avg" style="left: <?php echo esc_attr($avg_pct); ?>%;" title="<?php echo esc_attr(sprintf(__('Durchschnitt: %s EUR/m²', 'dbw-immo-suite'), $avg_formatted)); ?>"></div>
                        <div class="dbw-price-sqm-bar-marker" style="left: <?php echo esc_attr($current_pct); ?>%;"></div>
                    </div>
                    <div class="dbw-price-sqm-bar-legend">
                        <span><?php echo esc_html(number_format($range_min, 0, ',', '.')); ?> €</span>
                        <span><?php echo esc_html(sprintf(__('⌀ %s €', 'dbw-immo-suite'), $avg_formatted)); ?></span>
                        <span><?php echo esc_html(number_format($range_max, 0, ',', '.')); ?> €</span>
                    </div>
                </div>

                <div class="dbw-price-sqm-disclaimer">
                    <?php
                    echo esc_html(sprintf(
                        \DBW\ImmoSuite\dbw_anrede(
                            __('Basierend auf %d aktiven Objekten in %s', 'dbw-immo-suite'),
                            __('Basierend auf %d aktiven Objekten in %s', 'dbw-immo-suite')
                        ),
                        $comparison['count'],
                        $comparison['ort']
                    ));
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a compact price/sqm badge for archive cards.
     */
    public static function render_archive_badge($post_id)
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        if (empty($settings['show_price_per_sqm_archive'])) {
            return;
        }

        if (!get_theme_mod('dbw_immo_archive_show_price_sqm', false)) {
            return;
        }

        $calc = self::calculate($post_id);
        if (!$calc) {
            return;
        }

        $is_rent   = ($calc['type'] === 'miete');
        $formatted = number_format($calc['price_sqm'], $is_rent ? 2 : 0, ',', '.') . ' €/m²';

        // Determine color based on deviation
        $badge_class = 'dbw-price-sqm-badge-neutral';
        $ort = get_post_meta($post_id, 'ort', true);
        if ($ort) {
            $avg_data = self::get_area_average($ort, $calc['type']);
            $min_comparables = isset($settings['price_per_sqm_min_comparables']) ? (int) $settings['price_per_sqm_min_comparables'] : 3;
            if ($avg_data && $avg_data['count'] >= $min_comparables && $avg_data['avg'] > 0) {
                $dev = (($calc['price_sqm'] - $avg_data['avg']) / $avg_data['avg']) * 100;
                if ($dev <= -5) {
                    $badge_class = 'dbw-price-sqm-badge-below';
                } elseif ($dev >= 5) {
                    $badge_class = 'dbw-price-sqm-badge-above';
                }
            }
        }

        printf(
            '<span class="dbw-price-sqm-archive-badge %s">%s</span>',
            esc_attr($badge_class),
            esc_html($formatted)
        );
    }

    /**
     * Render price/sqm row for the expose template.
     */
    public static function render_expose_row($post_id)
    {
        $calc = self::calculate($post_id);
        if (!$calc) {
            return '';
        }

        $is_rent   = ($calc['type'] === 'miete');
        $label     = $is_rent ? 'Miete pro m&sup2;' : 'Preis pro m&sup2;';
        $formatted = number_format($calc['price_sqm'], $is_rent ? 2 : 0, ',', '.') . ' &euro;/m&sup2;';

        return sprintf(
            '<tr><td>%s</td><td>%s</td></tr>',
            $label,
            $formatted
        );
    }

    /**
     * Invalidate transient caches when a property is saved.
     */
    public function invalidate_cache($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $ort = get_post_meta($post_id, 'ort', true);
        if ($ort) {
            delete_transient('dbw_avg_sqm_' . sanitize_key($ort) . '_kauf');
            delete_transient('dbw_avg_sqm_' . sanitize_key($ort) . '_miete');
        }
    }

    /**
     * Also invalidate when a property is trashed.
     */
    public function invalidate_cache_on_trash($post_id)
    {
        if (get_post_type($post_id) === 'immobilie') {
            $this->invalidate_cache($post_id);
        }
    }

    /**
     * Helper: Check if a setting defaults to on (when not yet saved).
     */
    private static function setting_default_on($key, $settings)
    {
        $defaults_on = array('show_price_per_sqm', 'show_price_per_sqm_comparison');
        return !array_key_exists($key, $settings) && in_array($key, $defaults_on, true);
    }
}
