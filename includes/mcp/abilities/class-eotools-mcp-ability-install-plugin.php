<?php
/**
 * Ability: install a plugin from wordpress.org, without activating it.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Install_Plugin extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'install-plugin';
	}

	public function get_label() {
		return __( 'Installer une extension', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Installe une extension du répertoire officiel wordpress.org, SANS l\'activer : l\'utilisateur l\'active depuis la page Extensions, ou vous autorise temporairement à le faire (eo-tools/request-elevation puis eo-tools/activate-plugin). N\'installez qu\'avec l\'accord explicite de l\'utilisateur, après lui avoir présenté l\'extension (trouvée avec eo-tools/search-plugins). Les extensions incompatibles avec ce site sont refusées. Une extension installée ne s\'exécute pas tant qu\'elle n\'est pas activée.', 'eo-tools' );
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
					'description' => __( 'Identifiant wordpress.org de l\'extension ("slug" renvoyé par eo-tools/search-plugins, ex. "contact-form-7").', 'eo-tools' ),
				),
			),
			'required'             => array( 'slug' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'install_plugins' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Plugins::install( $input['slug'] );
	}
}
