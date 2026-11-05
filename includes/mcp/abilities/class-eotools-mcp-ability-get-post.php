<?php
/**
 * Ability: read a post, a page or any public content with its blocks.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Blocks;
use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Get_Post extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'get-post';
	}

	public function get_label() {
		return __( 'Lire un contenu', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Lit un article, une page ou un autre contenu : titre, statut, auteur, extrait, catégories, étiquettes, image mise en avant et contenu sous forme d\'arborescence de blocs (position "path", nom, attributs, HTML, blocs enfants). Indique aussi si une proposition de modification est en attente.', 'eo-tools' );
	}

	public function get_group() {
		return 'content';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'          => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Identifiant du contenu (voir eo-tools/list-posts).', 'eo-tools' ),
				),
				'content_format' => array(
					'type'        => 'string',
					'enum'        => array( 'blocks', 'raw', 'both' ),
					'default'     => 'blocks',
					'description' => __( '"blocks" : arborescence de blocs, "raw" : contenu brut enregistré en base, "both" : les deux.', 'eo-tools' ),
				),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		// Unknown content: let execute() answer "not found" instead of "permission denied".
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

		$format    = isset( $input['content_format'] ) ? $input['content_format'] : 'blocks';
		$author    = get_userdata( $post->post_author );
		$thumbnail = get_post_thumbnail_id( $post );

		$data = array(
			'id'             => $post->ID,
			'type'           => $post->post_type,
			'status'         => $post->post_status,
			'title'          => $post->post_title,
			'slug'           => $post->post_name,
			'link'           => get_permalink( $post ),
			'date'           => mysql_to_rfc3339( $post->post_date ),
			'modified'       => mysql_to_rfc3339( $post->post_modified ),
			'author'         => $author ? array(
				'id'   => $author->ID,
				'name' => $author->display_name,
			) : null,
			'excerpt'        => $post->post_excerpt,
			'parent'         => $post->post_parent,
			'template'       => get_page_template_slug( $post ),
			'featured_image' => $thumbnail ? array(
				'id'  => $thumbnail,
				'url' => wp_get_attachment_url( $thumbnail ),
				'alt' => get_post_meta( $thumbnail, '_wp_attachment_image_alt', true ),
			) : null,
			'terms'          => $this->get_terms( $post ),
		);

		if ( Eotools_Mcp_Proposals::is_proposal( $post ) ) {
			$source               = Eotools_Mcp_Proposals::get_source( $post );
			$data['proposal_of']  = $source ? $source->ID : null;
			$data['preview_link'] = get_preview_post_link( $post );
		} else {
			$proposal = Eotools_Mcp_Proposals::get_for( $post->ID );
			if ( $proposal ) {
				$data['pending_proposal'] = array(
					'proposal_id'  => $proposal->ID,
					'preview_link' => get_preview_post_link( $proposal ),
				);
			}
		}

		if ( 'raw' !== $format ) {
			$data['blocks'] = Eotools_Mcp_Blocks::to_tree( parse_blocks( $post->post_content ) );
		}
		if ( 'blocks' !== $format ) {
			$data['content'] = $post->post_content;
		}

		return $data;
	}

	/**
	 * Returns the terms of every public taxonomy of the post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array Terms keyed by taxonomy.
	 */
	private function get_terms( $post ) {
		$terms = array();

		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->public ) {
				continue;
			}

			$post_terms = get_the_terms( $post, $taxonomy->name );
			if ( empty( $post_terms ) || is_wp_error( $post_terms ) ) {
				continue;
			}

			$terms[ $taxonomy->name ] = array_map(
				function ( $term ) {
					return array(
						'id'   => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					);
				},
				$post_terms
			);
		}

		return $terms;
	}
}
