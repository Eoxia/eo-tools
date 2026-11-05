<?php
/**
 * Ability: list the site templates and template parts.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_List_Templates extends Eotools_Mcp_Ability_Template {

	public function get_slug() {
		return 'list-templates';
	}

	public function get_label() {
		return __( 'Lister les modèles du site', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Liste les modèles du thème (page, article, archive, accueil…) et les parties de modèle (en-tête, pied de page…), avec leur origine : fichier du thème ("theme") ou version personnalisée ("custom"). Un modèle s\'applique à toutes les pages qui l\'utilisent.', 'eo-tools' );
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'type' => array(
					'type' => 'string',
					'enum' => array( 'template', 'template_part' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function execute( $input ) {
		$error = $this->check_support();
		if ( $error ) {
			return $error;
		}

		$templates = array();
		foreach ( Eotools_Mcp_Templates::get_all( isset( $input['type'] ) ? $input['type'] : '' ) as $template ) {
			$templates[] = Eotools_Mcp_Templates::describe( $template );
		}

		return array(
			'theme'     => get_stylesheet(),
			'templates' => $templates,
		);
	}
}
