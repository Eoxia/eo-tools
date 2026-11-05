/**
 * EO Tools - MCP admin interface
 */
( function ( $ ) {
	'use strict';

	var data   = eoToolsMcpAdmin;
	var labels = data.labels;

	function post( action, params ) {
		return $.post( data.ajaxUrl, $.extend( { action: action, security: data.nonce }, params || {} ) );
	}

	function errorMessage( response, xhr ) {
		if ( response && response.data && response.data.message ) {
			return response.data.message;
		}
		if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
			return xhr.responseJSON.data.message;
		}
		return labels.error;
	}

	/* ---------------------------------------------------------------------
	 * Clipboard
	 * ------------------------------------------------------------------ */

	function copyText( text, $button ) {
		var done = function () {
			$button.text( labels.copied );
			setTimeout( function () {
				$button.text( labels.copy );
			}, 2000 );
		};

		// The Clipboard API is only available on HTTPS or localhost.
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}

		var $tmp = $( '<textarea readonly>' ).val( text ).css( { position: 'fixed', top: 0, left: '-9999px' } ).appendTo( 'body' );
		$tmp[ 0 ].select();
		document.execCommand( 'copy' );
		$tmp.remove();
		done();
	}

	function codeBlock( title, text ) {
		var $block  = $( '<div class="eo-mcp-code">' );
		var $header = $( '<div class="eo-mcp-code-header">' ).appendTo( $block );
		var $copy   = $( '<button type="button" class="button button-small">' ).text( labels.copy );

		$( '<span>' ).text( title || '' ).appendTo( $header );
		$copy.on( 'click', function () {
			copyText( text, $copy );
		} ).appendTo( $header );
		$( '<pre>' ).text( text ).appendTo( $block );

		return $block;
	}

	/* ---------------------------------------------------------------------
	 * Configuration snippets
	 * ------------------------------------------------------------------ */

	function json( value ) {
		return JSON.stringify( value, null, 2 );
	}

	function stdioConfig( connection ) {
		var servers = {};
		servers[ data.serverName ] = {
			command: 'npx',
			args: [ '-y', '@automattic/mcp-wordpress-remote@latest' ],
			env: {
				WP_API_URL: connection.endpoint,
				WP_API_USERNAME: connection.username,
				WP_API_PASSWORD: connection.password
			}
		};
		return json( { mcpServers: servers } );
	}

	function claudeCodeCommand( authorization ) {
		var command = 'claude mcp add --transport http ' + data.serverName + ' "' + data.endpoint + '"';

		return authorization ? command + ' --header "Authorization: ' + authorization + '"' : command;
	}

	/**
	 * Snippets shown as soon as a client is selected (no secret involved).
	 */
	function clientSnippets( client ) {
		if ( 'claude-code' === client.format ) {
			return [ codeBlock( labels.terminal, claudeCodeCommand() ) ];
		}
		if ( 'url' === client.format ) {
			return [ codeBlock( labels.connectorUrl, data.endpoint ) ];
		}
		return [];
	}

	/**
	 * Snippets shown once an Application Password has been generated.
	 */
	function passwordSnippets( client, connection ) {
		if ( 'offline' === client.format ) {
			return [
				codeBlock( labels.desktopConfig, stdioConfig( connection ) ),
				codeBlock( labels.codeCommand, claudeCodeCommand( connection.authorization ) )
			];
		}

		return [
			codeBlock( labels.username, connection.username ),
			codeBlock( labels.password, connection.password ),
			codeBlock( labels.header, 'Authorization: ' + connection.authorization )
		];
	}

	/* ---------------------------------------------------------------------
	 * Connections list
	 * ------------------------------------------------------------------ */

	function renderConnections( connections ) {
		var $list = $( '#eo-mcp-connections-list' ).empty();

		if ( ! connections.length ) {
			$( '<tr>' ).append( $( '<td colspan="4" class="eo-mcp-soon">' ).text( labels.noConnection ) ).appendTo( $list );
			return;
		}

		connections.forEach( function ( connection ) {
			var $row    = $( '<tr>' );
			var $status = $( '<span class="eo-mcp-badge">' ).text( connection.last_seen_label );
			var $revoke = $( '<button type="button" class="button-link button-link-delete">' ).text( labels.revoke );

			if ( connection.last_seen ) {
				$status.addClass( 'is-success' );
			}

			$revoke.on( 'click', function () {
				if ( ! window.confirm( labels.revokeConfirm ) ) {
					return;
				}
				$revoke.prop( 'disabled', true );
				post( 'eo_tools_mcp_revoke_connection', { uuid: connection.uuid } ).done( function ( response ) {
					if ( response.success ) {
						renderConnections( response.data.connections );
					} else {
						$revoke.prop( 'disabled', false );
						window.alert( errorMessage( response ) );
					}
				} ).fail( function ( xhr ) {
					$revoke.prop( 'disabled', false );
					window.alert( errorMessage( null, xhr ) );
				} );
			} );

			$( '<td>' ).text( connection.name ).appendTo( $row );
			$( '<td>' ).text( connection.created_label ).appendTo( $row );
			$( '<td>' ).append( $status ).appendTo( $row );
			$( '<td class="eo-mcp-actions">' ).append( $revoke ).appendTo( $row );
			$list.append( $row );
		} );
	}

	$( function () {

		/* Step 1: install the MCP Adapter. */
		var $installButton   = $( '#eo-mcp-install-adapter' );
		var $installFeedback = $( '#eo-mcp-install-feedback' );

		$installButton.on( 'click', function () {
			$installButton.prop( 'disabled', true ).addClass( 'updating-message' );
			$installFeedback.removeClass( 'is-error' ).text( labels.installing );

			post( 'eo_tools_mcp_install_adapter' )
				.done( function ( response ) {
					if ( response.success ) {
						window.location.reload();
						return;
					}
					showInstallError( errorMessage( response ) );
				} )
				.fail( function ( xhr ) {
					showInstallError( errorMessage( null, xhr ) );
				} );
		} );

		function showInstallError( message ) {
			$installButton.prop( 'disabled', false ).removeClass( 'updating-message' );
			$installFeedback.addClass( 'is-error' ).text( message );
		}

		/* Step 2: connect an AI client. */
		var $createButton   = $( '#eo-mcp-create-connection' );
		var $createFeedback = $( '#eo-mcp-connection-feedback' );
		var $result         = $( '#eo-mcp-connection-result' );
		var $warning        = $( '#eo-mcp-client-warning' );
		var $unavailable    = $( '#eo-mcp-client-unavailable' );
		var $link           = $( '#eo-mcp-client-link' );

		function setStatus( $el, status, message ) {
			var icon = 'ok' === status ? 'yes-alt' : ( 'warning' === status ? 'warning' : 'dismiss' );
			var css  = 'ok' === status ? 'is-success' : ( 'warning' === status ? 'is-warning' : 'is-error' );

			$el.attr( 'class', 'eo-mcp-status ' + css )
				.empty()
				.append( $( '<span class="dashicons">' ).addClass( 'dashicons-' + icon ) )
				.append( document.createTextNode( ' ' + message ) )
				.prop( 'hidden', false );
		}

		function showMessage( $el, message ) {
			$el.find( '.eo-mcp-status-text' ).text( message );
			$el.prop( 'hidden', ! message );
		}

		function currentClient() {
			return data.clients[ $( 'input[name="eo-mcp-client"]:checked' ).val() ];
		}

		function selectClient() {
			var client = currentClient();
			var $steps = $( '#eo-mcp-client-steps' ).empty();

			$createFeedback.removeClass( 'is-error' ).text( '' );
			$result.prop( 'hidden', true );

			if ( ! client ) {
				return;
			}

			client.steps.forEach( function ( step ) {
				$( '<li>' ).text( step ).appendTo( $steps );
			} );
			$( '#eo-mcp-client-snippets' ).empty().append( clientSnippets( client ) );

			// Claude and ChatGPT reach the site from the Internet.
			showMessage( $warning, 'url' === client.format && client.oauth ? data.oauthWarning : '' );

			if ( client.link ) {
				$link.attr( 'href', client.link.url ).text( client.link.label ).prop( 'hidden', false );
			} else {
				$link.prop( 'hidden', true );
			}

			if ( client.password && data.appPasswordsUnavailable ) {
				$createButton.prop( 'hidden', true );
				showMessage( $unavailable, data.appPasswordsUnavailable );
			} else {
				showMessage( $unavailable, '' );
				$createButton
					.text( 'offline' === client.format ? labels.genConfig : labels.genPassword )
					.toggleClass( 'button-primary', true === client.password )
					.prop( 'hidden', ! client.password );
			}
		}

		$( 'input[name="eo-mcp-client"]' ).on( 'change', selectClient );
		selectClient();

		$createButton.on( 'click', function () {
			var clientId = $( 'input[name="eo-mcp-client"]:checked' ).val();
			var client   = data.clients[ clientId ];

			$createButton.prop( 'disabled', true ).addClass( 'updating-message' );
			$createFeedback.removeClass( 'is-error' ).text( labels.generating );
			$result.prop( 'hidden', true );

			post( 'eo_tools_mcp_create_connection', { client: clientId } )
				.done( function ( response ) {
					if ( ! response.success ) {
						$createFeedback.addClass( 'is-error' ).text( errorMessage( response ) );
						return;
					}

					var connection = response.data;

					$createFeedback.text( '' );
					setStatus( $( '#eo-mcp-connection-test' ), connection.test.status, connection.test.message );
					$( '#eo-mcp-connection-snippets' ).empty().append( passwordSnippets( client, connection ) );
					$result.prop( 'hidden', false );

					renderConnections( connection.connections );
				} )
				.fail( function ( xhr ) {
					$createFeedback.addClass( 'is-error' ).text( errorMessage( null, xhr ) );
				} )
				.always( function () {
					$createButton.prop( 'disabled', false ).removeClass( 'updating-message' );
				} );
		} );

		/* Step 3: follow connections usage, refreshed automatically. */
		if ( $( '#eo-mcp-connections-list' ).length ) {
			var lastSeen = function ( connections ) {
				return connections.reduce( function ( max, connection ) {
					return Math.max( max, connection.last_seen );
				}, 0 );
			};
			var baseline  = lastSeen( data.connections );
			var startedAt = Date.now();

			renderConnections( data.connections );

			var timer = setInterval( function () {
				// Stop polling after 30 minutes; skip while the tab is hidden.
				if ( Date.now() - startedAt > 30 * 60 * 1000 ) {
					clearInterval( timer );
					return;
				}
				if ( document.hidden ) {
					return;
				}

				post( 'eo_tools_mcp_get_connections' ).done( function ( response ) {
					if ( ! response.success ) {
						return;
					}
					renderConnections( response.data.connections );

					if ( lastSeen( response.data.connections ) > baseline ) {
						showMessage( $( '#eo-mcp-connected' ), labels.connected );
					}
				} );
			}, 5000 );
		}

		/* Abilities tab: enable / disable abilities. */
		var $abilitiesFeedback = $( '#eo-mcp-abilities-feedback' );
		var $toggles           = $( '.eo-mcp-ability-toggle' );

		function saveAbilities( names, enabled ) {
			$toggles.prop( 'disabled', true );
			$abilitiesFeedback.removeClass( 'is-error' ).text( '' );

			post( 'eo_tools_mcp_toggle_abilities', { names: names, enabled: enabled ? '1' : '0' } )
				.done( function ( response ) {
					if ( ! response.success ) {
						$abilitiesFeedback.addClass( 'is-error' ).text( errorMessage( response ) );
						return;
					}
					$toggles.each( function () {
						var state = response.data.states[ this.value ];
						if ( 'undefined' !== typeof state ) {
							this.checked = state;
						}
					} );
					$abilitiesFeedback.text( labels.saved );
				} )
				.fail( function ( xhr ) {
					$abilitiesFeedback.addClass( 'is-error' ).text( errorMessage( null, xhr ) );
				} )
				.always( function () {
					$toggles.prop( 'disabled', false );
				} );
		}

		$toggles.on( 'change', function () {
			saveAbilities( [ this.value ], this.checked );
		} );

		$( '[data-eo-mcp-bulk]' ).on( 'click', function () {
			var names = $toggles.map( function () {
				return this.value;
			} ).get();
			saveAbilities( names, '1' === $( this ).attr( 'data-eo-mcp-bulk' ) );
		} );
	} );
}( jQuery ) );
