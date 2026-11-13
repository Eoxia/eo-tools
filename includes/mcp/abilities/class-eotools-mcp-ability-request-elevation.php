<?php
/**
 * Ability: ask a human for the temporary right to activate plugins.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Elevation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Request_Elevation extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'request-elevation';
	}

	public function get_label() {
		return __( 'Demander une autorisation temporaire', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Demande à un administrateur le droit temporaire d\'activer des extensions déjà installées (par ex. après eo-tools/install-plugin). La demande indique les extensions, la raison (affichée à l\'administrateur, soyez précis) et la durée souhaitée. Elle ne donne aucun droit tant qu\'un administrateur ne l\'a pas validée dans WordPress, en retapant son mot de passe, via le lien "approval_url" renvoyé : donnez ce lien à l\'utilisateur. Ne demandez jamais de code, de mot de passe ou de validation dans la conversation. L\'autorisation ne vaut que pour cette connexion, pour ces extensions, et expire d\'elle-même ; activez ensuite chaque extension avec eo-tools/activate-plugin.', 'eo-tools' );
	}

	public function get_group() {
		return 'plugins';
	}

	public function get_risk() {
		return self::RISK_WRITE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'plugins'  => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'minItems'    => 1,
					'maxItems'    => Eotools_Mcp_Elevation::MAX_PLUGINS,
					'description' => __( 'Identifiants ("slug") des extensions installées à activer, ex. ["contact-form-7", "flamingo"].', 'eo-tools' ),
				),
				'reason'   => array(
					'type'        => 'string',
					'description' => __( 'Pourquoi ces extensions doivent être activées, en une ou deux phrases, pour l\'administrateur.', 'eo-tools' ),
				),
				'duration' => array(
					'type'        => 'integer',
					'minimum'     => 5,
					'maximum'     => Eotools_Mcp_Elevation::MAX_DURATION,
					'default'     => Eotools_Mcp_Elevation::DEFAULT_DURATION,
					'description' => __( 'Durée souhaitée en minutes. L\'administrateur peut la réduire.', 'eo-tools' ),
				),
			),
			'required'             => array( 'plugins', 'reason' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'activate_plugins' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Elevation::request(
			$input['plugins'],
			$input['reason'],
			isset( $input['duration'] ) ? $input['duration'] : Eotools_Mcp_Elevation::DEFAULT_DURATION
		);
	}
}
