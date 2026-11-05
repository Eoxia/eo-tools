<?php
/**
 * Ability: details of a block type (attributes, styles, example…).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Get_Block extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'get-block';
	}

	public function get_label() {
		return __( 'Détail d\'un bloc', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Donne le détail d\'un bloc : attributs (type, valeur par défaut, valeurs possibles), blocs parents ou enfants autorisés, styles, fonctionnalités et exemple. Les attributs ayant une "source" sont lus depuis le HTML du bloc : ils doivent figurer dans son HTML. Un bloc "dynamic" est rendu par le serveur : seuls ses attributs comptent.', 'eo-tools' );
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
				'name' => array(
					'type'        => 'string',
					'description' => __( 'Nom du bloc, par exemple "core/heading" ou "eo-blocks/accordion".', 'eo-tools' ),
				),
			),
			'required'             => array( 'name' ),
			'additionalProperties' => false,
		);
	}

	public function execute( $input ) {
		$types = Eotools_Mcp_Blocks::get_block_types();
		$name  = (string) $input['name'];

		if ( ! isset( $types[ $name ] ) ) {
			return new \WP_Error( 'eo_tools_block_not_found', __( 'Bloc inconnu ou non disponible. Utilisez eo-tools/list-blocks pour voir les blocs disponibles.', 'eo-tools' ) );
		}

		$type   = $types[ $name ];
		$styles = array();
		foreach ( \WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( $name ) as $style ) {
			$styles[] = array(
				'name'  => $style['name'],
				'label' => isset( $style['label'] ) ? $style['label'] : $style['name'],
			);
		}

		return array_filter(
			array(
				'name'           => $name,
				'title'          => $type->title,
				'description'    => $type->description,
				'category'       => $type->category,
				'keywords'       => $type->keywords,
				'dynamic'        => $type->is_dynamic(),
				'parent'         => $type->parent,
				'ancestor'       => $type->ancestor,
				'allowed_blocks' => $type->allowed_blocks,
				'attributes'     => $type->attributes,
				'supports'       => $type->supports,
				'styles'         => $styles,
				'example'        => $type->example,
			),
			function ( $value ) {
				return null !== $value && array() !== $value && '' !== $value;
			}
		);
	}
}
