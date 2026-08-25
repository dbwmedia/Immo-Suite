<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Import monitoring.
 *
 * Two classes of findings, deliberately kept apart:
 *
 * - Faults (the import is broken): a run that aborted, or a cron that stopped
 *   firing. These are real, provable and worth an email.
 * - Notes (something looks quiet): single objects that failed, or no new feed
 *   for a long time. A broker who changes nothing simply uploads nothing, so
 *   silence is not evidence of a fault. Dashboard only by default.
 *
 * The stale threshold used to mail after 48h and produced a false alarm after
 * every quiet weekend. Time is a weak signal; whether the importer itself still
 * runs is a strong one, which is what the cron check covers.
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
        $days = isset($settings['monitor_stale_days']) && $settings['monitor_stale_days'] !== ''
            ? (int) $settings['monitor_stale_days']
            : 14;
        return (int) apply_filters('dbw_immo_import_stale_hours', $days * 24);
    }

    /**
     * How long the importer may stay silent before the cron counts as dead.
     * Generous, because WP-Cron only fires on traffic.
     */
    private static function cron_grace_hours()
    {
        return (int) apply_filters('dbw_immo_cron_grace_hours', 6);
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
     * Alert types that actually prove a broken import.
     */
    private static function is_fault($type)
    {
        return in_array($type, array('errors', 'cron'), true);
    }

    /**
     * Whether notes are mailed too. Off by default: the point of this rework is
     * that a quiet feed must not fill anyone's inbox.
     */
    private static function mail_everything()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        return isset($settings['monitor_mail_level']) && $settings['monitor_mail_level'] === 'all';
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
        $alert = self::cron_alert();

        if ($alert) {
            // A dead importer outranks anything the history says
            $this->raise($alert);
            return;
        }

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
                        __('Seit %1$d Tagen ist kein neuer OpenImmo-Feed angekommen (letzter Feed: %2$s). Das ist normal, solange sich beim Anbieter nichts aendert - dauerhaft still kann aber auch ein abgerissener FTP-Upload sein.', 'dbw-immo-suite'),
                        (int) round($hours / 24),
                        $last_ok['date']
                    ),
                );
            }
        }

        $this->raise($alert);
    }

    /**
     * Is the importer itself still running? A run stamps dbw_immo_last_run even
     * when there was nothing to do, so silence here means the cron stopped,
     * not that the broker was idle.
     *
     * @return array|null
     */
    private static function cron_alert()
    {
        $next     = wp_next_scheduled('dbw_immo_cron_hook');
        $last_run = (int) get_option('dbw_immo_last_run', 0);
        $grace    = self::cron_grace_hours() * HOUR_IN_SECONDS;

        if (!$next) {
            return array(
                'type'    => 'cron',
                'message' => __('Der automatische Import ist nicht mehr eingeplant. Bis der Zeitplan wieder steht, werden keine neuen Objekte uebernommen.', 'dbw-immo-suite'),
            );
        }

        // No stamp yet: plugin updated between runs, judge after the next one
        if (!$last_run) {
            return null;
        }

        if (time() - $last_run > $grace) {
            return array(
                'type'    => 'cron',
                'message' => sprintf(
                    __('Der automatische Import laeuft seit ueber %1$d Stunden nicht mehr (letzter Lauf: %2$s). Der WordPress-Cron feuert offenbar nicht.', 'dbw-immo-suite'),
                    self::cron_grace_hours(),
                    date_i18n('d.m.Y H:i', $last_run)
                ),
            );
        }

        return null;
    }

    /**
     * Persist the alert and mail it when it qualifies.
     */
    private function raise($alert)
    {
        if (!$alert) {
            delete_option(self::ALERT_OPTION);
            return;
        }

        $existing = get_option(self::ALERT_OPTION, array());
        $alert['raised']    = isset($existing['raised']) && isset($existing['type']) && $existing['type'] === $alert['type']
            ? $existing['raised']
            : current_time('mysql');
        $alert['last_mail'] = isset($existing['last_mail']) ? $existing['last_mail'] : 0;

        // Throttled email: at most one per 24h per ongoing alert. Notes stay
        // silent unless the site asked for everything.
        $may_mail = self::is_fault($alert['type']) || self::mail_everything();
        if ($may_mail && time() - (int) $alert['last_mail'] > DAY_IN_SECONDS) {
            $subject = self::is_fault($alert['type'])
                ? __('Immobilien-Import gestoert', 'dbw-immo-suite')
                : __('Hinweis zum Immobilien-Import', 'dbw-immo-suite');
            $sent = wp_mail(
                self::alert_email(),
                sprintf('[%s] %s', get_bloginfo('name'), $subject),
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
        $class = self::is_fault(isset($alert['type']) ? $alert['type'] : '') ? 'notice-warning' : 'notice-info';
        printf(
            '<div class="notice %s"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
            esc_attr($class),
            esc_html__('Immo Suite:', 'dbw-immo-suite'),
            esc_html($alert['message']),
            esc_url(admin_url('edit.php?post_type=immobilie&page=dbw-immo-import')),
            esc_html__('Zum Import-Dashboard', 'dbw-immo-suite')
        );
    }
}
