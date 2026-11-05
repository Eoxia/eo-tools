<?php
/**
 * Ability: search the code of the themes and plugins.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Search_Source extends Eotools_Mcp_Ability_Snippet {

	public function get_slug() {
		return 'search-source';
	}

	public function get_label() {
		return __( 'Chercher dans le code du thème et des extensions', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Cherche un texte dans le code d\'un dossier de thème ou d\'extension (sans tenir compte de la casse), par exemple "do_action( \'woocommerce_" pour trouver des hooks, ou une classe CSS. Renvoie les correspondances fichier:ligne.', 'eo-tools' );
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'path'  => array(
					'type'        => 'string',
					'description' => __( 'Dossier à partir de wp-content, par exemple plugins/contact-form-7.', 'eo-tools' ),
				),
				'query' => array( 'type' => 'string' ),
			),
			'required'             => array( 'path', 'query' ),
			'additionalProperties' => false,
		);
	}

	public function execute( $input ) {
		return Eotools_Mcp_Source::search( $input );
	}
}
