<?php
namespace EoTools\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Cookie_Interceptor {
	
	private static $services = array();

	public static function init() {
		$settings = get_option( 'eo_tools_cookies_settings', array( 'active' => false ) );
		
		if ( empty( $settings['active'] ) || is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
			return;
		}

		add_action( 'template_redirect', array( __CLASS__, 'start_buffering' ), 0 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_enqueue_scripts' ), 10, 3 );
	}

	public static function start_buffering() {
		ob_start( array( __CLASS__, 'intercept_html' ) );
	}

	public static function intercept_html( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}

		// Simple regex to find scripts and iframes.
		// Note: robust HTML parsing is complex, regex is faster but prone to edge cases.
		// We'll target <script ...>...</script> and <iframe ...></iframe>
		// And replace them based on rules defined in JS/JSON but we need them in PHP.
		// For MVP, we can rely heavily on the script_loader_tag, and for inline/hardcoded scripts, we block known patterns.
		
		// Wait, instead of regexing the whole HTML, we only need to block known domains.
		$blocked_domains = array(
			'google-analytics.com' => 'analytics',
			'googletagmanager.com' => 'analytics',
			'facebook.net'         => 'marketing',
			'facebook.com'         => 'marketing',
			'doubleclick.net'      => 'marketing',
			'youtube.com/embed'    => 'social',
			'twitter.com'          => 'social',
			// Brevo example
			'conversations-widget.brevo.com' => 'functional'
		);

		// If user has fully accepted everything, no need to intercept.
		// This should be optimized by reading the cookie early.
		$consent_cookie = isset( $_COOKIE['eotools_consent'] ) ? json_decode( stripslashes( $_COOKIE['eotools_consent'] ), true ) : null;

		foreach ( $blocked_domains as $domain => $category ) {
			// If this category is already accepted, skip blocking it.
			if ( $consent_cookie && ! empty( $consent_cookie[$category] ) && $consent_cookie[$category] === true ) {
				continue;
			}

			// Block Scripts
			// Example: <script src="...domain..."></script>
			// Or inline script containing domain.
			$pattern_script = '/<script([^>]*)>(.*?)<\/script>/is';
			$html = preg_replace_callback( $pattern_script, function( $matches ) use ( $domain, $category ) {
				$attributes = $matches[1];
				$content = $matches[2];

				if ( strpos( $attributes, $domain ) !== false || strpos( $content, $domain ) !== false ) {
					// It's a match!
					if ( strpos( $attributes, 'data-cookiecategory' ) === false ) {
						// Change type to text/plain
						$attributes = preg_replace( '/type=[\'"][^\'"]*[\'"]/', '', $attributes );
						// Change src to data-src
						$attributes = preg_replace( '/src=([\'"])/', 'data-src=$1', $attributes );
						
						return '<script type="text/plain" data-cookiecategory="' . esc_attr( $category ) . '"' . $attributes . '>' . $content . '</script>';
					}
				}
				return $matches[0];
			}, $html );

			// Block Iframes
			$pattern_iframe = '/<iframe([^>]*)>(.*?)<\/iframe>/is';
			$html = preg_replace_callback( $pattern_iframe, function( $matches ) use ( $domain, $category ) {
				$attributes = $matches[1];
				$content = $matches[2];

				if ( strpos( $attributes, $domain ) !== false ) {
					if ( strpos( $attributes, 'data-cookiecategory' ) === false ) {
						// Change src to data-src
						$attributes = preg_replace( '/src=([\'"])/', 'data-src=$1', $attributes );
						
						// Add placeholder logic via JS or a wrapper
						return '<div class="eo-tools-cookie-placeholder" data-cookiecategory="' . esc_attr( $category ) . '"></div><iframe data-cookiecategory="' . esc_attr( $category ) . '"' . $attributes . '>' . $content . '</iframe>';
					}
				}
				return $matches[0];
			}, $html );
		}

		return $html;
	}

	public static function filter_enqueue_scripts( $tag, $handle, $src ) {
		$blocked_domains = array(
			'google-analytics.com' => 'analytics',
			'googletagmanager.com' => 'analytics',
			'facebook.net'         => 'marketing',
			'youtube.com/embed'    => 'social',
			'conversations-widget.brevo.com' => 'functional'
		);

		$consent_cookie = isset( $_COOKIE['eotools_consent'] ) ? json_decode( stripslashes( $_COOKIE['eotools_consent'] ), true ) : null;

		foreach ( $blocked_domains as $domain => $category ) {
			if ( $consent_cookie && ! empty( $consent_cookie[$category] ) && $consent_cookie[$category] === true ) {
				continue; // Allowed
			}

			if ( strpos( $src, $domain ) !== false ) {
				// We need to modify this script tag
				$tag = str_replace( ' src=', ' data-src=', $tag );
				if ( strpos( $tag, 'type=' ) !== false ) {
					$tag = preg_replace( '/type=[\'"][^\'"]*[\'"]/', 'type="text/plain"', $tag );
				} else {
					$tag = str_replace( '<script ', '<script type="text/plain" ', $tag );
				}
				$tag = str_replace( '<script ', '<script data-cookiecategory="' . esc_attr( $category ) . '" ', $tag );
				break;
			}
		}

		return $tag;
	}
}
