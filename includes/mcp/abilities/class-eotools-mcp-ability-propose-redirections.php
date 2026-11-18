<?php
/**
 * Ability: propose 301 redirections, applied by a human.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Proposals;
use EoTools\Includes\Mcp\Eotools_Mcp_Redirections;
use EoTools\Includes\Mcp\Eotools_Mcp_Seo_Changes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Propose_Redirections extends Eotools_Mcp_Ability {

	const MAX_ITEMS = 50;

	public function get_slug() {
		return 'propose-redirections';
	}

	public function get_label() {
		return __( 'Proposer des redirections', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Propose des redirections permanentes (301) pour des adresses en erreur 404 : anciennes pages, liens cassés (section "errors" de eo-tools/audit-site-seo). Elles sont enregistrées dans EO Tools (module 404) et attendent la validation de l\'utilisateur dans EO Tools > MCP > À valider : vous ne pouvez pas les appliquer. Redirigez vers la page la plus proche par le sujet (pas systématiquement vers l\'accueil) et ne redirigez pas les tentatives de robots (fichiers .php, .env…). Une adresse qui affiche encore un contenu publié ne peut pas être redirigée : quand une proposition change le slug d\'un contenu publié, son ancienne adresse est redirigée automatiquement à l\'application. Proposer à nouveau la même adresse remplace la proposition en attente.', 'eo-tools' );
	}

	public function get_group() {
		return 'seo';
	}

	public function get_risk() {
		return self::RISK_SENSITIVE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'redirections' => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => self::MAX_ITEMS,
					'items'    => array(
						'type'                 => 'object',
						'properties'           => array(
							'from'   => array(
								'type'        => 'string',
								'description' => __( 'Adresse à rediriger : chemin ("/ancienne-page/") ou adresse complète du site.', 'eo-tools' ),
							),
							'to'     => array(
								'type'        => 'string',
								'description' => __( 'Destination : chemin ("/nouvelle-page/"), adresse complète du site, ou adresse d\'un autre site.', 'eo-tools' ),
							),
							'reason' => array(
								'type'        => 'string',
								'description' => __( 'Pourquoi cette destination, en une phrase, pour l\'utilisateur qui valide.', 'eo-tools' ),
							),
						),
						'required'             => array( 'from', 'to' ),
						'additionalProperties' => false,
					),
				),
			),
			'required'             => array( 'redirections' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		if ( ! Eotools_Mcp_Redirections::is_available() ) {
			return new \WP_Error( 'eo_tools_redirection_table', __( 'Le module 404 d\'EO Tools n\'est pas installé sur ce site (table des redirections absente).', 'eo-tools' ) );
		}

		$proposed = array();
		$errors   = array();

		foreach ( array_slice( (array) $input['redirections'], 0, self::MAX_ITEMS ) as $redirection ) {
			$from  = isset( $redirection['from'] ) ? (string) $redirection['from'] : '';
			$check = Eotools_Mcp_Redirections::check( $from, isset( $redirection['to'] ) ? (string) $redirection['to'] : '' );

			if ( is_wp_error( $check ) ) {
				$errors[] = array(
					'from'  => $from,
					'error' => $check->get_error_message(),
				);
				continue;
			}

			$item = Eotools_Mcp_Seo_Changes::add(
				Eotools_Mcp_Seo_Changes::KIND_REDIRECTION,
				$check['from'],
				array(
					'from'     => $check['from'],
					'to'       => $check['to'],
					'replaces' => $check['replaces'],
					'reason'   => isset( $redirection['reason'] ) ? sanitize_text_field( (string) $redirection['reason'] ) : '',
				)
			);
			if ( is_wp_error( $item ) ) {
				$errors[] = array(
					'from'  => $from,
					'error' => $item->get_error_message(),
				);
				break;
			}

			$proposed[] = array(
				'from'     => Eotools_Mcp_Redirections::source_url( $check['from'] ),
				'to'       => Eotools_Mcp_Redirections::target_url( $check['to'] ),
				'replaces' => $check['replaces'] ? Eotools_Mcp_Redirections::target_url( $check['replaces'] ) : null,
				'warnings' => $check['warnings'],
			);
		}

		return array(
			'proposed'       => $proposed,
			'errors'         => $errors,
			'validation_url' => Eotools_Mcp_Proposals::admin_url(),
			'message'        => $proposed ? __( 'Redirections proposées, rien n\'est encore actif. Donnez le lien de validation à l\'utilisateur : il les applique ou les rejette depuis EO Tools > MCP > À valider.', 'eo-tools' ) : __( 'Aucune redirection proposée.', 'eo-tools' ),
		);
	}
}
