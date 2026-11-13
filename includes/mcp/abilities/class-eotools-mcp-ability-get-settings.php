<?php
/**
 * Ability: read the WordPress settings (Settings menu).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Get_Settings extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'get-settings';
	}

	public function get_label() {
		return __( 'Lire les réglages WordPress', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Lit les réglages du menu Réglages de WordPress (Général, Écriture, Lecture, Commentaires, Médias, Permaliens, Confidentialité) : nom technique, libellé, type, valeur actuelle et valeurs possibles. Utilisez-la avant eo-tools/update-settings, par exemple pour une checklist de mise en ligne (titre, slogan, fuseau horaire, langue, indexation par les moteurs de recherche, permaliens, page d\'accueil, page de confidentialité). Les champs "page" attendent l\'identifiant d\'une page (trouvé avec eo-tools/list-posts). L\'historique renvoie les dernières modifications faites par une IA, avec leur valeur précédente.', 'eo-tools' );
	}

	public function get_group() {
		return 'settings';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'section'     => array(
					'type'        => 'string',
					'enum'        => array_keys( Eotools_Mcp_Settings::get_sections() ),
					'description' => __( 'Écran de réglages à lire. Sans valeur : tous.', 'eo-tools' ),
				),
				'include_log' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Ajoute l\'historique des réglages modifiés par une IA.', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		$result = Eotools_Mcp_Settings::get( isset( $input['section'] ) ? $input['section'] : '' );
		if ( ! is_wp_error( $result ) && ! empty( $input['include_log'] ) ) {
			$result['log'] = Eotools_Mcp_Settings::get_log();
		}

		return $result;
	}
}
