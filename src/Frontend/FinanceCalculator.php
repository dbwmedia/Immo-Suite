<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Kaufnebenkosten- & Finanzierungsrechner for single property view.
 * Only displayed for Kaufobjekte (kaufpreis > 0).
 */
class FinanceCalculator
{
    public function init()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue JS only on single immobilie pages with a Kaufpreis.
     */
    public function enqueue_assets()
    {
        if (!is_singular('immobilie')) {
            return;
        }

        if (!get_theme_mod('dbw_immo_single_show_calculator', true)) {
            return;
        }

        // get_queried_object_id() is reliable in wp_enqueue_scripts (before the loop);
        // the first get_post_meta() call primes the full meta cache for this post
        $post_id = get_queried_object_id();
        $kaufpreis = (float) get_post_meta($post_id, 'kaufpreis', true);

        if ($kaufpreis <= 0) {
            return;
        }

        $provision_raw = get_post_meta($post_id, 'provision_kaeufer', true);
        $plz = get_post_meta($post_id, 'plz', true);

        wp_enqueue_script(
            'dbw-immo-finance-calculator',
            DBW_IMMO_SUITE_URL . 'assets/js/finance-calculator.js',
            array(),
            DBW_IMMO_SUITE_VERSION,
            true
        );

        $settings = get_option('dbw_immo_suite_settings', array());

        wp_localize_script('dbw-immo-finance-calculator', 'dbwFinanceCalc', array(
            'kaufpreis'      => $kaufpreis,
            'provision'      => $provision_raw,
            'plz'            => $plz,
            'notarPercent'   => isset($settings['calc_notar_percent']) ? (float) $settings['calc_notar_percent'] : 1.5,
            'grundbuchPercent' => isset($settings['calc_grundbuch_percent']) ? (float) $settings['calc_grundbuch_percent'] : 0.5,
            'defaultZinssatz'  => isset($settings['calc_default_zinssatz']) ? (float) $settings['calc_default_zinssatz'] : 3.5,
            'defaultTilgung'   => isset($settings['calc_default_tilgung']) ? (float) $settings['calc_default_tilgung'] : 2.0,
            'gestOverride'     => isset($settings['calc_gest_override']) ? (float) $settings['calc_gest_override'] : 0,
            'i18n'       => array(
                'headline'          => \DBW\ImmoSuite\dbw_anrede(
                    __('Ihre Kaufnebenkosten & Finanzierung', 'dbw-immo-suite'),
                    __('Deine Kaufnebenkosten & Finanzierung', 'dbw-immo-suite')
                ),
                'kaufpreis'         => __('Kaufpreis', 'dbw-immo-suite'),
                'grunderwerbsteuer' => __('Grunderwerbsteuer', 'dbw-immo-suite'),
                'notarkosten'       => __('Notarkosten', 'dbw-immo-suite'),
                'grundbuchamt'      => __('Grundbuchamt', 'dbw-immo-suite'),
                'maklerprovision'   => __('Maklerprovision', 'dbw-immo-suite'),
                'gesamtkosten'      => __('Gesamtkosten', 'dbw-immo-suite'),
                'eigenkapital'      => __('Eigenkapital', 'dbw-immo-suite'),
                'zinssatz'          => __('Zinssatz', 'dbw-immo-suite'),
                'tilgung'           => __('Tilgung', 'dbw-immo-suite'),
                'darlehenssumme'    => __('Darlehenssumme', 'dbw-immo-suite'),
                'monatliche_rate'   => __('Monatliche Rate', 'dbw-immo-suite'),
                'zinskosten_10j'    => __('Zinskosten nach 10 Jahren', 'dbw-immo-suite'),
                'finanzierung'      => \DBW\ImmoSuite\dbw_anrede(
                    __('Ihre Finanzierung berechnen', 'dbw-immo-suite'),
                    __('Deine Finanzierung berechnen', 'dbw-immo-suite')
                ),
                'hinweis'           => \DBW\ImmoSuite\dbw_anrede(
                    __('Die Berechnung dient als unverbindliche Orientierung. Sprechen Sie uns fuer ein individuelles Finanzierungsangebot an.', 'dbw-immo-suite'),
                    __('Die Berechnung dient als unverbindliche Orientierung. Sprich uns fuer ein individuelles Finanzierungsangebot an.', 'dbw-immo-suite')
                ),
                'bundesland_unknown' => __('Bundesland nicht erkannt', 'dbw-immo-suite'),
            ),
        ));
    }

    /**
     * Render the calculator section.
     * Called from single-immobilie.php template.
     *
     * @param int $post_id
     */
    public static function render($post_id)
    {
        if (!get_theme_mod('dbw_immo_single_show_calculator', true)) {
            return;
        }

        $kaufpreis = (float) get_post_meta($post_id, 'kaufpreis', true);

        if ($kaufpreis <= 0) {
            return;
        }

        ?>
        <div class="dbw-section dbw-finance-calculator" id="dbw-finance-calculator">
            <h3 class="dbw-section-title" id="dbw-calc-headline"></h3>

            <div class="dbw-calc-grid">

                <!-- Kaufnebenkosten Card -->
                <div class="dbw-calc-card">
                    <div class="dbw-calc-card-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        <span><?php esc_html_e('Kaufnebenkosten', 'dbw-immo-suite'); ?></span>
                    </div>

                    <div class="dbw-calc-rows">
                        <div class="dbw-calc-row">
                            <span id="dbw-calc-label-kaufpreis"></span>
                            <span class="dbw-calc-val" id="dbw-calc-kaufpreis"></span>
                        </div>
                        <div class="dbw-calc-row">
                            <span>
                                <span id="dbw-calc-label-gest"></span>
                                <span class="dbw-calc-pct" id="dbw-calc-gest-detail"></span>
                            </span>
                            <span class="dbw-calc-val" id="dbw-calc-gest"></span>
                        </div>
                        <div class="dbw-calc-row">
                            <span>
                                <span id="dbw-calc-label-notar"></span>
                                <span class="dbw-calc-pct" id="dbw-calc-notar-detail"></span>
                            </span>
                            <span class="dbw-calc-val" id="dbw-calc-notar"></span>
                        </div>
                        <div class="dbw-calc-row">
                            <span>
                                <span id="dbw-calc-label-grundbuch"></span>
                                <span class="dbw-calc-pct" id="dbw-calc-grundbuch-detail"></span>
                            </span>
                            <span class="dbw-calc-val" id="dbw-calc-grundbuch"></span>
                        </div>
                        <div class="dbw-calc-row" id="dbw-calc-provision-row">
                            <span>
                                <span id="dbw-calc-label-provision"></span>
                                <span class="dbw-calc-pct" id="dbw-calc-provision-detail"></span>
                            </span>
                            <span class="dbw-calc-val" id="dbw-calc-provision"></span>
                        </div>
                    </div>

                    <div class="dbw-calc-total-row">
                        <span id="dbw-calc-label-gesamt"></span>
                        <span id="dbw-calc-gesamt"></span>
                    </div>
                </div>

                <!-- Finanzierungsrechner Card -->
                <div class="dbw-calc-card">
                    <div class="dbw-calc-card-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span id="dbw-calc-fin-headline"></span>
                    </div>

                    <div class="dbw-calc-sliders">
                        <div class="dbw-calc-slider-group">
                            <div class="dbw-calc-slider-header">
                                <label for="dbw-calc-eigenkapital" id="dbw-calc-label-ek"></label>
                                <output id="dbw-calc-ek-output" class="dbw-calc-output"></output>
                            </div>
                            <input type="range" id="dbw-calc-eigenkapital" class="dbw-calc-range" min="0" step="1000">
                        </div>

                        <div class="dbw-calc-slider-group">
                            <div class="dbw-calc-slider-header">
                                <label for="dbw-calc-zinssatz" id="dbw-calc-label-zins"></label>
                                <output id="dbw-calc-zins-output" class="dbw-calc-output"></output>
                            </div>
                            <input type="range" id="dbw-calc-zinssatz" class="dbw-calc-range" min="1.0" max="6.0" step="0.1">
                        </div>

                        <div class="dbw-calc-slider-group">
                            <div class="dbw-calc-slider-header">
                                <label for="dbw-calc-tilgung" id="dbw-calc-label-tilgung"></label>
                                <output id="dbw-calc-tilgung-output" class="dbw-calc-output"></output>
                            </div>
                            <input type="range" id="dbw-calc-tilgung" class="dbw-calc-range" min="1.0" max="5.0" step="0.1">
                        </div>
                    </div>

                    <!-- Ergebnis -->
                    <div class="dbw-calc-result">
                        <div class="dbw-calc-result-row">
                            <span id="dbw-calc-label-darlehen"></span>
                            <strong id="dbw-calc-darlehen"></strong>
                        </div>
                        <div class="dbw-calc-result-row dbw-calc-result-highlight">
                            <span id="dbw-calc-label-rate"></span>
                            <strong id="dbw-calc-rate"></strong>
                        </div>
                        <div class="dbw-calc-result-row">
                            <span id="dbw-calc-label-zinskosten"></span>
                            <strong id="dbw-calc-zinskosten"></strong>
                        </div>
                    </div>
                </div>

            </div>

            <p class="dbw-calc-hinweis" id="dbw-calc-hinweis"></p>

            <?php if (get_theme_mod('dbw_immo_single_show_contact', true)): ?>
            <div class="dbw-calc-cta">
                <button type="button"
                        class="dbw-cta dbw-cta--primary"
                        data-dbw-open-modal="<?php echo esc_attr($post_id); ?>"
                        data-dbw-intent="preis">
                    <svg class="dbw-cta__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span class="dbw-cta__text"><?php esc_html_e('Finanzierung unverbindlich anfragen', 'dbw-immo-suite'); ?></span>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
