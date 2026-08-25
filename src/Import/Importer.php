<?php

namespace DBW\ImmoSuite\Import;

if (!defined('ABSPATH')) { exit; }

/**
 * Handles OpenImmo XML Import
 */
class Importer
{

    /**
     * Current processing XML file path.
     * @var string
     */
    private $current_xml_file;

    /**
     * Safely extract a ZIP archive (Zip-Slip protection).
     */
    private function safe_extract_zip(\ZipArchive $zip, $target_dir)
    {
        $target_real = realpath($target_dir);
        if (!$target_real) return false;

        // Zip bomb guard: a hostile archive in the FTP dir must not be able
        // to fill the customer's hosting quota. Real feeds stay far below.
        $max_files = 2000;
        $max_bytes = 500 * MB_IN_BYTES;

        if ($zip->numFiles > $max_files) {
            $this->log_debug(sprintf('ZIP blockiert: %d Dateien (Limit %d).', $zip->numFiles, $max_files));
            return false;
        }

        $total_size = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Block traversal, absolute paths (Unix + Windows drive/UNC) and unreadable entries
            if ($name === false || $name === '' || strpos($name, '..') !== false || preg_match('#^([a-zA-Z]:)?[/\\\\]#', $name)) {
                $this->log_debug('Zip-Slip blocked: ' . $name);
                return false;
            }

            $stat = $zip->statIndex($i);
            $total_size += $stat ? (int) $stat['size'] : 0;
        }

        if ($total_size > $max_bytes) {
            $this->log_debug(sprintf('ZIP blockiert: %s entpackt (Limit %s).', size_format($total_size), size_format($max_bytes)));
            return false;
        }

        return $zip->extractTo($target_dir);
    }

    /**
     * ZIP hashes live in ONE non-autoloaded option. Timestamped ZIP names from
     * broker software used to create one autoloaded option per file — an
     * unbounded autoload blob that slowed every request over time.
     */
    private function get_xml_hashes()
    {
        $hashes = get_option('dbw_immo_xml_hashes', null);
        if (!is_array($hashes)) {
            // One-time migration: collect and remove legacy per-file options
            global $wpdb;
            $legacy = $wpdb->get_results(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'dbw\_immo\_last\_xml\_hash\_%'"
            );
            $hashes = array();
            foreach ((array) $legacy as $row) {
                $hashes[substr($row->option_name, strlen('dbw_immo_last_xml_hash_'))] = $row->option_value;
            }
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dbw\_immo\_last\_xml\_hash\_%'");
            add_option('dbw_immo_xml_hashes', $hashes, '', false);
        }
        return $hashes;
    }

    private function get_last_xml_hash($zip_basename)
    {
        $hashes = $this->get_xml_hashes();
        return isset($hashes[$zip_basename]) ? $hashes[$zip_basename] : '';
    }

    private function set_last_xml_hash($zip_basename, $hash)
    {
        $hashes = $this->get_xml_hashes();
        unset($hashes[$zip_basename]);
        $hashes[$zip_basename] = $hash;
        if (count($hashes) > 20) {
            $hashes = array_slice($hashes, -20, null, true);
        }
        update_option('dbw_immo_xml_hashes', $hashes, false);
    }

    /**
     * IDs of properties processed in the current run (for GC).
     * @var array
     */
    private $processed_openimmo_ids = array();

    /**
     * True once a processed XML declared <uebertragung umfang="VOLL">.
     * Garbage collection must only run after a full delivery - partial
     * deliveries (onOffice pushes per-object ZIPs) would otherwise archive
     * the entire remaining inventory.
     * @var bool
     */
    private $full_sync_seen = false;

    const LOCK_TRANSIENT = 'dbw_immo_import_lock';

    /** Marks that the current dashboard batch run contained a VOLL delivery. */
    const BATCH_FULL_SYNC_TRANSIENT = 'dbw_immo_batch_full_sync';

    /**
     * One lock for BOTH import paths (cron run_import + dashboard batch flow),
     * so an hourly cron tick cannot process the same ZIPs a batch run is
     * still working on.
     */
    private function acquire_lock($ttl)
    {
        if (get_transient(self::LOCK_TRANSIENT)) {
            return false;
        }
        set_transient(self::LOCK_TRANSIENT, time(), $ttl);
        return true;
    }

    private function refresh_lock($ttl)
    {
        set_transient(self::LOCK_TRANSIENT, time(), $ttl);
    }

    private function release_lock()
    {
        delete_transient(self::LOCK_TRANSIENT);
    }

    /**
     * Containment check for realpath'ed paths. A plain strpos prefix match
     * would accept "/pfad/openimmo-evil" for base "/pfad/openimmo".
     */
    private function path_within($path, $base)
    {
        $base = rtrim($base, '/\\');
        return $path === $base || strpos($path, $base . DIRECTORY_SEPARATOR) === 0;
    }

    /**
     * Run the import process.
     *
     * @return array Result stats.
     */
    public function run_import()
    {
        // Acquire lock (TTL matches the 600s time limit below)
        if (!$this->acquire_lock(600)) {
            return array('success' => false, 'message' => 'Import läuft bereits. Bitte warten.');
        }

        $this->log_debug('--- Starte Import ---');

        try {
            // Increase limits
            if (function_exists('set_time_limit')) { set_time_limit(600); }
            ini_set('max_execution_time', '600');
            wp_raise_memory_limit('admin');

            $options = get_option('dbw_immo_suite_settings');
            $xml_path = $this->resolve_import_path($options);

            if (!$xml_path || !is_dir($xml_path)) {
                throw new \Exception(__('Kein gueltiges Import-Verzeichnis konfiguriert.', 'dbw-immo-suite'));
            }

            $this->log_debug('Import-Pfad aufgeloest: ' . $xml_path);

            // Housekeeping: abandoned tmp_ dirs + old .processed/.skipped files
            $this->cleanup_import_dir($xml_path);

            $stats = array(
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
            );

            $xmls_processed = 0;

            // Time budget below the 600s limit: leftover files are picked up
            // by the next cron tick (image dedupe makes retries cheap), instead
            // of the whole request dying in the middle of a ZIP.
            $run_started = time();
            $time_budget = 480;

            // Verify glob() works on this path (catches open_basedir / permission issues)
            $zips = glob($xml_path . '*.zip');
            if ($zips === false) {
                $this->log_debug('glob() fehlgeschlagen für: ' . $xml_path . '*.zip — Berechtigungsproblem oder open_basedir Restriction.');
                $this->log_debug('open_basedir: ' . (ini_get('open_basedir') ?: '(nicht gesetzt)'));
                $this->log_debug('is_readable: ' . (is_readable($xml_path) ? 'ja' : 'nein'));

                // Fallback: try uploads dir
                $upload_dir = wp_upload_dir();
                $fallback = trailingslashit($upload_dir['basedir']) . 'openimmo/';
                if ($fallback !== $xml_path && is_dir($fallback)) {
                    $fallback_test = glob($fallback . '*.zip');
                    if ($fallback_test !== false) {
                        $this->log_debug('Fallback auf Uploads-Verzeichnis: ' . $fallback);
                        $xml_path = $fallback;
                        $zips = $fallback_test;
                    }
                }

                if ($zips === false) {
                    throw new \Exception(sprintf(
                        'Verzeichnis "%s" nicht lesbar (glob fehlgeschlagen). Ursache: Dateiberechtigungen oder PHP open_basedir (%s). Bitte den Uploads-Pfad verwenden.',
                        $xml_path,
                        ini_get('open_basedir') ?: 'nicht gesetzt'
                    ));
                }
            }

            // ZIP Processing
            if (!empty($zips)) {
                foreach ($zips as $zip_file) {
                    if (time() - $run_started > $time_budget) {
                        $this->log_debug('Zeitbudget erreicht, restliche ZIPs werden im naechsten Lauf verarbeitet.');
                        $this->full_sync_seen = false; // incomplete run must not trigger GC
                        break;
                    }

                    $temp_dir = $xml_path . 'tmp_' . uniqid() . '/';

                    $zip = new \ZipArchive;
                    if ($zip->open($zip_file) === TRUE) {
                        mkdir($temp_dir, 0755, true);
                        if (!$this->safe_extract_zip($zip, $temp_dir)) { $zip->close(); continue; }
                        $zip->close();

                        // Find XMLs inside temp_dir
                        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temp_dir));
                        $temp_xmls = array();
                        foreach ($files as $file) {
                            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                                $temp_xmls[] = $file->getPathname();
                            }
                        }

                        if (!empty($temp_xmls)) {
                            // Idempotency: Hashing XMLs
                            $hash = '';
                            foreach ($temp_xmls as $xf) {
                                $hash .= md5_file($xf);
                            }
                            $hash = md5($hash);

                            $last_hash = $this->get_last_xml_hash(basename($zip_file));

                            if ($hash === $last_hash && !empty($hash)) {
                                $this->log_debug('ZIP übersprungen (XML Hash identisch): ' . basename($zip_file));
                                $this->delete_directory($temp_dir);
                                rename($zip_file, $zip_file . '.skipped');

                                // NEW: Log skipped explicitly in history array
                                $this->log_history($zip_file, array('created' => 0, 'updated' => 0, 'errors' => 0), 'skipped');

                                continue;
                            }

                            // Process XMLs (history logs the per-file delta,
                            // not the accumulated run totals)
                            foreach ($temp_xmls as $file) {
                                $before = $stats;
                                $this->process_file($file, $stats);
                                $this->log_history($file, array(
                                    'created' => $stats['created'] - $before['created'],
                                    'updated' => $stats['updated'] - $before['updated'],
                                    'errors'  => $stats['errors'] - $before['errors'],
                                ), 'success');
                                $xmls_processed++;
                            }

                            $this->set_last_xml_hash(basename($zip_file), $hash);
                        }
                        else {
                            $this->log_debug('Keine XML in ZIP gefunden: ' . basename($zip_file));
                        }

                        // Cleanup temp dir and rename ZIP
                        $this->delete_directory($temp_dir);
                        rename($zip_file, $zip_file . '.processed');
                        $this->log_debug('ZIP erfolgreich verarbeitet: ' . basename($zip_file));
                    }
                    else {
                        // Typically a half-uploaded FTP file; retried next run
                        $this->log_debug('ZIP konnte nicht geoeffnet werden (evtl. unvollstaendig hochgeladen): ' . basename($zip_file));
                    }
                }
            }

            // Loose XML Processing (Fallback)
            $loose_files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($xml_path));
            $xml_files = array();
            foreach ($loose_files as $file) {
                // Ignore contents of tmp_ directories and .processed files
                if ($file->isFile() && strtolower($file->getExtension()) === 'xml' && strpos($file->getPathname(), '/tmp_') === false) {
                    $xml_files[] = $file->getPathname();
                }
            }

            if (!empty($xml_files)) {
                foreach ($xml_files as $file) {
                    if (time() - $run_started > $time_budget) {
                        $this->log_debug('Zeitbudget erreicht, restliche XML-Dateien werden im naechsten Lauf verarbeitet.');
                        $this->full_sync_seen = false;
                        break;
                    }

                    $before = $stats;
                    $this->process_file($file, $stats);
                    $this->log_history($file, array(
                        'created' => $stats['created'] - $before['created'],
                        'updated' => $stats['updated'] - $before['updated'],
                        'errors'  => $stats['errors'] - $before['errors'],
                    ), 'success');
                    $xmls_processed++;

                    // Rename XML file to prevent hour-by-hour reimports
                    rename($file, $file . '.processed');
                    $this->log_debug('Lose XML-Datei archiviert: ' . basename($file));
                }
            }
            else if (empty($zips)) {
                // Nur werfen, wenn gar keine Dateien da waren
                // Optional message
                $this->log_debug('Keine neuen Import-Dateien gefunden.');
            }

            // Garbage Collection - only after a delivery that declared itself
            // a full sync (<uebertragung umfang="VOLL">). A partial delivery
            // must never archive the rest of the inventory.
            if ($xmls_processed > 0 && $this->full_sync_seen && !empty($options['enable_garbage_collection'])) {
                $this->run_garbage_collection();
            } elseif ($xmls_processed > 0 && !$this->full_sync_seen && !empty($options['enable_garbage_collection'])) {
                $this->log_debug('Garbage Collection uebersprungen: Lieferung war kein Vollabgleich (umfang != VOLL).');
            }

            // Release Lock
            $this->release_lock();

            // Proof that the importer actually ran. A run without new files
            // writes no history entry, so this is the only way to tell a dead
            // cron apart from a broker who simply changed nothing.
            update_option('dbw_immo_last_run', time(), false);

            return array(
                'success' => true,
                'message' => sprintf('Import fertig. Erstellt: %d, Aktualisiert: %d, Fehler: %d', $stats['created'], $stats['updated'], $stats['errors']),
            );

        }
        catch (\Throwable $e) {
            // Error Handling (\Throwable: a TypeError must release the lock too)
            $this->release_lock();
            $this->log_debug('Error: ' . $e->getMessage());

            // Log failed run
            $this->log_history('System', array('created' => 0, 'updated' => 0, 'errors' => 1), 'error');
            update_option('dbw_immo_last_run', time(), false);

            return array('success' => false, 'message' => $e->getMessage());
        }
    }

    private function log_history($file, $stats, $status)
    {
        $history = get_option('dbw_immo_import_history', array());

        $entry = array(
            'date' => current_time('mysql'),
            'file' => basename($file),
            'status' => $status,
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'errors' => $stats['errors']
        );

        $history[] = $entry;

        // Keep last 50
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }

        update_option('dbw_immo_import_history', $history, false);
    }

    /**
     * Process a single XML file.
     *
     * @param string $file Path to XML file.
     * @param array  $stats Statistics array (reference).
     */
    private function process_file($file, &$stats)
    {
        $xml = $this->safe_load_xml($file);

        if (!$xml) {
            $stats['errors']++;
            return;
        }

        $this->current_xml_file = $file;

        // Track the delivery scope declared by the feed (VOLL vs. TEIL)
        if ($this->is_full_sync($xml)) {
            $this->full_sync_seen = true;
        }

        // Support wrapping 'openimmo_feedback' or just 'anbieter' list
        if (isset($xml->anbieter)) {
            foreach ($xml->anbieter as $anbieter) {
                if (isset($anbieter->immobilie)) {
                    foreach ($anbieter->immobilie as $immobilie) {
                        $this->import_property($immobilie, $stats);
                    }
                }
            }
        }
    }

    /**
     * Import a single property node.
     *
     * @param SimpleXMLElement $immobilie Property XML node.
     * @param array            $stats Statistics array.
     */
    private function import_property($immobilie, &$stats)
    {
        $verwaltung_techn = $immobilie->verwaltung_techn;
        $openimmo_id = (string)$verwaltung_techn->openimmo_obid; // Standard path

        if (empty($openimmo_id) && isset($immobilie->obid)) {
            $openimmo_id = (string)$immobilie->obid;
        }

        if (empty($openimmo_id)) {
            $stats['errors']++;
            return;
        }

        // Check Action Type (CHANGE, DELETE, REFERENZ)
        // OpenImmo XSD: <aktion aktionart="CHANGE|DELETE|REFERENZ"/>. Some
        // non-standard feeds use "actiontype" or the node value instead.
        $action_type = '';
        if (isset($verwaltung_techn->aktion)) {
            $action_type = (string)$verwaltung_techn->aktion['aktionart'];
            if (empty($action_type)) {
                $action_type = (string)$verwaltung_techn->aktion['actiontype'];
            }
            if (empty($action_type)) {
                $action_type = (string)$verwaltung_techn->aktion;
            }
            $action_type = strtoupper($action_type);
        }

        $existing_id = $this->get_property_by_openimmo_id($openimmo_id);

        $this->processed_openimmo_ids[] = $openimmo_id; // Add to tracked IDs for GC

        // If in batch mode, track in transient
        $batch_ids = get_transient('dbw_immo_batch_processed_ids');
        if (is_array($batch_ids)) {
            if (!in_array($openimmo_id, $batch_ids)) {
                $batch_ids[] = $openimmo_id;
                set_transient('dbw_immo_batch_processed_ids', $batch_ids, 3600);
            }
        }

        // HANDLE DELETION
        if ($action_type === 'DELETE' || $action_type === 'LÖSCHEN' || $action_type === 'LOESCHEN') {
            if ($existing_id) {
                // Move to trash
                wp_trash_post($existing_id);
                $this->log_debug("Immobilie $openimmo_id ($existing_id) wurde in den Papierkorb verschoben (Action: $action_type).");
                $stats['updated']++; // Count as handled
            }
            return; // Stop processing this property
        }

        // HANDLE ARCHIVING
        if ($action_type === 'ARCHIVE' || $action_type === 'ARCHIVIEREN') {
            if ($existing_id) {
                $update_data = array(
                    'ID' => $existing_id,
                    'post_status' => 'draft' // Set to draft
                );
                wp_update_post($update_data);
                update_post_meta($existing_id, \DBW\ImmoSuite\Core\Media::META_ARCHIVED, current_time('mysql'));
                $this->log_debug("Immobilie $openimmo_id ($existing_id) wurde archiviert/Entwurf (Action: $action_type).");
                $stats['updated']++;
            }
            return; // Stop processing
        }


        // Title Generation
        $geo = $immobilie->geo;
        $freitexte = $immobilie->freitexte;
        // onOffice ships an empty <objekttitel/> node, so isset() alone left the
        // post title blank ("(kein Titel)" in the admin list).
        $titel = isset($freitexte->objekttitel) ? trim((string)$freitexte->objekttitel) : '';
        if ($titel === '') {
            $titel = 'Immobilie ' . $openimmo_id;
        }

        $post_data = array(
            'post_title' => $titel,
            'post_content' => isset($freitexte->objektbeschreibung) ? (string)$freitexte->objektbeschreibung : '',
            'post_status' => 'publish',
            'post_type' => 'immobilie',
        );

        if ($existing_id) {
            $post_data['ID'] = $existing_id;
            wp_update_post($post_data);
            $post_id = $existing_id;
            // Back in the feed: the archive retention clock stops
            delete_post_meta($post_id, \DBW\ImmoSuite\Core\Media::META_ARCHIVED);
            $stats['updated']++;
        }
        else {
            $post_id = wp_insert_post($post_data);
            $stats['created']++;
            update_post_meta($post_id, 'openimmo_id', $openimmo_id);
        }

        // Map Fields
        $this->map_fields($post_id, $immobilie);

        // OpenImmo action REFERENZ marks the object as a reference. Set after
        // map_fields so it wins over the verkaufstatus mapping.
        if ($action_type === 'REFERENZ' && !get_post_meta($post_id, '_dbw_immo_manual_status_override', true)) {
            update_post_meta($post_id, '_dbw_immo_status', 'referenz');
        }

        // Process Attachments
        // We need the base path of the XML file to find images
        if (isset($this->current_xml_file)) {
            $base_path = dirname($this->current_xml_file);
            $this->process_attachments($post_id, $immobilie, $base_path);
            $this->process_contact_image($post_id, $immobilie, $base_path);
        }
    }

    /**
     * Map XML fields to Post Meta.
     * 
     * @param int $post_id
     * @param SimpleXMLElement $xml
     */
    private function map_fields($post_id, $xml)
    {
        // Basic Pricing
        if (isset($xml->preise)) {
            $kaufpreis = (string)$xml->preise->kaufpreis;
            $kaltmiete = (string)$xml->preise->kaltmiete;
            $warmmiete = (string)$xml->preise->warmmiete;
            $hausgeld = (string)$xml->preise->hausgeld;
            $nebenkosten = (string)$xml->preise->nebenkosten;
            $provision_kaeufer = (string)$xml->preise->aussen_courtage;

            update_post_meta($post_id, 'kaufpreis', $kaufpreis);
            update_post_meta($post_id, 'kaltmiete', $kaltmiete);
            update_post_meta($post_id, 'warmmiete', $warmmiete);
            update_post_meta($post_id, 'hausgeld', $hausgeld);
            update_post_meta($post_id, 'nebenkosten', $nebenkosten);
            update_post_meta($post_id, 'provision_kaeufer', $provision_kaeufer);

            // Set Marketing Type Taxonomy.
            // Primary source: <objektkategorie><vermarktungsart KAUF=".." MIETE_PACHT=".."
            // ERBPACHT=".." LEASING=".."/> (required by the OpenImmo XSD). The price
            // heuristic stays as fallback only - "Preis auf Anfrage" objects have no
            // price > 0 and would otherwise end up in no marketing filter at all.
            $marketing_terms = array();

            if (isset($xml->objektkategorie->vermarktungsart)) {
                $va = $xml->objektkategorie->vermarktungsart->attributes();
                $va_map = array(
                    'KAUF'        => 'Kauf',
                    'MIETE_PACHT' => 'Miete',
                    'ERBPACHT'    => 'Erbpacht',
                    'LEASING'     => 'Leasing',
                );
                foreach ($va_map as $attr => $term) {
                    $val = isset($va[$attr]) ? strtolower((string)$va[$attr]) : '';
                    if ($val === 'true' || $val === '1') {
                        $marketing_terms[] = $term;
                    }
                }
            }

            if (empty($marketing_terms)) {
                if (!empty($kaufpreis) && floatval($kaufpreis) > 0) {
                    $marketing_terms[] = 'Kauf';
                }
                if ((!empty($kaltmiete) && floatval($kaltmiete) > 0) || (!empty($warmmiete) && floatval($warmmiete) > 0)) {
                    $marketing_terms[] = 'Miete';
                }
            }

            if (!empty($marketing_terms)) {
                wp_set_object_terms($post_id, $marketing_terms, 'vermarktungsart', false); // Append = false (overwrite)
            }
        }

        // Areas
        if (isset($xml->flaechen)) {
            update_post_meta($post_id, 'wohnflaeche', (string)$xml->flaechen->wohnflaeche);
            update_post_meta($post_id, 'nutzflaeche', (string)$xml->flaechen->nutzflaeche);
            update_post_meta($post_id, 'grundstuecksflaeche', (string)$xml->flaechen->grundstuecksflaeche);
            update_post_meta($post_id, 'anzahl_zimmer', (string)$xml->flaechen->anzahl_zimmer);
            update_post_meta($post_id, 'anzahl_schlafzimmer', (string)$xml->flaechen->anzahl_schlafzimmer);
            update_post_meta($post_id, 'anzahl_badezimmer', (string)$xml->flaechen->anzahl_badezimmer);
            update_post_meta($post_id, 'anzahl_stellplaetze', (string)$xml->flaechen->anzahl_stellplaetze);
        }

        // Geo
        if (isset($xml->geo)) {
            update_post_meta($post_id, 'plz', (string)$xml->geo->plz);
            update_post_meta($post_id, 'ort', (string)$xml->geo->ort);
            update_post_meta($post_id, 'strasse', (string)$xml->geo->strasse);
            update_post_meta($post_id, 'hausnummer', (string)$xml->geo->hausnummer);

            if (isset($xml->geo->geokoordinaten)) {
                $coord = $xml->geo->geokoordinaten->attributes();
                update_post_meta($post_id, 'geo_breite', (string)$coord['breitengrad']);
                update_post_meta($post_id, 'geo_laenge', (string)$coord['laengengrad']);
            }

            // Set Location Taxonomy (replace, not append - a corrected Ort must
            // not leave the object in two location filters)
            $ort_name = trim((string)$xml->geo->ort);
            if ($ort_name !== '') {
                wp_set_object_terms($post_id, $ort_name, 'ort', false);
            }
        }

        // Infrastructure
        if (isset($xml->infrastruktur)) {
            $infra_data = array();
            foreach ($xml->infrastruktur->distanzen as $dist) {
                $type = (string)$dist['distanz_zu'];
                $val = (string)$dist;
                update_post_meta($post_id, 'distanz_' . sanitize_key($type), $val);
                $infra_data[$type] = $val;
            }
            // Optional: Store all as array for easier looping if needed
            update_post_meta($post_id, 'infrastruktur_all', $infra_data);
        }

        // Zustand & Baujahr (Generic)
        if (isset($xml->zustand_angaben)) {
            $zustand = $xml->zustand_angaben;
            if (isset($zustand->baujahr)) {
                update_post_meta($post_id, 'energiepass_baujahr', (string)$zustand->baujahr);
            }
            if (isset($zustand->zustand_art)) {
                update_post_meta($post_id, 'zustand_art', (string)$zustand->zustand_art);
            }

            // Status Mapping
            $status = 'aktiv'; // Default
            if (isset($zustand->verkaufstatus)) {
                $xml_status = strtolower((string)$zustand->verkaufstatus); // STAND, RESERVIERT, VERKAUFT, VERMIETET
                $status_attrib = isset($zustand->verkaufstatus['stand']) ? strtolower((string)$zustand->verkaufstatus['stand']) : '';

                // Check attribute first (often more reliable), then value
                $check_val = !empty($status_attrib) ? $status_attrib : $xml_status;

                if (in_array($check_val, array('verkauft', 'vermietet'))) {
                    $status = 'verkauft';
                }
                elseif ($check_val === 'reserviert') {
                    $status = 'reserviert';
                }
                elseif ($check_val === 'referenz') { // Custom extension support
                    $status = 'referenz';
                }
            }

            // A manual status change in the backend sets the override flag and
            // wins over the feed until the flag is cleared.
            $manual_override = get_post_meta($post_id, '_dbw_immo_manual_status_override', true);

            if (!$manual_override) {
                $old_status = get_post_meta($post_id, '_dbw_immo_status', true);
                update_post_meta($post_id, '_dbw_immo_status', $status);

                // Handle Sales Date
                if ($status === 'verkauft' && $old_status !== 'verkauft') {
                    $current_date = current_time('mysql');
                    update_post_meta($post_id, '_dbw_immo_sales_date', $current_date);
                }
            }
        }

        // Energy Pass
        if (isset($xml->zustand_angaben->energiepass)) {
            $ep = $xml->zustand_angaben->energiepass;
            update_post_meta($post_id, 'energiepass_art', (string)$ep->epart);
            update_post_meta($post_id, 'energiepass_gueltig_bis', (string)$ep->gueltig_bis);
            update_post_meta($post_id, 'energiepass_traeger', (string)$ep->primaerenergietraeger);
            update_post_meta($post_id, 'energiepass_wertklasse', (string)$ep->wertklasse);

            // GEG display duty: a VERBRAUCH pass carries its kWh value in
            // energieverbrauchkennwert, not endenergiebedarf. Store the raw
            // value and fall back so the frontend always has a figure.
            $bedarf    = trim((string)$ep->endenergiebedarf);
            $verbrauch = trim((string)$ep->energieverbrauchkennwert);
            update_post_meta($post_id, 'energiepass_verbrauchkennwert', $verbrauch);
            update_post_meta($post_id, 'energiepass_mitwarmwasser', (string)$ep->mitwarmwasser);
            update_post_meta($post_id, 'energiepass_endenergie', $bedarf !== '' ? $bedarf : $verbrauch);

            // Do not clobber the building year from zustand_angaben with an
            // empty pass year (both historically share this meta key).
            if (trim((string)$ep->baujahr) !== '') {
                update_post_meta($post_id, 'energiepass_baujahr', (string)$ep->baujahr);
            }
        }

        // Ausstattung (structured features)
        if (isset($xml->ausstattung)) {
            $features = array();
            $ausstattung = $xml->ausstattung;

            // Boolean features from OpenImmo spec
            $feature_map = array(
                'fahrstuhl'       => 'Aufzug',
                'gartennutzung'   => 'Garten',
                'kamin'           => 'Kamin',
                'klimatisiert'    => 'Klimaanlage',
                'rollstuhlgerecht' => 'Barrierefrei',
                'seniorengerecht' => 'Seniorengerecht',
                'swimmingpool'    => 'Pool',
                'wasch_trockenraum' => 'Wasch-/Trockenraum',
                'wintergarten'    => 'Wintergarten',
                'dv_verkabelung'  => 'Netzwerkverkabelung',
                'sauna'           => 'Sauna',
                'bibliothek'      => 'Bibliothek',
            );

            foreach ($feature_map as $xml_key => $label) {
                if (isset($ausstattung->$xml_key)) {
                    $val = $ausstattung->$xml_key;
                    $attrs = $val->attributes();
                    // Check if element exists and is not explicitly false
                    if ($val !== null && (string)$val !== 'false' && (string)$val !== '0') {
                        $features[] = $label;
                    }
                    // Also check for WAHR attribute
                    if (isset($attrs['WAHR']) && (string)$attrs['WAHR'] === 'true') {
                        if (!in_array($label, $features)) {
                            $features[] = $label;
                        }
                    }
                }
            }

            // Balkon/Terrasse
            if (isset($ausstattung->balkon_terrassen)) {
                $bt = $ausstattung->balkon_terrassen->attributes();
                if (isset($bt['BALKON']) && ((string)$bt['BALKON'] === 'true' || (string)$bt['BALKON'] === '1')) {
                    $features[] = 'Balkon';
                }
                if (isset($bt['TERRASSE']) && ((string)$bt['TERRASSE'] === 'true' || (string)$bt['TERRASSE'] === '1')) {
                    $features[] = 'Terrasse';
                }
            }

            // Keller
            if (isset($ausstattung->unterkellert)) {
                $keller_attr = (string)$ausstattung->unterkellert['keller']; // JA, NEIN, TEIL (per XSD)
                if (empty($keller_attr)) {
                    $keller_attr = (string)$ausstattung->unterkellert;
                }
                if (strtoupper($keller_attr) === 'JA' || strtoupper($keller_attr) === 'VOLL') {
                    $features[] = 'Keller';
                } elseif (strtoupper($keller_attr) === 'TEIL') {
                    $features[] = 'Teilkeller';
                }
            }

            // Heizung
            if (isset($ausstattung->heizungsart)) {
                $hz_attrs = $ausstattung->heizungsart->attributes();
                foreach ($hz_attrs as $name => $val) {
                    if ((string)$val === 'true' || (string)$val === '1') {
                        $features[] = ucfirst(strtolower(str_replace('_', ' ', $name)));
                    }
                }
            }

            // Boden
            if (isset($ausstattung->boden)) {
                $boden_attrs = $ausstattung->boden->attributes();
                foreach ($boden_attrs as $name => $val) {
                    if ((string)$val === 'true' || (string)$val === '1') {
                        $features[] = ucfirst(strtolower(str_replace('_', ' ', $name)));
                    }
                }
            }

            // Stellplatz
            if (isset($ausstattung->stellplatzart)) {
                $sp_attrs = $ausstattung->stellplatzart->attributes();
                foreach ($sp_attrs as $name => $val) {
                    if ((string)$val === 'true' || (string)$val === '1') {
                        $sp_label = str_replace(array('TIEFGARAGE', 'GARAGE', 'CARPORT', 'FREIPLATZ', 'PARKHAUS'),
                            array('Tiefgarage', 'Garage', 'Carport', 'Stellplatz', 'Parkhaus'), strtoupper($name));
                        $features[] = $sp_label;
                    }
                }
            }

            $features = array_unique($features);
            update_post_meta($post_id, '_dbw_immo_features', $features);
        }

        // Detailed Texts
        if (isset($xml->freitexte)) {
            update_post_meta($post_id, 'text_lage', (string)$xml->freitexte->lage);
            update_post_meta($post_id, 'text_ausstattung', (string)$xml->freitexte->ausstatt_beschr);
            update_post_meta($post_id, 'text_sonstiges', (string)$xml->freitexte->sonstige_angaben);
        }

        // Object Type Taxonomy - collect all terms, then set once as replace.
        // Append accumulated stale terms whenever the broker corrected the type.
        if (isset($xml->objektkategorie)) {
            $objektart_terms = array();

            // Iterate over children to find the type (e.g. <objektart><haus>...</haus></objektart>)
            // Simplified: Just taking the key name of the detailed type
            if (isset($xml->objektkategorie->objektart)) {
                foreach ($xml->objektkategorie->objektart->children() as $child) {
                    $objektart_terms[] = ucfirst($child->getName()); // e.g. 'Haus', 'Wohnung'
                    break;
                }
            }
            if (isset($xml->objektkategorie->nutzungsart)) {
                $usage_attributes = $xml->objektkategorie->nutzungsart->attributes();
                foreach ($usage_attributes as $name => $val) {
                    if ((string)$val === 'true' || (string)$val === '1') {
                        // e.g. WOHNEN or GEWERBE
                        $objektart_terms[] = ucfirst(strtolower($name));
                    }
                }
            }

            if (!empty($objektart_terms)) {
                wp_set_object_terms($post_id, array_unique($objektart_terms), 'objektart', false);
            }
        }

        // Contact Person Data
        if (isset($xml->kontaktperson)) {
            $k = $xml->kontaktperson;
            update_post_meta($post_id, 'kontaktperson_vorname', (string)$k->vorname);
            update_post_meta($post_id, 'kontaktperson_name', (string)$k->name);
            update_post_meta($post_id, 'kontaktperson_firma', (string)$k->firma);

            $email = (string)$k->email_direkt;
            if (empty($email))
                $email = (string)$k->email_zentrale;
            update_post_meta($post_id, 'kontaktperson_email', $email);

            $tel = (string)$k->tel_durchw;
            if (empty($tel))
                $tel = (string)$k->tel_zentrale;
            update_post_meta($post_id, 'kontaktperson_tel', $tel);
        }
    }

    /**
     * Process Attachments (Images).
     *
     * @param int              $post_id
     * @param SimpleXMLElement $xml
     * @param string           $base_path
     */
    private function process_attachments($post_id, $xml, $base_path)
    {
        $valid_filenames = array();

        if (isset($xml->anhaenge) && isset($xml->anhaenge->anhang)) {
            $menu_order = 0;
            foreach ($xml->anhaenge->anhang as $anhang) {
                $file_name = (string)$anhang->daten->pfad;
                $group = (string)$anhang->attributes()->gruppe; // TITELBILD, BILD, GRUNDRISS
                $title = (string)$anhang->anhangtitel;

                if (empty($file_name)) {
                    continue;
                }

                $valid_filenames[] = $file_name;

                $att_id = $this->upload_image($file_name, $base_path, $post_id);

                if (!is_wp_error($att_id) && $att_id) {
                    // Save OpenImmo specific meta
                    update_post_meta($att_id, '_openimmo_gruppe', $group);
                    update_post_meta($att_id, '_openimmo_titel', $title);

                    // Update Attachment post for title/alt/caption/order
                    $att_update = array(
                        'ID' => $att_id,
                        'post_title' => $title ? $title : basename($file_name),
                        'post_excerpt' => $title, // Caption
                        'menu_order' => $menu_order
                    );
                    wp_update_post($att_update);

                    // Update Alt Text
                    if ($title) {
                        update_post_meta($att_id, '_wp_attachment_image_alt', $title);
                    }

                    // Set Featured Image (TITELBILD takes precedence, otherwise first found)
                    if ($group === 'TITELBILD') {
                        set_post_thumbnail($post_id, $att_id);
                    }
                    elseif (!has_post_thumbnail($post_id) && $group !== 'GRUNDRISS') {
                        set_post_thumbnail($post_id, $att_id);
                    }
                }
                $menu_order++;
            }
        }

        // Orphan Cleanup: Delete legacy attachments that are no longer in this XML sync
        $existing_atts = new \WP_Query(array(
            'post_type' => 'attachment',
            'post_parent' => $post_id,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                    array(
                    'key' => '_openimmo_filename',
                    'compare' => 'EXISTS'
                )
            )
        ));

        if ($existing_atts->have_posts()) {
            foreach ($existing_atts->posts as $att_id) {
                $filename = get_post_meta($att_id, '_openimmo_filename', true);
                if (!empty($filename) && !in_array($filename, $valid_filenames)) {
                    // This attachment is no longer in the XML -> Delete it
                    wp_delete_attachment($att_id, true);
                    $this->log_debug("Altes Bild gelöscht (Post ID $post_id): $filename");
                }
            }
        }
    }

    /**
     * Process Contact Person Image.
     */
    private function process_contact_image($post_id, $xml, $base_path)
    {
        if (!isset($xml->kontaktperson) || !isset($xml->kontaktperson->foto)) {
            return;
        }

        $foto_node = $xml->kontaktperson->foto;
        $file_name = (string)$foto_node->daten->pfad;

        if (empty($file_name)) {
            return;
        }

        $att_id = $this->upload_image($file_name, $base_path, $post_id);

        if (!is_wp_error($att_id) && $att_id) {
            $url = wp_get_attachment_url($att_id);
            update_post_meta($post_id, 'kontaktperson_bild_url', $url);
            update_post_meta($post_id, 'kontaktperson_bild_id', $att_id);
        }
    }

    /**
     * Helper to upload image.
     */
    private function upload_image($file_name, $base_path, $post_id)
    {
        // Check if already imported (direct query instead of WP_Query for performance)
        global $wpdb;
        $existing_att_id = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_openimmo_filename' AND pm.meta_value = %s
             AND p.post_parent = %d AND p.post_type = 'attachment'
             LIMIT 1",
            $file_name, $post_id
        ));

        if ($existing_att_id) {
            return (int) $existing_att_id;
        }

        // Prevent path traversal: ensure file stays within base_path
        $full_path = trailingslashit($base_path) . $file_name;
        $real_full = realpath($full_path);
        $real_base = realpath($base_path);

        if (!$real_full || !$real_base || !$this->path_within($real_full, $real_base)) {
            $this->log_debug(sprintf('Path traversal blocked: %s (base: %s)', $file_name, $base_path));
            return false;
        }

        if (!file_exists($real_full)) {
            return false;
        }

        // Validate file type before uploading
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf');
        $ext = strtolower(pathinfo($real_full, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_types, true)) {
            $this->log_debug(sprintf('Blocked upload of disallowed file type: %s', $file_name));
            return false;
        }

        $file_array = array(
            'name' => basename($real_full),
            'tmp_name' => $real_full,
        );

        $tmp_file = wp_tempnam(basename($real_full));
        copy($real_full, $tmp_file);
        $file_array['tmp_name'] = $tmp_file;

        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        // Route the file into uploads/immobilien/<post-id>/ so the property
        // owns its folder and can be removed without leftovers.
        \DBW\ImmoSuite\Core\Media::begin_upload_capture($post_id);
        $att_id = media_handle_sideload($file_array, $post_id);
        \DBW\ImmoSuite\Core\Media::end_upload_capture();

        // Cleanup temp file if sideload failed (WP removes it on success)
        if (is_wp_error($att_id) && file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        if (!is_wp_error($att_id)) {
            update_post_meta($att_id, '_openimmo_filename', $file_name);
            \DBW\ImmoSuite\Core\Media::mark_attachment($att_id);
        }

        return $att_id;
    }

    /**
     * Find existing property by OpenImmo ID.
     *
     * @param string $openimmo_id
     * @return int|false Post ID or false.
     */
    private function get_property_by_openimmo_id($openimmo_id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = 'openimmo_id' AND pm.meta_value = %s
             AND p.post_type = 'immobilie'
             LIMIT 1",
            $openimmo_id
        )) ?: false;
    }

    /**
     * AJAX: Prepare Import (Step 1)
     * Locates files, extracts ZIPs, returns file list and counts.
     */
    public function ajax_prepare_import()
    {
        try {
            check_ajax_referer('dbw_immo_import_nonce', 'nonce');
            if (!current_user_can('manage_options'))
                wp_send_json_error('Keine Berechtigung');
            if (function_exists('set_time_limit')) { set_time_limit(600); }

            // 1. Locate XML Path
            $options = get_option('dbw_immo_suite_settings');
            $xml_path = $this->resolve_import_path($options);

            if (!$xml_path || !is_dir($xml_path))
                wp_send_json_error(__('Import-Verzeichnis nicht gefunden.', 'dbw-immo-suite'));

            // Same lock as the cron path - an hourly cron tick must not chew
            // on the ZIPs this batch run is about to process. Released in
            // ajax_finalize_import, TTL covers an abandoned browser session.
            if (!$this->acquire_lock(900)) {
                wp_send_json_error(__('Ein Import laeuft bereits (Cron oder anderer Tab). Bitte kurz warten und erneut versuchen.', 'dbw-immo-suite'));
            }

            $this->log_debug('AJAX Import-Pfad aufgeloest: ' . $xml_path);

            // Housekeeping: abandoned tmp_ dirs + old .processed/.skipped files
            $this->cleanup_import_dir($xml_path);

            // 2. Extract ZIPs & Check Hashes
            $zips = glob($xml_path . '*.zip');

            if ($zips === false) {
                $this->log_debug('glob() fehlgeschlagen für: ' . $xml_path . ' — open_basedir: ' . (ini_get('open_basedir') ?: '(nicht gesetzt)'));

                // Fallback: try uploads dir
                $upload_dir = isset($upload_dir) ? $upload_dir : wp_upload_dir();
                $fallback = trailingslashit($upload_dir['basedir']) . 'openimmo/';
                if ($fallback !== $xml_path && is_dir($fallback)) {
                    $fallback_test = glob($fallback . '*.zip');
                    if ($fallback_test !== false) {
                        $this->log_debug('Fallback auf Uploads-Verzeichnis: ' . $fallback);
                        $xml_path = $fallback;
                        $zips = $fallback_test;
                    }
                }

                if ($zips === false) {
                    $this->release_lock();
                    wp_send_json_error(sprintf(
                        'Verzeichnis "%s" nicht lesbar (Berechtigungsproblem oder PHP open_basedir). Bitte den Uploads-Pfad in den Einstellungen wählen.',
                        $xml_path
                    ));
                }
            }
            $active_zips = array();

            if (!empty($zips)) {
                foreach ($zips as $zip_file) {
                    $temp_dir = $xml_path . 'tmp_' . uniqid() . '/';
                    $zip = new \ZipArchive;
                    if ($zip->open($zip_file) === TRUE) {
                        mkdir($temp_dir, 0755, true);
                        if (!$this->safe_extract_zip($zip, $temp_dir)) { $zip->close(); continue; }
                        $zip->close();

                        // Find XMLs recursively (ZIPs may contain subdirectories)
                        $temp_xmls = array();
                        $temp_files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temp_dir));
                        foreach ($temp_files as $temp_file) {
                            if ($temp_file->isFile() && strtolower($temp_file->getExtension()) === 'xml') {
                                $temp_xmls[] = $temp_file->getPathname();
                            }
                        }

                        // Check MD5 Hash
                        if (!empty($temp_xmls)) {
                            $hash = '';
                            foreach ($temp_xmls as $xml_file) {
                                $hash .= md5_file($xml_file);
                            }
                            $hash = md5($hash);
                            $last_hash = $this->get_last_xml_hash(basename($zip_file));

                            if ($hash === $last_hash && !empty($hash)) {
                                $this->log_debug('ZIP übersprungen (XML Hash identisch): ' . basename($zip_file));
                                $this->delete_directory($temp_dir);
                                rename($zip_file, $zip_file . '.skipped');
                                $this->log_history($zip_file, array('created' => 0, 'updated' => 0, 'errors' => 0), 'skipped');
                                continue;
                            }

                            $active_zips[] = array(
                                'file' => $zip_file,
                                'temp_dir' => $temp_dir,
                                'hash' => $hash,
                                'xmls' => $temp_xmls
                            );
                        }
                    }
                }
            }

            // Save active ZIPs for cleanup phase
            if (!empty($active_zips)) {
                set_transient('dbw_immo_batch_zips', $active_zips, 3600);
            }

            // Allow garbage collection to track across batches
            set_transient('dbw_immo_batch_processed_ids', array(), 3600);
            delete_transient(self::BATCH_FULL_SYNC_TRANSIENT);

            // 3. Collect XML files for JS queue
            $xml_files_data = array();
            $batch_full_sync = false;

            // a) From active ZIPs
            foreach ($active_zips as $az) {
                foreach ($az['xmls'] as $xml_file) {
                    $xml = $this->safe_load_xml($xml_file);
                    if ($xml) {
                        // xpath covers multi-anbieter feeds; ->anbieter->immobilie
                        // would only count the first anbieter node
                        $props = $xml->xpath('anbieter/immobilie');
                        $count = is_array($props) ? count($props) : 0;
                        if ($count > 0) {
                            $xml_files_data[] = array(
                                'file' => $xml_file,
                                'count' => $count
                            );
                            $batch_full_sync = $batch_full_sync || $this->is_full_sync($xml);
                        }
                    }
                }
            }

            // b) From loose XML files
            $loose_files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($xml_path));
            foreach ($loose_files as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'xml' && strpos($file->getPathname(), '/tmp_') === false) {
                    $xml = $this->safe_load_xml($file->getPathname());
                    if ($xml) {
                        $props = $xml->xpath('anbieter/immobilie');
                        $count = is_array($props) ? count($props) : 0;
                        if ($count > 0) {
                            $xml_files_data[] = array(
                                'file' => $file->getPathname(),
                                'count' => $count,
                                'loose' => true
                            );
                            $batch_full_sync = $batch_full_sync || $this->is_full_sync($xml);
                        }
                    }
                }
            }

            if ($batch_full_sync) {
                set_transient(self::BATCH_FULL_SYNC_TRANSIENT, 1, 3600);
            }

            if (empty($xml_files_data)) {
                wp_send_json_success(array(
                    'files' => array(),
                    'message' => 'Analyse beendet. Keine Verarbeitung notwendig (Dateien unverändert oder nicht lesbar).'
                ));
            }

            // Initialize progress transient for live polling
            $total_properties = 0;
            $file_names = array();
            foreach ($xml_files_data as $fd) {
                $total_properties += $fd['count'];
                $file_names[] = basename($fd['file']);
            }
            set_transient('dbw_immo_import_progress', array(
                'status'       => 'running',
                'total_files'  => count($xml_files_data),
                'total'        => $total_properties,
                'processed'    => 0,
                'created'      => 0,
                'updated'      => 0,
                'errors'       => 0,
                'current_file' => '',
                'file_names'   => $file_names,
                'per_file'     => array(),
                'started'      => time(),
            ), 3600);

            wp_send_json_success(array(
                'files' => $xml_files_data,
                'message' => 'Dateien analysiert. Starte Prozess...'
            ));

        }
        catch (\Throwable $e) {
            $this->release_lock();
            $this->log_debug('Prepare Error: ' . $e->getMessage());
            wp_send_json_error(__('Fehler bei der Import-Vorbereitung. Details im Import-Log.', 'dbw-immo-suite'));
        }
    }

    /**
     * AJAX: Poll import progress (called every 2s from dashboard).
     */
    public function ajax_import_progress()
    {
        check_ajax_referer('dbw_immo_import_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error('Keine Berechtigung');

        $progress = get_transient('dbw_immo_import_progress');
        if (!$progress) {
            wp_send_json_success(array('status' => 'idle', 'processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0));
        }

        unset($progress['per_file']); // internal bookkeeping, not for the poller

        wp_send_json_success($progress);
    }

    /**
     * AJAX: Process Single Batch (Step 2)
     */
    public function ajax_process_batch()
    {
        try {
            check_ajax_referer('dbw_immo_import_nonce', 'nonce');
            if (!current_user_can('manage_options'))
                wp_send_json_error('Keine Berechtigung');
            if (function_exists('set_time_limit')) { set_time_limit(300); }
            wp_raise_memory_limit('admin');

            $file = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
            $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
            // Batch size: each request bootstraps WP and re-parses the XML, so
            // processing several properties per request cuts that overhead 5-10x
            $limit = isset($_POST['limit']) ? max(1, min(20, intval($_POST['limit']))) : 1;

            // Keep the shared lock alive while batches are running
            $this->refresh_lock(900);

            // Validate file path is within allowed import directory
            $options = get_option('dbw_immo_suite_settings');
            $allowed_base = $this->resolve_import_path($options);
            $real_file = realpath($file);
            $real_base = $allowed_base ? realpath($allowed_base) : false;

            if (!$real_file || !$real_base || !$this->path_within($real_file, $real_base)) {
                wp_send_json_error(__('Ungültiger Dateipfad.', 'dbw-immo-suite'));
            }

            if (!file_exists($real_file))
                wp_send_json_error(__('Datei nicht gefunden.', 'dbw-immo-suite'));

            $this->current_xml_file = $file; // IMPORTANT for images

            $xml = $this->safe_load_xml($file);
            // xpath covers multi-anbieter feeds (same flat indexing as prepare)
            $props = $xml ? $xml->xpath('anbieter/immobilie') : array();
            if (!$xml || !is_array($props) || !isset($props[$index])) {
                wp_send_json_error('Immobilie nicht gefunden bei Index ' . $index);
            }

            $stats = array('created' => 0, 'updated' => 0, 'errors' => 0);
            $processed = 0;

            for ($i = $index; $i < $index + $limit; $i++) {
                if (!isset($props[$i])) {
                    break;
                }
                $this->import_property($props[$i], $stats);
                $processed++;
            }

            // Update progress transient for live polling
            $progress = get_transient('dbw_immo_import_progress');
            if (is_array($progress)) {
                $progress['processed'] += $processed;
                $progress['current_file'] = basename($file);
                $progress['created']  += $stats['created'];
                $progress['updated']  += $stats['updated'];
                $progress['errors']   += $stats['errors'];

                // Per file, so the history can show real numbers instead of zeros
                if (!isset($progress['per_file'][$real_file])) {
                    $progress['per_file'][$real_file] = array('created' => 0, 'updated' => 0, 'errors' => 0);
                }
                $progress['per_file'][$real_file]['created'] += $stats['created'];
                $progress['per_file'][$real_file]['updated'] += $stats['updated'];
                $progress['per_file'][$real_file]['errors']  += $stats['errors'];

                set_transient('dbw_immo_import_progress', $progress, 3600);
            }

            if ($stats['errors'] > 0) {
                wp_send_json_error('Fehler beim Import ab Immobilie Index ' . $index . ' (' . $stats['errors'] . ' Fehler im Batch).');
            }

            wp_send_json_success(array(
                'message'   => $processed . ' Immobilie(n) importiert.',
                'processed' => $processed,
            ));

        }
        catch (\Throwable $e) {
            $this->log_debug("Batch Error (#$index): " . $e->getMessage());
            wp_send_json_error(__('Import-Fehler bei Immobilie Nr. ', 'dbw-immo-suite') . ($index + 1));
        }
    }

    /**
     * AJAX: Finalize Import (Step 3)
     */
    public function ajax_finalize_import()
    {
        try {
            check_ajax_referer('dbw_immo_import_nonce', 'nonce');
            if (!current_user_can('manage_options'))
                wp_send_json_error('Keine Berechtigung');

            $progress = get_transient('dbw_immo_import_progress');
            $per_file = (is_array($progress) && !empty($progress['per_file'])) ? $progress['per_file'] : array();

            // 1. Cleanup ZIPs
            $active_zips = get_transient('dbw_immo_batch_zips');
            if (is_array($active_zips)) {
                foreach ($active_zips as $az) {
                    $this->set_last_xml_hash(basename($az['file']), $az['hash']);
                    // Sum up every XML that was extracted from this ZIP
                    $zip_stats = $this->collect_file_stats($per_file, $az['temp_dir']);
                    $this->delete_directory($az['temp_dir']);
                    rename($az['file'], $az['file'] . '.processed');
                    $this->log_debug('ZIP erfolgreich verarbeitet: ' . basename($az['file']));
                    $this->log_history($az['file'], $zip_stats, 'success');
                }
                delete_transient('dbw_immo_batch_zips');
            }

            // 2. Cleanup Loose XMLs - validate against allowed import path
            $options = get_option('dbw_immo_suite_settings');
            $allowed_base = $this->resolve_import_path($options);

            $loose_files = isset($_POST['loose_files']) ? (array) $_POST['loose_files'] : array();
            if (!empty($loose_files) && $allowed_base) {
                foreach ($loose_files as $lf) {
                    $lf = sanitize_text_field($lf);
                    $real_path = realpath($lf);
                    // Only allow files within the configured import directory
                    if ($real_path && $this->path_within($real_path, (string) realpath($allowed_base)) && file_exists($real_path)) {
                        $loose_stats = isset($per_file[$real_path])
                            ? $per_file[$real_path]
                            : array('created' => 0, 'updated' => 0, 'errors' => 0);
                        rename($real_path, $real_path . '.processed');
                        $this->log_debug('Lose XML-Datei archiviert: ' . basename($real_path));
                        $this->log_history($real_path, $loose_stats, 'success');
                    }
                }
            }

            // 3. Garbage Collection - only if this batch run contained a
            // delivery that declared itself a full sync (umfang="VOLL")
            $options = get_option('dbw_immo_suite_settings');
            if (!empty($options['enable_garbage_collection'])) {
                $batch_ids = get_transient('dbw_immo_batch_processed_ids');
                if (is_array($batch_ids) && !empty($batch_ids)) {
                    if (get_transient(self::BATCH_FULL_SYNC_TRANSIENT)) {
                        // Populate instance variable for the GC run
                        $this->processed_openimmo_ids = $batch_ids;
                        $this->run_garbage_collection();
                    } else {
                        $this->log_debug('Garbage Collection uebersprungen: Lieferung war kein Vollabgleich (umfang != VOLL).');
                    }
                }
            }
            delete_transient('dbw_immo_batch_processed_ids');
            delete_transient(self::BATCH_FULL_SYNC_TRANSIENT);

            // Mark progress as done so polling stops
            $progress = get_transient('dbw_immo_import_progress');
            if (is_array($progress)) {
                unset($progress['per_file']); // keep the polled payload small
                $progress['status'] = 'done';
                set_transient('dbw_immo_import_progress', $progress, 60);
            }

            $this->release_lock();

            wp_send_json_success('Import und Cleanup erfolgreich abgeschlossen.');
        }
        catch (\Throwable $e) {
            $this->release_lock();

            // Mark progress as error
            $progress = get_transient('dbw_immo_import_progress');
            if (is_array($progress)) {
                $progress['status'] = 'error';
                $progress['error_message'] = $e->getMessage();
                set_transient('dbw_immo_import_progress', $progress, 60);
            }
            wp_send_json_error('Fehler bei Cleanup: ' . $e->getMessage());
        }
    }

    /**
     * Sum the per-file stats of every XML that came out of one directory.
     *
     * @param array  $per_file Absolute path => stats
     * @param string $dir      Directory the files were extracted to
     * @return array{created:int,updated:int,errors:int}
     */
    private function collect_file_stats($per_file, $dir)
    {
        $sum = array('created' => 0, 'updated' => 0, 'errors' => 0);
        $real_dir = realpath($dir);

        if (!$real_dir) {
            return $sum;
        }

        foreach ($per_file as $path => $stats) {
            if (strpos($path, $real_dir) !== 0) {
                continue;
            }
            $sum['created'] += (int) $stats['created'];
            $sum['updated'] += (int) $stats['updated'];
            $sum['errors']  += (int) $stats['errors'];
        }

        return $sum;
    }

    /**
     * AJAX Handler for triggering import.
     */
    public function ajax_run_import()
    {
        try {
            check_ajax_referer('dbw_immo_import_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Keine Berechtigung');
            }

            $result = $this->run_import();

            if ($result['success']) {
                wp_send_json_success($result['message']);
            }
            else {
                wp_send_json_error($result['message']);
            }
        }
        catch (\Throwable $e) {
            $this->log_debug('Finalize Error: ' . $e->getMessage());
            wp_send_json_error(__('Fehler beim Abschluss. Details im Import-Log.', 'dbw-immo-suite'));
        }
    }

    /**
     * AJAX: Dry run - analyze all pending files and report what an import
     * WOULD do (create/update/delete, hash skips, GC simulation) without
     * touching a single post, file hash or ZIP. Builds trust before the
     * real run, especially with garbage collection enabled.
     */
    public function ajax_dry_run()
    {
        $temp_dirs = array();

        try {
            check_ajax_referer('dbw_immo_import_nonce', 'nonce');
            if (!current_user_can('manage_options'))
                wp_send_json_error('Keine Berechtigung');
            if (function_exists('set_time_limit')) { set_time_limit(300); }

            $options = get_option('dbw_immo_suite_settings');
            $xml_path = $this->resolve_import_path($options);

            if (!$xml_path || !is_dir($xml_path))
                wp_send_json_error(__('Import-Verzeichnis nicht gefunden.', 'dbw-immo-suite'));

            $result = array(
                'files' => array(),
                'gc'    => array(
                    'enabled'   => !empty($options['enable_garbage_collection']),
                    'full_sync' => false,
                    'archive'   => array(),
                    'brake'     => false,
                ),
                'notes' => array(),
            );

            $feed_ids  = array();
            $full_sync = false;

            // ZIPs
            foreach (glob($xml_path . '*.zip') ?: array() as $zip_file) {
                $zip = new \ZipArchive;
                if ($zip->open($zip_file) !== TRUE) {
                    $result['notes'][] = sprintf(__('%s: ZIP nicht lesbar (evtl. Upload noch aktiv).', 'dbw-immo-suite'), basename($zip_file));
                    continue;
                }

                $temp_dir = $xml_path . 'tmp_dry_' . uniqid() . '/';
                mkdir($temp_dir, 0755, true);
                $temp_dirs[] = $temp_dir;

                if (!$this->safe_extract_zip($zip, $temp_dir)) {
                    $zip->close();
                    $result['notes'][] = sprintf(__('%s: ZIP blockiert (Details im Import-Log).', 'dbw-immo-suite'), basename($zip_file));
                    continue;
                }
                $zip->close();

                $temp_xmls = array();
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temp_dir)) as $f) {
                    if ($f->isFile() && strtolower($f->getExtension()) === 'xml') {
                        $temp_xmls[] = $f->getPathname();
                    }
                }

                if (empty($temp_xmls)) {
                    $result['notes'][] = sprintf(__('%s: keine XML im Archiv.', 'dbw-immo-suite'), basename($zip_file));
                    continue;
                }

                // Hash skip simulation
                $hash = '';
                foreach ($temp_xmls as $xf) { $hash .= md5_file($xf); }
                $hash = md5($hash);
                if ($hash === $this->get_last_xml_hash(basename($zip_file)) && !empty($hash)) {
                    $result['files'][] = array('file' => basename($zip_file), 'skipped' => true, 'items' => array());
                    continue;
                }

                $items = array();
                foreach ($temp_xmls as $xf) {
                    $this->dry_run_analyze_xml($xf, $items, $feed_ids, $full_sync);
                }
                $result['files'][] = array('file' => basename($zip_file), 'skipped' => false, 'items' => $items);
            }

            // Loose XMLs
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($xml_path)) as $f) {
                if ($f->isFile() && strtolower($f->getExtension()) === 'xml'
                    && strpos($f->getPathname(), '/tmp_') === false) {
                    $items = array();
                    $this->dry_run_analyze_xml($f->getPathname(), $items, $feed_ids, $full_sync);
                    if (!empty($items)) {
                        $result['files'][] = array('file' => basename($f->getPathname()), 'skipped' => false, 'items' => $items);
                    }
                }
            }

            // GC simulation
            $result['gc']['full_sync'] = $full_sync;
            if ($result['gc']['enabled'] && $full_sync && !empty($feed_ids)) {
                $all = new \WP_Query(array(
                    'post_type' => 'immobilie',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'meta_query' => array(array('key' => 'openimmo_id', 'compare' => 'EXISTS')),
                ));
                if ($all->have_posts()) {
                    update_meta_cache('post', $all->posts);
                    $feed_flip = array_flip($feed_ids);
                    foreach ($all->posts as $pid) {
                        $oid = get_post_meta($pid, 'openimmo_id', true);
                        if (!empty($oid) && !isset($feed_flip[$oid])) {
                            $result['gc']['archive'][] = get_the_title($pid) ?: ('#' . $pid);
                        }
                    }
                    $total = count($all->posts);
                    if (count($result['gc']['archive']) > 20 && count($result['gc']['archive']) * 2 > $total) {
                        $result['gc']['brake'] = true;
                    }
                }
            }

            wp_send_json_success($result);
        }
        catch (\Throwable $e) {
            $this->log_debug('Dry-Run Error: ' . $e->getMessage());
            wp_send_json_error(__('Fehler beim Testlauf. Details im Import-Log.', 'dbw-immo-suite'));
        }
        finally {
            foreach ($temp_dirs as $dir) {
                $this->delete_directory($dir);
            }
        }
    }

    /**
     * Classify every property of one XML for the dry run.
     */
    private function dry_run_analyze_xml($file, &$items, &$feed_ids, &$full_sync)
    {
        $xml = $this->safe_load_xml($file);
        if (!$xml) {
            $items[] = array('title' => basename($file), 'action' => 'fehler', 'detail' => __('XML nicht lesbar', 'dbw-immo-suite'));
            return;
        }

        if ($this->is_full_sync($xml)) {
            $full_sync = true;
        }

        $props = $xml->xpath('anbieter/immobilie');
        foreach ((array) $props as $p) {
            $vt = $p->verwaltung_techn;
            $oid = (string) $vt->openimmo_obid;
            if (empty($oid) && isset($p->obid)) {
                $oid = (string) $p->obid;
            }
            if (empty($oid)) {
                $items[] = array('title' => __('(ohne OpenImmo-ID)', 'dbw-immo-suite'), 'action' => 'fehler', 'detail' => '');
                continue;
            }

            $action = '';
            if (isset($vt->aktion)) {
                $action = (string) $vt->aktion['aktionart'];
                if ($action === '') { $action = (string) $vt->aktion['actiontype']; }
                if ($action === '') { $action = (string) $vt->aktion; }
                $action = strtoupper($action);
            }

            $title = isset($p->freitexte->objekttitel) ? trim((string) $p->freitexte->objekttitel) : '';
            if ($title === '') {
                $title = 'Immobilie ' . $oid;
            }

            $existing = $this->get_property_by_openimmo_id($oid);
            $feed_ids[] = $oid;

            if ($action === 'DELETE' || $action === 'LÖSCHEN' || $action === 'LOESCHEN') {
                $items[] = array('title' => $title, 'action' => $existing ? 'loeschen' : 'unbekannt', 'detail' => $oid);
            } elseif ($action === 'REFERENZ') {
                $items[] = array('title' => $title, 'action' => 'referenz', 'detail' => $oid);
            } else {
                $items[] = array('title' => $title, 'action' => $existing ? 'aktualisieren' : 'neu', 'detail' => $oid);
            }
        }
    }

    /**
     * AJAX: Return the tail of the import log for the dashboard viewer.
     */
    public function ajax_get_log()
    {
        check_ajax_referer('dbw_immo_import_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error('Keine Berechtigung');

        $log_file = DBW_IMMO_SUITE_PATH . 'logs/import-' . wp_hash('dbw_immo_import_log') . '.log';

        if (!file_exists($log_file)) {
            wp_send_json_success(array('lines' => array(), 'size' => 0));
        }

        $lines = array();
        $fh = new \SplFileObject($log_file, 'r');
        $fh->seek(PHP_INT_MAX);
        $total = $fh->key();
        $start = max(0, $total - 200);
        $fh->seek($start);
        while (!$fh->eof() && count($lines) < 220) {
            $line = rtrim((string) $fh->current());
            if ($line !== '') {
                $lines[] = $line;
            }
            $fh->next();
        }

        wp_send_json_success(array(
            'lines' => $lines,
            'size'  => size_format((int) filesize($log_file)),
        ));
    }

    /**
     * Resolve the configured import path to an absolute directory.
     */
    private function resolve_import_path($options)
    {
        $xml_path = isset($options['xml_path']) ? $options['xml_path'] : '';
        if (!empty($xml_path) && is_dir($xml_path)) {
            return trailingslashit($xml_path);
        } elseif (!empty($xml_path) && is_dir(ABSPATH . ltrim($xml_path, '/'))) {
            return trailingslashit(ABSPATH . ltrim($xml_path, '/'));
        } elseif (empty($xml_path)) {
            $upload_dir = wp_upload_dir();
            return $upload_dir['basedir'] . '/openimmo/';
        }
        return false;
    }

    /**
     * Safely load an XML file with XXE protection.
     *
     * @param string $file Path to XML file.
     * @return \SimpleXMLElement|false
     */
    private function safe_load_xml($file)
    {
        $previous = libxml_use_internal_errors(true);

        // PHP 8.0+ disables entity loading by default; calling the deprecated
        // libxml_disable_entity_loader() on 8.2+ can block simplexml_load_file entirely.
        if (PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
            $previous_entities = libxml_disable_entity_loader(true);
        }

        $xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NONET);

        if (PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
            libxml_disable_entity_loader($previous_entities);
        }

        if (!$xml) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $this->log_debug(sprintf('XML Parse Error in %s (Line %d): %s', basename($file), $error->line, trim($error->message)));
            }
            libxml_clear_errors();
        }

        libxml_use_internal_errors($previous);
        return $xml;
    }

    private function log_debug($msg)
    {
        $log_dir = DBW_IMMO_SUITE_PATH . 'logs';
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
            // Protect log directory from web access (Apache/LiteSpeed)
            @file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
            @file_put_contents($log_dir . '/index.php', "<?php // Silence is golden.\n");
        }
        // Unguessable filename so the log stays private on NGINX too (no .htaccess support)
        $log_file = $log_dir . '/import-' . wp_hash('dbw_immo_import_log') . '.log';
        $entry = date('Y-m-d H:i:s') . ' - ' . wp_strip_all_tags($msg) . PHP_EOL;
        @file_put_contents($log_file, $entry, FILE_APPEND);
    }

    /**
     * Recursively delete a directory.
     * 
     * @param string $dir
     * @return bool
     */
    private function delete_directory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }

    /**
     * Did this feed declare itself a full sync?
     * OpenImmo header: <uebertragung art=".." umfang="VOLL|TEIL" .../>
     */
    private function is_full_sync($xml)
    {
        if (!isset($xml->uebertragung)) {
            return false;
        }
        return strtoupper((string)$xml->uebertragung['umfang']) === 'VOLL';
    }

    /**
     * Housekeeping in the import directory: abandoned tmp_ extraction dirs
     * (aborted dashboard runs) and old .processed/.skipped files would
     * otherwise accumulate forever on the customer's hosting quota.
     */
    private function cleanup_import_dir($xml_path)
    {
        foreach (glob($xml_path . 'tmp_*', GLOB_ONLYDIR) ?: array() as $dir) {
            if (@filemtime($dir) < time() - DAY_IN_SECONDS) {
                $this->delete_directory($dir);
                $this->log_debug('Verwaistes Temp-Verzeichnis entfernt: ' . basename($dir));
            }
        }

        foreach (array('*.processed', '*.skipped') as $pattern) {
            foreach (glob($xml_path . $pattern) ?: array() as $file) {
                if (is_file($file) && @filemtime($file) < time() - 14 * DAY_IN_SECONDS) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Garbage Collection.
     * Archives all properties that were not in the current Full Sync.
     */
    private function run_garbage_collection()
    {
        $all_properties = new \WP_Query(array(
            'post_type' => 'immobilie',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                    array(
                    'key' => 'openimmo_id',
                    'compare' => 'EXISTS'
                )
            )
        ));

        if (!$all_properties->have_posts()) {
            return;
        }

        // One query instead of one get_post_meta query per property
        update_meta_cache('post', $all_properties->posts);
        $processed_flip = array_flip($this->processed_openimmo_ids);

        $to_archive = array();
        foreach ($all_properties->posts as $post_id) {
            $oid = get_post_meta($post_id, 'openimmo_id', true);
            if (!empty($oid) && !isset($processed_flip[$oid])) {
                $to_archive[$post_id] = $oid;
            }
        }

        if (empty($to_archive)) {
            return;
        }

        // Emergency brake: a delivery that would wipe most of the inventory is
        // almost certainly a partial delivery mislabeled as VOLL (or a broken
        // export). Better a visible alert than an empty website.
        $total = count($all_properties->posts);
        if (count($to_archive) > 20 && count($to_archive) * 2 > $total) {
            $this->log_debug(sprintf(
                'Garbage Collection ABGEBROCHEN (Notbremse): haette %d von %d Objekten archiviert. Lieferung pruefen.',
                count($to_archive),
                $total
            ));
            $this->log_history('Garbage Collection (Notbremse)', array('created' => 0, 'updated' => 0, 'errors' => 1), 'error');
            return;
        }

        foreach ($to_archive as $post_id => $oid) {
            // Not in the feed -> Archive it
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));

            update_post_meta($post_id, '_dbw_immo_status', 'archiviert');
            update_post_meta($post_id, \DBW\ImmoSuite\Core\Media::META_ARCHIVED, current_time('mysql'));

            $this->log_debug("Garbage Collection: Immobilie $oid ($post_id) archiviert, da nicht mehr im Feed.");
        }

        $this->log_debug('Garbage Collection abgeschlossen: ' . count($to_archive) . ' Objekte archiviert.');
    }
}