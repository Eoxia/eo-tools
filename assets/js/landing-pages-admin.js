/**
 * JavaScript for EO Blocks - Landing Pages admin interface
 */

jQuery(document).ready(function($) {
	// Reference to global config config object
	var config = window.eoLandingPagesConfig || {};
	var activeType = '';

	// Dynamically update the admin bar status badge
	function updateAdminBarBadge(settings) {
		if (typeof eoToolsLandingPagesAdmin === 'undefined') {
			return;
		}

		var csActive = settings.coming_soon && (settings.coming_soon.active === true || settings.coming_soon.active === 'true' || settings.coming_soon.active === 1 || settings.coming_soon.active === '1');
		var mActive = settings.maintenance && (settings.maintenance.active === true || settings.maintenance.active === 'true' || settings.maintenance.active === 1 || settings.maintenance.active === '1');

		var $adminBarSecondary = $('#wp-admin-bar-top-secondary');
		var $badge = $('#wp-admin-bar-eo-landing-pages-status');

		if (!csActive && !mActive) {
			$badge.remove();
			return;
		}

		var label = '';
		var badgeClass = 'eo-landing-pages-alert-badge';

		if (csActive && mActive) {
			label = eoToolsLandingPagesAdmin.labels.both;
			badgeClass += ' eo-alert-red';
		} else if (csActive) {
			label = eoToolsLandingPagesAdmin.labels.coming_soon;
			badgeClass += ' eo-alert-orange';
		} else {
			label = eoToolsLandingPagesAdmin.labels.maintenance;
			badgeClass += ' eo-alert-red';
		}

		if ($badge.length === 0) {
			var html = '<li id="wp-admin-bar-eo-landing-pages-status" class="' + badgeClass + '">' +
				'<a class="ab-item" href="' + eoToolsLandingPagesAdmin.adminUrl + '">' + label + '</a>' +
				'</li>';
			$adminBarSecondary.prepend(html);
		} else {
			$badge.attr('class', badgeClass);
			$badge.find('> .ab-item').text(label);
		}
	}

	// Sync color picker with text inputs
	function bindColorPicker(pickerId, textId) {
		$(pickerId).on('input', function() {
			$(textId).val($(this).val().toUpperCase());
			showFormDirty();
		});
		$(textId).on('input', function() {
			var val = $(this).val();
			if (val.match(/^#[0-9A-F]{6}$/i)) {
				$(pickerId).val(val);
				showFormDirty();
			}
		});
	}

	bindColorPicker('#eo-lp-form-bg-color', '#eo-lp-form-bg-color-text');
	bindColorPicker('#eo-lp-form-text-color', '#eo-lp-form-text-color-text');
	bindColorPicker('#eo-lp-form-accent-color', '#eo-lp-form-accent-color-text');

	// Mark form as dirty when inputs change
	$('#eo-lp-editor-form input, #eo-lp-editor-form textarea, #eo-lp-editor-form select').on('input change', function() {
		showFormDirty();
	});

	function showFormDirty() {
		$('.eo-lp-editor-status-text')
			.text('Changements non enregistrés')
			.css('color', '#2271b1');
		$('#eo-lp-save-btn')
			.removeClass('button-disabled')
			.css('background-color', '#2271b1');
	}

	function showFormSaving() {
		$('.eo-lp-editor-status-text')
			.text('Enregistrement...')
			.css('color', '#64748b');
	}

	function showFormSaved(msg) {
		$('.eo-lp-editor-status-text')
			.text(msg || 'Enregistré avec succès')
			.css('color', '#10b981');
		$('#eo-lp-save-btn').css('background-color', '#cbd5e1'); // grey-out button
	}

	function showFormError(msg) {
		$('.eo-lp-editor-status-text')
			.text(msg || 'Une erreur est survenue')
			.css('color', '#d63638');
	}

	// Toggle active switches
	$('.eo-lp-toggle-checkbox').on('change', function() {
		var $checkbox = $(this);
		var $card = $checkbox.closest('.eo-lp-card');
		var type = $card.data('type');
		var active = $checkbox.is(':checked');
		var $label = $checkbox.closest('.eo-lp-toggle-wrapper').find('.eo-lp-toggle-label');

		// Visual loading state
		$checkbox.prop('disabled', true);
		$label.text('MAJ...');

		$.ajax({
			url: eoToolsLandingPagesAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'eo_save_landing_page_settings',
				nonce: eoToolsLandingPagesAdmin.nonce,
				type: type,
				active: active ? 'true' : 'false',
				active_toggle: '1'
			},
			success: function(response) {
				$checkbox.prop('disabled', false);
				if (response.success) {
					// Update global config object
					config = response.data.settings;
					window.eoLandingPagesConfig = config;

					// Dynamically update the admin bar status badge
					updateAdminBarBadge(config);

					// Visual state updates
					if (active) {
						$card.addClass('active');
						$label.text('ACTIF').addClass('active');

						// Handle mutual exclusion of Coming Soon and Maintenance
						if (type === 'coming_soon') {
							var $mCard = $('.eo-lp-card[data-type="maintenance"]');
							$mCard.removeClass('active');
							$mCard.find('.eo-lp-toggle-checkbox').prop('checked', false);
							$mCard.find('.eo-lp-toggle-label').text('INACTIF').removeClass('active');
						} else if (type === 'maintenance') {
							var $csCard = $('.eo-lp-card[data-type="coming_soon"]');
							$csCard.removeClass('active');
							$csCard.find('.eo-lp-toggle-checkbox').prop('checked', false);
							$csCard.find('.eo-lp-toggle-label').text('INACTIF').removeClass('active');
						}
					} else {
						$card.removeClass('active');
						$label.text('INACTIF').removeClass('active');
					}
					
					// If currently editing this page, update form active state
					if (activeType === type) {
						showFormSaved('État mis à jour');
					}
				} else {
					$checkbox.prop('checked', !active); // revert
					$label.text(active ? 'INACTIF' : 'ACTIF');
					alert(response.data.message || 'Erreur lors de la modification de l\'état.');
				}
			},
			error: function() {
				$checkbox.prop('disabled', false);
				$checkbox.prop('checked', !active); // revert
				$label.text(active ? 'INACTIF' : 'ACTIF');
				alert('Impossible de contacter le serveur.');
			}
		});
	});

	// Click Edit Button
	$('.eo-lp-edit-btn').on('click', function() {
		var type = $(this).data('type');
		openEditor(type);
	});

	// Change labels dynamically based on selected style and type
	function adjustFieldLabels() {
		var style = $('#eo-lp-form-style').val();
		var type = $('#eo-lp-form-type').val();
		
		var $accentGroup = $('#eo-lp-form-accent-color').closest('.eo-lp-form-group');
		var $accentLabel = $accentGroup.find('label');

		if (style === 'minimalist') {
			// Minimalist style uses accent color for buttons (Login and 404 only)
			if (type === 'coming_soon' || type === 'maintenance') {
				// No buttons on Coming Soon / Maintenance in minimalist style
				$accentGroup.hide();
			} else {
				$accentGroup.show();
				$accentLabel.text('Couleur du bouton');
			}
		} else if (style === 'gradient') {
			$accentGroup.show();
			$accentLabel.text('Couleur de fin du dégradé');
		} else if (style === 'glassmorphism') {
			$accentGroup.show();
			$accentLabel.text('Couleur secondaire (Effet verre)');
		}
	}

	$('#eo-lp-form-style').on('change', function() {
		adjustFieldLabels();
	});

	var localIpRules = [];

	function renderIpRules(rules) {
		var $tbody = $('#eo-lp-ip-rules-tbody');
		$tbody.empty();

		if (rules.length === 0) {
			$tbody.append('<tr><td colspan="3" style="text-align: center; color: #64748b; padding: 12px;">Aucune règle IP configurée.</td></tr>');
			return;
		}

		rules.forEach(function(rule, index) {
			var actionLabel = rule.action === 'allow' ? 'Autoriser' : 'Bloquer';
			var badgeStyle = rule.action === 'allow' 
				? 'background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600;' 
				: 'background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600;';
			var row = '<tr>' +
				'<td style="font-family: monospace; padding: 8px 10px; font-size: 12px; vertical-align: middle;">' + $('<div/>').text(rule.ip).html() + '</td>' +
				'<td style="padding: 8px 10px; vertical-align: middle;"><span style="' + badgeStyle + '">' + actionLabel + '</span></td>' +
				'<td style="text-align: right; padding: 8px 10px; vertical-align: middle;">' +
					'<button type="button" class="button button-link-delete eo-lp-delete-ip-rule" data-index="' + index + '" style="color: #d63638; padding: 0; min-height: 0; line-height: 1;">' +
						'<span class="dashicons dashicons-no-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>' +
					'</button>' +
				'</td>' +
				'</tr>';
			$tbody.append(row);
		});
	}

	$(document).on('click', '.eo-lp-delete-ip-rule', function() {
		var index = $(this).data('index');
		localIpRules.splice(index, 1);
		$('#eo-lp-ip-rules-hidden').val(JSON.stringify(localIpRules));
		renderIpRules(localIpRules);
		showFormDirty();
	});

	$('#eo-lp-add-ip-rule-btn').on('click', function() {
		var ipVal = $.trim($('#eo-lp-new-ip-val').val());
		var actionVal = $('#eo-lp-new-ip-action').val();

		if (!ipVal) {
			alert('Veuillez saisir une adresse IP ou un bloc CIDR.');
			return;
		}

		// Basic IP/CIDR validation
		var ipv4CidrPattern = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}(?:\/[0-9]{1,2})?$/;
		if (!ipv4CidrPattern.test(ipVal)) {
			if (ipVal.indexOf(':') === -1) {
				alert('Le format de l\'adresse IP ou CIDR est invalide (ex: 192.168.1.1 ou 192.168.1.0/24).');
				return;
			}
		}

		localIpRules.push({
			ip: ipVal,
			action: actionVal
		});

		$('#eo-lp-new-ip-val').val('');
		$('#eo-lp-ip-rules-hidden').val(JSON.stringify(localIpRules));
		renderIpRules(localIpRules);
		showFormDirty();
	});

	$('#eo-lp-email-filtering-active').on('change', function() {
		$('.eo-lp-email-rules-group').toggle($(this).is(':checked'));
	});

	function ruleToRegexJS(rule) {
		if (rule.indexOf('!') === 0) {
			rule = rule.substring(1);
		}
		rule = rule.trim();

		var escaped = rule.replace(/[-\/\\^$*+?.()|[\]{}]/g, function(match) {
			if (match === '*') return '*';
			return '\\' + match;
		});

		if (rule.indexOf('*') !== -1) {
			var pattern = escaped.replace(/\*/g, '.*');
			return new RegExp('^' + pattern + '$', 'i');
		}

		if (rule.indexOf('@') === 0) {
			var domainRule = rule.substring(1);
			var escapedDomain = domainRule.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
			if (domainRule.indexOf('.') !== -1) {
				return new RegExp('@' + escapedDomain + '$', 'i');
			} else {
				return new RegExp('@' + escapedDomain + '(\\..+)?$', 'i');
			}
		}

		var escapedExact = rule.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
		return new RegExp('^' + escapedExact + '$', 'i');
	}

	function isEmailAllowedJS(email, rulesStr) {
		if (!rulesStr) return true;
		email = email.trim().toLowerCase();
		if (email.indexOf('@') === -1) {
			return false;
		}
		var rules = rulesStr.split(',').map(function(r) { return r.trim(); }).filter(Boolean);
		
		var hasAllowRules = false;
		var emailAllowed = false;

		for (var i = 0; i < rules.length; i++) {
			var rule = rules[i];
			var isBlockRule = (rule.indexOf('!') === 0);
			if (!isBlockRule) {
				hasAllowRules = true;
			}

			var regex = ruleToRegexJS(rule);
			if (regex.test(email)) {
				if (isBlockRule) {
					return false;
				} else {
					emailAllowed = true;
				}
			}
		}

		if (hasAllowRules) {
			return emailAllowed;
		}

		return true;
	}

	function runEmailTest() {
		var email = $.trim($('#eo-lp-email-test-input').val());
		var rulesStr = $('#eo-lp-email-rules').val();
		var $result = $('#eo-lp-email-test-result');

		if (!email) {
			$result.hide().text('').attr('style', '');
			return;
		}

		var allowed = isEmailAllowedJS(email, rulesStr);
		if (allowed) {
			$result.show()
				.text('OK')
				.attr('style', 'font-size: 11px; font-weight: bold; border-radius: 4px; padding: 4px 10px; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;');
		} else {
			$result.show()
				.text('KO')
				.attr('style', 'font-size: 11px; font-weight: bold; border-radius: 4px; padding: 4px 10px; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;');
		}
	}

	function syncEmailRules() {
		var blocked = $('#eo-lp-email-rules-blocked').val().split(',').map(function(r) { return r.trim(); }).filter(Boolean);
		var allowed = $('#eo-lp-email-rules-allowed').val().split(',').map(function(r) { return r.trim(); }).filter(Boolean);
		var finalRules = [];
		for (var i = 0; i < blocked.length; i++) {
			finalRules.push('!' + blocked[i].replace(/^!/, ''));
		}
		for (var j = 0; j < allowed.length; j++) {
			finalRules.push(allowed[j]);
		}
		$('#eo-lp-email-rules').val(finalRules.join(', '));
		runEmailTest();
	}

	$(document).on('input keyup change', '#eo-lp-email-rules-blocked, #eo-lp-email-rules-allowed', function() {
		syncEmailRules();
	});

	$(document).on('input keyup change', '#eo-lp-email-test-input', function() {
		runEmailTest();
	});

	// Email filter card sub-toggle handler
	$('.eo-lp-email-filter-toggle').on('change', function() {
		var $checkbox = $(this);
		var active = $checkbox.is(':checked');
		var $card = $checkbox.closest('.eo-lp-card');
		var type = $card.data('type');

		$checkbox.prop('disabled', true);

		$.ajax({
			url: eoToolsLandingPagesAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'eo_save_landing_page_settings',
				nonce: eoToolsLandingPagesAdmin.nonce,
				type: type,
				active: active ? 'true' : 'false',
				email_filter_toggle: '1'
			},
			success: function(response) {
				$checkbox.prop('disabled', false);
				if (response.success) {
					config = response.data.settings;
					window.eoLandingPagesConfig = config;
					
					// Sync form toggle if editing the same page
					if (activeType === type) {
						$('#eo-lp-email-filtering-active').prop('checked', active).trigger('change');
						showFormSaved('Filtrage e-mails mis à jour');
					}
				} else {
					$checkbox.prop('checked', !active);
					alert(response.data.message || 'Erreur lors de la modification du filtrage e-mail.');
				}
			},
			error: function() {
				$checkbox.prop('disabled', false);
				$checkbox.prop('checked', !active);
				alert('Impossible de contacter le serveur.');
			}
		});
	});

	// Toggle security wrapper when inherit checkbox changes
	$('#eo-lp-inherit-login-rules').on('change', function() {
		var inherited = $(this).is(':checked');
		if (inherited) {
			$('#eo-lp-register-inherit-banner').show();
			$('#eo-lp-security-settings-wrapper input, #eo-lp-security-settings-wrapper textarea, #eo-lp-security-settings-wrapper select, #eo-lp-security-settings-wrapper button').prop('disabled', true);
			
			// Load login settings for display
			var loginConfig = config['login'] || {};
			var emailFiltering = loginConfig.email_filtering_active === true || loginConfig.email_filtering_active === 'true' || loginConfig.email_filtering_active === 1 || loginConfig.email_filtering_active === '1';
			$('#eo-lp-email-filtering-active').prop('checked', emailFiltering);
			$('.eo-lp-email-rules-group').toggle(emailFiltering);
			var fullRules = loginConfig.email_rules || '';
			$('#eo-lp-email-rules').val(fullRules);
			var rulesArr = fullRules.split(',').map(function(r) { return r.trim(); }).filter(Boolean);
			var blockedRules = [], allowedRules = [];
			for (var i = 0; i < rulesArr.length; i++) {
				if (rulesArr[i].indexOf('!') === 0) blockedRules.push(rulesArr[i].substring(1));
				else allowedRules.push(rulesArr[i]);
			}
			$('#eo-lp-email-rules-blocked').val(blockedRules.join(', '));
			$('#eo-lp-email-rules-allowed').val(allowedRules.join(', '));
			var localIpRules = Array.isArray(loginConfig.ip_rules) ? loginConfig.ip_rules : [];
			$('#eo-lp-ip-rules-hidden').val(JSON.stringify(localIpRules));
			renderIpRules(localIpRules);
			// Ensure dynamically created elements are disabled
			$('#eo-lp-security-settings-wrapper input, #eo-lp-security-settings-wrapper textarea, #eo-lp-security-settings-wrapper select, #eo-lp-security-settings-wrapper button').prop('disabled', true);
		} else {
			$('#eo-lp-register-inherit-banner').hide();
			$('#eo-lp-security-settings-wrapper input, #eo-lp-security-settings-wrapper textarea, #eo-lp-security-settings-wrapper select, #eo-lp-security-settings-wrapper button').prop('disabled', false);
			
			// Load local register settings
			var pageConfig = config[activeType] || {};
			var emailFiltering = pageConfig.email_filtering_active === true || pageConfig.email_filtering_active === 'true' || pageConfig.email_filtering_active === 1 || pageConfig.email_filtering_active === '1';
			$('#eo-lp-email-filtering-active').prop('checked', emailFiltering);
			$('.eo-lp-email-rules-group').toggle(emailFiltering);
			var fullRules = pageConfig.email_rules || '';
			$('#eo-lp-email-rules').val(fullRules);
			var rulesArr = fullRules.split(',').map(function(r) { return r.trim(); }).filter(Boolean);
			var blockedRules = [], allowedRules = [];
			for (var i = 0; i < rulesArr.length; i++) {
				if (rulesArr[i].indexOf('!') === 0) blockedRules.push(rulesArr[i].substring(1));
				else allowedRules.push(rulesArr[i]);
			}
			$('#eo-lp-email-rules-blocked').val(blockedRules.join(', '));
			$('#eo-lp-email-rules-allowed').val(allowedRules.join(', '));
			var localIpRules = Array.isArray(pageConfig.ip_rules) ? pageConfig.ip_rules : [];
			$('#eo-lp-ip-rules-hidden').val(JSON.stringify(localIpRules));
			renderIpRules(localIpRules);
		}
	});

	// Link from banner to login settings
	$('#eo-lp-link-to-login').on('click', function(e) {
		e.preventDefault();
		openEditor('login');
	});

	function fetchLoginLogs() {
		var $tbody = $('#eo-lp-logs-tbody');
		$tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px; color: #64748b;">Chargement des données...</td></tr>');

		$.ajax({
			url: eoToolsLandingPagesAdmin.ajaxUrl,
			type: 'GET',
			data: {
				action: 'eo_get_login_logs',
				nonce: eoToolsLandingPagesAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					var logs = response.data.logs;
					$tbody.empty();
					if (logs.length === 0) {
						$tbody.append('<tr><td colspan="5" style="text-align: center; padding: 20px; color: #64748b;">Aucune tentative enregistrée.</td></tr>');
						return;
					}

					logs.forEach(function(log) {
						var statusBadge = '';
						var badgeStyle = 'padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block;';
						if (log.status === 'success') {
							statusBadge = '<span style="' + badgeStyle + ' background: #dcfce7; color: #15803d;">' + $('<div/>').text(log.status_label).html() + '</span>';
						} else if (log.status === 'failed') {
							statusBadge = '<span style="' + badgeStyle + ' background: #fee2e2; color: #b91c1c;">' + $('<div/>').text(log.status_label).html() + '</span>';
						} else {
							statusBadge = '<span style="' + badgeStyle + ' background: #fef3c7; color: #d97706;">' + $('<div/>').text(log.status_label).html() + '</span>';
						}

						var ua = log.user_agent || '';
						var shortUa = ua.length > 30 ? ua.substring(0, 30) + '...' : ua;

						var row = '<tr>' +
							'<td style="padding: 8px; font-size: 12px; vertical-align: middle;">' + $('<div/>').text(log.formatted_time).html() + '</td>' +
							'<td style="padding: 8px; font-size: 12px; vertical-align: middle; font-family: monospace;">' + $('<div/>').text(log.ip).html() + '</td>' +
							'<td style="padding: 8px; font-size: 12px; vertical-align: middle; font-weight: 600;">' + $('<div/>').text(log.username || '-').html() + '</td>' +
							'<td style="padding: 8px; vertical-align: middle;">' + statusBadge + '</td>' +
							'<td style="padding: 8px; font-size: 11px; vertical-align: middle; color: #64748b;" title="' + $('<div/>').attr('title', ua).attr('title') + '">' + $('<div/>').text(shortUa).html() + '</td>' +
							'</tr>';
						$tbody.append(row);
					});
				} else {
					$tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px; color: #d63638;">' + $('<div/>').text(response.data.message || 'Erreur lors du chargement des logs.').html() + '</td></tr>');
				}
			},
			error: function() {
				$tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px; color: #d63638;">Impossible de contacter le serveur pour charger les logs.</td></tr>');
			}
		});
	}

	$('#eo-lp-clear-logs-btn').on('click', function(e) {
		e.preventDefault();
		if (!confirm('Êtes-vous sûr de vouloir vider l\'historique des tentatives de connexion ? Cette action est irréversible.')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true).css('opacity', '0.5');

		$.ajax({
			url: eoToolsLandingPagesAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'eo_clear_login_logs',
				nonce: eoToolsLandingPagesAdmin.nonce
			},
			success: function(response) {
				$btn.prop('disabled', false).css('opacity', '1');
				if (response.success) {
					fetchLoginLogs();
					alert(response.data.message || 'Journal vidé.');
				} else {
					alert(response.data.message || 'Erreur lors de la suppression des logs.');
				}
			},
			error: function() {
				$btn.prop('disabled', false).css('opacity', '1');
				alert('Impossible de contacter le serveur.');
			}
		});
	});

	function openEditor(type) {
		activeType = type;
		var pageConfig = config[type] || {};
		
		// Fill form fields
		$('#eo-lp-form-type').val(type);
		$('#eo-lp-form-title').val(pageConfig.title || '');
		$('#eo-lp-form-description').val(pageConfig.description || '');
		$('#eo-lp-form-style').val(pageConfig.style || 'minimalist');

		// Set color pickers and text values
		$('#eo-lp-form-bg-color').val(pageConfig.bg_color || '#000000');
		$('#eo-lp-form-bg-color-text').val((pageConfig.bg_color || '#000000').toUpperCase());

		$('#eo-lp-form-text-color').val(pageConfig.text_color || '#ffffff');
		$('#eo-lp-form-text-color-text').val((pageConfig.text_color || '#ffffff').toUpperCase());

		$('#eo-lp-form-accent-color').val(pageConfig.accent_color || '#0066FF');
		$('#eo-lp-form-accent-color-text').val((pageConfig.accent_color || '#0066FF').toUpperCase());

		// Adjust fields display and labels
		adjustFieldLabels();

		// Toggle custom hints and security section depending on type
		$('.eo-lp-form-login-hint').toggle(type === 'login');
		$('.eo-lp-form-404-hint').toggle(type === '404');

		if (type === 'login' || type === 'register') {
			$('#eo-lp-login-security-section').show();

			if (type === 'register') {
				$('#eo-lp-register-inherit-group').show();
				$('#eo-lp-register-success-action-group').show();
				$('#eo-lp-form-success-action').val(pageConfig.success_action || 'none');
				var inheritRules = pageConfig.inherit_login_rules === true || pageConfig.inherit_login_rules === 'true' || pageConfig.inherit_login_rules === 1 || pageConfig.inherit_login_rules === '1';
				$('#eo-lp-inherit-login-rules').prop('checked', inheritRules).trigger('change');
				$('.eo-lp-logs-box').hide();
			} else {
				$('#eo-lp-register-inherit-group').hide();
				$('#eo-lp-register-success-action-group').hide();
				$('#eo-lp-register-inherit-banner').hide();
				$('#eo-lp-security-settings-wrapper input, #eo-lp-security-settings-wrapper textarea, #eo-lp-security-settings-wrapper select, #eo-lp-security-settings-wrapper button').prop('disabled', false);

				var emailFiltering = pageConfig.email_filtering_active === true || pageConfig.email_filtering_active === 'true' || pageConfig.email_filtering_active === 1 || pageConfig.email_filtering_active === '1';
				$('#eo-lp-email-filtering-active').prop('checked', emailFiltering);
				$('.eo-lp-email-rules-group').toggle(emailFiltering);

				var fullRules = pageConfig.email_rules || '';
				$('#eo-lp-email-rules').val(fullRules);
				var blockedRules = [];
				var allowedRules = [];
				var rulesArr = fullRules.split(',').map(function(r) { return r.trim(); }).filter(Boolean);
				for (var i = 0; i < rulesArr.length; i++) {
					if (rulesArr[i].indexOf('!') === 0) {
						blockedRules.push(rulesArr[i].substring(1));
					} else {
						allowedRules.push(rulesArr[i]);
					}
				}
				$('#eo-lp-email-rules-blocked').val(blockedRules.join(', '));
				$('#eo-lp-email-rules-allowed').val(allowedRules.join(', '));
				$('#eo-lp-log-limit').val(pageConfig.log_limit || 1000);

				// Clear email test input & result
				$('#eo-lp-email-test-input').val('');
				$('#eo-lp-email-test-result').hide().text('');

				// IP rules
				localIpRules = Array.isArray(pageConfig.ip_rules) ? pageConfig.ip_rules : [];
				$('#eo-lp-ip-rules-hidden').val(JSON.stringify(localIpRules));
				renderIpRules(localIpRules);

				$('.eo-lp-logs-box').show();
				fetchLoginLogs();
			}
		} else {
			$('#eo-lp-login-security-section').hide();
		}

		// Update panel title icon and text
		var card = $('.eo-lp-card[data-type="' + type + '"]');
		var cardIconClass = card.find('.eo-lp-card-icon').attr('class').replace('eo-lp-card-icon', '').trim();
		var cardTitle = card.find('.eo-lp-card-title').text();

		$('.eo-lp-editor-icon').attr('class', 'dashicons eo-lp-editor-icon ' + cardIconClass);
		$('.eo-lp-editor-title-text').text('Configuration - ' + cardTitle);

		// Style edit button preview
		var previewUrl = config.homeUrl + '?eo_preview_landing_page=' + type;
		$('.eo-lp-form-preview-btn').attr('href', previewUrl);

		// Clear status text
		$('.eo-lp-editor-status-text').text('');
		$('#eo-lp-save-btn').css('background-color', '#2271b1'); // reset color

		// Scroll to panel and display it
		$('.eo-lp-editor-panel').slideDown(300, function() {
			$('html, body').animate({
				scrollTop: $('.eo-lp-editor-panel').offset().top - 50
			}, 400);
		});
	}

	// Close Panel Button
	$('.eo-lp-editor-close-btn').on('click', function() {
		$('.eo-lp-editor-panel').slideUp(300);
		activeType = '';
	});

	// Form Submission (Save Settings)
	$('#eo-lp-editor-form').on('submit', function(e) {
		e.preventDefault();
		if (!activeType) return;

		showFormSaving();

		var formData = {
			action: 'eo_save_landing_page_settings',
			nonce: eoToolsLandingPagesAdmin.nonce,
			type: activeType,
			title: $('#eo-lp-form-title').val(),
			description: $('#eo-lp-form-description').val(),
			style: $('#eo-lp-form-style').val(),
			bg_color: $('#eo-lp-form-bg-color').val(),
			text_color: $('#eo-lp-form-text-color').val(),
			accent_color: $('#eo-lp-form-accent-color').val(),
			active: $('.eo-lp-card[data-type="' + activeType + '"] .eo-lp-toggle-checkbox').is(':checked') ? 'true' : 'false'
		};

		if (activeType === 'login' || activeType === 'register') {
			var wasDisabled = $('#eo-lp-email-filtering-active').prop('disabled');
			if (wasDisabled) {
				// if fields are disabled due to inheritance, we still save the disabled fields? 
				// No, if inheriting, we just save the local ones as they were or empty. Wait, jQuery doesn't care about disabled for .val()!
				// But we do care for is(':checked') which works on disabled checkboxes too.
			}
			formData.email_filtering_active = $('#eo-lp-email-filtering-active').is(':checked') ? 'true' : 'false';
			formData.email_rules = $('#eo-lp-email-rules').val();
			formData.ip_rules = $('#eo-lp-ip-rules-hidden').val();
			
			if (activeType === 'login') {
				formData.log_limit = $('#eo-lp-log-limit').val();
			} else if (activeType === 'register') {
				formData.inherit_login_rules = $('#eo-lp-inherit-login-rules').is(':checked') ? 'true' : 'false';
				formData.success_action = $('#eo-lp-form-success-action').val();
			}
		}

		$.ajax({
			url: eoToolsLandingPagesAdmin.ajaxUrl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					// Update global config object
					config = response.data.settings;
					window.eoLandingPagesConfig = config;

					// Sync email filter checkbox on card
					var emailFiltering = config.login.email_filtering_active === true || config.login.email_filtering_active === 'true' || config.login.email_filtering_active === 1 || config.login.email_filtering_active === '1';
					$('.eo-lp-email-filter-toggle').prop('checked', emailFiltering);

					// Dynamically update the admin bar status badge
					updateAdminBarBadge(config);

					// Visual state updates
					showFormSaved('Paramètres enregistrés avec succès !');

					// Check if Coming Soon or Maintenance was turned off because of mutual exclusion
					if (formData.active === 'true') {
						if (activeType === 'coming_soon') {
							var $mCard = $('.eo-lp-card[data-type="maintenance"]');
							$mCard.removeClass('active');
							$mCard.find('.eo-lp-toggle-checkbox').prop('checked', false);
							$mCard.find('.eo-lp-toggle-label').text('INACTIF').removeClass('active');
						} else if (activeType === 'maintenance') {
							var $csCard = $('.eo-lp-card[data-type="coming_soon"]');
							$csCard.removeClass('active');
							$csCard.find('.eo-lp-toggle-checkbox').prop('checked', false);
							$csCard.find('.eo-lp-toggle-label').text('INACTIF').removeClass('active');
						}
					}

					if (activeType === 'login') {
						fetchLoginLogs();
					}
				} else {
					showFormError(response.data.message || 'Erreur lors de l\'enregistrement.');
				}
			},
			error: function() {
				showFormError('Impossible de contacter le serveur.');
			}
		});
	});
});
