<?php

if (!defined('ABSPATH')) { exit; }

namespace DBW\ImmoSuite\Frontend;

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
        </div>
        <?php
        return ob_get_clean();
    }
}
