<?php
/**
 * Ability: list the blocks the AI may use (core and EO Blocks).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_List_Blocks extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'list-blocks';
	}

	public function get_label() {
		return __( 'Lister les blocs disponibles', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Liste les blocs utilisables pour rédiger une page ou un article : blocs du cœur de WordPress (core/*) et blocs EO Blocks (eo-blocks/*). Privilégiez les blocs EO Blocks quand ils correspondent au besoin. Utilisez eo-tools/get-block pour connaître les attributs d\'un bloc avant de l\'utiliser.', 'eo-tools' );
	}

	public function get_group() {
		return 'blocks';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'namespace' => array(
					'type'        => 'string',
					'enum'        => array_merge( array( 'all' ), Eotools_Mcp_Blocks::get_namespaces() ),
					'default'     => 'all',
					'description' => __( 'Limite la liste à un espace de noms, par exemple "eo-blocks".', 'eo-tools' ),
				),
				'search'    => array(
					'type'        => 'string',
					'description' => __( 'Recherche dans le nom, le titre, la description et les mots-clés.', 'eo-tools' ),
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
				'blocks' => array( 'type' => 'array' ),
			),
		);
	}

	public function execute( $input ) {
		$namespace = isset( $input['namespace'] ) ? $input['namespace'] : 'all';
		$search    = isset( $input['search'] ) ? mb_strtolower( trim( $input['search'] ) ) : '';
		$blocks    = array();

		foreach ( Eotools_Mcp_Blocks::get_block_types() as $name => $type ) {
			if ( 'all' !== $namespace && strtok( $name, '/' ) !== $namespace ) {
				continue;
			}

			if ( '' !== $search ) {
				$haystack = mb_strtolower( implode( ' ', array_merge( array( $name, $type->title, $type->description ), (array) $type->keywords ) ) );
				if ( false === strpos( $haystack, $search ) ) {
					continue;
				}
			}

			$block = array(
				'name'        => $name,
				'title'       => $type->title,
				'description' => $type->description,
				'category'    => $type->category,
			);

			if ( ! empty( $type->parent ) ) {
				$block['parent'] = $type->parent;
			}

			$blocks[] = $block;
		}

		return array( 'blocks' => $blocks );
	}
}
