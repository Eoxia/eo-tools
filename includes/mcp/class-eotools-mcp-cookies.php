<?php
/**
 * EO Tools cookie manager for the AI: scan, cookie list and admin validation.
 *
 * Same data and same records as the EO Tools > Cookies screen:
 * - the scan is the detailed scan of the "Scan" tab (EoTools_Detailed_Scan),
 *   run in steps within a time budget, then resumed by calling it again;
 * - the cookie list is the option "eo_tools_cookie_registry" (by category);
 * - every change is written in the consent report (table eotools_cookie_log)
 *   like the screen does: SCAN_SYNC, ADMIN_MODIFICATION, ADMIN_VALIDATION.
 *
 * The manager settings (on/off, consent lifetime, icons) are a plugin adapter:
 * includes/mcp/plugins/class-eotools-mcp-plugin-eo-tools-cookies.php.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Cookies {

	const REGISTRY = 'eo_tools_cookie_registry';

	/**
	 * Scan started by the AI and not finished yet.
	 */
	const SCAN_OPTION = 'eo_tools_mcp_cookie_scan';

	/**
	 * Seconds of scanning per call, to stay below the MCP request timeout.
	 */
	const TIME_BUDGET = 25;

	/**
	 * Categories of the cookie list.
	 */
	const CATEGORIES = array( 'strictly-necessary', 'functional', 'analytics', 'performance', 'marketing', 'social', 'others' );

	/**
	 * Open Cookie Database categories mapped to EO Tools categories.
	 */
	const DB_CATEGORIES = array(
		'necessary'       => 'strictly-necessary',
		'security'        => 'strictly-necessary',
		'functional'      => 'functional',
		'personalization' => 'functional',
		'analytics'       => 'analytics',
		'marketing'       => 'marketing',
	);

	/* ---------------------------------------------------------------------
	 * Cookie list
	 * ------------------------------------------------------------------ */

	/**
	 * @return array[] Registry keyed by category.
	 */
	private static function get_registry() {
		$registry = get_option( self::REGISTRY, array() );

		return is_array( $registry ) ? $registry : array();
	}

	/**
	 * Cookie list as shown to the AI, in the order of the screen.
	 *
	 * @return array[] { name, category, domain, duration_days, description }.
	 */
	public static function get_list() {
		$list = array();
		foreach ( self::get_registry() as $category => $cookies ) {
			foreach ( (array) $cookies as $cookie ) {
				if ( empty( $cookie['name'] ) ) {
					continue;
				}
				$list[] = array(
					'name'          => (string) $cookie['name'],
					'category'      => (string) $category,
					'domain'        => isset( $cookie['domain'] ) ? (string) $cookie['domain'] : '',
					'duration_days' => (int) ( isset( $cookie['date'] ) ? $cookie['date'] : ( isset( $cookie['duration'] ) ? $cookie['duration'] : 0 ) ),
					'description'   => isset( $cookie['comment'] ) ? (string) $cookie['comment'] : ( isset( $cookie['description'] ) ? (string) $cookie['description'] : '' ),
				);
			}
		}

		return $list;
	}

	/**
	 * Names of the cookies, in the order compared by the "Validation Admin" banner of the screen.
	 *
	 * @return string[]
	 */
	private static function get_names() {
		return wp_list_pluck( self::get_list(), 'name' );
	}

	/**
	 * Whether the current list was validated by an administrator.
	 *
	 * @return bool
	 */
	public static function is_validated() {
		global $wpdb;
		$table = $wpdb->prefix . 'eotools_cookie_log';
		$last  = $wpdb->get_var( "SELECT comments FROM $table WHERE consent_status = 'ADMIN_VALIDATION' ORDER BY time DESC, id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return null !== $last && implode( ', ', self::get_names() ) === $last;
	}

	/**
	 * Writes a line in the consent report, like the Cookies screen.
	 *
	 * @param string   $type  SCAN_SYNC, ADMIN_MODIFICATION or ADMIN_VALIDATION.
	 * @param string[] $lines Changes.
	 */
	private static function report( $type, $lines ) {
		global $wpdb;
		if ( ! $lines ) {
			return;
		}
		$comments = implode( ', ', $lines );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'eotools_cookie_log',
			array(
				'consent_id'     => md5( $comments ),
				'consent_status' => $type,
				'comments'       => $comments,
				'time'           => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Known cookie of the Open Cookie Database.
	 *
	 * @param string $name Cookie name.
	 * @return array|null { category, description }.
	 */
	private static function lookup( $name ) {
		static $index = null;

		if ( null === $index ) {
			$index = array();
			$file  = EO_TOOLS_PATH . 'assets/data/open-cookie-database.json';
			$data  = file_exists( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( isset( $data[0]['knowledges'] ) && is_array( $data[0]['knowledges'] ) ) {
				foreach ( $data[0]['knowledges'] as $item ) {
					if ( ! empty( $item['name'] ) && ! isset( $index[ strtolower( $item['name'] ) ] ) ) {
						$index[ strtolower( $item['name'] ) ] = $item;
					}
				}
			}
		}

		$item = isset( $index[ strtolower( $name ) ] ) ? $index[ strtolower( $name ) ] : null;
		if ( ! $item ) {
			return null;
		}

		return array(
			'category'    => isset( self::DB_CATEGORIES[ $item['category'] ] ) ? self::DB_CATEGORIES[ $item['category'] ] : 'others',
			'description' => trim( ( ! empty( $item['source'] ) ? $item['source'] . ' : ' : '' ) . ( isset( $item['comment'] ) ? $item['comment'] : '' ) ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Scan
	 * ------------------------------------------------------------------ */

	/**
	 * Runs the detailed scan of the site, or resumes the one in progress.
	 *
	 * @param bool $restart Start a new scan even if one is in progress.
	 * @return array|\WP_Error
	 */
	public static function scan( $restart = false ) {
		if ( ! class_exists( '\EoTools_Detailed_Scan' ) ) {
			return new \WP_Error( 'eo_tools_cookie_scan', __( 'Le scan des cookies n\'est pas disponible.', 'eo-tools' ) );
		}

		global $wpdb;
		$scan_id = (int) get_option( self::SCAN_OPTION, 0 );
		if ( $scan_id ) {
			$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}eotools_scan WHERE id = %d", $scan_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( 'pending' !== $status ) {
				$scan_id = 0;
			}
		}
		if ( ! $scan_id || $restart ) {
			$start   = \EoTools_Detailed_Scan::start_scan();
			$scan_id = (int) $start['batch_id'];
			update_option( self::SCAN_OPTION, $scan_id, false );
		}

		$deadline = microtime( true ) + self::TIME_BUDGET;
		do {
			$step = \EoTools_Detailed_Scan::process_batch( $scan_id, 1 );
		} while ( 'complete' !== $step['status'] && microtime( true ) < $deadline );

		$ref = (string) $wpdb->get_var( $wpdb->prepare( "SELECT ref FROM {$wpdb->prefix}eotools_scan WHERE id = %d", $scan_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( 'complete' !== $step['status'] ) {
			return array(
				'status'    => 'in_progress',
				'ref'       => $ref,
				'scanned'   => (int) $step['scanned'],
				'total'     => (int) $step['total'],
				'next_step' => __( 'Le scan continue : rappelez eo-tools/scan-cookies (sans "restart") pour le reprendre là où il s\'est arrêté.', 'eo-tools' ),
			);
		}

		delete_option( self::SCAN_OPTION );
		$added = self::add_scanned( $step['added_cookies'] );

		return array(
			'status'    => 'complete',
			'ref'       => $ref,
			'added'     => $added,
			'cookies'   => self::get_list(),
			'validated' => self::is_validated(),
			'next_step' => __( 'Vérifiez la liste : corrigez la catégorie et rédigez en français la description des cookies ajoutés (les noms "script_…" signalent un script tiers détecté dans la page, remplacez-les par les vrais cookies du service s\'il est identifiable), puis validez la liste avec eo-tools/update-cookies ("validate": true).', 'eo-tools' ),
		);
	}

	/**
	 * Adds the cookies found by a scan to the list, like the screen does when a scan ends.
	 *
	 * @param array[] $found { cat, cookie } items of the scan.
	 * @return array[] Added cookies { name, category, description }.
	 */
	private static function add_scanned( $found ) {
		$registry = self::get_registry();
		$names    = array_map( 'strtolower', self::get_names() );
		$added    = array();

		foreach ( (array) $found as $item ) {
			$cookie = $item['cookie'];
			if ( in_array( strtolower( $cookie['name'] ), $names, true ) ) {
				continue;
			}
			$category = $item['cat'];
			$known    = self::lookup( $cookie['name'] );
			if ( $known ) {
				$category          = $known['category'];
				$cookie['comment'] = $known['description'];
			}
			$registry[ $category ][] = $cookie;
			$names[]                 = strtolower( $cookie['name'] );
			$added[]                 = array(
				'name'        => $cookie['name'],
				'category'    => $category,
				'description' => $cookie['comment'],
			);
		}

		if ( $added ) {
			update_option( self::REGISTRY, $registry );
			self::report( 'SCAN_SYNC', array_map( function ( $cookie ) { return '+ ' . $cookie['name']; }, $added ) );
		}

		return $added;
	}

	/* ---------------------------------------------------------------------
	 * Changes and validation
	 * ------------------------------------------------------------------ */

	/**
	 * Adds or changes cookies, removes others, then validates the list if asked.
	 *
	 * @param array[]  $cookies  { name, category, domain, duration_days, description }, matched by name.
	 * @param string[] $remove   Names of cookies to remove.
	 * @param bool     $validate Record the administrator validation of the resulting list.
	 * @return array|\WP_Error
	 */
	public static function update( $cookies, $remove, $validate ) {
		$errors = array();
		$clean  = array();
		foreach ( (array) $cookies as $i => $cookie ) {
			$name = isset( $cookie['name'] ) ? trim( (string) $cookie['name'] ) : '';
			if ( ! preg_match( '/^[!#$%&\'*+\-.^_`|~a-zA-Z0-9]+$/', $name ) ) {
				/* translators: %s: cookie name */
				$errors[] = sprintf( __( 'Nom de cookie invalide : « %s ».', 'eo-tools' ), $name );
				continue;
			}
			if ( isset( $cookie['category'] ) && ! in_array( $cookie['category'], self::CATEGORIES, true ) ) {
				/* translators: 1: cookie name, 2: categories */
				$errors[] = sprintf( __( '%1$s : catégorie attendue parmi %2$s.', 'eo-tools' ), $name, implode( ', ', self::CATEGORIES ) );
				continue;
			}
			if ( isset( $cookie['duration_days'] ) && ( (int) $cookie['duration_days'] < 0 || (int) $cookie['duration_days'] > 395 ) ) {
				/* translators: %s: cookie name */
				$errors[] = sprintf( __( '%s : durée attendue entre 0 et 395 jours (13 mois au plus selon la CNIL).', 'eo-tools' ), $name );
				continue;
			}
			$clean[ strtolower( $name ) ] = array_merge( $cookie, array( 'name' => $name ) );
		}
		if ( $errors ) {
			return new \WP_Error( 'eo_tools_invalid_cookies', __( 'Rien n\'a été modifié :', 'eo-tools' ) . "\n" . implode( "\n", $errors ) );
		}

		$registry = self::get_registry();
		$lines    = array();
		$remove   = array_map( 'strtolower', array_map( 'strval', (array) $remove ) );

		// Removals and changes of the existing cookies. Moves to another category are applied after the loop.
		$moved = array();
		foreach ( $registry as $category => $list ) {
			$kept = array();
			foreach ( (array) $list as $cookie ) {
				$key = strtolower( (string) $cookie['name'] );
				if ( in_array( $key, $remove, true ) ) {
					$lines[] = '- ' . $cookie['name'];
					continue;
				}
				if ( isset( $clean[ $key ] ) ) {
					$change = $clean[ $key ];
					unset( $clean[ $key ] );
					$cookie = self::apply( $cookie, $change );
					$target = isset( $change['category'] ) ? $change['category'] : $category;
					$lines[] = '~ ' . $cookie['name'];
					if ( $target !== $category ) {
						$moved[ $target ][] = $cookie;
						continue;
					}
				}
				$kept[] = $cookie;
			}
			$registry[ $category ] = $kept;
		}
		foreach ( $moved as $category => $list ) {
			$registry[ $category ] = array_merge( isset( $registry[ $category ] ) ? (array) $registry[ $category ] : array(), $list );
		}

		// New cookies.
		foreach ( $clean as $change ) {
			$category                = isset( $change['category'] ) ? $change['category'] : 'others';
			$registry[ $category ][] = self::apply( array( 'id' => substr( md5( uniqid( '', true ) ), 0, 7 ) ), $change );
			$lines[]                 = '+ ' . $change['name'];
		}

		if ( $lines ) {
			update_option( self::REGISTRY, $registry );
			self::report( 'ADMIN_MODIFICATION', $lines );
		}

		$result = array(
			'changes' => $lines,
			'cookies' => self::get_list(),
		);

		if ( $validate ) {
			self::validate();
		}
		$result['validated'] = self::is_validated();

		return $result;
	}

	/**
	 * Applies changed fields to a cookie of the registry.
	 *
	 * @param array $cookie Registry cookie.
	 * @param array $change Fields sent by the AI.
	 * @return array
	 */
	private static function apply( $cookie, $change ) {
		$cookie['name'] = $change['name'];
		if ( isset( $change['domain'] ) ) {
			$cookie['domain'] = sanitize_text_field( (string) $change['domain'] );
		}
		if ( isset( $change['duration_days'] ) ) {
			$cookie['date'] = (string) (int) $change['duration_days'];
		}
		if ( isset( $change['description'] ) ) {
			$cookie['comment'] = sanitize_text_field( (string) $change['description'] );
		}
		$cookie += array(
			'domain'  => '',
			'date'    => '0',
			'comment' => '',
		);

		return $cookie;
	}

	/**
	 * Records the administrator validation of the current list, like the "Validation Admin" button.
	 */
	private static function validate() {
		global $wpdb;
		$names = self::get_names();
		if ( ! $names || self::is_validated() ) {
			return;
		}

		// The scans waiting for a validation are covered by this one.
		$wpdb->update( $wpdb->prefix . 'eotools_scan', array( 'requires_validation' => 0 ), array( 'requires_validation' => 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::report( 'ADMIN_VALIDATION', $names );
		Eotools_Mcp_Settings::log( 'cookie_list', '', implode( ', ', $names ), 'eo-tools-cookies' );
	}
}
