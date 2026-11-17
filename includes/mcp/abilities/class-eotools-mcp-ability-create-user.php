<?php
/**
 * Ability: ask for a new user account, created once an administrator approves it.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Settings;
use EoTools\Includes\Mcp\Eotools_Mcp_Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Create_User extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'create-user';
	}

	public function get_label() {
		return __( 'Créer un compte utilisateur (avec validation)', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Demande la création d\'un compte utilisateur (ex. un compte éditeur pour le client). Le compte n\'est créé qu\'une fois la demande validée par un administrateur dans WordPress, en retapant son mot de passe, via le lien "approval_url" renvoyé : donnez ce lien à l\'utilisateur. Seuls les rôles qui ne permettent pas de gérer le site sont possibles (éditeur au maximum, jamais administrateur). Le nouvel utilisateur reçoit un e-mail de WordPress pour choisir son mot de passe : ne demandez, ne générez et n\'écrivez jamais de mot de passe. Demandez l\'adresse e-mail à l\'utilisateur, ne l\'inventez pas. Rappeler l\'outil avec la même adresse renvoie la demande en attente.', 'eo-tools' );
	}

	public function get_group() {
		return 'users';
	}

	public function get_risk() {
		return self::RISK_WRITE;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'email'      => array(
					'type'        => 'string',
					'description' => __( 'Adresse e-mail du nouvel utilisateur, donnée par l\'utilisateur.', 'eo-tools' ),
				),
				'role'       => array(
					'type'        => 'string',
					'enum'        => array_values( Eotools_Mcp_Settings::get_allowed_roles() ),
					'default'     => 'editor',
					'description' => __( 'Rôle du compte.', 'eo-tools' ),
				),
				'first_name' => array( 'type' => 'string' ),
				'last_name'  => array( 'type' => 'string' ),
				'reason'     => array(
					'type'        => 'string',
					'description' => __( 'Pourquoi ce compte, en une phrase, pour l\'administrateur.', 'eo-tools' ),
				),
			),
			'required'             => array( 'email' ),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'create_users' ) && current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Users::request( $input );
	}
}
