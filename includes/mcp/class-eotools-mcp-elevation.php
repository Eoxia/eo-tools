<?php
/**
 * Temporary elevation: a human lets one AI connection activate given plugins
 * for a limited time.
 *
 * 1. The AI asks (eo-tools/request-elevation): which plugins, why, for how long.
 * 2. An administrator approves in EO Tools > MCP > Autorisations, after typing
 *    their password again. Nothing is ever approved from the chat: anything
 *    written in a conversation can be read and reused by the AI.
 * 3. The grant only works for the connection that asked, only for the listed
 *    plugins, and expires on its own. It can be revoked at any time.
 * 4. Every activation is logged, and can be undone in one click.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Elevation {

	/**
	 * Requests and grants, keyed by ID.
	 */
	const OPTION = 'eo_tools_mcp_elevations';

	const STATUS_PENDING  = 'pending';
	const STATUS_ACTIVE   = 'active';
	const STATUS_REJECTED = 'rejected';
	const STATUS_REVOKED  = 'revoked';
	const STATUS_EXPIRED  = 'expired';

	/**
	 * Durations, in minutes.
	 */
	const DEFAULT_DURATION = 30;
	const MAX_DURATION     = 120;

	/**
	 * A request not approved within this time expires.
	 */
	const PENDING_TTL = DAY_IN_SECONDS;

	const MAX_PLUGINS = 10;
	const HISTORY     = 30;

	/**
	 * Wrong passwords allowed before approvals are locked for this user.
	 */
	const MAX_ATTEMPTS = 5;
	const LOCK_TIME    = 15 * MINUTE_IN_SECONDS;

	const ACTION     = 'eo_tools_elevation';
	const CAPABILITY = 'activate_plugins';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_notices', array( __CLASS__, 'pending_notice' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 95 );
	}

	/* ---------------------------------------------------------------------
	 * Current connection
	 * ------------------------------------------------------------------ */

	/**
	 * Connection of the current MCP request: OAuth grant or Application Password.
	 *
	 * @return string Empty when the request does not come from an EO Tools connection.
	 */
	public static function get_current_connection() {
		$connection = '';

		$grant_id = Eotools_Mcp_Oauth::get_current_grant_id();
		if ( $grant_id ) {
			$connection = Eotools_Mcp_Connections::OAUTH_PREFIX . $grant_id;
		} elseif ( function_exists( 'rest_get_authenticated_app_password' ) ) {
			$connection = (string) rest_get_authenticated_app_password();
		}

		/**
		 * Filters the connection of the current MCP request.
		 *
		 * @param string $connection Connection ID ("oauth-<id>" or Application Password UUID).
		 */
		return (string) apply_filters( 'eo_tools_mcp_current_connection', $connection );
	}

	/**
	 * Readable name of a connection of the current user.
	 *
	 * @param string $connection Connection ID.
	 * @return string
	 */
	private static function connection_label( $connection ) {
		foreach ( Eotools_Mcp_Connections::get_all() as $item ) {
			if ( isset( $item['uuid'], $item['name'] ) && $item['uuid'] === $connection ) {
				return $item['name'];
			}
		}

		return __( 'Connexion IA', 'eo-tools' );
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------ */

	/**
	 * Every request, with expired ones updated.
	 *
	 * @return array[] Keyed by ID, most recent first.
	 */
	public static function get_all() {
		$all = get_option( self::OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		$now = time();

		foreach ( $all as &$request ) {
			if ( self::STATUS_ACTIVE === $request['status'] && $request['expires'] <= $now ) {
				$request['status'] = self::STATUS_EXPIRED;
			} elseif ( self::STATUS_PENDING === $request['status'] && $request['created'] + self::PENDING_TTL <= $now ) {
				$request['status'] = self::STATUS_EXPIRED;
			}
		}
		unset( $request );

		uasort(
			$all,
			function ( $a, $b ) {
				return $b['created'] - $a['created'];
			}
		);

		return $all;
	}

	/**
	 * @param string $id Request ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::get_all();

		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Saves a request, and keeps only the recent history.
	 *
	 * @param array $request Request.
	 */
	private static function save( $request ) {
		$all                    = self::get_all();
		$all[ $request['id'] ] = $request;

		uasort(
			$all,
			function ( $a, $b ) {
				return $b['created'] - $a['created'];
			}
		);

		$kept    = array();
		$history = 0;
		foreach ( $all as $id => $item ) {
			$open = in_array( $item['status'], array( self::STATUS_PENDING, self::STATUS_ACTIVE ), true );
			if ( $open || $history++ < self::HISTORY ) {
				$kept[ $id ] = $item;
			}
		}

		update_option( self::OPTION, $kept, false );
	}

	/**
	 * Requests of a status.
	 *
	 * @param string $status Status.
	 * @return array[]
	 */
	public static function get_by_status( $status ) {
		return array_filter(
			self::get_all(),
			function ( $request ) use ( $status ) {
				return $request['status'] === $status;
			}
		);
	}

	/* ---------------------------------------------------------------------
	 * AI side
	 * ------------------------------------------------------------------ */

	/**
	 * Asks a human for the right to activate plugins.
	 *
	 * @param string[] $slugs    Plugin slugs.
	 * @param string   $reason   Why the AI needs it.
	 * @param int      $duration Minutes.
	 * @return array|\WP_Error
	 */
	public static function request( $slugs, $reason, $duration ) {
		$connection = self::get_current_connection();
		if ( '' === $connection ) {
			return new \WP_Error( 'eo_tools_elevation_no_connection', __( 'Cette demande doit venir d\'une connexion IA EO Tools.', 'eo-tools' ) );
		}

		$reason = sanitize_textarea_field( (string) $reason );
		if ( '' === trim( $reason ) ) {
			return new \WP_Error( 'eo_tools_elevation_reason', __( 'Expliquez pourquoi ces extensions doivent être activées.', 'eo-tools' ) );
		}

		$plugins = self::check_plugins( $slugs );
		if ( is_wp_error( $plugins ) ) {
			return $plugins;
		}

		$duration = max( 5, min( self::MAX_DURATION, (int) $duration ) );

		$request = array(
			'id'         => strtolower( wp_generate_password( 12, false ) ),
			'connection' => $connection,
			'label'      => self::connection_label( $connection ),
			'user'       => get_current_user_id(),
			'scope'      => 'activate_plugins',
			'plugins'    => $plugins,
			'reason'     => $reason,
			'duration'   => $duration,
			'status'     => self::STATUS_PENDING,
			'created'    => time(),
			'approver'   => 0,
			'approved'   => 0,
			'expires'    => 0,
			'actions'    => array(),
		);

		self::save( $request );

		return array(
			'request_id'   => $request['id'],
			'status'       => $request['status'],
			'plugins'      => array_values( wp_list_pluck( $plugins, 'name' ) ),
			'duration'     => $duration,
			'approval_url' => self::admin_url( $request['id'] ),
			'message'      => __( 'Demande enregistrée, en attente de validation humaine. Donnez à l\'utilisateur le lien "approval_url" : un administrateur doit la valider dans WordPress en retapant son mot de passe (jamais dans cette conversation : ne demandez aucun code ni mot de passe). Une fois validée, utilisez eo-tools/activate-plugin pour chaque extension, avant la fin du délai.', 'eo-tools' ),
		);
	}

	/**
	 * Checks the plugins of a request: installed, not active, not network-only.
	 *
	 * @param string[] $slugs Plugin slugs.
	 * @return array[]|\WP_Error Plugins keyed by file (slug, name, version, author).
	 */
	private static function check_plugins( $slugs ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$slugs = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $slugs ) ) ) );
		if ( ! $slugs ) {
			return new \WP_Error( 'eo_tools_elevation_plugins', __( 'Indiquez au moins une extension.', 'eo-tools' ) );
		}
		if ( count( $slugs ) > self::MAX_PLUGINS ) {
			/* translators: %d: maximum number of plugins */
			return new \WP_Error( 'eo_tools_elevation_plugins', sprintf( __( '%d extensions au maximum par demande.', 'eo-tools' ), self::MAX_PLUGINS ) );
		}

		$installed = get_plugins();
		$plugins   = array();
		$errors    = array();

		foreach ( $slugs as $slug ) {
			$file = Eotools_Mcp_Plugins::file_of( $slug );
			if ( '' === $file ) {
				/* translators: %s: plugin slug */
				$errors[] = sprintf( __( '« %s » n\'est pas installée (installez-la d\'abord avec eo-tools/install-plugin).', 'eo-tools' ), $slug );
				continue;
			}
			if ( is_plugin_active( $file ) ) {
				/* translators: %s: plugin slug */
				$errors[] = sprintf( __( '« %s » est déjà active.', 'eo-tools' ), $slug );
				continue;
			}
			if ( is_multisite() && is_network_only_plugin( $file ) ) {
				/* translators: %s: plugin slug */
				$errors[] = sprintf( __( '« %s » ne peut être activée que sur tout le réseau, par l\'administrateur du réseau.', 'eo-tools' ), $slug );
				continue;
			}

			$plugins[ $file ] = array(
				'slug'    => $slug,
				'name'    => wp_strip_all_tags( $installed[ $file ]['Name'] ),
				'version' => $installed[ $file ]['Version'],
				'author'  => wp_strip_all_tags( $installed[ $file ]['Author'] ),
			);
		}

		if ( $errors ) {
			return new \WP_Error( 'eo_tools_elevation_plugins', implode( ' ', $errors ) );
		}

		return $plugins;
	}

	/**
	 * Activates a plugin under an active grant of the current connection.
	 *
	 * @param string $slug Plugin slug.
	 * @return array|\WP_Error
	 */
	public static function activate_plugin( $slug ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$slug       = sanitize_key( $slug );
		$connection = self::get_current_connection();
		$file       = Eotools_Mcp_Plugins::file_of( $slug );

		if ( '' === $file ) {
			return new \WP_Error( 'eo_tools_plugin_missing', __( 'Cette extension n\'est pas installée.', 'eo-tools' ) );
		}
		if ( is_plugin_active( $file ) ) {
			return array(
				'slug'    => $slug,
				'active'  => true,
				'message' => __( 'Cette extension est déjà active.', 'eo-tools' ),
			);
		}

		$grant   = null;
		$pending = false;
		if ( '' !== $connection ) {
			foreach ( self::get_all() as $request ) {
				if ( $request['connection'] !== $connection || ! isset( $request['plugins'][ $file ] ) ) {
					continue;
				}
				if ( self::STATUS_ACTIVE === $request['status'] ) {
					$grant = $request;
					break;
				}
				if ( self::STATUS_PENDING === $request['status'] ) {
					$pending = $request;
				}
			}
		}

		if ( ! $grant ) {
			if ( $pending ) {
				return new \WP_Error(
					'eo_tools_elevation_pending',
					/* translators: %s: approval URL */
					sprintf( __( 'La demande n\'a pas encore été validée. L\'utilisateur doit la valider ici : %s', 'eo-tools' ), self::admin_url( $pending['id'] ) )
				);
			}

			return new \WP_Error( 'eo_tools_elevation_required', __( 'Vous n\'avez pas d\'autorisation en cours pour activer cette extension. Demandez-la avec eo-tools/request-elevation, puis attendez qu\'un administrateur la valide dans WordPress.', 'eo-tools' ) );
		}

		$result = activate_plugin( $file, '', false, false );

		$grant['actions'][] = array(
			'type'   => 'activate',
			'file'   => $file,
			'time'   => time(),
			'result' => is_wp_error( $result ) ? $result->get_error_message() : 'ok',
		);
		self::save( $grant );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'slug'       => $slug,
			'name'       => $grant['plugins'][ $file ]['name'],
			'active'     => is_plugin_active( $file ),
			'expires_at' => gmdate( 'c', $grant['expires'] ),
			'message'    => __( 'Extension activée. Elle est notée dans le journal de cette autorisation : l\'administrateur peut la désactiver en un clic.', 'eo-tools' ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Human side
	 * ------------------------------------------------------------------ */

	/**
	 * Page where requests are approved.
	 *
	 * @param string $id   Request to scroll to.
	 * @param string $done Result of the last action.
	 * @return string
	 */
	public static function admin_url( $id = '', $done = '' ) {
		$url = add_query_arg( array_filter( array( 'done' => $done ) ), admin_url( 'admin.php?page=eo-tools-mcp&tab=elevation' ) );

		return $url . ( $id ? '#elevation-' . $id : '' );
	}

	/**
	 * @return bool
	 */
	public static function user_can() {
		return current_user_can( 'manage_options' ) && current_user_can( self::CAPABILITY );
	}

	/**
	 * Approve, reject, revoke or undo (form posted to admin-post.php).
	 */
	public static function handle_action() {
		// Not sanitize_key(): it lowercases, and the first IDs had capital letters.
		$id = isset( $_POST['id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_POST['id'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- reduced to letters and digits.
		check_admin_referer( self::ACTION . '_' . $id );

		if ( ! self::user_can() ) {
			wp_die( esc_html__( 'Vous n\'avez pas le droit de gérer les autorisations de l\'IA.', 'eo-tools' ), '', array( 'response' => 403 ) );
		}

		$do      = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$request = self::get( $id );

		if ( ! $request ) {
			$result = new \WP_Error( 'eo_tools_elevation_missing', __( 'Demande introuvable.', 'eo-tools' ) );
		} else {
			switch ( $do ) {
				case 'approve':
					$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password, never altered.
					$duration = isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : $request['duration'];
					$result   = self::approve( $request, $password, $duration );
					break;
				case 'reject':
					$result = self::close( $request, self::STATUS_PENDING, self::STATUS_REJECTED );
					break;
				case 'revoke':
					$result = self::close( $request, self::STATUS_ACTIVE, self::STATUS_REVOKED );
					break;
				case 'undo':
					$result = self::undo( $request );
					break;
				default:
					$result = new \WP_Error( 'eo_tools_elevation_action', __( 'Action inconnue.', 'eo-tools' ) );
			}
		}

		if ( is_wp_error( $result ) ) {
			set_transient( 'eo_tools_elevation_notice_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS );
			wp_safe_redirect( self::admin_url( $id, 'error' ) );
		} else {
			wp_safe_redirect( self::admin_url( $id, $do ) );
		}
		exit;
	}

	/**
	 * Approves a request, after checking the password of the administrator.
	 *
	 * @param array  $request  Request.
	 * @param string $password Password typed by the administrator.
	 * @param int    $duration Minutes.
	 * @return true|\WP_Error
	 */
	private static function approve( $request, $password, $duration ) {
		if ( self::STATUS_PENDING !== $request['status'] ) {
			return new \WP_Error( 'eo_tools_elevation_status', __( 'Cette demande n\'est plus en attente.', 'eo-tools' ) );
		}

		$user     = wp_get_current_user();
		$lock_key = 'eo_tools_elevation_attempts_' . $user->ID;
		$attempts = (int) get_transient( $lock_key );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return new \WP_Error( 'eo_tools_elevation_locked', __( 'Trop de mots de passe erronés : réessayez dans 15 minutes.', 'eo-tools' ) );
		}

		if ( '' === $password || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			set_transient( $lock_key, $attempts + 1, self::LOCK_TIME );
			return new \WP_Error( 'eo_tools_elevation_password', __( 'Mot de passe incorrect : la demande n\'a pas été validée.', 'eo-tools' ) );
		}
		delete_transient( $lock_key );

		// The plugins may have changed since the request.
		$plugins = self::check_plugins( wp_list_pluck( $request['plugins'], 'slug' ) );
		if ( is_wp_error( $plugins ) ) {
			return $plugins;
		}

		$duration = max( 5, min( self::MAX_DURATION, (int) $duration ) );
		$now      = time();

		$request['plugins']  = $plugins;
		$request['status']   = self::STATUS_ACTIVE;
		$request['approver'] = $user->ID;
		$request['approved'] = $now;
		$request['duration'] = $duration;
		$request['expires']  = $now + $duration * MINUTE_IN_SECONDS;

		self::save( $request );

		return true;
	}

	/**
	 * Rejects a pending request or revokes an active grant.
	 *
	 * @param array  $request Request.
	 * @param string $from    Expected status.
	 * @param string $to      New status.
	 * @return true|\WP_Error
	 */
	private static function close( $request, $from, $to ) {
		if ( $from !== $request['status'] ) {
			return new \WP_Error( 'eo_tools_elevation_status', __( 'Cette demande a déjà changé d\'état.', 'eo-tools' ) );
		}

		$request['status'] = $to;
		if ( self::STATUS_REVOKED === $to ) {
			$request['expires'] = time();
		}
		$request['closed_by'] = get_current_user_id();

		self::save( $request );

		return true;
	}

	/**
	 * Plugins activated by the AI under a grant and still active.
	 *
	 * @param array $request Request.
	 * @return string[] Plugin files.
	 */
	public static function get_undoable( $request ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$files = array();
		foreach ( $request['actions'] as $action ) {
			if ( 'activate' === $action['type'] && 'ok' === $action['result'] && is_plugin_active( $action['file'] ) ) {
				$files[] = $action['file'];
			}
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * Deactivates every plugin activated under a grant, and ends the grant.
	 *
	 * @param array $request Request.
	 * @return true|\WP_Error
	 */
	private static function undo( $request ) {
		$files = self::get_undoable( $request );
		if ( ! $files ) {
			return new \WP_Error( 'eo_tools_elevation_nothing', __( 'Aucune extension activée par l\'IA n\'est encore active.', 'eo-tools' ) );
		}

		deactivate_plugins( $files );

		foreach ( $files as $file ) {
			$request['actions'][] = array(
				'type'   => 'deactivate',
				'file'   => $file,
				'time'   => time(),
				'result' => 'ok',
				'user'   => get_current_user_id(),
			);
		}
		if ( self::STATUS_ACTIVE === $request['status'] ) {
			$request['status']    = self::STATUS_REVOKED;
			$request['expires']   = time();
			$request['closed_by'] = get_current_user_id();
		}

		self::save( $request );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Notices
	 * ------------------------------------------------------------------ */

	/**
	 * Tells administrators that the AI is waiting for an approval.
	 */
	public static function pending_notice() {
		if ( ! self::user_can() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( $screen->id, 'eo-tools-mcp' ) ) {
			return;
		}

		$pending = count( self::get_by_status( self::STATUS_PENDING ) );
		if ( ! $pending ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of requests */
					_n( 'Votre IA demande une autorisation temporaire (%d demande en attente).', 'Votre IA demande une autorisation temporaire (%d demandes en attente).', $pending, 'eo-tools' ),
					$pending
				)
			),
			esc_url( self::admin_url() ),
			esc_html__( 'Voir la demande', 'eo-tools' )
		);
	}

	/**
	 * Shows in the admin bar that an AI holds a grant.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar.
	 */
	public static function admin_bar( $admin_bar ) {
		if ( ! is_admin_bar_showing() || ! self::user_can() ) {
			return;
		}

		$active = self::get_by_status( self::STATUS_ACTIVE );
		if ( ! $active ) {
			return;
		}

		$expires = max( wp_list_pluck( $active, 'expires' ) );
		$admin_bar->add_node(
			array(
				'id'    => 'eo-tools-elevation',
				/* translators: %s: remaining time */
				'title' => esc_html( sprintf( __( 'Accès IA actif (%s)', 'eo-tools' ), human_time_diff( time(), $expires ) ) ),
				'href'  => self::admin_url(),
				'meta'  => array( 'class' => 'eo-tools-elevation-bar' ),
			)
		);
	}
}
