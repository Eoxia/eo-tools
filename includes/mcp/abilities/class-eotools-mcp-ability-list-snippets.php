<?php
/**
 * Ability: list the code snippets.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_List_Snippets extends Eotools_Mcp_Ability_Snippet {

	public function get_slug() {
		return 'list-snippets';
	}

	public function get_label() {
		return __( 'Lister les snippets', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Liste les snippets de code du site (CSS, JS et PHP) avec leur état (actif, en attente d\'activation, nouvelle version en attente) et leur dernière erreur. À consulter avant d\'en proposer un : améliorez un snippet existant plutôt que d\'en créer un doublon. "php_allowed" et "js_allowed" indiquent si le PHP et le JS sont autorisés sur ce site.', 'eo-tools' );
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => (object) array(),
			'additionalProperties' => false,
		);
	}

	public function execute( $input ) {
		return array(
			'php_allowed' => Eotools_Mcp_Snippets::user_can( 'php' ),
			'js_allowed'  => Eotools_Mcp_Snippets::user_can( 'js' ),
			'snippets'    => Eotools_Mcp_Snippets::all(),
		);
	}
}
