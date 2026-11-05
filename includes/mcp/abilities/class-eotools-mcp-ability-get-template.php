<?php
/**
 * Ability: read a site template.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Blocks;
use EoTools\Includes\Mcp\Eotools_Mcp_Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Get_Template extends Eotools_Mcp_Ability_Template {

	public function get_slug() {
		return 'get-template';
	}

	public function get_label() {
		return __( 'Lire un modèle', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Renvoie les blocs d\'un modèle ou d\'une partie de modèle, avec la position ("path") de chaque bloc pour eo-tools/propose-template-update. Avec "proposal": true, renvoie la proposition en cours à la place du modèle actuel.', 'eo-tools' );
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array_merge(
				$this->get_template_schema(),
				array(
					'proposal'       => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Lire la proposition en cours plutôt que le modèle actuel.', 'eo-tools' ),
					),
					'content_format' => array(
						'type'    => 'string',
						'enum'    => array( 'blocks', 'raw', 'both' ),
						'default' => 'blocks',
					),
				)
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	public function execute( $input ) {
		$template = $this->find_template( $input );
		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$data    = Eotools_Mcp_Templates::describe( $template );
		$content = $template->content;

		if ( ! empty( $input['proposal'] ) ) {
			$proposal = Eotools_Mcp_Templates::get_for( $template->id, $template->type );
			if ( ! $proposal ) {
				return new \WP_Error( 'eo_tools_proposal_not_found', __( 'Aucune proposition en cours pour ce modèle.', 'eo-tools' ) );
			}
			$content         = $proposal->post_content;
			$data['showing'] = 'proposal';
		}

		$format = isset( $input['content_format'] ) ? $input['content_format'] : 'blocks';
		if ( 'raw' !== $format ) {
			$data['blocks'] = Eotools_Mcp_Blocks::to_tree( parse_blocks( $content ) );
		}
		if ( 'blocks' !== $format ) {
			$data['content'] = $content;
		}

		return $data;
	}
}
