<?php
/**
 * Ability: site-wide SEO audit.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Audit_Site_Seo extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'audit-site-seo';
	}

	public function get_label() {
		return __( 'Auditer le SEO du site', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Audit SEO du site dans son ensemble. Renvoie quatre sections : "settings" (visibilité pour les moteurs, permaliens, titre et slogan, HTTPS, extensions SEO et réglages Yoast), "indexing" (robots.txt, plan de site XML, redirection http vers https, balises SEO de la page d\'accueil telle que la voient les moteurs), "content" (analyse des contenus publiés : titres SEO et méta descriptions manquants, trop longs ou en double, contenus courts, images sans texte alternatif, contenus orphelins sans lien interne entrant, noindex, scores Yoast…, avec le nombre de contenus concernés et quelques exemples) et "errors" (erreurs 404 relevées par EO Tools ces 30 derniers jours et redirections). Chaque section a des "findings" classés par gravité (critical, warning, info) : ce sont des constats, à toi d\'en faire un bilan priorisé et des recommandations concrètes pour l\'utilisateur. Pour la liste complète des contenus touchés par un problème, utilise eo-tools/list-seo-issues. Pour corriger un contenu : eo-tools/get-post, puis eo-tools/update-draft (brouillon) ou eo-tools/propose-update (contenu publié). L\'analyse des contenus est gardée en cache 15 minutes : refresh=true pour la relancer après des corrections.', 'eo-tools' );
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
				'sections' => array(
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
						'enum' => Eotools_Mcp_Seo::SECTIONS,
					),
					'description' => __( 'Sections à inclure. Par défaut : toutes.', 'eo-tools' ),
				),
				'examples' => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'maximum'     => 20,
					'default'     => 5,
					'description' => __( 'Nombre d\'exemples de contenus renvoyés pour chaque problème de la section "content".', 'eo-tools' ),
				),
				'refresh'  => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Relancer l\'analyse des contenus au lieu d\'utiliser celle en cache (15 minutes).', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Seo::audit_site(
			isset( $input['sections'] ) ? (array) $input['sections'] : Eotools_Mcp_Seo::SECTIONS,
			isset( $input['examples'] ) ? max( 0, min( 20, (int) $input['examples'] ) ) : 5,
			! empty( $input['refresh'] )
		);
	}
}
