<?php
/**
 * 301 redirections of EO Tools' 404 module, for the AI.
 *
 * Redirections are always stored in EO Tools (table eotools_redirections),
 * whatever the SEO plugin: the 404 log and the SEO audit already rely on it.
 * The source is the path as requested by the browser (REQUEST_URI, so with
 * the folder of the site when WordPress lives in one); the target is
 * relative to the home page, like in the EO Tools > 404-Erreur screen, or a
 * full address for another site.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Redirections {

	/**
	 * @return string Table name.
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'eotools_redirections';
	}

	/**
	 * @return bool Whether the table exists (created by the 404 module).
	 */
	public static function is_available() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return self::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::table() ) ) );
	}

	/**
	 * @return string Path of the home page, without the trailing slash ("" at the root of the domain).
	 */
	private static function home_path() {
		return untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	}

	/**
	 * Percent-encodes the non-ASCII characters, as browsers send them.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function encode( $path ) {
		return preg_replace_callback(
			'/[^\x00-\x7F]/',
			function ( $char ) {
				return '%' . strtoupper( bin2hex( $char[0] ) );
			},
			rawurldecode( $path )
		);
	}

	/**
	 * Source of a redirection, as the 404 module compares it.
	 *
	 * @param string $url Full address of the site, or path ("/ancienne-page/", relative to the home page).
	 * @return string|\WP_Error Path with the query string.
	 */
	public static function normalize_source( $url ) {
		$url   = trim( (string) $url );
		$parts = wp_parse_url( $url );

		if ( '' === $url || false === $parts ) {
			return new \WP_Error( 'eo_tools_redirection_source', __( 'Adresse à rediriger vide ou invalide.', 'eo-tools' ) );
		}

		if ( ! empty( $parts['host'] ) ) {
			if ( strtolower( $parts['host'] ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
				/* translators: %s: address. */
				return new \WP_Error( 'eo_tools_redirection_source', sprintf( __( '« %s » n\'est pas une adresse de ce site.', 'eo-tools' ), $url ) );
			}
			$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		} else {
			$path = '/' . ltrim( isset( $parts['path'] ) ? $parts['path'] : '', '/' );
			$home = self::home_path();
			if ( '' !== $home && 0 !== strpos( $path . '/', $home . '/' ) ) {
				$path = $home . $path;
			}
		}

		$path = self::encode( $path );
		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}

		return esc_url_raw( $path );
	}

	/**
	 * Target of a redirection: path relative to the home page, or full address of another site.
	 *
	 * @param string $url Address or path.
	 * @return string|\WP_Error
	 */
	public static function normalize_target( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return new \WP_Error( 'eo_tools_redirection_target', __( 'Adresse de destination vide.', 'eo-tools' ) );
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts ) {
			/* translators: %s: address. */
			return new \WP_Error( 'eo_tools_redirection_target', sprintf( __( 'Adresse de destination invalide : %s', 'eo-tools' ), $url ) );
		}

		if ( ! empty( $parts['host'] ) ) {
			if ( ! wp_http_validate_url( $url ) ) {
				/* translators: %s: address. */
				return new \WP_Error( 'eo_tools_redirection_target', sprintf( __( 'Adresse de destination invalide : %s', 'eo-tools' ), $url ) );
			}
			$home = home_url();
			if ( 0 !== stripos( $url, $home ) ) {
				// Another site.
				return esc_url_raw( $url );
			}
			$url = substr( $url, strlen( $home ) );
		} else {
			// A path that already contains the folder of the site.
			$home = self::home_path();
			if ( '' !== $home && 0 === strpos( '/' . ltrim( $url, '/' ) . '/', $home . '/' ) ) {
				$url = substr( '/' . ltrim( $url, '/' ), strlen( $home ) );
			}
		}

		return esc_url_raw( '/' . ltrim( self::encode( $url ), '/' ) );
	}

	/**
	 * Full address of a target.
	 *
	 * @param string $target Result of normalize_target().
	 * @return string
	 */
	public static function target_url( $target ) {
		return 0 === strpos( $target, 'http' ) ? $target : home_url( $target );
	}

	/**
	 * Full address of a source.
	 *
	 * @param string $source Result of normalize_source().
	 * @return string
	 */
	public static function source_url( $source ) {
		$host = wp_parse_url( home_url() );

		return $host['scheme'] . '://' . $host['host'] . ( isset( $host['port'] ) ? ':' . $host['port'] : '' ) . $source;
	}

	/**
	 * Redirection stored for a source.
	 *
	 * @param string $source Normalized source.
	 * @return object|null Row with redirect_to and status (redirected, ignored).
	 */
	public static function get( $source ) {
		global $wpdb;

		if ( ! self::is_available() ) {
			return null;
		}

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT url, redirect_to, status FROM $table WHERE url = %s", $source ) );
	}

	/**
	 * Checks a redirection before it is proposed.
	 *
	 * @param string $from Source given by the AI.
	 * @param string $to   Target given by the AI.
	 * @return array|\WP_Error Keys from, to, replaces (current target or null) and warnings.
	 */
	public static function check( $from, $to ) {
		$source = self::normalize_source( $from );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$target = self::normalize_target( $to );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$source_url = self::source_url( $source );
		if ( untrailingslashit( strtok( $source_url, '?' ) ) === untrailingslashit( home_url() ) ) {
			return new \WP_Error( 'eo_tools_redirection_home', __( 'La page d\'accueil ne peut pas être redirigée.', 'eo-tools' ) );
		}
		if ( untrailingslashit( $source_url ) === untrailingslashit( self::target_url( $target ) ) ) {
			return new \WP_Error( 'eo_tools_redirection_loop', __( 'L\'adresse de destination est l\'adresse redirigée elle-même.', 'eo-tools' ) );
		}

		// The 404 module only redirects addresses that do not answer.
		$post_id = url_to_postid( $source_url );
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			/* translators: 1: address, 2: content title. */
			return new \WP_Error( 'eo_tools_redirection_live', sprintf( __( '« %1$s » affiche le contenu publié « %2$s » : une redirection ne s\'applique qu\'aux adresses en erreur 404. Changez d\'abord l\'adresse du contenu (son slug, avec eo-tools/propose-update : l\'ancienne adresse sera alors redirigée automatiquement), ou dépubliez-le.', 'eo-tools' ), $source_url, get_the_title( $post_id ) ) );
		}

		$warnings = array();
		$current  = self::get( $source );

		if ( 0 !== strpos( $target, 'http' ) ) {
			$target_source = self::normalize_source( $target );
			$chained       = is_wp_error( $target_source ) ? null : self::get( $target_source );
			if ( $chained && 'redirected' === $chained->status ) {
				/* translators: 1: address, 2: address. */
				return new \WP_Error( 'eo_tools_redirection_chain', sprintf( __( 'La destination « %1$s » est elle-même redirigée vers « %2$s » : redirigez directement vers l\'adresse finale.', 'eo-tools' ), $target, $chained->redirect_to ) );
			}

			$target_path = strtok( $target, '?' );
			$target_id   = url_to_postid( home_url( $target_path ) );
			if ( '/' !== $target_path && ( ! $target_id || 'publish' !== get_post_status( $target_id ) ) ) {
				/* translators: %s: address. */
				$warnings[] = sprintf( __( 'La destination « %s » ne correspond à aucun contenu publié (archive, catégorie ou erreur ?) : vérifiez qu\'elle s\'affiche bien.', 'eo-tools' ), self::target_url( $target ) );
			}
		} else {
			$warnings[] = __( 'Redirection vers un autre site.', 'eo-tools' );
		}

		return array(
			'from'     => $source,
			'to'       => $target,
			'replaces' => $current && 'redirected' === $current->status ? $current->redirect_to : null,
			'warnings' => $warnings,
		);
	}

	/**
	 * Saves a redirection (human action).
	 *
	 * @param string $source Normalized source.
	 * @param string $target Normalized target.
	 * @return true|\WP_Error
	 */
	public static function save( $source, $target ) {
		global $wpdb;

		if ( ! self::is_available() ) {
			return new \WP_Error( 'eo_tools_redirection_table', __( 'Le module 404 d\'EO Tools n\'est pas installé (table des redirections absente).', 'eo-tools' ) );
		}

		$table = self::table();
		// Same query as the EO Tools > 404-Erreur screen.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$done = $wpdb->query( $wpdb->prepare( "INSERT INTO $table (url, redirect_to, status, created_at) VALUES (%s, %s, 'redirected', %s) ON DUPLICATE KEY UPDATE redirect_to = VALUES(redirect_to), status = VALUES(status)", $source, $target, current_time( 'mysql' ) ) );
		if ( false === $done ) {
			return new \WP_Error( 'eo_tools_redirection_save', __( 'La redirection n\'a pas pu être enregistrée.', 'eo-tools' ) );
		}

		// Older redirections to this address now go straight to the new target (no chain).
		$as_target = self::normalize_target( self::source_url( $source ) );
		if ( ! is_wp_error( $as_target ) && $as_target !== $target ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "UPDATE $table SET redirect_to = %s WHERE redirect_to = %s AND status = 'redirected' AND url <> %s", $target, $as_target, $source ) );
		}

		return true;
	}

	/**
	 * Addresses of a content and of its sub-pages, before its address changes.
	 *
	 * @param \WP_Post $post Published content.
	 * @return string[] Permalinks keyed by post ID.
	 */
	public static function get_links( $post ) {
		$links = array( $post->ID => get_permalink( $post ) );

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$children = get_pages(
				array(
					'child_of'    => $post->ID,
					'post_type'   => $post->post_type,
					'post_status' => 'publish',
				)
			);
			foreach ( (array) $children as $child ) {
				$links[ $child->ID ] = get_permalink( $child );
			}
		}

		return $links;
	}

	/**
	 * Redirects the old addresses of contents whose address changed (human action: a proposal applied).
	 *
	 * @param string[] $old_links Result of get_links() before the change.
	 * @return int Number of redirections saved.
	 */
	public static function redirect_moved( $old_links ) {
		if ( ! self::is_available() ) {
			return 0;
		}

		$saved = 0;
		foreach ( $old_links as $post_id => $old_link ) {
			clean_post_cache( $post_id );
			$new_link = get_permalink( $post_id );
			if ( ! $new_link || untrailingslashit( $new_link ) === untrailingslashit( $old_link ) ) {
				continue;
			}

			$source = self::normalize_source( $old_link );
			$target = self::normalize_target( $new_link );
			if ( ! is_wp_error( $source ) && ! is_wp_error( $target ) && true === self::save( $source, $target ) ) {
				++$saved;
			}
		}

		return $saved;
	}
}
