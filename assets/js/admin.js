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
