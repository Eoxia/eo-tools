<?php
/**
 * Ability: search the wordpress.org plugin directory.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Search_Plugins extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'search-plugins';
	}

	public function get_label() {
		return __( 'Chercher des extensions', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Cherche des extensions dans le répertoire officiel wordpress.org (gratuites). Renvoie pour chacune la note, le nombre de sites qui l\'utilisent, la dernière mise à jour, la compatibilité avec ce site ("incompatible") et des avertissements ("warnings" : peu utilisée, abandonnée, non testée…). Rédigez la recherche en anglais pour de meilleurs résultats. Recommandez de préférence une extension populaire, bien notée et à jour, et présentez à l\'utilisateur 1 à 3 choix avec leurs avantages avant d\'en installer une.', 'eo-tools' );
	}

	public function get_group() {
		return 'plugins';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'query'    => array(
					'type'        => 'string',
					'description' => __( 'Mots-clés, de préférence en anglais (ex. "contact form", "cookie banner").', 'eo-tools' ),
				),
				'per_page' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 20,
					'default' => 10,
				),
			),
			'required'             => array( 'query' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'activate_plugins' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Plugins::search( $input );
	}
}
