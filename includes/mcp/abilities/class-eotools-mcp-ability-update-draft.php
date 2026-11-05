<?php
/**
 * Ability: change a content that is not published yet.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Content;
use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Update_Draft extends Eotools_Mcp_Ability {

	/**
	 * Statuses this ability may change. Published content goes through eo-tools/propose-update.
	 */
	const STATUSES = array( 'draft', 'pending', 'future', 'auto-draft' );

	public function get_slug() {
		return 'update-draft';
	}

	public function get_label() {
		return __( 'Modifier un brouillon', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Modifie un contenu non publié (brouillon, en attente, planifié) : titre, contenu, extrait, image, catégories. Pour le contenu, utilisez "operations" pour une modification ciblée (positions données par eo-tools/get-post) ou "blocks" pour tout remplacer. Pour un contenu déjà publié, utilisez eo-tools/propose-update.', 'eo-tools' );
	}

	public function get_group() {
		return 'content';
	}

	public function get_risk() {
		return self::RISK_WRITE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array_merge(
				array(
					'id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Identifiant du contenu à modifier.', 'eo-tools' ),
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
		$post = get_post( (int) $input['id'] );

		if ( ! $post || ! in_array( $post->post_type, Eotools_Mcp_Ability_List_Posts::get_post_types(), true ) ) {
			return new \WP_Error( 'eo_tools_post_not_found', __( 'Contenu introuvable.', 'eo-tools' ) );
		}
		if ( Eotools_Mcp_Proposals::is_proposal( $post ) ) {
			return new \WP_Error( 'eo_tools_is_proposal', __( 'Ce contenu est une proposition de modification : utilisez eo-tools/propose-update avec l\'identifiant du contenu publié.', 'eo-tools' ) );
		}
		if ( ! in_array( $post->post_status, self::STATUSES, true ) ) {
			return new \WP_Error( 'eo_tools_published', __( 'Ce contenu est publié : utilisez eo-tools/propose-update pour proposer la modification à l\'utilisateur.', 'eo-tools' ) );
		}

		$warnings = array();
		$content  = Eotools_Mcp_Content::build_content( $post->post_content, $input, $warnings );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$data = Eotools_Mcp_Content::get_post_fields( $input );
		if ( null !== $content ) {
			$data['post_content'] = $content;
		}

		if ( $data ) {
			$data['ID'] = $post->ID;
			$updated    = wp_update_post( wp_slash( $data ), true );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		Eotools_Mcp_Content::apply_relations( $post->ID, $input, $warnings );

		return Eotools_Mcp_Content::response( get_post( $post->ID ), $warnings );
	}
}
