<?php

namespace DBW\ImmoSuite\Frontend;

if (!defined('ABSPATH')) { exit; }

/**
 * Shared card rendering for property listings.
 * Used by archive template, blocks, and shortcodes to avoid duplication.
 */
class CardRenderer
{

    /**
     * Render a standard property card.
     *
     * @param array $options {
     *     @type bool   $hide_price   Hide the price. Default false.
     *     @type bool   $show_date    Show the listing/sales date. Default false.
     *     @type bool   $is_reference Whether this is a reference/sold card. Default false.
     *     @type bool   $show_energy  Show energy flag. Default true.
     *     @type bool   $show_meta_labels Show labels under meta values. Default true.
     * }
     */
    public static function render($options = array())
    {
        $defaults = array(
            'hide_price'      => false,
            'show_date'       => false,
            'is_reference'    => false,
            'show_energy'     => true,
            'show_meta_labels' => true,
        );
        $opts = wp_parse_args($options, $defaults);

        $post_id = get_the_ID();
        $settings = get_option('dbw_immo_suite_settings');

        // Fetch all meta in a single query
        $all_meta = get_post_custom($post_id);
        $m = function($key) use ($all_meta) {
            return isset($all_meta[$key][0]) ? $all_meta[$key][0] : '';
        };

        // Price data
        $kaufpreis = $m('kaufpreis');
        $kaltmiete = $m('kaltmiete');
        $price = $kaufpreis ?: $kaltmiete;
        $is_rent = !$kaufpreis && $kaltmiete;
        $price_label = $is_rent ? __('Kaltmiete', 'dbw-immo-suite') : __('Kaufpreis', 'dbw-immo-suite');

        // Property data
        $area = $m('wohnflaeche');
        $rooms = $m('anzahl_zimmer');
        $bedrooms = $m('anzahl_schlafzimmer');
        $year = $m('energiepass_baujahr');
        $location = $m('ort');

        // Status / Badge
        $immo_status = $m('_dbw_immo_status');
        $sales_date = $m('_dbw_immo_sales_date');

        // Determine tag & grayscale
        $tag_data = null;
        $is_sold = $opts['is_reference'] || in_array($immo_status, array('verkauft', 'referenz'));
        $is_reserved = ($immo_status === 'reserviert');
        $use_grayscale = ($is_sold && !empty($settings['grayscale_sold']))
                      || ($is_reserved && !empty($settings['grayscale_reserved']));

        if ($opts['is_reference']) {
            $badge_text = ($immo_status === 'referenz')
                ? (!empty($settings['reference_badge_text']) ? $settings['reference_badge_text'] : __('Referenz', 'dbw-immo-suite'))
                : (!empty($settings['sold_badge_text']) ? $settings['sold_badge_text'] : __('Verkauft', 'dbw-immo-suite'));
            $badge_class = ($immo_status === 'referenz') ? 'dbw-tag-reference' : 'dbw-tag-sold';
            $tag_data = array('label' => $badge_text, 'class' => $badge_class);
        } elseif ($immo_status === 'reserviert') {
            $tag_data = array('label' => __('Reserviert', 'dbw-immo-suite'), 'class' => 'dbw-tag-reserved');
        } elseif (class_exists('\DBW\ImmoSuite\Frontend\Filter')) {
            $tag_data = Filter::get_status_label($post_id);
        }

        // Image — exclude contact person portrait
        $contact_img_id = $m('kontaktperson_bild_id');
        $image_id = false;
        $thumb_id = get_post_thumbnail_id($post_id);

        if ($thumb_id && (int) $thumb_id !== (int) $contact_img_id) {
            $image_id = $thumb_id;
        }

        if (!$image_id) {
            // Fallback: first attached property image (not contact portrait)
            $attached = get_attached_media('image', $post_id);
            foreach ($attached as $att) {
                if ($contact_img_id && (int) $att->ID === (int) $contact_img_id) {
                    continue;
                }
                $image_id = $att->ID;
                break;
            }
        }

        $has_image = (bool) $image_id;
        $grayscale_class = $use_grayscale ? ' dbw-grayscale' : '';

        // Card classes
        $card_classes = 'dbw-property-card';
        if ($opts['is_reference']) {
            $card_classes .= ' dbw-reference-card';
        }

        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class($card_classes); ?>>
            <a href="<?php the_permalink(); ?>" class="dbw-property-image<?php echo $has_image ? ' has-image' : ' dbw-property-image--placeholder'; ?>">
                <?php if ($has_image) : ?>
                    <?php echo wp_get_attachment_image($image_id, 'medium_large', false, array(
                        'class' => 'dbw-card-img' . $grayscale_class,
                        'loading' => 'lazy',
                        'decoding' => 'async',
                        'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw',
                    )); ?>
                <?php else : ?>
                    <div class="dbw-placeholder-content">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <span><?php _e('Bilder folgen demnächst', 'dbw-immo-suite'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($tag_data) : ?>
                    <span class="dbw-property-tag <?php echo esc_attr($tag_data['class']); ?>"><?php echo esc_html($tag_data['label']); ?></span>
                <?php endif; ?>
                <?php
                if ($opts['show_energy'] && class_exists('\DBW\ImmoSuite\Frontend\EnergyRenderer')) {
                    EnergyRenderer::render_archive_flag($post_id);
                }
                if (class_exists('\DBW\ImmoSuite\Frontend\PriceComparison')) {
                    PriceComparison::render_archive_badge($post_id);
                }
                ?>
            </a>

            <div class="dbw-property-content">
                <div class="dbw-card-body">
                    <h2 class="dbw-property-title">
                        <a href="<?php the_permalink(); ?>"><?php echo esc_html(self::get_display_title($post_id)); ?></a>
                    </h2>

                    <?php if ($location) : ?>
                        <div class="dbw-property-address">
                            <span class="dashicons dashicons-location"></span> <?php echo esc_html($location); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($opts['show_date']) : ?>
                        <div class="dbw-sales-date">
                            <?php
                            if ($opts['is_reference'] && $sales_date) {
                                echo date_i18n(get_option('date_format'), strtotime($sales_date));
                            } else {
                                echo esc_html(get_the_date());
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="dbw-card-meta-grid">
                        <?php if ($area && get_theme_mod('dbw_immo_archive_show_area', true)) : ?>
                            <div class="dbw-meta-item">
                                <div class="dbw-meta-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                </div>
                                <div class="dbw-meta-data">
                                    <span class="dbw-meta-value"><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($area, 'flaeche')); ?> m²</span>
                                    <?php if ($opts['show_meta_labels']) : ?>
                                        <span class="dbw-meta-label"><?php _e('Wohnfläche', 'dbw-immo-suite'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($rooms && get_theme_mod('dbw_immo_archive_show_rooms', true)) : ?>
                            <div class="dbw-meta-item">
                                <div class="dbw-meta-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                </div>
                                <div class="dbw-meta-data">
                                    <span class="dbw-meta-value"><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($rooms, 'zimmer')); ?> Zi.</span>
                                    <?php if ($opts['show_meta_labels']) : ?>
                                        <span class="dbw-meta-label"><?php _e('Zimmer', 'dbw-immo-suite'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($bedrooms && !$opts['is_reference']) : ?>
                            <div class="dbw-meta-item">
                                <div class="dbw-meta-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8v9"></path></svg>
                                </div>
                                <div class="dbw-meta-data">
                                    <span class="dbw-meta-value"><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($bedrooms, 'zimmer')); ?></span>
                                    <?php if ($opts['show_meta_labels']) : ?>
                                        <span class="dbw-meta-label"><?php _e('Schlafzimmer', 'dbw-immo-suite'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($year && !$opts['is_reference'] && get_theme_mod('dbw_immo_archive_show_year', true)) : ?>
                            <div class="dbw-meta-item">
                                <div class="dbw-meta-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                </div>
                                <div class="dbw-meta-data">
                                    <span class="dbw-meta-value"><?php echo esc_html($year); ?></span>
                                    <?php if ($opts['show_meta_labels']) : ?>
                                        <span class="dbw-meta-label"><?php _e('Baujahr', 'dbw-immo-suite'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dbw-card-footer">
                    <?php if (!$opts['hide_price'] && get_theme_mod('dbw_immo_archive_show_price', true)) : ?>
                        <div class="dbw-property-price">
                            <span class="dbw-price-label"><?php echo esc_html($price ? $price_label : __('Kaufpreis', 'dbw-immo-suite')); ?></span>
                            <span class="dbw-price-value"><?php echo $price ? esc_html(\DBW\ImmoSuite\dbw_format_number($price, 'preis')) . ' €' : esc_html__('Auf Anfrage', 'dbw-immo-suite'); ?></span>
                        </div>
                    <?php else : ?>
                        <div class="dbw-property-price"></div>
                    <?php endif; ?>

                    <a href="<?php the_permalink(); ?>" class="dbw-button-expose"><?php echo esc_html(get_theme_mod('dbw_immo_expose_btn_text', __('Zum Exposé', 'dbw-immo-suite'))); ?></a>
                </div>
            </div>
        </article>
        <?php
    }

    /**
     * Get display title with fallbacks for untitled properties.
     */
    public static function get_display_title($post_id)
    {
        $title = get_the_title($post_id);
        if (!empty($title)) {
            return $title;
        }

        // Fallback 1: Objektart + Ort
        $objektart_terms = wp_get_post_terms($post_id, 'objektart', array('fields' => 'names'));
        $objektart = (!is_wp_error($objektart_terms) && !empty($objektart_terms)) ? $objektart_terms[0] : '';
        $ort = get_post_meta($post_id, 'ort', true);

        if ($objektart && $ort) {
            return sprintf('%s in %s', $objektart, $ort);
        }
        if ($objektart) {
            return $objektart;
        }
        if ($ort) {
            return sprintf(__('Immobilie in %s', 'dbw-immo-suite'), $ort);
        }

        // Fallback 2: Immobilie Nr. [immonr]
        $immonr = get_post_meta($post_id, 'objektnr_extern', true);
        if (!empty($immonr)) {
            return sprintf(__('Immobilie Nr. %s', 'dbw-immo-suite'), $immonr);
        }

        return __('Immobilie', 'dbw-immo-suite');
    }

    /**
     * Helper: Build meta_query to exclude sold/reference items.
     * Used by Filter.php and GridBlock.php to avoid duplication.
     */
    public static function get_exclude_sold_meta_query()
    {
        return array(
            'relation' => 'OR',
            array(
                'key'     => '_dbw_immo_status',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_dbw_immo_status',
                'value'   => 'aktiv',
                'compare' => '=',
            ),
        );
    }
}
