<?php
/**
 * Ability: activate a plugin, under a temporary authorization given by a human.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Elevation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Activate_Plugin extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'activate-plugin';
	}

	public function get_label() {
		return __( 'Activer une extension (avec autorisation)', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Active une extension installée, uniquement si un administrateur a validé une demande eo-tools/request-elevation de cette connexion qui la contient, et avant la fin du délai. Sans autorisation validée, l\'activation est refusée et la réponse indique quoi faire. Si l\'extension provoque une erreur à l\'activation, elle reste désactivée. Prévenez l\'utilisateur de chaque extension activée.', 'eo-tools' );
	}

	public function get_group() {
		return 'plugins';
	}

	public function get_risk() {
		return self::RISK_SENSITIVE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'slug' => array(
					'type'        => 'string',
					'description' => __( 'Identifiant ("slug") de l\'extension, ex. "contact-form-7".', 'eo-tools' ),
				),
			),
			'required'             => array( 'slug' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'activate_plugins' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Elevation::activate_plugin( $input['slug'] );
	}
}
