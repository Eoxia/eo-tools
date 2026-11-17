<?php
/**
 * Ability: read the settings of a supported plugin.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Plugin_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Get_Plugin_Settings extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'get-plugin-settings';
	}

	public function get_label() {
		return __( 'Lire les réglages d\'une extension', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Lit les réglages d\'une extension prise en charge par EO Tools (sans "plugin" : liste des extensions prises en charge et si elles sont actives). Renvoie les valeurs actuelles ("settings"), la description des réglages modifiables ("schema") et l\'état des clés secrètes ("secrets" : renseignée ou non, "needed" si elle manque pour un réglage utilisé, et le lien où un humain la saisit). Les clés secrètes ne sont jamais renvoyées. "status" donne l\'état en lecture seule et les liens des écrans réservés à un humain (licence, scan, optimisation du pare-feu…). Pour Contact Form 7, "target" est l\'identifiant du formulaire (par défaut le premier) ; la liste des formulaires est dans "forms". Le titre SEO et la méta description d\'une page ne sont pas ici : champ "seo" de eo-tools/update-draft ou eo-tools/propose-update.', 'eo-tools' );
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
				'plugin' => array(
					'type'        => 'string',
					'enum'        => array_keys( Eotools_Mcp_Plugin_Settings::get_adapters() ),
					'description' => __( 'Identifiant ("slug") de l\'extension.', 'eo-tools' ),
				),
				'target' => array(
					'type'        => 'string',
					'description' => __( 'Objet de l\'extension (ex. identifiant du formulaire Contact Form 7). Facultatif.', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Plugin_Settings::get(
			isset( $input['plugin'] ) ? $input['plugin'] : '',
			isset( $input['target'] ) ? $input['target'] : ''
		);
	}
}
