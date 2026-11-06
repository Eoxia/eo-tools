<?php
/**
 * Drafts created by an AI, validated in bulk by a human.
 *
 * Every content created through MCP is marked. The "Brouillons IA" tab of
 * EO Tools > MCP lists the ones still waiting (draft or pending) so that a
 * human can publish or trash them in one click. The AI itself never publishes.
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

	const NONCE_ACTION = 'eo_tools_ai_drafts';

	/**
	 * Statuses of a content still waiting for a human.
	 */
	const WAITING_STATUSES = array( 'draft', 'pending' );

	const MAX_LISTED = 300;

	public static function init() {
		add_action( 'admin_post_eo_tools_ai_drafts', array( __CLASS__, 'handle_bulk' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
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
			$created = (int) get_post_meta( $post->ID, self::META_CREATED, true );
			$day     = wp_date( 'Y-m-d', $created ? $created : strtotime( $post->post_date_gmt . ' UTC' ) );

			$groups[ $day ][] = $post;
		}

		return $groups;
	}

	/**
	 * Local creation time of an AI-created content.
	 *
	 * @param \WP_Post $post Post.
	 * @return int Timestamp.
	 */
	public static function get_created( $post ) {
		return (int) get_post_meta( $post->ID, self::META_CREATED, true );
	}

	/**
	 * URL of the "Brouillons IA" tab.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function admin_url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=eo-tools-mcp&tab=drafts' ) );
	}

	/* ---------------------------------------------------------------------
	 * Human validation
	 * ------------------------------------------------------------------ */

	/**
	 * Publishes or trashes the selected AI drafts (human action from the admin tab).
	 */
	public static function handle_bulk() {
		check_admin_referer( self::NONCE_ACTION );

		$action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$ids    = isset( $_POST['drafts'] ) ? array_unique( array_map( 'absint', (array) wp_unslash( $_POST['drafts'] ) ) ) : array();

		if ( ! in_array( $action, array( 'publish', 'trash' ), true ) || ! $ids ) {
			wp_safe_redirect( self::admin_url( array( 'done' => 'none' ) ) );
			exit;
		}

		$posts = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post && self::is_ai_draft( $post ) ) {
				$posts[] = $post;
			}
		}

		$result = 'publish' === $action ? self::publish( $posts ) : self::trash( $posts );

		wp_safe_redirect(
			self::admin_url(
				array(
					'done'   => 'publish' === $action ? 'published' : 'trashed',
					'count'  => $result['done'],
					'failed' => $result['failed'] + count( $ids ) - count( $posts ),
				)
			)
		);
		exit;
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
			$type = get_post_type_object( $post->post_type );
			if ( ! $type || ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( $type->cap->publish_posts ) ) {
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

	/**
	 * Adds "Créé par l'IA" next to the title of AI drafts in admin lists.
	 *
	 * @param string[] $states Post states.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public static function post_states( $states, $post ) {
		if ( in_array( $post->post_status, self::WAITING_STATUSES, true ) && '' !== get_post_meta( $post->ID, self::META_CREATED, true ) ) {
			$states['eo_tools_ai_draft'] = __( 'Créé par l\'IA', 'eo-tools' );
		}

		return $states;
	}
}
