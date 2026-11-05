<?php
/**
 * EO Tools MCP server.
 *
 * Registers a dedicated MCP server on top of MCP Adapter, where each enabled
 * EO Tools ability is exposed directly as an MCP tool.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Server {

	const SERVER_ID        = 'eo-tools';
	const ROUTE_NAMESPACE  = 'eo-tools';
	const ROUTE            = 'mcp';
	const TEST_HEADER      = 'X-EO-Tools-Test';
	const LAST_SEEN_OPTION = 'eo_tools_mcp_last_seen';

	public static function init() {
		add_action( 'mcp_adapter_init', array( __CLASS__, 'register_server' ) );
		add_action( 'application_password_did_authenticate', array( __CLASS__, 'record_usage' ), 10, 2 );
	}

	/**
	 * Returns the public URL of the EO Tools MCP endpoint.
	 *
	 * @return string
	 */
	public static function get_endpoint_url() {
		return rest_url( self::ROUTE_NAMESPACE . '/' . self::ROUTE );
	}

	/**
	 * Whether the current HTTP request targets the EO Tools MCP endpoint.
	 *
	 * Works before the REST API is loaded, e.g. during authentication.
	 *
	 * @return bool
	 */
	public static function is_mcp_request() {
		$route       = '/' . self::ROUTE_NAMESPACE . '/' . self::ROUTE;
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$rest_route  = isset( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$suffix = '/' . rest_get_url_prefix() . $route;

		return untrailingslashit( $rest_route ) === $route
			|| substr( untrailingslashit( $path ), -strlen( $suffix ) ) === $suffix;
	}

	/**
	 * Registers the EO Tools server with MCP Adapter.
	 *
	 * @param \WP\MCP\Core\McpAdapter $adapter MCP Adapter instance.
	 */
	public static function register_server( $adapter ) {
		/**
		 * Filters the ability names exposed as tools on the EO Tools MCP server.
		 *
		 * @param string[] $tools Ability names.
		 */
		$tools = (array) apply_filters( 'eo_tools_mcp_tools', array() );

		$result = $adapter->create_server(
			self::SERVER_ID,
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			__( 'EO Tools', 'eo-tools' ),
			__( 'Outils EO Tools pour gérer les contenus de ce site WordPress.', 'eo-tools' ),
			EO_TOOLS_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			$tools,
			array(),
			array(),
			array( __CLASS__, 'check_transport_permission' )
		);

		if ( is_wp_error( $result ) ) {
			error_log( 'EO Tools MCP: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Only users able to edit content may talk to the EO Tools server.
	 * Each ability still checks its own capabilities.
	 *
	 * @return bool
	 */
	public static function check_transport_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Records the last time an EO Tools connection reached the MCP endpoint.
	 *
	 * WordPress only updates an Application Password "last used" date once a day,
	 * which is too coarse to confirm that a client has just connected.
	 *
	 * @param \WP_User $user Authenticated user.
	 * @param array    $item Application Password used.
	 */
	public static function record_usage( $user, $item ) {
		if ( empty( $item['app_id'] ) || Eotools_Mcp_Connections::APP_ID !== $item['app_id'] ) {
			return;
		}

		// Ignore EO Tools' own connection test.
		if ( ! empty( $_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', self::TEST_HEADER ) ) ] ) ) {
			return;
		}

		if ( ! self::is_mcp_request() ) {
			return;
		}

		$last_seen = get_user_meta( $user->ID, self::LAST_SEEN_OPTION, true );
		$last_seen = is_array( $last_seen ) ? $last_seen : array();

		// Throttle writes to one per minute per connection.
		if ( isset( $last_seen[ $item['uuid'] ] ) && $last_seen[ $item['uuid'] ] > time() - MINUTE_IN_SECONDS ) {
			return;
		}

		$last_seen[ $item['uuid'] ] = time();
		update_user_meta( $user->ID, self::LAST_SEEN_OPTION, $last_seen );
	}
}
