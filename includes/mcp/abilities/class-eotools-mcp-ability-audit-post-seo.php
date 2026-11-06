<?php
/**
 * Ability: detailed SEO audit of one page or post.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Audit_Post_Seo extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'audit-post-seo';
	}

	public function get_label() {
		return __( 'Auditer le SEO d\'une page', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Audit SEO détaillé d\'une page, d\'un article ou de tout autre contenu, publié ou en brouillon. Indique le contenu par "id" ou par son adresse "url". Renvoie : titre SEO et méta description (avec leur longueur), données Yoast si Yoast est actif (requête cible, scores SEO et lisibilité, noindex, canonique, contenu pilier), nombre de mots, plan des titres H1 à H6, images sans texte alternatif, image mise en avant, liens entrants (contenus, menus et modèles qui pointent vers la page), liens sortants internes et externes (y compris les liens vers des contenus non publiés ou des adresses inconnues), titres et descriptions en double avec d\'autres contenus, présence de la requête cible (titre, slug, introduction, sous-titres, méta description, textes alternatifs, densité) et balises de la page telle que la voient les moteurs (title, description, robots, canonical, Open Graph, H1, données structurées). Les "findings" sont classés par gravité (critical, warning, info) : ce sont des constats, à toi d\'en faire un bilan priorisé et des recommandations concrètes. Paramètre "keyword" pour vérifier une autre requête que celle de Yoast (ou s\'il n\'y a pas Yoast). Pour corriger : eo-tools/get-post, puis eo-tools/update-draft (brouillon) ou eo-tools/propose-update (contenu publié). Pour le bilan du site entier : eo-tools/audit-site-seo.', 'eo-tools' );
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
				'id'      => array(
					'type'        => 'integer',
					'description' => __( 'Identifiant du contenu.', 'eo-tools' ),
				),
				'url'     => array(
					'type'        => 'string',
					'description' => __( 'Adresse de la page sur ce site (à la place de "id").', 'eo-tools' ),
				),
				'keyword' => array(
					'type'        => 'string',
					'description' => __( 'Requête cible à vérifier. Par défaut : la requête cible Yoast du contenu.', 'eo-tools' ),
				),
				'refresh' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Relancer l\'analyse du site (utilisée pour les doublons) au lieu de celle en cache (15 minutes).', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		$post = null;
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( (int) $input['id'] );
		} elseif ( ! empty( $input['url'] ) ) {
			$post = Eotools_Mcp_Seo::find_post_by_url( esc_url_raw( $input['url'] ) );
		}

		if ( ! $post || 'trash' === $post->post_status || ! in_array( $post->post_type, Eotools_Mcp_Seo::get_post_types(), true ) ) {
			return new \WP_Error( 'eo_tools_post_not_found', __( 'Contenu introuvable. Indiquez "id" (voir eo-tools/list-posts) ou l\'adresse "url" d\'une page de ce site. Pour la page d\'accueil construite dans un modèle, utilisez eo-tools/audit-site-seo (section "indexing").', 'eo-tools' ) );
		}

		return Eotools_Mcp_Seo::audit_post(
			$post,
			isset( $input['keyword'] ) ? sanitize_text_field( $input['keyword'] ) : '',
			! empty( $input['refresh'] )
		);
	}
}
