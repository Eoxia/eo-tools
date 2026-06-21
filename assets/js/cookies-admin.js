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
			const $item = $(`
				<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px; display: flex; justify-content: space-between;">
					<div style="flex: 1;">
						<div style="display: flex; gap: 20px; margin-bottom: 10px;">
							<div style="width: 150px;"><strong>Cookie</strong></div>
							<div><code>${cookie.name}</code></div>
						</div>
						<div style="display: flex; gap: 20px; margin-bottom: 10px;">
							<div style="width: 150px;"><strong>${wp.i18n.__('Durée', 'eo-tools')}</strong></div>
							<div>${cookie.duration} ${wp.i18n.__('jours', 'eo-tools')}</div>
						</div>
						<div style="display: flex; gap: 20px;">
							<div style="width: 150px;"><strong>Description</strong></div>
							<div style="color: #475569;">${cookie.description}</div>
						</div>
					</div>
					<div style="display: flex; gap: 10px; align-items: flex-start;">
						<button type="button" class="button button-small eo-edit-cookie" data-id="${cookie.id}" title="${wp.i18n.__('Modifier', 'eo-tools')}">
							<span class="dashicons dashicons-edit" style="margin-top: 2px;"></span> ${wp.i18n.__('Modifier', 'eo-tools')}
						</button>
						<button type="button" class="button button-small eo-delete-cookie" data-id="${cookie.id}" title="${wp.i18n.__('Supprimer', 'eo-tools')}" style="color: #d63638;">
							<span class="dashicons dashicons-trash" style="margin-top: 2px;"></span> ${wp.i18n.__('Supprimer', 'eo-tools')}
						</button>
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
		const duration = $('#eo-cookie-duration').val();
		const description = $('#eo-cookie-desc').val();

		const cookieData = { id, name, duration, description };

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
			$('#eo-cookie-name').val(cookie.name);
			$('#eo-cookie-duration').val(cookie.duration);
			$('#eo-cookie-desc').val(cookie.description);
			$('#eo-cookie-modal-title').text(wp.i18n.__('Modifier un cookie', 'eo-tools'));
			$('#eo-cookie-modal').css('display', 'flex');
		}
	});

	// Delete Cookie
	$(document).on('click', '.eo-delete-cookie', function() {
		if (confirm(wp.i18n.__('Êtes-vous sûr de vouloir supprimer ce cookie ?', 'eo-tools'))) {
			const id = $(this).data('id');
			cookieRegistry[currentCategory] = cookieRegistry[currentCategory].filter(c => c.id !== id);
			saveRegistry();
		}
	});

	// Initial load
	loadRegistry();
});
