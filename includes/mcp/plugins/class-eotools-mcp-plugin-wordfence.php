<?php
/**
 * Plugin adapter: Wordfence Security.
 *
 * Firewall mode, brute force protection and alert e-mails, saved through the
 * Wordfence API (wfConfig::validate / save), like its own settings screen.
 *
 * Left to a human, with a link in "status": the setup wizard (it registers a
 * Wordfence license with an e-mail address and the terms of use) and the
 * firewall optimization (it changes the server configuration files and offers
 * to download their backup).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugin_Wordfence extends Eotools_Mcp_Plugin_Adapter {

	/**
	 * Settings stored by wfConfig, keyed by EO Tools name.
	 */
	const MAP = array(
		'brute_force_protection' => 'loginSecurityEnabled',
		'max_login_failures'     => 'loginSec_maxFailures',
		'max_forgot_password'    => 'loginSec_maxForgotPasswd',
		'count_failures_minutes' => 'loginSec_countFailMins',
		'lockout_minutes'        => 'loginSec_lockoutMins',
		'lock_invalid_usernames' => 'loginSec_lockInvalidUsers',
		'alert_emails'           => 'alertEmails',
	);

	public function get_slug() {
		return 'wordfence';
	}

	public function get_label() {
		return 'Wordfence Security';
	}

	public function is_available() {
		return class_exists( '\wfConfig' ) && class_exists( '\wfWAF' ) && class_exists( '\wfFirewall' );
	}

	public function get_schema() {
		return array(
			'firewall_mode'          => array(
				'type'    => 'enum',
				'choices' => array( 'enabled', 'learning-mode', 'disabled' ),
				'label'   => __( 'Pare-feu : "enabled" (activé et protecteur), "learning-mode" (apprentissage, ne bloque rien), "disabled"', 'eo-tools' ),
			),
			'brute_force_protection' => array(
				'type'  => 'bool',
				'label' => __( 'Protection contre la force brute (connexions)', 'eo-tools' ),
			),
			'max_login_failures'     => array(
				'type'  => 'int',
				'min'   => 2,
				'max'   => 500,
				'label' => __( 'Blocage après ce nombre d\'échecs de connexion (20 pour laisser All In One Security bloquer en premier)', 'eo-tools' ),
			),
			'max_forgot_password'    => array(
				'type'  => 'int',
				'min'   => 1,
				'max'   => 500,
				'label' => __( 'Blocage après ce nombre de demandes « mot de passe oublié »', 'eo-tools' ),
			),
			'count_failures_minutes' => array(
				'type'  => 'int',
				'min'   => 5,
				'max'   => 1440,
				'label' => __( 'Période de comptage des échecs, en minutes', 'eo-tools' ),
			),
			'lockout_minutes'        => array(
				'type'  => 'int',
				'min'   => 5,
				'max'   => 86400,
				'label' => __( 'Durée du blocage, en minutes', 'eo-tools' ),
			),
			'lock_invalid_usernames' => array(
				'type'  => 'bool',
				'label' => __( 'Bloquer immédiatement les identifiants inexistants', 'eo-tools' ),
			),
			'alert_emails'           => array(
				'type'  => 'emails',
				'label' => __( 'Adresses qui reçoivent les alertes de sécurité, séparées par des virgules', 'eo-tools' ),
			),
		);
	}

	/**
	 * Firewall mode stored by the Wordfence firewall.
	 *
	 * @return string
	 */
	private function firewall_mode() {
		try {
			return (string) \wfWAF::getInstance()->getStorageEngine()->getConfig( 'wafStatus' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	protected function read_settings( $target ) {
		$settings = array( 'firewall_mode' => $this->firewall_mode() );
		foreach ( self::MAP as $name => $key ) {
			$type              = $this->get_schema()[ $name ]['type'];
			$value             = \wfConfig::get( $key );
			$settings[ $name ] = 'bool' === $type ? (bool) $value : ( 'int' === $type ? (int) $value : (string) $value );
		}

		return $settings;
	}

	/**
	 * Values in the Wordfence format.
	 *
	 * @param array $clean Checked values.
	 * @return array
	 */
	private function to_changes( $clean ) {
		$changes = array();
		foreach ( $clean as $name => $value ) {
			$key             = 'firewall_mode' === $name ? 'wafStatus' : self::MAP[ $name ];
			$changes[ $key ] = is_bool( $value ) ? (int) $value : $value;
		}

		return $changes;
	}

	protected function check_values( $clean, $target ) {
		try {
			$valid = \wfConfig::validate( $this->to_changes( $clean ) );
		} catch ( \Exception $e ) {
			return array( 'wordfence' => $e->getMessage() );
		}
		if ( true === $valid ) {
			return array();
		}

		$names  = array_flip( self::MAP ) + array( 'wafStatus' => 'firewall_mode' );
		$errors = array();
		foreach ( (array) $valid as $error ) {
			$name            = isset( $names[ $error['option'] ] ) ? $names[ $error['option'] ] : $error['option'];
			$errors[ $name ] = $error['error'];
		}

		return $errors;
	}

	protected function write_settings( $clean, $target ) {
		try {
			\wfConfig::save( \wfConfig::clean( $this->to_changes( $clean ) ) );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'eo_tools_wordfence', $e->getMessage() );
		}

		$notes = array();
		if ( ! $this->is_optimized() ) {
			$notes[] = sprintf(
				/* translators: %s: URL */
				__( 'Le pare-feu n\'est pas encore optimisé (protection étendue) : l\'utilisateur doit cliquer sur « Optimiser le pare-feu » ici, car l\'opération modifie les fichiers du serveur : %s', 'eo-tools' ),
				$this->optimize_url()
			);
		}

		return $notes;
	}

	/**
	 * Whether the firewall runs before WordPress (extended protection).
	 *
	 * @return bool
	 */
	private function is_optimized() {
		return \wfFirewall::PROTECTION_MODE_EXTENDED === ( new \wfFirewall() )->protectionMode();
	}

	/**
	 * @return string
	 */
	private function optimize_url() {
		return network_admin_url( 'admin.php?page=WordfenceWAF&subpage=waf_options#configureAutoPrepend' );
	}

	protected function get_status() {
		$onboarding = (string) \wfConfig::get( 'onboardingAttempt1' );

		return array(
			'firewall_optimized'  => $this->is_optimized(),
			'optimize_url'        => $this->optimize_url(),
			'license_installed'   => '' !== (string) \wfConfig::get( 'apiKey' ),
			'setup_wizard_done'   => in_array( $onboarding, array( 'license', 'emails' ), true ) || '' !== (string) \wfConfig::get( 'apiKey' ),
			'setup_wizard_url'    => network_admin_url( 'admin.php?page=Wordfence' ),
			'setup_wizard_reason' => __( 'L\'assistant de démarrage enregistre une licence Wordfence (gratuite) avec une adresse e-mail et l\'acceptation des conditions : un humain doit le faire.', 'eo-tools' ),
		);
	}
}
