<?php
/**
 * Ability: check the rendered page of a content.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Check_Render extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'check-render';
	}

	public function get_label() {
		return __( 'Vérifier le rendu', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Affiche la page comme un visiteur (brouillon, proposition ou contenu publié) et signale les problèmes visibles : erreurs PHP, blocs vides, images cassées ou sans texte alternatif, liens vides, titres. Renvoie aussi le plan des titres. À utiliser après chaque création ou modification, avant de donner le lien à l\'utilisateur ; eo-tools/screenshot montre le résultat en image.', 'eo-tools' );
	}

	public function get_group() {
		return 'preview';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Identifiant du contenu, ou de la proposition ("proposal_id").', 'eo-tools' ),
				),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return ! empty( $input['id'] ) && get_post( (int) $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
	}

	public function execute( $input ) {
		$post = get_post( (int) $input['id'] );

		if ( ! $post || ! is_post_type_viewable( $post->post_type ) ) {
			return new \WP_Error( 'eo_tools_post_not_found', __( 'Contenu introuvable ou sans page visible.', 'eo-tools' ) );
		}

		return Eotools_Mcp_Preview::check( $post );
	}
}
