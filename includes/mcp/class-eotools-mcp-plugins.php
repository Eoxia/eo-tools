<?php
/**
 * Plugins: list the installed plugins, search and install plugins from wordpress.org.
 *
 * The AI never activates a plugin: an installed plugin stays inactive until a
 * human activates it from the Plugins screen. Only plugins of the official
 * wordpress.org directory can be installed, after a compatibility check.
 * Plugins installed by the AI are marked on the Plugins screen.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugins {

	/**
	 * Plugins installed by the AI: plugin file => array( slug, user, time ).
	 */
	const INSTALLED_OPTION = 'eo_tools_mcp_ai_plugins';

	/**
	 * A plugin not updated for this long is flagged.
	 */
	const STALE_AFTER = 2 * YEAR_IN_SECONDS;

	public static function init() {
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_action( 'deleted_plugin', array( __CLASS__, 'forget' ), 10, 2 );
	}

	private static function load_admin_files() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}

	public static function can_install() {
		return current_user_can( 'install_plugins' ) && wp_is_file_mod_allowed( 'eo_tools_mcp_install_plugin' );
	}

	/* ---------------------------------------------------------------------
	 * Installed plugins
	 * ------------------------------------------------------------------ */

	/**
	 * Installed plugins.
	 *
	 * @param string $status "all", "active" or "inactive".
	 * @return array
	 */
	public static function get_installed( $status = 'all' ) {
		self::load_admin_files();

		$updates = get_site_transient( 'update_plugins' );
		$by_ai   = (array) get_option( self::INSTALLED_OPTION, array() );
		$list    = array();

		foreach ( get_plugins() as $file => $data ) {
			$active = is_plugin_active( $file );
			if ( ( 'active' === $status && ! $active ) || ( 'inactive' === $status && $active ) ) {
				continue;
			}

			$plugin = array(
				'file'        => $file,
				'slug'        => self::slug_of( $file ),
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'active'      => $active,
				'description' => wp_strip_all_tags( $data['Description'] ),
				'author'      => wp_strip_all_tags( $data['Author'] ),
			);
			if ( is_multisite() && is_plugin_active_for_network( $file ) ) {
				$plugin['network_active'] = true;
			}
			if ( isset( $updates->response[ $file ]->new_version ) ) {
				$plugin['update_available'] = $updates->response[ $file ]->new_version;
			}
			if ( isset( $by_ai[ $file ] ) ) {
				$plugin['installed_by_ai'] = gmdate( 'c', (int) $by_ai[ $file ]['time'] );
			}

			$list[] = $plugin;
		}

		return array(
			'plugins'     => $list,
			'mu_plugins'  => array_values( wp_list_pluck( get_mu_plugins(), 'Name' ) ),
			'can_install' => self::can_install(),
		);
	}

	/**
	 * Slug (folder) of a plugin file.
	 *
	 * @param string $file Plugin file, e.g. "contact-form-7/wp-contact-form-7.php".
	 * @return string
	 */
	private static function slug_of( $file ) {
		return false === strpos( $file, '/' ) ? basename( $file, '.php' ) : dirname( $file );
	}

	/**
	 * Installed plugin file of a slug.
	 *
	 * @param string $slug Slug.
	 * @return string Empty when not installed.
	 */
	private static function file_of( $slug ) {
		self::load_admin_files();
		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( self::slug_of( $file ) === $slug ) {
				return $file;
			}
		}

		return '';
	}

	/* ---------------------------------------------------------------------
	 * wordpress.org directory
	 * ------------------------------------------------------------------ */

	/**
	 * Fields asked to the wordpress.org API.
	 */
	const API_FIELDS = array(
		'short_description' => true,
		'description'       => false,
		'sections'          => false,
		'rating'            => true,
		'num_ratings'       => true,
		'active_installs'   => true,
		'last_updated'      => true,
		'tested'            => true,
		'requires'          => true,
		'requires_php'      => true,
		'icons'             => false,
		'banners'           => false,
		'tags'              => false,
		'versions'          => false,
		'contributors'      => false,
		'screenshots'       => false,
		'reviews'           => false,
	);

	/**
	 * Searches the wordpress.org plugin directory.
	 *
	 * @param array $input Query, page size.
	 * @return array|\WP_Error
	 */
	public static function search( $input ) {
		self::load_admin_files();

		$query = isset( $input['query'] ) ? trim( (string) $input['query'] ) : '';
		if ( strlen( $query ) < 2 ) {
			return new \WP_Error( 'eo_tools_plugins_query', __( 'Indiquez ce que vous cherchez (au moins 2 caractères).', 'eo-tools' ) );
		}

		$result = plugins_api(
			'query_plugins',
			array(
				'search'   => $query,
				'per_page' => isset( $input['per_page'] ) ? min( 20, max( 1, (int) $input['per_page'] ) ) : 10,
				'fields'   => self::API_FIELDS,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$plugins = array();
		foreach ( (array) $result->plugins as $plugin ) {
			$plugins[] = self::describe( (object) $plugin );
		}

		return array(
			'query'   => $query,
			'total'   => isset( $result->info['results'] ) ? (int) $result->info['results'] : count( $plugins ),
			'plugins' => $plugins,
		);
	}

	/**
	 * Summary of a directory plugin, with compatibility and quality flags.
	 *
	 * @param object $plugin Plugin from plugins_api().
	 * @return array
	 */
	private static function describe( $plugin ) {
		$installed = self::file_of( $plugin->slug );
		$data      = array(
			'slug'              => $plugin->slug,
			'name'              => html_entity_decode( wp_strip_all_tags( $plugin->name ), ENT_QUOTES ),
			'version'           => $plugin->version,
			'short_description' => html_entity_decode( wp_strip_all_tags( isset( $plugin->short_description ) ? $plugin->short_description : '' ), ENT_QUOTES ),
			'author'            => wp_strip_all_tags( isset( $plugin->author ) ? $plugin->author : '' ),
			'rating'            => isset( $plugin->rating ) ? round( $plugin->rating / 20, 1 ) : null,
			'num_ratings'       => isset( $plugin->num_ratings ) ? (int) $plugin->num_ratings : 0,
			'active_installs'   => isset( $plugin->active_installs ) ? (int) $plugin->active_installs : 0,
			'last_updated'      => isset( $plugin->last_updated ) ? $plugin->last_updated : '',
			'tested_up_to'      => isset( $plugin->tested ) ? $plugin->tested : '',
			'link'              => 'https://wordpress.org/plugins/' . $plugin->slug . '/',
			'installed'         => '' !== $installed,
		);
		if ( $installed ) {
			$data['active'] = is_plugin_active( $installed );
		}

		$problems = self::check_compatibility( $plugin );
		if ( $problems['errors'] ) {
			$data['incompatible'] = $problems['errors'];
		}
		if ( $problems['warnings'] ) {
			$data['warnings'] = $problems['warnings'];
		}

		return $data;
	}

	/**
	 * Compatibility errors (blocking) and quality warnings of a directory plugin.
	 *
	 * @param object $plugin Plugin from plugins_api().
	 * @return array With "errors" and "warnings".
	 */
	private static function check_compatibility( $plugin ) {
		$errors   = array();
		$warnings = array();

		if ( ! empty( $plugin->requires ) && ! is_wp_version_compatible( $plugin->requires ) ) {
			/* translators: %s: WordPress version. */
			$errors[] = sprintf( __( 'Demande WordPress %s ou plus.', 'eo-tools' ), $plugin->requires );
		}
		if ( ! empty( $plugin->requires_php ) && ! is_php_version_compatible( $plugin->requires_php ) ) {
			/* translators: %s: PHP version. */
			$errors[] = sprintf( __( 'Demande PHP %s ou plus.', 'eo-tools' ), $plugin->requires_php );
		}

		$wp_major = implode( '.', array_slice( explode( '.', get_bloginfo( 'version' ) ), 0, 2 ) );
		if ( ! empty( $plugin->tested ) && version_compare( $plugin->tested, $wp_major, '<' ) ) {
			/* translators: 1: tested WordPress version, 2: current WordPress version. */
			$warnings[] = sprintf( __( 'Testée jusqu\'à WordPress %1$s seulement (le site est en %2$s).', 'eo-tools' ), $plugin->tested, $wp_major );
		}
		if ( ! empty( $plugin->last_updated ) && strtotime( $plugin->last_updated ) < time() - self::STALE_AFTER ) {
			$warnings[] = __( 'Pas mise à jour depuis plus de 2 ans : elle est peut-être abandonnée.', 'eo-tools' );
		}
		if ( isset( $plugin->num_ratings, $plugin->rating ) && $plugin->num_ratings >= 5 && $plugin->rating < 70 ) {
			$warnings[] = __( 'Note moyenne faible.', 'eo-tools' );
		}
		if ( isset( $plugin->active_installs ) && $plugin->active_installs < 1000 ) {
			$warnings[] = __( 'Peu utilisée (moins de 1 000 sites).', 'eo-tools' );
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Installs a plugin from wordpress.org, without activating it.
	 *
	 * @param string $slug Directory slug.
	 * @return array|\WP_Error
	 */
	public static function install( $slug ) {
		if ( ! self::can_install() ) {
			return new \WP_Error( 'eo_tools_cannot_install', __( 'L\'installation d\'extensions est impossible sur ce site (droits insuffisants ou modification des fichiers désactivée).', 'eo-tools' ) );
		}

		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return new \WP_Error( 'eo_tools_plugin_slug', __( 'Indiquez le "slug" de l\'extension renvoyé par eo-tools/search-plugins.', 'eo-tools' ) );
		}

		self::load_admin_files();
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$existing = self::file_of( $slug );
		if ( $existing ) {
			return new \WP_Error(
				'eo_tools_plugin_installed',
				is_plugin_active( $existing ) ? __( 'Cette extension est déjà installée et active.', 'eo-tools' ) : __( 'Cette extension est déjà installée, mais pas activée : l\'utilisateur peut l\'activer depuis la page Extensions.', 'eo-tools' )
			);
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => self::API_FIELDS,
			)
		);
		if ( is_wp_error( $api ) ) {
			return new \WP_Error( 'eo_tools_plugin_not_found', __( 'Extension introuvable sur wordpress.org (ou retirée du répertoire).', 'eo-tools' ) );
		}

		$problems = self::check_compatibility( $api );
		if ( $problems['errors'] ) {
			/* translators: %s: compatibility problems. */
			return new \WP_Error( 'eo_tools_plugin_incompatible', sprintf( __( 'Extension incompatible avec ce site : %s', 'eo-tools' ), implode( ' ', $problems['errors'] ) ) );
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
			return new \WP_Error( 'eo_tools_plugin_install_failed', $skin->get_error_messages() );
		}
		if ( ! $result ) {
			return new \WP_Error( 'eo_tools_plugin_filesystem', __( 'Impossible d\'écrire les fichiers de l\'extension sur le serveur.', 'eo-tools' ) );
		}

		wp_clean_plugins_cache();
		$file = $upgrader->plugin_info();
		if ( ! $file ) {
			$file = self::file_of( $slug );
		}

		$by_ai          = (array) get_option( self::INSTALLED_OPTION, array() );
		$by_ai[ $file ] = array(
			'slug' => $slug,
			'user' => get_current_user_id(),
			'time' => time(),
		);
		update_option( self::INSTALLED_OPTION, $by_ai, false );

		$data              = self::describe( $api );
		$data['file']      = $file;
		$data['active']    = false;
		$data['admin_url'] = admin_url( 'plugins.php?plugin_status=inactive&s=' . rawurlencode( $slug ) );
		$data['message']   = __( 'Extension installée mais PAS activée : vous ne pouvez pas l\'activer. Dites à l\'utilisateur ce qu\'elle fait et pourquoi vous la proposez, et donnez-lui le lien "admin_url" pour l\'activer lui-même s\'il le souhaite. Rappelez-lui qu\'il peut la supprimer s\'il n\'en veut pas.', 'eo-tools' );

		return $data;
	}

	/* ---------------------------------------------------------------------
	 * Plugins screen
	 * ------------------------------------------------------------------ */

	/**
	 * "Installée par l'IA" under the plugins installed by the AI.
	 *
	 * @param string[] $meta Row meta.
	 * @param string   $file Plugin file.
	 * @return string[]
	 */
	public static function row_meta( $meta, $file ) {
		$by_ai = (array) get_option( self::INSTALLED_OPTION, array() );
		if ( isset( $by_ai[ $file ] ) ) {
			$meta[] = '<span class="dashicons dashicons-admin-generic" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom"></span> ' . esc_html(
				sprintf(
					/* translators: %s: date. */
					__( 'Installée par l\'IA (EO Tools) le %s', 'eo-tools' ),
					wp_date( get_option( 'date_format' ), (int) $by_ai[ $file ]['time'] )
				)
			);
		}

		return $meta;
	}

	/**
	 * Forgets a deleted plugin.
	 *
	 * @param string $file    Plugin file.
	 * @param bool   $deleted Whether it was deleted.
	 */
	public static function forget( $file, $deleted ) {
		$by_ai = (array) get_option( self::INSTALLED_OPTION, array() );
		if ( $deleted && isset( $by_ai[ $file ] ) ) {
			unset( $by_ai[ $file ] );
			update_option( self::INSTALLED_OPTION, $by_ai, false );
		}
	}
}
