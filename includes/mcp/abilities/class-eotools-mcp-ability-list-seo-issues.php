<?php
/**
 * Ability: contents affected by one SEO issue.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_List_Seo_Issues extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'list-seo-issues';
	}

	public function get_label() {
		return __( 'Lister les problèmes SEO', 'eo-tools' );
	}

	public function get_description() {
		$types = array();
		foreach ( Eotools_Mcp_Seo::get_issue_types() as $type => $config ) {
			$types[] = $type . ' (' . $config['label'] . ')';
		}
		$term_types = array();
		foreach ( Eotools_Mcp_Seo::get_term_issue_types() as $type => $config ) {
			$term_types[] = $type . ' (' . $config['label'] . ')';
		}

		/* translators: 1: list of content issue types, 2: list of term issue types. */
		return sprintf( __( 'Liste paginée de tous les contenus publiés touchés par un problème SEO trouvé par eo-tools/audit-site-seo, avec pour chacun son titre SEO, sa méta description, sa requête cible, son score Yoast le cas échéant, son nombre de mots, ses images sans texte alternatif et ses liens internes. Pour les doublons, "same_as" donne les identifiants des contenus identiques. Types de problèmes des contenus : %1$s. Types de problèmes des archives de catégories, étiquettes… (section "terms" de l\'audit ; chaque archive avec ses champs SEO saisis, à corriger avec eo-tools/propose-term-seo) : %2$s. Le type "media_without_alt" liste les images de la médiathèque sans texte alternatif, à compléter avec eo-tools/update-media.', 'eo-tools' ), implode( ', ', $types ), implode( ', ', $term_types ) );
	}

	public function get_group() {
		return 'seo';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'issue'     => array(
					'type'        => 'string',
					'enum'        => array_merge( array_keys( Eotools_Mcp_Seo::get_issue_types() ), array_keys( Eotools_Mcp_Seo::get_term_issue_types() ), array( 'media_without_alt' ) ),
					'description' => __( 'Type de problème (clé de "content.data.issues" dans eo-tools/audit-site-seo).', 'eo-tools' ),
				),
				'post_type' => array(
					'type'        => 'string',
					'enum'        => Eotools_Mcp_Seo::get_post_types(),
					'description' => __( 'Limiter à un type de contenu ("post", "page"…).', 'eo-tools' ),
				),
				'taxonomy'  => array_filter(
					array(
						'type'        => 'string',
						'enum'        => Eotools_Mcp_Seo::get_taxonomies(),
						'description' => __( 'Problèmes des archives uniquement : limiter à une taxonomie ("category", "post_tag"…).', 'eo-tools' ),
					)
				),
				'per_page'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 50,
				),
				'page'      => array(
					'type'    => 'integer',
					'minimum' => 1,
					'default' => 1,
				),
				'refresh'   => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Relancer l\'analyse des contenus au lieu d\'utiliser celle en cache (15 minutes).', 'eo-tools' ),
				),
			),
			'required'             => array( 'issue' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		if ( isset( $input['issue'] ) && 'media_without_alt' === $input['issue'] ) {
			return Eotools_Mcp_Seo::list_media_without_alt(
				isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
				isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 50
			);
		}

		if ( isset( $input['issue'] ) && 0 === strpos( (string) $input['issue'], 'term_' ) ) {
			return Eotools_Mcp_Seo::list_term_issue(
				(string) $input['issue'],
				isset( $input['taxonomy'] ) ? (string) $input['taxonomy'] : '',
				isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
				isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 50,
				! empty( $input['refresh'] )
			);
		}

		return Eotools_Mcp_Seo::list_issue(
			isset( $input['issue'] ) ? (string) $input['issue'] : '',
			isset( $input['post_type'] ) ? (string) $input['post_type'] : '',
			isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
			isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 50,
			! empty( $input['refresh'] )
		);
	}
}
