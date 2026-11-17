<?php
/**
 * Ability: change the cookie list of the EO Tools cookie manager and validate it.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Cookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Update_Cookies extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'update-cookies';
	}

	public function get_label() {
		return __( 'Modifier et valider la liste des cookies', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Modifie la liste des cookies du gestionnaire d\'EO Tools (onglet « Gérer les cookies »), affichée aux visiteurs dans le bandeau : ajout ou modification par nom ("cookies"), suppression ("remove"). "validate": true enregistre la validation de la liste dans le rapport de consentements, comme le bouton « Validation Admin » : ne validez qu\'une liste vérifiée (catégories justes, descriptions en français, pas de cookie inventé). Sans paramètre, renvoie la liste actuelle et si elle est validée. Chaque modification est notée dans le rapport de consentements.', 'eo-tools' );
	}

	public function get_group() {
		return 'cookies';
	}

	public function get_risk() {
		return self::RISK_WRITE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'cookies'  => array(
					'type'        => 'array',
					'description' => __( 'Cookies à ajouter ou à modifier, retrouvés par leur nom. Seuls les champs indiqués changent.', 'eo-tools' ),
					'items'       => array(
						'type'                 => 'object',
						'properties'           => array(
							'name'          => array( 'type' => 'string' ),
							'category'      => array(
								'type' => 'string',
								'enum' => Eotools_Mcp_Cookies::CATEGORIES,
							),
							'domain'        => array(
								'type'        => 'string',
								'description' => __( 'Domaine qui dépose le cookie, vide pour le site lui-même.', 'eo-tools' ),
							),
							'duration_days' => array(
								'type'        => 'integer',
								'description' => __( 'Durée de vie en jours (0 = session, 395 au plus).', 'eo-tools' ),
							),
							'description'   => array(
								'type'        => 'string',
								'description' => __( 'À quoi sert le cookie, en français, pour les visiteurs.', 'eo-tools' ),
							),
						),
						'required'             => array( 'name' ),
						'additionalProperties' => false,
					),
				),
				'remove'   => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => __( 'Noms des cookies à retirer de la liste.', 'eo-tools' ),
				),
				'validate' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Valider la liste obtenue (validation administrateur, notée dans le rapport de consentements).', 'eo-tools' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Cookies::update(
			isset( $input['cookies'] ) ? $input['cookies'] : array(),
			isset( $input['remove'] ) ? $input['remove'] : array(),
			! empty( $input['validate'] )
		);
	}
}
