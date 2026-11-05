<?php
/**
 * Ability: screenshot of a content, returned as an image to the AI.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Admin\Eotools_Mcp_Page;
use EoTools\Includes\Mcp\Eotools_Mcp_Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Screenshot extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'screenshot';
	}

	public function get_label() {
		return __( 'Capture d\'écran', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Renvoie une capture d\'écran de la page (haut de page sur ordinateur, ou mobile) pour vérifier visuellement la mise en page d\'un brouillon, d\'une proposition ou d\'un contenu publié. La capture est réalisée par le service mShots de WordPress.com via un lien d\'aperçu temporaire ; elle prend quelques secondes.', 'eo-tools' );
	}

	public function get_group() {
		return 'preview';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'     => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Identifiant du contenu, ou de la proposition ("proposal_id").', 'eo-tools' ),
				),
				'device' => array(
					'type'    => 'string',
					'enum'    => array( 'desktop', 'mobile' ),
					'default' => 'desktop',
				),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return ! empty( $input['id'] ) && get_post( (int) $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
	}

	public function execute( $input ) {
		$post = get_post( (int) $input['id'] );

		if ( ! $post || ! is_post_type_viewable( $post->post_type ) ) {
			return new \WP_Error( 'eo_tools_post_not_found', __( 'Contenu introuvable ou sans page visible.', 'eo-tools' ) );
		}

		// The screenshot service loads the page from the Internet.
		if ( ! Eotools_Mcp_Page::is_site_reachable_online() ) {
			return new \WP_Error( 'eo_tools_screenshot_offline', __( 'Capture impossible : le site n\'est pas accessible publiquement en HTTPS. Utilisez eo-tools/check-render.', 'eo-tools' ) );
		}

		$image = Eotools_Mcp_Preview::screenshot( $post, isset( $input['device'] ) ? $input['device'] : 'desktop' );
		if ( is_wp_error( $image ) ) {
			return $image;
		}

		// MCP Adapter turns this structure into an image content block.
		return array(
			'type'     => 'image',
			'results'  => $image['body'],
			'mimeType' => $image['mime'],
		);
	}
}
