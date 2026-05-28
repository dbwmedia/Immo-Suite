<?php

namespace DBW\ImmoSuite\Frontend;

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

        // Price data
        $kaufpreis = get_post_meta($post_id, 'kaufpreis', true);
        $kaltmiete = get_post_meta($post_id, 'kaltmiete', true);
        $price = $kaufpreis ?: $kaltmiete;
        $is_rent = !$kaufpreis && $kaltmiete;
        $price_label = $is_rent ? __('Kaltmiete', 'dbw-immo-suite') : __('Kaufpreis', 'dbw-immo-suite');

        // Property data
        $area = get_post_meta($post_id, 'wohnflaeche', true);
        $rooms = get_post_meta($post_id, 'anzahl_zimmer', true);
        $bedrooms = get_post_meta($post_id, 'anzahl_schlafzimmer', true);
        $year = get_post_meta($post_id, 'energiepass_baujahr', true);
        $location = get_post_meta($post_id, 'ort', true);

        // Status / Badge
        $immo_status = get_post_meta($post_id, '_dbw_immo_status', true);
        $sales_date = get_post_meta($post_id, '_dbw_immo_sales_date', true);

        // Determine tag
        $tag_data = null;
        $is_inactive = $opts['is_reference'] || in_array($immo_status, array('verkauft', 'referenz', 'reserviert'));

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

        // Image style
        $image_style = '';
        if (has_post_thumbnail()) {
            $image_style = 'background-image: url(' . get_the_post_thumbnail_url($post_id, 'medium-large') . ');';
            if ($is_inactive) {
                $image_style .= ' filter: grayscale(100%);';
            }
        }

        // Card classes
        $card_classes = 'dbw-property-card';
        if ($opts['is_reference']) {
            $card_classes .= ' dbw-reference-card';
        }

        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class($card_classes); ?>>
            <a href="<?php the_permalink(); ?>" class="dbw-property-image" style="<?php echo $image_style; ?>">
                <?php if ($tag_data) : ?>
                    <span class="dbw-property-tag <?php echo esc_attr($tag_data['class']); ?>"><?php echo esc_html($tag_data['label']); ?></span>
                <?php endif; ?>
                <?php
                if ($opts['show_energy'] && class_exists('\DBW\ImmoSuite\Frontend\EnergyRenderer')) {
                    EnergyRenderer::render_archive_flag($post_id);
                }
                ?>
            </a>

            <div class="dbw-property-content">
                <div class="dbw-card-body">
                    <h2 class="dbw-property-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
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
                                    <span class="dbw-meta-value"><?php echo esc_html($area); ?> m²</span>
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
                                    <span class="dbw-meta-value"><?php echo esc_html($rooms); ?> Zi.</span>
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
                                    <span class="dbw-meta-value"><?php echo esc_html($bedrooms); ?></span>
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
                    <?php if (!$opts['hide_price'] && $price && get_theme_mod('dbw_immo_archive_show_price', true)) : ?>
                        <div class="dbw-property-price">
                            <span class="dbw-price-label"><?php echo esc_html($price_label); ?></span>
                            <span class="dbw-price-value"><?php echo esc_html(number_format_i18n((float) $price, 0)); ?> €</span>
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
