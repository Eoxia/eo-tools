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

	function buildSnippets( format, connection ) {
		var servers = {};

		switch ( format ) {
			case 'npx-json':
				return [ codeBlock( 'claude_desktop_config.json', stdioConfig( connection ) ) ];

			case 'claude-cli':
				return [ codeBlock( 'Terminal', 'claude mcp add --transport http ' + data.serverName + ' "' + connection.endpoint + '" --header "Authorization: ' + connection.authorization + '"' ) ];

			case 'url-json':
				servers[ data.serverName ] = {
					url: connection.endpoint,
					headers: { Authorization: connection.authorization }
				};
				return [ codeBlock( 'mcp.json', json( { mcpServers: servers } ) ) ];

			case 'vscode-json':
				servers[ data.serverName ] = {
					type: 'http',
					url: connection.endpoint,
					headers: { Authorization: connection.authorization }
				};
				return [ codeBlock( '.vscode/mcp.json', json( { servers: servers } ) ) ];

			default:
				return [
					codeBlock( labels.endpoint, connection.endpoint ),
					codeBlock( labels.username, connection.username ),
					codeBlock( labels.password, connection.password ),
					codeBlock( labels.header, 'Authorization: ' + connection.authorization ),
					codeBlock( labels.stdioConfig, stdioConfig( connection ) )
				];
		}
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

		/* Step 2: create a connection. */
		var $createButton   = $( '#eo-mcp-create-connection' );
		var $createFeedback = $( '#eo-mcp-connection-feedback' );
		var $result         = $( '#eo-mcp-connection-result' );

		var $unavailable = $( '#eo-mcp-client-unavailable' );
		var $test        = $( '#eo-mcp-connection-test' );

		function setStatus( $el, status, message ) {
			var icon = 'ok' === status ? 'yes-alt' : ( 'warning' === status ? 'warning' : 'dismiss' );
			var css  = 'ok' === status ? 'is-success' : ( 'warning' === status ? 'is-warning' : 'is-error' );

			$el.attr( 'class', 'eo-mcp-status ' + css )
				.empty()
				.append( $( '<span class="dashicons">' ).addClass( 'dashicons-' + icon ) )
				.append( document.createTextNode( ' ' + message ) )
				.prop( 'hidden', false );
		}

		/*
		 * OAuth clients (claude.ai, ChatGPT) only need the connector URL: they handle the
		 * authorization themselves. Other clients need a generated Application Password.
		 */
		function selectClient() {
			var client = data.clients[ $( 'input[name="eo-mcp-client"]:checked' ).val() ];

			$createFeedback.removeClass( 'is-error' ).text( '' );
			$result.prop( 'hidden', true );
			$unavailable.prop( 'hidden', true );
			$createButton.prop( 'hidden', true );

			if ( ! client ) {
				return;
			}

			if ( 'oauth' === client.format ) {
				if ( data.oauthWarning ) {
					setStatus( $test, 'warning', data.oauthWarning );
				} else {
					$test.prop( 'hidden', true );
				}
				$( '#eo-mcp-connection-help' ).text( client.help );
				$( '#eo-mcp-connection-snippets' ).empty().append( codeBlock( labels.connectorUrl, data.endpoint ) );
				$( '#eo-mcp-connection-once' ).prop( 'hidden', true );
				$result.prop( 'hidden', false );
				return;
			}

			if ( data.appPasswordsUnavailable ) {
				$unavailable.find( '.eo-mcp-status-text' ).text( data.appPasswordsUnavailable );
				$unavailable.prop( 'hidden', false );
				return;
			}

			$createButton.prop( 'hidden', false );
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
					setStatus( $test, connection.test.status, connection.test.message );
					$( '#eo-mcp-connection-help' ).text( client.help );
					$( '#eo-mcp-connection-snippets' ).empty().append( buildSnippets( client.format, connection ) );
					$( '#eo-mcp-connection-once' ).prop( 'hidden', false );
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

		/* Step 3: follow connections usage. */
		var $refreshButton = $( '#eo-mcp-refresh-connections' );

		$refreshButton.on( 'click', function () {
			$refreshButton.prop( 'disabled', true );
			post( 'eo_tools_mcp_get_connections' )
				.done( function ( response ) {
					if ( response.success ) {
						renderConnections( response.data.connections );
					}
				} )
				.always( function () {
					$refreshButton.prop( 'disabled', false );
				} );
		} );

		if ( $( '#eo-mcp-connections-list' ).length ) {
			renderConnections( data.connections );
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
