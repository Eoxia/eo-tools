<?php
/**
 * Base class of the abilities working on the site templates.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Eotools_Mcp_Ability_Template extends Eotools_Mcp_Ability {

	public function get_group() {
		return 'site';
	}

	public function has_permission( $input ) {
		return current_user_can( Eotools_Mcp_Templates::CAPABILITY );
	}

	/**
	 * Schema of the "id" and "type" inputs.
	 *
	 * @return array
	 */
	protected function get_template_schema() {
		return array(
			'id'   => array(
				'type'        => 'string',
				'description' => __( 'Identifiant du modèle renvoyé par eo-tools/list-templates (ex. "beflex//header"), ou son slug.', 'eo-tools' ),
			),
			'type' => array(
				'type'        => 'string',
				'enum'        => array( 'template', 'template_part' ),
				'description' => __( 'Modèle de page ou partie de modèle (en-tête, pied de page…). Facultatif.', 'eo-tools' ),
			),
		);
	}

	/**
	 * Error returned when the theme has no block templates.
	 *
	 * @return \WP_Error|null
	 */
	protected function check_support() {
		if ( Eotools_Mcp_Templates::is_supported() ) {
			return null;
		}

		return new \WP_Error( 'eo_tools_templates_unsupported', __( 'Le thème actif n\'utilise pas de modèles en blocs : ses modèles sont des fichiers PHP et ne peuvent pas être modifiés ici.', 'eo-tools' ) );
	}

	/**
	 * @param array $input Input.
	 * @return \WP_Block_Template|\WP_Error
	 */
	protected function find_template( $input ) {
		$error = $this->check_support();
		if ( $error ) {
			return $error;
		}

		return Eotools_Mcp_Templates::find( isset( $input['id'] ) ? $input['id'] : '', isset( $input['type'] ) ? $input['type'] : '' );
	}
}
