<?php
/**
 * SEO changes prepared by the AI that are not contents: 301 redirections and
 * SEO fields of taxonomy terms (categories, tags…).
 *
 * Like the other proposals, they wait in the "À valider" tab of EO Tools >
 * MCP and only a human applies them. They are small, so they live in one
 * option instead of posts.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Seo_Changes {

	const OPTION   = 'eo_tools_mcp_seo_changes';
	const LIFETIME = 30 * DAY_IN_SECONDS;
	const MAX      = 500;

	const KIND_REDIRECTION = 'redirection';
	const KIND_TERM        = 'term';

	public static function init() {
		add_action( 'admin_post_eo_tools_apply_seo_change', array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_post_eo_tools_reject_seo_change', array( __CLASS__, 'handle_reject' ) );
	}

	/**
	 * @return array Keys next_id and items (keyed by ID).
	 */
	private static function load() {
		$store = get_option( self::OPTION, array() );
		if ( ! is_array( $store ) || ! isset( $store['items'] ) ) {
			$store = array(
				'next_id' => 1,
				'items'   => array(),
			);
		}

		return $store;
	}

	/**
	 * @param array $store Store.
	 */
	private static function save( $store ) {
		update_option( self::OPTION, $store, false );
	}

	/**
	 * Pending changes, newest first. Changes older than their lifetime are dropped.
	 *
	 * @param string $kind KIND_* constant, or empty for all.
	 * @return array[]
	 */
	public static function get_all( $kind = '' ) {
		$store = self::load();
		$limit = time() - self::LIFETIME;
		$items = array();
		$old   = false;

		foreach ( $store['items'] as $id => $item ) {
			if ( $item['created'] < $limit ) {
				unset( $store['items'][ $id ] );
				$old = true;
				continue;
			}
			if ( '' === $kind || $kind === $item['kind'] ) {
				$items[] = $item;
			}
		}
		if ( $old ) {
			self::save( $store );
		}

		usort(
			$items,
			function ( $a, $b ) {
				return $b['created'] - $a['created'];
			}
		);

		return $items;
	}

	/**
	 * Pending changes the current user may apply.
	 *
	 * @return array[]
	 */
	public static function get_all_for_user() {
		return array_values( array_filter( self::get_all(), array( __CLASS__, 'user_can' ) ) );
	}

	/**
	 * @param int $id Change ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$store = self::load();

		return isset( $store['items'][ $id ] ) ? $store['items'][ $id ] : null;
	}

	/**
	 * Adds a change, or replaces the pending change of the same target.
	 *
	 * @param string $kind KIND_* constant.
	 * @param string $key  Target (source address, taxonomy:term_id).
	 * @param array  $data Change.
	 * @return array|\WP_Error The stored change.
	 */
	public static function add( $kind, $key, $data ) {
		$store = self::load();

		foreach ( $store['items'] as $id => $item ) {
			if ( $item['kind'] === $kind && $item['key'] === $key ) {
				unset( $store['items'][ $id ] );
			}
		}
		if ( count( $store['items'] ) >= self::MAX ) {
			return new \WP_Error( 'eo_tools_seo_changes_full', __( 'Trop de modifications SEO attendent une validation : demandez à l\'utilisateur de traiter celles en attente (EO Tools > MCP > À valider).', 'eo-tools' ) );
		}

		$item = array(
			'id'      => (int) $store['next_id'],
			'kind'    => $kind,
			'key'     => $key,
			'data'    => $data,
			'created' => time(),
			'user'    => get_current_user_id(),
		);

		$store['items'][ $item['id'] ] = $item;
		$store['next_id']              = $item['id'] + 1;
		self::save( $store );

		return $item;
	}

	/**
	 * Pending change of a target.
	 *
	 * @param string $kind KIND_* constant.
	 * @param string $key  Target.
	 * @return array|null
	 */
	public static function find( $kind, $key ) {
		foreach ( self::get_all( $kind ) as $item ) {
			if ( $item['key'] === $key ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * @param int $id Change ID.
	 */
	public static function remove( $id ) {
		$store = self::load();
		unset( $store['items'][ $id ] );
		self::save( $store );
	}

	/**
	 * Whether the current user may apply or reject a change.
	 *
	 * @param array $item Change.
	 * @return bool
	 */
	public static function user_can( $item ) {
		if ( self::KIND_TERM === $item['kind'] ) {
			return current_user_can( 'edit_term', (int) $item['data']['term_id'] );
		}

		// Same right as the EO Tools > 404-Erreur screen.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Applies a change (human action), then removes it.
	 *
	 * @param array $item Change.
	 * @return true|\WP_Error
	 */
	public static function apply( $item ) {
		if ( self::KIND_REDIRECTION === $item['kind'] ) {
			$result = Eotools_Mcp_Redirections::save( $item['data']['from'], $item['data']['to'] );
		} else {
			$result = self::apply_term( $item['data'] );
		}

		if ( ! is_wp_error( $result ) ) {
			self::remove( $item['id'] );
			// The audits must see the change.
			Eotools_Mcp_Seo::clear_cache();
		}

		return $result;
	}

	/**
	 * @param array $data Term change: taxonomy, term_id, seo, archive_text.
	 * @return true|\WP_Error
	 */
	private static function apply_term( $data ) {
		$term = get_term( (int) $data['term_id'], $data['taxonomy'] );
		if ( ! $term instanceof \WP_Term ) {
			return new \WP_Error( 'eo_tools_term_missing', __( 'Ce terme n\'existe plus.', 'eo-tools' ) );
		}

		if ( isset( $data['archive_text'] ) ) {
			$updated = wp_update_term( $term->term_id, $term->taxonomy, array( 'description' => $data['archive_text'] ) );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			$term = get_term( $term->term_id, $term->taxonomy );
		}

		if ( ! empty( $data['seo'] ) ) {
			Eotools_Mcp_Seo::get_provider()->set_term_seo( $term, $data['seo'] );
		}

		return true;
	}

	/**
	 * Whether the term changed since the proposal (someone edited it meanwhile).
	 *
	 * @param array $item Term change.
	 * @return bool
	 */
	public static function has_conflict( $item ) {
		if ( self::KIND_TERM !== $item['kind'] ) {
			return false;
		}
		$term = get_term( (int) $item['data']['term_id'], $item['data']['taxonomy'] );
		if ( ! $term instanceof \WP_Term ) {
			return true;
		}

		return self::term_snapshot( $term ) !== $item['data']['before'];
	}

	/**
	 * Current values of a term, as shown before / after in the admin.
	 *
	 * @param \WP_Term $term Term.
	 * @return array Keys seo and archive_text.
	 */
	public static function term_snapshot( $term ) {
		return array(
			'seo'          => Eotools_Mcp_Seo::get_provider()->get_term_seo_values( $term ),
			'archive_text' => $term->description,
		);
	}

	/**
	 * Applies a change from the "À valider" tab.
	 */
	public static function handle_apply() {
		$item   = self::get_requested();
		$result = self::apply( $item );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Modification non appliquée', 'eo-tools' ), array( 'back_link' => true ) );
		}

		wp_safe_redirect( Eotools_Mcp_Proposals::admin_url( 'seo-applied' ) );
		exit;
	}

	/**
	 * Rejects a change from the "À valider" tab.
	 */
	public static function handle_reject() {
		$item = self::get_requested();
		self::remove( $item['id'] );

		wp_safe_redirect( Eotools_Mcp_Proposals::admin_url( 'rejected' ) );
		exit;
	}

	/**
	 * Checks the nonce and the rights of an apply / reject request.
	 *
	 * @return array
	 */
	private static function get_requested() {
		$id = isset( $_POST['change'] ) ? absint( $_POST['change'] ) : 0;
		check_admin_referer( 'eo_tools_seo_change_' . $id );

		$item = self::get( $id );
		if ( ! $item ) {
			wp_die( esc_html__( 'Cette modification n\'existe plus.', 'eo-tools' ), '', array( 'response' => 404, 'back_link' => true ) );
		}
		if ( ! self::user_can( $item ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas le droit d\'appliquer cette modification.', 'eo-tools' ), '', array( 'response' => 403 ) );
		}

		return $item;
	}
}
