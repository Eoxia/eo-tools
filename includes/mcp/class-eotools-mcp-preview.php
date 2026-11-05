<?php
/**
 * Lets the AI check what it built: temporary preview links, render diagnostics
 * and screenshots.
 *
 * A preview link holds a random token, valid for one content and a few minutes.
 * It shows a draft or a proposal without a WordPress session, so that EO Tools
 * itself and the screenshot service can load it.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Preview {

	const QUERY_VAR = 'eo_tools_preview';
	const TTL       = 10 * MINUTE_IN_SECONDS;

	const SCREENSHOT_SERVICE = 'https://s0.wp.com/mshots/v1/';

	/**
	 * Content ID unlocked by a valid token for the current request.
	 *
	 * @var int
	 */
	private static $post_id = 0;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'read_token' ) );
	}

	/**
	 * Creates a temporary public preview link for a content.
	 *
	 * @param \WP_Post $post Content (draft, proposal or published).
	 * @return string
	 */
	public static function create_link( $post ) {
		$token = wp_generate_password( 32, false, false );
		set_transient( self::QUERY_VAR . '_' . hash( 'sha256', $token ), $post->ID, self::TTL );

		$base = 'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post );

		return add_query_arg( self::QUERY_VAR, $token, $base );
	}

	/**
	 * Unlocks the content of a valid token for the current request.
	 */
	public static function read_token() {
		$token = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return;
		}

		$post_id = (int) get_transient( self::QUERY_VAR . '_' . hash( 'sha256', $token ) );
		if ( ! $post_id ) {
			return;
		}

		self::$post_id = $post_id;

		add_filter( 'posts_results', array( __CLASS__, 'unlock_post' ), 10, 2 );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'show_admin_bar', '__return_false' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}

	/**
	 * Shows the unlocked content as if it were published, for this request only.
	 *
	 * @param \WP_Post[] $posts Posts found by the query.
	 * @param \WP_Query  $query Query.
	 * @return \WP_Post[]
	 */
	public static function unlock_post( $posts, $query ) {
		if ( ! $query->is_main_query() || 1 !== count( $posts ) || (int) $posts[0]->ID !== self::$post_id ) {
			return $posts;
		}

		$post              = clone $posts[0];
		$post->post_status = 'publish';

		return array( $post );
	}

	/* ---------------------------------------------------------------------
	 * Render diagnostics
	 * ------------------------------------------------------------------ */

	/**
	 * Loads the rendered page and looks for visible problems.
	 *
	 * @param \WP_Post $post Content.
	 * @return array|\WP_Error
	 */
	public static function check( $post ) {
		$response = wp_remote_get(
			self::create_link( $post ),
			array(
				'timeout'   => 30,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'eo_tools_render', __( 'Impossible de charger la page pour la vérifier.', 'eo-tools' ) );
		}

		$code   = wp_remote_retrieve_response_code( $response );
		$html   = wp_remote_retrieve_body( $response );
		$issues = array();

		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code. */
			$issues[] = sprintf( __( 'La page répond avec le code HTTP %d.', 'eo-tools' ), $code );
		}

		if ( preg_match_all( '#<b>(Fatal error|Parse error|Warning|Notice|Deprecated)</b>:\s*(.{0,200}?)<br#s', $html, $errors, PREG_SET_ORDER ) ) {
			foreach ( array_slice( $errors, 0, 5 ) as $error ) {
				$issues[] = sprintf( 'PHP %s : %s', $error[1], wp_strip_all_tags( $error[2] ) );
			}
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );

		// The content area of the theme, or the whole page.
		$main = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-post-content ") or contains(concat(" ", normalize-space(@class), " "), " entry-content ")]' )->item( 0 );
		if ( ! $main ) {
			$main = $dom->getElementsByTagName( 'body' )->item( 0 );
		}
		if ( ! $main ) {
			return array(
				'http_status' => $code,
				'issues'      => array_merge( $issues, array( __( 'La page ne contient pas de HTML exploitable.', 'eo-tools' ) ) ),
			);
		}

		// Outline: what a visitor reads, in order.
		$outline = array();
		$h1      = 0;
		foreach ( $xpath->query( './/h1|.//h2|.//h3|.//h4|.//h5|.//h6', $main ) as $heading ) {
			$level = (int) substr( $heading->nodeName, 1 );
			$h1   += 1 === $level ? 1 : 0;
			$text  = self::text( $heading );
			if ( '' === $text ) {
				$issues[] = sprintf( __( 'Titre %s vide.', 'eo-tools' ), strtoupper( $heading->nodeName ) );
			}
			$outline[] = str_repeat( '  ', $level - 1 ) . strtoupper( $heading->nodeName ) . ' ' . mb_substr( $text, 0, 120 );
		}
		if ( $h1 > 1 ) {
			/* translators: %d: number of H1 titles. */
			$issues[] = sprintf( __( 'La page contient %d titres H1 dans le contenu (un seul recommandé).', 'eo-tools' ), $h1 );
		}

		// Images: missing alternative text, broken files.
		$images  = $xpath->query( './/img', $main );
		$checked = array();
		foreach ( $images as $image ) {
			$src = $image->getAttribute( 'src' );
			if ( '' === $src ) {
				$issues[] = __( 'Image sans adresse (src vide).', 'eo-tools' );
				continue;
			}
			if ( ! $image->hasAttribute( 'alt' ) || ( '' === trim( $image->getAttribute( 'alt' ) ) && 'true' !== $image->getAttribute( 'aria-hidden' ) ) ) {
				/* translators: %s: image file name. */
				$issues[] = sprintf( __( 'Image sans texte alternatif : %s', 'eo-tools' ), wp_basename( wp_parse_url( $src, PHP_URL_PATH ) ) );
			}
			if ( count( $checked ) < 15 && ! isset( $checked[ $src ] ) && 0 !== strpos( $src, 'data:' ) ) {
				$checked[ $src ] = true;
				$head            = wp_safe_remote_head( $src, array( 'timeout' => 10 ) );
				if ( is_wp_error( $head ) || wp_remote_retrieve_response_code( $head ) >= 400 ) {
					/* translators: %s: image URL. */
					$issues[] = sprintf( __( 'Image introuvable : %s', 'eo-tools' ), $src );
				}
			}
		}

		// Blocks rendered without any visible content.
		$empty = array();
		foreach ( $xpath->query( './/*[contains(@class, "wp-block-")]', $main ) as $block ) {
			$classes = $block->getAttribute( 'class' );
			if ( preg_match( '/wp-block-(separator|spacer|column\b|buttons|embed__wrapper|cover__background|image)/', $classes ) ) {
				continue;
			}
			if ( '' === self::text( $block ) && 0 === $xpath->query( './/img|.//svg|.//iframe|.//video|.//input|.//canvas|.//hr', $block )->length ) {
				preg_match( '/wp-block-([a-z0-9-]+)/', $classes, $match );
				$empty[ $match[1] ] = ( isset( $empty[ $match[1] ] ) ? $empty[ $match[1] ] : 0 ) + 1;
			}
		}
		foreach ( $empty as $name => $count ) {
			/* translators: 1: block class, 2: count. */
			$issues[] = sprintf( __( 'Bloc rendu vide : wp-block-%1$s (%2$d fois).', 'eo-tools' ), $name, $count );
		}

		$empty_links = $xpath->query( './/a[not(@href) or @href="" or @href="#"]', $main )->length;
		if ( $empty_links ) {
			/* translators: %d: number of links. */
			$issues[] = sprintf( __( '%d lien(s) sans destination (href vide ou "#").', 'eo-tools' ), $empty_links );
		}

		/**
		 * Filters the problems found in the rendered page (e.g. block-specific checks).
		 *
		 * @param string[]     $issues Problems found.
		 * @param \DOMXPath    $xpath  Rendered page.
		 * @param \DOMElement  $main   Content area.
		 * @param \WP_Post     $post   Content checked.
		 */
		$issues = (array) apply_filters( 'eo_tools_mcp_render_issues', $issues, $xpath, $main, $post );

		return array(
			'http_status' => $code,
			'issues'      => array_values( array_unique( $issues ) ),
			'outline'     => $outline,
			'stats'       => array(
				'words'  => count( preg_split( '/\s+/u', self::text( $main ), -1, PREG_SPLIT_NO_EMPTY ) ),
				'images' => $images->length,
				'links'  => $xpath->query( './/a', $main )->length,
			),
		);
	}

	/**
	 * Visible text of an element, whitespace collapsed.
	 */
	private static function text( $node ) {
		return trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
	}

	/* ---------------------------------------------------------------------
	 * Screenshots
	 * ------------------------------------------------------------------ */

	/**
	 * Takes a screenshot of the content with WordPress.com mShots.
	 *
	 * mShots builds the image in the background: the first requests return a
	 * placeholder, so the service is polled for a few seconds.
	 *
	 * @param \WP_Post $post   Content.
	 * @param string   $device desktop or mobile.
	 * @return array|\WP_Error { body: binary image, mime: string }
	 */
	public static function screenshot( $post, $device = 'desktop' ) {
		$size = 'mobile' === $device
			? array( 'vpw' => 390, 'vph' => 1600, 'w' => 390, 'h' => 1600 )
			: array( 'vpw' => 1280, 'vph' => 2400, 'w' => 1000, 'h' => 1875 );

		$url = add_query_arg( $size, self::SCREENSHOT_SERVICE . rawurlencode( self::create_link( $post ) ) );

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			if ( $attempt > 0 ) {
				sleep( 3 );
			}

			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 20,
					'redirection' => 0,
				)
			);
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$mime = (string) wp_remote_retrieve_header( $response, 'content-type' );
			$body = wp_remote_retrieve_body( $response );

			if ( 200 === wp_remote_retrieve_response_code( $response ) && preg_match( '#image/(jpeg|png)#', $mime ) && strlen( $body ) > 2000 ) {
				return array(
					'body' => $body,
					'mime' => strtok( $mime, ';' ),
				);
			}
		}

		return new \WP_Error( 'eo_tools_screenshot', __( 'La capture d\'écran n\'a pas pu être générée à temps. Réessayez dans quelques secondes, ou utilisez eo-tools/check-render.', 'eo-tools' ) );
	}
}
