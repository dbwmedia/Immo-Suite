<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Import monitoring: raises an admin notice + throttled email when the last
 * import failed or no feed has been processed for too long. A broken FTP
 * push otherwise goes unnoticed for weeks while the website shows stale data.
 */
class ImportMonitor
{
    const CRON_HOOK    = 'dbw_immo_monitor_check';
    const ALERT_OPTION = 'dbw_immo_import_alert';

    /**
     * Hours without a processed feed before the "stale" alert fires.
     * Filterable for brokers whose software only pushes on changes.
     */
    private static function stale_hours()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        $hours = isset($settings['monitor_stale_hours']) && $settings['monitor_stale_hours'] !== ''
            ? (int) $settings['monitor_stale_hours']
            : 48;
        return (int) apply_filters('dbw_immo_import_stale_hours', $hours);
    }

    /**
     * Where technical warnings go. Falls back to the WordPress admin address,
     * which on a customer site is the customer — the agency usually wants these
     * instead, hence the dedicated setting.
     */
    private static function alert_email()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        $mail = isset($settings['monitor_email']) ? $settings['monitor_email'] : '';
        $mail = is_email($mail) ? $mail : get_option('admin_email');
        return apply_filters('dbw_immo_monitor_email', $mail);
    }

    /**
     * Whether a run that finished but reported single object errors should mail.
     * Off by default: one broken image in a feed of 80 is not an incident.
     */
    private static function mail_on_partial()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        return !empty($settings['monitor_mail_on_partial']);
    }

    public function init()
    {
        // Evaluate right after each hourly import run + one independent daily
        // check (catches the case where the import cron itself stopped firing)
        add_action('dbw_immo_cron_hook', array($this, 'check'), 20);
        add_action(self::CRON_HOOK, array($this, 'check'));
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }

        add_action('admin_notices', array($this, 'admin_notice'));
    }

    /**
     * Evaluate import health and raise/clear the alert.
     */
    public function check()
    {
        $history = get_option('dbw_immo_import_history', array());
        if (empty($history)) {
            // Never imported → onboarding case, not a monitoring case
            delete_option(self::ALERT_OPTION);
            return;
        }

        $last = end($history);
        $alert = null;

        if (!in_array($last['status'], array('success', 'skipped'), true)) {
            // Run aborted — the feed did not get through at all
            $alert = array(
                'type'    => 'errors',
                'message' => sprintf(
                    __('Der letzte OpenImmo-Import (%1$s, Datei %2$s) ist mit Fehlern beendet worden.', 'dbw-immo-suite'),
                    $last['date'],
                    $last['file']
                ),
            );
        } elseif (!empty($last['errors'])) {
            // Run finished, single objects failed. Worth a notice, not an alarm:
            // one unreadable image must not mail every day.
            $alert = array(
                'type'    => 'partial',
                'message' => sprintf(
                    _n(
                        'Der letzte OpenImmo-Import (%1$s, Datei %2$s) lief durch, dabei konnte %3$d Objekt nicht verarbeitet werden.',
                        'Der letzte OpenImmo-Import (%1$s, Datei %2$s) lief durch, dabei konnten %3$d Objekte nicht verarbeitet werden.',
                        (int) $last['errors'],
                        'dbw-immo-suite'
                    ),
                    $last['date'],
                    $last['file'],
                    (int) $last['errors']
                ),
            );
        } else {
            // Newest processed feed (success or hash-skip both count as "feed arrived")
            $last_ok = null;
            foreach (array_reverse($history) as $entry) {
                if (in_array($entry['status'], array('success', 'skipped'), true)) {
                    $last_ok = $entry;
                    break;
                }
            }
            $hours = self::stale_hours();
            if ($hours > 0 && $last_ok && (current_time('timestamp') - strtotime($last_ok['date'])) > $hours * HOUR_IN_SECONDS) {
                $alert = array(
                    'type'    => 'stale',
                    'message' => sprintf(
                        __('Seit ueber %1$d Stunden wurde kein OpenImmo-Feed mehr verarbeitet (letzter Feed: %2$s). Moeglicherweise ist der FTP-Upload der Maklersoftware gestoert.', 'dbw-immo-suite'),
                        $hours,
                        $last_ok['date']
                    ),
                );
            }
        }

        if (!$alert) {
            delete_option(self::ALERT_OPTION);
            return;
        }

        $existing = get_option(self::ALERT_OPTION, array());
        $alert['raised']    = isset($existing['raised']) && isset($existing['type']) && $existing['type'] === $alert['type']
            ? $existing['raised']
            : current_time('mysql');
        $alert['last_mail'] = isset($existing['last_mail']) ? $existing['last_mail'] : 0;

        // Throttled email: at most one per 24h per ongoing alert.
        // A partial run only mails when the site explicitly asked for it.
        $may_mail = $alert['type'] !== 'partial' || self::mail_on_partial();
        if ($may_mail && time() - (int) $alert['last_mail'] > DAY_IN_SECONDS) {
            $sent = wp_mail(
                self::alert_email(),
                sprintf('[%s] %s', get_bloginfo('name'), __('Immobilien-Import benoetigt Aufmerksamkeit', 'dbw-immo-suite')),
                $alert['message'] . "\n\n"
                    . __('Import-Dashboard:', 'dbw-immo-suite') . ' '
                    . admin_url('edit.php?post_type=immobilie&page=dbw-immo-import')
            );
            if ($sent) {
                $alert['last_mail'] = time();
            }
        }

        update_option(self::ALERT_OPTION, $alert, false);
    }

    /**
     * Warning notice on Immo screens while the alert is active.
     */
    public function admin_notice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || strpos((string) $screen->id, 'immobilie') === false) {
            return;
        }
        $alert = get_option(self::ALERT_OPTION);
        if (empty($alert['message'])) {
            return;
        }
        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
            esc_html__('Immo Suite:', 'dbw-immo-suite'),
            esc_html($alert['message']),
            esc_url(admin_url('edit.php?post_type=immobilie&page=dbw-immo-import')),
            esc_html__('Zum Import-Dashboard', 'dbw-immo-suite')
        );
    }
}
