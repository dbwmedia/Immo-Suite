jQuery(document).ready(function ($) {
    var nonce = (typeof dbwImmoAdmin !== 'undefined') ? dbwImmoAdmin.nonce : '';
    var pollTimer = null;

    // WordPress admin blue while running, a saturated green on success
    var COLOR_RUNNING = '#3858e9';
    var COLOR_DONE    = '#16a34a';
    var COLOR_ERROR   = '#d63638';
    var lastTotal     = 0;

    // --- UI Elements ---
    var $btn       = $('#dbw-immo-trigger-import');
    var $panel     = $('#dbw-immo-progress-panel');
    var $status    = $('#dbw-immo-import-status');
    var $bar       = $('#dbw-immo-progress-bar');
    var $pct       = $('#dbw-immo-progress-pct');
    var $title     = $('#dbw-immo-progress-title');
    var $counter   = $('#dbw-immo-progress-counter');
    var $file      = $('#dbw-immo-progress-file');
    var $spinner   = $('#dbw-immo-progress-spinner');
    var $created   = $('#dbw-stat-created');
    var $updated   = $('#dbw-stat-updated');
    var $errors    = $('#dbw-stat-errors');

    // --- Progress UI helpers ---
    function showProgress(title) {
        $panel.show().css('--dbw-progress-color', COLOR_RUNNING);
        $bar.css('background', COLOR_RUNNING);
        $title.text(title);
        $counter.text('');
        $file.text('');
        $bar.css('width', '0%');
        $pct.text('');
        $created.text('0');
        $updated.text('0');
        $errors.text('0');
        $spinner.addClass('is-active');
        $status.hide();
    }

    function updateProgress(data) {
        if (!data || !data.total) return;

        var pct = Math.round((data.processed / data.total) * 100);
        $bar.css('width', pct + '%');
        $pct.text(pct + '%');
        $counter.text(data.processed + ' / ' + data.total + ' Immobilien');
        $title.text('Importiere...');

        if (data.current_file) {
            var filesInfo = data.total_files > 1
                ? data.total_files + ' Dateien'
                : '1 Datei';
            $file.text('Aktuelle Datei: ' + data.current_file + ' (' + filesInfo + ')');
        }

        $created.text(data.created || 0);
        $updated.text(data.updated || 0);
        $errors.text(data.errors || 0);
    }

    function showDone(data) {
        stopPolling();
        $spinner.removeClass('is-active');
        $panel.css('--dbw-progress-color', COLOR_DONE);
        $bar.css({'width': '100%', 'background': COLOR_DONE});
        $pct.text('100%');
        $title.text('Import abgeschlossen');

        data = data || {};
        // An idle transient has no counters, fall back to what the queue knew
        var processed = (typeof data.processed === 'number') ? data.processed : lastTotal;

        $counter.text(processed === 1
            ? '1 Immobilie verarbeitet'
            : processed + ' Immobilien verarbeitet');
        $created.text(data.created || 0);
        $updated.text(data.updated || 0);
        $errors.text(data.errors || 0);

        $btn.prop('disabled', false).text('Import jetzt starten');
        refreshHistory();
    }

    function showError(msg) {
        stopPolling();
        $spinner.removeClass('is-active');
        $panel.css('--dbw-progress-color', COLOR_ERROR);
        $bar.css('background', COLOR_ERROR);
        $title.text('Fehler');
        var $notice = $('<div class="notice notice-error inline" style="margin-top:12px;"></div>');
        $notice.append($('<p></p>').text(msg));
        $status.empty().append($notice).show();
        $btn.prop('disabled', false).text('Import jetzt starten');
    }

    // --- Polling ---
    function startPolling() {
        stopPolling();
        pollTimer = setInterval(function () {
            $.post(ajaxurl, {
                action: 'dbw_immo_import_progress',
                nonce: nonce
            }, function (response) {
                if (!response.success || !response.data) return;
                var d = response.data;
                if (d.status === 'running') {
                    updateProgress(d);
                } else if (d.status === 'done') {
                    showDone(d);
                } else if (d.status === 'error') {
                    showError(d.error_message || 'Unbekannter Fehler');
                }
            });
        }, 2000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // --- History refresh ---
    function refreshHistory() {
        $.post(ajaxurl, {
            action: 'dbw_immo_refresh_history',
            nonce: nonce
        }, function (response) {
            if (response.success && response.data) {
                // Server response is admin-only, pre-sanitized HTML from ImportDashboard
                var $wrapper = $('#dbw-immo-history-wrapper');
                $wrapper.empty();
                $wrapper[0].innerHTML = response.data;
            }
        });
    }

    // --- Import Flow ---
    $btn.on('click', function (e) {
        e.preventDefault();
        $btn.prop('disabled', true).text('Initialisiere...');
        showProgress('Prüfe Dateien und entpacke ZIPs...');

        // Step 1: Prepare
        $.post(ajaxurl, {
            action: 'dbw_immo_prepare_import',
            nonce: nonce
        }, function (response) {
            if (!response.success) {
                showError(response.data);
                return;
            }

            var files = response.data.files;
            var BATCH_SIZE = 8; // properties per request (server caps at 20)
            var flattenQueue = [];
            var looseFiles = [];
            var total = 0;

            $.each(files, function (i, f) {
                if (f.loose) looseFiles.push(f.file);
                total += f.count;
                for (var j = 0; j < f.count; j += BATCH_SIZE) {
                    flattenQueue.push({ file: f.file, index: j, limit: Math.min(BATCH_SIZE, f.count - j) });
                }
            });
            lastTotal = total;

            if (total === 0) {
                $title.text('Keine neuen Immobilien. Räume auf...');
                finalizeImport(looseFiles, 0);
                return;
            }

            $title.text(total + ' Immobilien gefunden. Starte Import...');
            startPolling();

            // Step 2: Process queue
            processBatchQueue(0, flattenQueue, looseFiles, total);

        }).fail(function (xhr, textStatus, error) {
            showError('Server Fehler: ' + textStatus + ' ' + error);
        });
    });

    function processBatchQueue(currentIdx, queue, looseFiles, total) {
        if (currentIdx >= queue.length) {
            finalizeImport(looseFiles, total);
            return;
        }

        var item = queue[currentIdx];

        $.post(ajaxurl, {
            action: 'dbw_immo_process_batch',
            nonce: nonce,
            file: item.file,
            index: item.index,
            limit: item.limit
        }, function (response) {
            if (!response.success) {
                console.error('Batch Error at index ' + item.index + ': ' + response.data);
            }
            processBatchQueue(currentIdx + 1, queue, looseFiles, total);
        }).fail(function () {
            console.error('Server Failure at index ' + item.index);
            processBatchQueue(currentIdx + 1, queue, looseFiles, total);
        });
    }

    function finalizeImport(looseFiles, totalProcessed) {
        lastTotal = totalProcessed;
        $title.text('Räume auf und führe Garbage Collection aus...');

        $.post(ajaxurl, {
            action: 'dbw_immo_finalize_import',
            nonce: nonce,
            loose_files: looseFiles
        }, function () {
            // showDone is triggered by polling detecting status=done
            // but if polling hasn't caught it yet, force it
            stopPolling();
            $.post(ajaxurl, {
                action: 'dbw_immo_import_progress',
                nonce: nonce
            }, function (response) {
                if (response.success && response.data) {
                    showDone(response.data);
                } else {
                    showDone({ processed: totalProcessed, created: 0, updated: 0, errors: 0 });
                }
            });
        }).fail(function () {
            showError('Fehler beim Aufräumen (Finalize).');
        });
    }

    // --- Dry Run (Vorschau) ---
    var $dryBtn   = $('#dbw-immo-dry-run');
    var $dryPanel = $('#dbw-immo-dryrun-panel');

    var DRY_ACTION_LABELS = {
        neu: ['Neu', '#16a34a'],
        aktualisieren: ['Aktualisieren', '#3858e9'],
        loeschen: ['Löschen', '#d63638'],
        referenz: ['Referenz', '#7e56ff'],
        unbekannt: ['Löschung (unbekanntes Objekt)', '#787c82'],
        fehler: ['Fehler', '#d63638']
    };

    function el(tag, styles, text) {
        var node = document.createElement(tag);
        if (styles) node.style.cssText = styles;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function renderDryRun(data) {
        var panel = $dryPanel[0];
        panel.innerHTML = '';

        var title = el('strong', 'display:block; margin-bottom:10px; font-size:14px;', 'Testlauf: Das würde beim nächsten Import passieren');
        panel.appendChild(title);

        var hasContent = false;

        (data.files || []).forEach(function (f) {
            hasContent = true;
            var head = el('div', 'margin:10px 0 4px; font-weight:600;', f.file + (f.skipped ? ' — unverändert, wird übersprungen' : ''));
            panel.appendChild(head);

            (f.items || []).forEach(function (item) {
                var meta = DRY_ACTION_LABELS[item.action] || [item.action, '#787c82'];
                var row = el('div', 'display:flex; align-items:center; gap:8px; padding:2px 0 2px 12px; font-size:13px;');
                var badge = el('span', 'display:inline-block; min-width:96px; font-weight:600; color:' + meta[1] + ';', meta[0]);
                row.appendChild(badge);
                row.appendChild(el('span', '', item.title));
                panel.appendChild(row);
            });
        });

        (data.notes || []).forEach(function (note) {
            hasContent = true;
            panel.appendChild(el('div', 'margin-top:6px; color:#856404; font-size:13px;', '⚠ ' + note));
        });

        // GC section
        if (data.gc && data.gc.enabled) {
            var gcHead = el('div', 'margin:14px 0 4px; font-weight:600;', 'Garbage Collection');
            panel.appendChild(gcHead);
            if (!data.gc.full_sync) {
                panel.appendChild(el('div', 'padding-left:12px; font-size:13px; color:#50575e;', 'Läuft nicht: Lieferung ist kein Vollabgleich (umfang ≠ VOLL).'));
            } else if (data.gc.brake) {
                panel.appendChild(el('div', 'padding-left:12px; font-size:13px; color:#d63638; font-weight:600;',
                    'NOTBREMSE würde greifen: ' + data.gc.archive.length + ' Objekte wären betroffen — der Import bricht die GC ab und meldet einen Fehler.'));
            } else if (data.gc.archive.length) {
                panel.appendChild(el('div', 'padding-left:12px; font-size:13px; color:#856404;',
                    data.gc.archive.length + ' Objekt(e) würden archiviert:'));
                data.gc.archive.forEach(function (t) {
                    panel.appendChild(el('div', 'padding-left:24px; font-size:13px;', '• ' + t));
                });
            } else {
                panel.appendChild(el('div', 'padding-left:12px; font-size:13px; color:#50575e;', 'Nichts zu archivieren.'));
            }
        }

        if (!hasContent) {
            panel.appendChild(el('div', 'font-size:13px; color:#50575e;', 'Keine neuen Dateien im Import-Verzeichnis.'));
        }

        $dryPanel.show();
    }

    if ($dryBtn.length) {
        $dryBtn.on('click', function () {
            $dryBtn.prop('disabled', true).text('Analysiere...');
            $dryPanel.hide();
            $.post(ajaxurl, { action: 'dbw_immo_dry_run', nonce: nonce }, function (response) {
                $dryBtn.prop('disabled', false).text('Testlauf (Vorschau)');
                if (response.success) {
                    renderDryRun(response.data || {});
                } else {
                    showError(response.data || 'Testlauf fehlgeschlagen.');
                }
            }).fail(function () {
                $dryBtn.prop('disabled', false).text('Testlauf (Vorschau)');
                showError('Server-Fehler beim Testlauf.');
            });
        });
    }

    // --- Log Viewer ---
    var $logDetails = $('#dbw-immo-log-details');
    var $logOutput  = $('#dbw-immo-log-output');
    var $logMeta    = $('#dbw-immo-log-meta');
    var logLoaded   = false;

    function loadLog() {
        $.post(ajaxurl, { action: 'dbw_immo_get_log', nonce: nonce }, function (response) {
            if (response.success && response.data) {
                var lines = response.data.lines || [];
                $logOutput.text(lines.length ? lines.join('\n') : 'Log ist leer.');
                $logMeta.text(lines.length ? 'Letzte ' + lines.length + ' Zeilen · ' + (response.data.size || '') : '');
                $logOutput.scrollTop($logOutput[0].scrollHeight);
            }
        });
    }

    if ($logDetails.length) {
        $logDetails.on('toggle', function () {
            if (this.open && !logLoaded) {
                logLoaded = true;
                loadLog();
            }
        });
        $('#dbw-immo-log-refresh').on('click', loadLog);
    }

    // --- Check if import is already running on page load ---
    if ($panel.length) {
        $.post(ajaxurl, {
            action: 'dbw_immo_import_progress',
            nonce: nonce
        }, function (response) {
            if (response.success && response.data && response.data.status === 'running') {
                $btn.prop('disabled', true).text('Import läuft...');
                showProgress('Import läuft...');
                updateProgress(response.data);
                startPolling();
            }
        });
    }
});
