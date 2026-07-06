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
	if (typeof window.eoToolsCookiesAdmin === 'undefined') return;

	let cookieRegistry = {};
	let currentSortCol = 'name';
	let currentSortDir = 'asc';
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
				if ($('#eo-cookie-list-container').length) {
					renderCookieTable();
				}
			} else {
				alert(wp.i18n.__('Erreur lors du chargement des cookies.', 'eo-tools'));
			}
		});
	}

	function saveRegistry(changeLog, callback) {
		if (typeof changeLog === 'function') {
			callback = changeLog;
			changeLog = null;
		}
		
		$.post(eoToolsCookiesAdmin.ajaxUrl, {
			action: 'eo_tools_save_cookie_registry',
			security: eoToolsCookiesAdmin.nonce,
			registry: cookieRegistry,
			change_log: changeLog || []
		}, function(response) {
			if (response.success) {
				if ($('#eo-cookie-list-container').length) {
					renderCookieTable();
				}
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
	let globalHistoryArray = [];
	
	$(document).on('click', '.eo-validate-scan-btn', function() {
		currentValidationTimestamp = $(this).data('timestamp') || 0;
		currentValidationDate = $(this).data('date') || '';
		const addNamesStr = $(this).data('added-names') || '';
		const delNamesStr = $(this).data('deleted-names') || '';
		currentValidationNames = [];
		if (addNamesStr) addNamesStr.split(',').forEach(n => currentValidationNames.push('+ ' + n));
		if (delNamesStr) delNamesStr.split(',').forEach(n => currentValidationNames.push('- ' + n));
		
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
		
		if (currentValidationTimestamp === 'manual') {
			const cookiesToAdd = window.eoManualBulkAddCookies || [];
			cookiesToAdd.forEach(name => {
				let foundInDB = false;
				if (window.openCookieDB) {
					const match = window.openCookieDB.find(c => c.name.toLowerCase() === name.toLowerCase());
					if (match) {
						let cat = match.category;
						if (cat === 'Analytics') cat = 'analytics';
						else if (cat === 'Marketing') cat = 'marketing';
						else if (cat === 'Social') cat = 'social';
						else if (cat === 'Functional') cat = 'functional';
						else cat = 'others';
						
						if (!cookieRegistry[cat]) cookieRegistry[cat] = [];
						if (!Array.isArray(cookieRegistry[cat])) cookieRegistry[cat] = Object.values(cookieRegistry[cat]);
						
						if (!cookieRegistry[cat].some(c => c.name === match.name)) {
							cookieRegistry[cat].push({
								name: match.name,
								domain: match.domain || '',
								duration: match.retention || '0',
								description: match.description || ''
							});
						}
						foundInDB = true;
					}
				}
				if (!foundInDB) {
					if (!cookieRegistry['functional']) cookieRegistry['functional'] = [];
					if (!Array.isArray(cookieRegistry['functional'])) cookieRegistry['functional'] = Object.values(cookieRegistry['functional']);
					
					if (!cookieRegistry['functional'].some(c => c.name === name)) {
						cookieRegistry['functional'].push({
							name: name,
							domain: '',
							duration: '0',
							description: 'Ajout manuel'
						});
					}
				}
			});
			saveRegistry();
			$('#eo-scan-validation-modal').hide();
			$('#eo-cookie-search-db').val('');
			showNotice(wp.i18n.__('Cookies ajoutés avec succès !', 'eo-tools'));
			$btn.prop('disabled', false).text(originalText);
			return;
		}

		// Apply modifications to registry
		const scanToValidate = globalHistoryArray.find(item => item.timestamp == currentValidationTimestamp);
		if (scanToValidate) {
			if (scanToValidate.deletedNames && scanToValidate.deletedNames.length > 0) {
				scanToValidate.deletedNames.forEach(dName => {
					for (const cat in cookieRegistry) {
						if (cookieRegistry[cat]) {
							const arr = Array.isArray(cookieRegistry[cat]) ? cookieRegistry[cat] : Object.values(cookieRegistry[cat]);
							cookieRegistry[cat] = arr.filter(c => c.name !== dName);
						}
					}
				});
			}
			if (scanToValidate.addedCookies && scanToValidate.addedCookies.length > 0) {
				scanToValidate.addedCookies.forEach(item => {
					if (!cookieRegistry[item.cat]) cookieRegistry[item.cat] = [];
					else if (!Array.isArray(cookieRegistry[item.cat])) cookieRegistry[item.cat] = Object.values(cookieRegistry[item.cat]);
					
					// Avoid duplicates
					if (!cookieRegistry[item.cat].some(c => c.name === item.cookie.name)) {
						cookieRegistry[item.cat].push(item.cookie);
					}
				});
			}
		}

		saveRegistry(() => {
			$.post(eoToolsCookiesAdmin.ajaxUrl, {
				action: 'eo_tools_validate_scan',
				security: eoToolsCookiesAdmin.nonce,
				timestamp: currentValidationTimestamp,
				date: currentValidationDate,
				names: currentValidationNames
			}, function(response) {
				if (response.success) {
					$('#eo-scan-validation-modal').hide();
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
	});

	function renderCookieTable() {
		const $container = $('#eo-cookie-list-container');
		if (!$container.length) return;

		let allCookies = [];
		for (const cat in cookieRegistry) {
			if (cookieRegistry[cat] && Array.isArray(cookieRegistry[cat])) {
				cookieRegistry[cat].forEach(c => {
					if (c && c.name) {
						c.catRaw = cat;
						c.catTitle = catTitles[cat] || cat;
						allCookies.push(c);
					}
				});
			}
		}

		if (allCookies.length === 0) {
			$container.html(`<p style="color: #64748b; font-style: italic; padding: 20px; text-align: center;">${wp.i18n.__('Aucun cookie défini pour le moment.', 'eo-tools')}</p>`);
			return;
		}

		// Sort logic
		allCookies.sort((a, b) => {
			let valA = '', valB = '';
			switch(currentSortCol) {
				case 'category': valA = a.catTitle; valB = b.catTitle; break;
				case 'name': valA = a.name; valB = b.name; break;
				case 'domain': valA = a.domain || ''; valB = b.domain || ''; break;
				case 'duration': valA = parseInt(a.date || a.duration || 0); valB = parseInt(b.date || b.duration || 0); break;
				case 'comment': valA = a.comment || ''; valB = b.comment || ''; break;
			}
			
			if (typeof valA === 'string') {
				valA = valA.toLowerCase();
				valB = valB.toLowerCase();
			}
			
			if (valA < valB) return currentSortDir === 'asc' ? -1 : 1;
			if (valA > valB) return currentSortDir === 'asc' ? 1 : -1;
			return 0;
		});

		let rowsHtml = '';
		allCookies.forEach(cookie => {
			
			rowsHtml += `
				<tr style="background: #fff;">
					<td style="vertical-align: middle;">${cookie.catTitle}</td>
					<td style="vertical-align: middle;"><strong>${cookie.name}</strong></td>
					<td style="vertical-align: middle;">${cookie.domain || wp.i18n.__('Géré localement', 'eo-tools')}</td>
					<td style="vertical-align: middle;">${cookie.date || cookie.duration} ${wp.i18n.__('jours', 'eo-tools')}</td>
					<td style="vertical-align: middle; font-size: 13px; color: #475569;">${cookie.comment || cookie.description || ''}</td>
					<td style="vertical-align: middle; text-align: right;">
						<button type="button" class="button-link eo-edit-cookie" data-cat="${cookie.catRaw}" data-id="${cookie.id}" title="${wp.i18n.__('Modifier', 'eo-tools')}" style="color: #64748b; padding: 0; margin-right: 10px;">
							<span class="dashicons dashicons-edit" style="font-size: 20px; width: 20px; height: 20px;"></span>
						</button>
						<button type="button" class="button-link eo-delete-cookie" data-cat="${cookie.catRaw}" data-id="${cookie.id}" title="${wp.i18n.__('Supprimer', 'eo-tools')}" style="color: #ef4444; padding: 0;">
							<span class="dashicons dashicons-trash" style="font-size: 20px; width: 20px; height: 20px;"></span>
						</button>
					</td>
				</tr>
			`;
		});

		const getSortIcon = (col) => {
			if (currentSortCol !== col) return '';
			return currentSortDir === 'asc' ? ' <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>' : ' <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>';
		};

		$container.html(`
				<table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">
					<thead>
						<tr>
							<th class="eo-sortable-th" data-sort="category" style="width: 15%; cursor: pointer;">${wp.i18n.__('Type', 'eo-tools')}${getSortIcon('category')}</th>
							<th class="eo-sortable-th" data-sort="name" style="width: 15%; cursor: pointer;">${wp.i18n.__('Cookie', 'eo-tools')}${getSortIcon('name')}</th>
							<th class="eo-sortable-th" data-sort="domain" style="width: 15%; cursor: pointer;">${wp.i18n.__('Domaine', 'eo-tools')}${getSortIcon('domain')}</th>
							<th class="eo-sortable-th" data-sort="duration" style="width: 10%; cursor: pointer;">${wp.i18n.__('Durée', 'eo-tools')}${getSortIcon('duration')}</th>
							<th class="eo-sortable-th" data-sort="comment" style="width: 25%; cursor: pointer;">${wp.i18n.__('Commentaire', 'eo-tools')}${getSortIcon('comment')}</th>
							<th style="width: 10%; text-align: right;">${wp.i18n.__('Actions', 'eo-tools')}</th>
						</tr>
					</thead>
					<tbody>
						${rowsHtml}
					</tbody>
				</table>
		`);

		// Mettre à jour le bandeau de validation en haut
		let activeCookiesList = [];
		for (const cat in cookieRegistry) {
			if (cookieRegistry[cat]) {
				cookieRegistry[cat].forEach(c => {
					activeCookiesList.push(c.name);
				});
			}
		}
		let activeCookiesListStr = activeCookiesList.join(', ');
		if (activeCookiesList.length > 0 && activeCookiesListStr !== window.eoLastAdminValidationCookies) {
			$('#eo-validation-cookie-list').text(activeCookiesListStr);
			$('#eo-validation-prompt-container').css('display', 'flex');
		} else {
			$('#eo-validation-prompt-container').hide();
		}
	}

	// Handle sort
	$(document).on('click', '.eo-sortable-th', function() {
		const col = $(this).data('sort');
		if (currentSortCol === col) {
			currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
		} else {
			currentSortCol = col;
			currentSortDir = 'asc';
		}
		renderCookieTable();
	});

	// Handle Validation & Save button
	$(document).on('click', '#eo-save-consent-btn', function() {
		const $btn = $(this);
		$btn.prop('disabled', true).text(wp.i18n.__('Enregistrement...', 'eo-tools'));
		
		let activeCookiesList = [];
		for (const cat in cookieRegistry) {
			if (cookieRegistry[cat]) {
				cookieRegistry[cat].forEach(c => {
					activeCookiesList.push(c.name);
				});
			}
		}

		$.post(eoToolsCookiesAdmin.ajaxUrl, {
			action: 'eo_tools_save_cookie_registry',
			security: eoToolsCookiesAdmin.nonce,
			registry: cookieRegistry,
			change_log: activeCookiesList,
			action_type: 'ADMIN_VALIDATION'
		}, function(response) {
			$btn.prop('disabled', false).text(wp.i18n.__('Validation Admin', 'eo-tools'));
			if (response.success) {
				window.location.reload();
			} else {
				alert(wp.i18n.__('Erreur lors de l\'enregistrement.', 'eo-tools'));
			}
		}).fail(function() {
			$btn.prop('disabled', false).text(wp.i18n.__('Validation Admin', 'eo-tools'));
			alert(wp.i18n.__('Erreur lors de l\'enregistrement.', 'eo-tools'));
		});
	});

	// Open Add Modal
	$('#eo-add-cookie-btn').on('click', function() {
		$('#eo-cookie-form')[0].reset();
		$('#eo-cookie-id').val('');
		$('#eo-cookie-old-cat').val('');
		$('#eo-cookie-cat').val('strictly-necessary');
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
		const name = $('#eo-cookie-name').val();
		const domain = $('#eo-cookie-domain').val();
		const date = $('#eo-cookie-duration').val();
		const comment = $('#eo-cookie-desc').val();

		const cookieData = { id, name, domain, date, comment };

		// If editing and category changed, remove from old category
		if (oldCat && oldCat !== newCat && cookieRegistry[oldCat]) {
			cookieRegistry[oldCat] = cookieRegistry[oldCat].filter(c => c.id !== id);
		}

		if (!cookieRegistry[newCat]) {
			cookieRegistry[newCat] = [];
		}

		const existingIndex = cookieRegistry[newCat].findIndex(c => c.id === id);
		let changeLogMsg = '';
		if (existingIndex > -1) {
			cookieRegistry[newCat][existingIndex] = cookieData;
			changeLogMsg = '~ ' + name;
		} else {
			cookieRegistry[newCat].push(cookieData);
			changeLogMsg = '+ ' + name;
		}

		saveRegistry([changeLogMsg], () => {
			$('#eo-cookie-modal').hide();
		});
	});

	// Edit Cookie
	$(document).on('click', '.eo-edit-cookie', function() {
		const cat = $(this).data('cat');
		const id = $(this).data('id');
		const cookies = cookieRegistry[cat] || [];
		const cookie = cookies.find(c => c.id === id);
		
		if (cookie) {
			$('#eo-cookie-id').val(cookie.id);
			$('#eo-cookie-old-cat').val(cat);
			$('#eo-cookie-cat').val(cat);
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
		const cat = $(this).data('cat');
		const id = $(this).data('id');
		const cookies = cookieRegistry[cat] || [];
		const cookie = cookies.find(c => c.id === id);
		if (!cookie) return;

		if (confirm(wp.i18n.__('Êtes-vous sûr de vouloir supprimer ce cookie ?', 'eo-tools'))) {
			cookieRegistry[cat] = cookies.filter(c => c.id !== id);
			saveRegistry(['- ' + cookie.name]);
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
				const cat = mapCategory(res.category);
				
				$('#eo-cookie-form')[0].reset();
				$('#eo-cookie-id').val('');
				$('#eo-cookie-old-cat').val('');
				$('#eo-cookie-cat').val(cat);
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
		$btn.prop('disabled', true).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" style="animation: dashicons-spin 1s infinite linear; vertical-align: middle; margin-right: 5px; margin-top: -2px;"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg> ' + wp.i18n.__('Analyse en cours...', 'eo-tools'));
		
		// 2. Add temporary line in History table
		const scanDate = new Date().toLocaleString();
		const tempId = 'scan-' + Date.now();
		const $tbody = $('#eo-scan-history-list');
		
		// Remove empty state if present
		if ($tbody.find('td[colspan="7"]').text().includes(wp.i18n.__('Aucun scan effectué', 'eo-tools')) || $tbody.find('td[colspan="7"]').text().includes(wp.i18n.__('Chargement', 'eo-tools'))) {
			$tbody.empty();
		}
		
		$tbody.prepend(`
			<tr id="${tempId}" style="background-color: #f8fafc;">
				<td><strong>${scanDate}</strong></td>
				<td><span style="background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" style="animation: dashicons-spin 1s infinite linear; vertical-align: middle; margin-right: 4px; margin-top: -2px;"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg>${wp.i18n.__('EN COURS', 'eo-tools')}</span></td>
				<td colspan="5" style="color: #64748b; font-style: italic;">${wp.i18n.__('Scan en cours d\'exécution...', 'eo-tools')}</td>
			</tr>
		`);

		// Fetch frontend cookies first
		$.post(eoToolsCookiesAdmin.ajaxUrl, {
			action: 'eo_tools_scan_frontend_cookies',
			security: eoToolsCookiesAdmin.nonce
		}).done(function(backendResponse) {
			let backendCookies = [];
			if (backendResponse.success && backendResponse.data && backendResponse.data.cookies) {
				backendCookies = backendResponse.data.cookies;
			}
			
			try {
				const localCookiesStr = document.cookie ? document.cookie.split(';') : [];
				const localCookies = localCookiesStr.map(c => c.trim().split('=')[0]).filter(c => c);
				
				// Combine and deduplicate
				const allCookies = [...new Set([...backendCookies, ...localCookies])];
				
				let foundCount = 0;
				let addedCount = 0;
				let addedNames = [];
				let addedCookiesObj = [];
				
				allCookies.forEach(name => {
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
					
					addedCookiesObj.push({
						cat: cat,
						cookie: {
							id: generateId(),
							name: name,
							domain: dbMatch ? dbMatch.domain : '',
							date: dbMatch ? parseRetention(dbMatch.date) : 365,
							comment: dbMatch ? dbMatch.comment : wp.i18n.__('Détecté automatiquement lors du scan.', 'eo-tools'),
							active: true
						}
					});
					
					addedCount++;
					addedNames.push(name);
				});
				
				// Find deleted cookies
				const registryNames = [];
				for (const cat in cookieRegistry) {
					if (cookieRegistry[cat]) {
						const arr = Array.isArray(cookieRegistry[cat]) ? cookieRegistry[cat] : Object.values(cookieRegistry[cat]);
						arr.forEach(c => {
							if (c && typeof c === 'object' && c.name && c.name.trim() !== '') {
								registryNames.push(c.name);
							} else if (typeof c === 'string' && c.trim() !== '') {
								registryNames.push(c);
							}
						});
					}
				}
				
				const deletedNames = registryNames.filter(name => !allCookies.includes(name));
				const deletedCount = deletedNames.length;
				
				// Save Scan History
				const scanResult = {
					date: scanDate,
					timestamp: Date.now(),
					status: 'COMPLETED',
					found: foundCount,
					foundNames: allCookies,
					added: addedCount,
					addedNames: addedNames,
					addedCookies: addedCookiesObj,
					deleted: deletedCount,
					deletedNames: deletedNames
				};
				
				$.post(eoToolsCookiesAdmin.ajaxUrl, {
					action: 'eo_tools_save_scan_result',
					security: eoToolsCookiesAdmin.nonce,
					result: JSON.stringify(scanResult)
				}, function(res) {
					if (res.success) {
						renderScanHistory(res.data);
						if (addedCount > 0 || deletedCount > 0) {
							// Trigger modal automatically
							currentValidationTimestamp = scanResult.timestamp;
							currentValidationDate = scanResult.date;
							currentValidationNames = [];
							if (scanResult.addedNames) scanResult.addedNames.forEach(n => currentValidationNames.push('+ ' + n));
							if (scanResult.deletedNames) scanResult.deletedNames.forEach(n => currentValidationNames.push('- ' + n));
							
							const $list = $('#eo-scan-validation-list');
							$list.empty();
							currentValidationNames.forEach(name => {
								const color = name.startsWith('+') ? '#10b981' : '#ef4444';
								$list.append(`<li style="color: ${color}; font-weight: bold;">${name}</li>`);
							});
							$('#eo-scan-validation-modal').css('display', 'flex');
						}
					}
				}).always(function() {
					$btn.prop('disabled', false).html(originalHtml);
				});
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
					deleted: 0,
					deletedNames: [],
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
		}).fail(function() {
			$btn.prop('disabled', false).html(originalHtml);
			alert(wp.i18n.__('Erreur lors du scan backend.', 'eo-tools'));
		});
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
		globalHistoryArray = historyArray;
		const $tbody = $('#eo-scan-history-list');
		if (!$tbody.length) return; // If we are not on the cookies tab
		
		$tbody.empty();
		
		if (!historyArray || historyArray.length === 0) {
			$tbody.html(`<tr><td colspan="7" style="text-align: center; color: #64748b; font-style: italic; padding: 15px;">${wp.i18n.__('Aucun scan effectué.', 'eo-tools')}</td></tr>`);
			return;
		}
		
		historyArray.forEach(item => {
			if (item.status === 'FAILED' || item.status === 'ERREUR') {
				$tbody.append(`
					<tr>
						<td><strong>${item.ref || ''}</strong><br><span style="font-size:10px;color:#64748b;">${item.date}</span></td>
						<td><span style="background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">${wp.i18n.__('ERREUR', 'eo-tools')}</span></td>
						<td colspan="5" style="color: #dc2626;">${item.error || wp.i18n.__('Erreur inconnue', 'eo-tools')}</td>
					</tr>
				`);
			} else {
				let addedHtml = '-';
				if ((item.added > 0 || item.deleted > 0) || (item.addedNames && item.addedNames.length > 0) || (item.deletedNames && item.deletedNames.length > 0)) {
					const countDel = item.deleted || (item.deletedNames ? item.deletedNames.length : 0);
					const countAdd = item.added || (item.addedNames ? item.addedNames.length : 0);
					addedHtml = `<span style="color: #ef4444; font-weight: bold;">- ${countDel}</span> <span style="color: #cbd5e1; margin: 0 4px;">|</span> <span style="color: #10b981; font-weight: bold;">+ ${countAdd}</span>`;
				}
				
				let addedNamesHtml = '-';
				let changesArr = [];
				if (item.deletedNames && item.deletedNames.length > 0) {
					item.deletedNames.forEach(name => {
						changesArr.push(`<span style="color: #ef4444;">- ${name}</span>`);
					});
				}
				if (item.addedNames && item.addedNames.length > 0) {
					item.addedNames.forEach(name => {
						changesArr.push(`<span style="color: #10b981;">+ ${name}</span>`);
					});
				}
				if (changesArr.length > 0) {
					addedNamesHtml = changesArr.join('<span style="color: #cbd5e1;">, </span>');
				}
				
				let actionHtml = '';
				
				let foundHtml = item.found;
				let foundNamesHtml = '-';
				if (item.found > 0 && item.foundNames && item.foundNames.length > 0) {
					foundNamesHtml = item.foundNames.join(', ');
				}

				// Bouton détails pour le scan détaillé
				if (item.source === 'detailed_scan') {
					let batchLink = item.batch_id ? `&batch_id=${item.batch_id}` : '';
					actionHtml += `<a href="?page=eo-tools-cookies&tab=detailed_scan${batchLink}" class="button button-secondary" style="font-size: 11px; padding: 0 8px; min-height: 24px; line-height: 22px; display: inline-block; margin-bottom: 5px;">${wp.i18n.__('Consulter les détails', 'eo-tools')}</a><br>`;
				}

				if (item.added > 0 || item.deleted > 0) {
					if (item.validated) {
						actionHtml += `<span style="color: #166534; font-size: 11px;"><span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> ${wp.i18n.sprintf(wp.i18n.__('Validé le %s', 'eo-tools'), item.validatedDate || item.date)} - <a href="?page=eo-tools-cookies&tab=report" style="color: #166534; text-decoration: underline;">${wp.i18n.__('Voir le rapport', 'eo-tools')}</a></span>`;
					} else {
						const addNamesData = (item.addedNames && item.addedNames.length > 0) ? item.addedNames.join(',') : '';
						const delNamesData = (item.deletedNames && item.deletedNames.length > 0) ? item.deletedNames.join(',') : '';
						actionHtml += `<button type="button" class="button button-primary eo-validate-scan-btn" data-timestamp="${item.timestamp || 0}" data-date="${item.date}" data-added-names="${addNamesData}" data-deleted-names="${delNamesData}" style="font-size: 11px; padding: 0 8px; min-height: 24px; line-height: 22px; background: #eab308; border-color: #ca8a04; color: #fff;">${wp.i18n.__('Valider les nouveaux cookies', 'eo-tools')}</button>`;
					}
				} else if (actionHtml === '') {
					actionHtml = '-';
				}

				$tbody.append(`
					<tr>
						<td><strong>${item.ref || ''}</strong><br><span style="font-size:10px;color:#64748b;">${item.date}</span></td>
						<td><span style="background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">${item.status}</span></td>
						<td><strong>${typeof item.totalFound !== 'undefined' ? item.totalFound : (typeof item.found !== 'undefined' ? item.found : '-')}</strong></td>
						<td><span style="font-size: 10px; color: #64748b; background: #f1f5f9; padding: 2px 4px; border-radius: 3px; cursor: text; user-select: all;" title="Copier la liste" onclick="navigator.clipboard.writeText('${foundNamesHtml.replace(/'/g, "\\'")}');">${foundNamesHtml}</span></td>
						<td>${addedHtml}</td>
						<td><span style="font-size: 10px; font-weight: bold;">${addedNamesHtml}</span></td>
						<td style="text-align: right; white-space: nowrap;">
							${actionHtml}
						</td>
					</tr>
				`);
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
	
	window.showNotice = function(msg, type = 'success') {
		const bg = type === 'error' ? '#ef4444' : '#10b981';
		const $notice = $('<div style="position:fixed;bottom:20px;right:20px;background:'+bg+';color:white;padding:10px 20px;border-radius:4px;z-index:999999;box-shadow:0 4px 6px rgba(0,0,0,0.1);">'+msg+'</div>');
		$('body').append($notice);
		setTimeout(() => $notice.fadeOut(300, function(){ $(this).remove(); }), 3000);
	};
});

