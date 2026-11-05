<?php
/**
 * Ability: list posts, pages and other public content types.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_List_Posts extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'list-posts';
	}

	public function get_label() {
		return __( 'Lister les contenus', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Liste les articles, pages et autres contenus du site, avec filtres par type, statut et recherche. Renvoie l\'identifiant de chaque contenu, à utiliser ensuite avec eo-tools/get-post.', 'eo-tools' );
	}

	public function get_group() {
		return 'content';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	/**
	 * Public content types, except media.
	 *
	 * @return string[]
	 */
	public static function get_post_types() {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			)
		);
		unset( $types['attachment'] );

		return array_values( $types );
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_type' => array(
					'type'        => 'string',
					'enum'        => self::get_post_types(),
					'default'     => 'post',
					'description' => __( 'Type de contenu : "post" pour les articles, "page" pour les pages.', 'eo-tools' ),
				),
				'status'    => array(
					'type'        => 'string',
					'enum'        => array( 'any', 'publish', 'draft', 'pending', 'private', 'future' ),
					'default'     => 'any',
					'description' => __( 'Statut de publication. "any" renvoie tous les statuts sauf la corbeille.', 'eo-tools' ),
				),
				'search'    => array(
					'type'        => 'string',
					'description' => __( 'Recherche dans le titre et le contenu.', 'eo-tools' ),
				),
				'orderby'   => array(
					'type'    => 'string',
					'enum'    => array( 'date', 'modified', 'title' ),
					'default' => 'date',
				),
				'per_page'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 20,
				),
				'page'      => array(
					'type'    => 'integer',
					'minimum' => 1,
					'default' => 1,
				),
			),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	public function get_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'items'       => array( 'type' => 'array' ),
				'total'       => array( 'type' => 'integer' ),
				'total_pages' => array( 'type' => 'integer' ),
			),
		);
	}

	public function has_permission( $input ) {
		$post_type = get_post_type_object( isset( $input['post_type'] ) ? $input['post_type'] : 'post' );

		return $post_type && current_user_can( $post_type->cap->edit_posts );
	}

	public function execute( $input ) {
		$input = wp_parse_args(
			$input,
			array(
				'post_type' => 'post',
				'status'    => 'any',
				'search'    => '',
				'orderby'   => 'date',
				'per_page'  => 20,
				'page'      => 1,
			)
		);

		$query = new \WP_Query(
			array(
				'post_type'      => $input['post_type'],
				'post_status'    => $input['status'],
				's'              => $input['search'],
				'orderby'        => $input['orderby'],
				'order'          => 'title' === $input['orderby'] ? 'ASC' : 'DESC',
				'posts_per_page' => (int) $input['per_page'],
				'paged'          => (int) $input['page'],
				'perm'           => 'editable',
				'no_found_rows'  => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'status'   => $post->post_status,
				'type'     => $post->post_type,
				'date'     => mysql_to_rfc3339( $post->post_date ),
				'modified' => mysql_to_rfc3339( $post->post_modified ),
				'link'     => get_permalink( $post ),
				'excerpt'  => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : strip_shortcodes( $post->post_content ) ), 30 ),
			);
		}

		return array(
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}
}
