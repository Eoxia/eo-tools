<?php
/**
 * Ability: list the available updates.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Check_Updates extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'check-updates';
	}

	public function get_label() {
		return __( 'Vérifier les mises à jour', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Liste les mises à jour disponibles : WordPress (version mineure ou majeure), extensions, thèmes et traductions, avec la compatibilité de chaque nouvelle version avec ce site et le lien vers son journal des modifications. Renvoie aussi les dernières mises à jour faites et, si besoin, la raison pour laquelle le site ne peut pas être mis à jour d\'ici. À appeler avant eo-tools/update-site, et pour présenter à l\'utilisateur ce qui va être mis à jour.', 'eo-tools' );
	}

	public function get_group() {
		return 'updates';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'refresh' => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => __( 'Interroger wordpress.org maintenant plutôt qu\'utiliser la dernière vérification.', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'update_plugins' ) || current_user_can( 'update_core' ) || current_user_can( 'update_themes' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Updates::get_available( ! isset( $input['refresh'] ) || $input['refresh'] );
	}
}
