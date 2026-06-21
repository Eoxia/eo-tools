jQuery(document).ready(function($) {
	if (typeof window.eoCookieChartData !== 'undefined') {
		const ctx = document.getElementById('eoCookieStatsChart');
		if (ctx) {
			new Chart(ctx, {
				type: 'line',
				data: {
					labels: window.eoCookieChartData.labels,
					datasets: [
						{
							label: 'Requêtes (Vues)',
							data: window.eoCookieChartData.views,
							borderColor: '#94a3b8',
							backgroundColor: 'rgba(148, 163, 184, 0.2)',
							yAxisID: 'y',
							fill: true,
							tension: 0.1
						},
						{
							label: '% de consentement',
							data: window.eoCookieChartData.consent,
							borderColor: '#4b5563',
							backgroundColor: '#4b5563',
							type: 'bar',
							yAxisID: 'y1'
						}
					]
				},
				options: {
					responsive: true,
					interaction: {
						mode: 'index',
						intersect: false,
					},
					scales: {
						y: {
							type: 'linear',
							display: true,
							position: 'left',
							title: {
								display: true,
								text: 'Requêtes'
							},
							min: 0
						},
						y1: {
							type: 'linear',
							display: true,
							position: 'right',
							title: {
								display: true,
								text: '% de consentement'
							},
							min: 0,
							max: 100,
							grid: {
								drawOnChartArea: false,
							},
						},
					}
				}
			});
		}

		$('#eoCookieExportCsv').on('click', function(e) {
			e.preventDefault();
			const data = window.eoCookieChartData;
			let csvContent = "data:text/csv;charset=utf-8,";
			csvContent += "Date,Requetes,% Consentement\n";
			
			for (let i = 0; i < data.labels.length; i++) {
				csvContent += data.labels[i] + "," + data.views[i] + "," + data.consent[i] + "\n";
			}

			const encodedUri = encodeURI(csvContent);
			const link = document.createElement("a");
			link.setAttribute("href", encodedUri);
			link.setAttribute("download", "eo_tools_cookie_stats.csv");
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		});
	}

	// --- Cookie Manager Logic ---
	if (typeof window.eoToolsCookiesAdmin === 'undefined' || $('#eo-cookie-list-container').length === 0) return;

	let cookieRegistry = {};
	let currentCategory = 'strictly-necessary';
	
	const catTitles = {
		'strictly-necessary': wp.i18n.__('Nécessaire', 'eo-tools'),
		'functional': wp.i18n.__('Fonctionnelle', 'eo-tools'),
		'analytics': wp.i18n.__('Analytique', 'eo-tools'),
		'performance': wp.i18n.__('Performance', 'eo-tools'),
		'marketing': wp.i18n.__('Publicité', 'eo-tools'),
		'social': wp.i18n.__('Réseaux sociaux', 'eo-tools'),
		'others': wp.i18n.__('Autres', 'eo-tools')
	};

	const catDescs = {
		'strictly-necessary': wp.i18n.__('Ces cookies sont indispensables au bon fonctionnement du site.', 'eo-tools'),
		'functional': wp.i18n.__('Ces cookies permettent d\'améliorer et de personnaliser les fonctionnalités du site Web.', 'eo-tools'),
		'analytics': wp.i18n.__('Ces cookies nous permettent de déterminer le nombre de visites et les sources du trafic.', 'eo-tools'),
		'performance': wp.i18n.__('Ces cookies sont utilisés pour comprendre et analyser les indices de performance clés du site.', 'eo-tools'),
		'marketing': wp.i18n.__('Ces cookies sont utilisés pour effectuer le suivi des visiteurs au travers des sites Web.', 'eo-tools'),
		'social': wp.i18n.__('Ces cookies sont définis par une série de services de médias sociaux.', 'eo-tools'),
		'others': wp.i18n.__('Ces cookies n\'ont pas encore été catégorisés.', 'eo-tools')
	};

	function loadRegistry() {
		$.post(eoToolsCookiesAdmin.ajaxUrl, {
			action: 'eo_tools_get_cookie_registry',
			security: eoToolsCookiesAdmin.nonce
		}, function(response) {
			if (response.success) {
				cookieRegistry = response.data || {};
				renderSidebarCounts();
				renderCookieList();
			} else {
				alert(wp.i18n.__('Erreur lors du chargement des cookies.', 'eo-tools'));
			}
		});
	}

	function saveRegistry(callback) {
		$.post(eoToolsCookiesAdmin.ajaxUrl, {
			action: 'eo_tools_save_cookie_registry',
			security: eoToolsCookiesAdmin.nonce,
			registry: cookieRegistry
		}, function(response) {
			if (response.success) {
				renderSidebarCounts();
				renderCookieList();
				if (callback) callback();
			} else {
				showNotice(wp.i18n.__('Erreur lors de l\'enregistrement.', 'eo-tools'), 'error');
			}
		});
	}

	function generateId() {
		return Math.random().toString(36).substring(2, 9);
	}

	function renderSidebarCounts() {
		$('.eo-cookie-cat-item').each(function() {
			const cat = $(this).data('cat');
			const count = cookieRegistry[cat] ? cookieRegistry[cat].length : 0;
			$(this).find('.count').text(`(${count})`);
		});
	}

	// --- Validation Modal Logic ---
	let currentValidationTimestamp = 0;
	let currentValidationDate = '';
	let currentValidationNames = [];
	
	$(document).on('click', '.eo-validate-scan-btn', function() {
		currentValidationTimestamp = $(this).data('timestamp') || 0;
		currentValidationDate = $(this).data('date') || '';
		const namesStr = $(this).data('names');
		currentValidationNames = namesStr ? namesStr.split(',') : [];
		
		const $list = $('#eo-scan-validation-list');
		$list.empty();
		
		if (currentValidationNames.length > 0) {
			currentValidationNames.forEach(name => {
				$list.append(`<li>${name}</li>`);
			});
		} else {
			$list.append(`<li>${wp.i18n.__('Aucun nom disponible', 'eo-tools')}</li>`);
		}
		
		$('#eo-scan-validation-modal').css('display', 'flex');
	});
	
	$('#eo-scan-validation-cancel').on('click', function() {
		$('#eo-scan-validation-modal').hide();
	});
	
	$('#eo-scan-validation-confirm').on('click', function() {
		const $btn = $(this);
		const originalText = $btn.text();
		
		$btn.prop('disabled', true).text(wp.i18n.__('Validation...', 'eo-tools'));
		
		$.post(eoToolsCookiesAdmin.ajaxUrl, {
			action: 'eo_tools_validate_scan',
			security: eoToolsCookiesAdmin.nonce,
			timestamp: currentValidationTimestamp,
			date: currentValidationDate,
			names: currentValidationNames
		}, function(response) {
			if (response.success) {
				$('#eo-scan-validation-modal').hide();
				// Assume showNotice is defined elsewhere or replaces existing message
				if (typeof showNotice !== 'undefined') showNotice(wp.i18n.__('Cookies validés avec succès et journalisés dans le rapport de consentements.', 'eo-tools'));
				if (typeof renderScanHistory !== 'undefined') renderScanHistory(response.data);
			} else {
				if (typeof showNotice !== 'undefined') {
					showNotice(wp.i18n.__('Erreur lors de la validation : ', 'eo-tools') + (response.data || ''), 'error');
				} else {
					alert(wp.i18n.__('Erreur lors de la validation.', 'eo-tools'));
				}
			}
		}).fail(function() {
			if (typeof showNotice !== 'undefined') {
				showNotice(wp.i18n.__('Erreur serveur lors de la validation.', 'eo-tools'), 'error');
			} else {
				alert(wp.i18n.__('Erreur serveur lors de la validation.', 'eo-tools'));
			}
		}).always(function() {
			$btn.prop('disabled', false).text(originalText);
		});
	});

	function renderCookieList() {
		$('#eo-cookie-current-cat-title').text(catTitles[currentCategory]);
		$('#eo-cookie-current-cat-desc').text(catDescs[currentCategory]);

		const $container = $('#eo-cookie-items');
		$container.empty();

		const cookies = cookieRegistry[currentCategory] || [];

		if (cookies.length === 0) {
			$container.html(`<p style="color: #64748b; font-style: italic;">${wp.i18n.__('Aucun cookie défini pour cette catégorie.', 'eo-tools')}</p>`);
			return;
		}

		cookies.forEach(cookie => {
			const isStandard = openCookieDB.some(c => c.name === cookie.name);
			const $item = $(`
				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-start; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<div style="flex: 1;">
						<div style="display: flex; gap: 20px; margin-bottom: 15px;">
							<div style="width: 150px; color: #64748b; font-weight: 500;">Cookie</div>
							<div style="font-family: monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #334155;">${cookie.name}</div>
						</div>
						<div style="display: flex; gap: 20px; margin-bottom: 15px;">
							<div style="width: 150px; color: #64748b; font-weight: 500;">${wp.i18n.__('Domaine', 'eo-tools')}</div>
							<div style="color: #334155;">${cookie.domain || ''}</div>
						</div>
						<div style="display: flex; gap: 20px; margin-bottom: 15px;">
							<div style="width: 150px; color: #64748b; font-weight: 500;">${wp.i18n.__('Durée', 'eo-tools')}</div>
							<div style="color: #334155;">${cookie.date || cookie.duration} ${wp.i18n.__('jours', 'eo-tools')}</div>
						</div>
						<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px;">
							<h4 style="margin: 0; font-size: 1rem;">${cookie.name}</h4>
							<label class="eo-toggle" style="display: inline-block; position: relative; width: 44px; height: 24px; flex-shrink: 0;" title="${cookie.active ? wp.i18n.__('Désactiver ce cookie', 'eo-tools') : wp.i18n.__('Activer ce cookie', 'eo-tools')}">
								<input type="checkbox" class="eo-cookie-active-toggle" data-cat="${currentCategory}" data-id="${cookie.id}" ${cookie.active ? 'checked' : ''} style="opacity: 0; width: 0; height: 0; position: absolute;">
								<span class="eo-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: ${cookie.active ? '#10b981' : '#cbd5e1'}; transition: .3s; border-radius: 34px;">
									<span class="eo-knob" style="position: absolute; content: ''; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; transform: ${cookie.active ? 'translateX(20px)' : 'translateX(0)'}; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></span>
								</span>
							</label>
						</div>
						<div style="font-size: 12px; color: #64748b; margin-bottom: 8px; display: grid; grid-template-columns: 100px 1fr; gap: 4px;">
							<strong>${wp.i18n.__('Domaine', 'eo-tools')}</strong> <span>${cookie.domain || wp.i18n.__('Géré localement', 'eo-tools')}</span>
							<strong>${wp.i18n.__('Durée', 'eo-tools')}</strong> <span>${cookie.date} ${wp.i18n.__('jours', 'eo-tools')}</span>
							${cookie.date > 395 ? `<div style="grid-column: 1 / -1; margin-top: 5px; color: #dc2626; background: #fef2f2; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px;"><span class="dashicons dashicons-warning" style="font-size: 14px; width: 14px; height: 14px; margin-top: 1px;"></span> ${wp.i18n.__('Attention : ce cookie dépasse la limite légale de conservation des 13 mois dictée par la CNIL.', 'eo-tools')}</div>` : ''}
						</div>
						<p style="margin: 0; font-size: 13px; color: #334155;">${cookie.comment}</p>
					</div>
					<div style="display: flex; gap: 15px; align-items: center; margin-left: 20px;">
						<button type="button" class="button-link eo-edit-cookie" data-id="${cookie.id}" title="${wp.i18n.__('Modifier', 'eo-tools')}" style="color: #64748b; padding: 0;">
							<span class="dashicons dashicons-edit" style="font-size: 22px; width: 22px; height: 22px;"></span>
						</button>
						${isStandard ? `
						<button type="button" class="button-link" title="${wp.i18n.__('Cookie standard (suppression impossible)', 'eo-tools')}" style="color: #e2e8f0; padding: 0; cursor: not-allowed;">
							<span class="dashicons dashicons-trash" style="font-size: 22px; width: 22px; height: 22px;"></span>
						</button>
						` : `
						<button type="button" class="button-link eo-delete-cookie" data-id="${cookie.id}" title="${wp.i18n.__('Supprimer', 'eo-tools')}" style="color: #ef4444; padding: 0;">
							<span class="dashicons dashicons-trash" style="font-size: 22px; width: 22px; height: 22px;"></span>
						</button>
						`}
					</div>
				</div>
			`);
			$container.append($item);
		});
	}

	// Change category
	$('.eo-cookie-cat-item').on('click', function() {
		$('.eo-cookie-cat-item').removeClass('active').css('background', 'transparent');
		$(this).addClass('active').css('background', '#f8fafc');
		currentCategory = $(this).data('cat');
		renderCookieList();
	});

	// Open Add Modal
	$('#eo-add-cookie-btn').on('click', function() {
		$('#eo-cookie-form')[0].reset();
		$('#eo-cookie-id').val('');
		$('#eo-cookie-old-cat').val('');
		$('#eo-cookie-cat').val(currentCategory);
		$('#eo-cookie-active').prop('checked', true);
		$('#eo-cookie-domain').val('');
		$('#eo-cookie-modal-title').text(wp.i18n.__('Ajouter un cookie', 'eo-tools'));
		$('#eo-cookie-modal').css('display', 'flex');
	});

	// Close Modal
	$('#eo-cookie-modal-cancel').on('click', function(e) {
		e.preventDefault();
		$('#eo-cookie-modal').hide();
	});

	// Submit Form
	$('#eo-cookie-form').on('submit', function(e) {
		e.preventDefault();
		
		const id = $('#eo-cookie-id').val() || generateId();
		const oldCat = $('#eo-cookie-old-cat').val();
		const newCat = $('#eo-cookie-cat').val();
		const active = $('#eo-cookie-active').is(':checked');
		const name = $('#eo-cookie-name').val();
		const domain = $('#eo-cookie-domain').val();
		const date = $('#eo-cookie-duration').val();
		const comment = $('#eo-cookie-desc').val();

		const cookieData = { id, name, domain, date, comment, active };

		// If editing and category changed, remove from old category
		if (oldCat && oldCat !== newCat && cookieRegistry[oldCat]) {
			cookieRegistry[oldCat] = cookieRegistry[oldCat].filter(c => c.id !== id);
		}

		if (!cookieRegistry[newCat]) {
			cookieRegistry[newCat] = [];
		}

		const existingIndex = cookieRegistry[newCat].findIndex(c => c.id === id);
		if (existingIndex > -1) {
			cookieRegistry[newCat][existingIndex] = cookieData;
		} else {
			cookieRegistry[newCat].push(cookieData);
		}

		saveRegistry(() => {
			$('#eo-cookie-modal').hide();
		});
	});

	// Edit Cookie
	$(document).on('click', '.eo-edit-cookie', function() {
		const id = $(this).data('id');
		const cookies = cookieRegistry[currentCategory] || [];
		const cookie = cookies.find(c => c.id === id);
		
		if (cookie) {
			$('#eo-cookie-id').val(cookie.id);
			$('#eo-cookie-old-cat').val(currentCategory);
			$('#eo-cookie-cat').val(currentCategory);
			$('#eo-cookie-active').prop('checked', cookie.active !== false);
			$('#eo-cookie-name').val(cookie.name);
			$('#eo-cookie-domain').val(cookie.domain || '');
			$('#eo-cookie-duration').val(cookie.date || cookie.duration);
			$('#eo-cookie-desc').val(cookie.comment || cookie.description);
			$('#eo-cookie-modal-title').text(wp.i18n.__('Modifier un cookie', 'eo-tools'));
			$('#eo-cookie-modal').css('display', 'flex');
		}
	});

	// Delete Cookie
	$(document).on('click', '.eo-delete-cookie', function() {
		const id = $(this).data('id');
		cookieRegistry[currentCategory] = cookieRegistry[currentCategory].filter(c => c.id !== id);
		saveRegistry();
	});

	// Toggle Active status inline
	$(document).on('click', '.eo-toggle-active', function() {
		const id = $(this).data('id');
		const cookies = cookieRegistry[currentCategory] || [];
		const cookie = cookies.find(c => c.id === id);
		if (cookie) {
			cookie.active = (cookie.active === false) ? true : false;
			saveRegistry();
		}
	});

	// --- Open Cookie Database & Scanner Logic ---
	let openCookieDB = [];
	
	// Load the database
	$.getJSON(eoToolsCookiesAdmin.pluginUrl + 'assets/data/open-cookie-database.json', function(data) {
		if (data && data[0] && data[0].knowledges) {
			openCookieDB = data[0].knowledges;
		}
	});

	// Helper to map DB category to our category
	function mapCategory(dbCat) {
		const mapping = {
			'functional': 'functional',
			'analytics': 'analytics',
			'marketing': 'marketing',
			'social_media': 'social',
			'performance': 'performance'
		};
		return mapping[dbCat] || 'others';
	}

	// Helper to parse Retention text to days
	function parseRetention(text) {
		text = (text || '').toLowerCase();
		if (text.includes('session')) return 0;
		if (text.includes('year')) {
			const match = text.match(/(\d+)/);
			return match ? parseInt(match[1], 10) * 365 : 365;
		}
		if (text.includes('month')) {
			const match = text.match(/(\d+)/);
			return match ? parseInt(match[1], 10) * 30 : 30;
		}
		if (text.includes('day')) {
			const match = text.match(/(\d+)/);
			return match ? parseInt(match[1], 10) : 1;
		}
		return 365; // default
	}

	// Autocomplete Search
	$('#eo-cookie-search-db').on('input', function() {
		const query = $(this).val().trim().toLowerCase();
		$('.eo-cookie-autocomplete-list').remove();
		
		if (query.length < 2 || openCookieDB.length === 0) return;
		
		const results = openCookieDB.filter(c => c.name.toLowerCase().includes(query)).slice(0, 10);
		if (results.length === 0) return;
		
		const $list = $('<div class="eo-cookie-autocomplete-list" style="position: absolute; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 5px; width: 300px; max-height: 250px; overflow-y: auto;"></div>');
		
		results.forEach(res => {
			const $item = $(`<div style="padding: 10px; border-bottom: 1px solid #f1f1f1; cursor: pointer;">
				<strong>${res.name}</strong> <span style="color: #64748b; font-size: 11px;">(${res.domain || '1st party'})</span>
				<div style="font-size: 11px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${res.comment || ''}</div>
			</div>`);
			
			$item.on('mouseover', function() { $(this).css('background', '#f8fafc'); });
			$item.on('mouseout', function() { $(this).css('background', 'transparent'); });
			
			$item.on('click', function() {
				// Pre-fill modal
				currentCategory = mapCategory(res.category);
				$('.eo-cookie-cat-item').removeClass('active').css('background', 'transparent');
				$(`.eo-cookie-cat-item[data-cat="${currentCategory}"]`).addClass('active').css('background', '#f8fafc');
				renderCookieList();
				
				$('#eo-cookie-form')[0].reset();
				$('#eo-cookie-id').val('');
				$('#eo-cookie-old-cat').val('');
				$('#eo-cookie-cat').val(currentCategory);
				$('#eo-cookie-active').prop('checked', true);
				$('#eo-cookie-name').val(res.name);
				$('#eo-cookie-domain').val(res.domain || '');
				$('#eo-cookie-duration').val(parseRetention(res.date));
				$('#eo-cookie-desc').val(res.comment || '');
				$('#eo-cookie-modal-title').text(wp.i18n.__('Ajouter un cookie (depuis la base)', 'eo-tools'));
				$('#eo-cookie-modal').css('display', 'flex');
				
				$('#eo-cookie-search-db').val('');
				$('.eo-cookie-autocomplete-list').remove();
			});
			$list.append($item);
		});
		
		// Insert absolute relative to parent
		$(this).parent().css('position', 'relative').append($list);
		$list.css({ top: '100%', left: 0, right: 0, width: 'auto' });
	});
	
	// Close autocomplete on outside click
	$(document).on('click', function(e) {
		if (!$(e.target).closest('#eo-cookie-search-db, .eo-cookie-autocomplete-list').length) {
			$('.eo-cookie-autocomplete-list').remove();
		}
	});

	// Local Scanner
	$('#eo-scan-cookies-btn').on('click', function() {
		const $btn = $(this);
		const originalHtml = $btn.html();
		
		// 1. Loading UI on button
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: dashicons-spin 1s infinite linear; margin-top: 3px;"></span> ' + wp.i18n.__('Analyse en cours...', 'eo-tools'));
		
		// 2. Add temporary line in History table
		const scanDate = new Date().toLocaleString();
		const tempId = 'scan-' + Date.now();
		const $tbody = $('#eo-scan-history-list');
		
		// Remove empty state if present
		if ($tbody.find('td[colspan="4"]').text().includes(wp.i18n.__('Aucun scan effectué', 'eo-tools')) || $tbody.find('td[colspan="4"]').text().includes(wp.i18n.__('Chargement', 'eo-tools'))) {
			$tbody.empty();
		}
		
		$tbody.prepend(`
			<tr id="${tempId}" style="background-color: #f8fafc;">
				<td><strong>${scanDate}</strong></td>
				<td><span style="background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;"><span class="dashicons dashicons-update" style="animation: dashicons-spin 1s infinite linear; font-size: 14px; width: 14px; height: 14px; margin-right: 4px; vertical-align: text-top;"></span>${wp.i18n.__('EN COURS', 'eo-tools')}</span></td>
				<td>-</td>
				<td>-</td>
			</tr>
		`);

		// Fake delay for UX (1.5 seconds)
		setTimeout(function() {
			try {
				const localCookies = document.cookie.split(';');
				let foundCount = 0;
				let addedCount = 0;
				let addedNames = [];
				
				localCookies.forEach(cookieStr => {
					const parts = cookieStr.trim().split('=');
					if (parts.length < 2) return;
					const name = parts[0];
					
					// Check if already in registry
					let exists = false;
					for (const cat in cookieRegistry) {
						if (cookieRegistry[cat]) {
							const arr = Array.isArray(cookieRegistry[cat]) ? cookieRegistry[cat] : Object.values(cookieRegistry[cat]);
							if (arr.some(c => c.name === name)) {
								exists = true;
								break;
							}
						}
					}
					
					foundCount++;
					if (exists) return; // Skip already registered
					
					// Lookup in DB
					const dbMatch = (openCookieDB && openCookieDB.length > 0) ? openCookieDB.find(c => c.name === name) : null;
					const cat = dbMatch ? mapCategory(dbMatch.category) : 'others';
					
					if (!cookieRegistry[cat]) {
						cookieRegistry[cat] = [];
					} else if (!Array.isArray(cookieRegistry[cat])) {
						cookieRegistry[cat] = Object.values(cookieRegistry[cat]);
					}
					
					cookieRegistry[cat].push({
						id: generateId(),
						name: name,
						domain: dbMatch ? dbMatch.domain : '',
						date: dbMatch ? parseRetention(dbMatch.date) : 365,
						comment: dbMatch ? dbMatch.comment : wp.i18n.__('Détecté automatiquement lors du scan.', 'eo-tools'),
						active: true
					});
					addedCount++;
					addedNames.push(name);
				});
				
				// Save Scan History
				const scanResult = {
					date: scanDate,
					timestamp: Date.now(),
					status: 'COMPLETED',
					found: foundCount,
					added: addedCount,
					addedNames: addedNames
				};
				
				$.post(eoToolsCookiesAdmin.ajaxUrl, {
					action: 'eo_tools_save_scan_result',
					security: eoToolsCookiesAdmin.nonce,
					result: JSON.stringify(scanResult)
				}, function(res) {
					if (res.success) {
						renderScanHistory(res.data);
					}
				});
				
				if (addedCount > 0) {
					saveRegistry(() => {
						$btn.prop('disabled', false).html(originalHtml);
					});
				} else {
					$btn.prop('disabled', false).html(originalHtml);
				}
			} catch (e) {
				console.error('Scan error:', e);
				$btn.prop('disabled', false).html(originalHtml);
				
				const errorResult = {
					date: scanDate,
					timestamp: Date.now(),
					status: 'FAILED',
					found: 0,
					added: 0,
					addedNames: [],
					error: e.message
				};
				
				// Optional: Save failed scan to DB to persist the error in history
				$.post(eoToolsCookiesAdmin.ajaxUrl, {
					action: 'eo_tools_save_scan_result',
					security: eoToolsCookiesAdmin.nonce,
					result: JSON.stringify(errorResult)
				}, function(res) {
					if (res.success) {
						renderScanHistory(res.data);
					}
				});
			}
		}, 1500);
	});

	// Scan History Loading
	function loadScanHistory() {
		$.post(eoToolsCookiesAdmin.ajaxUrl, {
			action: 'eo_tools_get_scan_history',
			security: eoToolsCookiesAdmin.nonce
		}, function(response) {
			if (response.success) {
				renderScanHistory(response.data);
			}
		});
	}
	
	function renderScanHistory(historyArray) {
		const $tbody = $('#eo-scan-history-list');
		if (!$tbody.length) return; // If we are not on the cookies tab
		
		$tbody.empty();
		
		if (!historyArray || historyArray.length === 0) {
			$tbody.html(`<tr><td colspan="4" style="text-align: center; color: #64748b; font-style: italic; padding: 15px;">${wp.i18n.__('Aucun scan effectué.', 'eo-tools')}</td></tr>`);
			return;
		}
		
		historyArray.forEach(item => {
			if (item.status === 'FAILED' || item.status === 'ERREUR') {
				$tbody.append(`
					<tr>
						<td><strong>${item.date}</strong></td>
						<td><span style="background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">${wp.i18n.__('ERREUR', 'eo-tools')}</span></td>
						<td colspan="2" style="color: #dc2626;">${item.error || wp.i18n.__('Erreur inconnue', 'eo-tools')}</td>
					</tr>
				`);
			} else {
				let addedHtml = item.added;
				if (item.added > 0 && item.addedNames && item.addedNames.length > 0) {
					addedHtml = `<span title="${item.addedNames.join(', ')}" style="cursor: help; border-bottom: 1px dotted #64748b;">${item.added}</span>`;
				}
				
				$tbody.append(`
					<tr>
						<td><strong>${item.date}</strong></td>
						<td><span style="background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">${item.status}</span></td>
						<td>${item.found}</td>
						<td>${addedHtml}</td>
					</tr>
				`);
				
				// Show moderation notice if cookies were added
				if (item.added > 0 && item === historyArray[0]) { // Only for the most recent scan
					if (item.validated) {
						$tbody.append(`
							<tr style="background: #f0fdf4;">
								<td colspan="4" style="color: #166534; padding: 10px 15px; font-size: 13px; font-style: italic;">
									<span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 16px; margin-top: 1px; width: 16px; height: 16px;"></span>
									<strong>${wp.i18n.sprintf(wp.i18n.__('Validé le %s', 'eo-tools'), item.validatedDate || item.date)}</strong> - <a href="?page=eo-tools-cookies&tab=report" style="color: #166534; text-decoration: underline;">${wp.i18n.__('Voir le rapport de consentements', 'eo-tools')}</a>
								</td>
							</tr>
						`);
					} else {
						const namesData = (item.addedNames && item.addedNames.length > 0) ? item.addedNames.join(',') : '';
						$tbody.append(`
							<tr style="background: #fefce8;">
								<td colspan="4" style="color: #854d0e; padding: 10px 15px; font-size: 13px; font-style: italic; display: flex; justify-content: space-between; align-items: center;">
									<div>
										<span class="dashicons dashicons-warning" style="color: #eab308; font-size: 16px; margin-top: 1px; width: 16px; height: 16px;"></span>
										<strong>${wp.i18n.__('Modération requise :', 'eo-tools')}</strong> ${wp.i18n.sprintf(wp.i18n.__('Le scanner Eoxia a détecté %d nouveaux cookies. Veuillez valider leur catégorie et utilité avant publication.', 'eo-tools'), item.added)}
									</div>
									<button type="button" class="button button-primary eo-validate-scan-btn" data-timestamp="${item.timestamp || 0}" data-date="${item.date}" data-names="${namesData}" style="font-size: 12px; padding: 0 10px; min-height: 26px; line-height: 24px;">${wp.i18n.__('Valider ces cookies', 'eo-tools')}</button>
								</td>
							</tr>
						`);
					}
				}
			}
		});
	}

	// Add basic CSS animation for the spinner
	if (!$('#eo-spin-style').length) {
		$('head').append('<style id="eo-spin-style">@keyframes dashicons-spin { 100% { transform: rotate(360deg); } }</style>');
	}

	// Initial load
	loadRegistry();
	loadScanHistory();
});
