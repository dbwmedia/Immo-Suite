<?php

namespace DBW\ImmoSuite\PostTypes;

if (!defined('ABSPATH')) { exit; }

/**
 * Inquiry inbox: stores contact/expose requests as a non-public CPT so leads
 * survive lost emails. Mail delivery stays untouched (notification channel).
 */
class Inquiry
{
    const POST_TYPE = 'immo_anfrage';
    const CRON_HOOK = 'dbw_immo_inquiry_cleanup';

    /**
     * Workflow statuses (meta `_dbw_anfrage_status`).
     */
    public static function get_statuses()
    {
        return array(
            'neu'         => __('Neu', 'dbw-immo-suite'),
            'kontaktiert' => __('Kontaktiert', 'dbw-immo-suite'),
            'erledigt'    => __('Erledigt', 'dbw-immo-suite'),
        );
    }

    /**
     * Intent labels shared by admin list and detail view.
     */
    public static function get_intent_labels()
    {
        return array(
            'besichtigung' => __('Besichtigung', 'dbw-immo-suite'),
            'info'         => __('Mehr Infos', 'dbw-immo-suite'),
            'preis'        => __('Preis/Finanzierung', 'dbw-immo-suite'),
            'rueckruf'     => __('Rueckruf', 'dbw-immo-suite'),
            'expose'       => __('Expose-Anfrage', 'dbw-immo-suite'),
        );
    }

    /**
     * Whether inbox storage is enabled (default: on).
     */
    public static function is_enabled()
    {
        $settings = get_option('dbw_immo_suite_settings');
        return !isset($settings['inquiry_store']) || (int) $settings['inquiry_store'] === 1;
    }

    /**
     * Retention in days for the automatic cleanup (0 = keep forever).
     */
    public static function get_retention_days()
    {
        $settings = get_option('dbw_immo_suite_settings');
        return isset($settings['inquiry_retention_days']) ? absint($settings['inquiry_retention_days']) : 180;
    }

    public function register_post_type()
    {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name'          => __('Anfragen', 'dbw-immo-suite'),
                'singular_name' => __('Anfrage', 'dbw-immo-suite'),
                'menu_name'     => __('Anfragen', 'dbw-immo-suite'),
                'all_items'     => __('Anfragen', 'dbw-immo-suite'),
                'edit_item'     => __('Anfrage ansehen', 'dbw-immo-suite'),
                'search_items'  => __('Anfragen durchsuchen', 'dbw-immo-suite'),
                'not_found'     => __('Keine Anfragen vorhanden.', 'dbw-immo-suite'),
            ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=immobilie',
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'supports'            => array('title'),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            // Inquiries are created by visitors via the forms, never manually
            'capabilities'        => array('create_posts' => 'do_not_allow'),
        ));

        // Daily cleanup of old inquiries (GDPR storage limitation)
        add_action(self::CRON_HOOK, array($this, 'run_cleanup'));
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Store an inquiry. Called from ContactForm/ExposeRequest BEFORE wp_mail,
     * so a failed mail no longer loses the lead.
     *
     * @param array $data {
     *     name, email, phone, message, intent, intent_details (string),
     *     property_id (int), source ('kontakt'|'expose'), preferred
     * }
     * @return int|false Post ID or false.
     */
    public static function save(array $data)
    {
        if (!self::is_enabled()) {
            return false;
        }

        $property_id    = isset($data['property_id']) ? (int) $data['property_id'] : 0;
        $property_title = $property_id ? get_the_title($property_id) : '';
        $name           = isset($data['name']) ? sanitize_text_field($data['name']) : '';

        $post_id = wp_insert_post(array(
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish', // CPT is not publicly queryable
            'post_title'  => trim($name . ($property_title ? ' · ' . $property_title : '')),
        ), true);

        if (is_wp_error($post_id) || !$post_id) {
            return false;
        }

        $meta = array(
            '_dbw_anfrage_status'    => 'neu',
            '_dbw_anfrage_name'      => $name,
            '_dbw_anfrage_email'     => isset($data['email']) ? sanitize_email($data['email']) : '',
            '_dbw_anfrage_phone'     => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
            '_dbw_anfrage_message'   => isset($data['message']) ? sanitize_textarea_field($data['message']) : '',
            '_dbw_anfrage_intent'    => isset($data['intent']) ? sanitize_key($data['intent']) : '',
            '_dbw_anfrage_details'   => isset($data['intent_details']) ? sanitize_textarea_field($data['intent_details']) : '',
            '_dbw_anfrage_property'  => $property_id,
            '_dbw_anfrage_source'    => isset($data['source']) ? sanitize_key($data['source']) : 'kontakt',
            '_dbw_anfrage_preferred' => isset($data['preferred']) ? sanitize_key($data['preferred']) : '',
            '_dbw_anfrage_consent'   => wp_date('Y-m-d H:i:s'),
        );
        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        return $post_id;
    }

    /**
     * Delete inquiries older than the configured retention (hard delete — PII).
     */
    public function run_cleanup()
    {
        $days = self::get_retention_days();
        if ($days < 1) {
            return;
        }

        // Loop until empty (bounded): a daily cap of 100 could otherwise build
        // a backlog that outlives the advertised GDPR retention period
        for ($round = 0; $round < 20; $round++) {
            $old = get_posts(array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 100,
                'fields'         => 'ids',
                'date_query'     => array(
                    array('before' => $days . ' days ago', 'column' => 'post_date_gmt'),
                ),
            ));

            if (empty($old)) {
                break;
            }

            foreach ($old as $post_id) {
                wp_delete_post($post_id, true);
            }
        }
    }

    /**
     * Number of inquiries with status "neu" (menu bubble + dashboard widget).
     */
    public static function count_new()
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_dbw_anfrage_status')
             WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value = 'neu'",
            self::POST_TYPE
        ));
    }
}
