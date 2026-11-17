<?php
/**
 * Users: the AI asks for a new account, an administrator approves it.
 *
 * - The account is only created when an administrator approves the request in
 *   EO Tools > MCP > Autorisations, after typing their password again.
 * - Only roles unable to manage the site can be given (editor and below).
 * - The AI never sees a password: WordPress e-mails the new user a link to
 *   choose one.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Users {

	/**
	 * Asks for a new account.
	 *
	 * @param array $data { email, role, first_name, last_name, reason }.
	 * @return array|\WP_Error
	 */
	public static function request( $data ) {
		$user_data = self::check( $data );
		if ( is_wp_error( $user_data ) ) {
			return $user_data;
		}

		// Do not ask twice for the same account.
		foreach ( Eotools_Mcp_Elevation::get_by_status( Eotools_Mcp_Elevation::STATUS_PENDING ) as $pending ) {
			if ( Eotools_Mcp_Elevation::SCOPE_CREATE_USER === Eotools_Mcp_Elevation::scope_of( $pending ) && $pending['user_data']['email'] === $user_data['email'] ) {
				return self::response( $pending );
			}
		}

		$request = Eotools_Mcp_Elevation::create_request(
			Eotools_Mcp_Elevation::SCOPE_CREATE_USER,
			array(
				'user_data' => $user_data,
				'reason'    => isset( $data['reason'] ) ? sanitize_textarea_field( (string) $data['reason'] ) : '',
			)
		);
		if ( is_wp_error( $request ) ) {
			return $request;
		}

		return self::response( $request );
	}

	/**
	 * Answer given to the AI for a request.
	 *
	 * @param array $request Request.
	 * @return array
	 */
	private static function response( $request ) {
		return array(
			'request_id'   => $request['id'],
			'status'       => $request['status'],
			'email'        => $request['user_data']['email'],
			'role'         => $request['user_data']['role'],
			'approval_url' => Eotools_Mcp_Elevation::admin_url( $request['id'] ),
			'message'      => __( 'Demande de création de compte enregistrée, en attente de validation humaine. Donnez à l\'utilisateur le lien "approval_url" : un administrateur la valide dans WordPress en retapant son mot de passe, et le compte est alors créé. Le nouvel utilisateur reçoit un e-mail pour choisir son mot de passe : ne demandez ni ne proposez jamais de mot de passe.', 'eo-tools' ),
		);
	}

	/**
	 * Checks and normalizes the account asked for.
	 *
	 * @param array $data Data sent by the AI.
	 * @return array|\WP_Error { email, role, first_name, last_name }.
	 */
	private static function check( $data ) {
		$email = isset( $data['email'] ) ? sanitize_email( (string) $data['email'] ) : '';
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'eo_tools_user_email', __( 'Adresse e-mail invalide.', 'eo-tools' ) );
		}
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'eo_tools_user_exists', __( 'Un compte existe déjà avec cette adresse e-mail.', 'eo-tools' ) );
		}

		$role = isset( $data['role'] ) ? sanitize_key( (string) $data['role'] ) : 'editor';
		if ( ! in_array( $role, Eotools_Mcp_Settings::get_allowed_roles(), true ) ) {
			/* translators: %s: allowed roles */
			return new \WP_Error( 'eo_tools_user_role', sprintf( __( 'Ce rôle n\'existe pas ou donne trop de droits. Rôles possibles : %s.', 'eo-tools' ), implode( ', ', Eotools_Mcp_Settings::get_allowed_roles() ) ) );
		}

		return array(
			'email'      => $email,
			'role'       => $role,
			'first_name' => isset( $data['first_name'] ) ? sanitize_text_field( (string) $data['first_name'] ) : '',
			'last_name'  => isset( $data['last_name'] ) ? sanitize_text_field( (string) $data['last_name'] ) : '',
		);
	}

	/**
	 * Creates the account of an approved request.
	 *
	 * @param array $request Request.
	 * @return int|\WP_Error New user ID.
	 */
	public static function create_from_request( $request ) {
		// Things may have changed since the request (account created by hand, role removed…).
		$data = self::check( $request['user_data'] );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$display = trim( $data['first_name'] . ' ' . $data['last_name'] );

		$user_id = wp_insert_user(
			array(
				'user_login'   => self::unique_login( $data['email'] ),
				'user_email'   => $data['email'],
				'user_pass'    => wp_generate_password( 24, true, true ),
				'role'         => $data['role'],
				'first_name'   => $data['first_name'],
				'last_name'    => $data['last_name'],
				'display_name' => '' !== $display ? $display : $data['email'],
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// The new user chooses their password from the link in this e-mail.
		wp_new_user_notification( $user_id, null, 'user' );

		return $user_id;
	}

	/**
	 * Free login built from an e-mail address.
	 *
	 * @param string $email E-mail.
	 * @return string
	 */
	private static function unique_login( $email ) {
		$base = sanitize_user( strstr( $email, '@', true ), true );
		$base = '' !== $base ? $base : 'utilisateur';

		$login  = $base;
		$suffix = 2;
		while ( username_exists( $login ) ) {
			$login = $base . $suffix++;
		}

		return $login;
	}
}
