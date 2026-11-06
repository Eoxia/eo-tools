<?php
/**
 * Ability: propose a change to a site template.
 *
 * The change is stored in a proposal. The user previews it on a page of the
 * site and applies or rejects it: the AI cannot apply it.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Content;
use EoTools\Includes\Mcp\Eotools_Mcp_Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Propose_Template_Update extends Eotools_Mcp_Ability_Template {

	public function get_slug() {
		return 'propose-template-update';
	}

	public function get_label() {
		return __( 'Proposer une modification de modèle', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Propose une modification d\'un modèle ou d\'une partie de modèle (en-tête, pied de page…) sans toucher au site : le changement est enregistré dans une proposition et un lien d\'aperçu est renvoyé. Attention, un modèle s\'applique à toutes les pages qui l\'utilisent : ne modifiez que ce qui est demandé, de préférence avec "operations", et conservez les blocs de structure (core/template-part, core/post-content, core/query…). Donnez le lien d\'aperçu à l\'utilisateur : il applique ou rejette la modification lui-même (vous ne pouvez pas l\'appliquer). Appeler à nouveau cet outil complète la proposition en cours ; les positions de "operations" se réfèrent alors à la proposition (lisible avec eo-tools/get-template et "proposal": true).', 'eo-tools' );
	}

	public function get_risk() {
		return self::RISK_SENSITIVE;
	}

	public function get_input_schema() {
		$fields = Eotools_Mcp_Content::get_fields_schema( true );

		return array(
			'type'                 => 'object',
			'properties'           => array_merge(
				$this->get_template_schema(),
				array(
					'blocks'      => $fields['blocks'],
					'operations'  => $fields['operations'],
					'restart'     => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Abandonne la proposition en cours et repart du modèle actuel.', 'eo-tools' ),
					),
					'preview_url' => array(
						'type'        => 'string',
						'description' => __( 'Adresse d\'une page du site qui utilise ce modèle, pour l\'aperçu. Par défaut, une page adaptée est choisie (l\'accueil pour l\'en-tête et le pied de page).', 'eo-tools' ),
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
		if ( ! isset( $input['blocks'] ) && ! isset( $input['operations'] ) ) {
			return new \WP_Error( 'eo_tools_no_change', __( 'Aucune modification demandée : indiquez "operations" ou "blocks".', 'eo-tools' ) );
		}

		$preview_url = '';
		if ( ! empty( $input['preview_url'] ) ) {
			$preview_url = esc_url_raw( $input['preview_url'] );
			if ( wp_parse_url( $preview_url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
				return new \WP_Error( 'eo_tools_preview_url', __( '"preview_url" doit être une page de ce site.', 'eo-tools' ) );
			}
		}

		$proposal = Eotools_Mcp_Templates::get_or_create( $template, ! empty( $input['restart'] ), $preview_url );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		$warnings = array();
		$content  = Eotools_Mcp_Content::build_content( $proposal->post_content, $input, $warnings );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'           => $proposal->ID,
					'post_content' => $content,
				)
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$proposal = get_post( $proposal->ID );

		return array(
			'id'           => $template->id,
			'type'         => Eotools_Mcp_Templates::type_key( $template ),
			'title'        => $template->title,
			'proposal_id'  => $proposal->ID,
			'preview_link' => Eotools_Mcp_Templates::preview_url( $proposal ),
			'message'      => __( 'Modification enregistrée dans une proposition, le site n\'a pas changé. Donnez le lien d\'aperçu à l\'utilisateur (il doit être connecté en administrateur) : il pourra appliquer ou rejeter la modification depuis cette page, ou depuis EO Tools > MCP > À valider.', 'eo-tools' ),
			'warnings'     => array_values( array_unique( $warnings ) ),
			/* translators: %d: proposal ID. */
			'next_step'    => sprintf( __( 'Vérifiez le rendu avec eo-tools/check-render (template_proposal_id %d) et corrigez les problèmes signalés avant de donner le lien d\'aperçu à l\'utilisateur.', 'eo-tools' ), $proposal->ID ),
		);
	}
}
