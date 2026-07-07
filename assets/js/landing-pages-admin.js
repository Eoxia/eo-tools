/**
 * JavaScript for EO Tools - Landing Pages admin interface.
 */

jQuery( function ( $ ) {
	var admin  = window.eoToolsLandingPagesAdmin || {};
	var i18n   = admin.i18n || {};
	var labels = admin.labels || {};
	var config = window.eoLandingPagesConfig || {};
	var activeType = '';

	/**
	 * Display a native WordPress admin notice (no browser alert()).
	 */
	function showNotice( message, type ) {
		type = type || 'success';
		var $wrap = $( '.eo-landing-pages-admin-wrapper' );
		$wrap.find( '.eo-lp-inline-notice' ).remove();

		var $notice = $(
			'<div class="notice notice-' + type + ' is-dismissible eo-lp-inline-notice"><p></p></div>'
		);
		$notice.find( 'p' ).text( message );
		$wrap.find( '.wp-header-end' ).after( $notice );

		window.setTimeout( function () {
			$notice.fadeOut( 400, function () {
				$( this ).remove();
			} );
		}, 4000 );
	}

	/**
	 * Dynamically update the admin bar status badge.
	 */
	function updateAdminBarBadge( settings ) {
		if ( ! admin.adminUrl ) {
			return;
		}

		var csActive = isActive( settings.coming_soon );
		var mActive  = isActive( settings.maintenance );

		var $adminBarSecondary = $( '#wp-admin-bar-top-secondary' );
		var $badge = $( '#wp-admin-bar-eo-landing-pages-status' );

		if ( ! csActive && ! mActive ) {
			$badge.remove();
			return;
		}

		var label = '';
		var badgeClass = 'eo-landing-pages-alert-badge';

		if ( csActive && mActive ) {
			label = labels.both;
			badgeClass += ' eo-alert-red';
		} else if ( csActive ) {
			label = labels.coming_soon;
			badgeClass += ' eo-alert-orange';
		} else {
			label = labels.maintenance;
			badgeClass += ' eo-alert-red';
		}

		if ( $badge.length === 0 ) {
			var html = '<li id="wp-admin-bar-eo-landing-pages-status" class="' + badgeClass + '">' +
				'<a class="ab-item" href="' + admin.adminUrl + '"></a>' +
				'</li>';
			var $node = $( html );
			$node.find( '.ab-item' ).text( label );
			$adminBarSecondary.prepend( $node );
		} else {
			$badge.attr( 'class', badgeClass );
			$badge.find( '> .ab-item' ).text( label );
		}
	}

	function isActive( conf ) {
		return conf && ( conf.active === true || conf.active === 'true' || conf.active === 1 || conf.active === '1' );
	}

	/**
	 * Sync a color picker with its text input.
	 */
	function bindColorPicker( pickerId, textId ) {
		$( pickerId ).on( 'input', function () {
			$( textId ).val( $( this ).val().toUpperCase() );
			showFormDirty();
		} );
		$( textId ).on( 'input', function () {
			var val = $( this ).val();
			if ( val.match( /^#[0-9A-F]{6}$/i ) ) {
				$( pickerId ).val( val );
				showFormDirty();
			}
		} );
	}

	bindColorPicker( '#eo-lp-form-bg-color', '#eo-lp-form-bg-color-text' );
	bindColorPicker( '#eo-lp-form-text-color', '#eo-lp-form-text-color-text' );
	bindColorPicker( '#eo-lp-form-accent-color', '#eo-lp-form-accent-color-text' );

	$( '#eo-lp-editor-form input, #eo-lp-editor-form textarea, #eo-lp-editor-form select' ).on( 'input change', function () {
		showFormDirty();
	} );

	function showFormDirty() {
		$( '.eo-lp-editor-status-text' ).text( i18n.unsaved ).css( 'color', '#2271b1' );
		$( '#eo-lp-save-btn' ).removeClass( 'button-disabled' ).css( 'background-color', '#2271b1' );
	}

	function showFormSaving() {
		$( '.eo-lp-editor-status-text' ).text( i18n.saving ).css( 'color', '#64748b' );
	}

	function showFormSaved( msg ) {
		$( '.eo-lp-editor-status-text' ).text( msg || i18n.saved ).css( 'color', '#10b981' );
		$( '#eo-lp-save-btn' ).css( 'background-color', '#cbd5e1' );
	}

	function showFormError( msg ) {
		$( '.eo-lp-editor-status-text' ).text( msg || i18n.error ).css( 'color', '#d63638' );
	}

	/**
	 * Disable the opposite exclusive card (coming soon <-> maintenance).
	 */
	function disableOppositeCard( type ) {
		var opposite = null;
		if ( type === 'coming_soon' ) {
			opposite = 'maintenance';
		} else if ( type === 'maintenance' ) {
			opposite = 'coming_soon';
		}
		if ( ! opposite ) {
			return;
		}
		var $card = $( '.eo-lp-card[data-type="' + opposite + '"]' );
		$card.removeClass( 'active' );
		$card.find( '.eo-lp-toggle-checkbox' ).prop( 'checked', false );
		$card.find( '.eo-lp-toggle-label' ).text( i18n.inactive ).removeClass( 'active' );
	}

	// Toggle active switches.
	$( '.eo-lp-toggle-checkbox' ).on( 'change', function () {
		var $checkbox = $( this );
		var $card  = $checkbox.closest( '.eo-lp-card' );
		var type   = $card.data( 'type' );
		var active = $checkbox.is( ':checked' );
		var $label = $checkbox.closest( '.eo-lp-toggle-wrapper' ).find( '.eo-lp-toggle-label' );

		$checkbox.prop( 'disabled', true );
		$label.text( i18n.updating );

		$.ajax( {
			url: admin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'eo_save_landing_page_settings',
				nonce: admin.nonce,
				type: type,
				active: active ? 'true' : 'false',
				active_toggle: '1'
			},
			success: function ( response ) {
				$checkbox.prop( 'disabled', false );
				if ( response.success ) {
					config = response.data.settings;
					window.eoLandingPagesConfig = config;
					updateAdminBarBadge( config );

					if ( active ) {
						$card.addClass( 'active' );
						$label.text( i18n.active ).addClass( 'active' );
						disableOppositeCard( type );
					} else {
						$card.removeClass( 'active' );
						$label.text( i18n.inactive ).removeClass( 'active' );
					}

					if ( activeType === type ) {
						showFormSaved( i18n.stateUpdated );
					}
				} else {
					$checkbox.prop( 'checked', ! active );
					$label.text( active ? i18n.inactive : i18n.active );
					showNotice( ( response.data && response.data.message ) || i18n.toggleError, 'error' );
				}
			},
			error: function () {
				$checkbox.prop( 'disabled', false );
				$checkbox.prop( 'checked', ! active );
				$label.text( active ? i18n.inactive : i18n.active );
				showNotice( i18n.serverError, 'error' );
			}
		} );
	} );

	// Open the editor.
	$( '.eo-lp-edit-btn' ).on( 'click', function () {
		openEditor( $( this ).data( 'type' ) );
	} );

	/**
	 * Adjust the accent color field label based on style and type.
	 */
	function adjustFieldLabels() {
		var style = $( '#eo-lp-form-style' ).val();
		var type  = $( '#eo-lp-form-type' ).val();

		var $accentGroup = $( '#eo-lp-form-accent-color' ).closest( '.eo-lp-form-group' );
		var $accentLabel = $accentGroup.find( 'label' );

		if ( style === 'minimalist' ) {
			// Only the 404 page shows a button in the minimalist style.
			if ( type === 'coming_soon' || type === 'maintenance' ) {
				$accentGroup.hide();
			} else {
				$accentGroup.show();
				$accentLabel.text( i18n.accentButton );
			}
		} else if ( style === 'gradient' ) {
			$accentGroup.show();
			$accentLabel.text( i18n.accentGradient );
		} else if ( style === 'glassmorphism' ) {
			$accentGroup.show();
			$accentLabel.text( i18n.accentGlass );
		}
	}

	$( '#eo-lp-form-style' ).on( 'change', adjustFieldLabels );

	function openEditor( type ) {
		activeType = type;
		var pageConfig = config[ type ] || {};

		$( '#eo-lp-form-type' ).val( type );
		$( '#eo-lp-form-title' ).val( pageConfig.title || '' );
		$( '#eo-lp-form-description' ).val( pageConfig.description || '' );
		$( '#eo-lp-form-style' ).val( pageConfig.style || 'minimalist' );

		$( '#eo-lp-form-bg-color' ).val( pageConfig.bg_color || '#000000' );
		$( '#eo-lp-form-bg-color-text' ).val( ( pageConfig.bg_color || '#000000' ).toUpperCase() );

		$( '#eo-lp-form-text-color' ).val( pageConfig.text_color || '#ffffff' );
		$( '#eo-lp-form-text-color-text' ).val( ( pageConfig.text_color || '#ffffff' ).toUpperCase() );

		$( '#eo-lp-form-accent-color' ).val( pageConfig.accent_color || '#0066FF' );
		$( '#eo-lp-form-accent-color-text' ).val( ( pageConfig.accent_color || '#0066FF' ).toUpperCase() );

		adjustFieldLabels();

		// 404 specific hint (Back to home button).
		$( '.eo-lp-form-404-hint' ).toggle( type === '404' );

		// Update panel title icon and text.
		var $card = $( '.eo-lp-card[data-type="' + type + '"]' );
		var cardIconClass = $card.find( '.eo-lp-card-icon' ).attr( 'class' ).replace( 'eo-lp-card-icon', '' ).trim();
		var cardTitle = $card.find( '.eo-lp-card-title' ).text();

		$( '.eo-lp-editor-icon' ).attr( 'class', 'dashicons eo-lp-editor-icon ' + cardIconClass );
		$( '.eo-lp-editor-title-text' ).text( ( i18n.configuration || 'Configuration' ) + ' - ' + cardTitle );

		$( '.eo-lp-editor-status-text' ).text( '' );
		$( '#eo-lp-save-btn' ).css( 'background-color', '#2271b1' );

		$( '.eo-lp-editor-panel' ).slideDown( 300, function () {
			$( 'html, body' ).animate( {
				scrollTop: $( '.eo-lp-editor-panel' ).offset().top - 50
			}, 400 );
		} );
	}

	// Close the editor.
	$( '.eo-lp-editor-close-btn' ).on( 'click', function () {
		$( '.eo-lp-editor-panel' ).slideUp( 300 );
		activeType = '';
	} );

	// Preview the currently edited (unsaved) values in a new tab.
	$( '.eo-lp-form-preview-btn' ).on( 'click', function () {
		if ( ! activeType ) {
			return;
		}
		var $form = $( '#eo-lp-preview-form' );
		$form.find( '[name="type"]' ).val( activeType );
		$form.find( '[name="title"]' ).val( $( '#eo-lp-form-title' ).val() );
		$form.find( '[name="description"]' ).val( $( '#eo-lp-form-description' ).val() );
		$form.find( '[name="style"]' ).val( $( '#eo-lp-form-style' ).val() );
		$form.find( '[name="bg_color"]' ).val( $( '#eo-lp-form-bg-color' ).val() );
		$form.find( '[name="text_color"]' ).val( $( '#eo-lp-form-text-color' ).val() );
		$form.find( '[name="accent_color"]' ).val( $( '#eo-lp-form-accent-color' ).val() );
		$form.get( 0 ).submit();
	} );

	// Save settings.
	$( '#eo-lp-editor-form' ).on( 'submit', function ( e ) {
		e.preventDefault();
		if ( ! activeType ) {
			return;
		}

		showFormSaving();

		var formData = {
			action: 'eo_save_landing_page_settings',
			nonce: admin.nonce,
			type: activeType,
			title: $( '#eo-lp-form-title' ).val(),
			description: $( '#eo-lp-form-description' ).val(),
			style: $( '#eo-lp-form-style' ).val(),
			bg_color: $( '#eo-lp-form-bg-color' ).val(),
			text_color: $( '#eo-lp-form-text-color' ).val(),
			accent_color: $( '#eo-lp-form-accent-color' ).val(),
			active: $( '.eo-lp-card[data-type="' + activeType + '"] .eo-lp-toggle-checkbox' ).is( ':checked' ) ? 'true' : 'false'
		};

		$.ajax( {
			url: admin.ajaxUrl,
			type: 'POST',
			data: formData,
			success: function ( response ) {
				if ( response.success ) {
					config = response.data.settings;
					window.eoLandingPagesConfig = config;
					updateAdminBarBadge( config );
					showFormSaved( i18n.savedFull );

					if ( formData.active === 'true' ) {
						disableOppositeCard( activeType );
					}
				} else {
					showFormError( ( response.data && response.data.message ) || i18n.error );
				}
			},
			error: function () {
				showFormError( i18n.serverError );
			}
		} );
	} );
} );
