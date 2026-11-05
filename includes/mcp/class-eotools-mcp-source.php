<?php
/**
 * Read-only access to the code of the installed themes and plugins.
 *
 * Lets the AI find the real hooks, functions and CSS classes before writing a
 * snippet. Only wp-content/themes and wp-content/plugins can be read, and only
 * code or text files.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Source {

	/**
	 * File types the AI may read.
	 */
	const READABLE = array( 'php', 'css', 'scss', 'js', 'json', 'html', 'txt', 'md' );

	const MAX_MATCHES = 60;

	/**
	 * Lists a folder or reads a file.
	 *
	 * @param array $input Path and line range.
	 * @return array|\WP_Error
	 */
	public static function read( $input ) {
		$path = trim( isset( $input['path'] ) ? (string) $input['path'] : '', '/' );
		if ( '' === $path ) {
			$plugins = array();
			foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
				$plugins[] = 'plugins/' . dirname( $file );
			}

			return array(
				'active_theme' => 'themes/' . get_stylesheet(),
				'parent_theme' => is_child_theme() ? 'themes/' . get_template() : null,
				'plugins'      => $plugins,
			);
		}

		$real = self::resolve( $path );
		if ( is_wp_error( $real ) ) {
			return $real;
		}

		if ( is_dir( $real ) ) {
			$items = array();
			foreach ( (array) scandir( $real ) as $name ) {
				if ( '' === $name || '.' === $name[0] ) {
					continue;
				}
				$items[] = is_dir( $real . '/' . $name ) ? $name . '/' : $name;
				if ( count( $items ) >= 300 ) {
					break;
				}
			}

			return array(
				'path'  => $path,
				'items' => $items,
			);
		}

		if ( ! self::readable( $real ) ) {
			/* translators: %s: file extensions. */
			return new \WP_Error( 'eo_tools_source_type', sprintf( __( 'Seuls les fichiers de code et de texte peuvent être lus : %s.', 'eo-tools' ), implode( ', ', self::READABLE ) ) );
		}

		$lines = (array) file( $real, FILE_IGNORE_NEW_LINES );
		$start = max( 1, isset( $input['start'] ) ? (int) $input['start'] : 1 );
		$count = min( 800, max( 1, isset( $input['lines'] ) ? (int) $input['lines'] : 400 ) );
		$part  = array_slice( $lines, $start - 1, $count );
		$out   = '';
		foreach ( $part as $i => $line ) {
			$out .= ( $start + $i ) . ': ' . $line . "\n";
		}
		if ( strlen( $out ) > 40000 ) {
			$out = substr( $out, 0, 40000 ) . "\n[…]";
		}

		return array(
			'path'        => $path,
			'total_lines' => count( $lines ),
			'from'        => $start,
			'to'          => $start + count( $part ) - 1,
			'content'     => $out,
		);
	}

	/**
	 * Searches text in a theme or plugin folder (case insensitive).
	 *
	 * @param array $input Path and query.
	 * @return array|\WP_Error
	 */
	public static function search( $input ) {
		$path  = trim( isset( $input['path'] ) ? (string) $input['path'] : '', '/' );
		$query = isset( $input['query'] ) ? (string) $input['query'] : '';
		$real  = self::resolve( $path );
		if ( is_wp_error( $real ) ) {
			return $real;
		}
		if ( strlen( $query ) < 3 ) {
			return new \WP_Error( 'eo_tools_source_query', __( 'Cherchez au moins 3 caractères.', 'eo-tools' ) );
		}

		$matches = array();
		$scanned = 0;
		$root    = strlen( wp_normalize_path( WP_CONTENT_DIR ) ) + 1;
		$files   = is_dir( $real ) ? new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $real, \FilesystemIterator::SKIP_DOTS ) ) : array( new \SplFileInfo( $real ) );

		foreach ( $files as $file ) {
			$name = wp_normalize_path( $file->getPathname() );
			if ( ! self::readable( $name ) || $file->getSize() > 1048576 || preg_match( '#/(node_modules|vendor|\.git)/#', $name ) ) {
				continue;
			}
			if ( ++$scanned > 5000 ) {
				break;
			}
			foreach ( (array) file( $name, FILE_IGNORE_NEW_LINES ) as $n => $line ) {
				if ( false !== stripos( $line, $query ) ) {
					$matches[] = substr( $name, $root ) . ':' . ( $n + 1 ) . ': ' . trim( substr( $line, 0, 200 ) );
					if ( count( $matches ) >= self::MAX_MATCHES ) {
						break 2;
					}
				}
			}
		}

		return array(
			'query'     => $query,
			'matches'   => $matches,
			'truncated' => count( $matches ) >= self::MAX_MATCHES,
		);
	}

	/**
	 * Resolves a path inside wp-content/themes or wp-content/plugins, refusing anything else.
	 *
	 * @param string $path Path relative to wp-content.
	 * @return string|\WP_Error Real path.
	 */
	private static function resolve( $path ) {
		$real = realpath( WP_CONTENT_DIR . '/' . $path );
		if ( ! $real ) {
			return new \WP_Error( 'eo_tools_source_missing', __( 'Ce chemin n\'existe pas. Appelez eo-tools/read-source sans "path" pour voir le thème et les extensions.', 'eo-tools' ) );
		}

		$real = wp_normalize_path( $real );
		foreach ( array( get_theme_root(), WP_PLUGIN_DIR ) as $root ) {
			$root = wp_normalize_path( (string) realpath( $root ) );
			if ( $root && ( $real === $root || 0 === strpos( $real, $root . '/' ) ) ) {
				return $real;
			}
		}

		return new \WP_Error( 'eo_tools_source_forbidden', __( 'Seuls les dossiers des thèmes et des extensions peuvent être lus.', 'eo-tools' ) );
	}

	/**
	 * @param string $file Path.
	 * @return bool
	 */
	private static function readable( $file ) {
		return in_array( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ), self::READABLE, true ) && is_readable( $file );
	}
}
