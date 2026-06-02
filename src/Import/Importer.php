<?php

if (!defined('ABSPATH')) { exit; }

namespace DBW\ImmoSuite\Import;

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
     * IDs of properties processed in the current run (for GC).
     * @var array
     */
    private $processed_openimmo_ids = array();

    /**
     * Run the import process.
     *
     * @return array Result stats.
     */
    public function run_import()
    {
        // 1. Check Lock
        if (get_transient('dbw_immo_import_lock')) {
            return array('success' => false, 'message' => 'Import läuft bereits. Bitte warten.');
        }

        // 2. Set Lock (expires in 5 mins security fallback)
        set_transient('dbw_immo_import_lock', time(), 300);

        $this->log_debug('--- Starte Import ---');

        try {
            // Increase limits
            if (function_exists('set_time_limit')) { set_time_limit(600); }
            ini_set('max_execution_time', '600');
            wp_raise_memory_limit('admin');

            $options = get_option('dbw_immo_suite_settings');
            $xml_path = isset($options['xml_path']) ? $options['xml_path'] : '';

            // Path Logic
            $xml_path_raw = $xml_path;
            if (!empty($xml_path) && is_dir($xml_path)) {
                $xml_path = trailingslashit($xml_path);
            }
            elseif (!empty($xml_path) && is_dir(ABSPATH . ltrim($xml_path, '/'))) {
                $xml_path = trailingslashit(ABSPATH . ltrim($xml_path, '/'));
            }
            elseif (empty($xml_path)) {
                $upload_dir = wp_upload_dir();
                $xml_path = $upload_dir['basedir'] . '/openimmo/';
            }
            else {
                throw new \Exception(sprintf('Verzeichnis "%s" nicht gefunden (konfiguriert: "%s").', $xml_path, $xml_path_raw));
            }

            if (!is_dir($xml_path)) {
                throw new \Exception('Kein gültiges Import-Verzeichnis: ' . $xml_path);
            }

            $this->log_debug('Import-Pfad aufgelöst: ' . $xml_path . ' (konfiguriert: ' . $xml_path_raw . ')');

            $stats = array(
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
            );

            $xmls_processed = 0;

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
                    $temp_dir = $xml_path . 'tmp_' . uniqid() . '/';

                    $zip = new \ZipArchive;
                    if ($zip->open($zip_file) === TRUE) {
                        mkdir($temp_dir, 0755, true);
                        $zip->extractTo($temp_dir);
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

                            $last_hash = get_option('dbw_immo_last_xml_hash_' . basename($zip_file), '');

                            if ($hash === $last_hash && !empty($hash)) {
                                $this->log_debug('ZIP übersprungen (XML Hash identisch): ' . basename($zip_file));
                                $this->delete_directory($temp_dir);
                                rename($zip_file, $zip_file . '.skipped');

                                // NEW: Log skipped explicitly in history array
                                $this->log_history($zip_file, array('created' => 0, 'updated' => 0, 'errors' => 0), 'skipped');

                                continue;
                            }

                            // Process XMLs
                            foreach ($temp_xmls as $file) {
                                $this->process_file($file, $stats);
                                $this->log_history($file, $stats, 'success');
                                $xmls_processed++;
                            }

                            update_option('dbw_immo_last_xml_hash_' . basename($zip_file), $hash);
                        }
                        else {
                            $this->log_debug('Keine XML in ZIP gefunden: ' . basename($zip_file));
                        }

                        // Cleanup temp dir and rename ZIP
                        $this->delete_directory($temp_dir);
                        rename($zip_file, $zip_file . '.processed');
                        $this->log_debug('ZIP erfolgreich verarbeitet: ' . basename($zip_file));
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
                    $this->process_file($file, $stats);
                    $this->log_history($file, $stats, 'success');
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

            // Garbage Collection
            if ($xmls_processed > 0 && !empty($options['enable_garbage_collection'])) {
                $this->run_garbage_collection();
            }

            // Release Lock
            delete_transient('dbw_immo_import_lock');

            return array(
                'success' => true,
                'message' => sprintf('Import fertig. Erstellt: %d, Aktualisiert: %d, Fehler: %d', $stats['created'], $stats['updated'], $stats['errors']),
            );

        }
        catch (\Exception $e) {
            // Error Handling
            delete_transient('dbw_immo_import_lock');
            $this->log_debug('Error: ' . $e->getMessage());

            // Log failed run
            $this->log_history('System', array('created' => 0, 'updated' => 0, 'errors' => 1), 'error');

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

        update_option('dbw_immo_import_history', $history);
    }

    /**
     * Extract ZIP file.
     * 
     * @param string $zip_file
     * @param string $target_path
     */
    private function extract_zip($zip_file, $target_path)
    {
        $zip = new \ZipArchive;
        if ($zip->open($zip_file) === TRUE) {
            // Extract to a folder named after the zip to avoid collisions
            $extract_to = $target_path . pathinfo($zip_file, PATHINFO_FILENAME) . '/';
            if (!is_dir($extract_to)) {
                mkdir($extract_to, 0755, true);
            }
            $zip->extractTo($extract_to);
            $zip->close();

            // Optional: Rename/Move ZIP to '.processed' or similar to avoid re-extraction?
            // For now, we leave it. Real production code should move it.
            rename($zip_file, $zip_file . '.processed');
        }
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

        // Check Action Type (DELETE, ARCHIVE, etc.)
        $action_type = '';
        if (isset($verwaltung_techn->aktion)) {
            $action_type = (string)$verwaltung_techn->aktion['actiontype'];
            // Sometimes it's the node value
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
                $this->log_debug("Immobilie $openimmo_id ($existing_id) wurde archiviert/Entwurf (Action: $action_type).");
                $stats['updated']++;
            }
            return; // Stop processing
        }


        // Title Generation
        $geo = $immobilie->geo;
        $freitexte = $immobilie->freitexte;
        $titel = isset($freitexte->objekttitel) ? (string)$freitexte->objekttitel : 'Immobilie ' . $openimmo_id;

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
            $stats['updated']++;
        }
        else {
            $post_id = wp_insert_post($post_data);
            $stats['created']++;
            update_post_meta($post_id, 'openimmo_id', $openimmo_id);
        }

        // Map Fields
        $this->map_fields($post_id, $immobilie);

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

            // Set Marketing Type Taxonomy
            $marketing_terms = array();
            if (!empty($kaufpreis) && floatval($kaufpreis) > 0) {
                $marketing_terms[] = 'Kauf';
            }
            if ((!empty($kaltmiete) && floatval($kaltmiete) > 0) || (!empty($warmmiete) && floatval($warmmiete) > 0)) {
                $marketing_terms[] = 'Miete';
            }

            // Fallback: Check OpenImmo <vermarktungsart> if available (often checked via attributes or subnodes)
            // For now, price-based is robust enough for standard XMLs.

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

            // Set Location Taxonomy
            wp_set_object_terms($post_id, (string)$xml->geo->ort, 'ort', true);
        }

        // Infrastructure
        if (isset($xml->infrastruktur)) {
            $infra_data = array();
            foreach ($xml->infrastruktur->distanzen as $dist) {
                $type = (string)$dist['distanz_zu'];
                $val = (string)$dist;
                update_post_meta($post_id, 'distanz_' . strtolower($type), $val);
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

            // Allow manual override in backend (don't overwrite if manual change? - User requirement: "Der intern gespeicherte Status darf vom XML abweichen, wenn im Backend manuell geändert wurde.")
            // Logic: We always update from XML unless we implement a "Lock Status" checkbox. 
            // BUT: The requirement says "soll Status aus OpenImmo XML intelligent interpretieren... Der intern gespeicherte Status darf vom XML abweichen".
            // This usually means: If I set it to "Sold" manually, the next import of "Active" shouldn't overwrite it? 
            // OR: If XML says "Sold", we update. If XML says "Active" but we manually set "Sold", do we keep "Sold"?
            // A safer approach for now: Always update from XML if XML has explicit status. If XML status is "Active" (default/missing), maybe preserve?
            // "Wenn kein Status eindeutig ermittelbar ist → Default „Aktiv“."

            // Let's implement a meta field `_dbw_immo_status_locked` check?
            // For now, let's keep it simple: Update based on XML. If user changes locally, they should lock it or XML should be source of truth.
            // Wait, "Der intern gespeicherte Status darf vom XML abweichen, wenn im Backend manuell geändert wurde." 
            // IMPLICATION: If I change it manually, the import should NOT overwrite it?
            // Implementation: Check if `_dbw_immo_manual_status` flag is set. If so, skip update.

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
            update_post_meta($post_id, 'energiepass_endenergie', (string)$ep->endenergiebedarf);
            update_post_meta($post_id, 'energiepass_traeger', (string)$ep->primaerenergietraeger);
            update_post_meta($post_id, 'energiepass_wertklasse', (string)$ep->wertklasse);
            update_post_meta($post_id, 'energiepass_baujahr', (string)$ep->baujahr);
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
                $keller_attr = (string)$ausstattung->unterkellert['kpiuell']; // JA, NEIN, TEIL
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

        // Object Type Taxonomy
        if (isset($xml->objektkategorie)) {
            // Iterate over children to find the type (e.g. <objektart><haus>...</haus></objektart>)
            // Simplified: Just taking the key name of the detailed type
            if (isset($xml->objektkategorie->objektart)) {
                foreach ($xml->objektkategorie->objektart->children() as $child) {
                    $type_name = $child->getName(); // e.g. 'haus', 'wohnung'
                    wp_set_object_terms($post_id, ucfirst($type_name), 'objektart', true);
                    break;
                }
            }
            if (isset($xml->objektkategorie->nutzungsart)) {
                $usage_attributes = $xml->objektkategorie->nutzungsart->attributes();
                foreach ($usage_attributes as $name => $val) {
                    if ((string)$val === 'true' || (string)$val === '1') {
                        // e.g. WOHNEN or GEWERBE
                        wp_set_object_terms($post_id, ucfirst(strtolower($name)), 'objektart', true);
                    }
                }
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

        if (!$real_full || !$real_base || strpos($real_full, $real_base) !== 0) {
            $this->log_debug(sprintf('Path traversal blocked: %s (base: %s)', $file_name, $base_path));
            return false;
        }

        if (!file_exists($real_full)) {
            return false;
        }

        // Validate file type before uploading
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'svg');
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

        $att_id = media_handle_sideload($file_array, $post_id);

        // Cleanup temp file if sideload failed (WP removes it on success)
        if (is_wp_error($att_id) && file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        if (!is_wp_error($att_id)) {
            update_post_meta($att_id, '_openimmo_filename', $file_name);
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
            $xml_path = isset($options['xml_path']) ? $options['xml_path'] : '';
            $xml_path_raw = $xml_path;
            if (!empty($xml_path) && is_dir($xml_path)) {
                $xml_path = trailingslashit($xml_path);
            }
            elseif (!empty($xml_path) && is_dir(ABSPATH . ltrim($xml_path, '/'))) {
                $xml_path = trailingslashit(ABSPATH . ltrim($xml_path, '/'));
            }
            elseif (empty($xml_path)) {
                $upload_dir = wp_upload_dir();
                $xml_path = $upload_dir['basedir'] . '/openimmo/';
            }
            else {
                wp_send_json_error('Pfad nicht gefunden: ' . $xml_path);
            }

            if (!is_dir($xml_path))
                wp_send_json_error('Verzeichnis existiert nicht: ' . $xml_path);

            $this->log_debug('AJAX Import-Pfad aufgelöst: ' . $xml_path . ' (konfiguriert: ' . $xml_path_raw . ')');

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
                        $zip->extractTo($temp_dir);
                        $zip->close();

                        // Check MD5 Hash
                        $temp_xmls = glob($temp_dir . '*.xml');
                        if (!empty($temp_xmls)) {
                            $hash = '';
                            foreach ($temp_xmls as $xml_file) {
                                $hash .= md5_file($xml_file);
                            }
                            $hash = md5($hash);
                            $last_hash = get_option('dbw_immo_last_xml_hash_' . basename($zip_file), '');

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

            // 3. Collect XML files for JS queue
            $xml_files_data = array();

            // a) From active ZIPs
            foreach ($active_zips as $az) {
                foreach ($az['xmls'] as $xml_file) {
                    $xml = $this->safe_load_xml($xml_file);
                    if ($xml && isset($xml->anbieter->immobilie)) {
                        $count = count($xml->anbieter->immobilie);
                        if ($count > 0) {
                            $xml_files_data[] = array(
                                'file' => $xml_file,
                                'count' => $count
                            );
                        }
                    }
                }
            }

            // b) From loose XML files
            $loose_files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($xml_path));
            foreach ($loose_files as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'xml' && strpos($file->getPathname(), '/tmp_') === false) {
                    $xml = $this->safe_load_xml($file->getPathname());
                    if ($xml && isset($xml->anbieter->immobilie)) {
                        $count = count($xml->anbieter->immobilie);
                        if ($count > 0) {
                            $xml_files_data[] = array(
                                'file' => $file->getPathname(),
                                'count' => $count,
                                'loose' => true
                            );
                        }
                    }
                }
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
                'started'      => time(),
            ), 3600);

            wp_send_json_success(array(
                'files' => $xml_files_data,
                'message' => 'Dateien analysiert. Starte Prozess...'
            ));

        }
        catch (\Throwable $e) {
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
            wp_send_json_success(array('status' => 'idle'));
        }

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

            // Validate file path is within allowed import directory
            $options = get_option('dbw_immo_suite_settings');
            $allowed_base = $this->resolve_import_path($options);
            $real_file = realpath($file);
            $real_base = $allowed_base ? realpath($allowed_base) : false;

            if (!$real_file || !$real_base || strpos($real_file, $real_base) !== 0) {
                wp_send_json_error(__('Ungültiger Dateipfad.', 'dbw-immo-suite'));
            }

            if (!file_exists($real_file))
                wp_send_json_error(__('Datei nicht gefunden.', 'dbw-immo-suite'));

            $this->current_xml_file = $file; // IMPORTANT for images

            $xml = $this->safe_load_xml($file);
            if (!$xml || !isset($xml->anbieter->immobilie[$index])) {
                wp_send_json_error('Immobilie nicht gefunden bei Index ' . $index);
            }

            $node = $xml->anbieter->immobilie[$index];
            $stats = array('created' => 0, 'updated' => 0, 'errors' => 0);

            $this->import_property($node, $stats);

            // Update progress transient for live polling
            $progress = get_transient('dbw_immo_import_progress');
            if (is_array($progress)) {
                $progress['processed']++;
                $progress['current_file'] = basename($file);
                $progress['created']  += $stats['created'];
                $progress['updated']  += $stats['updated'];
                $progress['errors']   += $stats['errors'];
                set_transient('dbw_immo_import_progress', $progress, 3600);
            }

            if ($stats['errors'] > 0) {
                wp_send_json_error('Fehler beim Import der Immobilie Index ' . $index);
            }

            wp_send_json_success(array('message' => 'Immobilie ' . ($index + 1) . ' importiert.'));

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

            // 1. Cleanup ZIPs
            $active_zips = get_transient('dbw_immo_batch_zips');
            if (is_array($active_zips)) {
                foreach ($active_zips as $az) {
                    update_option('dbw_immo_last_xml_hash_' . basename($az['file']), $az['hash']);
                    $this->delete_directory($az['temp_dir']);
                    rename($az['file'], $az['file'] . '.processed');
                    $this->log_debug('ZIP erfolgreich verarbeitet: ' . basename($az['file']));
                    $this->log_history($az['file'], array('created' => 0, 'updated' => 0, 'errors' => 0), 'success');
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
                    if ($real_path && strpos($real_path, realpath($allowed_base)) === 0 && file_exists($real_path)) {
                        rename($real_path, $real_path . '.processed');
                        $this->log_debug('Lose XML-Datei archiviert: ' . basename($real_path));
                    }
                }
            }

            // 3. Garbage Collection
            $options = get_option('dbw_immo_suite_settings');
            if (!empty($options['enable_garbage_collection'])) {
                $batch_ids = get_transient('dbw_immo_batch_processed_ids');
                if (is_array($batch_ids) && !empty($batch_ids)) {
                    // Populate instance variable for the GC run
                    $this->processed_openimmo_ids = $batch_ids;
                    $this->run_garbage_collection();
                }
            }
            delete_transient('dbw_immo_batch_processed_ids');

            // Mark progress as done so polling stops
            $progress = get_transient('dbw_immo_import_progress');
            if (is_array($progress)) {
                $progress['status'] = 'done';
                set_transient('dbw_immo_import_progress', $progress, 60);
            }

            wp_send_json_success('Import und Cleanup erfolgreich abgeschlossen.');
        }
        catch (\Throwable $e) {
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
        $disable_entities = function_exists('libxml_disable_entity_loader');

        if ($disable_entities) {
            $previous_entities = libxml_disable_entity_loader(true);
        }

        $xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);

        if ($disable_entities) {
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
        $log_file = WP_CONTENT_DIR . '/openimmo_import.log';
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

        $archived_count = 0;

        if ($all_properties->have_posts()) {
            $processed_flip = array_flip($this->processed_openimmo_ids);
            foreach ($all_properties->posts as $post_id) {
                $oid = get_post_meta($post_id, 'openimmo_id', true);
                if (!empty($oid) && !isset($processed_flip[$oid])) {
                    // Not in the feed -> Archive it
                    $update_data = array(
                        'ID' => $post_id,
                        'post_status' => 'draft'
                    );
                    wp_update_post($update_data);

                    update_post_meta($post_id, '_dbw_immo_status', 'archiviert');

                    $this->log_debug("Garbage Collection: Immobilie $oid ($post_id) archiviert, da nicht mehr im Feed.");
                    $archived_count++;
                }
            }
        }

        if ($archived_count > 0) {
            $this->log_debug("Garbage Collection abgeschlossen: $archived_count Objekte archiviert.");
        }
    }
}