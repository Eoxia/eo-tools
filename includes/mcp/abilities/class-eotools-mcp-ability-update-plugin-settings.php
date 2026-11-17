<?php
/**
 * Ability: change the settings of a supported plugin.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Plugin_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Update_Plugin_Settings extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'update-plugin-settings';
	}

	public function get_label() {
		return __( 'Modifier les réglages d\'une extension', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Modifie des réglages d\'une extension prise en charge par EO Tools, appliqués immédiatement sur le site en ligne. Lisez d\'abord les réglages avec eo-tools/get-plugin-settings (noms dans "schema"). Présentez les changements à l\'utilisateur et obtenez son accord avant de les appliquer ; demandez-lui les valeurs que vous ne connaissez pas (adresse e-mail de réception, clé de site du captcha…), ne les inventez pas. Si une valeur est refusée, rien n\'est modifié. La réponse donne l\'ancienne valeur de chaque réglage changé. Les clés secrètes (clé secrète de captcha, mot de passe…) ne passent JAMAIS par vous : ne les demandez pas, donnez à l\'utilisateur le lien indiqué dans "notes" ou "secrets" pour qu\'il les saisisse lui-même dans WordPress.', 'eo-tools' );
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
				'plugin'   => array(
					'type'        => 'string',
					'enum'        => array_keys( Eotools_Mcp_Plugin_Settings::get_adapters() ),
					'description' => __( 'Identifiant ("slug") de l\'extension.', 'eo-tools' ),
				),
				'target'   => array(
					'type'        => 'string',
					'description' => __( 'Objet de l\'extension (ex. identifiant du formulaire Contact Form 7). Facultatif.', 'eo-tools' ),
				),
				'settings' => array(
					'type'                 => 'object',
					'minProperties'        => 1,
					'description'          => __( 'Nouvelles valeurs par nom de réglage, ex. {"mail_recipient": "contact@exemple.fr", "captcha": "really-simple-captcha"}.', 'eo-tools' ),
					'additionalProperties' => array(
						'type' => array( 'string', 'boolean' ),
					),
				),
			),
			'required'             => array( 'plugin', 'settings' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Plugin_Settings::update(
			$input['plugin'],
			$input['settings'],
			isset( $input['target'] ) ? $input['target'] : ''
		);
	}
}
