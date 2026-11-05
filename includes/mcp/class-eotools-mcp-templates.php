<?php
/**
 * Site templates (block themes): reading, change proposals and their preview.
 *
 * A template (wp_template) or template part (wp_template_part, e.g. header or
 * footer) is shared by many pages, so the AI never changes it directly. It
 * stores its change in a proposal, kept in a private post type so that
 * WordPress never loads it as a real template. The proposal is previewed on a
 * page of the site, with the template swapped for that request only, and a
 * human applies or rejects it. Applying goes through the REST API of the site
 * editor: WordPress keeps a revision and the "reset to theme" action.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Templates {

	const POST_TYPE  = 'eo_tools_tpl_prop';
	const CAPABILITY = 'edit_theme_options';
	const QUERY_VAR  = 'eo_tools_template_proposal';

	const META_TEMPLATE    = '_eo_tools_template_id';
	const META_TYPE        = '_eo_tools_template_type';
	const META_BASE        = '_eo_tools_template_base';
	const META_PREVIEW_URL = '_eo_tools_template_preview_url';

	const TYPES = array(
		'template'      => 'wp_template',
		'template_part' => 'wp_template_part',
	);

	/**
	 * Proposal previewed during the current request.
	 *
	 * @var \WP_Post|null
	 */
	private static $preview = null;

	/**
	 * Pending proposals, loaded once per request.
	 *
	 * @var \WP_Post[]|null
	 */
	private static $pending = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'read_preview_request' ), 20 );
		add_action( 'wp_body_open', array( __CLASS__, 'render_banner' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_banner' ) );
		add_action( 'admin_post_eo_tools_apply_template_proposal', array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_post_eo_tools_reject_template_proposal', array( __CLASS__, 'handle_reject' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 91 );
		add_action( Eotools_Mcp_Proposals::PURGE_CRON, array( __CLASS__, 'purge' ) );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Propositions de modèles IA', 'eo-tools' ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'can_export'          => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor', 'author' ),
			)
		);
	}

	/**
	 * Whether the active theme uses block templates.
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return wp_is_block_theme() || current_theme_supports( 'block-template-parts' );
	}

	/* ---------------------------------------------------------------------
	 * Templates
	 * ------------------------------------------------------------------ */

	/**
	 * Every template and template part of the active theme.
	 *
	 * @param string $type "template", "template_part" or "" for both.
	 * @return \WP_Block_Template[]
	 */
	public static function get_all( $type = '' ) {
		$templates = array();

		foreach ( self::TYPES as $key => $post_type ) {
			if ( '' !== $type && $type !== $key ) {
				continue;
			}
			if ( 'wp_template' === $post_type && ! wp_is_block_theme() ) {
				continue;
			}
			$templates = array_merge( $templates, get_block_templates( array(), $post_type ) );
		}

		return $templates;
	}

	/**
	 * Finds a template by ID ("theme//slug") or slug.
	 *
	 * @param string $id   Template ID or slug.
	 * @param string $type "template", "template_part" or "" to look in both.
	 * @return \WP_Block_Template|\WP_Error
	 */
	public static function find( $id, $type = '' ) {
		$id = trim( (string) $id );
		if ( '' === $id ) {
			return new \WP_Error( 'eo_tools_template_not_found', __( 'Identifiant de modèle manquant.', 'eo-tools' ) );
		}
		if ( false === strpos( $id, '//' ) ) {
			$id = get_stylesheet() . '//' . $id;
		}

		$found = array();
		foreach ( self::TYPES as $key => $post_type ) {
			if ( '' !== $type && $type !== $key ) {
				continue;
			}
			$template = get_block_template( $id, $post_type );
			if ( $template ) {
				$found[] = $template;
			}
		}

		if ( empty( $found ) ) {
			return new \WP_Error( 'eo_tools_template_not_found', __( 'Modèle introuvable. Utilisez eo-tools/list-templates pour connaître les modèles disponibles.', 'eo-tools' ) );
		}
		if ( count( $found ) > 1 ) {
			return new \WP_Error( 'eo_tools_template_ambiguous', __( 'Un modèle et une partie de modèle portent ce nom : précisez "type".', 'eo-tools' ) );
		}

		return $found[0];
	}

	/**
	 * "template" or "template_part".
	 *
	 * @param \WP_Block_Template $template Template.
	 * @return string
	 */
	public static function type_key( $template ) {
		return 'wp_template_part' === $template->type ? 'template_part' : 'template';
	}

	/**
	 * Summary of a template for the AI.
	 *
	 * @param \WP_Block_Template $template Template.
	 * @return array
	 */
	public static function describe( $template ) {
		$data = array(
			'id'          => $template->id,
			'type'        => self::type_key( $template ),
			'slug'        => $template->slug,
			'title'       => $template->title,
			'description' => $template->description,
			'source'      => $template->source,
			'customized'  => 'custom' === $template->source && ! empty( $template->has_theme_file ),
		);

		if ( 'wp_template_part' === $template->type ) {
			$data['area'] = $template->area;
		}

		$proposal = self::get_for( $template->id, $template->type );
		if ( $proposal ) {
			$data['pending_proposal'] = array(
				'proposal_id'  => $proposal->ID,
				'preview_link' => self::preview_url( $proposal ),
			);
		}

		return $data;
	}

	/**
	 * Fingerprint of a template content, to detect changes made since a proposal.
	 *
	 * @param \WP_Block_Template $template Template.
	 * @return string
	 */
	private static function fingerprint( $template ) {
		return md5( (string) $template->content );
	}

	/**
	 * A page of the site that shows the template, to preview it.
	 *
	 * @param \WP_Block_Template $template Template.
	 * @return string
	 */
	public static function default_preview_url( $template ) {
		$slug = $template->slug;
		$post = null;

		if ( 'wp_template' === $template->type ) {
			if ( preg_match( '/^(page|single)(?:-(.+))?$/', $slug, $match ) ) {
				$post_type = 'page' === $match[1] ? 'page' : ( isset( $match[2] ) && post_type_exists( $match[2] ) ? $match[2] : 'post' );
				$posts     = get_posts(
					array(
						'post_type'      => $post_type,
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'orderby'        => 'modified',
					)
				);
				$post      = $posts ? $posts[0] : null;
			} elseif ( 'home' === $slug && 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ) {
				return get_permalink( (int) get_option( 'page_for_posts' ) );
			} elseif ( in_array( $slug, array( 'archive', 'category' ), true ) ) {
				$terms = get_terms(
					array(
						'taxonomy'   => 'category',
						'hide_empty' => true,
						'number'     => 1,
					)
				);
				if ( $terms && ! is_wp_error( $terms ) ) {
					return get_term_link( $terms[0] );
				}
			} elseif ( 'search' === $slug ) {
				return add_query_arg( 's', 'a', home_url( '/' ) );
			} elseif ( '404' === $slug ) {
				return home_url( '/eo-tools-page-introuvable/' );
			}
		}

		return $post ? get_permalink( $post ) : home_url( '/' );
	}

	/* ---------------------------------------------------------------------
	 * Proposals
	 * ------------------------------------------------------------------ */

	/**
	 * Pending proposal of a template.
	 *
	 * @param string $id        Template ID.
	 * @param string $post_type wp_template or wp_template_part.
	 * @return \WP_Post|null
	 */
	public static function get_for( $id, $post_type ) {
		foreach ( self::get_all_pending() as $proposal ) {
			if ( get_post_meta( $proposal->ID, self::META_TEMPLATE, true ) === $id && get_post_meta( $proposal->ID, self::META_TYPE, true ) === $post_type ) {
				// Fresh copy: the list may have been loaded before the proposal was updated.
				return get_post( $proposal->ID );
			}
		}

		return null;
	}

	/**
	 * Every pending template proposal, newest first.
	 *
	 * @return \WP_Post[]
	 */
	public static function get_all_pending() {
		if ( null === self::$pending ) {
			self::$pending = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'orderby'        => 'modified',
					'order'          => 'DESC',
				)
			);
		}

		return self::$pending;
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
	 * Template a proposal changes.
	 *
	 * @param \WP_Post $proposal Proposal.
	 * @return \WP_Block_Template|null
	 */
	public static function get_template( $proposal ) {
		$template = get_block_template( get_post_meta( $proposal->ID, self::META_TEMPLATE, true ), get_post_meta( $proposal->ID, self::META_TYPE, true ) );

		return $template ? $template : null;
	}

	/**
	 * Creates the proposal of a template, or returns the pending one.
	 *
	 * @param \WP_Block_Template $template    Template.
	 * @param bool               $restart     Discard the pending proposal and start again from the current template.
	 * @param string             $preview_url Page used to preview the template.
	 * @return \WP_Post|\WP_Error
	 */
	public static function get_or_create( $template, $restart = false, $preview_url = '' ) {
		$pending = self::get_for( $template->id, $template->type );

		if ( $pending && ! $restart ) {
			if ( '' !== $preview_url ) {
				update_post_meta( $pending->ID, self::META_PREVIEW_URL, esc_url_raw( $preview_url ) );
			}
			return $pending;
		}
		if ( $pending ) {
			wp_delete_post( $pending->ID, true );
		}

		$proposal_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'private',
					'post_title'   => $template->title,
					'post_content' => $template->content,
					'post_author'  => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $proposal_id ) ) {
			return $proposal_id;
		}

		update_post_meta( $proposal_id, self::META_TEMPLATE, $template->id );
		update_post_meta( $proposal_id, self::META_TYPE, $template->type );
		update_post_meta( $proposal_id, self::META_BASE, self::fingerprint( $template ) );
		update_post_meta( $proposal_id, self::META_PREVIEW_URL, esc_url_raw( '' !== $preview_url ? $preview_url : self::default_preview_url( $template ) ) );
		self::$pending = null;

		return get_post( $proposal_id );
	}

	/**
	 * Whether the template changed since the proposal was created.
	 *
	 * @param \WP_Post                $proposal Proposal.
	 * @param \WP_Block_Template|null $template Template.
	 * @return bool
	 */
	public static function has_conflict( $proposal, $template ) {
		return $template && get_post_meta( $proposal->ID, self::META_BASE, true ) !== self::fingerprint( $template );
	}

	/**
	 * Saves the proposed content in the template, as the site editor does, then deletes the proposal.
	 *
	 * @param \WP_Post $proposal Proposal.
	 * @return \WP_Block_Template|\WP_Error Updated template.
	 */
	public static function apply( $proposal ) {
		$template = self::get_template( $proposal );
		if ( ! $template ) {
			return new \WP_Error( 'eo_tools_template_not_found', __( 'Le modèle de cette proposition n\'existe plus.', 'eo-tools' ) );
		}

		$route   = 'wp_template_part' === $template->type ? '/wp/v2/template-parts/' : '/wp/v2/templates/';
		$request = new \WP_REST_Request( 'POST', $route . $template->id );
		$request->set_param( 'content', $proposal->post_content );

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return $response->as_error();
		}

		wp_delete_post( $proposal->ID, true );
		self::$pending = null;

		return get_block_template( $template->id, $template->type );
	}

	/**
	 * Deletes the proposals older than their lifetime.
	 */
	public static function purge() {
		$old = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - Eotools_Mcp_Proposals::LIFETIME ),
					),
				),
			)
		);

		foreach ( $old as $proposal_id ) {
			wp_delete_post( $proposal_id, true );
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

	/**
	 * Starts the preview of a proposal requested by an administrator.
	 */
	public static function read_preview_request() {
		if ( is_admin() || ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		self::start_preview( absint( $_GET[ self::QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Shows the proposed template instead of the current one, for this request only.
	 *
	 * Called for an administrator, or by Eotools_Mcp_Preview for a valid preview token.
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

		add_filter( 'get_block_templates', array( __CLASS__, 'swap_templates' ), 999, 3 );
		add_filter( 'get_block_template', array( __CLASS__, 'swap_template' ), 999, 3 );
		add_filter( 'get_block_file_template', array( __CLASS__, 'swap_template' ), 999, 3 );
		add_filter( 'posts_results', array( __CLASS__, 'swap_template_part_post' ), 999, 2 );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		nocache_headers();

		return true;
	}

	/**
	 * Whether a template is the one being previewed.
	 *
	 * @param string $id   Template ID.
	 * @param string $type Template type.
	 * @return bool
	 */
	private static function is_previewed( $id, $type ) {
		return self::$preview
			&& get_post_meta( self::$preview->ID, self::META_TEMPLATE, true ) === $id
			&& get_post_meta( self::$preview->ID, self::META_TYPE, true ) === $type;
	}

	/**
	 * @param \WP_Block_Template[] $templates Templates.
	 * @param array                $query     Query.
	 * @param string               $type      Template type.
	 * @return \WP_Block_Template[]
	 */
	public static function swap_templates( $templates, $query, $type ) {
		foreach ( $templates as $index => $template ) {
			$templates[ $index ] = self::swap_template( $template, $template ? $template->id : '', $type );
		}

		return $templates;
	}

	/**
	 * @param \WP_Block_Template|null $template Template.
	 * @param string                  $id       Template ID.
	 * @param string                  $type     Template type.
	 * @return \WP_Block_Template|null
	 */
	public static function swap_template( $template, $id, $type ) {
		if ( $template instanceof \WP_Block_Template && self::is_previewed( $template->id, $type ) ) {
			$template          = clone $template;
			$template->content = self::$preview->post_content;
		}

		return $template;
	}

	/**
	 * The template part block reads a customized part straight from the database.
	 *
	 * @param \WP_Post[] $posts Posts found.
	 * @param \WP_Query  $query Query.
	 * @return \WP_Post[]
	 */
	public static function swap_template_part_post( $posts, $query ) {
		if ( 'wp_template_part' !== $query->get( 'post_type' ) ) {
			return $posts;
		}

		foreach ( $posts as $index => $post ) {
			if ( self::is_previewed( get_stylesheet() . '//' . $post->post_name, 'wp_template_part' ) ) {
				$post               = clone $post;
				$post->post_content = self::$preview->post_content;
				$posts[ $index ]    = $post;
			}
		}

		return $posts;
	}

	/* ---------------------------------------------------------------------
	 * Human validation
	 * ------------------------------------------------------------------ */

	/**
	 * Displays the "Apply / Reject" banner on the preview.
	 */
	public static function render_banner() {
		static $rendered = false;

		if ( $rendered || ! self::$preview || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$template = self::get_template( self::$preview );
		if ( ! $template ) {
			return;
		}

		$rendered = true;
		$banner   = array(
			'proposal_id'   => self::$preview->ID,
			/* translators: %s: template title. */
			'title'         => sprintf( __( 'Aperçu d\'une modification du modèle « %s » proposée par l\'IA', 'eo-tools' ), $template->title ),
			'notice'        => __( 'Ce modèle est utilisé sur plusieurs pages du site.', 'eo-tools' ),
			'current_link'  => remove_query_arg( self::QUERY_VAR ),
			'conflict'      => self::has_conflict( self::$preview, $template ) ? __( 'Attention : le modèle a été modifié depuis cette proposition. L\'appliquer remplacera ces changements.', 'eo-tools' ) : '',
			'apply_action'  => 'eo_tools_apply_template_proposal',
			'reject_action' => 'eo_tools_reject_template_proposal',
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

	/**
	 * Checks the nonce and the rights of an apply / reject request.
	 *
	 * @return \WP_Post
	 */
	private static function get_requested_proposal() {
		$proposal_id = isset( $_POST['proposal'] ) ? absint( $_POST['proposal'] ) : 0;
		check_admin_referer( 'eo_tools_proposal_' . $proposal_id );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas le droit de modifier les modèles du site.', 'eo-tools' ), '', array( 'response' => 403 ) );
		}

		$proposal = self::get_proposal( $proposal_id );
		if ( ! $proposal ) {
			wp_die( esc_html__( 'Cette proposition n\'existe plus.', 'eo-tools' ), '', array( 'response' => 404, 'back_link' => true ) );
		}

		return $proposal;
	}

	/**
	 * Admin bar link to the pending template proposals.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar.
	 */
	public static function admin_bar( $admin_bar ) {
		if ( self::$preview || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$count = count( self::get_all_pending() );
		if ( ! $count ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'eo-tools-template-proposals',
				'title' => '<span class="ab-icon dashicons dashicons-layout" style="top:2px"></span>' . esc_html(
					/* translators: %d: number of proposals. */
					sprintf( _n( '%d modèle modifié par l\'IA en attente', '%d modèles modifiés par l\'IA en attente', $count, 'eo-tools' ), $count )
				),
				'href'  => Eotools_Mcp_Proposals::admin_url(),
			)
		);
	}
}
