<?php
/**
 * Content editing shared by the writing abilities.
 *
 * The AI changes a content either with a full block tree ("blocks") or with
 * targeted operations ("operations"). Operations keep untouched blocks byte for
 * byte, so a block the AI did not change can never be damaged.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Content {

	/**
	 * JSON Schema of a block node sent by the AI.
	 *
	 * @return array
	 */
	public static function get_block_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'name'         => array(
					'type'        => 'string',
					'description' => __( 'Nom du bloc, par exemple "core/paragraph".', 'eo-tools' ),
				),
				'attributes'   => array(
					'type'        => 'object',
					'description' => __( 'Attributs du bloc. Le texte va dans "content" (paragraphe, titre, élément de liste), "text" (bouton), "citation" (citation), "summary" (détails) ; une image utilise "url", "alt", "id" ; un tableau utilise "head" et "body" (tableaux de lignes, chaque ligne étant un tableau de cellules).', 'eo-tools' ),
				),
				'inner_blocks' => array(
					'type'        => 'array',
					'description' => __( 'Blocs enfants, même format.', 'eo-tools' ),
				),
				'html'         => array(
					'type'        => 'string',
					'description' => __( 'HTML du bloc, uniquement pour un bloc conservé tel quel (copié depuis eo-tools/get-post) ou un bloc que EO Tools ne sait pas générer.', 'eo-tools' ),
				),
			),
			'required'   => array( 'name' ),
		);
	}

	/**
	 * Input properties shared by the abilities that create or change a content.
	 *
	 * @param bool $with_operations Whether targeted operations are accepted (existing content).
	 * @return array
	 */
	public static function get_fields_schema( $with_operations ) {
		$fields = array(
			'title'             => array(
				'type'        => 'string',
				'description' => __( 'Titre.', 'eo-tools' ),
			),
			'blocks'            => array(
				'type'        => 'array',
				'items'       => self::get_block_schema(),
				'description' => __( 'Contenu complet sous forme d\'arborescence de blocs. Utilisez eo-tools/list-blocks et eo-tools/get-block pour choisir les blocs et leurs attributs.', 'eo-tools' ),
			),
			'excerpt'           => array(
				'type'        => 'string',
				'description' => __( 'Extrait.', 'eo-tools' ),
			),
			'slug'              => array(
				'type'        => 'string',
				'description' => __( 'Identifiant d\'URL (slug).', 'eo-tools' ),
			),
			'featured_image_id' => array(
				'type'        => 'integer',
				'description' => __( 'Identifiant du média à utiliser comme image mise en avant (0 pour la retirer).', 'eo-tools' ),
			),
			'categories'        => array(
				'type'        => 'array',
				'items'       => array( 'type' => array( 'integer', 'string' ) ),
				'description' => __( 'Articles uniquement : catégories (identifiants ou noms ; un nom inconnu crée la catégorie). Remplace les catégories actuelles.', 'eo-tools' ),
			),
			'tags'              => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => __( 'Articles uniquement : étiquettes (noms). Remplace les étiquettes actuelles.', 'eo-tools' ),
			),
		);

		if ( $with_operations ) {
			$fields['blocks']['description'] .= ' ' . __( 'Remplace tout le contenu : préférez "operations" pour une modification partielle.', 'eo-tools' );
			$fields['operations']             = array(
				'type'        => 'array',
				'description' => __( 'Modifications ciblées, à la place de "blocks". Les blocs non visés sont conservés à l\'identique. Chaque "path" désigne la position d\'un bloc dans le contenu D\'ORIGINE, telle que renvoyée par eo-tools/get-post (ex. "2" ou "2.0" pour le premier enfant du troisième bloc).', 'eo-tools' ),
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'action' => array(
							'type'        => 'string',
							'enum'        => array( 'replace', 'insert_before', 'insert_after', 'delete', 'append' ),
							'description' => __( '"append" ajoute des blocs à la fin du bloc visé (ou du contenu si "path" est vide).', 'eo-tools' ),
						),
						'path'   => array( 'type' => 'string' ),
						'blocks' => array(
							'type'  => 'array',
							'items' => self::get_block_schema(),
						),
					),
					'required'   => array( 'action' ),
				),
			);
		}

		return $fields;
	}

	/**
	 * Computes the new post content from the input.
	 *
	 * @param string $current  Current post content.
	 * @param array  $input    Ability input.
	 * @param array  $warnings Warnings, completed by reference.
	 * @return string|null|\WP_Error New content, or null when the content is not changed.
	 */
	public static function build_content( $current, $input, &$warnings ) {
		$builder = new Eotools_Mcp_Block_Builder();

		if ( isset( $input['blocks'] ) ) {
			$content = $builder->to_markup( $input['blocks'] );
		} elseif ( isset( $input['operations'] ) ) {
			$content = self::apply_operations( $current, $input['operations'], $builder );
		} else {
			return null;
		}

		$warnings = array_merge( $warnings, $builder->get_warnings() );

		return $content;
	}

	/**
	 * Applies targeted operations to a content.
	 *
	 * Paths refer to the original content. Operations are applied from the last
	 * position to the first one, so earlier positions stay valid.
	 *
	 * @param string                    $content    Current content.
	 * @param array[]                   $operations Operations.
	 * @param Eotools_Mcp_Block_Builder $builder    Builder for the new blocks.
	 * @return string|\WP_Error
	 */
	public static function apply_operations( $content, $operations, $builder ) {
		if ( ! is_array( $operations ) || empty( $operations ) ) {
			return new \WP_Error( 'eo_tools_no_operation', __( 'Aucune opération à appliquer.', 'eo-tools' ) );
		}

		// Same positions as eo-tools/get-post, which skips the whitespace between blocks.
		$root = self::get_root_blocks( $content );

		$order = array( 'insert_after' => 0, 'append' => 0, 'replace' => 1, 'delete' => 1, 'insert_before' => 2 );
		$queue = array();
		foreach ( array_values( $operations ) as $index => $operation ) {
			$action = isset( $operation['action'] ) ? $operation['action'] : '';
			$path   = isset( $operation['path'] ) ? trim( (string) $operation['path'] ) : '';

			if ( ! isset( $order[ $action ] ) ) {
				/* translators: %d: operation number. */
				return new \WP_Error( 'eo_tools_operation', sprintf( __( 'Opération %d : action inconnue.', 'eo-tools' ), $index + 1 ) );
			}
			if ( '' === $path && 'append' !== $action ) {
				/* translators: %d: operation number. */
				return new \WP_Error( 'eo_tools_operation', sprintf( __( 'Opération %d : "path" est obligatoire.', 'eo-tools' ), $index + 1 ) );
			}
			if ( '' !== $path && ! preg_match( '/^\d+(\.\d+)*$/', $path ) ) {
				/* translators: 1: operation number, 2: path. */
				return new \WP_Error( 'eo_tools_operation', sprintf( __( 'Opération %1$d : chemin « %2$s » invalide.', 'eo-tools' ), $index + 1, $path ) );
			}

			$segments = '' === $path ? array() : array_map( 'intval', explode( '.', $path ) );
			if ( 'append' === $action ) {
				$segments[] = PHP_INT_MAX; // After every child of the target.
			}

			$queue[] = array(
				'index'    => $index,
				'action'   => $action,
				'path'     => $path,
				'segments' => $segments,
				'blocks'   => isset( $operation['blocks'] ) ? $operation['blocks'] : array(),
			);
		}

		// Last positions first; at the same position: insert_after, then replace/delete, then insert_before.
		usort(
			$queue,
			function ( $a, $b ) use ( $order ) {
				$count = max( count( $a['segments'] ), count( $b['segments'] ) );
				for ( $i = 0; $i < $count; $i++ ) {
					if ( ! isset( $a['segments'][ $i ] ) ) {
						return 1;
					}
					if ( ! isset( $b['segments'][ $i ] ) ) {
						return -1;
					}
					if ( $a['segments'][ $i ] !== $b['segments'][ $i ] ) {
						return $b['segments'][ $i ] - $a['segments'][ $i ] > 0 ? 1 : -1;
					}
				}
				return $order[ $a['action'] ] - $order[ $b['action'] ];
			}
		);

		foreach ( $queue as $operation ) {
			$result = self::apply_operation( $root, $operation, $builder );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return serialize_blocks( $root );
	}

	/**
	 * Top-level blocks of a content, without the whitespace between blocks.
	 *
	 * @param string $content Content.
	 * @return array[]
	 */
	public static function get_root_blocks( $content ) {
		return array_values(
			array_filter(
				parse_blocks( $content ),
				function ( $block ) {
					return ! empty( $block['blockName'] ) || '' !== trim( (string) $block['innerHTML'] );
				}
			)
		);
	}

	/**
	 * Applies one operation to the root block list.
	 *
	 * @param array[]                   $root      Root blocks, changed by reference.
	 * @param array                     $operation Operation.
	 * @param Eotools_Mcp_Block_Builder $builder   Builder.
	 * @return true|\WP_Error
	 */
	private static function apply_operation( &$root, $operation, $builder ) {
		$segments = $operation['segments'];
		$position = array_pop( $segments );
		$label    = '' === $operation['path'] ? '/' : $operation['path'];

		// Walk to the parent container.
		$parent_block = null;
		$siblings     = &$root;
		$parent_name  = null;
		foreach ( $segments as $segment ) {
			if ( ! isset( $siblings[ $segment ] ) ) {
				/* translators: %s: path. */
				return new \WP_Error( 'eo_tools_path', sprintf( __( 'Aucun bloc à la position « %s ».', 'eo-tools' ), $label ) );
			}
			$parent_block = &$siblings[ $segment ];
			$parent_name  = $parent_block['blockName'];
			$siblings     = &$parent_block['innerBlocks'];
		}

		$count = count( $siblings );
		if ( PHP_INT_MAX === $position ) {
			$position = $count;
		} elseif ( $position >= $count ) {
			/* translators: %s: path. */
			return new \WP_Error( 'eo_tools_path', sprintf( __( 'Aucun bloc à la position « %s ».', 'eo-tools' ), $label ) );
		}

		$new_blocks = array();
		if ( 'delete' !== $operation['action'] ) {
			$new_blocks = $builder->build_nodes( $operation['blocks'], $parent_name, $operation['path'] );
			if ( is_wp_error( $new_blocks ) ) {
				return $new_blocks;
			}
			if ( empty( $new_blocks ) ) {
				/* translators: %s: path. */
				return new \WP_Error( 'eo_tools_operation', sprintf( __( 'Opération sur « %s » : "blocks" est vide.', 'eo-tools' ), $label ) );
			}
		}

		switch ( $operation['action'] ) {
			case 'replace':
				array_splice( $siblings, $position, 1, $new_blocks );
				break;
			case 'delete':
				array_splice( $siblings, $position, 1 );
				break;
			case 'insert_before':
			case 'append':
				array_splice( $siblings, $position, 0, $new_blocks );
				break;
			case 'insert_after':
				array_splice( $siblings, $position + 1, 0, $new_blocks );
				break;
		}

		if ( null !== $parent_block ) {
			self::sync_inner_content( $parent_block );
		}

		return true;
	}

	/**
	 * Rebuilds innerContent after the inner blocks of a container changed.
	 *
	 * innerContent holds the HTML fragments with a null placeholder per inner block.
	 *
	 * @param array $block Parsed block, changed by reference.
	 */
	private static function sync_inner_content( &$block ) {
		$content = $block['innerContent'];
		$nulls   = array_keys( $content, null, true );
		$count   = count( $block['innerBlocks'] );

		if ( count( $nulls ) === $count ) {
			return;
		}

		if ( $nulls ) {
			$first     = reset( $nulls );
			$last      = end( $nulls );
			$prefix    = implode( '', array_slice( $content, 0, $first ) );
			$suffix    = implode( '', array_slice( $content, $last + 1 ) );
			$separator = '';
			if ( count( $nulls ) > 1 && is_string( $content[ $first + 1 ] ) ) {
				$separator = $content[ $first + 1 ];
			}
		} else {
			// Empty container: insert the children before its closing tag.
			$html      = implode( '', $content );
			$prefix    = $html;
			$suffix    = '';
			$separator = '';
			if ( preg_match( '#^(.*)(</[a-zA-Z0-9-]+>\s*)$#s', $html, $matches ) ) {
				$prefix = $matches[1];
				$suffix = $matches[2];
			}
		}

		$new_content = array();
		if ( '' !== $prefix ) {
			$new_content[] = $prefix;
		}
		for ( $i = 0; $i < $count; $i++ ) {
			if ( $i > 0 && '' !== $separator ) {
				$new_content[] = $separator;
			}
			$new_content[] = null;
		}
		if ( '' !== $suffix ) {
			$new_content[] = $suffix;
		}

		$block['innerContent'] = $new_content;
	}

	/**
	 * Post fields (title, excerpt, slug) from the input.
	 *
	 * @param array $input Ability input.
	 * @return array Post data for wp_insert_post() / wp_update_post().
	 */
	public static function get_post_fields( $input ) {
		$fields = array();
		if ( isset( $input['title'] ) ) {
			$fields['post_title'] = (string) $input['title'];
		}
		if ( isset( $input['excerpt'] ) ) {
			$fields['post_excerpt'] = (string) $input['excerpt'];
		}
		if ( isset( $input['slug'] ) ) {
			$fields['post_name'] = sanitize_title( $input['slug'] );
		}

		return $fields;
	}

	/**
	 * Applies the featured image, categories and tags of the input to a post.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $input    Ability input.
	 * @param array $warnings Warnings, completed by reference.
	 */
	public static function apply_relations( $post_id, $input, &$warnings ) {
		if ( isset( $input['featured_image_id'] ) ) {
			$image_id = (int) $input['featured_image_id'];
			if ( 0 === $image_id ) {
				delete_post_thumbnail( $post_id );
			} elseif ( wp_attachment_is_image( $image_id ) ) {
				set_post_thumbnail( $post_id, $image_id );
			} else {
				/* translators: %d: attachment ID. */
				$warnings[] = sprintf( __( 'Image mise en avant ignorée : le média %d n\'est pas une image.', 'eo-tools' ), $image_id );
			}
		}

		$post_type = get_post_type( $post_id );

		if ( isset( $input['categories'] ) ) {
			if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
				$ids = array();
				foreach ( (array) $input['categories'] as $category ) {
					if ( is_numeric( $category ) && term_exists( (int) $category, 'category' ) ) {
						$ids[] = (int) $category;
						continue;
					}
					$term = term_exists( (string) $category, 'category' );
					if ( ! $term && current_user_can( 'manage_categories' ) ) {
						$term = wp_insert_term( (string) $category, 'category' );
					}
					if ( $term && ! is_wp_error( $term ) ) {
						$ids[] = (int) $term['term_id'];
					} else {
						/* translators: %s: category. */
						$warnings[] = sprintf( __( 'Catégorie ignorée : %s.', 'eo-tools' ), $category );
					}
				}
				wp_set_post_categories( $post_id, $ids );
			} else {
				$warnings[] = __( 'Catégories ignorées : ce type de contenu n\'en a pas.', 'eo-tools' );
			}
		}

		if ( isset( $input['tags'] ) ) {
			if ( is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
				wp_set_post_tags( $post_id, array_map( 'strval', (array) $input['tags'] ) );
			} else {
				$warnings[] = __( 'Étiquettes ignorées : ce type de contenu n\'en a pas.', 'eo-tools' );
			}
		}
	}

	/**
	 * Common response of the writing abilities.
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $warnings Warnings.
	 * @return array
	 */
	public static function response( $post, $warnings ) {
		return array(
			'id'           => $post->ID,
			'type'         => $post->post_type,
			'status'       => $post->post_status,
			'title'        => $post->post_title,
			'edit_link'    => get_edit_post_link( $post->ID, 'raw' ),
			'preview_link' => get_preview_post_link( $post ),
			'warnings'     => array_values( array_unique( $warnings ) ),
			'next_step'    => self::check_hint( $post->ID ),
		);
	}

	/**
	 * Reminds the AI to check its work before sending the preview link.
	 *
	 * @param int $post_id Content or proposal ID.
	 * @return string
	 */
	public static function check_hint( $post_id ) {
		/* translators: %d: content ID. */
		return sprintf( __( 'Vérifiez le rendu avec eo-tools/check-render (id %d) et corrigez les problèmes signalés avant de donner le lien d\'aperçu à l\'utilisateur.', 'eo-tools' ), $post_id );
	}
}
