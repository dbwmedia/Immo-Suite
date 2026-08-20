<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Weekly summary mail for the broker.
 *
 * Every Monday morning: new listings, sold objects, inquiries, the most
 * viewed exposes of the week and the import health - the "your website is
 * working for you" mail. Can be disabled in the settings.
 */
class WeeklyReport
{
    const CRON_HOOK = 'dbw_immo_weekly_report';

    public function init()
    {
        add_action(self::CRON_HOOK, array($this, 'send'));

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $first = new \DateTime('next monday 07:00', wp_timezone());
            wp_schedule_event($first->getTimestamp(), 'weekly', self::CRON_HOOK);
        }
    }

    public static function is_enabled()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        return !isset($settings['weekly_report_enabled']) || (int) $settings['weekly_report_enabled'] === 1;
    }

    private static function recipient()
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        $mail = isset($settings['weekly_report_email']) ? $settings['weekly_report_email'] : '';
        return is_email($mail) ? $mail : get_option('admin_email');
    }

    /**
     * Collect the week's numbers.
     *
     * @return array
     */
    public static function collect()
    {
        $since_ts    = current_time('timestamp') - 7 * DAY_IN_SECONDS;
        $since_mysql = wp_date('Y-m-d H:i:s', $since_ts);

        // New listings (published within the last 7 days)
        $new_posts = get_posts(array(
            'post_type'      => 'immobilie',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'date_query'     => array(array('after' => '7 days ago')),
        ));

        // Sold within the last 7 days (sales date is set by the importer)
        $sold_posts = get_posts(array(
            'post_type'      => 'immobilie',
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_dbw_immo_sales_date',
                    'value'   => $since_mysql,
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ),
            ),
        ));

        // Inquiries of the last 7 days (only when the inbox stores them)
        $inquiries = 0;
        if (class_exists('DBW\ImmoSuite\PostTypes\Inquiry') && \DBW\ImmoSuite\PostTypes\Inquiry::is_enabled()) {
            $inquiries = count(get_posts(array(
                'post_type'      => \DBW\ImmoSuite\PostTypes\Inquiry::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 500,
                'fields'         => 'ids',
                'date_query'     => array(array('after' => '7 days ago')),
            )));
        }

        // Active inventory
        $counts = wp_count_posts('immobilie');
        $active = isset($counts->publish) ? (int) $counts->publish : 0;

        // Import health
        $history = get_option('dbw_immo_import_history', array());
        $last    = !empty($history) ? end($history) : null;

        return array(
            'since_ts'  => $since_ts,
            'new'       => array_map('intval', $new_posts),
            'sold'      => array_map('intval', $sold_posts),
            'inquiries' => $inquiries,
            'active'    => $active,
            'top'       => Stats::top_week(3),
            'last_run'  => $last,
        );
    }

    /**
     * Build and send the mail, then reset the weekly view counters.
     *
     * @param bool $is_test Test send: ignores the enabled setting and keeps
     *                      the weekly counters untouched.
     */
    public function send($is_test = false)
    {
        if (!$is_test && !self::is_enabled()) {
            return;
        }

        $data = self::collect();

        $site    = get_bloginfo('name');
        $subject = sprintf('[%s] %s', $site, __('Ihre Immobilien-Woche', 'dbw-immo-suite'));
        $body    = self::render_html($data);

        $sent = wp_mail(
            self::recipient(),
            $subject,
            $body,
            array('Content-Type: text/html; charset=UTF-8')
        );

        if ($sent && !$is_test) {
            Stats::reset_week();
        }
    }

    /**
     * Minimal, mail-client-safe HTML (inline styles only).
     */
    private static function render_html($data)
    {
        $range = sprintf(
            /* translators: 1: start date, 2: end date */
            __('%1$s bis %2$s', 'dbw-immo-suite'),
            wp_date('d.m.Y', $data['since_ts']),
            wp_date('d.m.Y', current_time('timestamp'))
        );

        $dashboard_url = admin_url('edit.php?post_type=immobilie&page=dbw-immo-import');
        $settings_url  = admin_url('edit.php?post_type=immobilie&page=dbw-immo-suite-settings#tab-report');

        $stat_cell = function ($value, $label) {
            return '<td align="center" style="padding:16px 8px; background:#f8f8fb; border-radius:8px;">'
                . '<div style="font-size:28px; font-weight:700; color:#1d2327; line-height:1.2;">' . esc_html($value) . '</div>'
                . '<div style="font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#666; margin-top:4px;">' . esc_html($label) . '</div>'
                . '</td>';
        };

        $rows = '';

        // New listings list
        if (!empty($data['new'])) {
            $items = '';
            foreach (array_slice($data['new'], 0, 5) as $pid) {
                $items .= '<li style="margin:4px 0;"><a href="' . esc_url(get_permalink($pid)) . '" style="color:#2271b1; text-decoration:none;">'
                    . esc_html(get_the_title($pid)) . '</a></li>';
            }
            $rows .= '<h3 style="margin:24px 0 8px; font-size:15px; color:#1d2327;">' . esc_html__('Neu online', 'dbw-immo-suite') . '</h3>'
                . '<ul style="margin:0; padding-left:18px; color:#333; font-size:14px;">' . $items . '</ul>';
        }

        // Sold list
        if (!empty($data['sold'])) {
            $items = '';
            foreach (array_slice($data['sold'], 0, 5) as $pid) {
                $items .= '<li style="margin:4px 0;">' . esc_html(get_the_title($pid)) . '</li>';
            }
            $rows .= '<h3 style="margin:24px 0 8px; font-size:15px; color:#1d2327;">' . esc_html__('Verkauft', 'dbw-immo-suite') . ' 🎉</h3>'
                . '<ul style="margin:0; padding-left:18px; color:#333; font-size:14px;">' . $items . '</ul>';
        }

        // Top viewed
        if (!empty($data['top'])) {
            $items = '';
            foreach ($data['top'] as $entry) {
                $items .= '<li style="margin:4px 0;"><a href="' . esc_url(get_permalink($entry['id'])) . '" style="color:#2271b1; text-decoration:none;">'
                    . esc_html(get_the_title($entry['id'])) . '</a>'
                    . ' <span style="color:#666;">(' . (int) $entry['views'] . ' ' . esc_html__('Aufrufe', 'dbw-immo-suite') . ')</span></li>';
            }
            $rows .= '<h3 style="margin:24px 0 8px; font-size:15px; color:#1d2327;">' . esc_html__('Meistgesehen diese Woche', 'dbw-immo-suite') . '</h3>'
                . '<ol style="margin:0; padding-left:18px; color:#333; font-size:14px;">' . $items . '</ol>';
        }

        if ($rows === '' && empty($data['inquiries'])) {
            $rows = '<p style="color:#333; font-size:14px; margin:20px 0 0;">'
                . esc_html__('Eine ruhige Woche: keine neuen Objekte, keine Verkaeufe. Der Import laeuft und die Website ist bereit.', 'dbw-immo-suite')
                . '</p>';
        }

        // Import health line
        $health = '';
        if (!empty($data['last_run'])) {
            $ok = in_array($data['last_run']['status'], array('success', 'skipped'), true) && empty($data['last_run']['errors']);
            $health = '<p style="margin:24px 0 0; padding:10px 14px; border-radius:6px; font-size:13px; '
                . ($ok ? 'background:#edfaef; color:#116329;' : 'background:#fcf0f1; color:#a4262c;') . '">'
                . ($ok
                    ? esc_html__('Import-System: alles in Ordnung.', 'dbw-immo-suite')
                    : esc_html__('Import-System: der letzte Lauf hatte Fehler - bitte Dashboard pruefen.', 'dbw-immo-suite'))
                . ' <span style="color:inherit; opacity:0.75;">(' . esc_html__('Letzter Feed:', 'dbw-immo-suite') . ' ' . esc_html($data['last_run']['date']) . ')</span>'
                . '</p>';
        }

        return '<!DOCTYPE html><html><body style="margin:0; padding:0; background:#f0f0f1;">'
            . '<div style="max-width:560px; margin:0 auto; padding:24px 16px; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
            . '<div style="background:#ffffff; border-radius:12px; padding:28px 28px 24px; box-shadow:0 1px 3px rgba(0,0,0,0.06);">'
            . '<div style="font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:#888;">' . esc_html(get_bloginfo('name')) . '</div>'
            . '<h1 style="margin:6px 0 2px; font-size:21px; color:#1d2327;">' . esc_html__('Ihre Immobilien-Woche', 'dbw-immo-suite') . '</h1>'
            . '<div style="font-size:13px; color:#666; margin-bottom:20px;">' . esc_html($range) . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="6" style="border-collapse:separate;"><tr>'
            . $stat_cell(count($data['new']), __('Neu', 'dbw-immo-suite'))
            . $stat_cell(count($data['sold']), __('Verkauft', 'dbw-immo-suite'))
            . $stat_cell($data['inquiries'], __('Anfragen', 'dbw-immo-suite'))
            . $stat_cell($data['active'], __('Aktiv', 'dbw-immo-suite'))
            . '</tr></table>'
            . $rows
            . $health
            . '<p style="margin:28px 0 0;"><a href="' . esc_url($dashboard_url) . '" style="display:inline-block; background:#1d2327; color:#ffffff; text-decoration:none; font-size:13px; font-weight:600; padding:10px 18px; border-radius:6px;">'
            . esc_html__('Zum Dashboard', 'dbw-immo-suite') . '</a></p>'
            . '</div>'
            . '<p style="text-align:center; font-size:11px; color:#999; margin-top:14px;">'
            . esc_html__('Automatischer Wochenbericht der Immo Suite.', 'dbw-immo-suite')
            . ' <a href="' . esc_url($settings_url) . '" style="color:#999;">' . esc_html__('Abbestellen in den Einstellungen', 'dbw-immo-suite') . '</a>'
            . '</p>'
            . '</div></body></html>';
    }
}
