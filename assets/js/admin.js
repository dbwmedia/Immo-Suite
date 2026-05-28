jQuery(document).ready(function ($) {
    var nonce = (typeof dbwImmoAdmin !== 'undefined') ? dbwImmoAdmin.nonce : '';

    $('#dbw-immo-trigger-import').on('click', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var $status = $('#dbw-immo-import-status');

        $btn.prop('disabled', true).text('Initialisiere...');
        $status.html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Prüfe Dateien und entpacke ZIPs...').show();

        // Step 1: Prepare Import (Scan files, extract ZIPs)
        $.post(ajaxurl, {
            action: 'dbw_immo_prepare_import',
            nonce: nonce
        }, function (response) {
            if (!response.success) {
                $status.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                $btn.prop('disabled', false).text('Import starten');
                return;
            }

            var files = response.data.files;
            var flattenQueue = [];
            var looseFiles = [];

            // Build a flat queue of individual tasks: [ {file: 'path', index: 0}, ... ]
            $.each(files, function (i, f) {
                if (f.loose) {
                    looseFiles.push(f.file);
                }
                for (var j = 0; j < f.count; j++) {
                    flattenQueue.push({
                        file: f.file,
                        index: j
                    });
                }
            });

            var total = flattenQueue.length;
            if (total === 0) {
                $status.html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> ' + response.data.message + ' Räume auf...');
                finalizeImport(looseFiles, $status, $btn, 0);
                return;
            }

            $status.html('<div class="notice notice-info inline"><p>Analyse fertig. ' + total + ' Immobilien gefunden. Starte Batch-Import...</p></div>');

            // Step 2: Process Queue
            processBatchQueue(0, flattenQueue, looseFiles, $status, $btn);

        }).fail(function (xhr, status, error) {
            $status.html('<div class="notice notice-error inline"><p>Server Fehler bei Vorbereitung: ' + status + ' ' + error + '</p></div>');
            $btn.prop('disabled', false).text('Import starten');
        });
    });

    function processBatchQueue(currentIdx, queue, looseFiles, $status, $btn) {
        if (currentIdx >= queue.length) {
            finalizeImport(looseFiles, $status, $btn, queue.length);
            return;
        }

        var item = queue[currentIdx];
        var progress = Math.round(((currentIdx + 1) / queue.length) * 100);

        $status.html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Importiere ' + (currentIdx + 1) + ' von ' + queue.length + ' (' + progress + '%)');

        $.post(ajaxurl, {
            action: 'dbw_immo_process_batch',
            nonce: nonce,
            file: item.file,
            index: item.index
        }, function (response) {
            if (!response.success) {
                console.error("Batch Error at index " + currentIdx + ": " + response.data);
            }
            processBatchQueue(currentIdx + 1, queue, looseFiles, $status, $btn);
        }).fail(function (xhr) {
            console.error("Server Failure at index " + currentIdx);
            processBatchQueue(currentIdx + 1, queue, looseFiles, $status, $btn);
        });
    }

    function finalizeImport(looseFiles, $status, $btn, totalProcessed) {
        $status.html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Räume temporäre Dateien auf und führe Garbage Collection aus...');

        $.post(ajaxurl, {
            action: 'dbw_immo_finalize_import',
            nonce: nonce,
            loose_files: looseFiles
        }, function (response) {
            if (totalProcessed > 0) {
                $status.html('<div class="notice notice-success inline"><p><strong>Import durchgelaufen!</strong> ' + totalProcessed + ' Immobilien verarbeitet. Cleanup abgeschlossen.</p></div>');
            } else {
                var msg = response.success && response.data ? response.data : 'Keine Änderungen vorgenommen.';
                $status.html('<div class="notice notice-success inline"><p><strong>Import beendet.</strong> ' + msg + '</p></div>');
            }
            $btn.prop('disabled', false).text('Import starten');
        }).fail(function () {
            $status.html('<div class="notice notice-error inline"><p>Fehler beim Aufräumen (Finalize).</p></div>');
            $btn.prop('disabled', false).text('Import starten');
        });
    }
});
