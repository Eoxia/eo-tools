<?php
/**
 * Site updates: WordPress, plugins, themes and translations.
 *
 * Works like the WordPress background updates, with the same safety net:
 * - only the updates WordPress itself offers, compatible with the site;
 * - the site is in maintenance mode during each update;
 * - an active plugin or theme stays active, and the site is loaded right after
 *   its update: on a fatal error, the previous version is restored;
 * - a major WordPress version needs an explicit agreement.
 * Every update is recorded in a short history.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Updates {

	const LOG_OPTION = 'eo_tools_mcp_update_log';
	const LOG_SIZE   = 50;

	/**
	 * Plugins updated last: replacing their files during the request that runs them is riskier.
	 */
	const SELF_PLUGINS = array( 'eo-tools', 'mcp-adapter' );

	private static function load_admin_files() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Why updates cannot run on this site, or empty.
	 *
	 * @return string
	 */
	public static function get_blocker() {
		if ( ! wp_is_file_mod_allowed( 'eo_tools_mcp_update' ) ) {
			return __( 'Les mises à jour depuis WordPress sont désactivées sur ce site (DISALLOW_FILE_MODS) : elles sont sans doute gérées par votre hébergeur ou votre agence.', 'eo-tools' );
		}

		self::load_admin_files();
		if ( 'direct' !== get_filesystem_method() ) {
			return __( 'WordPress ne peut pas écrire directement ses fichiers sur ce serveur (identifiants FTP demandés) : faites les mises à jour depuis Tableau de bord > Mises à jour.', 'eo-tools' );
		}

		return '';
	}

	/* ---------------------------------------------------------------------
	 * Available updates
	 * ------------------------------------------------------------------ */

	/**
	 * Updates available for the site.
	 *
	 * @param bool $refresh Ask wordpress.org again instead of using the last check.
	 * @return array
	 */
	public static function get_available( $refresh = true ) {
		self::load_admin_files();

		if ( $refresh ) {
			wp_clean_update_cache();
			wp_version_check( array(), true );
			wp_update_plugins();
			wp_update_themes();
		}

		$data = array(
			'wordpress'    => self::get_core_update(),
			'plugins'      => array(),
			'themes'       => array(),
			'translations' => count( wp_get_translation_updates() ),
		);

		$plugins = get_plugins();
		$updates = get_site_transient( 'update_plugins' );
		foreach ( isset( $updates->response ) ? (array) $updates->response : array() as $file => $update ) {
			if ( ! isset( $plugins[ $file ] ) ) {
				continue;
			}
			$data['plugins'][] = array_merge(
				array(
					'file'            => $file,
					'name'            => $plugins[ $file ]['Name'],
					'current_version' => $plugins[ $file ]['Version'],
					'new_version'     => $update->new_version,
					'active'          => is_plugin_active( $file ),
					'changelog'       => isset( $update->url ) ? $update->url : '',
				),
				self::compatibility( $update )
			);
		}

		$updates = get_site_transient( 'update_themes' );
		foreach ( isset( $updates->response ) ? (array) $updates->response : array() as $stylesheet => $update ) {
			$theme = wp_get_theme( $stylesheet );
			if ( ! $theme->exists() ) {
				continue;
			}
			$data['themes'][] = array_merge(
				array(
					'stylesheet'      => $stylesheet,
					'name'            => $theme->get( 'Name' ),
					'current_version' => $theme->get( 'Version' ),
					'new_version'     => $update['new_version'],
					'active'          => in_array( $stylesheet, array( get_stylesheet(), get_template() ), true ),
				),
				self::compatibility( (object) $update )
			);
		}

		$data['total'] = ( $data['wordpress'] ? 1 : 0 ) + count( $data['plugins'] ) + count( $data['themes'] ) + ( $data['translations'] ? 1 : 0 );

		$blocker = self::get_blocker();
		if ( $blocker ) {
			$data['cannot_update'] = $blocker;
		}

		$log = (array) get_option( self::LOG_OPTION, array() );
		if ( $log ) {
			$data['recent_updates'] = array_slice( $log, 0, 10 );
		}

		return $data;
	}

	/**
	 * WordPress update offered to the site.
	 *
	 * @return array|null
	 */
	private static function get_core_update() {
		$update = self::find_core_offer();
		if ( ! $update ) {
			return null;
		}

		$current = get_bloginfo( 'version' );

		return array(
			'current_version' => $current,
			'new_version'     => $update->current,
			'major'           => self::is_major( $current, $update->current ),
			'compatible'      => is_php_version_compatible( $update->php_version ) && ( empty( $update->mysql_version ) || version_compare( $GLOBALS['wpdb']->db_version(), $update->mysql_version, '>=' ) ),
			'requires_php'    => $update->php_version,
		);
	}

	/**
	 * First WordPress update offer ("upgrade"), in the site language when available.
	 *
	 * @return object|null
	 */
	private static function find_core_offer() {
		$offers = get_core_updates();
		if ( ! is_array( $offers ) ) {
			return null;
		}

		$fallback = null;
		foreach ( $offers as $offer ) {
			if ( 'upgrade' !== $offer->response ) {
				continue;
			}
			if ( get_locale() === $offer->locale ) {
				return $offer;
			}
			$fallback = $fallback ? $fallback : $offer;
		}

		return $fallback;
	}

	/**
	 * Whether going from one WordPress version to another is a major update (e.g. 7.1 to 7.2).
	 *
	 * @param string $from Current version.
	 * @param string $to   New version.
	 * @return bool
	 */
	private static function is_major( $from, $to ) {
		$branch = function ( $version ) {
			return implode( '.', array_slice( explode( '.', preg_replace( '/-.*$/', '', $version ) ), 0, 2 ) );
		};

		return $branch( $from ) !== $branch( $to );
	}

	/**
	 * Compatibility of a plugin or theme update.
	 *
	 * @param object $update Update offer.
	 * @return array
	 */
	private static function compatibility( $update ) {
		$problems = array();
		if ( ! empty( $update->requires ) && ! is_wp_version_compatible( $update->requires ) ) {
			/* translators: %s: WordPress version. */
			$problems[] = sprintf( __( 'La nouvelle version demande WordPress %s ou plus.', 'eo-tools' ), $update->requires );
		}
		if ( ! empty( $update->requires_php ) && ! is_php_version_compatible( $update->requires_php ) ) {
			/* translators: %s: PHP version. */
			$problems[] = sprintf( __( 'La nouvelle version demande PHP %s ou plus.', 'eo-tools' ), $update->requires_php );
		}

		$data = array( 'compatible' => empty( $problems ) );
		if ( $problems ) {
			$data['problems'] = $problems;
		}
		if ( ! empty( $update->tested ) ) {
			$data['tested_up_to'] = $update->tested;
		}

		return $data;
	}

	/* ---------------------------------------------------------------------
	 * Running updates
	 * ------------------------------------------------------------------ */

	/**
	 * Runs the requested updates, in the WordPress order: plugins, themes, WordPress, translations.
	 *
	 * @param array $input {
	 *     @type string[]|string $plugins      Plugin files, or "all".
	 *     @type string[]|string $themes       Theme stylesheets, or "all".
	 *     @type bool            $wordpress    Update WordPress.
	 *     @type bool            $allow_major  Allow a major WordPress version.
	 *     @type bool            $translations Update the translations.
	 * }
	 * @return array|\WP_Error Report.
	 */
	public static function run( $input ) {
		$blocker = self::get_blocker();
		if ( $blocker ) {
			return new \WP_Error( 'eo_tools_updates_blocked', $blocker );
		}

		self::load_admin_files();
		if ( ! WP_Filesystem() ) {
			return new \WP_Error( 'eo_tools_updates_filesystem', __( 'Impossible d\'accéder aux fichiers du site.', 'eo-tools' ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 15 * MINUTE_IN_SECONDS ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Same as core updates.
		}

		// Use the last check: the AI looked at it with eo-tools/check-updates just before.
		$available = self::get_available( false );
		$report    = array();

		// Like the background updates: an active plugin stays active during its update.
		add_filter( 'wp_doing_cron', '__return_true' );

		$plugins = self::pick( isset( $input['plugins'] ) ? $input['plugins'] : array(), $available['plugins'], 'file' );
		usort(
			$plugins,
			function ( $a, $b ) {
				return (int) self::is_self( $a['file'] ) - (int) self::is_self( $b['file'] );
			}
		);
		foreach ( $plugins as $plugin ) {
			$report[] = current_user_can( 'update_plugins' ) ? self::update_plugin( $plugin ) : self::denied( $plugin['name'] );
		}

		foreach ( self::pick( isset( $input['themes'] ) ? $input['themes'] : array(), $available['themes'], 'stylesheet' ) as $theme ) {
			$report[] = current_user_can( 'update_themes' ) ? self::update_theme( $theme ) : self::denied( $theme['name'] );
		}

		remove_filter( 'wp_doing_cron', '__return_true' );

		if ( ! empty( $input['wordpress'] ) ) {
			$report[] = current_user_can( 'update_core' ) ? self::update_core( ! empty( $input['allow_major'] ) ) : self::denied( 'WordPress' );
		}

		if ( ! empty( $input['translations'] ) && $available['translations'] ) {
			$report[] = self::update_translations();
		}

		if ( ! $report ) {
			return new \WP_Error( 'eo_tools_updates_nothing', __( 'Aucune mise à jour à faire avec ces choix. Appelez d\'abord eo-tools/check-updates.', 'eo-tools' ) );
		}

		wp_clean_update_cache();
		self::log( $report );

		$health = self::check_site();
		$counts = array_count_values( wp_list_pluck( $report, 'status' ) );

		return array(
			'summary'   => $counts,
			'results'   => $report,
			'site_ok'   => true === $health,
			'site_note' => true === $health ? __( 'Le site répond normalement après les mises à jour.', 'eo-tools' ) : $health,
			'message'   => __( 'Faites un compte rendu simple à l\'utilisateur : ce qui a été mis à jour, ce qui a été annulé (retour automatique à l\'ancienne version) et pourquoi, et ce qui reste à faire. Proposez-lui de vérifier quelques pages importantes du site.', 'eo-tools' ),
		);
	}

	/**
	 * Requested items among the available updates.
	 *
	 * @param string[]|string $wanted    Keys, or "all".
	 * @param array[]         $available Available updates.
	 * @param string          $key       Key field.
	 * @return array[]
	 */
	private static function pick( $wanted, $available, $key ) {
		if ( 'all' === $wanted || array( 'all' ) === $wanted ) {
			return $available;
		}

		$wanted = (array) $wanted;
		$picked = array();
		foreach ( $available as $item ) {
			// Accept the plugin file or its folder ("contact-form-7").
			if ( in_array( $item[ $key ], $wanted, true ) || in_array( dirname( $item[ $key ] ), $wanted, true ) ) {
				$picked[] = $item;
			}
		}

		return $picked;
	}

	private static function is_self( $file ) {
		return in_array( dirname( $file ), self::SELF_PLUGINS, true );
	}

	private static function denied( $name ) {
		return array(
			'name'    => $name,
			'status'  => 'skipped',
			'message' => __( 'Droits insuffisants pour cette mise à jour.', 'eo-tools' ),
		);
	}

	/**
	 * Updates one plugin; restores the previous version if the site breaks.
	 *
	 * @param array $plugin Available update.
	 * @return array Result.
	 */
	private static function update_plugin( $plugin ) {
		$result = array(
			'type' => 'plugin',
			'name' => $plugin['name'],
			'from' => $plugin['current_version'],
			'to'   => $plugin['new_version'],
		);

		if ( ! $plugin['compatible'] ) {
			return $result + array(
				'status'  => 'skipped',
				'message' => implode( ' ', $plugin['problems'] ),
			);
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );

		$upgrader->maintenance_mode( true );
		$done = $upgrader->upgrade( $plugin['file'], array( 'clear_update_cache' => false ) );

		$error = self::upgrader_error( $done, $skin );
		if ( $error ) {
			$upgrader->maintenance_mode( false );
			return $result + array(
				'status'  => 'failed',
				'message' => $error,
			);
		}

		if ( $plugin['active'] ) {
			$health = self::check_site();
			if ( true !== $health ) {
				$backup   = array(
					array(
						'dir'  => 'plugins',
						'slug' => dirname( $plugin['file'] ),
						'src'  => WP_PLUGIN_DIR,
					),
				);
				$restored = $upgrader->restore_temp_backup( $backup );
				$upgrader->delete_temp_backup( $backup );
				$upgrader->maintenance_mode( false );

				return $result + array(
					'status'  => is_wp_error( $restored ) ? 'broken' : 'rolled_back',
					'message' => is_wp_error( $restored )
						/* translators: %s: error. */
						? sprintf( __( 'La nouvelle version casse le site et l\'ancienne n\'a pas pu être restaurée : %s', 'eo-tools' ), $restored->get_error_message() )
						/* translators: %s: error. */
						: sprintf( __( 'La nouvelle version cassait le site : l\'ancienne version a été restaurée. Erreur : %s', 'eo-tools' ), $health ),
				);
			}
		}

		$upgrader->maintenance_mode( false );

		return $result + array( 'status' => 'updated' );
	}

	/**
	 * Updates one theme; restores the previous version if the active theme breaks the site.
	 *
	 * @param array $theme Available update.
	 * @return array Result.
	 */
	private static function update_theme( $theme ) {
		$result = array(
			'type' => 'theme',
			'name' => $theme['name'],
			'from' => $theme['current_version'],
			'to'   => $theme['new_version'],
		);

		if ( ! $theme['compatible'] ) {
			return $result + array(
				'status'  => 'skipped',
				'message' => implode( ' ', $theme['problems'] ),
			);
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );

		$upgrader->maintenance_mode( true );
		$done  = $upgrader->upgrade( $theme['stylesheet'], array( 'clear_update_cache' => false ) );
		$error = self::upgrader_error( $done, $skin );
		if ( $error ) {
			$upgrader->maintenance_mode( false );
			return $result + array(
				'status'  => 'failed',
				'message' => $error,
			);
		}

		if ( $theme['active'] ) {
			$health = self::check_site();
			if ( true !== $health ) {
				$backup   = array(
					array(
						'dir'  => 'themes',
						'slug' => $theme['stylesheet'],
						'src'  => get_theme_root( $theme['stylesheet'] ),
					),
				);
				$restored = $upgrader->restore_temp_backup( $backup );
				$upgrader->delete_temp_backup( $backup );
				$upgrader->maintenance_mode( false );

				return $result + array(
					'status'  => is_wp_error( $restored ) ? 'broken' : 'rolled_back',
					/* translators: %s: error. */
					'message' => sprintf( __( 'La nouvelle version cassait le site : l\'ancienne version a été restaurée. Erreur : %s', 'eo-tools' ), $health ),
				);
			}
		}

		$upgrader->maintenance_mode( false );

		return $result + array( 'status' => 'updated' );
	}

	/**
	 * Updates WordPress. WordPress restores its own files if the copy fails.
	 *
	 * @param bool $allow_major Allow a major version.
	 * @return array Result.
	 */
	private static function update_core( $allow_major ) {
		$offer   = self::find_core_offer();
		$current = get_bloginfo( 'version' );
		$result  = array(
			'type' => 'wordpress',
			'name' => 'WordPress',
			'from' => $current,
		);

		if ( ! $offer ) {
			return $result + array(
				'status'  => 'skipped',
				'message' => __( 'WordPress est déjà à jour.', 'eo-tools' ),
			);
		}

		$result['to'] = $offer->current;

		if ( self::is_major( $current, $offer->current ) && ! $allow_major ) {
			return $result + array(
				'status'  => 'skipped',
				'message' => __( 'Version majeure : demandez l\'accord de l\'utilisateur (après une sauvegarde), puis relancez avec "allow_major": true.', 'eo-tools' ),
			);
		}
		if ( ! is_php_version_compatible( $offer->php_version ) ) {
			return $result + array(
				'status'  => 'skipped',
				/* translators: %s: PHP version. */
				'message' => sprintf( __( 'Cette version demande PHP %s ou plus.', 'eo-tools' ), $offer->php_version ),
			);
		}

		$skin = new \Automatic_Upgrader_Skin();
		add_filter( 'update_feedback', array( $skin, 'feedback' ) );
		$upgrader = new \Core_Upgrader( $skin );
		$done     = $upgrader->upgrade( $offer, array( 'pre_check_md5' => false ) );
		remove_filter( 'update_feedback', array( $skin, 'feedback' ) );

		if ( is_wp_error( $done ) ) {
			return $result + array(
				'status'  => 'up_to_date' === $done->get_error_code() ? 'skipped' : 'failed',
				'message' => $done->get_error_message(),
			);
		}
		if ( ! $done ) {
			return $result + array(
				'status'  => 'failed',
				'message' => __( 'Impossible d\'accéder aux fichiers du site.', 'eo-tools' ),
			);
		}

		return $result + array( 'status' => 'updated' );
	}

	/**
	 * Updates every translation.
	 *
	 * @return array Result.
	 */
	private static function update_translations() {
		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Language_Pack_Upgrader( $skin );
		$done     = $upgrader->bulk_upgrade( array(), array( 'clear_update_cache' => false ) );

		return array(
			'type'   => 'translations',
			'name'   => __( 'Traductions', 'eo-tools' ),
			'status' => is_wp_error( $done ) || false === $done ? 'failed' : 'updated',
		) + ( is_wp_error( $done ) ? array( 'message' => $done->get_error_message() ) : array() );
	}

	/**
	 * Error of an upgrader run, or empty.
	 *
	 * @param mixed                     $done Upgrader result.
	 * @param \Automatic_Upgrader_Skin $skin Skin.
	 * @return string
	 */
	private static function upgrader_error( $done, $skin ) {
		if ( is_wp_error( $done ) ) {
			return $done->get_error_message();
		}
		if ( is_wp_error( $skin->result ) ) {
			return $skin->result->get_error_message();
		}
		if ( false === $done || null === $done ) {
			return __( 'Impossible d\'accéder aux fichiers du site.', 'eo-tools' );
		}

		return '';
	}

	/**
	 * Loads the home page like the WordPress background updates do, and looks for a fatal error.
	 *
	 * @return true|string True, or the error.
	 */
	public static function check_site() {
		// During maintenance mode, WordPress only serves the page to a scrape key derived from the .maintenance file.
		$upgrading = null;
		if ( file_exists( ABSPATH . '.maintenance' ) ) {
			include ABSPATH . '.maintenance';
		}
		if ( is_int( $upgrading ) ) {
			$key   = md5( $upgrading );
			$nonce = (string) $upgrading;
		} else {
			$key   = md5( wp_generate_password( 32, false ) );
			$nonce = (string) wp_rand();
		}
		set_transient( 'scrape_key_' . $key, $nonce, 60 );

		// The update may need a short delay to be served (opcode cache), as in core.
		sleep( 2 );

		$url      = add_query_arg(
			array(
				'wp_scrape_key'   => $key,
				'wp_scrape_nonce' => $nonce,
			),
			home_url( '/' )
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 50,
				'headers'   => array( 'Cache-Control' => 'no-cache' ),
				/** This filter is documented in wp-includes/class-wp-http-streams.php */
				'sslverify' => apply_filters( 'https_local_ssl_verify', false, $url ),
			)
		);
		delete_transient( 'scrape_key_' . $key );

		if ( is_wp_error( $response ) ) {
			/* translators: %s: error. */
			return sprintf( __( 'Le site ne répond pas : %s', 'eo-tools' ), $response->get_error_message() );
		}

		$body  = wp_remote_retrieve_body( $response );
		$start = "###### wp_scraping_result_start:$key ######";
		$pos   = strpos( $body, $start );
		if ( false !== $pos ) {
			$json  = substr( $body, $pos + strlen( $start ) );
			$json  = substr( $json, 0, (int) strpos( $json, "###### wp_scraping_result_end:$key ######" ) );
			$error = json_decode( trim( $json ), true );
			if ( is_array( $error ) && isset( $error['message'] ) ) {
				return Eotools_Mcp_Snippets::clean_error( $error['message'] . ( isset( $error['file'] ) ? ' (' . $error['file'] . ':' . $error['line'] . ')' : '' ) );
			}
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 500 ) {
			/* translators: %d: HTTP status. */
			return sprintf( __( 'Le site répond avec une erreur HTTP %d.', 'eo-tools' ), $code );
		}

		return true;
	}

	/**
	 * Records the results in the update history.
	 *
	 * @param array[] $report Results.
	 */
	private static function log( $report ) {
		$log  = (array) get_option( self::LOG_OPTION, array() );
		$user = wp_get_current_user();
		foreach ( $report as $item ) {
			array_unshift(
				$log,
				array_filter(
					array(
						'date'    => gmdate( 'c' ),
						'user'    => $user->user_login,
						'name'    => $item['name'],
						'from'    => isset( $item['from'] ) ? $item['from'] : '',
						'to'      => isset( $item['to'] ) ? $item['to'] : '',
						'status'  => $item['status'],
						'message' => isset( $item['message'] ) ? $item['message'] : '',
					)
				)
			);
		}

		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_SIZE ), false );
	}
}
