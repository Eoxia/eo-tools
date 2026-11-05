<?php
/**
 * Ability: propose a change to a published content.
 *
 * The change is stored in a temporary copy. The user previews it and applies or
 * rejects it from the preview: the AI cannot publish it.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Content;
use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Propose_Update extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'propose-update';
	}

	public function get_label() {
		return __( 'Proposer une modification', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Propose une modification d\'un contenu publié sans le toucher : les changements sont enregistrés dans une copie temporaire et un lien d\'aperçu est renvoyé. Donnez ce lien à l\'utilisateur : il applique ou rejette la modification lui-même depuis l\'aperçu (vous ne pouvez pas l\'appliquer). Appeler à nouveau cet outil complète la proposition en cours ; les positions de "operations" se réfèrent alors à la proposition (lisible avec eo-tools/get-post et son identifiant "proposal_id").', 'eo-tools' );
	}

	public function get_group() {
		return 'content';
	}

	public function get_risk() {
		return self::RISK_SENSITIVE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array_merge(
				array(
					'id'      => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Identifiant du contenu publié.', 'eo-tools' ),
					),
					'restart' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Abandonne la proposition en cours et repart du contenu publié.', 'eo-tools' ),
					),
				),
				Eotools_Mcp_Content::get_fields_schema( true )
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		if ( empty( $input['id'] ) || ! get_post( (int) $input['id'] ) ) {
			return current_user_can( 'edit_posts' );
		}

		return current_user_can( 'edit_post', (int) $input['id'] );
	}

	public function execute( $input ) {
		$source = get_post( (int) $input['id'] );

		if ( ! $source || ! in_array( $source->post_type, Eotools_Mcp_Ability_List_Posts::get_post_types(), true ) || Eotools_Mcp_Proposals::is_proposal( $source ) ) {
			return new \WP_Error( 'eo_tools_post_not_found', __( 'Contenu publié introuvable. Utilisez l\'identifiant du contenu publié, pas celui de la proposition.', 'eo-tools' ) );
		}
		if ( ! in_array( $source->post_status, array( 'publish', 'private' ), true ) ) {
			return new \WP_Error( 'eo_tools_not_published', __( 'Ce contenu n\'est pas publié : modifiez-le directement avec eo-tools/update-draft.', 'eo-tools' ) );
		}

		$changed = array_values( array_intersect( array( 'title', 'blocks', 'operations', 'excerpt', 'slug', 'featured_image_id', 'categories', 'tags' ), array_keys( $input ) ) );
		if ( empty( $changed ) ) {
			return new \WP_Error( 'eo_tools_no_change', __( 'Aucune modification demandée.', 'eo-tools' ) );
		}

		$proposal = Eotools_Mcp_Proposals::get_or_create( $source, ! empty( $input['restart'] ) );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		$warnings = array();
		$content  = Eotools_Mcp_Content::build_content( $proposal->post_content, $input, $warnings );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$data = Eotools_Mcp_Content::get_post_fields( $input );
		if ( null !== $content ) {
			$data['post_content'] = $content;
		}
		$data['ID'] = $proposal->ID;

		$updated = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		Eotools_Mcp_Content::apply_relations( $proposal->ID, $input, $warnings );

		$fields = str_replace(
			array( 'blocks', 'operations', 'featured_image_id' ),
			array( 'content', 'content', 'featured_image' ),
			$changed
		);
		Eotools_Mcp_Proposals::add_changed_fields( $proposal->ID, $fields );

		$proposal = get_post( $proposal->ID );

		return array(
			'id'           => $source->ID,
			'proposal_id'  => $proposal->ID,
			'title'        => $proposal->post_title,
			'preview_link' => get_preview_post_link( $proposal ),
			'message'      => __( 'Modification enregistrée dans une proposition, le contenu publié n\'a pas changé. Donnez le lien d\'aperçu à l\'utilisateur : il pourra appliquer ou rejeter la modification depuis cette page.', 'eo-tools' ),
			'warnings'     => array_values( array_unique( $warnings ) ),
			'next_step'    => Eotools_Mcp_Content::check_hint( $proposal->ID ),
		);
	}
}
