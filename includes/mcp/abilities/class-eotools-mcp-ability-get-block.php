<?php
/**
 * Ability: details of a block type (attributes, styles, example…).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Block_Builder;
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
		return __( 'Donne le détail d\'un bloc : attributs (type, valeur par défaut, valeurs possibles), blocs parents ou enfants autorisés, styles, fonctionnalités et exemple. La section "writing" explique comment envoyer ce bloc aux outils de rédaction.', 'eo-tools' );
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
				'writing'        => $this->get_writing_help( $name ),
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

	/**
	 * Explains how to send this block to the writing abilities.
	 *
	 * @param string $name Block name.
	 * @return array
	 */
	private function get_writing_help( $name ) {
		$builders = Eotools_Mcp_Block_Builder::get_builders();

		if ( isset( $builders[ $name ] ) ) {
			$attributes = $builders[ $name ]['attributes'];
			if ( $builders[ $name ]['wrapper'] ) {
				$attributes = array_merge( $attributes, Eotools_Mcp_Block_Builder::WRAPPER_ATTRIBUTES );
			}

			return array(
				'mode'                 => 'generated',
				'supported_attributes' => array_values( array_unique( $attributes ) ),
				'help'                 => __( 'Envoyez seulement le nom et les attributs : EO Tools génère le HTML. Les autres attributs sont ignorés.', 'eo-tools' ),
			);
		}

		if ( 0 === strpos( $name, 'eo-blocks/' ) || in_array( $name, Eotools_Mcp_Block_Builder::SERVER_RENDERED_BLOCKS, true ) ) {
			return array(
				'mode' => 'server_rendered',
				'help' => __( 'Bloc affiché par le serveur : envoyez le nom, les attributs et éventuellement les blocs enfants, sans HTML.', 'eo-tools' ),
			);
		}

		return array(
			'mode' => 'html_required',
			'help' => __( 'EO Tools ne sait pas générer ce bloc : il faut fournir son HTML exact (champ "html"), par exemple copié depuis eo-tools/get-post. Préférez un bloc généré.', 'eo-tools' ),
		);
	}
}
