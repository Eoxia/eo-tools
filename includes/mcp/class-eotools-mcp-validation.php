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

	public static function init() {
		add_action( 'admin_post_eo_tools_bulk_validate', array( __CLASS__, 'handle_bulk' ) );
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
