<?php
/**
 * Ability: install a Google font on the site.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Fonts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Install_Font extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'install-font';
	}

	public function get_label() {
		return __( 'Installer une police', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Installe une police Google Fonts dans la bibliothèque de polices du site : les fichiers sont téléchargés et hébergés sur le site (pas d\'appel à Google pour les visiteurs, conforme RGPD). N\'installe que les graisses utiles (par défaut 400 et 700). Le site ne change pas : utilisez ensuite la police dans eo-tools/propose-styles-update ("add_font_families").', 'eo-tools' );
	}

	public function get_group() {
		return 'styles';
	}

	public function get_risk() {
		return self::RISK_WRITE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'family'  => array(
					'type'        => 'string',
					'description' => __( 'Nom ou slug de la police (ex. "Playfair Display").', 'eo-tools' ),
				),
				'weights' => array(
					'type'        => 'array',
					'items'       => array( 'type' => array( 'string', 'integer' ) ),
					'default'     => array( '400', '700' ),
					'description' => __( 'Graisses à installer (ex. ["400", "600", "700"]).', 'eo-tools' ),
				),
				'italic'  => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Installer aussi les italiques.', 'eo-tools' ),
				),
			),
			'required'             => array( 'family' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'edit_theme_options' ) && current_user_can( 'upload_files' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Fonts::install( $input );
	}
}
