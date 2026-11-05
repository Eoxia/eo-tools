<?php
/**
 * Ability: search openly licensed photos on Openverse.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Search_Images extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'search-images';
	}

	public function get_label() {
		return __( 'Chercher des photos libres', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Cherche des photos et illustrations sous licence libre sur Openverse (la banque d\'images libres de WordPress). Rédigez la recherche en anglais pour de meilleurs résultats. Importez ensuite l\'image choisie avec eo-tools/import-image et son "openverse_id" : le crédit de l\'auteur est ajouté automatiquement. Si vous savez produire une image vous-même (SVG…), utilisez directement eo-tools/import-image.', 'eo-tools' );
	}

	public function get_group() {
		return 'media';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'query'       => array(
					'type'        => 'string',
					'description' => __( 'Mots-clés, de préférence en anglais.', 'eo-tools' ),
				),
				'orientation' => array(
					'type' => 'string',
					'enum' => array( 'wide', 'tall', 'square' ),
				),
				'category'    => array(
					'type' => 'string',
					'enum' => array( 'photograph', 'illustration', 'digitized_artwork' ),
				),
				'commercial'  => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => __( 'Uniquement les images utilisables sur un site commercial et modifiables.', 'eo-tools' ),
				),
				'per_page'    => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 20,
					'default' => 10,
				),
			),
			'required'             => array( 'query' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'upload_files' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Media::search(
			wp_parse_args(
				$input,
				array(
					'per_page'   => 10,
					'commercial' => true,
				)
			)
		);
	}
}
