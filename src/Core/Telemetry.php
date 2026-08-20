<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Daily health ping to the vendor (dbw media).
 *
 * Purely technical data (version, import health, inventory count) so broken
 * feeds are spotted before the customer calls. No personal data, no visitor
 * data. Can be disabled in the settings; failures are silently ignored and
 * never slow down the site (non-blocking request).
 */
class Telemetry
{
    const CRON_HOOK = 'dbw_immo_telemetry_ping';
    const ENDPOINT  = 'https://os.dbw-media.de/api/immo-telemetry.php';

    public function init()
    {
        add_action(self::CRON_HOOK, array($this, 'ping'));

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 6 * HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function is_enabled()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        return !isset($settings['telemetry_enabled']) || (int) $settings['telemetry_enabled'] === 1;
    }

    /**
     * Payload: technical health data only.
     */
    public static function payload()
    {
        $history = get_option('dbw_immo_import_history', array());
        $last    = !empty($history) ? end($history) : null;
        $counts  = wp_count_posts('immobilie');
        $alert   = get_option('dbw_immo_import_alert');

        return array(
            'site'           => home_url(),
            'plugin_version' => defined('DBW_IMMO_SUITE_VERSION') ? DBW_IMMO_SUITE_VERSION : '',
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => PHP_VERSION,
            'license_valid'  => (bool) \DBW\ImmoSuite\Core\License::is_valid(),
            'properties'     => isset($counts->publish) ? (int) $counts->publish : 0,
            'last_import'    => $last ? array(
                'date'   => $last['date'],
                'status' => $last['status'],
                'errors' => (int) $last['errors'],
            ) : null,
            'alert'          => !empty($alert['type']) ? $alert['type'] : null,
            'sent_at'        => current_time('mysql'),
        );
    }

    public function ping()
    {
        if (!self::is_enabled()) {
            return;
        }

        // Endpoint override for staging/self-hosted setups
        $endpoint = apply_filters('dbw_immo_telemetry_endpoint', self::ENDPOINT);

        wp_remote_post($endpoint, array(
            'timeout'  => 5,
            'blocking' => false,
            'headers'  => array('Content-Type' => 'application/json'),
            'body'     => wp_json_encode(self::payload()),
        ));
    }
}
