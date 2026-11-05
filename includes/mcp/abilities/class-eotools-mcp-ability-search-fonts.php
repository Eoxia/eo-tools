<?php
/**
 * Ability: search the Google Fonts collection.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Fonts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Search_Fonts extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'search-fonts';
	}

	public function get_label() {
		return __( 'Chercher des polices', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Cherche une police dans la collection Google Fonts de WordPress, par nom et / ou catégorie, avec ses graisses disponibles et si elle est déjà installée. Les polices sont gratuites et, une fois installées, hébergées sur le site.', 'eo-tools' );
	}

	public function get_group() {
		return 'styles';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'query'    => array(
					'type'        => 'string',
					'description' => __( 'Nom ou partie du nom (ex. "playfair").', 'eo-tools' ),
				),
				'category' => array(
					'type' => 'string',
					'enum' => array( 'sans-serif', 'serif', 'display', 'handwriting', 'monospace' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'edit_theme_options' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Fonts::search( $input );
	}
}
