<?php
/**
 * Ability: propose a CSS or PHP snippet.
 *
 * The snippet is saved switched off (or, for an active snippet, as a new
 * version waiting for validation). Only a human switches it on.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Propose_Snippet extends Eotools_Mcp_Ability_Snippet {

	public function get_slug() {
		return 'propose-snippet';
	}

	public function get_label() {
		return __( 'Proposer du code (snippet)', 'eo-tools' );
	}

	public function get_description() {
		return __( 'DERNIER RECOURS, quand la demande est impossible sans code : préférez toujours un réglage, un bloc, un style de bloc, un modèle (eo-tools/propose-template-update) ou une extension installée. Propose un snippet CSS ou PHP : il est enregistré DÉSACTIVÉ et l\'utilisateur l\'active lui-même depuis EO Tools > MCP > Snippets (vous ne pouvez pas l\'activer). Avec "id", modifie un snippet existant : s\'il est actif, le nouveau code attend la validation de l\'utilisateur. Règles : un seul objectif par snippet, titre court et description d\'une ou deux phrases en français ; lisez le code avec eo-tools/read-source et eo-tools/search-source pour trouver les vraies classes CSS et les vrais hooks (n\'en inventez jamais). CSS : préférez les variables du thème (var(--wp--preset--color--…)), des sélecteurs précis, pas de !important sauf nécessité, pensez mobile. PHP : sans balise <?php ni namespace ; le code est chargé pendant plugins_loaded, travaillez donc dans des hooks (init, wp_enqueue_scripts, the_content…) ; préfixez les fonctions par eotools_ dans un if ( ! function_exists() ) ; échappez toute sortie, nettoyez toute entrée, vérifiez les droits ; vérifiez qu\'une extension est active avant d\'utiliser ses fonctions. Interdit sauf demande explicite : commandes shell, eval, écriture de fichiers, création d\'utilisateurs ou changement de rôles, envoi d\'e-mails, appels à des services externes. Ensuite, expliquez à l\'utilisateur ce que fait le code et invitez-le à l\'activer dans l\'onglet Snippets.', 'eo-tools' );
	}

	public function get_risk() {
		return self::RISK_SENSITIVE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'          => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Snippet à modifier ; absent pour en créer un.', 'eo-tools' ),
				),
				'type'        => array(
					'type'        => 'string',
					'enum'        => Eotools_Mcp_Snippets::TYPES,
					'description' => __( 'Obligatoire à la création : css ou php. Préférez le CSS quand il suffit.', 'eo-tools' ),
				),
				'title'       => array(
					'type'        => 'string',
					'description' => __( 'Nom court.', 'eo-tools' ),
				),
				'description' => array(
					'type'        => 'string',
					'description' => __( 'Ce que fait le code et pourquoi, en une ou deux phrases simples.', 'eo-tools' ),
				),
				'scope'       => array(
					'type'        => 'string',
					'enum'        => Eotools_Mcp_Snippets::SCOPES,
					'description' => __( 'Où le code s\'exécute : le site public (front, aussi chargé dans l\'éditeur de blocs), l\'administration (admin) ou partout (everywhere).', 'eo-tools' ),
				),
				'code'        => array(
					'type'        => 'string',
					'description' => __( 'Code CSS, ou PHP sans la balise <?php. Envoyez le code complet.', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function execute( $input ) {
		$id = Eotools_Mcp_Snippets::propose( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$snippet = Eotools_Mcp_Snippets::get( $id, false );
		$pending = ! empty( $snippet['pending_version'] );
		$data    = array(
			'id'        => $id,
			'title'     => $snippet['title'],
			'type'      => $snippet['type'],
			'scope'     => $snippet['scope'],
			'active'    => $snippet['active'],
			'admin_url' => Eotools_Mcp_Snippets::admin_url( $id ),
			'message'   => $pending
				? __( 'Ce snippet est actif : le nouveau code attend la validation de l\'utilisateur, l\'ancien continue de tourner. Donnez-lui le lien "admin_url" pour appliquer la nouvelle version.', 'eo-tools' )
				: __( 'Snippet enregistré, désactivé. Expliquez à l\'utilisateur ce qu\'il fait et donnez-lui le lien "admin_url" pour l\'activer. Pour le PHP, le site est testé avec le snippet avant son activation : si cela échoue, il reste désactivé et l\'erreur s\'affiche.', 'eo-tools' ),
		);

		$preview = Eotools_Mcp_Snippets::preview_url( $id );
		if ( $preview ) {
			$data['preview_link'] = $preview;
			$data['message']     .= ' ' . __( 'Le lien "preview_link" montre le site avec ce CSS appliqué (administrateur connecté).', 'eo-tools' );
		}

		if ( 'php' === $snippet['type'] ) {
			$code      = $pending ? Eotools_Mcp_Snippets::get_pending( $id )['code'] : get_post_field( 'post_content', $id );
			$sensitive = Eotools_Mcp_Snippets::sensitive( $code );
			if ( $sensitive ) {
				$data['warnings'] = array(
					/* translators: %s: list of PHP functions. */
					sprintf( __( 'Le code utilise des fonctions sensibles (%s) : l\'utilisateur en sera averti. Retirez-les si elles ne sont pas indispensables.', 'eo-tools' ), implode( ', ', $sensitive ) ),
				);
			}
		}

		return $data;
	}
}
