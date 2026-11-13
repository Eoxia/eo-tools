<?php
/**
 * Ability: change WordPress settings (Settings menu).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Update_Settings extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'update-settings';
	}

	public function get_label() {
		return __( 'Modifier les réglages WordPress', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Modifie un ou plusieurs réglages du menu Réglages de WordPress, appliqués immédiatement sur le site en ligne. Lisez d\'abord les réglages avec eo-tools/get-settings pour connaître les noms techniques et les valeurs possibles. Présentez à l\'utilisateur les changements prévus et obtenez son accord avant de les appliquer, surtout pour l\'indexation (blog_public), les permaliens (les adresses des contenus changent), la page d\'accueil et les inscriptions. Si une valeur est refusée, rien n\'est modifié. Les adresses du site (siteurl, home) ne sont pas modifiables ; une nouvelle adresse e-mail d\'administration n\'est appliquée qu\'après confirmation par e-mail ; le rôle par défaut ne peut pas être un rôle d\'administration. La réponse donne l\'ancienne valeur de chaque réglage, pour pouvoir revenir en arrière.', 'eo-tools' );
	}

	public function get_group() {
		return 'settings';
	}

	public function get_risk() {
		return self::RISK_SENSITIVE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'settings' => array(
					'type'                 => 'object',
					'description'          => __( 'Nouvelles valeurs, par nom technique. Ex. {"blogname": "Mon site", "timezone_string": "Europe/Paris", "blog_public": true, "permalink_structure": "/%postname%/"}.', 'eo-tools' ),
					'minProperties'        => 1,
					'additionalProperties' => array(
						'type' => array( 'string', 'integer', 'number', 'boolean' ),
					),
				),
			),
			'required'             => array( 'settings' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Settings::update( $input['settings'] );
	}
}
