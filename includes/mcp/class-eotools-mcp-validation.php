<?php
/**
 * Bulk validation of everything an AI prepared: new drafts, changes of
 * published contents, templates and styles.
 *
 * Human action from the "À valider" tab of EO Tools > MCP. Each item keeps
 * the rights checks of its own type.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Validation {

	const NONCE_ACTION = 'eo_tools_bulk_validate';
	const FORM_ID      = 'eo-mcp-validate';

	/**
	 * Row action and bulk action of the Pages / Posts lists.
	 */
	const LIST_ACTION   = 'eo_tools_validate_ai';
	const ARG_VALIDATED = 'eo_tools_ai_validated';
	const ARG_FAILED    = 'eo_tools_ai_failed';

	public static function init() {
		add_action( 'admin_post_eo_tools_bulk_validate', array( __CLASS__, 'handle_bulk' ) );
		add_action( 'admin_post_' . self::LIST_ACTION, array( __CLASS__, 'handle_row_action' ) );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_actions' ), 20, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 20, 2 );
		add_action( 'load-edit.php', array( __CLASS__, 'register_bulk_action' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_list_notice' ) );
		add_filter( 'removable_query_args', array( __CLASS__, 'removable_query_args' ) );
	}

	/**
	 * Validates (publishes / applies) or rejects the selected items.
	 */
	public static function handle_bulk() {
		check_admin_referer( self::NONCE_ACTION );

		$decision = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$selected = array();
		foreach ( array( 'drafts', 'contents', 'templates', 'styles' ) as $key ) {
			$selected[ $key ] = isset( $_POST[ $key ] ) ? array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ $key ] ) ) ) ) : array();
		}

		if ( ! in_array( $decision, array( 'apply', 'reject' ), true ) || ! array_filter( $selected ) ) {
			wp_safe_redirect( Eotools_Mcp_Proposals::admin_url( 'none' ) );
			exit;
		}

		$apply  = 'apply' === $decision;
		$result = array( 'done' => 0, 'failed' => 0 );

		// Drafts.
		$drafts = array();
		foreach ( $selected['drafts'] as $id ) {
			if ( Eotools_Mcp_Ai_Drafts::is_ai_draft( $id ) ) {
				$drafts[] = get_post( $id );
			} else {
				++$result['failed'];
			}
		}
		self::add( $result, $apply ? Eotools_Mcp_Ai_Drafts::publish( $drafts ) : Eotools_Mcp_Ai_Drafts::trash( $drafts ) );

		// Changes of published contents.
		foreach ( $selected['contents'] as $id ) {
			$proposal = get_post( $id );
			$source   = $proposal && Eotools_Mcp_Proposals::is_proposal( $proposal ) ? Eotools_Mcp_Proposals::get_source( $proposal ) : null;

			if ( ! $source || ! current_user_can( 'edit_post', $source->ID ) ) {
				++$result['failed'];
				continue;
			}

			self::count( $result, $apply ? Eotools_Mcp_Proposals::apply( $proposal ) : wp_delete_post( $proposal->ID, true ) );
		}

		// Templates and styles.
		$site_wide = array(
			'templates' => Eotools_Mcp_Templates::class,
			'styles'    => Eotools_Mcp_Styles::class,
		);
		foreach ( $site_wide as $key => $class ) {
			foreach ( $selected[ $key ] as $id ) {
				$proposal = current_user_can( $class::CAPABILITY ) ? $class::get_proposal( $id ) : null;

				if ( ! $proposal ) {
					++$result['failed'];
					continue;
				}

				self::count( $result, $apply ? $class::apply( $proposal ) : wp_delete_post( $proposal->ID, true ) );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'count'  => $result['done'],
					'failed' => $result['failed'],
				),
				Eotools_Mcp_Proposals::admin_url( $apply ? 'validated' : 'bulk-rejected' )
			)
		);
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Pages / Posts lists: "Valider proposition IA" row and bulk actions
	 * ------------------------------------------------------------------ */

	/**
	 * Whether the current user can validate something of the AI on this post:
	 * publish an AI draft, or apply the pending change of a published content.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function can_validate( $post ) {
		if ( Eotools_Mcp_Ai_Drafts::is_ai_draft( $post ) ) {
			return Eotools_Mcp_Ai_Drafts::can_publish( $post );
		}

		return isset( Eotools_Mcp_Proposals::get_pending_map()[ $post->ID ] ) && current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Publishes the AI draft, or applies the pending change of a published content.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool Whether something was validated.
	 */
	public static function validate( $post ) {
		if ( ! self::can_validate( $post ) ) {
			return false;
		}

		if ( Eotools_Mcp_Ai_Drafts::is_ai_draft( $post ) ) {
			return 1 === Eotools_Mcp_Ai_Drafts::publish( array( $post ) )['done'];
		}

		$proposal = Eotools_Mcp_Proposals::get_for( $post->ID );

		return $proposal && ! is_wp_error( Eotools_Mcp_Proposals::apply( $proposal ) );
	}

	/**
	 * "Valider proposition IA" link, after the native actions (Modifier, Prévisualiser…).
	 *
	 * @param string[] $actions Row actions.
	 * @param \WP_Post $post    Post.
	 * @return string[]
	 */
	public static function row_actions( $actions, $post ) {
		if ( ! self::can_validate( $post ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::LIST_ACTION,
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::LIST_ACTION . '_' . $post->ID
		);

		$actions['eo_tools_validate'] = sprintf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( $url ),
			/* translators: %s: post title. */
			esc_attr( sprintf( __( 'Valider la proposition IA pour « %s »', 'eo-tools' ), get_the_title( $post ) ) ),
			esc_html__( 'Valider proposition IA', 'eo-tools' )
		);

		return $actions;
	}

	/**
	 * Validates one post from its row action, then goes back to the list.
	 */
	public static function handle_row_action() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( self::LIST_ACTION . '_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_die( esc_html__( 'Ce contenu n\'existe plus.', 'eo-tools' ), '', array( 'response' => 404, 'back_link' => true ) );
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'edit.php?post_type=' . $post->post_type );
		}

		$validated = self::validate( $post );

		wp_safe_redirect(
			add_query_arg(
				array(
					self::ARG_VALIDATED => $validated ? 1 : 0,
					self::ARG_FAILED    => $validated ? 0 : 1,
				),
				$back
			)
		);
		exit;
	}

	/**
	 * Adds "Valider propositions IA" to the bulk actions of the list, when it has something to validate.
	 */
	public static function register_bulk_action() {
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->post_type || ! self::has_waiting( $screen->post_type ) ) {
			return;
		}

		add_filter( 'bulk_actions-' . $screen->id, array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-' . $screen->id, array( __CLASS__, 'handle_list_bulk_action' ), 10, 3 );
	}

	/**
	 * Whether a post type has AI drafts or pending changes.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private static function has_waiting( $post_type ) {
		if ( Eotools_Mcp_Ai_Drafts::has_waiting( $post_type ) ) {
			return true;
		}

		return (bool) get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => Eotools_Mcp_Proposals::STATUS,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
	}

	/**
	 * @param string[] $actions Bulk actions.
	 * @return string[]
	 */
	public static function bulk_actions( $actions ) {
		$actions[ self::LIST_ACTION ] = __( 'Valider propositions IA', 'eo-tools' );

		return $actions;
	}

	/**
	 * Validates the selected posts. Posts without anything to validate are ignored.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Bulk action.
	 * @param int[]  $post_ids Selected posts.
	 * @return string
	 */
	public static function handle_list_bulk_action( $redirect, $action, $post_ids ) {
		if ( self::LIST_ACTION !== $action ) {
			return $redirect;
		}

		$posts = array_filter( array_map( 'get_post', $post_ids ) );

		// Parents first, so that sub-pages are published under a published page.
		usort(
			$posts,
			function ( $a, $b ) {
				return count( get_post_ancestors( $a ) ) - count( get_post_ancestors( $b ) );
			}
		);

		$validated = 0;
		$failed    = 0;
		foreach ( $posts as $post ) {
			if ( ! self::can_validate( $post ) ) {
				continue;
			}
			if ( self::validate( $post ) ) {
				++$validated;
			} else {
				++$failed;
			}
		}

		return add_query_arg(
			array(
				self::ARG_VALIDATED => $validated,
				self::ARG_FAILED    => $failed,
			),
			$redirect
		);
	}

	/**
	 * Result of the row / bulk action, displayed above the list.
	 */
	public static function render_list_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( ! isset( $_GET[ self::ARG_VALIDATED ] ) ) {
			return;
		}
		$validated = absint( $_GET[ self::ARG_VALIDATED ] );
		$failed    = isset( $_GET[ self::ARG_FAILED ] ) ? absint( $_GET[ self::ARG_FAILED ] ) : 0;
		// phpcs:enable

		if ( $validated ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of validated AI proposals. */
				esc_html( sprintf( _n( '%d proposition IA validée.', '%d propositions IA validées.', $validated, 'eo-tools' ), $validated ) )
			);
		}

		if ( $failed ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of AI proposals not validated. */
				esc_html( sprintf( _n( '%d proposition IA n\'a pas pu être validée.', '%d propositions IA n\'ont pas pu être validées.', $failed, 'eo-tools' ), $failed ) )
			);
		}

		if ( ! $validated && ! $failed ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'Aucun des contenus sélectionnés n\'avait de proposition IA à valider.', 'eo-tools' )
			);
		}
	}

	/**
	 * @param string[] $args Query args removed from the URL after display.
	 * @return string[]
	 */
	public static function removable_query_args( $args ) {
		$args[] = self::ARG_VALIDATED;
		$args[] = self::ARG_FAILED;

		return $args;
	}

	/**
	 * @param int[] $result Running totals.
	 * @param mixed $outcome Result of an apply / delete call.
	 */
	private static function count( &$result, $outcome ) {
		if ( ! $outcome || is_wp_error( $outcome ) ) {
			++$result['failed'];
		} else {
			++$result['done'];
		}
	}

	/**
	 * @param int[] $result Running totals.
	 * @param int[] $more   Totals to add.
	 */
	private static function add( &$result, $more ) {
		$result['done']   += $more['done'];
		$result['failed'] += $more['failed'];
	}
}
