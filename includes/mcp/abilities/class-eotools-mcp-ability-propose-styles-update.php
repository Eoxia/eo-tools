<?php
/**
 * Ability: propose a change to the global styles.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Propose_Styles_Update extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'propose-styles-update';
	}

	public function get_label() {
		return __( 'Proposer de nouveaux styles', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Propose une modification des styles globaux (palette, polices, tailles, espacements, styles des liens, titres, boutons et blocs) sans toucher au site : la proposition est prévisualisée sur tout le site et l\'utilisateur l\'applique ou la rejette lui-même (vous ne pouvez pas l\'appliquer). "changes" suit le format theme.json ("settings" et "styles") et est fusionné avec les styles actuels : les objets sont fusionnés, les listes remplacées entièrement, null remet la valeur du thème. Palette : remplacez "settings.color.palette.theme" par la liste complète {slug, name, color} en gardant les slugs existants (le thème et ses blocs les utilisent). Utilisez les couleurs et polices par référence ("var(--wp--preset--color--slug)", "var(--wp--preset--font-family--slug)"). Pour une nouvelle police : eo-tools/search-fonts, eo-tools/install-font, puis "add_font_families". "variation" part d\'une variation de style du thème. Vérifiez ensuite le rendu et les contrastes avec eo-tools/check-render ("styles_proposal_id"). Appeler à nouveau complète la proposition en cours.', 'eo-tools' );
	}

	public function get_group() {
		return 'styles';
	}

	public function get_risk() {
		return self::RISK_SENSITIVE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'summary'           => array(
					'type'        => 'string',
					'description' => __( 'Ce que change la proposition, en une phrase simple pour l\'utilisateur (affichée sur l\'aperçu).', 'eo-tools' ),
				),
				'changes'           => array(
					'type'        => 'object',
					'description' => __( 'Modifications au format theme.json : { "settings": {…}, "styles": {…} }.', 'eo-tools' ),
				),
				'variation'         => array(
					'type'        => 'string',
					'description' => __( 'Titre d\'une variation de style du thème (eo-tools/get-styles) à utiliser comme point de départ.', 'eo-tools' ),
				),
				'add_font_families' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => __( 'Slugs de polices installées à rendre disponibles dans les styles.', 'eo-tools' ),
				),
				'restart'           => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Abandonne la proposition en cours et repart des styles actuels.', 'eo-tools' ),
				),
				'preview_url'       => array(
					'type'        => 'string',
					'description' => __( 'Page du site pour l\'aperçu (l\'accueil par défaut).', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( Eotools_Mcp_Styles::CAPABILITY );
	}

	public function execute( $input ) {
		if ( empty( $input['changes'] ) && empty( $input['variation'] ) && empty( $input['add_font_families'] ) && empty( $input['restart'] ) ) {
			return new \WP_Error( 'eo_tools_no_change', __( 'Aucune modification demandée : indiquez "changes", "variation" ou "add_font_families".', 'eo-tools' ) );
		}

		$result = Eotools_Mcp_Styles::propose( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$proposal = $result['proposal'];

		return array(
			'proposal_id'  => $proposal->ID,
			'preview_link' => Eotools_Mcp_Styles::preview_url( $proposal ),
			'user_styles'  => $result['user_data'],
			'warnings'     => $result['warnings'],
			'message'      => __( 'Styles enregistrés dans une proposition, le site n\'a pas changé. Donnez le lien d\'aperçu à l\'utilisateur (connecté en administrateur) : il pourra appliquer ou rejeter les nouveaux styles depuis cette page, ou depuis EO Tools > MCP > À valider.', 'eo-tools' ),
			/* translators: %d: proposal ID. */
			'next_step'    => sprintf( __( 'Vérifiez le rendu et les contrastes avec eo-tools/check-render (styles_proposal_id %d) et corrigez les problèmes avant de donner le lien à l\'utilisateur.', 'eo-tools' ), $proposal->ID ),
		);
	}
}
