<?php
/**
 * Ability: read a code snippet.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Get_Snippet extends Eotools_Mcp_Ability_Snippet {

	public function get_slug() {
		return 'get-snippet';
	}

	public function get_label() {
		return __( 'Lire un snippet', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Renvoie un snippet avec son code, et sa nouvelle version en attente s\'il en a une.', 'eo-tools' );
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	public function execute( $input ) {
		$snippet = Eotools_Mcp_Snippets::get( (int) $input['id'] );

		return $snippet ? $snippet : new \WP_Error( 'eo_tools_snippet_not_found', __( 'Aucun snippet avec cet identifiant.', 'eo-tools' ) );
	}
}
