<?php
/**
 * Ability: check the rendered page of a content or of a template proposal.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Preview;
use EoTools\Includes\Mcp\Eotools_Mcp_Templates;
use EoTools\Includes\Mcp\Eotools_Mcp_Styles;

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
		return __( 'Affiche la page comme un visiteur (brouillon, proposition, contenu publié ou page du site avec un modèle proposé) et signale les problèmes visibles : erreurs PHP, blocs vides, images cassées ou sans texte alternatif, liens vides, titres. Renvoie aussi le plan des titres. À utiliser après chaque création ou modification, et corriger les problèmes avant de donner le lien d\'aperçu à l\'utilisateur.', 'eo-tools' );
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
				'id'                   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Identifiant du contenu, ou de la proposition ("proposal_id").', 'eo-tools' ),
				),
				'template_proposal_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'À la place de "id" : proposition de modèle renvoyée par eo-tools/propose-template-update. Toute la page est vérifiée (en-tête et pied de page compris).', 'eo-tools' ),
				),
				'styles_proposal_id'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'À la place de "id" : proposition de styles renvoyée par eo-tools/propose-styles-update. Toute la page est vérifiée, contrastes des couleurs compris.', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		if ( ! empty( $input['template_proposal_id'] ) || ! empty( $input['styles_proposal_id'] ) ) {
			return current_user_can( Eotools_Mcp_Templates::CAPABILITY );
		}

		return ! empty( $input['id'] ) && get_post( (int) $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
	}

	public function execute( $input ) {
		if ( ! empty( $input['template_proposal_id'] ) ) {
			$proposal = Eotools_Mcp_Templates::get_proposal( (int) $input['template_proposal_id'] );
			if ( ! $proposal ) {
				return new \WP_Error( 'eo_tools_proposal_not_found', __( 'Proposition de modèle introuvable (appliquée ou rejetée ?).', 'eo-tools' ) );
			}

			return Eotools_Mcp_Preview::check_template( $proposal );
		}

		if ( ! empty( $input['styles_proposal_id'] ) ) {
			$proposal = Eotools_Mcp_Styles::get_proposal( (int) $input['styles_proposal_id'] );
			if ( ! $proposal ) {
				return new \WP_Error( 'eo_tools_proposal_not_found', __( 'Proposition de styles introuvable (appliquée ou rejetée ?).', 'eo-tools' ) );
			}

			return Eotools_Mcp_Preview::check_styles( $proposal );
		}

		$post = empty( $input['id'] ) ? null : get_post( (int) $input['id'] );

		if ( ! $post || ! is_post_type_viewable( $post->post_type ) ) {
			return new \WP_Error( 'eo_tools_post_not_found', __( 'Contenu introuvable ou sans page visible. Indiquez "id" ou "template_proposal_id".', 'eo-tools' ) );
		}

		return Eotools_Mcp_Preview::check( $post );
	}
}
