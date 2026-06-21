(function(window, document) {
	'use strict';

	const eoToolsCookies = {
		services: {},
		categories: {
			'strictly-necessary': { name: 'Strictement nécessaires', description: 'Cookies techniques indispensables au fonctionnement du site.' },
			'analytics': { name: 'Mesure d\'audience', description: 'Permettent de comprendre comment les visiteurs interagissent avec le site.' },
			'marketing': { name: 'Marketing', description: 'Utilisés pour suivre les visiteurs au travers des sites web afin d\'afficher des publicités pertinentes.' },
			'social': { name: 'Réseaux sociaux', description: 'Permettent le partage de contenu sur les réseaux sociaux et la lecture de vidéos.' },
			'functional': { name: 'Fonctionnel', description: 'Améliorent les fonctionnalités et la personnalisation du site.' }
		},
		consent: {},
		
		init: function() {
			this.loadConsent();
			this.renderBanner();
			this.runServices();
			this.bindEvents();
		},

		addService: function(key, config) {
			this.services[key] = config;
		},

		loadConsent: function() {
			const cookie = document.cookie.split('; ').find(row => row.startsWith('eotools_consent='));
			if (cookie) {
				try {
					this.consent = JSON.parse(decodeURIComponent(cookie.split('=')[1]));
				} catch (e) {
					this.consent = {};
				}
			}
		},

		saveConsent: function(type) {
			const durationDays = window.eoToolsCookieData ? window.eoToolsCookieData.durationDays : 365;
			const date = new Date();
			date.setTime(date.getTime() + (durationDays * 24 * 60 * 60 * 1000));
			
			if (type === 'accept_all') {
				Object.keys(this.categories).forEach(cat => this.consent[cat] = true);
			} else if (type === 'refuse_all') {
				Object.keys(this.categories).forEach(cat => this.consent[cat] = (cat === 'strictly-necessary'));
			}
			// custom is already modified in this.consent object

			document.cookie = "eotools_consent=" + encodeURIComponent(JSON.stringify(this.consent)) + "; expires=" + date.toUTCString() + "; path=/; SameSite=Lax";
			
			this.sendStats(type);
			this.runServices();
			this.hideBanner();
			this.hideModal();
		},

		sendStats: function(type) {
			if (!window.eoToolsCookieData || !window.eoToolsCookieData.ajaxUrl) return;

			const data = new FormData();
			data.append('action', 'eo_tools_cookie_stats');
			data.append('security', window.eoToolsCookieData.nonce);
			data.append('type', type);

			fetch(window.eoToolsCookieData.ajaxUrl, {
				method: 'POST',
				body: data
			});
		},

		hasConsent: function(category) {
			if (category === 'strictly-necessary') return true;
			return this.consent[category] === true;
		},

		runServices: function() {
			Object.keys(this.services).forEach(key => {
				const service = this.services[key];
				if (this.hasConsent(service.type)) {
					if (typeof service.js === 'function') {
						service.js();
					}
					// Unblock intercepted scripts
					document.querySelectorAll('script[type="text/plain"][data-cookiecategory="' + service.type + '"]').forEach(script => {
						const newScript = document.createElement('script');
						if (script.hasAttribute('data-src')) {
							newScript.src = script.getAttribute('data-src');
						}
						newScript.innerHTML = script.innerHTML;
						script.parentNode.replaceChild(newScript, script);
					});

					// Unblock iframes
					document.querySelectorAll('iframe[data-cookiecategory="' + service.type + '"]').forEach(iframe => {
						if (iframe.hasAttribute('data-src')) {
							iframe.src = iframe.getAttribute('data-src');
							iframe.removeAttribute('data-src');
							const placeholder = iframe.previousElementSibling;
							if (placeholder && placeholder.classList.contains('eo-tools-cookie-placeholder')) {
								placeholder.remove();
							}
						}
					});
				} else {
					if (typeof service.fallback === 'function') {
						service.fallback();
					}
				}
			});
		},

		renderBanner: function() {
			// Don't show if already consented (at least one choice made, strictly-necessary is always there if they saved custom)
			if (Object.keys(this.consent).length > 0) return;

			// Send view stat
			this.sendStats('view');

			const banner = document.createElement('div');
			banner.id = 'eo-tools-cookie-banner';
			banner.innerHTML = `
				<div class="eo-tools-cookie-content">
					<div class="eo-tools-cookie-text">
						<h3>Gestion de vos préférences sur les cookies</h3>
						<p>Nous utilisons des cookies pour assurer le bon fonctionnement du site, mesurer l'audience et vous proposer des publicités personnalisées. Vous pouvez tous les accepter, tous les refuser ou choisir vos préférences.</p>
					</div>
					<div class="eo-tools-cookie-actions">
						<button id="eo-cookie-refuse-all" class="eo-cookie-btn">Tout refuser</button>
						<button id="eo-cookie-customize" class="eo-cookie-btn eo-cookie-btn-outline">Personnaliser</button>
						<button id="eo-cookie-accept-all" class="eo-cookie-btn eo-cookie-btn-primary">Tout accepter</button>
					</div>
				</div>
			`;
			document.body.appendChild(banner);

			const modal = document.createElement('div');
			modal.id = 'eo-tools-cookie-modal';
			
			let categoriesHtml = '';
			Object.keys(this.categories).forEach(cat => {
				const isStrict = cat === 'strictly-necessary';
				categoriesHtml += `
					<div class="eo-cookie-category">
						<div class="eo-cookie-cat-info">
							<h4>${this.categories[cat].name}</h4>
							<p>${this.categories[cat].description}</p>
						</div>
						<div class="eo-cookie-toggle">
							<label class="switch">
								<input type="checkbox" data-category="${cat}" ${isStrict ? 'checked disabled' : ''}>
								<span class="slider round"></span>
							</label>
						</div>
					</div>
				`;
			});

			modal.innerHTML = `
				<div class="eo-tools-cookie-modal-content">
					<div class="eo-tools-cookie-modal-header">
						<h3>Personnalisation des cookies</h3>
						<button id="eo-cookie-close-modal">&times;</button>
					</div>
					<div class="eo-tools-cookie-modal-body">
						${categoriesHtml}
					</div>
					<div class="eo-tools-cookie-modal-footer">
						<button id="eo-cookie-save-custom" class="eo-cookie-btn eo-cookie-btn-primary">Enregistrer mes choix</button>
					</div>
				</div>
			`;
			document.body.appendChild(modal);

			// Revoke button
			const revokeBtn = document.createElement('button');
			revokeBtn.id = 'eo-tools-cookie-revoke';
			revokeBtn.innerHTML = '🍪';
			document.body.appendChild(revokeBtn);
		},

		bindEvents: function() {
			document.addEventListener('click', (e) => {
				if (e.target.id === 'eo-cookie-accept-all') {
					this.saveConsent('accept_all');
				} else if (e.target.id === 'eo-cookie-refuse-all') {
					this.saveConsent('refuse_all');
				} else if (e.target.id === 'eo-cookie-customize' || e.target.id === 'eo-tools-cookie-revoke') {
					this.showModal();
				} else if (e.target.id === 'eo-cookie-close-modal') {
					this.hideModal();
				} else if (e.target.id === 'eo-cookie-save-custom') {
					const checkboxes = document.querySelectorAll('#eo-tools-cookie-modal input[type="checkbox"]');
					checkboxes.forEach(cb => {
						this.consent[cb.dataset.category] = cb.checked;
					});
					this.saveConsent('custom');
				}
			});
		},

		hideBanner: function() {
			const banner = document.getElementById('eo-tools-cookie-banner');
			if (banner) banner.style.display = 'none';
		},

		showModal: function() {
			const modal = document.getElementById('eo-tools-cookie-modal');
			if (modal) {
				// Update checkboxes based on current consent
				Object.keys(this.categories).forEach(cat => {
					if (cat !== 'strictly-necessary') {
						const cb = modal.querySelector('input[data-category="' + cat + '"]');
						if (cb) cb.checked = !!this.consent[cat];
					}
				});
				modal.style.display = 'flex';
			}
		},

		hideModal: function() {
			const modal = document.getElementById('eo-tools-cookie-modal');
			if (modal) modal.style.display = 'none';
		}
	};

	window.eoToolsCookies = eoToolsCookies;

	// Example built-in services
	eoToolsCookies.addService('google-analytics', {
		key: 'google-analytics',
		type: 'analytics',
		name: 'Google Analytics',
		needConsent: true,
		js: function() {
			// This will be handled mainly by the interceptor unblocking the script tags.
		}
	});

	document.addEventListener('DOMContentLoaded', function() {
		eoToolsCookies.init();
	});

})(window, document);
