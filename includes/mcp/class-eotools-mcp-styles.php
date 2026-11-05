<?php
/**
 * Global styles (Site editor > Styles): reading, change proposals and their preview.
 *
 * Global styles apply to the whole site, so the AI never changes them
 * directly. It stores the new user styles in a proposal (private post type),
 * previewed on the site with the styles swapped for that request only, and a
 * human applies or rejects it. Applying goes through the REST API of the site
 * editor: WordPress keeps a revision, visible in the styles history.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Styles {

	const POST_TYPE  = 'eo_tools_style_prop';
	const CAPABILITY = 'edit_theme_options';
	const QUERY_VAR  = 'eo_tools_styles_proposal';

	const META_BASE        = '_eo_tools_styles_base';
	const META_PREVIEW_URL = '_eo_tools_styles_preview_url';
	const META_SUMMARY     = '_eo_tools_styles_summary';

	/**
	 * Proposal previewed during the current request.
	 *
	 * @var \WP_Post|null
	 */
	private static $preview = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'read_preview_request' ), 20 );
		add_action( 'wp_body_open', array( __CLASS__, 'render_banner' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_banner' ) );
		add_action( 'admin_post_eo_tools_apply_styles_proposal', array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_post_eo_tools_reject_styles_proposal', array( __CLASS__, 'handle_reject' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 92 );
		add_action( Eotools_Mcp_Proposals::PURGE_CRON, array( __CLASS__, 'purge' ) );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'Propositions de styles IA', 'eo-tools' ),
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'can_export'   => false,
				'rewrite'      => false,
				'query_var'    => false,
				'supports'     => array( 'title', 'editor', 'author' ),
			)
		);
	}

	/**
	 * Whether the active theme has global styles (theme.json).
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return wp_theme_has_theme_json();
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * Global styles post of the active theme, created when missing.
	 *
	 * @return array Post fields (ID, post_content…).
	 */
	private static function get_user_post() {
		return \WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
	}

	/**
	 * Current user styles (what the Styles panel saved), as an array.
	 *
	 * @return array With "settings" and "styles".
	 */
	public static function get_user_config() {
		$post = self::get_user_post();

		return self::normalize( isset( $post['post_content'] ) ? json_decode( $post['post_content'], true ) : array() );
	}

	/**
	 * Makes sure "settings" and "styles" are objects (WordPress sometimes stores empty lists).
	 *
	 * @param mixed $config Decoded user config.
	 * @return array
	 */
	private static function normalize( $config ) {
		$config = is_array( $config ) ? $config : array();

		foreach ( array( 'settings', 'styles' ) as $key ) {
			if ( empty( $config[ $key ] ) || ! is_array( $config[ $key ] ) ) {
				$config[ $key ] = array();
			}
		}

		$config['version']                     = \WP_Theme_JSON::LATEST_SCHEMA;
		$config['isGlobalStylesUserThemeJSON'] = true;

		return $config;
	}

	/**
	 * Summary of the styles for the AI.
	 *
	 * @param bool $include_blocks Include the styles of every block.
	 * @return array
	 */
	public static function describe( $include_blocks = false ) {
		$user     = self::get_user_config();
		$settings = wp_get_global_settings();
		$styles   = wp_get_global_styles();

		$by_origin = function ( $list, $fields ) {
			$out = array();
			foreach ( (array) $list as $origin => $items ) {
				foreach ( (array) $items as $item ) {
					$entry = array( 'origin' => $origin );
					foreach ( $fields as $field ) {
						if ( isset( $item[ $field ] ) ) {
							$entry[ $field ] = $item[ $field ];
						}
					}
					$out[] = $entry;
				}
			}
			return $out;
		};

		$data = array(
			'theme'            => get_stylesheet(),
			'palette'          => $by_origin( isset( $settings['color']['palette'] ) ? $settings['color']['palette'] : array(), array( 'slug', 'name', 'color' ) ),
			'gradients'        => $by_origin( isset( $settings['color']['gradients'] ) ? array_intersect_key( $settings['color']['gradients'], array_flip( array( 'theme', 'custom' ) ) ) : array(), array( 'slug', 'name', 'gradient' ) ),
			'font_families'    => $by_origin( isset( $settings['typography']['fontFamilies'] ) ? $settings['typography']['fontFamilies'] : array(), array( 'slug', 'name', 'fontFamily' ) ),
			'font_sizes'       => $by_origin( isset( $settings['typography']['fontSizes'] ) ? array_intersect_key( $settings['typography']['fontSizes'], array_flip( array( 'theme', 'custom' ) ) ) : array(), array( 'slug', 'name', 'size', 'fluid' ) ),
			'spacing_sizes'    => $by_origin( isset( $settings['spacing']['spacingSizes'] ) ? array_intersect_key( $settings['spacing']['spacingSizes'], array_flip( array( 'theme', 'custom' ) ) ) : array(), array( 'slug', 'name', 'size' ) ),
			'layout'           => isset( $settings['layout'] ) ? $settings['layout'] : array(),
			'styles'           => array_intersect_key( $styles, array_flip( array( 'color', 'typography', 'spacing', 'border', 'dimensions', 'shadow', 'elements' ) ) ),
			'blocks_styled'    => isset( $styles['blocks'] ) ? array_keys( array_filter( $styles['blocks'] ) ) : array(),
			'user_changes'     => array_intersect_key( $user, array_flip( array( 'settings', 'styles' ) ) ),
			'variations'       => array(),
			'installed_fonts'  => Eotools_Mcp_Fonts::get_installed_summary(),
		);

		if ( $include_blocks && isset( $styles['blocks'] ) ) {
			$data['block_styles'] = array_filter( $styles['blocks'] );
		}

		foreach ( \WP_Theme_JSON_Resolver::get_style_variations() as $variation ) {
			$colors = array();
			if ( isset( $variation['settings']['color']['palette']['theme'] ) ) {
				$colors = wp_list_pluck( $variation['settings']['color']['palette']['theme'], 'color' );
			} elseif ( isset( $variation['settings']['color']['palette'] ) && is_array( $variation['settings']['color']['palette'] ) ) {
				$colors = wp_list_pluck( $variation['settings']['color']['palette'], 'color' );
			}
			$data['variations'][] = array(
				'title'  => $variation['title'],
				'colors' => array_values( array_slice( array_filter( $colors ), 0, 8 ) ),
			);
		}

		$proposal = self::get_pending();
		if ( $proposal ) {
			$data['pending_proposal'] = array(
				'proposal_id'  => $proposal->ID,
				'summary'      => get_post_meta( $proposal->ID, self::META_SUMMARY, true ),
				'preview_link' => self::preview_url( $proposal ),
			);
		}

		return $data;
	}

	/* ---------------------------------------------------------------------
	 * Proposals
	 * ------------------------------------------------------------------ */

	/**
	 * The pending proposal (one at a time, as the styles are one set).
	 *
	 * @return \WP_Post|null
	 */
	public static function get_pending() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * @param int|\WP_Post $post Post.
	 * @return \WP_Post|null
	 */
	public static function get_proposal( $post ) {
		$post = get_post( $post );

		return $post && self::POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * Proposed user config.
	 *
	 * @param \WP_Post $proposal Proposal.
	 * @return array
	 */
	public static function get_proposed_config( $proposal ) {
		return self::normalize( json_decode( $proposal->post_content, true ) );
	}

	/**
	 * Fingerprint of the current user styles, to detect changes made since a proposal.
	 *
	 * @return string
	 */
	private static function fingerprint() {
		$post = self::get_user_post();

		return md5( isset( $post['post_content'] ) ? (string) $post['post_content'] : '' );
	}

	/**
	 * Builds and stores a proposal from the AI input.
	 *
	 * @param array $input {
	 *     @type array  $changes          Partial theme.json ("settings", "styles") merged into the styles.
	 *     @type string $variation        Title of a theme style variation to start from.
	 *     @type string[] $add_font_families Slugs of installed fonts to make available.
	 *     @type bool   $restart          Start again from the current styles.
	 *     @type string $summary          What the change does, for the user.
	 *     @type string $preview_url      Page used to preview.
	 * }
	 * @return array|\WP_Error
	 */
	public static function propose( $input ) {
		if ( ! self::is_supported() ) {
			return new \WP_Error( 'eo_tools_styles_unsupported', __( 'Le thème actif n\'utilise pas de styles globaux (theme.json) : utilisez plutôt un snippet CSS.', 'eo-tools' ) );
		}

		$pending  = self::get_pending();
		$warnings = array();
		$config   = ( $pending && empty( $input['restart'] ) ) ? self::get_proposed_config( $pending ) : self::get_user_config();

		if ( ! empty( $input['variation'] ) ) {
			$variation = null;
			foreach ( \WP_Theme_JSON_Resolver::get_style_variations() as $item ) {
				if ( 0 === strcasecmp( $item['title'], $input['variation'] ) ) {
					$variation = $item;
				}
			}
			if ( ! $variation ) {
				return new \WP_Error( 'eo_tools_styles_variation', __( 'Variation de style introuvable : utilisez un titre renvoyé par eo-tools/get-styles.', 'eo-tools' ) );
			}
			// Like the Styles panel: the variation replaces the user styles, the custom fonts are kept.
			$fonts  = isset( $config['settings']['typography']['fontFamilies']['custom'] ) ? $config['settings']['typography']['fontFamilies']['custom'] : null;
			$config = self::normalize(
				array(
					'settings' => isset( $variation['settings'] ) ? $variation['settings'] : array(),
					'styles'   => isset( $variation['styles'] ) ? $variation['styles'] : array(),
				)
			);
			if ( $fonts ) {
				$config['settings']['typography']['fontFamilies']['custom'] = $fonts;
			}
		}

		if ( ! empty( $input['changes'] ) && is_array( $input['changes'] ) ) {
			$changes = array_intersect_key( $input['changes'], array_flip( array( 'settings', 'styles' ) ) );
			if ( count( $changes ) !== count( $input['changes'] ) ) {
				$warnings[] = __( 'Seules les clés "settings" et "styles" de "changes" sont prises en compte.', 'eo-tools' );
			}
			$config = self::merge( $config, $changes );
		}

		if ( ! empty( $input['add_font_families'] ) ) {
			$added = Eotools_Mcp_Fonts::add_to_config( $config, (array) $input['add_font_families'] );
			if ( is_wp_error( $added ) ) {
				return $added;
			}
			$config = $added;
		}

		if ( isset( $config['styles']['css'] ) && ! current_user_can( 'edit_css' ) ) {
			unset( $config['styles']['css'] );
			$warnings[] = __( 'Le CSS personnalisé a été retiré : droits insuffisants.', 'eo-tools' );
		}

		// Same cleaning as WordPress: unknown keys and unsafe values are dropped.
		$clean   = self::normalize( ( new \WP_Theme_JSON( $config, 'custom' ) )->get_raw_data() );
		$dropped = array_diff( self::paths( $config ), self::paths( $clean ) );
		$dropped = array_filter(
			$dropped,
			function ( $path ) {
				return ! preg_match( '/^(version|isGlobalStylesUserThemeJSON)/', $path );
			}
		);
		if ( $dropped ) {
			/* translators: %s: list of theme.json paths. */
			$warnings[] = sprintf( __( 'Réglages ignorés car invalides pour theme.json : %s', 'eo-tools' ), implode( ', ', array_slice( array_values( $dropped ), 0, 15 ) ) );
		}

		$preview_url = '';
		if ( ! empty( $input['preview_url'] ) ) {
			$preview_url = esc_url_raw( $input['preview_url'] );
			if ( wp_parse_url( $preview_url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
				return new \WP_Error( 'eo_tools_preview_url', __( '"preview_url" doit être une page de ce site.', 'eo-tools' ) );
			}
		}

		$data = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'private',
			'post_title'   => __( 'Styles du site', 'eo-tools' ),
			'post_content' => wp_json_encode( $clean ),
			'post_author'  => get_current_user_id(),
		);

		if ( $pending && empty( $input['restart'] ) ) {
			$data['ID'] = $pending->ID;
			$id         = wp_update_post( wp_slash( $data ), true );
		} else {
			if ( $pending ) {
				wp_delete_post( $pending->ID, true );
			}
			$id = wp_insert_post( wp_slash( $data ), true );
			if ( ! is_wp_error( $id ) ) {
				update_post_meta( $id, self::META_BASE, self::fingerprint() );
			}
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( '' !== $preview_url || ! get_post_meta( $id, self::META_PREVIEW_URL, true ) ) {
			update_post_meta( $id, self::META_PREVIEW_URL, '' !== $preview_url ? $preview_url : home_url( '/' ) );
		}
		if ( ! empty( $input['summary'] ) ) {
			update_post_meta( $id, self::META_SUMMARY, sanitize_text_field( $input['summary'] ) );
		}

		return array(
			'proposal'  => get_post( $id ),
			'warnings'  => $warnings,
			'user_data' => array_intersect_key( $clean, array_flip( array( 'settings', 'styles' ) ) ),
		);
	}

	/**
	 * Deep merge: objects are merged, lists replaced, null removes a key (back to the theme value).
	 *
	 * @param array $base    Base.
	 * @param array $changes Changes.
	 * @return array
	 */
	private static function merge( $base, $changes ) {
		foreach ( $changes as $key => $value ) {
			if ( null === $value ) {
				unset( $base[ $key ] );
			} elseif ( is_array( $value ) && ! wp_is_numeric_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && ! wp_is_numeric_array( $base[ $key ] ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}

	/**
	 * Leaf paths of a config, to report what WordPress dropped.
	 *
	 * @param array  $data   Data.
	 * @param string $prefix Path prefix.
	 * @return string[]
	 */
	private static function paths( $data, $prefix = '' ) {
		$paths = array();
		foreach ( $data as $key => $value ) {
			$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			if ( is_array( $value ) && $value && ! wp_is_numeric_array( $value ) ) {
				$paths = array_merge( $paths, self::paths( $value, $path ) );
			} else {
				$paths[] = $path;
			}
		}

		return $paths;
	}

	/**
	 * Contrast of the main text / background pairs with the proposed styles (WCAG ratio, 4.5 minimum).
	 *
	 * @param \WP_Post $proposal Proposal.
	 * @return array[] Pairs, with "ratio" and "ok"; pairs that cannot be computed are skipped.
	 */
	public static function contrast_report( $proposal ) {
		$config = self::get_proposed_config( $proposal );
		$swap   = function () use ( $config ) {
			return new \WP_Theme_JSON_Data( $config, 'custom' );
		};

		add_filter( 'wp_theme_json_data_user', $swap, 999 );
		\WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();
		$styles  = wp_get_global_styles();
		$palette = array();
		foreach ( (array) wp_get_global_settings( array( 'color', 'palette' ) ) as $items ) {
			foreach ( (array) $items as $item ) {
				$palette[ $item['slug'] ] = $item['color'];
			}
		}
		remove_filter( 'wp_theme_json_data_user', $swap, 999 );
		\WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();

		$get = function ( $path ) use ( $styles ) {
			$value = $styles;
			foreach ( explode( '.', $path ) as $key ) {
				if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
					return null;
				}
				$value = $value[ $key ];
			}
			return is_string( $value ) ? $value : null;
		};

		$background = $get( 'color.background' );
		$pairs      = array(
			__( 'Texte sur le fond du site', 'eo-tools' ) => array( $get( 'color.text' ), $background ),
			__( 'Liens sur le fond du site', 'eo-tools' ) => array( $get( 'elements.link.color.text' ), $background ),
			__( 'Titres sur le fond du site', 'eo-tools' ) => array( $get( 'elements.heading.color.text' ), $background ),
			__( 'Texte des boutons sur leur fond', 'eo-tools' ) => array( $get( 'elements.button.color.text' ), $get( 'elements.button.color.background' ) ),
		);

		$report = array();
		foreach ( $pairs as $label => $pair ) {
			$front = self::to_rgb( $pair[0], $palette );
			$back  = self::to_rgb( $pair[1], $palette );
			if ( ! $front || ! $back ) {
				continue;
			}
			$ratio    = self::contrast( $front, $back );
			$report[] = array(
				'pair'  => $label,
				'ratio' => round( $ratio, 2 ),
				'ok'    => $ratio >= 4.5,
			);
		}

		return $report;
	}

	/**
	 * RGB values of a theme.json color (hex, rgb(), preset reference).
	 *
	 * @param string|null $value   Color.
	 * @param string[]    $palette Palette colors by slug.
	 * @return int[]|null
	 */
	private static function to_rgb( $value, $palette ) {
		if ( ! $value ) {
			return null;
		}
		if ( preg_match( '/^var:preset\|color\|([a-z0-9-]+)$/i', $value, $m ) || preg_match( '/var\(\s*--wp--preset--color--([a-z0-9-]+)\s*\)/i', $value, $m ) ) {
			return isset( $palette[ $m[1] ] ) ? self::to_rgb( $palette[ $m[1] ], array() ) : null;
		}
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})([0-9a-f]{2})?$/i', trim( $value ), $m ) ) {
			$hex = 3 === strlen( $m[1] ) ? preg_replace( '/(.)/', '$1$1', $m[1] ) : $m[1];
			return array_map( 'hexdec', str_split( $hex, 2 ) );
		}
		if ( preg_match( '/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/i', trim( $value ), $m ) ) {
			return array( (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		if ( 'white' === strtolower( $value ) ) {
			return array( 255, 255, 255 );
		}
		if ( 'black' === strtolower( $value ) ) {
			return array( 0, 0, 0 );
		}

		return null;
	}

	/**
	 * WCAG contrast ratio of two colors.
	 *
	 * @param int[] $a RGB.
	 * @param int[] $b RGB.
	 * @return float
	 */
	private static function contrast( $a, $b ) {
		$luminance = function ( $rgb ) {
			$c = array_map(
				function ( $v ) {
					$v /= 255;
					return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
				},
				$rgb
			);
			return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		};
		$l1 = $luminance( $a );
		$l2 = $luminance( $b );

		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}

	public static function has_conflict( $proposal ) {
		return get_post_meta( $proposal->ID, self::META_BASE, true ) !== self::fingerprint();
	}

	/**
	 * Saves the proposed styles as the Styles panel does, then deletes the proposal.
	 *
	 * @param \WP_Post $proposal Proposal.
	 * @return true|\WP_Error
	 */
	public static function apply( $proposal ) {
		$post   = self::get_user_post();
		$config = self::get_proposed_config( $proposal );

		$request = new \WP_REST_Request( 'POST', '/wp/v2/global-styles/' . (int) $post['ID'] );
		$request->set_param( 'settings', $config['settings'] );
		$request->set_param( 'styles', $config['styles'] );

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return $response->as_error();
		}

		wp_delete_post( $proposal->ID, true );
		\WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();

		return true;
	}

	public static function purge() {
		$old = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - Eotools_Mcp_Proposals::LIFETIME ),
					),
				),
			)
		);
		foreach ( $old as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/* ---------------------------------------------------------------------
	 * Preview
	 * ------------------------------------------------------------------ */

	/**
	 * Preview link for a logged-in administrator.
	 *
	 * @param \WP_Post $proposal Proposal.
	 * @return string
	 */
	public static function preview_url( $proposal ) {
		$url = get_post_meta( $proposal->ID, self::META_PREVIEW_URL, true );

		return add_query_arg( self::QUERY_VAR, $proposal->ID, $url ? $url : home_url( '/' ) );
	}

	public static function read_preview_request() {
		if ( is_admin() || ! isset( $_GET[ self::QUERY_VAR ] ) || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview.
			return;
		}

		self::start_preview( absint( $_GET[ self::QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Shows the proposed styles instead of the current ones, for this request only.
	 *
	 * @param int $proposal_id Proposal ID.
	 * @return bool Whether the preview started.
	 */
	public static function start_preview( $proposal_id ) {
		$proposal = self::get_proposal( $proposal_id );
		if ( ! $proposal ) {
			return false;
		}

		self::$preview = $proposal;
		$config        = self::get_proposed_config( $proposal );

		add_filter(
			'wp_theme_json_data_user',
			function () use ( $config ) {
				return new \WP_Theme_JSON_Data( $config, 'custom' );
			},
			999
		);
		// Styles computed before this point must be computed again (non-persistent caches).
		\WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		nocache_headers();

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Human validation
	 * ------------------------------------------------------------------ */

	public static function render_banner() {
		static $rendered = false;

		if ( $rendered || ! self::$preview || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$rendered = true;
		$summary  = get_post_meta( self::$preview->ID, self::META_SUMMARY, true );
		$banner   = array(
			'proposal_id'   => self::$preview->ID,
			'title'         => __( 'Aperçu de nouveaux styles proposés par l\'IA', 'eo-tools' ),
			'notice'        => ( $summary ? $summary . ' ' : '' ) . __( 'Les styles s\'appliquent à tout le site.', 'eo-tools' ),
			'current_link'  => remove_query_arg( self::QUERY_VAR ),
			'conflict'      => self::has_conflict( self::$preview ) ? __( 'Attention : les styles ont été modifiés depuis cette proposition. L\'appliquer remplacera ces changements.', 'eo-tools' ) : '',
			'apply_action'  => 'eo_tools_apply_styles_proposal',
			'reject_action' => 'eo_tools_reject_styles_proposal',
		);

		include EO_TOOLS_PATH . 'includes/templates/proposal-banner-template.php';
	}

	public static function handle_apply() {
		$proposal = self::get_requested_proposal();
		$back     = get_post_meta( $proposal->ID, self::META_PREVIEW_URL, true );
		$result   = self::apply( $proposal );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Proposition non appliquée', 'eo-tools' ), array( 'back_link' => true ) );
		}

		wp_safe_redirect( self::from_admin() ? Eotools_Mcp_Proposals::admin_url( 'applied' ) : ( $back ? $back : home_url( '/' ) ) );
		exit;
	}

	public static function handle_reject() {
		$proposal = self::get_requested_proposal();
		$back     = get_post_meta( $proposal->ID, self::META_PREVIEW_URL, true );

		wp_delete_post( $proposal->ID, true );

		wp_safe_redirect( self::from_admin() ? Eotools_Mcp_Proposals::admin_url( 'rejected' ) : ( $back ? $back : home_url( '/' ) ) );
		exit;
	}

	private static function from_admin() {
		return isset( $_POST['eo_tools_from'] ) && 'admin' === sanitize_key( wp_unslash( $_POST['eo_tools_from'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in get_requested_proposal().
	}

	private static function get_requested_proposal() {
		$proposal_id = isset( $_POST['proposal'] ) ? absint( $_POST['proposal'] ) : 0;
		check_admin_referer( 'eo_tools_proposal_' . $proposal_id );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas le droit de modifier les styles du site.', 'eo-tools' ), '', array( 'response' => 403 ) );
		}

		$proposal = self::get_proposal( $proposal_id );
		if ( ! $proposal ) {
			wp_die( esc_html__( 'Cette proposition n\'existe plus.', 'eo-tools' ), '', array( 'response' => 404, 'back_link' => true ) );
		}

		return $proposal;
	}

	public static function admin_bar( $admin_bar ) {
		if ( self::$preview || ! current_user_can( self::CAPABILITY ) || ! self::get_pending() ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'eo-tools-styles-proposal',
				'title' => '<span class="ab-icon dashicons dashicons-art" style="top:2px"></span>' . esc_html__( 'Nouveaux styles IA en attente', 'eo-tools' ),
				'href'  => Eotools_Mcp_Proposals::admin_url(),
			)
		);
	}
}
