<?php
/**
 * Ability: read the code of the themes and plugins.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Read_Source extends Eotools_Mcp_Ability_Snippet {

	public function get_slug() {
		return 'read-source';
	}

	public function get_label() {
		return __( 'Lire le code du thème et des extensions', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Accès en lecture seule au code des thèmes et extensions installés, pour trouver les bons hooks, classes CSS et fonctions avant d\'écrire un snippet. Donnez un dossier pour le lister ("plugins/woocommerce/includes") ou un fichier pour le lire (par plage de lignes pour les longs fichiers). Les chemins partent de wp-content et commencent par themes/ ou plugins/ ; sans "path", renvoie le thème actif et les extensions actives.', 'eo-tools' );
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
					'description' => __( 'Par exemple themes/beflex/style.css.', 'eo-tools' ),
				),
				'start' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'default' => 1,
				),
				'lines' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 800,
					'default' => 400,
				),
			),
			'additionalProperties' => false,
		);
	}

	public function execute( $input ) {
		return Eotools_Mcp_Source::read( $input );
	}
}
