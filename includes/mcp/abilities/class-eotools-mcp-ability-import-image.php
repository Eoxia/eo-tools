<?php
/**
 * Ability: import an image into the media library.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Import_Image extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'import-image';
	}

	public function get_label() {
		return __( 'Importer une image', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Ajoute une image à la médiathèque, depuis une photo Openverse ("openverse_id", trouvée avec eo-tools/search-images), une adresse publique ("url"), un SVG que vous dessinez ("svg" : formes, dégradés, motifs, icônes ; il est nettoyé) ou une image encodée en base64 ("data"). Renseignez toujours "alt". Renvoie l\'identifiant du média et un bloc core/image prêt à insérer.', 'eo-tools' );
	}

	public function get_group() {
		return 'media';
	}

	public function get_risk() {
		return self::RISK_WRITE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'openverse_id' => array(
					'type'        => 'string',
					'description' => __( 'Identifiant Openverse de l\'image.', 'eo-tools' ),
				),
				'url'          => array(
					'type'        => 'string',
					'description' => __( 'Adresse publique d\'une image JPEG, PNG, GIF, WebP ou AVIF.', 'eo-tools' ),
				),
				'svg'          => array(
					'type'        => 'string',
					'description' => __( 'Code SVG complet (<svg …>…</svg>), avec un viewBox. Scripts et références externes sont supprimés.', 'eo-tools' ),
				),
				'data'         => array(
					'type'        => 'string',
					'description' => __( 'Image JPEG, PNG, GIF, WebP ou AVIF encodée en base64.', 'eo-tools' ),
				),
				'alt'          => array(
					'type'        => 'string',
					'description' => __( 'Texte alternatif décrivant l\'image (accessibilité, SEO).', 'eo-tools' ),
				),
				'caption'      => array(
					'type'        => 'string',
					'description' => __( 'Légende. Pour Openverse, le crédit de l\'auteur est ajouté automatiquement.', 'eo-tools' ),
				),
				'title'        => array( 'type' => 'string' ),
				'filename'     => array(
					'type'        => 'string',
					'description' => __( 'Nom du fichier, sans extension (ex. "equipe-bureau").', 'eo-tools' ),
				),
			),
			'required'             => array( 'alt' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'upload_files' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Media::import( $input );
	}
}
