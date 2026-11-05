<?php
/**
 * Ability: update WordPress, plugins, themes and translations.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Update_Site extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'update-site';
	}

	public function get_label() {
		return __( 'Mettre à jour le site', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Met à jour les extensions, les thèmes, WordPress et les traductions, comme les mises à jour automatiques de WordPress : le site passe quelques secondes en maintenance pendant chaque mise à jour, et si une extension ou un thème actif casse le site, son ancienne version est restaurée automatiquement. Seules les mises à jour compatibles sont faites. Avant : appelez eo-tools/check-updates, présentez la liste à l\'utilisateur et lancez seulement avec son accord ; pour une version MAJEURE de WordPress, recommandez une sauvegarde et demandez un accord explicite ("allow_major"). Faites les mises à jour par lots de 5 extensions au plus par appel (elles peuvent prendre du temps), puis WordPress, puis les traductions. Ne réessayez pas une mise à jour annulée ("rolled_back") : expliquez le problème à l\'utilisateur.', 'eo-tools' );
	}

	public function get_group() {
		return 'updates';
	}

	public function get_risk() {
		return self::RISK_SENSITIVE;
	}

	public function get_input_schema() {
		$list = array(
			'oneOf' => array(
				array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				array(
					'type' => 'string',
					'enum' => array( 'all' ),
				),
			),
		);

		return array(
			'type'                 => 'object',
			'properties'           => array(
				'plugins'      => $list + array( 'description' => __( 'Extensions à mettre à jour : leurs "file" (ou dossiers) renvoyés par eo-tools/check-updates, ou "all".', 'eo-tools' ) ),
				'themes'       => $list + array( 'description' => __( 'Thèmes à mettre à jour : leurs "stylesheet", ou "all".', 'eo-tools' ) ),
				'wordpress'    => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Mettre à jour WordPress.', 'eo-tools' ),
				),
				'allow_major'  => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Autoriser une version majeure de WordPress (accord explicite de l\'utilisateur requis).', 'eo-tools' ),
				),
				'translations' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'update_plugins' ) || current_user_can( 'update_core' ) || current_user_can( 'update_themes' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Updates::run( $input );
	}
}
