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
				alert(wp.i18n.__('Erreur lors de l\'enregistrement.', 'eo-tools'));
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
						<div style="display: flex; gap: 20px;">
							<div style="width: 150px; color: #64748b; font-weight: 500;">${wp.i18n.__('Description', 'eo-tools')}</div>
							<div style="flex: 1; color: #475569; line-height: 1.5;">${cookie.comment || cookie.description}</div>
						</div>
					</div>
					<div style="display: flex; gap: 15px; align-items: center;">
						<div class="eo-toggle-active" data-id="${cookie.id}" style="cursor: pointer; width: 44px; height: 24px; background: ${cookie.active !== false ? '#10b981' : '#cbd5e1'}; border-radius: 12px; position: relative; transition: background 0.3s; margin-right: 10px;" title="${cookie.active !== false ? wp.i18n.__('Désactiver', 'eo-tools') : wp.i18n.__('Activer', 'eo-tools')}">
							<div style="position: absolute; top: 2px; left: ${cookie.active !== false ? '22px' : '2px'}; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: left 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>
						</div>
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
		
		// 1. Loading UI
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: dashicons-spin 1s infinite linear; margin-top: 3px;"></span> ' + wp.i18n.__('Analyse en cours...', 'eo-tools'));
		
		// Fake delay for UX (1.5 seconds)
		setTimeout(function() {
			try {
				const localCookies = document.cookie.split(';');
				let foundCount = 0;
				let addedCount = 0;
				
				localCookies.forEach(cookieStr => {
					const parts = cookieStr.trim().split('=');
					if (parts.length < 2) return;
					const name = parts[0];
					
					// Check if already in registry
					let exists = false;
					for (const cat in cookieRegistry) {
						if (cookieRegistry[cat] && cookieRegistry[cat].some(c => c.name === name)) {
							exists = true;
							break;
						}
					}
					
					foundCount++;
					if (exists) return; // Skip already registered
					
					// Lookup in DB
					const dbMatch = (openCookieDB && openCookieDB.length > 0) ? openCookieDB.find(c => c.name === name) : null;
					const cat = dbMatch ? mapCategory(dbMatch.category) : 'others';
					
					if (!cookieRegistry[cat] || !Array.isArray(cookieRegistry[cat])) {
						cookieRegistry[cat] = [];
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
				});
				
				// Save Scan History
				const scanResult = {
					date: new Date().toLocaleString(),
					status: 'COMPLETED',
					found: foundCount,
					added: addedCount
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
						alert(wp.i18n.__('Scan terminé.', 'eo-tools') + ' ' + addedCount + ' ' + wp.i18n.__('nouveaux cookies détectés et ajoutés.', 'eo-tools'));
					});
				} else {
					$btn.prop('disabled', false).html(originalHtml);
					alert(wp.i18n.__('Scan terminé. Aucun nouveau cookie détecté.', 'eo-tools'));
				}
			} catch (e) {
				console.error('Scan error:', e);
				$btn.prop('disabled', false).html(originalHtml);
				alert('Erreur lors du scan : ' + e.message);
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
			$tbody.append(`
				<tr>
					<td><strong>${item.date}</strong></td>
					<td><span style="background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">${item.status}</span></td>
					<td>${item.found}</td>
					<td>${item.added}</td>
				</tr>
			`);
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
