<?php
/**
 * Change proposals for published content.
 *
 * The AI never changes a published content directly: it creates a temporary
 * copy holding its changes (a "proposal"), that a human previews and then
 * applies or rejects with the buttons displayed on the preview. Only humans
 * can apply a proposal: no ability does it.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Proposals {

	const STATUS = 'eo_tools_proposal';

	const META_SOURCE = '_eo_tools_proposal_of';
	const META_BASE   = '_eo_tools_proposal_base';
	const META_FIELDS = '_eo_tools_proposal_fields';

	const PURGE_CRON = 'eo_tools_mcp_proposals_purge';
	const LIFETIME   = 7 * DAY_IN_SECONDS;

	/**
	 * Post meta never copied between the published content and its proposal.
	 */
	const SKIPPED_META = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_encloseme', '_pingme', '_wp_trash_meta_status', '_wp_trash_meta_time' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_status' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'render_banner' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_banner' ) );
		add_action( 'admin_post_eo_tools_apply_proposal', array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_post_eo_tools_reject_proposal', array( __CLASS__, 'handle_reject' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );

		if ( ! wp_next_scheduled( self::PURGE_CRON ) ) {
			wp_schedule_event( time(), 'daily', self::PURGE_CRON );
		}
		add_action( self::PURGE_CRON, array( __CLASS__, 'purge' ) );
	}

	/**
	 * Proposals are hidden everywhere (site, search, admin lists) but can be previewed.
	 */
	public static function register_status() {
		register_post_status(
			self::STATUS,
			array(
				'label'                     => __( 'Proposition IA', 'eo-tools' ),
				'public'                    => false,
				'internal'                  => true,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Proposals
	 * ------------------------------------------------------------------ */

	/**
	 * Returns the pending proposal of a published content.
	 *
	 * @param int $post_id Published content ID.
	 * @return \WP_Post|null
	 */
	public static function get_for( $post_id ) {
		$proposals = get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => self::STATUS,
				'meta_key'         => self::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => (int) $post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page'   => 1,
				'suppress_filters' => false,
			)
		);

		return $proposals ? $proposals[0] : null;
	}

	/**
	 * Returns the published content a proposal belongs to.
	 *
	 * @param \WP_Post $proposal Proposal.
	 * @return \WP_Post|null
	 */
	public static function get_source( $proposal ) {
		$source = get_post( (int) get_post_meta( $proposal->ID, self::META_SOURCE, true ) );

		return $source && self::STATUS !== $source->post_status ? $source : null;
	}

	public static function is_proposal( $post ) {
		$post = get_post( $post );

		return $post && self::STATUS === $post->post_status;
	}

	/**
	 * Creates the proposal of a published content, or returns the pending one.
	 *
	 * @param \WP_Post $source  Published content.
	 * @param bool     $restart Discard the pending proposal and start again from the published content.
	 * @return \WP_Post|\WP_Error
	 */
	public static function get_or_create( $source, $restart = false ) {
		$pending = self::get_for( $source->ID );

		if ( $pending && ! $restart ) {
			return $pending;
		}
		if ( $pending ) {
			wp_delete_post( $pending->ID, true );
		}

		$proposal_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $source->post_type,
					'post_status'  => self::STATUS,
					'post_title'   => $source->post_title,
					'post_content' => $source->post_content,
					'post_excerpt' => $source->post_excerpt,
					'post_parent'  => $source->post_parent,
					'menu_order'   => $source->menu_order,
					'post_author'  => get_current_user_id(),
				)
			),
			true
		);

		if ( is_wp_error( $proposal_id ) ) {
			return $proposal_id;
		}

		// Same template, settings and SEO meta as the published content, for a faithful preview.
		foreach ( get_post_meta( $source->ID ) as $key => $values ) {
			if ( in_array( $key, self::SKIPPED_META, true ) || 0 === strpos( $key, '_eo_tools_proposal' ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $proposal_id, wp_slash( $key ), wp_slash( maybe_unserialize( $value ) ) );
			}
		}

		foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
			wp_set_object_terms( $proposal_id, wp_get_object_terms( $source->ID, $taxonomy, array( 'fields' => 'ids' ) ), $taxonomy );
		}

		update_post_meta( $proposal_id, self::META_SOURCE, $source->ID );
		update_post_meta( $proposal_id, self::META_BASE, $source->post_modified_gmt );
		update_post_meta( $proposal_id, self::META_FIELDS, array() );

		return get_post( $proposal_id );
	}

	/**
	 * Records which fields a proposal changes: only those are applied to the published content.
	 *
	 * @param int      $proposal_id Proposal ID.
	 * @param string[] $fields      Changed fields.
	 */
	public static function add_changed_fields( $proposal_id, $fields ) {
		$current = (array) get_post_meta( $proposal_id, self::META_FIELDS, true );
		update_post_meta( $proposal_id, self::META_FIELDS, array_values( array_unique( array_merge( $current, $fields ) ) ) );
	}

	/**
	 * Whether the published content changed since the proposal was created.
	 *
	 * @param \WP_Post $proposal Proposal.
	 * @param \WP_Post $source   Published content.
	 * @return bool
	 */
	public static function has_conflict( $proposal, $source ) {
		return get_post_meta( $proposal->ID, self::META_BASE, true ) !== $source->post_modified_gmt;
	}

	/**
	 * Copies the changes of a proposal onto the published content, then deletes the proposal.
	 *
	 * WordPress keeps a revision of the previous version of the published content.
	 *
	 * @param \WP_Post $proposal Proposal.
	 * @return \WP_Post|\WP_Error The updated published content.
	 */
	public static function apply( $proposal ) {
		$source = self::get_source( $proposal );
		if ( ! $source ) {
			return new \WP_Error( 'eo_tools_proposal_source', __( 'Le contenu publié de cette proposition n\'existe plus.', 'eo-tools' ) );
		}

		$fields = (array) get_post_meta( $proposal->ID, self::META_FIELDS, true );
		$data   = array( 'ID' => $source->ID );
		$map    = array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
			'slug'    => 'post_name',
		);
		foreach ( $map as $field => $column ) {
			if ( in_array( $field, $fields, true ) ) {
				$data[ $column ] = $proposal->$column;
			}
		}

		$updated = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		if ( in_array( 'featured_image', $fields, true ) ) {
			$thumbnail = get_post_thumbnail_id( $proposal );
			if ( $thumbnail ) {
				set_post_thumbnail( $source->ID, $thumbnail );
			} else {
				delete_post_thumbnail( $source->ID );
			}
		}

		foreach ( array( 'categories' => 'category', 'tags' => 'post_tag' ) as $field => $taxonomy ) {
			if ( in_array( $field, $fields, true ) ) {
				wp_set_object_terms( $source->ID, wp_get_object_terms( $proposal->ID, $taxonomy, array( 'fields' => 'ids' ) ), $taxonomy );
			}
		}

		wp_delete_post( $proposal->ID, true );

		return get_post( $source->ID );
	}

	/**
	 * Deletes the proposals older than their lifetime.
	 */
	public static function purge() {
		$old = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => self::STATUS,
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - self::LIFETIME ),
					),
				),
			)
		);

		foreach ( $old as $proposal_id ) {
			wp_delete_post( $proposal_id, true );
		}
	}

	/* ---------------------------------------------------------------------
	 * Human validation: banner on the preview and admin actions
	 * ------------------------------------------------------------------ */

	/**
	 * Displays the "Apply / Reject" banner on the preview of a proposal.
	 */
	public static function render_banner() {
		static $rendered = false;

		if ( $rendered || ! is_preview() || ! is_singular() ) {
			return;
		}

		$proposal = get_queried_object();
		if ( ! $proposal instanceof \WP_Post || ! self::is_proposal( $proposal ) ) {
			return;
		}

		$source = self::get_source( $proposal );
		if ( ! $source || ! current_user_can( 'edit_post', $source->ID ) ) {
			return;
		}

		$rendered = true;
		$conflict = self::has_conflict( $proposal, $source );

		include EO_TOOLS_PATH . 'includes/templates/proposal-banner-template.php';
	}

	/**
	 * Applies a proposal (human action from the banner).
	 */
	public static function handle_apply() {
		$proposal = self::get_requested_proposal();
		$source   = self::get_source( $proposal );
		$result   = self::apply( $proposal );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Proposition non appliquée', 'eo-tools' ), array( 'back_link' => true ) );
		}

		wp_safe_redirect( get_permalink( $source ) );
		exit;
	}

	/**
	 * Rejects a proposal (human action from the banner).
	 */
	public static function handle_reject() {
		$proposal = self::get_requested_proposal();
		$source   = self::get_source( $proposal );

		wp_delete_post( $proposal->ID, true );

		wp_safe_redirect( $source ? get_permalink( $source ) : home_url( '/' ) );
		exit;
	}

	/**
	 * Checks the nonce and the rights of an apply / reject request.
	 *
	 * @return \WP_Post
	 */
	private static function get_requested_proposal() {
		$proposal_id = isset( $_POST['proposal'] ) ? absint( $_POST['proposal'] ) : 0;
		check_admin_referer( 'eo_tools_proposal_' . $proposal_id );

		$proposal = get_post( $proposal_id );
		if ( ! $proposal || ! self::is_proposal( $proposal ) ) {
			wp_die( esc_html__( 'Cette proposition n\'existe plus.', 'eo-tools' ), '', array( 'response' => 404, 'back_link' => true ) );
		}

		$source = self::get_source( $proposal );
		if ( $source && ! current_user_can( 'edit_post', $source->ID ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas le droit de modifier ce contenu.', 'eo-tools' ), '', array( 'response' => 403 ) );
		}

		return $proposal;
	}

	/**
	 * Tells editors of a published content that a proposal is waiting.
	 */
	public static function render_admin_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		$post = get_post();
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$proposal = self::get_for( $post->ID );
		if ( ! $proposal ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s <a href="%s" target="_blank">%s</a></p></div>',
			esc_html__( 'Une IA propose des modifications pour ce contenu.', 'eo-tools' ),
			esc_url( get_preview_post_link( $proposal ) ),
			esc_html__( 'Voir l\'aperçu et valider', 'eo-tools' )
		);
	}

	/**
	 * Adds "Modification proposée par l'IA" next to the title in admin lists.
	 *
	 * @param string[] $states Post states.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public static function post_states( $states, $post ) {
		static $sources = null;

		// One query for the whole list.
		if ( null === $sources ) {
			global $wpdb;
			$sources = array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_status = %s",
						self::META_SOURCE,
						self::STATUS
					)
				)
			);
		}

		if ( in_array( $post->ID, $sources, true ) ) {
			$states['eo_tools_proposal'] = __( 'Modification proposée par l\'IA', 'eo-tools' );
		}

		return $states;
	}
}
