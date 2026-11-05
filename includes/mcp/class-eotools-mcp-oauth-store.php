<?php
/**
 * Storage for the EO Tools MCP OAuth server.
 *
 * Secrets (client secrets, access and refresh tokens) are never stored in clear:
 * only their SHA-256 hash is kept. Authorization codes live in short transients.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Oauth_Store {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'eo_tools_mcp_oauth_db_version';

	const CODE_TTL    = 600;
	const ACCESS_TTL  = HOUR_IN_SECONDS;
	const REFRESH_TTL = 90 * DAY_IN_SECONDS;

	public static function clients_table() {
		global $wpdb;
		return $wpdb->prefix . 'eotools_mcp_oauth_clients';
	}

	public static function tokens_table() {
		global $wpdb;
		return $wpdb->prefix . 'eotools_mcp_oauth_tokens';
	}

	public static function maybe_create_tables() {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$clients         = self::clients_table();
		$tokens          = self::tokens_table();

		$sql = "CREATE TABLE $clients (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(64) NOT NULL,
			client_secret_hash char(64) DEFAULT '' NOT NULL,
			client_name varchar(255) NOT NULL,
			redirect_uris text NOT NULL,
			auth_method varchar(32) NOT NULL,
			created bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY client_id (client_id)
		) $charset_collate;
		CREATE TABLE $tokens (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			client_id varchar(64) NOT NULL,
			access_hash char(64) NOT NULL,
			access_expires bigint(20) unsigned NOT NULL,
			refresh_hash char(64) NOT NULL,
			refresh_expires bigint(20) unsigned NOT NULL,
			created bigint(20) unsigned NOT NULL,
			last_used bigint(20) unsigned DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY access_hash (access_hash),
			UNIQUE KEY refresh_hash (refresh_hash),
			KEY user_id (user_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	private static function generate_secret() {
		return bin2hex( random_bytes( 32 ) );
	}

	private static function hash( $secret ) {
		return hash( 'sha256', (string) $secret );
	}

	/* ---------------------------------------------------------------------
	 * Clients
	 * ------------------------------------------------------------------ */

	/**
	 * Registers a client (RFC 7591).
	 *
	 * @param string   $name          Client name.
	 * @param string[] $redirect_uris Allowed redirect URIs.
	 * @param string   $auth_method   none, client_secret_post or client_secret_basic.
	 * @return array|\WP_Error { client_id, client_secret (null for public clients), created }.
	 */
	public static function create_client( $name, $redirect_uris, $auth_method ) {
		global $wpdb;

		$client_id = wp_generate_uuid4();
		$secret    = 'none' === $auth_method ? null : self::generate_secret();
		$created   = time();

		$inserted = $wpdb->insert(
			self::clients_table(),
			array(
				'client_id'          => $client_id,
				'client_secret_hash' => null === $secret ? '' : self::hash( $secret ),
				'client_name'        => $name,
				'redirect_uris'      => wp_json_encode( array_values( $redirect_uris ) ),
				'auth_method'        => $auth_method,
				'created'            => $created,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'server_error', 'Unable to register the client.' );
		}

		return array(
			'client_id'     => $client_id,
			'client_secret' => $secret,
			'created'       => $created,
		);
	}

	/**
	 * @param string $client_id Client ID.
	 * @return array|null Client with decoded redirect_uris.
	 */
	public static function get_client( $client_id ) {
		global $wpdb;

		if ( ! is_string( $client_id ) || '' === $client_id ) {
			return null;
		}

		$client = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::clients_table() . ' WHERE client_id = %s', $client_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( ! $client ) {
			return null;
		}

		$client['redirect_uris'] = (array) json_decode( $client['redirect_uris'], true );

		return $client;
	}

	/**
	 * Checks a client secret against a registered client.
	 *
	 * @param array       $client Client.
	 * @param string|null $secret Secret sent by the client.
	 * @return bool
	 */
	public static function verify_client_secret( $client, $secret ) {
		if ( 'none' === $client['auth_method'] ) {
			return true;
		}

		return is_string( $secret ) && '' !== $secret && hash_equals( $client['client_secret_hash'], self::hash( $secret ) );
	}

	/* ---------------------------------------------------------------------
	 * Authorization codes
	 * ------------------------------------------------------------------ */

	/**
	 * @param array $data Code data (client_id, user_id, redirect_uri, code_challenge, resource).
	 * @return string The authorization code.
	 */
	public static function create_code( $data ) {
		$code = self::generate_secret();
		set_transient( 'eo_tools_mcp_code_' . self::hash( $code ), $data, self::CODE_TTL );

		return $code;
	}

	/**
	 * Returns the code data and invalidates the code (single use).
	 *
	 * @param string $code Authorization code.
	 * @return array|null
	 */
	public static function consume_code( $code ) {
		if ( ! is_string( $code ) || '' === $code ) {
			return null;
		}

		$key  = 'eo_tools_mcp_code_' . self::hash( $code );
		$data = get_transient( $key );
		delete_transient( $key );

		return is_array( $data ) ? $data : null;
	}

	/* ---------------------------------------------------------------------
	 * Tokens
	 * ------------------------------------------------------------------ */

	/**
	 * Creates a new grant (connection) for a user and a client.
	 *
	 * @return array { access_token, refresh_token, expires_in }.
	 */
	public static function create_token( $user_id, $client_id ) {
		global $wpdb;

		$access  = self::generate_secret();
		$refresh = self::generate_secret();
		$now     = time();

		$wpdb->insert(
			self::tokens_table(),
			array(
				'user_id'         => (int) $user_id,
				'client_id'       => $client_id,
				'access_hash'     => self::hash( $access ),
				'access_expires'  => $now + self::ACCESS_TTL,
				'refresh_hash'    => self::hash( $refresh ),
				'refresh_expires' => $now + self::REFRESH_TTL,
				'created'         => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%d' )
		);

		return array(
			'access_token'  => $access,
			'refresh_token' => $refresh,
			'expires_in'    => self::ACCESS_TTL,
		);
	}

	/**
	 * Rotates the tokens of a grant from its refresh token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @param string $client_id     Client the token was issued to.
	 * @return array|null New tokens, plus user_id, or null if the refresh token is invalid.
	 */
	public static function refresh_token( $refresh_token, $client_id ) {
		global $wpdb;

		if ( ! is_string( $refresh_token ) || '' === $refresh_token ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::tokens_table() . ' WHERE refresh_hash = %s AND client_id = %s AND refresh_expires > %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::hash( $refresh_token ),
				$client_id,
				time()
			)
		);

		if ( ! $row ) {
			return null;
		}

		$access  = self::generate_secret();
		$refresh = self::generate_secret();
		$now     = time();

		// Matching on the old refresh hash makes the rotation atomic: a refresh token can only be used once.
		$updated = $wpdb->update(
			self::tokens_table(),
			array(
				'access_hash'     => self::hash( $access ),
				'access_expires'  => $now + self::ACCESS_TTL,
				'refresh_hash'    => self::hash( $refresh ),
				'refresh_expires' => $now + self::REFRESH_TTL,
			),
			array(
				'id'           => $row->id,
				'refresh_hash' => $row->refresh_hash,
			),
			array( '%s', '%d', '%s', '%d' ),
			array( '%d', '%s' )
		);

		if ( ! $updated ) {
			return null;
		}

		return array(
			'user_id'       => (int) $row->user_id,
			'access_token'  => $access,
			'refresh_token' => $refresh,
			'expires_in'    => self::ACCESS_TTL,
		);
	}

	/**
	 * Returns the valid grant matching an access token, and records its usage.
	 *
	 * @param string $access_token Access token.
	 * @return object|null
	 */
	public static function get_by_access_token( $access_token ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::tokens_table() . ' WHERE access_hash = %s AND access_expires > %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::hash( $access_token ),
				time()
			)
		);

		if ( $row && (int) $row->last_used < time() - MINUTE_IN_SECONDS ) {
			$wpdb->update( self::tokens_table(), array( 'last_used' => time() ), array( 'id' => $row->id ), array( '%d' ), array( '%d' ) );
		}

		return $row ? $row : null;
	}

	/**
	 * Lists the active grants of a user, with their client name.
	 *
	 * @param int $user_id User ID.
	 * @return object[]
	 */
	public static function get_user_tokens( $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT t.id, t.created, t.last_used, c.client_name FROM ' . self::tokens_table() . ' t LEFT JOIN ' . self::clients_table() . ' c ON c.client_id = t.client_id WHERE t.user_id = %d AND t.refresh_expires > %d ORDER BY t.created DESC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $user_id,
				time()
			)
		);
	}

	/**
	 * Revokes a grant of a user.
	 *
	 * @return bool
	 */
	public static function revoke_token( $id, $user_id ) {
		global $wpdb;

		return (bool) $wpdb->delete(
			self::tokens_table(),
			array(
				'id'      => (int) $id,
				'user_id' => (int) $user_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Deletes expired grants and registered clients that were never authorized.
	 */
	public static function purge() {
		global $wpdb;

		$tokens  = self::tokens_table();
		$clients = self::clients_table();

		$wpdb->query( $wpdb->prepare( "DELETE FROM $tokens WHERE refresh_expires < %d", time() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE c FROM $clients c LEFT JOIN $tokens t ON t.client_id = c.client_id WHERE t.id IS NULL AND c.created < %d", time() - DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
