<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

class EnergyRenderer
{
    const COLOR_MAP = [
        'A+' => '#188a38',
        'A' => '#37a431',
        'B' => '#8eb32c',
        'C' => '#c6cc26',
        'D' => '#eae01c',
        'E' => '#f8ca12',
        'F' => '#e48325',
        'G' => '#c83f2a',
        'H' => '#b32822'
    ];

    const ENERGY_PRICE_DEFAULTS = [
        'gas'         => 0.12,
        'oel'         => 0.10,
        'fernwaerme'  => 0.14,
        'strom'       => 0.35,
        'holz'        => 0.06,
        'pellet'      => 0.06,
        'waermepumpe'  => 0.12,
        'fluessiggas' => 0.09,
        'solar'       => 0.00,
    ];

    const ENERGY_LABELS = [
        'gas'         => 'Gas',
        'oel'         => 'Oel',
        'fernwaerme'  => 'Fernwaerme',
        'strom'       => 'Strom (Direktheizung)',
        'holz'        => 'Holz',
        'pellet'      => 'Pellet',
        'waermepumpe'  => 'Waermepumpe',
        'fluessiggas' => 'Fluessiggas',
        'solar'       => 'Solar',
    ];

    /**
     * Map raw energiepass_traeger value to a normalized key.
     */
    private static function map_energy_source($raw)
    {
        if (empty($raw)) {
            return 'gas';
        }

        $normalized = strtolower(trim($raw));
        $normalized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $normalized);
        $normalized = str_replace(['_', '-', ' '], '', $normalized);

        $map = [
            'gas'           => 'gas',
            'erdgas'        => 'gas',
            'gasheizung'    => 'gas',
            'oel'           => 'oel',
            'heizoel'       => 'oel',
            'oelheizung'    => 'oel',
            'fernwaerme'    => 'fernwaerme',
            'fernwrme'      => 'fernwaerme',
            'nahwaerme'     => 'fernwaerme',
            'strom'         => 'strom',
            'elektro'       => 'strom',
            'stromheizung'  => 'strom',
            'holz'          => 'holz',
            'holzheizung'   => 'holz',
            'pellet'        => 'pellet',
            'pellets'       => 'pellet',
            'holzpellets'   => 'pellet',
            'waermepumpe'   => 'waermepumpe',
            'wrmepumpe'     => 'waermepumpe',
            'luftwaermepumpe' => 'waermepumpe',
            'erdwaermepumpe'  => 'waermepumpe',
            'erdwaerme'     => 'waermepumpe',
            'geothermie'    => 'waermepumpe',
            'fluessiggas'   => 'fluessiggas',
            'flssiggas'     => 'fluessiggas',
            'lpg'           => 'fluessiggas',
            'solar'         => 'solar',
            'solarenergie'  => 'solar',
            'solarthermie'  => 'solar',
        ];

        foreach ($map as $needle => $key) {
            if (strpos($normalized, $needle) !== false) {
                return $key;
            }
        }

        return 'gas';
    }

    /**
     * Get energy price per kWh for a given source key.
     */
    private static function get_energy_price($source_key)
    {
        $settings = get_option('dbw_immo_suite_settings', []);
        $setting_key = 'energy_price_' . $source_key;

        if (isset($settings[$setting_key]) && $settings[$setting_key] !== '') {
            return (float) $settings[$setting_key];
        }

        return self::ENERGY_PRICE_DEFAULTS[$source_key] ?? 0.12;
    }

    /**
     * Renders the small flag for the archive grid.
     */
    public static function render_archive_flag($post_id)
    {
        if (!get_theme_mod('dbw_immo_archive_show_energy_class', true)) {
            return;
        }

        $class = get_post_meta($post_id, 'energiepass_wertklasse', true);

        if (empty($class) || !array_key_exists($class, self::COLOR_MAP)) {
            return;
        }

        $color = self::COLOR_MAP[$class];

        echo sprintf(
            '<div class="dbw-energy-flag" style="background:%s;">%s</div>',
            esc_attr($color),
            esc_html($class)
        );
    }

    /**
     * Renders the detailed energy scale for the single property view.
     */
    public static function render_single_scale($post_id)
    {
        // Use cached meta (already primed by single-immobilie.php's get_post_custom)
        $energy_pass_art = get_post_meta($post_id, 'energiepass_art', true);
        $energy_end = get_post_meta($post_id, 'energiepass_endenergie', true);
        $energy_class = get_post_meta($post_id, 'energiepass_wertklasse', true);
        $energy_source_raw = get_post_meta($post_id, 'energiepass_traeger', true);
        $energy_source = $energy_source_raw ? ucwords(strtolower(str_replace('_', ' ', $energy_source_raw))) : '';
        $energy_valid = get_post_meta($post_id, 'energiepass_gueltig_bis', true);
        $energy_year = get_post_meta($post_id, 'energiepass_baujahr', true);

        $class = trim(strtoupper($energy_class));

        ob_start();
        ?>
        <div class="dbw-energy-container">
            <h3 class="dbw-section-title" style="margin-top:0; margin-bottom:1.5rem; font-size:1.25rem;"><?php _e('Energie & Heizung', 'dbw-immo-suite'); ?></h3>

            <div class="dbw-energy-grid">
                <?php if ($energy_year): ?>
                    <div class="dbw-energy-item">
                        <span><?php _e('Baujahr', 'dbw-immo-suite'); ?></span>
                        <strong><?php echo esc_html($energy_year); ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($energy_pass_art): ?>
                    <div class="dbw-energy-item">
                        <span><?php _e('Ausweistyp', 'dbw-immo-suite'); ?></span>
                        <strong><?php echo esc_html(ucfirst(strtolower($energy_pass_art)) . 'sausweis'); ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($energy_end): ?>
                    <div class="dbw-energy-item">
                        <span><?php _e('Endenergieverbrauch', 'dbw-immo-suite'); ?></span>
                        <strong><?php echo esc_html($energy_end); ?> kWh/(m²&middot;a)</strong>
                    </div>
                <?php endif; ?>

                <?php if ($energy_source): ?>
                    <div class="dbw-energy-item">
                        <span><?php _e('Energieträger', 'dbw-immo-suite'); ?></span>
                        <strong><?php echo esc_html($energy_source); ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($energy_valid): ?>
                    <div class="dbw-energy-item">
                        <span><?php _e('Gültig bis', 'dbw-immo-suite'); ?></span>
                        <strong><?php echo esc_html(date_i18n('d.m.Y', strtotime($energy_valid))); ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($class): ?>
                    <div class="dbw-energy-item">
                        <span><?php _e('Energieeffizienzklasse', 'dbw-immo-suite'); ?></span>
                        <strong><?php echo esc_html($class); ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($class && array_key_exists($class, self::COLOR_MAP)): ?>
                <?php
                $keys = array_keys(self::COLOR_MAP);
                $index = array_search($class, $keys);
                $total = count($keys);
                $left_percent = (($index + 0.5) / $total) * 100;
                ?>
                <div class="dbw-energy-scale-wrapper" style="position:relative; margin-top:3rem; padding-bottom:10px;">
                    <div style="position:relative; height:15px; margin-bottom:10px;">
                        <div class="dbw-scale-indicator" style="left:<?php echo $left_percent; ?>%;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 21l-9-9h18z" /></svg>
                        </div>
                    </div>
                    <div class="dbw-scale-bar">
                        <?php foreach (self::COLOR_MAP as $key => $color): ?>
                            <div class="dbw-scale-segment" style="background-color:<?php echo esc_attr($color); ?>;">
                                <?php echo esc_html($key); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php echo self::render_energy_costs($post_id, $energy_end, $energy_source_raw); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the energy cost estimation box.
     */
    private static function render_energy_costs($post_id, $energy_end, $energy_source_raw)
    {
        $settings = get_option('dbw_immo_suite_settings', []);
        $show = isset($settings['energy_show_costs']) ? (bool) $settings['energy_show_costs'] : true;
        if (!$show) {
            return '';
        }

        $endenergie = (float) $energy_end;
        $wohnflaeche = (float) get_post_meta($post_id, 'wohnflaeche', true);

        if ($endenergie <= 0 || $wohnflaeche <= 0) {
            return '';
        }

        $source_key = self::map_energy_source($energy_source_raw);
        $source_unknown = empty($energy_source_raw);
        $price_kwh = self::get_energy_price($source_key);
        $source_label = self::ENERGY_LABELS[$source_key] ?? 'Gas';

        $jahresverbrauch = $endenergie * $wohnflaeche;
        $jahreskosten = $jahresverbrauch * $price_kwh;
        $monatskosten = $jahreskosten / 12;

        // Comparison: average ~100 kWh/m²a — only show when positive (below average)
        $avg_jahreskosten = 100 * $wohnflaeche * $price_kwh;
        $diff_percent = $avg_jahreskosten > 0
            ? round((($jahreskosten - $avg_jahreskosten) / $avg_jahreskosten) * 100)
            : 0;

        $show_positive_hint = ($diff_percent < -5);
        $positive_text = $show_positive_hint
            ? sprintf(
                __('Unterdurchschnittliche Heizkosten — %d %% unter dem Durchschnitt fuer diese Groesse', 'dbw-immo-suite'),
                abs($diff_percent)
            )
            : '';

        $solar_hint = ($source_key === 'solar');

        ob_start();
        ?>
        <div class="dbw-ecost-box" data-endenergie="<?php echo esc_attr($endenergie); ?>" data-wohnflaeche="<?php echo esc_attr($wohnflaeche); ?>" data-price="<?php echo esc_attr($price_kwh); ?>">
            <div class="dbw-ecost-header">
                <svg class="dbw-ecost-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c1 3-2 5-2 8a4 4 0 0 0 8 0c0-3-2-4-2-8"/><path d="M12 22v-4"/><path d="M8 22h8"/></svg>
                <span><?php esc_html_e('Geschaetzte Heizkosten', 'dbw-immo-suite'); ?></span>
            </div>

            <div class="dbw-ecost-result">
                <div class="dbw-ecost-monthly">
                    ~<?php echo number_format($monatskosten, 0, ',', '.'); ?> &euro;/<?php esc_html_e('Monat', 'dbw-immo-suite'); ?>
                </div>
                <div class="dbw-ecost-yearly">
                    <?php echo number_format($jahreskosten, 0, ',', '.'); ?> &euro;/<?php esc_html_e('Jahr', 'dbw-immo-suite'); ?>
                </div>
            </div>

            <div class="dbw-ecost-details">
                <div class="dbw-ecost-detail-row">
                    <span><?php esc_html_e('Energietraeger', 'dbw-immo-suite'); ?></span>
                    <strong>
                        <?php echo esc_html($source_label); ?>
                        <?php if ($source_unknown): ?>
                            <span class="dbw-ecost-note">(<?php esc_html_e('angenommen', 'dbw-immo-suite'); ?>)</span>
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="dbw-ecost-detail-row">
                    <span><?php esc_html_e('Energiepreis', 'dbw-immo-suite'); ?></span>
                    <strong><?php echo number_format($price_kwh, 2, ',', '.'); ?> &euro;/kWh</strong>
                </div>
                <div class="dbw-ecost-detail-row">
                    <span><?php esc_html_e('Jahresverbrauch', 'dbw-immo-suite'); ?></span>
                    <strong><?php echo number_format($jahresverbrauch, 0, ',', '.'); ?> kWh</strong>
                </div>
            </div>

            <?php if ($solar_hint): ?>
                <div class="dbw-ecost-solar-hint">
                    <?php esc_html_e('Primaerenergie durch Solaranlage — keine laufenden Heizkosten.', 'dbw-immo-suite'); ?>
                </div>
            <?php elseif ($show_positive_hint): ?>
                <div class="dbw-ecost-positive-hint">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span><?php echo esc_html($positive_text); ?></span>
                </div>
            <?php endif; ?>

            <div class="dbw-ecost-slider-group">
                <div class="dbw-ecost-slider-header">
                    <label for="dbw-ecost-price-slider"><?php esc_html_e('Energiepreis anpassen', 'dbw-immo-suite'); ?></label>
                    <output id="dbw-ecost-price-output" class="dbw-ecost-output"><?php echo number_format($price_kwh, 2, ',', '.'); ?> &euro;/kWh</output>
                </div>
                <input type="range" id="dbw-ecost-price-slider" class="dbw-calc-range" min="0.03" max="0.50" step="0.01" value="<?php echo esc_attr($price_kwh); ?>">
            </div>

            <p class="dbw-ecost-disclaimer">
                <?php esc_html_e('Schaetzung basierend auf Energieausweis-Daten. Tatsaechliche Kosten koennen abweichen.', 'dbw-immo-suite'); ?>
            </p>
        </div>

        <script>
        (function(){
            var box = document.querySelector('.dbw-ecost-box');
            if (!box) return;
            var slider = document.getElementById('dbw-ecost-price-slider');
            if (!slider) return;
            var endenergie = parseFloat(box.dataset.endenergie);
            var wohnflaeche = parseFloat(box.dataset.wohnflaeche);
            var output = document.getElementById('dbw-ecost-price-output');
            var monthly = box.querySelector('.dbw-ecost-monthly');
            var yearly = box.querySelector('.dbw-ecost-yearly');
            var priceRow = box.querySelectorAll('.dbw-ecost-detail-row')[1];
            var verbrauchRow = box.querySelectorAll('.dbw-ecost-detail-row')[2];

            function fmt(n) { return n.toLocaleString('de-DE', {maximumFractionDigits:0}); }

            function update() {
                var price = parseFloat(slider.value);
                var fill = ((price - 0.03) / (0.50 - 0.03)) * 100;
                slider.style.setProperty('--fill', fill + '%');
                output.innerHTML = price.toLocaleString('de-DE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' \u20AC/kWh';
                var jv = endenergie * wohnflaeche;
                var jk = jv * price;
                var mk = jk / 12;
                monthly.innerHTML = '~' + fmt(mk) + ' \u20AC/Monat';
                yearly.innerHTML = fmt(jk) + ' \u20AC/Jahr';
                if (priceRow) priceRow.querySelector('strong').innerHTML = price.toLocaleString('de-DE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' \u20AC/kWh';

                var avgJk = 100 * wohnflaeche * price;
                var diff = avgJk > 0 ? Math.round(((jk - avgJk) / avgJk) * 100) : 0;
                var hint = box.querySelector('.dbw-ecost-positive-hint');
                if (hint) {
                    if (diff < -5) {
                        hint.style.display = '';
                        hint.querySelector('span').textContent = 'Unterdurchschnittliche Heizkosten \u2014 ' + Math.abs(diff) + ' % unter dem Durchschnitt fuer diese Groesse';
                    } else {
                        hint.style.display = 'none';
                    }
                }
            }

            slider.addEventListener('input', update);
            update();
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
