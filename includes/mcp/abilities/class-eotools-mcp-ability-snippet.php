<?php
/**
 * Base class of the code abilities (snippets and theme / plugin source).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Eotools_Mcp_Ability_Snippet extends Eotools_Mcp_Ability {

	public function get_group() {
		return 'code';
	}

	public function has_permission( $input ) {
		return Eotools_Mcp_Snippets::user_can();
	}
}
