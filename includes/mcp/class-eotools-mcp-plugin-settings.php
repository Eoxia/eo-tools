<?php
/**
 * Plugin settings: registry of the plugin adapters the AI can use.
 *
 * Each supported plugin has an adapter (includes/mcp/plugins/) that knows which
 * of its settings can be read and changed, and how. More plugins can be added
 * with the "eo_tools_mcp_plugin_adapters" filter.
 *
 * Changes are logged with their previous value, in the same log as the
 * WordPress settings. Secrets are typed by a human in EO Tools > MCP >
 * Autorisations and are never returned to the AI.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

use EoTools\Includes\Mcp\Plugins\Eotools_Mcp_Plugin_Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugin_Settings {

	const SECRET_ACTION = 'eo_tools_plugin_secret';

	/**
	 * @var Eotools_Mcp_Plugin_Adapter[]|null Keyed by plugin slug.
	 */
	private static $adapters = null;

	public static function init() {
		add_action( 'admin_post_' . self::SECRET_ACTION, array( __CLASS__, 'handle_secret' ) );
	}

	/**
	 * @return Eotools_Mcp_Plugin_Adapter[] Keyed by plugin slug.
	 */
	public static function get_adapters() {
		if ( null !== self::$adapters ) {
			return self::$adapters;
		}

		/**
		 * Filters the plugin adapters: plugins whose settings the AI can read and change.
		 *
		 * @param string[] $classes Class names extending Eotools_Mcp_Plugin_Adapter.
		 */
		$classes = (array) apply_filters(
			'eo_tools_mcp_plugin_adapters',
			array(
				Plugins\Eotools_Mcp_Plugin_Cf7::class,
			)
		);

		self::$adapters = array();
		foreach ( $classes as $class ) {
			if ( is_string( $class ) && is_subclass_of( $class, Eotools_Mcp_Plugin_Adapter::class ) ) {
				$adapter                                = new $class();
				self::$adapters[ $adapter->get_slug() ] = $adapter;
			}
		}

		return self::$adapters;
	}

	/**
	 * Adapter of a plugin, ready to use.
	 *
	 * @param string $plugin Plugin slug.
	 * @return Eotools_Mcp_Plugin_Adapter|\WP_Error
	 */
	private static function get_adapter( $plugin ) {
		$adapters = self::get_adapters();
		if ( ! isset( $adapters[ $plugin ] ) ) {
			/* translators: %s: supported plugins */
			return new \WP_Error( 'eo_tools_plugin_unsupported', sprintf( __( 'EO Tools ne sait pas encore configurer cette extension. Extensions prises en charge : %s.', 'eo-tools' ), implode( ', ', array_keys( $adapters ) ) ) );
		}
		if ( ! $adapters[ $plugin ]->is_available() ) {
			return new \WP_Error( 'eo_tools_plugin_inactive', __( 'Cette extension n\'est pas active. Installez-la (eo-tools/install-plugin) et faites-la activer (eo-tools/request-elevation) avant de la configurer.', 'eo-tools' ) );
		}

		return $adapters[ $plugin ];
	}

	/**
	 * Supported plugins, or the settings of one plugin.
	 *
	 * @param string $plugin Plugin slug, empty to list the supported plugins.
	 * @param string $target Object of the plugin (e.g. a form ID).
	 * @return array|\WP_Error
	 */
	public static function get( $plugin = '', $target = '' ) {
		if ( '' === $plugin ) {
			$list = array();
			foreach ( self::get_adapters() as $slug => $adapter ) {
				$list[] = array(
					'plugin' => $slug,
					'label'  => $adapter->get_label(),
					'active' => $adapter->is_available(),
				);
			}

			return array( 'plugins' => $list );
		}

		$adapter = self::get_adapter( $plugin );
		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}

		$result = $adapter->get( $target );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['plugin']  = $plugin;
		$result['schema']  = $adapter->get_schema();
		$result['secrets'] = $adapter->describe_secrets();

		return $result;
	}

	/**
	 * Changes the settings of a plugin.
	 *
	 * @param string $plugin Plugin slug.
	 * @param array  $values New values keyed by setting name.
	 * @param string $target Object of the plugin (e.g. a form ID).
	 * @return array|\WP_Error
	 */
	public static function update( $plugin, $values, $target = '' ) {
		$adapter = self::get_adapter( $plugin );
		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}
		if ( ! is_array( $values ) || ! $values ) {
			return new \WP_Error( 'eo_tools_no_settings', __( 'Aucun réglage à modifier.', 'eo-tools' ) );
		}

		$result = $adapter->update( $values, $target );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$context = $plugin . ( '' !== (string) $result['target'] ? '#' . $result['target'] : '' );
		foreach ( $result['changed'] as $change ) {
			Eotools_Mcp_Settings::log( $change['name'], $change['before'], $change['after'], $context );
		}

		foreach ( $adapter->describe_secrets() as $secret ) {
			if ( ! $secret['filled'] ) {
				$result['notes'][] = sprintf(
					/* translators: 1: secret name, 2: URL */
					__( 'La clé « %1$s » n\'est pas encore renseignée. Elle ne doit pas passer par la conversation : donnez à l\'utilisateur ce lien pour la saisir lui-même dans WordPress : %2$s', 'eo-tools' ),
					$secret['label'],
					$secret['url']
				);
			}
		}

		return $result;
	}

	/* ---------------------------------------------------------------------
	 * Secrets, typed by a human
	 * ------------------------------------------------------------------ */

	/**
	 * Page where a human types a secret.
	 *
	 * @param string $plugin Plugin slug.
	 * @param string $name   Secret name.
	 * @return string
	 */
	public static function secret_url( $plugin, $name ) {
		return admin_url( 'admin.php?page=eo-tools-mcp&tab=elevation#secret-' . $plugin . '-' . $name );
	}

	/**
	 * Secrets of the active plugins, for the admin screen.
	 *
	 * @return array[] { plugin, plugin_label, name, label, help, filled }.
	 */
	public static function get_all_secrets() {
		$list = array();
		foreach ( self::get_adapters() as $slug => $adapter ) {
			if ( ! $adapter->is_available() ) {
				continue;
			}
			foreach ( $adapter->get_secrets() as $name => $secret ) {
				$list[] = array(
					'plugin'       => $slug,
					'plugin_label' => $adapter->get_label(),
					'name'         => $name,
					'label'        => $secret['label'],
					'help'         => isset( $secret['help'] ) ? $secret['help'] : '',
					'filled'       => $adapter->has_secret( $name ),
				);
			}
		}

		return $list;
	}

	/**
	 * Saves a secret typed by an administrator (form posted to admin-post.php).
	 */
	public static function handle_secret() {
		$plugin = isset( $_POST['plugin'] ) ? sanitize_key( wp_unslash( $_POST['plugin'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_key( wp_unslash( $_POST['name'] ) ) : '';
		check_admin_referer( self::SECRET_ACTION . '_' . $plugin . '_' . $name );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas le droit de modifier ces réglages.', 'eo-tools' ), '', array( 'response' => 403 ) );
		}

		$value   = isset( $_POST['secret'] ) ? trim( (string) wp_unslash( $_POST['secret'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- secret, checked by the adapter.
		$adapter = self::get_adapter( $plugin );

		if ( is_wp_error( $adapter ) ) {
			$result = $adapter;
		} elseif ( ! isset( $adapter->get_secrets()[ $name ] ) ) {
			$result = new \WP_Error( 'eo_tools_no_secret', __( 'Clé inconnue.', 'eo-tools' ) );
		} elseif ( '' === $value ) {
			$result = new \WP_Error( 'eo_tools_secret_empty', __( 'Saisissez la clé.', 'eo-tools' ) );
		} else {
			$filled = $adapter->has_secret( $name );
			$result = $adapter->save_secret( $name, $value );
			if ( true === $result ) {
				// Never log the secret itself.
				Eotools_Mcp_Settings::log( $name, $filled ? '••••' : '', '••••', $plugin );
			}
		}

		if ( is_wp_error( $result ) ) {
			set_transient( 'eo_tools_elevation_notice_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=eo-tools-mcp&tab=elevation&done=error#secret-' . $plugin . '-' . $name ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=eo-tools-mcp&tab=elevation&done=secret#secret-' . $plugin . '-' . $name ) );
		}
		exit;
	}
}
