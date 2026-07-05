jQuery(document).ready(function($) {
    var $btnStart = $('#eo-start-detailed-scan');
    var $btnPause = $('#eo-pause-detailed-scan');
    var $btnResume = $('#eo-resume-detailed-scan');
    var $statusText = $('#eo-detailed-scan-status');
    var $console = $('#eo-detailed-scan-console');
    var $progressMaskCircle = $('#eo-detailed-scan-progress-mask-circle');
    var $progressText = $('#eo-detailed-scan-progress-text');
    var $resultsTableBody = $('#eo-detailed-scan-results-table tbody');
    var $countsTable = $('#eo-detailed-scan-counts-table');
    var $resultsTable = $('#eo-detailed-scan-results-table');
    
    var isPaused = false;
    var counts = {};
    var totalItems = 0;
    var scannedItems = 0;
    var startTime = null;

    function formatTime(date) {
        return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute:'2-digit', second:'2-digit' });
    }

    function formatDuration(ms) {
        var totalSeconds = Math.floor(ms / 1000);
        var hours = Math.floor(totalSeconds / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;
        var res = '';
        if (hours > 0) res += hours + 'h ';
        if (minutes > 0 || hours > 0) res += minutes + 'm ';
        res += seconds + 's';
        return res;
    }

    var $btnOpenConsole = $('#eo-detailed-scan-open-console');
    var $floatingConsole = $('#eo-detailed-scan-floating-console');
    var $consoleHeader = $('#eo-floating-console-header');
    var $consoleBody = $('#eo-detailed-scan-console');
    var $consoleIcon = $('#eo-console-toggle-icon');
    var $consoleLastMsg = $('#eo-console-last-msg');

    // Floating console logic
    $btnOpenConsole.on('click', function(e) {
        e.preventDefault();
        $floatingConsole.show();
        $consoleBody.show();
        $consoleIcon.text('â–¼');
        $consoleBody.scrollTop($consoleBody[0].scrollHeight);
    });

    $consoleHeader.on('click', function(e) {
        if ($(e.target).closest('#eo-console-copy, #eo-console-clear').length > 0) return;
        $consoleBody.slideToggle(200, function() {
            if ($consoleBody.is(':visible')) {
                $consoleIcon.text('â–¼');
            } else {
                $consoleIcon.text('â–²');
            }
        });
    });

    $('#eo-console-copy').on('click', function() {
        var text = $consoleBody.text();
        navigator.clipboard.writeText(text).then(function() {
            var $btn = $('#eo-console-copy');
            var oldText = $btn.text();
            $btn.text('Copié !').css('color', '#10b981');
            setTimeout(function() {
                $btn.text(oldText).css('color', '');
            }, 2000);
        });
    });

    $('#eo-console-clear').on('click', function() {
        $consoleBody.empty();
        $consoleLastMsg.text('Dernier : Console vidée');
    });

    // Preload past scan if batch_id is set
    if (typeof eo_tools_preload_batch_id !== 'undefined' && eo_tools_preload_batch_id) {
        $btnStart.hide();
        $progressContainer.show();
        $progressText.text('100%');
        $('#eo-detailed-scan-progress-mask-circle').css('stroke-dashoffset', '0');
        
        logToConsole('Chargement des résultats passés...', 'info');

        $.post(eo_tools_admin_vars.ajaxUrl || ajaxurl, {
            action: 'eo_tools_get_detailed_scan_results',
            security: eo_tools_admin_vars.nonce,
            batch_id: eo_tools_preload_batch_id
        }, function(response) {
            if (response.success && response.data) {
                // Update counts
                for (const type in response.data.counts) {
                    const count = response.data.counts[type];
                    const $row = $countsTable.find('tr[data-type="' + type + '"]');
                    if ($row.length) {
                        $row.find('.count-total').text(count);
                        $row.find('.count-scanned').text(count);
                        $row.find('.count-percent').text('100%');
                    }
                }
                
                // Add items to result table
                if (response.data.items && response.data.items.length > 0) {
                    $resultsTable.find('#eo-detailed-scan-empty-row').hide();
                    response.data.items.forEach(item => {
                        let cookiesArr = [];
                        try {
                            if (item.cookies_found) {
                                cookiesArr = JSON.parse(item.cookies_found);
                            }
                        } catch(e) {}
                        
                        let total = cookiesArr.length;
                        let necessary = 0, analytics = 0, advertising = 0, social = 0, others = 0;
                        
                        cookiesArr.forEach(cName => {
                            const name = cName.toLowerCase();
                            if (name.includes('ga') || name.includes('matomo')) analytics++;
                            else if (name.includes('ads') || name.includes('pixel') || name.includes('fbp')) advertising++;
                            else if (name.includes('tw') || name.includes('li_') || name.includes('social')) social++;
                            else if (name.includes('phpsessid') || name.includes('wordpress')) necessary++;
                            else others++;
                        });
                        
                        let totalHtml = total > 0 ? `<span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px;">${total}</span>` : `<span style="color: #94a3b8;">0</span>`;
                        
                        $resultsTable.find('tbody').append(`
                            <tr style="background-color: ${total > 0 ? '#fef2f2' : 'transparent'}">
                                <td style="word-break: break-all; font-family: monospace; font-size: 12px;">
                                    <a href="${item.url}" target="_blank" style="color: #2563eb; text-decoration: none;">${item.url}</a>
                                    ${total > 0 ? `<div style="margin-top: 5px; font-size: 11px; color: #64748b;">Cookies: ${cookiesArr.join(', ')}</div>` : ''}
                                </td>
                                <td><span style="background: #e2e8f0; padding: 2px 5px; border-radius: 3px; font-size: 10px; text-transform: uppercase;">${item.item_type}</span></td>
                                <td style="text-align: center; font-weight: bold;">${totalHtml}</td>
                                <td style="text-align: center; color: #64748b;">${necessary > 0 ? necessary : '-'}</td>
                                <td style="text-align: center; color: #64748b;">${analytics > 0 ? analytics : '-'}</td>
                                <td style="text-align: center; color: #64748b;">${advertising > 0 ? advertising : '-'}</td>
                                <td style="text-align: center; color: #64748b;">${social > 0 ? social : '-'}</td>
                                <td style="text-align: center; color: #64748b;">${others > 0 ? others : '-'}</td>
                            </tr>
                        `);
                    });
                }
                logToConsole('Résultats chargés avec succès.', 'info');
            } else {
                logToConsole('Erreur lors du chargement des résultats.', 'error');
            }
        });
    }

    function logToConsole(message, type = 'info') {
        var date = new Date();
        var timeStr = date.toTimeString().split(' ')[0];
        var monthStr = date.toLocaleString('en-US', { month: 'short' });
        var dayStr = String(date.getDate()).padStart(2, '0');
        var timestamp = `[${monthStr} ${dayStr} ${timeStr}:${type}]`;
        
        var color = '#10b981'; // default green
        if (type === 'error') color = '#ef4444';
        else if (type === 'warn') color = '#f59e0b';
        
        var $msg = $('<div>').css('color', color).text(`${timestamp} ${message}`);
        $consoleBody.append($msg);
        $consoleBody.scrollTop($consoleBody[0].scrollHeight);

        $consoleLastMsg.text('Dernier : ' + message);

        if (!$floatingConsole.is(':visible')) {
            $floatingConsole.fadeIn();
        }
    }

    function updateProgressBar() {
        if (totalItems === 0) return;
        var percent = Math.round((scannedItems / totalItems) * 100);
        var offset = 377 - (percent / 100) * 377;
        $progressMaskCircle.css('stroke-dashoffset', offset);
        $progressText.text(percent + '%');
    }

    function processBatch() {
        if (isPaused) {
            logToConsole('Scan mis en pause.', 'warn');
            $statusText.text('En pause');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'eo_tools_process_scan_batch',
                security: eo_tools_admin_vars.nonce,
                batch_id: currentBatchId
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.status === 'complete') {
                        logToConsole('Scan terminé avec succès ! Les résultats ont été ajoutés à l\'historique des scans.', 'info');
                        $btnPause.hide();
                        $btnStart.css("display", "inline-flex");
                        $statusText.text('Terminé');
                        $progressMaskCircle.css('stroke-dashoffset', 0);
                        $progressText.text('100%');
                        
                        if (startTime) {
                            var endTime = new Date();
                            $('#eo-scan-time-end').text(formatTime(endTime));
                            $('#eo-scan-time-duration').text(formatDuration(endTime - startTime));
                        }
                        
                        return;
                    }

                    // Mettre à jour les stats
                    scannedItems = parseInt(response.data.scanned) || 0;
                    totalItems = parseInt(response.data.total) || 0;
                    updateProgressBar();

                    // Mise à jour du tableau 1 avec les counts renvoyés
                    if (response.data.scanned_counts) {
                        var sc = response.data.scanned_counts;
                        $('#eo-detailed-scan-counts-table tr[data-type="posts"] .count-scanned').text(sc.posts);
                        $('#eo-detailed-scan-counts-table tr[data-type="pages"] .count-scanned').text(sc.pages);
                        $('#eo-detailed-scan-counts-table tr[data-type="cpts"] .count-scanned').text(sc.cpts);
                        $('#eo-detailed-scan-counts-table tr[data-type="attachments"] .count-scanned').text(sc.attachments);
                        $('#eo-detailed-scan-counts-table tr[data-type="headers_footers"] .count-scanned').text(sc.headers_footers);
                        
                        if (counts.posts > 0) $('#eo-detailed-scan-counts-table tr[data-type="posts"] .count-percent').text(Math.round((sc.posts / counts.posts) * 100) + '%');
                        if (counts.pages > 0) $('#eo-detailed-scan-counts-table tr[data-type="pages"] .count-percent').text(Math.round((sc.pages / counts.pages) * 100) + '%');
                        if (counts.cpts > 0) $('#eo-detailed-scan-counts-table tr[data-type="cpts"] .count-percent').text(Math.round((sc.cpts / counts.cpts) * 100) + '%');
                        if (counts.attachments > 0) $('#eo-detailed-scan-counts-table tr[data-type="attachments"] .count-percent').text(Math.round((sc.attachments / counts.attachments) * 100) + '%');
                        if (counts.headers_footers > 0) $('#eo-detailed-scan-counts-table tr[data-type="headers_footers"] .count-percent').text(Math.round((sc.headers_footers / counts.headers_footers) * 100) + '%');
                    }

                    // Afficher les résultats
                    if (response.data.results && response.data.results.length > 0) {
                        $('#eo-detailed-scan-empty-row').hide();
                        
                        response.data.results.forEach(function(item) {
                            logToConsole(`Scanned URL: ${item.url} (${item.type}) - Cookies: ${item.cookies}`);
                            
                            var typeLabel = item.type === 'page' || item.type === 'post' ? 'Web' : 'BDD';
                            var $tr = $('<tr>').html(`
                                <td><a href="${item.url}" target="_blank">${item.url}</a></td>
                                <td>${typeLabel}</td>
                                <td style="text-align: center;"><strong>${item.cookies}</strong></td>
                                <td style="text-align: center;">-</td>
                                <td style="text-align: center;">-</td>
                                <td style="text-align: center;">-</td>
                                <td style="text-align: center;">-</td>
                                <td style="text-align: center;">-</td>
                            `);
                            $resultsTableBody.prepend($tr);
                        });
                    }

                    // Continue next batch
                    var batchDelay = eo_tools_admin_vars.batch_delay ? parseInt(eo_tools_admin_vars.batch_delay) : 0;
                    if (batchDelay > 0) {
                        setTimeout(processBatch, batchDelay);
                    } else {
                        processBatch();
                    }
                } else {
                    logToConsole('Erreur lors du traitement du lot.', 'error');
                    $btnPause.hide();
                    $btnResume.css("display", "inline-flex");
                    isPaused = true;
                }
            },
            error: function() {
                logToConsole('Erreur de connexion AJAX. Tentative de reprise...', 'error');
                setTimeout(processBatch, 5000); // Retry in 5s
            }
        });
    }

    $btnStart.on('click', function(e) {
        e.preventDefault();

        $btnStart.hide();
        $btnPause.css("display", "inline-flex");
        $btnResume.hide();
        $statusText.text('Initialisation...');
        $resultsTableBody.html('<tr id="eo-detailed-scan-empty-row"><td colspan="8" style="text-align: center;">Scan en cours...</td></tr>');
        $progressMaskCircle.css('stroke-dashoffset', 377);
        $progressText.text('0%');
        
        $consoleBody.html('');
        
        startTime = new Date();
        $('#eo-detailed-scan-timing').show();
        $('#eo-detailed-scan-progress-container').show();
        $('#eo-scan-time-start').text(formatTime(startTime));
        $('#eo-scan-time-end').text('-');
        $('#eo-scan-time-duration').text('-');

        $floatingConsole.show();
        $consoleBody.show();
        $consoleIcon.text('â–¼');
        logToConsole('Démarrage du scan complet...', 'info');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'eo_tools_start_detailed_scan',
                security: eo_tools_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentBatchId = response.data.batch_id;
                    logToConsole('Initialisation terminée. ' + response.data.total + ' éléments trouvés dans la BDD.');
                    counts = response.data.counts;
                    totalItems = response.data.total;
                    scannedItems = 0;
                    
                    // Update table 1 counts
                    $('#eo-detailed-scan-counts-table tr[data-type="posts"] .count-total').text(counts.posts);
                    $('#eo-detailed-scan-counts-table tr[data-type="pages"] .count-total').text(counts.pages);
                    $('#eo-detailed-scan-counts-table tr[data-type="cpts"] .count-total').text(counts.cpts);
                    $('#eo-detailed-scan-counts-table tr[data-type="attachments"] .count-total').text(counts.attachments);
                    $('#eo-detailed-scan-counts-table tr[data-type="headers_footers"] .count-total').text(counts.headers_footers);

                    $statusText.text('Scan en cours...');
                    isPaused = false;
                    processBatch();
                } else {
                    logToConsole('Erreur lors de l\'initialisation du scan.', 'error');
                    $btnStart.css("display", "inline-flex");
                    $btnPause.hide();
                }
            },
            error: function() {
                logToConsole('Erreur de connexion AJAX (Init).', 'error');
                $btnStart.css("display", "inline-flex");
                $btnPause.hide();
            }
        });
    });

    $btnPause.on('click', function(e) {
        e.preventDefault();
        isPaused = true;
        $btnPause.hide();
        $btnResume.css("display", "inline-flex");
    });

    $btnResume.on('click', function(e) {
        e.preventDefault();
        isPaused = false;
        $btnResume.hide();
        $btnPause.css("display", "inline-flex");
        $statusText.text('Reprise du scan...');
        
        $floatingConsole.show();
        $consoleBody.show();
        $consoleIcon.text('â–¼');
        logToConsole('Reprise du scan...', 'info');
        processBatch();
    });

});

