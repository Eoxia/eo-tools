<?php
/**
 * Plugin adapter: All In One WP Security & Firewall (AIOS).
 *
 * Account and login security: login lockdown (attempts, period, lockout time).
 * Two features that clash with Wordfence or MainWP can only be switched off
 * here: the login page renaming (breaks MainWP) and the file change detection
 * (Wordfence scans the files).
 *
 * Left to a human, with a link in "status":
 * - the AIOS firewall rules, written in .htaccess (Wordfence handles the firewall);
 * - "Disable PHP file editing", which AIOS writes in wp-config.php.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugin_Aios extends Eotools_Mcp_Plugin_Adapter {

	/**
	 * AIOS settings keyed by EO Tools name.
	 */
	const MAP = array(
		'login_lockdown'          => 'aiowps_enable_login_lockdown',
		'max_login_attempts'      => 'aiowps_max_login_attempts',
		'retry_time_period'       => 'aiowps_retry_time_period',
		'lockout_time_length'     => 'aiowps_lockout_time_length',
		'max_lockout_time_length' => 'aiowps_max_lockout_time_length',
		'rename_login_page'       => 'aiowps_enable_rename_login_page',
		'file_change_detection'   => 'aiowps_enable_automated_fcd_scan',
	);

	/**
	 * Firewall features of AIOS, redundant with Wordfence.
	 */
	const FIREWALL = array(
		'aiowps_enable_basic_firewall'                => 'Basic firewall',
		'aiowps_enable_6g_firewall'                   => '6G firewall',
		'aiowps_enable_5g_firewall'                   => '5G firewall',
		'aiowps_enable_blacklisting'                  => 'IP blacklist',
		'aiowps_enable_brute_force_attack_prevention' => 'Cookie based brute force prevention',
	);

	/**
	 * Settings that can only be switched off with EO Tools.
	 */
	const OFF_ONLY = array( 'rename_login_page', 'file_change_detection' );

	public function get_slug() {
		return 'all-in-one-wp-security-and-firewall';
	}

	public function get_label() {
		return 'All In One WP Security';
	}

	public function is_available() {
		return isset( $GLOBALS['aio_wp_security']->configs ) && is_object( $GLOBALS['aio_wp_security']->configs );
	}

	public function get_schema() {
		return array(
			'login_lockdown'          => array(
				'type'  => 'bool',
				'label' => __( 'Verrouillage après trop d\'échecs de connexion', 'eo-tools' ),
			),
			'max_login_attempts'      => array(
				'type'  => 'int',
				'min'   => 1,
				'max'   => 100,
				'label' => __( 'Nombre d\'essais avant le verrouillage (au moins 10 conseillé)', 'eo-tools' ),
			),
			'retry_time_period'       => array(
				'type'  => 'int',
				'min'   => 1,
				'max'   => 1440,
				'label' => __( 'Période de comptage des essais, en minutes', 'eo-tools' ),
			),
			'lockout_time_length'     => array(
				'type'  => 'int',
				'min'   => 1,
				'max'   => 43200,
				'label' => __( 'Durée minimale du verrouillage, en minutes', 'eo-tools' ),
			),
			'max_lockout_time_length' => array(
				'type'  => 'int',
				'min'   => 2,
				'max'   => 43200,
				'label' => __( 'Durée maximale du verrouillage (en cas de récidive), en minutes, supérieure à la durée minimale', 'eo-tools' ),
			),
			'rename_login_page'       => array(
				'type'  => 'bool',
				'label' => __( 'Renommage de la page de connexion. Ne peut être que désactivé ici (false) : il casse la connexion MainWP.', 'eo-tools' ),
			),
			'file_change_detection'   => array(
				'type'  => 'bool',
				'label' => __( 'Détection de modification des fichiers. Ne peut être que désactivée ici (false) : Wordfence analyse déjà les fichiers.', 'eo-tools' ),
			),
		);
	}

	/**
	 * @return \AIOWPSecurity_Config
	 */
	private function configs() {
		return $GLOBALS['aio_wp_security']->configs;
	}

	protected function read_settings( $target ) {
		$configs  = $this->configs();
		$settings = array();
		foreach ( self::MAP as $name => $key ) {
			$value             = $configs->get_value( $key );
			$settings[ $name ] = 'bool' === $this->get_schema()[ $name ]['type'] ? '1' === (string) $value : (int) $value;
		}

		return $settings;
	}

	protected function check_values( $clean, $target ) {
		$errors = array();
		foreach ( self::OFF_ONLY as $name ) {
			if ( ! empty( $clean[ $name ] ) ) {
				$errors[ $name ] = __( 'Ce réglage ne peut être que désactivé avec EO Tools.', 'eo-tools' );
			}
		}

		$current = $this->read_settings( $target );
		$min     = isset( $clean['lockout_time_length'] ) ? $clean['lockout_time_length'] : $current['lockout_time_length'];
		$max     = isset( $clean['max_lockout_time_length'] ) ? $clean['max_lockout_time_length'] : $current['max_lockout_time_length'];
		if ( $min >= $max ) {
			$errors['max_lockout_time_length'] = __( 'La durée maximale doit être supérieure à la durée minimale du verrouillage.', 'eo-tools' );
		}

		return $errors;
	}

	protected function write_settings( $clean, $target ) {
		$configs = $this->configs();
		foreach ( $clean as $name => $value ) {
			$configs->set_value( self::MAP[ $name ], is_bool( $value ) ? ( $value ? '1' : '' ) : (string) $value );
		}

		// Like the AIOS screen: the unlock requests need a secret key.
		if ( ! empty( $clean['login_lockdown'] ) && '' === (string) $configs->get_value( 'aiowps_unlock_request_secret_key' ) ) {
			$configs->set_value( 'aiowps_unlock_request_secret_key', wp_generate_password( 20, false ) );
		}
		$configs->save_config();

		$notes = array();
		if ( $this->get_firewall_conflicts() ) {
			$notes[] = __( 'Des règles de pare-feu AIOS sont actives alors que Wordfence gère le pare-feu : voir "status.firewall_conflicts".', 'eo-tools' );
		}

		return $notes;
	}

	/**
	 * Firewall features of AIOS that are on.
	 *
	 * @return string[]
	 */
	private function get_firewall_conflicts() {
		$configs = $this->configs();
		$active  = array();
		foreach ( self::FIREWALL as $key => $label ) {
			if ( '1' === (string) $configs->get_value( $key ) ) {
				$active[] = $label;
			}
		}

		return $active;
	}

	protected function get_status() {
		$conflicts = $this->get_firewall_conflicts();
		$status    = array(
			'firewall_conflicts'    => $conflicts,
			'php_file_editing'      => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ? 'disabled' : 'enabled',
			'php_file_editing_url'  => admin_url( 'admin.php?page=aiowpsec_filesystem&tab=file-protection' ),
			'php_file_editing_note' => __( 'AIOS désactive l\'édition des fichiers PHP en modifiant wp-config.php : un humain coche « Disable PHP file editing » à cette adresse.', 'eo-tools' ),
		);
		if ( $conflicts ) {
			$status['firewall_url'] = admin_url( 'admin.php?page=aiowpsec_firewall' );
		}

		return $status;
	}
}
