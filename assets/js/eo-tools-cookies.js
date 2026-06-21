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

		generateId: function() {
			return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
		},

		saveConsent: function(type) {
			const durationDays = window.eoToolsCookieData ? window.eoToolsCookieData.durationDays : 365;
			const date = new Date();
			date.setTime(date.getTime() + (durationDays * 24 * 60 * 60 * 1000));
			
			if (type === 'accept_all') {
				Object.keys(this.categories).forEach(cat => this.consent[cat] = true);
			} else if (type === 'refuse_all') {
				Object.keys(this.categories).forEach(cat => this.consent[cat] = (cat === 'strictly-necessary'));
			} else if (type === 'custom') {
				// Check if they actually accepted all or refused all manually
				let allAccepted = true;
				let allRefused = true;
				Object.keys(this.categories).forEach(cat => {
					if (cat !== 'strictly-necessary') {
						if (!this.consent[cat]) allAccepted = false;
						if (this.consent[cat]) allRefused = false;
					}
				});
				if (allAccepted) {
					type = 'accept_all';
				} else if (allRefused) {
					type = 'refuse_all';
				}
			}

			if (!this.consent.id) {
				this.consent.id = this.generateId();
			}

			document.cookie = "eotools_consent=" + encodeURIComponent(JSON.stringify(this.consent)) + "; expires=" + date.toUTCString() + "; path=/; SameSite=Lax";
			
			this.sendStats(type);
			this.runServices();
			this.hideBanner();
			this.hideModal();

			const existingBtn = document.getElementById('eo-tools-cookie-revoke');
			if (existingBtn) existingBtn.remove();
			this.renderRevokeButton();
		},

		sendStats: function(type) {
			if (!window.eoToolsCookieData || !window.eoToolsCookieData.ajaxUrl) return;

			const data = new FormData();
			data.append('action', 'eo_tools_cookie_stats');
			data.append('security', window.eoToolsCookieData.nonce);
			data.append('type', type);
			if (this.consent && this.consent.id) {
				data.append('consent_id', this.consent.id);
			}

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

		renderRevokeButton: function() {
			if (document.getElementById('eo-tools-cookie-revoke')) return;

			let isAllAccepted = true;
			Object.keys(this.categories).forEach(cat => {
				if (cat !== 'strictly-necessary' && !this.consent[cat]) {
					isAllAccepted = false;
				}
			});

			const revokeBtn = document.createElement('button');
			revokeBtn.id = 'eo-tools-cookie-revoke';

			const customIconFull = (window.eoToolsCookieData && window.eoToolsCookieData.iconFull) ? window.eoToolsCookieData.iconFull : '';
			const customIconPartial = (window.eoToolsCookieData && window.eoToolsCookieData.iconPartial) ? window.eoToolsCookieData.iconPartial : '';

			if (isAllAccepted) {
				if (customIconFull) {
					revokeBtn.innerHTML = '<img src="' + customIconFull + '" alt="Cookies" style="width:20px;height:20px;" />';
				} else {
					// FA Cookie
					revokeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width:20px;height:20px;fill:currentColor;"><path d="M257.5 27.6c-.8-5.4-4.9-9.8-10.3-10.6v0c-12.1-1.7-24.7-2.6-37.5-2.6C93.9 14.4 0 108.3 0 223.9c0 115.6 93.9 209.5 209.7 209.5 115.8 0 209.7-93.9 209.7-209.5 0-12.8-.9-25.3-2.6-37.5-.8-5.4-5.2-9.5-10.6-10.3-23.7-3.4-46.7-10.8-68.5-21.7-1.3-.6-2.5-1.5-3.5-2.5-12.9-12.9-22-29.3-26.6-47.3-.5-2.1-1.3-4.2-2.3-6.1-9.9-19-24.3-35.8-41.5-49.3-1.6-1.2-3.4-2.1-5.3-2.6-18.7-4.9-35.9-14.7-49.7-28.5-1.3-1.3-2.4-2.8-3.3-4.4-11.2-21-18.8-44.1-22.3-68.2zM209.7 481.5c-142.3 0-257.7-115.5-257.7-257.6S67.4-33.7 209.7-33.7c14.6 0 29.1 1.2 43.1 3.5 13.5 2.2 21.6 15 19 28.5-5.2 26.6-4.6 54.1 1.7 80.8 2.2 9.5 7.6 18.2 15.6 24.6 20.9 16.7 38.3 37.1 50.4 60.5 4.3 8.3 10.9 15.1 18.8 19.8 22.8 13.5 47.6 22.3 73.6 25.8 13.4 1.8 21.5 14.7 19.2 28-2.6 15.4-4 31.1-4 47.1 0 142.1-115.4 257.6-257.7 257.6zM136.5 144c13.3 0 24-10.7 24-24s-10.7-24-24-24-24 10.7-24 24 10.7 24 24 24zm-24 112c0 13.3 10.7 24 24 24s24-10.7 24-24-10.7-24-24-24-24 10.7-24 24zm144 80c13.3 0 24-10.7 24-24s-10.7-24-24-24-24 10.7-24 24 10.7 24 24 24z"/></svg>';
				}
			} else {
				if (customIconPartial) {
					revokeBtn.innerHTML = '<img src="' + customIconPartial + '" alt="Cookies" style="width:20px;height:20px;" />';
				} else {
					// FA Cookie Bite
					revokeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width:20px;height:20px;fill:currentColor;"><path d="M510.5 218.4c-4.8-19.1-23.7-30.8-42.9-26-25.5 6.4-52.5.3-74.8-16.7-18.7-14.3-31.5-35-36-58.4-4.5-23.4.1-47.6 13.1-68.4 10-15.9 5.3-37-10.5-47-21.3-13.6-45.7-21.4-71.1-21.4C163.6-19.5 62.4 81.8 62.4 206.8c0 125 101.3 226.3 225.9 226.3 113.8 0 208-84.1 223.5-195.4 2.1-15.1-4.8-29.8-17.7-37.4l36.4 18.1zM288.3 481.1c-149.2 0-273.9-124.6-273.9-274.3 0-149.7 124.7-274.3 273.9-274.3 21.8 0 42.9 2.6 63.3 7.5-11.4 20.3-15.9 44-11.8 67.5 4.6 26.6 19.3 49.9 40.5 65.5 19.7 14.5 44 21.2 68.2 18.9 4.3 25.1 4.5 50.8.6 75.8-15.8 100.8-101.4 177.3-205.6 177.3zM161.4 148c13.3 0 24-10.7 24-24s-10.7-24-24-24-24 10.7-24 24 10.7 24 24 24zm-24 112c0 13.3 10.7 24 24 24s24-10.7 24-24-10.7-24-24-24-24 10.7-24 24zm144 80c13.3 0 24-10.7 24-24s-10.7-24-24-24-24 10.7-24 24 10.7 24 24 24z"/></svg>';
				}
			}
			document.body.appendChild(revokeBtn);
		},

		renderModal: function() {
			if (document.getElementById('eo-tools-cookie-modal')) return;

			const modal = document.createElement('div');
			modal.id = 'eo-tools-cookie-modal';
			
			let categoriesHtml = `
				<div class="eo-cookie-category eo-cookie-category-master" style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0;">
					<div class="eo-cookie-cat-info">
						<h4 style="margin: 0 0 5px 0;">Tout accepter</h4>
						<p style="margin: 0; font-size: 0.85rem; color: #64748b;">Activer ou désactiver tous les cookies optionnels en un seul clic.</p>
					</div>
					<div class="eo-cookie-toggle">
						<label class="switch">
							<input type="checkbox" id="eo-cookie-master-toggle">
							<span class="slider round"></span>
						</label>
					</div>
				</div>
			`;
			
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
		},

		renderBanner: function() {
			this.renderRevokeButton();
			this.renderModal();

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
		},

		bindEvents: function() {
			document.addEventListener('click', (e) => {
				if (e.target.closest('#eo-cookie-accept-all')) {
					this.saveConsent('accept_all');
				} else if (e.target.closest('#eo-cookie-refuse-all')) {
					this.saveConsent('refuse_all');
				} else if (e.target.closest('#eo-cookie-customize') || e.target.closest('#eo-tools-cookie-revoke')) {
					this.showModal();
				} else if (e.target.closest('#eo-cookie-close-modal')) {
					this.hideModal();
				} else if (e.target.closest('#eo-cookie-save-custom')) {
					const checkboxes = document.querySelectorAll('#eo-tools-cookie-modal input[type="checkbox"]:not(#eo-cookie-master-toggle)');
					checkboxes.forEach(cb => {
						this.consent[cb.dataset.category] = cb.checked;
					});
					this.saveConsent('custom');
				}
			});

			document.addEventListener('change', (e) => {
				if (e.target.id === 'eo-cookie-master-toggle') {
					const isChecked = e.target.checked;
					const checkboxes = document.querySelectorAll('#eo-tools-cookie-modal input[type="checkbox"]:not(#eo-cookie-master-toggle):not(:disabled)');
					checkboxes.forEach(cb => {
						cb.checked = isChecked;
					});
				} else if (e.target.matches('#eo-tools-cookie-modal input[type="checkbox"]:not(#eo-cookie-master-toggle):not(:disabled)')) {
					const checkboxes = document.querySelectorAll('#eo-tools-cookie-modal input[type="checkbox"]:not(#eo-cookie-master-toggle):not(:disabled)');
					const allChecked = Array.from(checkboxes).every(cb => cb.checked);
					const masterToggle = document.getElementById('eo-cookie-master-toggle');
					if (masterToggle) masterToggle.checked = allChecked;
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
				const checkboxes = document.querySelectorAll('#eo-tools-cookie-modal input[type="checkbox"]:not(#eo-cookie-master-toggle)');
				checkboxes.forEach(cb => {
					if (cb.dataset.category && cb.dataset.category !== 'strictly-necessary') {
						cb.checked = !!this.consent[cb.dataset.category];
					}
				});

				const masterToggle = document.getElementById('eo-cookie-master-toggle');
				if (masterToggle) {
					const optionalCheckboxes = document.querySelectorAll('#eo-tools-cookie-modal input[type="checkbox"]:not(#eo-cookie-master-toggle):not(:disabled)');
					const allChecked = optionalCheckboxes.length > 0 && Array.from(optionalCheckboxes).every(cb => cb.checked);
					masterToggle.checked = allChecked;
				}

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
