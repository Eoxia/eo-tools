<?php
/**
 * Block helpers for the MCP abilities.
 *
 * The AI works with a simplified block tree rather than raw serialized markup:
 * { name, attributes, html, inner_blocks }.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Blocks {

	/**
	 * Block namespaces the AI may use.
	 *
	 * @return string[]
	 */
	public static function get_namespaces() {
		/**
		 * Filters the block namespaces exposed to the AI.
		 *
		 * @param string[] $namespaces Namespaces, e.g. "core", "eo-blocks".
		 */
		return (array) apply_filters( 'eo_tools_mcp_block_namespaces', array( 'core', 'eo-blocks' ) );
	}

	/**
	 * Returns the block types the AI may use.
	 *
	 * Blocks hidden from the inserter are skipped, unless they are child blocks
	 * (e.g. core/column), which are needed inside their parent.
	 *
	 * @return \WP_Block_Type[] Keyed by block name.
	 */
	public static function get_block_types() {
		$namespaces = self::get_namespaces();
		$types      = array();

		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( ! in_array( strtok( $name, '/' ), $namespaces, true ) ) {
				continue;
			}

			$in_inserter = ! isset( $type->supports['inserter'] ) || false !== $type->supports['inserter'];
			if ( ! $in_inserter && empty( $type->parent ) && empty( $type->ancestor ) ) {
				continue;
			}

			$types[ $name ] = $type;
		}

		ksort( $types );

		return $types;
	}

	/**
	 * Usage guide of a block for the AI.
	 *
	 * EO Tools documents the main core blocks; other plugins (EO Blocks) document
	 * their own blocks through the "eo_tools_mcp_block_guide" filter.
	 *
	 * @param string $name Block name.
	 * @return string Guide, empty when the block has none.
	 */
	public static function get_guide( $name ) {
		$guides = array(
			'core/heading' => __( 'Le thème affiche souvent déjà le titre de la page en H1 : commencez le contenu en H2, puis H3 pour les sous-parties, sans sauter de niveau.', 'eo-tools' ),
			'core/group'   => __( 'Conteneur de section. Section pleine largeur avec fond : "align": "full", une couleur de fond (backgroundColor ou style.color.background), des marges internes (style.spacing.padding) et "layout": {"type": "constrained"}. Éléments côte à côte sur une ligne : "layout": {"type": "flex", "flexWrap": "nowrap"}.', 'eo-tools' ),
			'core/columns' => __( 'N\'accepte que des core/column. 2 à 4 colonnes en général ; largeur optionnelle par colonne ("width": "33.33%"). Sur mobile, les colonnes s\'empilent.', 'eo-tools' ),
			'core/image'   => __( 'Importez d\'abord l\'image avec eo-tools/import-image (ou trouvez-la avec eo-tools/search-images) et utilisez le bloc renvoyé. Renseignez toujours "alt".', 'eo-tools' ),
			'core/buttons' => __( 'Conteneur de core/button. Pour centrer : "layout": {"type": "flex", "justifyContent": "center"}.', 'eo-tools' ),
			'core/button'  => __( 'Toujours dans un core/buttons, avec "text" et "url". Le style contour s\'obtient avec "className": "is-style-outline".', 'eo-tools' ),
		);

		/**
		 * Filters the usage guide of a block for the AI.
		 *
		 * @param string $guide Guide (plain text or Markdown), empty when none.
		 * @param string $name  Block name.
		 */
		return (string) apply_filters( 'eo_tools_mcp_block_guide', isset( $guides[ $name ] ) ? $guides[ $name ] : '', $name );
	}

	/**
	 * Converts parsed blocks (parse_blocks()) to the simplified tree.
	 *
	 * Each node has a "path" (e.g. "2.0"), used by the writing abilities to target a block.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @param string  $parent Path of the parent block.
	 * @return array[]
	 */
	public static function to_tree( $blocks, $parent = '' ) {
		$tree  = array();
		$index = 0;

		foreach ( $blocks as $block ) {
			$html = trim( (string) $block['innerHTML'] );

			// Whitespace between blocks.
			if ( empty( $block['blockName'] ) && '' === $html ) {
				continue;
			}

			$path = '' === $parent ? (string) $index : $parent . '.' . $index;
			++$index;

			$node = array(
				'path' => $path,
				'name' => empty( $block['blockName'] ) ? 'core/freeform' : $block['blockName'],
			);

			if ( ! empty( $block['attrs'] ) ) {
				$node['attributes'] = $block['attrs'];
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$node['inner_blocks'] = self::to_tree( $block['innerBlocks'], $path );
			} elseif ( '' !== $html ) {
				$node['html'] = $html;
			}

			$tree[] = $node;
		}

		return $tree;
	}
}
