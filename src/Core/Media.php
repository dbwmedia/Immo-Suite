<?php

namespace DBW\ImmoSuite\Core;

if (!defined('ABSPATH')) { exit; }

/**
 * Central media handling for imported property images.
 *
 * Three jobs:
 * 1. Keep imported images out of the WordPress media library UI (they are
 *    plugin data, not editorial assets).
 * 2. Store them in their own upload folder (uploads/immobilien/<post-id>/)
 *    so a property can be removed without leaving files behind.
 * 3. Delete them reliably - on hard delete, and via a daily maintenance run
 *    that clears orphans and (optionally) long-archived properties.
 */
class Media
{
    /** Marker on every attachment the plugin created. */
    const META_MARKER = '_dbw_immo_media';

    /** Legacy marker written by the importer since day one. */
    const META_FILENAME = '_openimmo_filename';

    /** Date a property was archived (garbage collection / ARCHIVE action). */
    const META_ARCHIVED = '_dbw_immo_archived_date';

    /** Upload sub directory inside wp-content/uploads. */
    const UPLOAD_DIR = 'immobilien';

    const CRON_HOOK = 'dbw_immo_media_cleanup';

    /** Cached storage total, recalculated after every cleanup. */
    const SIZE_TRANSIENT = 'dbw_immo_media_size';

    /** Property id whose uploads are currently being captured. */
    private static $capture_post_id = 0;

    public function init()
    {
        add_action('before_delete_post', array($this, 'delete_property_media'));

        add_action(self::CRON_HOOK, array($this, 'run_maintenance'));
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }

        if (is_admin()) {
            add_action('admin_init', array(__CLASS__, 'maybe_migrate_markers'));

            if (self::hide_from_library()) {
                add_action('pre_get_posts', array($this, 'filter_library_query'));
                add_filter('ajax_query_attachments_args', array($this, 'filter_ajax_query'));
                add_action('restrict_manage_posts', array($this, 'render_library_filter'));
            }
        }
    }

    /* ---------------------------------------------------------------------
     * Settings
     * ------------------------------------------------------------------ */

    private static function option($key, $default)
    {
        $settings = get_option('dbw_immo_suite_settings', array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    public static function hide_from_library()
    {
        return (bool) self::option('media_hide_library', 1);
    }

    public static function use_own_folder()
    {
        return (bool) self::option('media_own_folder', 1);
    }

    /** Months after which archived properties are deleted. 0 = never. */
    public static function archive_retention_months()
    {
        return max(0, min(120, (int) self::option('media_archive_retention_months', 0)));
    }

    /* ---------------------------------------------------------------------
     * Upload folder
     * ------------------------------------------------------------------ */

    /**
     * Route the next media_handle_sideload() into uploads/immobilien/<id>/.
     */
    public static function begin_upload_capture($post_id)
    {
        if (!self::use_own_folder() || !$post_id) {
            return;
        }
        self::$capture_post_id = (int) $post_id;
        add_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));
    }

    public static function end_upload_capture()
    {
        remove_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));
        self::$capture_post_id = 0;
    }

    public static function filter_upload_dir($dirs)
    {
        if (!self::$capture_post_id || !empty($dirs['error'])) {
            return $dirs;
        }

        $subdir = '/' . self::UPLOAD_DIR . '/' . self::$capture_post_id;

        $dirs['subdir'] = $subdir;
        $dirs['path']   = $dirs['basedir'] . $subdir;
        $dirs['url']    = $dirs['baseurl'] . $subdir;

        return $dirs;
    }

    /**
     * Flag an attachment as plugin owned. Called right after the sideload.
     */
    public static function mark_attachment($att_id)
    {
        update_post_meta((int) $att_id, self::META_MARKER, 1);
    }

    /* ---------------------------------------------------------------------
     * Library visibility
     * ------------------------------------------------------------------ */

    /**
     * List view (upload.php). Default hides property images, the dropdown
     * added below can switch to "only" or "all".
     */
    public function filter_library_query($query)
    {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'upload.php' || !$query->is_main_query()) {
            return;
        }

        $mode = isset($_GET['dbw_immo_media']) ? sanitize_key($_GET['dbw_immo_media']) : 'hide';

        if ($mode === 'all') {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = self::meta_clause($mode === 'only');
        $query->set('meta_query', $meta_query);
    }

    /**
     * Grid view and the block editor media modal.
     */
    public function filter_ajax_query($args)
    {
        // Keep them visible while editing the property they belong to,
        // otherwise the featured image could not be picked manually.
        $context_id = isset($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0;
        if ($context_id && get_post_type($context_id) === 'immobilie') {
            return $args;
        }

        $meta_query = isset($args['meta_query']) ? (array) $args['meta_query'] : array();
        $meta_query[] = self::meta_clause(false);
        $args['meta_query'] = $meta_query;

        return $args;
    }

    /**
     * @param bool $only True: only property images. False: everything else.
     */
    private static function meta_clause($only)
    {
        return array(
            'key'     => self::META_MARKER,
            'compare' => $only ? 'EXISTS' : 'NOT EXISTS',
        );
    }

    public function render_library_filter($post_type)
    {
        if ($post_type !== 'attachment') {
            return;
        }

        $current = isset($_GET['dbw_immo_media']) ? sanitize_key($_GET['dbw_immo_media']) : 'hide';
        $options = array(
            'hide' => __('Immobilien-Bilder ausblenden', 'dbw-immo-suite'),
            'only' => __('Nur Immobilien-Bilder', 'dbw-immo-suite'),
            'all'  => __('Alle Medien anzeigen', 'dbw-immo-suite'),
        );
        ?>
        <select name="dbw_immo_media">
            <?php foreach ($options as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * One-off: give attachments imported before v2.6.0 the new marker so a
     * single meta key is enough for every query.
     */
    public static function maybe_migrate_markers()
    {
        if (get_option('dbw_immo_media_migrated') === DBW_IMMO_SUITE_VERSION) {
            return;
        }

        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
             SELECT DISTINCT pm.post_id, %s, '1'
             FROM {$wpdb->postmeta} pm
             WHERE pm.meta_key = %s
               AND NOT EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta} pm2
                   WHERE pm2.post_id = pm.post_id AND pm2.meta_key = %s
               )",
            self::META_MARKER,
            self::META_FILENAME,
            self::META_MARKER
        ));

        update_option('dbw_immo_media_migrated', DBW_IMMO_SUITE_VERSION, false);
    }

    /* ---------------------------------------------------------------------
     * Deletion
     * ------------------------------------------------------------------ */

    /**
     * Hard delete of a property removes its images and its upload folder.
     */
    public function delete_property_media($post_id)
    {
        if (get_post_type($post_id) !== 'immobilie') {
            return;
        }

        $attachments = get_posts(array(
            'post_type'      => 'attachment',
            'post_parent'    => $post_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ));

        foreach ($attachments as $att_id) {
            wp_delete_attachment($att_id, true);
        }

        self::remove_upload_folder($post_id);
        self::flush_size_cache();
    }

    /**
     * Remove uploads/immobilien/<post-id>/ once it is empty.
     */
    public static function remove_upload_folder($post_id)
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return;
        }

        $dir = trailingslashit($uploads['basedir']) . self::UPLOAD_DIR . '/' . (int) $post_id;

        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: array() as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($dir);
    }

    /* ---------------------------------------------------------------------
     * Reporting + cleanup helpers (used by the admin tool and the cron)
     * ------------------------------------------------------------------ */

    /**
     * Count property attachments per category.
     *
     * @return array{total:int,active:int,archived:int,trashed:int,orphaned:int,bytes:int,prop_total:int,prop_archived:int,prop_trashed:int}
     */
    public static function get_stats()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT a.ID, parent.post_type AS parent_type, parent.post_status AS parent_status
             FROM {$wpdb->posts} a
             LEFT JOIN {$wpdb->posts} parent ON parent.ID = a.post_parent
             WHERE a.post_type = 'attachment'
               AND EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta} m
                   WHERE m.post_id = a.ID AND m.meta_key = '" . self::META_MARKER . "'
               )"
        );

        $stats = array(
            'total'    => 0,
            'active'   => 0,
            'archived' => 0,
            'trashed'  => 0,
            'orphaned' => 0,
            'bytes'    => 0,
        );

        foreach ($rows as $row) {
            $stats['total']++;

            if ($row->parent_type !== 'immobilie') {
                $stats['orphaned']++;
            } elseif ($row->parent_status === 'trash') {
                $stats['trashed']++;
            } elseif ($row->parent_status === 'draft') {
                $stats['archived']++;
            } else {
                $stats['active']++;
            }
        }

        $stats['bytes'] = self::storage_bytes();

        $counts = $wpdb->get_results(
            "SELECT post_status, COUNT(*) AS num FROM {$wpdb->posts}
             WHERE post_type = 'immobilie' GROUP BY post_status"
        );

        $stats['prop_total']    = 0;
        $stats['prop_archived'] = 0;
        $stats['prop_trashed']  = 0;

        foreach ($counts as $count) {
            $stats['prop_total'] += (int) $count->num;

            if ($count->post_status === 'trash') {
                $stats['prop_trashed'] = (int) $count->num;
            }
        }

        $stats['prop_archived'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             WHERE p.post_type = 'immobilie' AND p.post_status = 'draft'
               AND " . self::archived_sql()
        );

        return $stats;
    }

    /**
     * Attachments whose parent property no longer exists (or never was one).
     *
     * @return int[]
     */
    public static function find_orphans($limit = 100)
    {
        global $wpdb;

        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT a.ID
             FROM {$wpdb->posts} a
             LEFT JOIN {$wpdb->posts} parent ON parent.ID = a.post_parent
             WHERE a.post_type = 'attachment'
               AND (parent.ID IS NULL OR parent.post_type <> 'immobilie')
               AND EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta} m
                   WHERE m.post_id = a.ID AND m.meta_key = '" . self::META_MARKER . "'
               )
             LIMIT %d",
            (int) $limit
        )));
    }

    /**
     * Property ids by lifecycle state.
     *
     * @param string $state 'trashed'|'archived'|'all'
     * @return int[]
     */
    public static function find_properties($state, $limit = 20)
    {
        global $wpdb;

        if ($state === 'trashed') {
            return array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'immobilie' AND post_status = 'trash'
                 LIMIT %d",
                (int) $limit
            )));
        }

        if ($state === 'archived') {
            // Only objects the plugin archived, never a draft someone parked by hand.
            return array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 WHERE p.post_type = 'immobilie' AND p.post_status = 'draft'
                   AND " . self::archived_sql() . "
                 LIMIT %d",
                (int) $limit
            )));
        }

        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'immobilie' LIMIT %d",
            (int) $limit
        )));
    }

    /**
     * SQL condition for "the plugin archived this object".
     *
     * Covers both markers: the status meta written since day one and the
     * archive date added in v2.6.0 (the ARCHIVE action only writes the date).
     */
    private static function archived_sql()
    {
        global $wpdb;

        return "EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta} am
                   WHERE am.post_id = p.ID
                     AND (
                         (am.meta_key = '_dbw_immo_status' AND am.meta_value = 'archiviert')
                         OR am.meta_key = '" . self::META_ARCHIVED . "'
                     )
               )";
    }

    /**
     * Disk usage of all property images, thumbnails included.
     *
     * Counts the actual files, not just the plugin's own folder - images
     * imported before v2.6.0 still live in the year/month structure.
     * Cached, because it stats a few thousand files.
     */
    public static function storage_bytes()
    {
        $cached = get_transient(self::SIZE_TRANSIENT);
        if ($cached !== false) {
            return (int) $cached;
        }

        global $wpdb;

        $ids = array_map('intval', $wpdb->get_col(
            "SELECT a.ID FROM {$wpdb->posts} a
             WHERE a.post_type = 'attachment'
               AND EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta} m
                   WHERE m.post_id = a.ID AND m.meta_key = '" . self::META_MARKER . "'
               )"
        ));

        if (empty($ids)) {
            set_transient(self::SIZE_TRANSIENT, 0, 12 * HOUR_IN_SECONDS);
            return 0;
        }

        // One query for all attachment metadata instead of one per image
        update_meta_cache('post', $ids);

        $uploads = wp_upload_dir();
        $basedir = !empty($uploads['error']) ? '' : trailingslashit($uploads['basedir']);
        $bytes = 0;

        foreach ($ids as $id) {
            $file = get_attached_file($id);
            if ($file && is_file($file)) {
                $bytes += (int) filesize($file);
            }

            $meta = wp_get_attachment_metadata($id);
            if (empty($meta['sizes']) || empty($meta['file']) || $basedir === '') {
                continue;
            }

            $size_dir = trailingslashit($basedir . dirname($meta['file']));

            foreach ($meta['sizes'] as $size) {
                if (empty($size['file'])) {
                    continue;
                }
                if (isset($size['filesize'])) {
                    $bytes += (int) $size['filesize'];
                } elseif (is_file($size_dir . $size['file'])) {
                    $bytes += (int) filesize($size_dir . $size['file']);
                }
            }
        }

        set_transient(self::SIZE_TRANSIENT, $bytes, 12 * HOUR_IN_SECONDS);

        return $bytes;
    }

    /**
     * Drop the cached size after anything was deleted.
     */
    public static function flush_size_cache()
    {
        delete_transient(self::SIZE_TRANSIENT);
    }

    /* ---------------------------------------------------------------------
     * Maintenance cron
     * ------------------------------------------------------------------ */

    /**
     * Daily: drop orphaned images, then apply the archive retention.
     */
    public function run_maintenance()
    {
        foreach (self::find_orphans(200) as $att_id) {
            wp_delete_attachment($att_id, true);
        }

        $this->apply_archive_retention();
    }

    /**
     * Delete properties that have been archived longer than the configured
     * retention. Their images and upload folder go with them.
     */
    private function apply_archive_retention()
    {
        $months = self::archive_retention_months();
        if ($months < 1) {
            return;
        }

        $candidates = get_posts(array(
            'post_type'      => 'immobilie',
            'post_status'    => 'draft',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'OR',
                array('key' => '_dbw_immo_status', 'value' => 'archiviert'),
                array('key' => self::META_ARCHIVED, 'compare' => 'EXISTS'),
            ),
        ));

        $cutoff = strtotime('-' . $months . ' months');

        foreach ($candidates as $post_id) {
            $archived = get_post_meta($post_id, self::META_ARCHIVED, true);

            // Archived before v2.6.0: start the clock now instead of deleting
            // something whose age we cannot prove.
            if (empty($archived)) {
                update_post_meta($post_id, self::META_ARCHIVED, current_time('mysql'));
                continue;
            }

            if (strtotime($archived) < $cutoff) {
                wp_delete_post($post_id, true);
            }
        }
    }
}
