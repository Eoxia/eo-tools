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
