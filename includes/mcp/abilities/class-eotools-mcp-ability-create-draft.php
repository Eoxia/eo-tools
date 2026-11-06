<?php
/**
 * Base ability: create a draft of a given content type.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Ai_Drafts;
use EoTools\Includes\Mcp\Eotools_Mcp_Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Eotools_Mcp_Ability_Create_Draft extends Eotools_Mcp_Ability {

	/**
	 * @return string Post type created by the ability.
	 */
	abstract protected function get_post_type();

	public function get_group() {
		return 'content';
	}

	public function get_risk() {
		return self::RISK_WRITE;
	}

	public function get_input_schema() {
		$properties = Eotools_Mcp_Content::get_fields_schema( false );

		if ( is_post_type_hierarchical( $this->get_post_type() ) ) {
			$properties['parent'] = array(
				'type'        => 'integer',
				'description' => __( 'Identifiant de la page parente.', 'eo-tools' ),
			);
			unset( $properties['categories'], $properties['tags'] );
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array( 'title' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		$post_type = get_post_type_object( $this->get_post_type() );

		return $post_type && current_user_can( $post_type->cap->create_posts );
	}

	public function execute( $input ) {
		$warnings = array();
		$content  = Eotools_Mcp_Content::build_content( '', $input, $warnings );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$data = array_merge(
			Eotools_Mcp_Content::get_post_fields( $input ),
			array(
				'post_type'    => $this->get_post_type(),
				'post_status'  => 'draft',
				'post_content' => (string) $content,
			)
		);

		if ( ! empty( $input['parent'] ) ) {
			$data['post_parent'] = (int) $input['parent'];
		}

		// Block comments contain backslashes: data must be slashed for wp_insert_post().
		$post_id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		Eotools_Mcp_Ai_Drafts::mark( $post_id );
		Eotools_Mcp_Content::apply_relations( $post_id, $input, $warnings );

		$response               = Eotools_Mcp_Content::response( get_post( $post_id ), $warnings );
		$response['validation'] = __( 'Ce brouillon n\'est pas publié. L\'utilisateur peut publier en une fois tous les brouillons créés par l\'IA depuis EO Tools > MCP > À valider, ou avec le bouton « Valider » de la liste des pages / articles.', 'eo-tools' );

		return $response;
	}
}
