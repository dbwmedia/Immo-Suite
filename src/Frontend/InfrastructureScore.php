<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

class InfrastructureScore
{
    private static $categories = array(
        'oepnv' => array(
            'label'   => 'OEPNV',
            'icon'    => 'bus',
            'weight'  => 25,
            'keys'    => array('distanz_oeffentliche_verkehrsmittel', 'distanz_us_bahn', 'distanz_bus'),
        ),
        'einkaufen' => array(
            'label'   => 'Einkaufen',
            'icon'    => 'cart',
            'weight'  => 20,
            'keys'    => array('distanz_einkaufsmoeglichkeiten', 'distanz_zentrum'),
        ),
        'bildung' => array(
            'label'   => 'Bildung',
            'icon'    => 'graduation',
            'weight'  => 25,
            'keys'    => array('distanz_kindergarten', 'distanz_grundschule', 'distanz_gymnasium', 'distanz_realschule', 'distanz_hauptschule', 'distanz_gesamtschule'),
        ),
        'gastronomie' => array(
            'label'   => 'Gastronomie',
            'icon'    => 'fork',
            'weight'  => 10,
            'keys'    => array('distanz_gaststaetten'),
        ),
        'verkehr' => array(
            'label'   => 'Verkehr',
            'icon'    => 'car',
            'weight'  => 20,
            'keys'    => array('distanz_autobahn', 'distanz_flughafen'),
        ),
    );

    private static $label_map = array(
        'distanz_oeffentliche_verkehrsmittel' => 'OEPNV',
        'distanz_us_bahn'                     => 'U-/S-Bahn',
        'distanz_bus'                         => 'Bus',
        'distanz_einkaufsmoeglichkeiten'      => 'Einkaufen',
        'distanz_zentrum'                     => 'Zentrum',
        'distanz_kindergarten'                => 'Kindergarten',
        'distanz_grundschule'                 => 'Grundschule',
        'distanz_gymnasium'                   => 'Gymnasium',
        'distanz_realschule'                  => 'Realschule',
        'distanz_hauptschule'                 => 'Hauptschule',
        'distanz_gesamtschule'                => 'Gesamtschule',
        'distanz_gaststaetten'                => 'Gaststaetten',
        'distanz_autobahn'                    => 'Autobahn',
        'distanz_flughafen'                   => 'Flughafen',
    );

    public function init()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets()
    {
        if (!is_singular('immobilie')) {
            return;
        }

        if (!get_theme_mod('dbw_immo_single_show_infra_score', true)) {
            return;
        }

        $post_id = get_the_ID();
        $data = self::calculate($post_id);

        if ($data === null) {
            return;
        }

        wp_enqueue_script(
            'dbw-immo-infra-score',
            DBW_IMMO_SUITE_URL . 'assets/js/infra-score.js',
            array(),
            DBW_IMMO_SUITE_VERSION,
            true
        );
    }

    /**
     * Parse a distance string to meters.
     * Handles: "200", "200m", "200 m", "0.2", "0.2 km", "0,2km", "0,2 km"
     */
    public static function parse_distance_to_meters($raw)
    {
        if (empty($raw)) {
            return null;
        }

        $raw = trim((string) $raw);
        $raw = str_replace(',', '.', $raw);

        // Extract numeric value and optional unit
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*(km|m)?$/i', $raw, $matches)) {
            return null;
        }

        $value = (float) $matches[1];
        $unit  = isset($matches[2]) ? strtolower($matches[2]) : '';

        if ($unit === 'km') {
            return $value * 1000;
        }

        if ($unit === 'm') {
            return $value;
        }

        // No unit: heuristic — values <= 30 are likely km, otherwise meters
        if ($value <= 30) {
            return $value * 1000;
        }

        return $value;
    }

    /**
     * Score a distance in meters on a 0-10 scale.
     */
    private static function score_distance($meters)
    {
        if ($meters <= 500)   return 10;
        if ($meters <= 1000)  return 8;
        if ($meters <= 2000)  return 6;
        if ($meters <= 5000)  return 4;
        if ($meters <= 10000) return 2;
        return 1;
    }

    /**
     * Calculate infrastructure score for a property.
     *
     * @param int $post_id
     * @return array|null Null if fewer than 3 distance fields available.
     */
    public static function calculate($post_id)
    {
        $all_meta = get_post_custom($post_id);

        // Collect all distanz_ values
        $distances = array();
        foreach ($all_meta as $key => $val) {
            if (strpos($key, 'distanz_') === 0 && !empty($val[0])) {
                $meters = self::parse_distance_to_meters($val[0]);
                if ($meters !== null) {
                    $distances[$key] = array(
                        'raw'    => $val[0],
                        'meters' => $meters,
                        'score'  => self::score_distance($meters),
                    );
                }
            }
        }

        // Minimum 3 distance fields required
        if (count($distances) < 3) {
            return null;
        }

        // Calculate per-category scores
        $categories = array();
        $total_weight = 0;
        $weighted_sum = 0;

        foreach (self::$categories as $cat_key => $cat) {
            $cat_scores = array();
            $cat_details = array();

            foreach ($cat['keys'] as $meta_key) {
                if (isset($distances[$meta_key])) {
                    $d = $distances[$meta_key];
                    $cat_scores[] = $d['score'];
                    $label = isset(self::$label_map[$meta_key]) ? self::$label_map[$meta_key] : ucfirst(str_replace('distanz_', '', $meta_key));
                    $cat_details[] = array(
                        'label'  => $label,
                        'meters' => $d['meters'],
                        'score'  => $d['score'],
                        'raw'    => $d['raw'],
                    );
                }
            }

            if (empty($cat_scores)) {
                continue;
            }

            $avg_score = array_sum($cat_scores) / count($cat_scores);

            $categories[$cat_key] = array(
                'label'   => $cat['label'],
                'icon'    => $cat['icon'],
                'score'   => round($avg_score, 1),
                'weight'  => $cat['weight'],
                'details' => $cat_details,
            );

            $total_weight += $cat['weight'];
            $weighted_sum += $avg_score * $cat['weight'];
        }

        if ($total_weight === 0) {
            return null;
        }

        $overall = round($weighted_sum / $total_weight, 1);

        return array(
            'score'      => $overall,
            'label'      => self::get_score_label($overall),
            'color'      => self::get_score_color($overall),
            'categories' => $categories,
        );
    }

    private static function get_score_label($score)
    {
        if ($score >= 8) return 'Sehr gut';
        if ($score >= 6) return 'Gut';
        if ($score >= 4) return 'Durchschnitt';
        return 'Ausbaufaehig';
    }

    private static function get_score_color($score)
    {
        if ($score >= 8) return '#28a745';
        if ($score >= 6) return 'var(--dbw-accent, #2573a7)';
        if ($score >= 4) return '#f39c12';
        return '#e74c3c';
    }

    private static function get_score_hex($score)
    {
        if ($score >= 8) return '#28a745';
        if ($score >= 6) return '#2573a7';
        if ($score >= 4) return '#f39c12';
        return '#e74c3c';
    }

    private static function format_distance($meters)
    {
        if ($meters >= 1000) {
            return round($meters / 1000, 1) . ' km';
        }
        return round($meters) . ' m';
    }

    /**
     * Render the SVG icon for a category.
     */
    private static function render_icon($icon)
    {
        $icons = array(
            'bus' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v6"/><path d="M16 6v6"/><path d="M2 12h20"/><path d="M17.5 18H19a2 2 0 0 0 2-2V6a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v10a2 2 0 0 0 2 2h1.5"/><circle cx="8" cy="18" r="2"/><circle cx="16" cy="18" r="2"/></svg>',
            'cart' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>',
            'graduation' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/></svg>',
            'fork' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>',
            'car' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9L18 10l-3-5H9L6 10l-2.5 1.1C2.7 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>',
        );

        return isset($icons[$icon]) ? $icons[$icon] : '';
    }

    /**
     * Render the infrastructure score section on the single property page.
     *
     * @param int $post_id
     */
    public static function render($post_id)
    {
        if (!get_theme_mod('dbw_immo_single_show_infra_score', true)) {
            return;
        }

        $data = self::calculate($post_id);

        if ($data === null) {
            return;
        }

        $score = $data['score'];
        $color = $data['color'];
        // SVG ring calculations (radius 54, circumference ~339.29)
        $circumference = 339.29;
        $offset = $circumference - ($circumference * ($score / 10));

        ?>
        <div class="dbw-section dbw-infra-score" id="dbw-infra-score" data-score="<?php echo esc_attr($score); ?>">
            <h3 class="dbw-section-title">Infrastruktur-Score</h3>

            <div class="dbw-infra-score-layout">
                <!-- Hero Ring -->
                <div class="dbw-infra-ring-wrap">
                    <svg class="dbw-infra-ring" viewBox="0 0 120 120" width="140" height="140">
                        <circle cx="60" cy="60" r="54" fill="none" stroke="#e8e8e8" stroke-width="8"/>
                        <circle class="dbw-infra-ring-progress" cx="60" cy="60" r="54" fill="none"
                            stroke="<?php echo esc_attr(self::get_score_hex($score)); ?>"
                            stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="<?php echo esc_attr($circumference); ?>"
                            stroke-dashoffset="<?php echo esc_attr($circumference); ?>"
                            data-target="<?php echo esc_attr($offset); ?>"
                            transform="rotate(-90 60 60)"/>
                        <text x="60" y="56" text-anchor="middle" class="dbw-infra-ring-score"
                            fill="<?php echo esc_attr(self::get_score_hex($score)); ?>"><?php echo esc_html($score); ?></text>
                        <text x="60" y="72" text-anchor="middle" class="dbw-infra-ring-max">von 10</text>
                    </svg>
                    <div class="dbw-infra-ring-label" style="color: <?php echo esc_attr(self::get_score_hex($score)); ?>;">
                        <?php echo esc_html($data['label']); ?>
                    </div>
                </div>

                <!-- Category Bars -->
                <div class="dbw-infra-categories">
                    <?php foreach ($data['categories'] as $cat_key => $cat): ?>
                        <div class="dbw-infra-cat" data-cat="<?php echo esc_attr($cat_key); ?>">
                            <button class="dbw-infra-cat-header" type="button" aria-expanded="false" aria-controls="dbw-infra-detail-<?php echo esc_attr($cat_key); ?>">
                                <span class="dbw-infra-cat-icon"><?php echo self::render_icon($cat['icon']); ?></span>
                                <span class="dbw-infra-cat-label"><?php echo esc_html($cat['label']); ?></span>
                                <span class="dbw-infra-cat-bar-wrap">
                                    <span class="dbw-infra-cat-bar" data-width="<?php echo esc_attr($cat['score'] * 10); ?>"
                                        style="background-color: <?php echo esc_attr(self::get_score_hex($cat['score'])); ?>;"></span>
                                </span>
                                <span class="dbw-infra-cat-score"><?php echo esc_html($cat['score']); ?>/10</span>
                                <span class="dbw-infra-cat-toggle" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </span>
                            </button>
                            <div class="dbw-infra-cat-details" id="dbw-infra-detail-<?php echo esc_attr($cat_key); ?>" hidden>
                                <?php foreach ($cat['details'] as $detail): ?>
                                    <div class="dbw-infra-detail-row">
                                        <span><?php echo esc_html($detail['label']); ?></span>
                                        <span class="dbw-infra-detail-dist"><?php echo esc_html(self::format_distance($detail['meters'])); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render compact version for the PDF expose.
     *
     * @param int $post_id
     * @param string $accent Hex color for accent
     * @param string $primary Hex color for primary text
     */
    public static function render_expose($post_id, $accent = '#2573a7', $primary = '#2c3e50')
    {
        $data = self::calculate($post_id);

        if ($data === null) {
            return;
        }

        $score = $data['score'];
        $hex_color = self::get_score_hex($score);
        $circumference = 339.29;
        $offset = $circumference - ($circumference * ($score / 10));

        ?>
        <div class="section avoid-break" style="margin-top: 1.5em;">
            <h2 class="section-title">Infrastruktur-Score</h2>
            <div style="display: flex; align-items: flex-start; gap: 24px;">
                <div style="flex-shrink: 0; text-align: center;">
                    <svg viewBox="0 0 120 120" width="80" height="80">
                        <circle cx="60" cy="60" r="54" fill="none" stroke="#e8e8e8" stroke-width="8"/>
                        <circle cx="60" cy="60" r="54" fill="none"
                            stroke="<?php echo esc_attr($hex_color); ?>"
                            stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="<?php echo esc_attr($circumference); ?>"
                            stroke-dashoffset="<?php echo esc_attr($offset); ?>"
                            transform="rotate(-90 60 60)"/>
                        <text x="60" y="58" text-anchor="middle" font-size="28" font-weight="800"
                            fill="<?php echo esc_attr($hex_color); ?>"><?php echo esc_html($score); ?></text>
                        <text x="60" y="74" text-anchor="middle" font-size="10" fill="#888">von 10</text>
                    </svg>
                    <div style="font-size: 8pt; font-weight: 600; color: <?php echo esc_attr($hex_color); ?>; margin-top: 2px;">
                        <?php echo esc_html($data['label']); ?>
                    </div>
                </div>
                <table class="facts-table" style="flex: 1;">
                    <?php foreach ($data['categories'] as $cat): ?>
                        <tr>
                            <td><?php echo esc_html($cat['label']); ?></td>
                            <td>
                                <span style="display: inline-block; width: 60px; height: 6px; background: #e8e8e8; border-radius: 3px; vertical-align: middle; margin-right: 6px; position: relative;">
                                    <span style="display: block; height: 100%; width: <?php echo esc_attr($cat['score'] * 10); ?>%; background: <?php echo esc_attr(self::get_score_hex($cat['score'])); ?>; border-radius: 3px;"></span>
                                </span>
                                <strong><?php echo esc_html($cat['score']); ?>/10</strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php
    }
}
