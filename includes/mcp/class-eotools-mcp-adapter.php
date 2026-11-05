<?php
/**
 * MCP Adapter dependency management.
 *
 * Detects, installs and activates the official WordPress "MCP Adapter" plugin
 * (https://wordpress.org/plugins/mcp-adapter/), which exposes the Abilities API
 * to MCP clients.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Adapter {

	const SLUG = 'mcp-adapter';

	const STATUS_NOT_INSTALLED = 'not_installed';
	const STATUS_INACTIVE      = 'inactive';
	const STATUS_ACTIVE        = 'active';

	/**
	 * Returns the plugin file (relative to the plugins directory) of the installed adapter.
	 *
	 * @return string|null Plugin file, or null if the adapter is not installed.
	 */
	public static function get_plugin_file() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $plugin_file ) {
			if ( 0 === strpos( $plugin_file, self::SLUG . '/' ) ) {
				return $plugin_file;
			}
		}

		return null;
	}

	/**
	 * Returns the current status of the adapter.
	 *
	 * @return string One of the STATUS_* constants.
	 */
	public static function get_status() {
		$plugin_file = self::get_plugin_file();

		if ( null === $plugin_file ) {
			return self::STATUS_NOT_INSTALLED;
		}

		return is_plugin_active( $plugin_file ) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
	}

	/**
	 * Returns the installed adapter version.
	 *
	 * @return string Version, or empty string if not installed.
	 */
	public static function get_version() {
		$plugin_file = self::get_plugin_file();

		if ( null === $plugin_file ) {
			return '';
		}

		$plugins = get_plugins();

		return isset( $plugins[ $plugin_file ]['Version'] ) ? $plugins[ $plugin_file ]['Version'] : '';
	}

	/**
	 * Whether the current user and the server allow installing the adapter automatically.
	 *
	 * @return bool
	 */
	public static function can_install() {
		return current_user_can( 'install_plugins' ) && wp_is_file_mod_allowed( 'eo_tools_mcp_install' );
	}

	/**
	 * Whether the current user can activate the adapter.
	 *
	 * @return bool
	 */
	public static function can_activate() {
		return current_user_can( 'activate_plugins' );
	}

	/**
	 * Installs (if needed) and activates the adapter.
	 *
	 * @return true|\WP_Error
	 */
	public static function install_and_activate() {
		if ( self::STATUS_NOT_INSTALLED === self::get_status() ) {
			$installed = self::install();
			if ( is_wp_error( $installed ) ) {
				return $installed;
			}
		}

		return self::activate();
	}

	/**
	 * Downloads and installs the adapter from wordpress.org.
	 *
	 * @return true|\WP_Error
	 */
	public static function install() {
		if ( ! self::can_install() ) {
			return new \WP_Error( 'eo_tools_mcp_cannot_install', __( 'Vous n\'avez pas l\'autorisation d\'installer des extensions sur ce site.', 'eo-tools' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => self::SLUG,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}

		if ( $skin->get_errors()->has_errors() ) {
			return new \WP_Error( 'eo_tools_mcp_install_failed', $skin->get_error_messages() );
		}

		if ( ! $result ) {
			return new \WP_Error( 'eo_tools_mcp_filesystem', __( 'Impossible d\'accéder au système de fichiers. Installez l\'extension manuellement.', 'eo-tools' ) );
		}

		wp_clean_plugins_cache();

		return true;
	}

	/**
	 * Activates the installed adapter.
	 *
	 * @return true|\WP_Error
	 */
	public static function activate() {
		if ( ! self::can_activate() ) {
			return new \WP_Error( 'eo_tools_mcp_cannot_activate', __( 'Vous n\'avez pas l\'autorisation d\'activer des extensions sur ce site.', 'eo-tools' ) );
		}

		$plugin_file = self::get_plugin_file();

		if ( null === $plugin_file ) {
			return new \WP_Error( 'eo_tools_mcp_not_installed', __( 'L\'extension MCP Adapter n\'est pas installée.', 'eo-tools' ) );
		}

		if ( is_plugin_active( $plugin_file ) ) {
			return true;
		}

		$result = activate_plugin( $plugin_file );

		return is_wp_error( $result ) ? $result : true;
	}
}
