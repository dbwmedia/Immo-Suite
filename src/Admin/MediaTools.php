<?php

namespace DBW\ImmoSuite\Admin;

use DBW\ImmoSuite\Core\Media;

if (!defined('ABSPATH')) { exit; }

/**
 * "Medien" screen: shows what the plugin stores in the media library and
 * offers batched cleanup runs for leftovers.
 */
class MediaTools
{
    const NONCE = 'dbw_immo_media_nonce';

    /** Attachments per AJAX batch. */
    const BATCH_ATTACHMENTS = 50;

    /** Properties per AJAX batch (each drags its images along). */
    const BATCH_PROPERTIES = 5;

    public function init()
    {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('wp_ajax_dbw_immo_media_cleanup', array($this, 'ajax_cleanup'));
    }

    public function add_menu_page()
    {
        add_submenu_page(
            'edit.php?post_type=immobilie',
            __('Medien-Bereinigung', 'dbw-immo-suite'),
            __('Medien', 'dbw-immo-suite'),
            'manage_options',
            'dbw-immo-media',
            array($this, 'render_page')
        );
    }

    /* ---------------------------------------------------------------------
     * Screen
     * ------------------------------------------------------------------ */

    public function render_page()
    {
        $stats = Media::get_stats();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Medien-Bereinigung', 'dbw-immo-suite'); ?></h1>
            <hr class="wp-header-end">

            <p class="description" style="max-width: 780px; margin-top: 12px;">
                <?php _e('Importierte Bilder gehoeren zur Immobilie, nicht zur Mediathek. Hier siehst du, wie viele davon aktuell gespeichert sind und was davon nur noch Ballast ist. <b>Alle Kacheln zaehlen Bilder</b>, die Zeile darunter nennt die zugehoerigen Objekte.', 'dbw-immo-suite'); ?>
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin: 20px 0; max-width: 900px;">
                <?php
                $prop_active = max(0, $stats['prop_total'] - $stats['prop_trashed'] - $stats['prop_archived']);

                $this->render_stat_card(
                    __('Bilder gesamt', 'dbw-immo-suite'),
                    $stats['total'],
                    '#1d2327',
                    sprintf(_n('%d Objekt', '%d Objekte', $stats['prop_total'], 'dbw-immo-suite'), $stats['prop_total'])
                );
                $this->render_stat_card(
                    __('Bei aktiven Objekten', 'dbw-immo-suite'),
                    $stats['active'],
                    '#16a34a',
                    sprintf(_n('%d Objekt', '%d Objekte', $prop_active, 'dbw-immo-suite'), $prop_active)
                );
                $this->render_stat_card(
                    __('Bei archivierten', 'dbw-immo-suite'),
                    $stats['archived'],
                    '#dba617',
                    sprintf(_n('%d Objekt', '%d Objekte', $stats['prop_archived'], 'dbw-immo-suite'), $stats['prop_archived'])
                );
                $this->render_stat_card(
                    __('Im Papierkorb', 'dbw-immo-suite'),
                    $stats['trashed'],
                    '#996800',
                    sprintf(_n('%d Objekt', '%d Objekte', $stats['prop_trashed'], 'dbw-immo-suite'), $stats['prop_trashed'])
                );
                $this->render_stat_card(
                    __('Verwaist', 'dbw-immo-suite'),
                    $stats['orphaned'],
                    '#d63638',
                    __('ohne Objekt', 'dbw-immo-suite')
                );
                $this->render_stat_card(
                    __('Speicher', 'dbw-immo-suite'),
                    size_format($stats['bytes'], 1),
                    '#3858e9',
                    __('inkl. Thumbnails', 'dbw-immo-suite')
                );
                ?>
            </div>

            <div class="card" style="max-width: 900px; padding: 20px;">
                <h2 class="title" style="margin-top: 0;"><?php _e('Aufraeumen', 'dbw-immo-suite'); ?></h2>

                <?php
                $this->render_task(
                    'orphans',
                    __('Verwaiste Bilder loeschen', 'dbw-immo-suite'),
                    __('Bilder, deren Immobilie nicht mehr existiert. Immer sicher zu loeschen.', 'dbw-immo-suite'),
                    sprintf(_n('%d Bild', '%d Bilder', $stats['orphaned'], 'dbw-immo-suite'), $stats['orphaned']),
                    $stats['orphaned']
                );

                $this->render_task(
                    'trashed',
                    __('Papierkorb endgueltig leeren', 'dbw-immo-suite'),
                    __('Loescht alle Immobilien im Papierkorb samt Bildern. Danach nicht wiederherstellbar.', 'dbw-immo-suite'),
                    sprintf(__('%1$d Objekte / %2$d Bilder', 'dbw-immo-suite'), $stats['prop_trashed'], $stats['trashed']),
                    $stats['prop_trashed']
                );

                $this->render_task(
                    'archived',
                    __('Archivierte Objekte loeschen', 'dbw-immo-suite'),
                    __('Objekte, die nicht mehr im OpenImmo-Feed stehen (Status "archiviert"), samt Bildern.', 'dbw-immo-suite'),
                    sprintf(__('%1$d Objekte / %2$d Bilder', 'dbw-immo-suite'), $stats['prop_archived'], $stats['archived']),
                    $stats['prop_archived']
                );
                ?>
            </div>

            <div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px; border-left: 4px solid #d63638;">
                <h2 class="title" style="margin-top: 0;"><?php _e('Kompletter Reset', 'dbw-immo-suite'); ?></h2>
                <p class="description">
                    <?php _e('Loescht ALLE Immobilien und ALLE dazugehoerigen Bilder. Gedacht fuer den Wechsel von Demo- auf Echtdaten vor dem Live-Gang. Der naechste Import baut den Bestand aus dem Feed neu auf.', 'dbw-immo-suite'); ?>
                </p>
                <p>
                    <label>
                        <input type="checkbox" id="dbw-media-reset-confirm">
                        <?php _e('Ja, ich will alle Immobilien und Bilder unwiderruflich loeschen.', 'dbw-immo-suite'); ?>
                    </label>
                </p>
                <p>
                    <button type="button" class="button button-secondary dbw-media-run" data-task="reset" disabled>
                        <?php _e('Alles loeschen', 'dbw-immo-suite'); ?>
                    </button>
                </p>
            </div>

            <div id="dbw-media-progress" style="display:none; max-width: 900px; margin-top: 20px; padding: 16px; background: #f0f0f1; border-radius: 6px;">
                <p style="margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span class="spinner is-active" style="float:none; margin:0;"></span>
                    <strong id="dbw-media-progress-text"><?php _e('Laeuft...', 'dbw-immo-suite'); ?></strong>
                </p>
            </div>

            <div id="dbw-media-result" style="display:none; max-width: 900px; margin-top: 20px;"></div>
        </div>

        <script>
        (function() {
            var nonce   = <?php echo wp_json_encode(wp_create_nonce(self::NONCE)); ?>;
            var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

            var confirmBox = document.getElementById('dbw-media-reset-confirm');
            var resetBtn   = document.querySelector('.dbw-media-run[data-task="reset"]');
            if (confirmBox && resetBtn) {
                confirmBox.addEventListener('change', function() {
                    resetBtn.disabled = !this.checked;
                });
            }

            var progress = document.getElementById('dbw-media-progress');
            var progressText = document.getElementById('dbw-media-progress-text');
            var result = document.getElementById('dbw-media-result');

            function run(task, total, done) {
                progress.style.display = '';
                result.style.display = 'none';

                var body = new URLSearchParams();
                body.append('action', 'dbw_immo_media_cleanup');
                body.append('nonce', nonce);
                body.append('task', task);
                if (task === 'reset') body.append('confirm', '1');

                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (!res.success) {
                        finish('<div class="notice notice-error"><p>' + (res.data || 'Fehler') + '</p></div>');
                        return;
                    }
                    done += res.data.processed;
                    progressText.textContent = done + ' erledigt, ' + res.data.remaining + ' verbleibend...';

                    if (res.data.remaining > 0 && res.data.processed > 0) {
                        run(task, total, done);
                    } else {
                        finish('<div class="notice notice-success"><p>' + res.data.message.replace('%d', done) + '</p></div>');
                    }
                })
                .catch(function(err) {
                    finish('<div class="notice notice-error"><p>' + err + '</p></div>');
                });
            }

            function finish(html) {
                progress.style.display = 'none';
                result.innerHTML = html + '<p><a href="" class="button">Seite neu laden</a></p>';
                result.style.display = '';
            }

            document.querySelectorAll('.dbw-media-run').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var task = this.dataset.task;
                    // Every irreversible bulk delete needs an explicit confirm
                    var confirmMsgs = {
                        reset: 'Wirklich alle Immobilien und Bilder loeschen?',
                        trashed: 'Papierkorb wirklich endgueltig leeren? Immobilien und Bilder werden unwiderruflich geloescht.',
                        archived: 'Archivierte Objekte wirklich endgueltig loeschen? Immobilien und Bilder werden unwiderruflich geloescht.'
                    };
                    if (confirmMsgs[task] && !window.confirm(confirmMsgs[task])) {
                        return;
                    }
                    document.querySelectorAll('.dbw-media-run').forEach(function(b) { b.disabled = true; });
                    run(task, 0, 0);
                });
            });
        })();
        </script>
        <?php
    }

    private function render_stat_card($label, $value, $color, $sub = '')
    {
        ?>
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 14px; text-align: center;">
            <div style="font-size: 22px; font-weight: 700; color: <?php echo esc_attr($color); ?>;">
                <?php echo esc_html($value); ?>
            </div>
            <div style="font-size: 11px; color: #50575e; text-transform: uppercase; margin-top: 4px;">
                <?php echo esc_html($label); ?>
            </div>
            <?php if ($sub !== '') : ?>
                <div style="font-size: 11px; color: #8c8f94; margin-top: 2px;">
                    <?php echo esc_html($sub); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_task($task, $title, $description, $button_label, $count)
    {
        ?>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 0; border-top: 1px solid #f0f0f1;">
            <div>
                <strong><?php echo esc_html($title); ?></strong>
                <p class="description" style="margin: 4px 0 0;"><?php echo esc_html($description); ?></p>
            </div>
            <div style="white-space: nowrap;">
                <button type="button" class="button dbw-media-run" data-task="<?php echo esc_attr($task); ?>" <?php disabled($count, 0); ?>>
                    <?php echo esc_html($button_label); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------------
     * AJAX
     * ------------------------------------------------------------------ */

    public function ajax_cleanup()
    {
        check_ajax_referer(self::NONCE, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'dbw-immo-suite'));
        }

        $task = isset($_POST['task']) ? sanitize_key($_POST['task']) : '';

        switch ($task) {
            case 'orphans':
                $processed = $this->delete_orphans();
                $message   = __('%d verwaiste Bilder geloescht.', 'dbw-immo-suite');
                break;

            case 'trashed':
                $processed = $this->delete_properties('trashed');
                $message   = __('%d Immobilien inkl. Bildern geloescht.', 'dbw-immo-suite');
                break;

            case 'archived':
                $processed = $this->delete_properties('archived');
                $message   = __('%d archivierte Immobilien inkl. Bildern geloescht.', 'dbw-immo-suite');
                break;

            case 'reset':
                if (empty($_POST['confirm'])) {
                    wp_send_json_error(__('Bestaetigung fehlt.', 'dbw-immo-suite'));
                }
                $processed = $this->delete_properties('all');
                if ($processed === 0) {
                    $processed = $this->delete_orphans();
                }
                $message = __('Reset abgeschlossen (%d Objekte verarbeitet).', 'dbw-immo-suite');
                break;

            default:
                wp_send_json_error(__('Unbekannte Aktion.', 'dbw-immo-suite'));
        }

        Media::flush_size_cache();
        $stats = Media::get_stats();

        $remaining = array(
            'orphans'  => $stats['orphaned'],
            'trashed'  => $stats['prop_trashed'],
            'archived' => $stats['prop_archived'],
            'reset'    => $stats['prop_total'] + $stats['orphaned'],
        );

        wp_send_json_success(array(
            'processed' => $processed,
            'remaining' => isset($remaining[$task]) ? $remaining[$task] : 0,
            'message'   => $message,
        ));
    }

    private function delete_orphans()
    {
        $ids = Media::find_orphans(self::BATCH_ATTACHMENTS);

        foreach ($ids as $att_id) {
            wp_delete_attachment($att_id, true);
        }

        return count($ids);
    }

    private function delete_properties($state)
    {
        $ids = Media::find_properties($state, self::BATCH_PROPERTIES);

        foreach ($ids as $post_id) {
            wp_delete_post($post_id, true); // before_delete_post removes the media
        }

        return count($ids);
    }
}
