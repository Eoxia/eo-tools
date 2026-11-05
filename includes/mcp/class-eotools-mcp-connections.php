<?php
/**
 * MCP connections management.
 *
 * A connection is a WordPress Application Password tagged with the EO Tools
 * application id, so EO Tools only lists and revokes its own passwords.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Connections {

	const APP_ID       = '774bc6d7-6a92-4b55-8db5-8978551b1049';
	const OAUTH_PREFIX = 'oauth-';

	/**
	 * Returns why connections cannot be created for the current user, if so.
	 *
	 * @return string|null Human readable reason, or null when available.
	 */
	public static function get_unavailable_reason() {
		if ( ! wp_is_application_passwords_available() ) {
			if ( ! wp_is_application_passwords_supported() ) {
				if ( self::is_local_site() ) {
					return __( 'Votre site local n\'est pas déclaré comme environnement de développement, WordPress y désactive donc les mots de passe d\'application. Ajoutez la ligne define( \'WP_ENVIRONMENT_TYPE\', \'local\' ); dans votre fichier wp-config.php.', 'eo-tools' );
				}

				return __( 'Votre site doit être servi en HTTPS pour connecter une IA. WordPress désactive les mots de passe d\'application sur les sites en HTTP (sauf en environnement de développement local).', 'eo-tools' );
			}

			return __( 'Les mots de passe d\'application sont désactivés sur ce site, probablement par une extension de sécurité ou par votre hébergeur.', 'eo-tools' );
		}

		if ( ! wp_is_application_passwords_available_for_user( wp_get_current_user() ) ) {
			return __( 'Les mots de passe d\'application sont désactivés pour votre compte.', 'eo-tools' );
		}

		return null;
	}

	/**
	 * Whether the site URL looks like a local development site.
	 *
	 * @return bool
	 */
	public static function is_local_site() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true ) ) {
			return true;
		}

		return (bool) preg_match( '/\.(local|test|localhost)$/', $host );
	}

	/**
	 * Creates a new connection for the current user.
	 *
	 * @param string $client_label Name of the AI client, used to name the password.
	 * @return array|\WP_Error {
	 *     @type string $endpoint      MCP endpoint URL.
	 *     @type string $username      User login.
	 *     @type string $password      Application Password, shown only once.
	 *     @type string $authorization Value for the HTTP Authorization header.
	 *     @type array  $connection    Public connection data.
	 * }
	 */
	public static function create( $client_label ) {
		$reason = self::get_unavailable_reason();
		if ( null !== $reason ) {
			return new \WP_Error( 'eo_tools_mcp_unavailable', $reason );
		}

		$user = wp_get_current_user();
		$name = sprintf(
			/* translators: 1: AI client name, 2: date. */
			__( 'EO Tools MCP – %1$s – %2$s', 'eo-tools' ),
			$client_label,
			wp_date( get_option( 'date_format' ) )
		);

		$created = \WP_Application_Passwords::create_new_application_password(
			$user->ID,
			array(
				'name'   => $name,
				'app_id' => self::APP_ID,
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		list( $password, $item ) = $created;

		return array(
			'endpoint'      => Eotools_Mcp_Server::get_endpoint_url(),
			'username'      => $user->user_login,
			'password'      => \WP_Application_Passwords::chunk_password( $password ),
			'authorization' => 'Basic ' . base64_encode( $user->user_login . ':' . $password ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'connection'    => self::format( $item, array() ),
		);
	}

	/**
	 * Lists the EO Tools connections of the current user.
	 *
	 * @return array[]
	 */
	public static function get_all() {
		$user_id   = get_current_user_id();
		$last_seen = get_user_meta( $user_id, Eotools_Mcp_Server::LAST_SEEN_OPTION, true );
		$last_seen = is_array( $last_seen ) ? $last_seen : array();

		$connections = array();
		foreach ( \WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
			if ( isset( $item['app_id'] ) && self::APP_ID === $item['app_id'] ) {
				$connections[] = self::format( $item, $last_seen );
			}
		}

		foreach ( Eotools_Mcp_Oauth_Store::get_user_tokens( $user_id ) as $grant ) {
			$uuid          = self::OAUTH_PREFIX . $grant->id;
			$connections[] = self::format(
				array(
					'uuid'    => $uuid,
					/* translators: %s: AI client name. */
					'name'    => sprintf( __( '%s (connexion OAuth)', 'eo-tools' ), $grant->client_name ? $grant->client_name : __( 'Client IA', 'eo-tools' ) ),
					'created' => $grant->created,
				),
				array( $uuid => (int) $grant->last_used )
			);
		}

		usort(
			$connections,
			function ( $a, $b ) {
				return $b['created'] - $a['created'];
			}
		);

		return $connections;
	}

	/**
	 * Revokes one EO Tools connection of the current user.
	 *
	 * @param string $uuid Application Password UUID.
	 * @return true|\WP_Error
	 */
	public static function revoke( $uuid ) {
		$user_id = get_current_user_id();

		if ( 0 === strpos( (string) $uuid, self::OAUTH_PREFIX ) ) {
			$revoked = Eotools_Mcp_Oauth_Store::revoke_token( (int) substr( $uuid, strlen( self::OAUTH_PREFIX ) ), $user_id );

			return $revoked ? true : new \WP_Error( 'eo_tools_mcp_not_found', __( 'Connexion introuvable.', 'eo-tools' ) );
		}

		$item   = \WP_Application_Passwords::get_user_application_password( $user_id, $uuid );

		if ( ! $item || empty( $item['app_id'] ) || self::APP_ID !== $item['app_id'] ) {
			return new \WP_Error( 'eo_tools_mcp_not_found', __( 'Connexion introuvable.', 'eo-tools' ) );
		}

		$deleted = \WP_Application_Passwords::delete_application_password( $user_id, $uuid );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		$last_seen = get_user_meta( $user_id, Eotools_Mcp_Server::LAST_SEEN_OPTION, true );
		if ( is_array( $last_seen ) && isset( $last_seen[ $uuid ] ) ) {
			unset( $last_seen[ $uuid ] );
			update_user_meta( $user_id, Eotools_Mcp_Server::LAST_SEEN_OPTION, $last_seen );
		}

		return true;
	}

	/**
	 * Calls the MCP endpoint the way an AI client would, to detect server issues
	 * (Authorization header stripped, broken permalinks, blocked loopback…).
	 *
	 * @param string $authorization Value for the HTTP Authorization header.
	 * @return array {
	 *     @type string $status  ok, warning or error.
	 *     @type string $message Human readable result.
	 * }
	 */
	public static function test( $authorization ) {
		$endpoint = Eotools_Mcp_Server::get_endpoint_url();
		$headers  = array(
			'Authorization'               => $authorization,
			'Content-Type'                => 'application/json',
			'Accept'                      => 'application/json, text/event-stream',
			Eotools_Mcp_Server::TEST_HEADER => '1',
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 15,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'headers'   => $headers,
				'body'      => wp_json_encode(
					array(
						'jsonrpc' => '2.0',
						'id'      => 1,
						'method'  => 'initialize',
						'params'  => array(
							'protocolVersion' => '2025-11-25',
							'capabilities'    => new \stdClass(),
							'clientInfo'      => array(
								'name'    => 'eo-tools-connection-test',
								'version' => EO_TOOLS_VERSION,
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'warning',
				'message' => __( 'Votre serveur n\'a pas pu s\'appeler lui-même pour tester la connexion. Cela ne bloque pas forcément votre IA : essayez directement depuis votre client.', 'eo-tools' ),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Close the test session right away.
		$session_id = wp_remote_retrieve_header( $response, 'mcp-session-id' );
		if ( $session_id ) {
			$headers['Mcp-Session-Id'] = $session_id;
			wp_remote_request(
				$endpoint,
				array(
					'method'    => 'DELETE',
					'timeout'   => 5,
					'blocking'  => false,
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
					'headers'   => $headers,
				)
			);
		}

		if ( 200 === $code && isset( $body['result'] ) ) {
			return array(
				'status'  => 'ok',
				'message' => __( 'Connexion vérifiée : le serveur MCP EO Tools répond correctement.', 'eo-tools' ),
			);
		}

		if ( 401 === $code ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Le site a refusé l\'identification. Votre serveur supprime probablement l\'en-tête « Authorization » : demandez à votre hébergeur de le transmettre à PHP.', 'eo-tools' ),
			);
		}

		if ( 403 === $code ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Votre compte n\'a pas les droits suffisants pour utiliser le serveur MCP.', 'eo-tools' ),
			);
		}

		if ( 404 === $code ) {
			return array(
				'status'  => 'error',
				'message' => __( 'L\'adresse du serveur MCP est introuvable. Allez dans Réglages > Permaliens et cliquez sur « Enregistrer les modifications ».', 'eo-tools' ),
			);
		}

		return array(
			'status'  => 'error',
			/* translators: %d: HTTP status code. */
			'message' => sprintf( __( 'Le serveur MCP a répondu avec une erreur inattendue (code %d).', 'eo-tools' ), $code ),
		);
	}

	/**
	 * Formats an Application Password item for display.
	 *
	 * @param array $item      Application Password item.
	 * @param array $last_seen Last MCP usage timestamps, keyed by UUID.
	 * @return array
	 */
	private static function format( $item, $last_seen ) {
		$seen = isset( $last_seen[ $item['uuid'] ] ) ? (int) $last_seen[ $item['uuid'] ] : 0;

		return array(
			'uuid'            => $item['uuid'],
			'name'            => $item['name'],
			'created'         => (int) $item['created'],
			'created_label'   => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $item['created'] ),
			'last_seen'       => $seen,
			'last_seen_label' => $seen
				/* translators: %s: human readable time difference. */
				? sprintf( __( 'Utilisée il y a %s', 'eo-tools' ), human_time_diff( $seen ) )
				: __( 'Jamais utilisée', 'eo-tools' ),
		);
	}
}
