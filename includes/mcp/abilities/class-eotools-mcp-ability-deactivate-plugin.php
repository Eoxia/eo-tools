<?php
/**
 * Ability: deactivate a plugin, under a temporary authorization given by a human.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Elevation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Deactivate_Plugin extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'deactivate-plugin';
	}

	public function get_label() {
		return __( 'Désactiver une extension (avec autorisation)', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Désactive une extension active, uniquement si un administrateur a validé une demande eo-tools/request-elevation de cette connexion qui la contient dans "deactivate", et avant la fin du délai. Sans autorisation validée, la désactivation est refusée et la réponse indique quoi faire. L\'extension n\'est pas supprimée : ses réglages sont conservés. Prévenez l\'utilisateur de chaque extension désactivée.', 'eo-tools' );
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
					'description' => __( 'Identifiant ("slug") de l\'extension, ex. "hello-dolly".', 'eo-tools' ),
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
		return Eotools_Mcp_Elevation::deactivate_plugin( $input['slug'] );
	}
}
