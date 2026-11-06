<?php
/**
 * Drafts created by an AI, validated by a human.
 *
 * Every content created through MCP is marked. The ones still waiting (draft
 * or pending) are listed in the "À valider" tab of EO Tools > MCP, where a
 * human publishes or trashes them in bulk, and get a quick "Valider" link in
 * the Pages / Posts lists. The AI itself never publishes.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ai_Drafts {

	/**
	 * Post meta holding the creation time (GMT timestamp) of an AI-created content.
	 */
	const META_CREATED = '_eo_tools_ai_created';

	/**
	 * Statuses of a content still waiting for a human.
	 */
	const WAITING_STATUSES = array( 'draft', 'pending' );

	const MAX_LISTED = 300;

	const QUICK_ACTION = 'eo_tools_publish_ai_draft';
	const QUICK_ARG    = 'eo_tools_ai_published';

	public static function init() {
		add_action( 'admin_post_' . self::QUICK_ACTION, array( __CLASS__, 'handle_quick_publish' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'render_quick_notice' ) );
		add_filter( 'removable_query_args', array( __CLASS__, 'removable_query_args' ) );
	}

	/**
	 * Marks a content as created by an AI.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function mark( $post_id ) {
		update_post_meta( $post_id, self::META_CREATED, time() );
	}

	/**
	 * @param \WP_Post|int $post Post.
	 * @return bool
	 */
	public static function is_ai_draft( $post ) {
		$post = get_post( $post );

		return $post && in_array( $post->post_status, self::WAITING_STATUSES, true ) && '' !== get_post_meta( $post->ID, self::META_CREATED, true );
	}

	/**
	 * AI-created contents still waiting, that the current user can edit, newest first.
	 *
	 * @return \WP_Post[]
	 */
	public static function get_waiting() {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => self::WAITING_STATUSES,
				'posts_per_page' => self::MAX_LISTED,
				'meta_key'       => self::META_CREATED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			)
		);

		return array_values(
			array_filter(
				$posts,
				function ( $post ) {
					return current_user_can( 'edit_post', $post->ID );
				}
			)
		);
	}

	/**
	 * Groups contents by local creation day, newest day first.
	 *
	 * @param \WP_Post[] $posts Posts sorted newest first.
	 * @return array[] Lists of posts keyed by day (Y-m-d).
	 */
	public static function group_by_day( $posts ) {
		$groups = array();

		foreach ( $posts as $post ) {
			$created = self::get_created( $post );
			$day     = wp_date( 'Y-m-d', $created ? $created : strtotime( $post->post_date_gmt . ' UTC' ) );

			$groups[ $day ][] = $post;
		}

		return $groups;
	}

	/**
	 * Creation time of an AI-created content.
	 *
	 * @param \WP_Post $post Post.
	 * @return int Timestamp.
	 */
	public static function get_created( $post ) {
		return (int) get_post_meta( $post->ID, self::META_CREATED, true );
	}

	/**
	 * Whether the current user may publish this content.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function can_publish( $post ) {
		$type = get_post_type_object( $post->post_type );

		return $type && current_user_can( 'edit_post', $post->ID ) && current_user_can( $type->cap->publish_posts );
	}

	/**
	 * Publishes AI drafts, parents before their children.
	 *
	 * The publication date is the current time (WordPress resets the date of
	 * a draft whose date was never set).
	 *
	 * @param \WP_Post[] $posts AI drafts.
	 * @return int[] Keys "done" and "failed".
	 */
	public static function publish( $posts ) {
		usort(
			$posts,
			function ( $a, $b ) {
				return count( get_post_ancestors( $a ) ) - count( get_post_ancestors( $b ) );
			}
		);

		$result = array( 'done' => 0, 'failed' => 0 );

		foreach ( $posts as $post ) {
			if ( ! self::can_publish( $post ) ) {
				++$result['failed'];
				continue;
			}

			$updated = wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				++$result['failed'];
			} else {
				++$result['done'];
			}
		}

		return $result;
	}

	/**
	 * Moves AI drafts to the trash (they can still be restored from there).
	 *
	 * @param \WP_Post[] $posts AI drafts.
	 * @return int[] Keys "done" and "failed".
	 */
	public static function trash( $posts ) {
		$result = array( 'done' => 0, 'failed' => 0 );

		foreach ( $posts as $post ) {
			if ( current_user_can( 'delete_post', $post->ID ) && wp_trash_post( $post->ID ) ) {
				++$result['done'];
			} else {
				++$result['failed'];
			}
		}

		return $result;
	}

	/* ---------------------------------------------------------------------
	 * Pages / Posts lists
	 * ------------------------------------------------------------------ */

	/**
	 * Adds "Créé par l'IA" next to the title of AI drafts, with a quick "Valider" link in the lists.
	 *
	 * @param string[] $states Post states.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public static function post_states( $states, $post ) {
		if ( ! in_array( $post->post_status, self::WAITING_STATUSES, true ) || '' === get_post_meta( $post->ID, self::META_CREATED, true ) ) {
			return $states;
		}

		$state = esc_html__( 'Créé par l\'IA', 'eo-tools' );

		// The states are displayed as HTML only in the Pages / Posts lists: elsewhere they are escaped.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'edit' === $screen->base && self::can_publish( $post ) ) {
			$state .= sprintf(
				' <a href="%1$s" class="button button-small eo-tools-ai-validate" aria-label="%2$s">%3$s</a>',
				esc_url( self::quick_publish_url( $post ) ),
				/* translators: %s: post title. */
				esc_attr( sprintf( __( 'Publier « %s »', 'eo-tools' ), get_the_title( $post ) ) ),
				esc_html__( 'Valider', 'eo-tools' )
			);
		}

		$states['eo_tools_ai_draft'] = $state;

		return $states;
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function quick_publish_url( $post ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::QUICK_ACTION,
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::QUICK_ACTION . '_' . $post->ID
		);
	}

	/**
	 * Publishes one AI draft from the "Valider" link, then goes back to the list.
	 */
	public static function handle_quick_publish() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( self::QUICK_ACTION . '_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || ! self::is_ai_draft( $post ) ) {
			wp_die( esc_html__( 'Ce brouillon n\'existe plus ou a déjà été publié.', 'eo-tools' ), '', array( 'response' => 404, 'back_link' => true ) );
		}
		if ( ! self::can_publish( $post ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas le droit de publier ce contenu.', 'eo-tools' ), '', array( 'response' => 403 ) );
		}

		$result = self::publish( array( $post ) );
		if ( ! $result['done'] ) {
			wp_die( esc_html__( 'Le contenu n\'a pas pu être publié.', 'eo-tools' ), '', array( 'back_link' => true ) );
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'edit.php?post_type=' . $post->post_type );
		}

		wp_safe_redirect( add_query_arg( self::QUICK_ARG, $post->ID, $back ) );
		exit;
	}

	/**
	 * Confirms the quick publication in the list.
	 */
	public static function render_quick_notice() {
		$post_id = isset( $_GET[ self::QUICK_ARG ] ) ? absint( $_GET[ self::QUICK_ARG ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s" target="_blank">%s</a></p></div>',
			/* translators: %s: post title. */
			esc_html( sprintf( __( '« %s » est publié.', 'eo-tools' ), get_the_title( $post ) ) ),
			esc_url( get_permalink( $post ) ),
			esc_html__( 'Voir', 'eo-tools' )
		);
	}

	/**
	 * @param string[] $args Query args removed from the URL after display.
	 * @return string[]
	 */
	public static function removable_query_args( $args ) {
		$args[] = self::QUICK_ARG;

		return $args;
	}
}
