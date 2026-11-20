<?php
/**
 * Proposal types of other plugins, validated in the "À valider" tab.
 *
 * Another plugin whose abilities prepare changes that a human must validate
 * (for example WPshop: products to create in Dolibarr) declares a proposal type
 * with the `eo_tools_mcp_proposal_types` filter. EO Tools lists its pending items
 * in the "À valider" tab, next to its own proposals, and calls the plugin back
 * to apply or reject them, one by one or in bulk. The items stay stored by the
 * plugin: EO Tools only displays, calls and counts.
 *
 * No ability can validate: only a human, from the tab.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Proposal_Types {

	/**
	 * Bulk form field: ext[<type>][] = item IDs.
	 */
	const FIELD = 'ext';

	/**
	 * Error messages of the last validation, per user, shown once in the tab.
	 */
	const ERRORS_TRANSIENT = 'eo_tools_mcp_ext_errors_';

	/**
	 * @var array[]|null Validated types, keyed by type ID.
	 */
	private static $types = null;

	public static function init() {
		add_action( 'admin_post_eo_tools_apply_ext_proposal', array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_post_eo_tools_reject_ext_proposal', array( __CLASS__, 'handle_reject' ) );
	}

	/**
	 * Declared proposal types.
	 *
	 * @return array[] Keyed by type ID.
	 */
	public static function get_types() {
		if ( null !== self::$types ) {
			return self::$types;
		}

		/**
		 * Filters the proposal types of other plugins, validated in the "À valider" tab.
		 *
		 * Each type, keyed by a unique ID (e.g. "wpshop-dolibarr-product"):
		 *
		 * - label       (string, required) Section title, e.g. "Produits à créer dans Dolibarr".
		 * - description (string)           Shown under the title.
		 * - provider    (string)           Name of the plugin, shown next to the title.
		 * - capability  (string)           Capability needed to see and validate. Default "manage_options".
		 * - items       (callable, required) Returns the pending items. Each item is an array:
		 *       id      (string|int, required) Unique within the type.
		 *       title   (string, required)
		 *       details (string) HTML (filtered with wp_kses_post), e.g. the fields to create.
		 *       created (int)    Timestamp.
		 *       warning (string) Shown as a warning badge, e.g. "Doublon possible".
		 *       link    (string) URL of a preview or of the related object.
		 *       link_label (string)
		 * - apply       (callable, required) Receives the item ID, returns true or a WP_Error.
		 * - reject      (callable, required) Receives the item ID, returns true or a WP_Error.
		 *
		 * @param array[] $types Proposal types keyed by ID.
		 */
		$types = (array) apply_filters( 'eo_tools_mcp_proposal_types', array() );

		self::$types = array();
		foreach ( $types as $type_id => $type ) {
			$type_id = sanitize_key( $type_id );
			if ( '' === $type_id || ! is_array( $type ) || empty( $type['label'] ) || ! isset( $type['items'], $type['apply'], $type['reject'] )
				|| ! is_callable( $type['items'] ) || ! is_callable( $type['apply'] ) || ! is_callable( $type['reject'] ) ) {
				continue;
			}

			self::$types[ $type_id ] = array(
				'label'       => (string) $type['label'],
				'description' => isset( $type['description'] ) ? (string) $type['description'] : '',
				'provider'    => isset( $type['provider'] ) ? (string) $type['provider'] : '',
				'capability'  => ! empty( $type['capability'] ) ? (string) $type['capability'] : 'manage_options',
				'items'       => $type['items'],
				'apply'       => $type['apply'],
				'reject'      => $type['reject'],
			);
		}

		return self::$types;
	}

	/**
	 * @param string $type_id Type ID.
	 * @return array|null
	 */
	public static function get_type( $type_id ) {
		$types = self::get_types();

		return isset( $types[ $type_id ] ) ? $types[ $type_id ] : null;
	}

	/**
	 * @param string $type_id Type ID.
	 * @return bool
	 */
	public static function user_can( $type_id ) {
		$type = self::get_type( $type_id );

		return $type && current_user_can( $type['capability'] );
	}

	/**
	 * Pending items of a type, normalized. Empty if the user cannot validate them.
	 *
	 * @param string $type_id Type ID.
	 * @return array[] Keyed by item ID (string).
	 */
	public static function get_items( $type_id ) {
		if ( ! self::user_can( $type_id ) ) {
			return array();
		}

		$type  = self::get_type( $type_id );
		$items = array();
		foreach ( (array) call_user_func( $type['items'] ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['id'], $item['title'] ) || '' === (string) $item['id'] ) {
				continue;
			}

			$id           = (string) $item['id'];
			$items[ $id ] = array(
				'id'         => $id,
				'title'      => (string) $item['title'],
				'details'    => isset( $item['details'] ) ? (string) $item['details'] : '',
				'created'    => isset( $item['created'] ) ? (int) $item['created'] : 0,
				'warning'    => isset( $item['warning'] ) ? (string) $item['warning'] : '',
				'link'       => isset( $item['link'] ) ? (string) $item['link'] : '',
				'link_label' => isset( $item['link_label'] ) ? (string) $item['link_label'] : '',
			);
		}

		return $items;
	}

	/**
	 * Pending items of every type the user can validate.
	 *
	 * @return array[] Keyed by type ID: array( 'type' => array, 'items' => array[] ). Types without items are left out.
	 */
	public static function get_all_pending() {
		$pending = array();
		foreach ( self::get_types() as $type_id => $type ) {
			$items = self::get_items( $type_id );
			if ( $items ) {
				$pending[ $type_id ] = array(
					'type'  => $type,
					'items' => $items,
				);
			}
		}

		return $pending;
	}

	/**
	 * Number of pending items the user can validate.
	 *
	 * @param array[]|null $pending Result of get_all_pending(), if already loaded.
	 * @return int
	 */
	public static function count_pending( $pending = null ) {
		$count = 0;
		foreach ( null === $pending ? self::get_all_pending() : $pending as $group ) {
			$count += count( $group['items'] );
		}

		return $count;
	}

	/**
	 * Applies or rejects an item, through its plugin.
	 *
	 * @param string $type_id Type ID.
	 * @param string $item_id Item ID.
	 * @param bool   $apply   True to apply, false to reject.
	 * @return true|\WP_Error
	 */
	public static function process( $type_id, $item_id, $apply ) {
		$items = self::get_items( $type_id );
		if ( ! isset( $items[ (string) $item_id ] ) ) {
			return new \WP_Error( 'eo_tools_ext_proposal_missing', __( 'Cette proposition n\'existe plus, ou vous n\'avez pas le droit de la valider.', 'eo-tools' ) );
		}

		$type   = self::get_type( $type_id );
		$result = call_user_func( $apply ? $type['apply'] : $type['reject'], (string) $item_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( true !== $result ) {
			return new \WP_Error( 'eo_tools_ext_proposal_failed', __( 'L\'extension n\'a pas pu traiter cette proposition.', 'eo-tools' ) );
		}

		return true;
	}

	/**
	 * Selected items of the bulk form.
	 *
	 * @return array[] Item IDs keyed by type ID.
	 */
	public static function get_selected() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by the caller (bulk form).
		$posted = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array();

		$selected = array();
		foreach ( $posted as $type_id => $ids ) {
			$type_id = sanitize_key( $type_id );
			if ( self::get_type( $type_id ) ) {
				$selected[ $type_id ] = array_unique( array_filter( array_map( 'sanitize_text_field', (array) $ids ), 'strlen' ) );
			}
		}

		return array_filter( $selected );
	}

	/**
	 * Remembers an error message, shown once in the tab.
	 *
	 * @param string $title   Item title.
	 * @param string $message Error message.
	 */
	public static function add_error( $title, $message ) {
		$key      = self::ERRORS_TRANSIENT . get_current_user_id();
		$errors   = array_values( array_filter( (array) get_transient( $key ) ) );
		$errors[] = $title ? $title . ' : ' . $message : $message;
		set_transient( $key, $errors, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Error messages of the last validation, then forgets them.
	 *
	 * @return string[]
	 */
	public static function pop_errors() {
		$key    = self::ERRORS_TRANSIENT . get_current_user_id();
		$errors = array_values( array_filter( (array) get_transient( $key ) ) );
		delete_transient( $key );

		return $errors;
	}

	/**
	 * Nonce action of the Apply / Reject buttons of an item.
	 *
	 * @param string $type_id Type ID.
	 * @param string $item_id Item ID.
	 * @return string
	 */
	public static function nonce_action( $type_id, $item_id ) {
		return 'eo_tools_ext_proposal_' . $type_id . '_' . $item_id;
	}

	public static function handle_apply() {
		self::handle_single( true );
	}

	public static function handle_reject() {
		self::handle_single( false );
	}

	/**
	 * Apply / Reject button of one item.
	 *
	 * @param bool $apply True to apply, false to reject.
	 */
	private static function handle_single( $apply ) {
		$type_id = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$item_id = isset( $_POST['item'] ) ? sanitize_text_field( wp_unslash( $_POST['item'] ) ) : '';
		check_admin_referer( self::nonce_action( $type_id, $item_id ) );

		$items  = self::get_items( $type_id );
		$title  = isset( $items[ $item_id ] ) ? $items[ $item_id ]['title'] : '';
		$result = self::process( $type_id, $item_id, $apply );

		if ( is_wp_error( $result ) ) {
			self::add_error( $title, $result->get_error_message() );
			wp_safe_redirect( add_query_arg( 'failed', 1, Eotools_Mcp_Proposals::admin_url() ) );
			exit;
		}

		wp_safe_redirect( Eotools_Mcp_Proposals::admin_url( $apply ? 'ext-applied' : 'rejected' ) );
		exit;
	}
}
