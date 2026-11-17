<?php
/**
 * Ability: scan the site for cookies with the EO Tools cookie manager.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Cookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Scan_Cookies extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'scan-cookies';
	}

	public function get_label() {
		return __( 'Scanner les cookies du site', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Lance le scan détaillé du gestionnaire de cookies d\'EO Tools (le même que l\'onglet Scan de EO Tools > Cookies) : toutes les pages, articles et médias publiés sont analysés. Le scan avance par étapes de 25 secondes environ : tant que "status" vaut "in_progress", rappelez l\'outil pour le reprendre. Une fois terminé, les cookies trouvés sont ajoutés à la liste du gestionnaire (catégorie et description reprises de l\'Open Cookie Database quand le cookie y est connu), comme le fait l\'écran, et la réponse donne la liste complète. Vérifiez-la, corrigez-la et validez-la ensuite avec eo-tools/update-cookies.', 'eo-tools' );
	}

	public function get_group() {
		return 'cookies';
	}

	public function get_risk() {
		return self::RISK_WRITE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'restart' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Recommencer un nouveau scan au lieu de reprendre celui en cours.', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Cookies::scan( ! empty( $input['restart'] ) );
	}
}
