<?php
/**
 * OAuth 2.1 authorization server for the EO Tools MCP server.
 *
 * Lets web AI clients (claude.ai, ChatGPT…) connect with a simple "Authorize"
 * click, following the MCP authorization specification:
 * - Protected Resource Metadata (RFC 9728) and Authorization Server Metadata (RFC 8414),
 * - Dynamic Client Registration (RFC 7591),
 * - Authorization Code flow with mandatory PKCE (S256),
 * - rotating refresh tokens.
 *
 * Tokens are only accepted on the EO Tools MCP endpoint.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Oauth {

	const AUTHORIZE_PAGE  = 'eo-tools-mcp-authorize';
	const AUTHORIZE_PATH  = 'eo-tools-oauth/authorize';
	const ROUTE_NAMESPACE = 'eo-tools/oauth';
	const SCOPE           = 'mcp';
	const CAPABILITY      = 'manage_options';
	const PURGE_CRON      = 'eo_tools_mcp_oauth_purge';

	/**
	 * Whether the current request carried a bearer token that was rejected.
	 *
	 * @var bool
	 */
	private static $invalid_token = false;

	public static function init() {
		add_action( 'init', array( Eotools_Mcp_Oauth_Store::class, 'maybe_create_tables' ) );
		add_action( 'parse_request', array( __CLASS__, 'handle_public_paths' ), 0 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_authorize_page' ) );

		add_filter( 'determine_current_user', array( __CLASS__, 'authenticate_bearer' ), 30 );
		add_filter( 'application_password_is_api_request', array( __CLASS__, 'skip_application_passwords' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_authenticate_header' ), 10, 3 );

		if ( ! wp_next_scheduled( self::PURGE_CRON ) ) {
			wp_schedule_event( time(), 'daily', self::PURGE_CRON );
		}
		add_action( self::PURGE_CRON, array( Eotools_Mcp_Oauth_Store::class, 'purge' ) );
	}

	/* ---------------------------------------------------------------------
	 * URLs
	 * ------------------------------------------------------------------ */

	public static function get_issuer() {
		return untrailingslashit( home_url() );
	}

	public static function get_resource_metadata_url() {
		return home_url( '/.well-known/oauth-protected-resource' );
	}

	/**
	 * Compares two URLs, ignoring a trailing slash.
	 */
	private static function same_url( $a, $b ) {
		return untrailingslashit( (string) $a ) === untrailingslashit( (string) $b );
	}

	/* ---------------------------------------------------------------------
	 * Public paths: discovery metadata and authorization entry point
	 * ------------------------------------------------------------------ */

	/**
	 * Serves the .well-known metadata documents and the authorization endpoint.
	 *
	 * @param \WP $wp Current WordPress environment.
	 */
	public static function handle_public_paths( $wp ) {
		$path = trim( (string) $wp->request, '/' );

		if ( '.well-known/oauth-protected-resource' === $path || 0 === strpos( $path, '.well-known/oauth-protected-resource/' ) ) {
			self::send_json( self::get_resource_metadata() );
		}

		if (
			'.well-known/oauth-authorization-server' === $path
			|| 0 === strpos( $path, '.well-known/oauth-authorization-server/' )
			|| '.well-known/openid-configuration' === $path
		) {
			self::send_json( self::get_server_metadata() );
		}

		if ( self::AUTHORIZE_PATH === $path ) {
			// Forward to the consent screen in wp-admin, which takes care of the login.
			$params = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $params['page'] );
			wp_redirect( add_query_arg( rawurlencode_deep( $params ), admin_url( 'admin.php?page=' . self::AUTHORIZE_PAGE ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}

	public static function get_resource_metadata() {
		return array(
			'resource'                 => Eotools_Mcp_Server::get_endpoint_url(),
			'authorization_servers'    => array( self::get_issuer() ),
			'scopes_supported'         => array( self::SCOPE ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name'            => sprintf( 'EO Tools – %s', get_bloginfo( 'name' ) ),
		);
	}

	public static function get_server_metadata() {
		return array(
			'issuer'                                => self::get_issuer(),
			'authorization_endpoint'                => home_url( '/' . self::AUTHORIZE_PATH ),
			'token_endpoint'                        => rest_url( self::ROUTE_NAMESPACE . '/token' ),
			'registration_endpoint'                 => rest_url( self::ROUTE_NAMESPACE . '/register' ),
			'scopes_supported'                      => array( self::SCOPE ),
			'response_types_supported'              => array( 'code' ),
			'response_modes_supported'              => array( 'query' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'authorization_response_iss_parameter_supported' => true,
		);
	}

	private static function send_json( $data, $status = 200 ) {
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Cache-Control: no-store' );
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * REST endpoints: registration and token
	 * ------------------------------------------------------------------ */

	public static function register_routes() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_register' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_token' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private static function oauth_response( $data, $status = 200 ) {
		$response = new \WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}

	private static function oauth_error( $error, $description, $status = 400 ) {
		return self::oauth_response(
			array(
				'error'             => $error,
				'error_description' => $description,
			),
			$status
		);
	}

	/**
	 * Whether a redirect URI is acceptable: HTTPS, or HTTP on the loopback interface (desktop clients).
	 */
	private static function is_valid_redirect_uri( $uri ) {
		if ( ! is_string( $uri ) || strlen( $uri ) > 2000 || false !== strpos( $uri, '#' ) ) {
			return false;
		}

		$parts = wp_parse_url( $uri );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( 'https' === $parts['scheme'] ) {
			return true;
		}

		return 'http' === $parts['scheme'] && in_array( $parts['host'], array( 'localhost', '127.0.0.1', '[::1]' ), true );
	}

	/**
	 * Dynamic Client Registration (RFC 7591).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_register( $request ) {
		$params        = (array) $request->get_json_params();
		$redirect_uris = isset( $params['redirect_uris'] ) ? $params['redirect_uris'] : null;

		if ( ! is_array( $redirect_uris ) || empty( $redirect_uris ) || count( $redirect_uris ) > 10 ) {
			return self::oauth_error( 'invalid_redirect_uri', 'At least one redirect URI is required.' );
		}

		foreach ( $redirect_uris as $uri ) {
			if ( ! self::is_valid_redirect_uri( $uri ) ) {
				return self::oauth_error( 'invalid_redirect_uri', 'Redirect URIs must use HTTPS (or HTTP on localhost).' );
			}
		}

		$auth_method = isset( $params['token_endpoint_auth_method'] ) ? $params['token_endpoint_auth_method'] : 'client_secret_basic';
		if ( ! in_array( $auth_method, array( 'none', 'client_secret_post', 'client_secret_basic' ), true ) ) {
			return self::oauth_error( 'invalid_client_metadata', 'Unsupported token_endpoint_auth_method.' );
		}

		$name = isset( $params['client_name'] ) && is_string( $params['client_name'] ) ? sanitize_text_field( $params['client_name'] ) : '';
		$name = '' !== $name ? mb_substr( $name, 0, 100 ) : __( 'Client IA', 'eo-tools' );

		$client = Eotools_Mcp_Oauth_Store::create_client( $name, $redirect_uris, $auth_method );
		if ( is_wp_error( $client ) ) {
			return self::oauth_error( 'server_error', $client->get_error_message(), 500 );
		}

		$response = array(
			'client_id'                  => $client['client_id'],
			'client_id_issued_at'        => $client['created'],
			'client_name'                => $name,
			'redirect_uris'              => array_values( $redirect_uris ),
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => $auth_method,
		);

		if ( null !== $client['client_secret'] ) {
			$response['client_secret']            = $client['client_secret'];
			$response['client_secret_expires_at'] = 0;
		}

		return self::oauth_response( $response, 201 );
	}

	/**
	 * Token endpoint: authorization_code and refresh_token grants.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_token( $request ) {
		$client_id     = (string) $request->get_param( 'client_id' );
		$client_secret = $request->get_param( 'client_secret' );

		// client_secret_basic.
		$basic = self::get_authorization_header();
		if ( 0 === stripos( $basic, 'Basic ' ) ) {
			$decoded = base64_decode( substr( $basic, 6 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false !== $decoded && false !== strpos( $decoded, ':' ) ) {
				list( $basic_id, $basic_secret ) = explode( ':', $decoded, 2 );
				$client_id     = rawurldecode( $basic_id );
				$client_secret = rawurldecode( $basic_secret );
			}
		}

		$client = Eotools_Mcp_Oauth_Store::get_client( $client_id );
		if ( ! $client || ! Eotools_Mcp_Oauth_Store::verify_client_secret( $client, $client_secret ) ) {
			return self::oauth_error( 'invalid_client', 'Client authentication failed.', 401 );
		}

		$resource = $request->get_param( 'resource' );
		if ( null !== $resource && ! self::same_url( $resource, Eotools_Mcp_Server::get_endpoint_url() ) ) {
			return self::oauth_error( 'invalid_target', 'Unknown resource.' );
		}

		$grant_type = (string) $request->get_param( 'grant_type' );

		if ( 'authorization_code' === $grant_type ) {
			$code = Eotools_Mcp_Oauth_Store::consume_code( (string) $request->get_param( 'code' ) );

			if (
				! $code
				|| $code['client_id'] !== $client['client_id']
				|| $code['redirect_uri'] !== (string) $request->get_param( 'redirect_uri' )
			) {
				return self::oauth_error( 'invalid_grant', 'Invalid or expired authorization code.' );
			}

			$verifier = (string) $request->get_param( 'code_verifier' );
			$expected = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			if ( strlen( $verifier ) < 43 || ! hash_equals( $code['code_challenge'], $expected ) ) {
				return self::oauth_error( 'invalid_grant', 'PKCE verification failed.' );
			}

			if ( ! user_can( $code['user_id'], self::CAPABILITY ) ) {
				return self::oauth_error( 'invalid_grant', 'The user is no longer allowed to connect.' );
			}

			$tokens = Eotools_Mcp_Oauth_Store::create_token( $code['user_id'], $client['client_id'] );
		} elseif ( 'refresh_token' === $grant_type ) {
			$tokens = Eotools_Mcp_Oauth_Store::refresh_token( (string) $request->get_param( 'refresh_token' ), $client['client_id'] );

			if ( ! $tokens || ! user_can( $tokens['user_id'], self::CAPABILITY ) ) {
				return self::oauth_error( 'invalid_grant', 'Invalid or expired refresh token.' );
			}
		} else {
			return self::oauth_error( 'unsupported_grant_type', 'Unsupported grant type.' );
		}

		return self::oauth_response(
			array(
				'access_token'  => $tokens['access_token'],
				'token_type'    => 'Bearer',
				'expires_in'    => $tokens['expires_in'],
				'refresh_token' => $tokens['refresh_token'],
				'scope'         => self::SCOPE,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Authorization endpoint (consent screen)
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the consent screen as a hidden admin page, so WordPress handles the login.
	 */
	public static function register_authorize_page() {
		$hook = add_submenu_page( '', __( 'Autoriser l\'accès', 'eo-tools' ), '', 'read', self::AUTHORIZE_PAGE, '__return_null' );

		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'handle_authorize' ) );
		}
	}

	/**
	 * Displays the consent screen (GET) or processes the decision (POST).
	 */
	public static function handle_authorize() {
		// phpcs:disable WordPress.Security.NonceVerification -- The decision is nonce-checked below.
		$source = 'POST' === $_SERVER['REQUEST_METHOD'] ? $_POST : $_GET; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$params = array();
		foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method', 'scope', 'resource' ) as $key ) {
			$params[ $key ] = isset( $source[ $key ] ) && is_string( $source[ $key ] ) ? wp_unslash( $source[ $key ] ) : '';
		}
		// phpcs:enable WordPress.Security.NonceVerification

		// Without a valid client and redirect URI, nothing can be sent back to the client.
		$client = Eotools_Mcp_Oauth_Store::get_client( $params['client_id'] );
		if ( ! $client ) {
			self::render_consent_error( __( 'Cette application n\'est pas reconnue. Relancez la connexion depuis votre IA.', 'eo-tools' ) );
		}
		if ( ! in_array( $params['redirect_uri'], $client['redirect_uris'], true ) ) {
			self::render_consent_error( __( 'L\'adresse de retour de cette application n\'est pas valide.', 'eo-tools' ) );
		}

		if ( 'code' !== $params['response_type'] ) {
			self::redirect_with_error( $params, 'unsupported_response_type' );
		}
		if ( '' === $params['code_challenge'] || 'S256' !== $params['code_challenge_method'] ) {
			self::redirect_with_error( $params, 'invalid_request', 'PKCE with S256 is required.' );
		}
		if ( '' !== $params['resource'] && ! self::same_url( $params['resource'], Eotools_Mcp_Server::get_endpoint_url() ) ) {
			self::redirect_with_error( $params, 'invalid_target' );
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			self::render_consent_error( __( 'Seuls les administrateurs du site peuvent connecter une IA.', 'eo-tools' ) );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'eo_tools_mcp_authorize_' . $client['client_id'] );

			if ( empty( $_POST['approve'] ) ) {
				self::redirect_with_error( $params, 'access_denied' );
			}

			$code = Eotools_Mcp_Oauth_Store::create_code(
				array(
					'client_id'      => $client['client_id'],
					'user_id'        => get_current_user_id(),
					'redirect_uri'   => $params['redirect_uri'],
					'code_challenge' => $params['code_challenge'],
				)
			);

			self::redirect_to_client(
				$params['redirect_uri'],
				array(
					'code'  => $code,
					'state' => $params['state'],
					'iss'   => self::get_issuer(),
				)
			);
		}

		$redirect_host = wp_parse_url( $params['redirect_uri'], PHP_URL_HOST );
		$user          = wp_get_current_user();

		include EO_TOOLS_PATH . 'includes/admin/views/html-mcp-oauth-consent.php';
		exit;
	}

	private static function redirect_with_error( $params, $error, $description = '' ) {
		self::redirect_to_client(
			$params['redirect_uri'],
			array_filter(
				array(
					'error'             => $error,
					'error_description' => $description,
					'state'             => $params['state'],
					'iss'               => self::get_issuer(),
				)
			)
		);
	}

	private static function redirect_to_client( $redirect_uri, $args ) {
		// The redirect URI was checked against the client registration.
		wp_redirect( add_query_arg( rawurlencode_deep( array_filter( $args, 'strlen' ) ), $redirect_uri ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	private static function render_consent_error( $message ) {
		wp_die( esc_html( $message ), esc_html__( 'Connexion impossible', 'eo-tools' ), array( 'response' => 400 ) );
	}

	/* ---------------------------------------------------------------------
	 * Bearer authentication on the MCP endpoint
	 * ------------------------------------------------------------------ */

	private static function get_authorization_header() {
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				return trim( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
			}
		}

		return '';
	}

	/**
	 * Authenticates MCP requests carrying an EO Tools access token.
	 *
	 * @param int|false $user_id User ID already determined.
	 * @return int|false
	 */
	public static function authenticate_bearer( $user_id ) {
		if ( $user_id || ! Eotools_Mcp_Server::is_mcp_request() ) {
			return $user_id;
		}

		$header = self::get_authorization_header();
		if ( 0 !== stripos( $header, 'Bearer ' ) ) {
			return $user_id;
		}

		$grant = Eotools_Mcp_Oauth_Store::get_by_access_token( trim( substr( $header, 7 ) ) );
		if ( ! $grant || ! user_can( (int) $grant->user_id, self::CAPABILITY ) ) {
			self::$invalid_token = true;
			return $user_id;
		}

		return (int) $grant->user_id;
	}

	/**
	 * Basic credentials sent to the OAuth endpoints are client credentials, not Application Passwords.
	 *
	 * @param bool $is_api_request Whether the request is an API request.
	 * @return bool
	 */
	public static function skip_application_passwords( $is_api_request ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';

		return false !== strpos( $request_uri, '/' . self::ROUTE_NAMESPACE . '/' ) ? false : $is_api_request;
	}

	/**
	 * Tells unauthenticated MCP clients where to find the authorization server.
	 *
	 * @param \WP_HTTP_Response $response Response.
	 * @param \WP_REST_Server   $server   Server.
	 * @param \WP_REST_Request  $request  Request.
	 * @return \WP_HTTP_Response
	 */
	public static function add_authenticate_header( $response, $server, $request ) {
		$route = '/' . Eotools_Mcp_Server::ROUTE_NAMESPACE . '/' . Eotools_Mcp_Server::ROUTE;

		if ( 401 === $response->get_status() && $route === $request->get_route() ) {
			$value = 'Bearer resource_metadata="' . self::get_resource_metadata_url() . '"';
			if ( self::$invalid_token ) {
				$value .= ', error="invalid_token"';
			}
			$response->header( 'WWW-Authenticate', $value );
		}

		return $response;
	}
}
