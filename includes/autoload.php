<?php
/**
 * Autoloader
 *
 * @package EoTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function( $class ) {
		if ( 0 !== strpos( $class, 'EoTools\\' ) ) {
			return;
		}

		$path = str_replace( '\\', '/', str_replace( 'EoTools\\', '', $class ) );
		$path = str_replace( '_', '-', strtolower( $path ) );
		$parts = explode( '/', $path );
		$filename = 'class-' . array_pop( $parts ) . '.php';
		
		$filepath = EO_TOOLS_PATH . implode( '/', $parts ) . '/' . $filename;
		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}
);
