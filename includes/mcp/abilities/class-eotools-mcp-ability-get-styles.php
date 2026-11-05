<?php
/**
 * Ability: read the global styles of the site.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Get_Styles extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'get-styles';
	}

	public function get_label() {
		return __( 'Lire les styles du site', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Renvoie les styles globaux du site (Éditeur de site > Styles) : palette de couleurs, dégradés, polices, tailles de texte, espacements, largeurs de contenu, styles du site et des éléments (liens, titres, boutons), avec l\'origine de chaque valeur ("default" WordPress, "theme" ou "custom" personnalisé). "user_changes" contient ce qui a été personnalisé, "variations" les styles prêts à l\'emploi du thème, "installed_fonts" les polices installées. Avec "include_blocks", ajoute les styles de chaque bloc.', 'eo-tools' );
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
				'include_blocks' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( Eotools_Mcp_Styles::CAPABILITY );
	}

	public function execute( $input ) {
		if ( ! Eotools_Mcp_Styles::is_supported() ) {
			return new \WP_Error( 'eo_tools_styles_unsupported', __( 'Le thème actif n\'utilise pas de styles globaux (theme.json).', 'eo-tools' ) );
		}

		return Eotools_Mcp_Styles::describe( ! empty( $input['include_blocks'] ) );
	}
}
