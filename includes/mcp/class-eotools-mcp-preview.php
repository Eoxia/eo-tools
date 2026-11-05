<?php
/**
 * Lets the AI check what it built: temporary preview links and render diagnostics.
 *
 * A preview link holds a random token, valid for one content and a few minutes.
 * It shows a draft or a proposal without a WordPress session, so that EO Tools
 * can load the page as a visitor sees it.
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
		$base = 'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post );

		return add_query_arg( self::QUERY_VAR, self::create_token( $post->ID ), $base );
	}

	/**
	 * Creates a temporary public preview link for a template proposal.
	 *
	 * @param \WP_Post $proposal Template proposal.
	 * @return string
	 */
	public static function create_template_link( $proposal ) {
		$base = get_post_meta( $proposal->ID, Eotools_Mcp_Templates::META_PREVIEW_URL, true );

		return add_query_arg( self::QUERY_VAR, self::create_token( 'template:' . $proposal->ID ), $base ? $base : home_url( '/' ) );
	}

	/**
	 * Creates a temporary public preview link for a global styles proposal.
	 *
	 * @param \WP_Post $proposal Styles proposal.
	 * @return string
	 */
	public static function create_styles_link( $proposal ) {
		$base = get_post_meta( $proposal->ID, Eotools_Mcp_Styles::META_PREVIEW_URL, true );

		return add_query_arg( self::QUERY_VAR, self::create_token( 'styles:' . $proposal->ID ), $base ? $base : home_url( '/' ) );
	}

	/**
	 * @param int|string $target Content ID, or "template:<proposal ID>".
	 * @return string Token.
	 */
	private static function create_token( $target ) {
		$token = wp_generate_password( 32, false, false );
		set_transient( self::QUERY_VAR . '_' . hash( 'sha256', $token ), (string) $target, self::TTL );

		return $token;
	}

	/**
	 * Unlocks the content of a valid token for the current request.
	 */
	public static function read_token() {
		$token = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return;
		}

		$target = (string) get_transient( self::QUERY_VAR . '_' . hash( 'sha256', $token ) );
		if ( '' === $target ) {
			return;
		}

		if ( 0 === strpos( $target, 'template:' ) ) {
			if ( ! Eotools_Mcp_Templates::start_preview( (int) substr( $target, 9 ) ) ) {
				return;
			}
		} elseif ( 0 === strpos( $target, 'styles:' ) ) {
			if ( ! Eotools_Mcp_Styles::start_preview( (int) substr( $target, 7 ) ) ) {
				return;
			}
		} else {
			self::$post_id = (int) $target;
			add_filter( 'posts_results', array( __CLASS__, 'unlock_post' ), 10, 2 );
		}

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
		return self::check_url( self::create_link( $post ), $post, false );
	}

	/**
	 * Loads a page of the site with a proposed template and looks for visible problems.
	 *
	 * The whole page is checked, since a template (or a header, a footer) is not limited to the content area.
	 * Problems already present on the current page are listed apart, so that the AI
	 * only fixes what its change caused.
	 *
	 * @param \WP_Post $proposal Template proposal.
	 * @return array|\WP_Error
	 */
	public static function check_template( $proposal ) {
		$base = get_post_meta( $proposal->ID, Eotools_Mcp_Templates::META_PREVIEW_URL, true );

		return self::check_against_live( self::create_template_link( $proposal ), $base ? $base : home_url( '/' ), $proposal );
	}

	/**
	 * Loads a page of the site with proposed global styles and looks for visible problems.
	 *
	 * @param \WP_Post $proposal Styles proposal.
	 * @return array|\WP_Error
	 */
	public static function check_styles( $proposal ) {
		$base   = get_post_meta( $proposal->ID, Eotools_Mcp_Styles::META_PREVIEW_URL, true );
		$result = self::check_against_live( self::create_styles_link( $proposal ), $base ? $base : home_url( '/' ), $proposal );
		if ( ! is_wp_error( $result ) ) {
			$result['contrast'] = Eotools_Mcp_Styles::contrast_report( $proposal );
			foreach ( $result['contrast'] as $pair ) {
				if ( ! $pair['ok'] ) {
					/* translators: 1: color pair, 2: contrast ratio. */
					$result['issues'][] = sprintf( __( 'Contraste insuffisant : %1$s (%2$s:1, il faut au moins 4.5:1).', 'eo-tools' ), $pair['pair'], $pair['ratio'] );
				}
			}
			$result['note_styles'] = __( 'Cette vérification ne juge pas l\'esthétique : vérifiez aussi la lisibilité des tailles de texte et l\'harmonie des couleurs.', 'eo-tools' );
		}

		return $result;
	}

	/**
	 * Checks a whole page with a proposal and lists apart the problems the live page already has.
	 *
	 * @param string   $preview_url Preview link (with token).
	 * @param string   $live_url    Same page without the proposal.
	 * @param \WP_Post $proposal    Proposal.
	 * @return array|\WP_Error
	 */
	private static function check_against_live( $preview_url, $live_url, $proposal ) {
		$result = self::check_url( $preview_url, $proposal, true );
		if ( is_wp_error( $result ) || empty( $result['issues'] ) ) {
			return $result;
		}

		$current = self::check_url( $live_url, $proposal, true );
		if ( is_wp_error( $current ) ) {
			return $result;
		}

		$existing = array_values( array_intersect( $result['issues'], $current['issues'] ) );
		if ( $existing ) {
			$result['issues']          = array_values( array_diff( $result['issues'], $existing ) );
			$result['existing_issues'] = $existing;
			$result['note']            = __( '"existing_issues" sont déjà présents sur le site actuel : ils ne viennent pas de votre modification. Signalez-les à l\'utilisateur sans les corriger, sauf s\'il le demande.', 'eo-tools' );
		}

		return $result;
	}

	/**
	 * @param string   $url        Preview link.
	 * @param \WP_Post $post       Content or template proposal checked.
	 * @param bool     $whole_page Check the whole page instead of the content area.
	 * @return array|\WP_Error
	 */
	private static function check_url( $url, $post, $whole_page ) {
		$response = wp_remote_get(
			$url,
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
		$main = $whole_page ? null : $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-post-content ") or contains(concat(" ", normalize-space(@class), " "), " entry-content ")]' )->item( 0 );
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
}
