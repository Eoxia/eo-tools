<?php
/**
 * Plugin adapter: Easy WP SMTP.
 *
 * Sends the site e-mails through an SMTP server ("Other SMTP" mailer): host,
 * port, encryption, login, sender. The SMTP password is a secret typed by a
 * human. Other mailers (Gmail, API services…) need an account connection and
 * stay in the plugin screens.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugin_Easy_Wp_Smtp extends Eotools_Mcp_Plugin_Adapter {

	/**
	 * Settings keyed by EO Tools name: [ plugin group, plugin key ].
	 */
	const MAP = array(
		'mailer'           => array( 'mail', 'mailer' ),
		'smtp_host'        => array( 'smtp', 'host' ),
		'smtp_port'        => array( 'smtp', 'port' ),
		'smtp_encryption'  => array( 'smtp', 'encryption' ),
		'smtp_autotls'     => array( 'smtp', 'autotls' ),
		'smtp_auth'        => array( 'smtp', 'auth' ),
		'smtp_user'        => array( 'smtp', 'user' ),
		'from_email'       => array( 'mail', 'from_email' ),
		'from_name'        => array( 'mail', 'from_name' ),
		'from_email_force' => array( 'mail', 'from_email_force' ),
		'from_name_force'  => array( 'mail', 'from_name_force' ),
	);

	public function get_slug() {
		return 'easy-wp-smtp';
	}

	public function get_label() {
		return 'Easy WP SMTP';
	}

	public function is_available() {
		return class_exists( '\EasyWPSMTP\Options' );
	}

	public function get_schema() {
		return array(
			'mailer'           => array(
				'type'    => 'enum',
				'choices' => array( 'smtp', 'mail' ),
				'label'   => __( 'Méthode d\'envoi : "smtp" (serveur SMTP, « Other SMTP ») ou "mail" (fonction PHP par défaut, déconseillée)', 'eo-tools' ),
			),
			'smtp_host'        => array(
				'type'  => 'string',
				'label' => __( 'Serveur SMTP, ex. "mail.mondomaine.fr"', 'eo-tools' ),
			),
			'smtp_port'        => array(
				'type'  => 'int',
				'min'   => 1,
				'max'   => 65535,
				'label' => __( 'Port SMTP (465 = SSL, 587 = TLS)', 'eo-tools' ),
			),
			'smtp_encryption'  => array(
				'type'    => 'enum',
				'choices' => array( 'none', 'ssl', 'tls' ),
				'label'   => __( 'Chiffrement, déduit du port : 465 = "ssl", 587 = "tls"', 'eo-tools' ),
			),
			'smtp_autotls'     => array(
				'type'  => 'bool',
				'label' => __( 'TLS automatique quand le serveur le propose (laisser activé)', 'eo-tools' ),
			),
			'smtp_auth'        => array(
				'type'  => 'bool',
				'label' => __( 'Authentification par identifiant et mot de passe', 'eo-tools' ),
			),
			'smtp_user'        => array(
				'type'  => 'string',
				'label' => __( 'Identifiant SMTP (souvent l\'adresse e-mail). Le mot de passe est saisi par un humain dans WordPress.', 'eo-tools' ),
			),
			'from_email'       => array(
				'type'  => 'email',
				'label' => __( 'Adresse d\'expédition', 'eo-tools' ),
			),
			'from_name'        => array(
				'type'  => 'string',
				'label' => __( 'Nom d\'expéditeur', 'eo-tools' ),
			),
			'from_email_force' => array(
				'type'  => 'bool',
				'label' => __( 'Forcer l\'adresse d\'expédition pour tous les e-mails du site', 'eo-tools' ),
			),
			'from_name_force'  => array(
				'type'  => 'bool',
				'label' => __( 'Forcer le nom d\'expéditeur pour tous les e-mails du site', 'eo-tools' ),
			),
		);
	}

	/**
	 * @return \EasyWPSMTP\Options
	 */
	private function options() {
		return \EasyWPSMTP\Options::init();
	}

	protected function read_settings( $target ) {
		$options  = $this->options();
		$settings = array();
		foreach ( self::MAP as $name => $key ) {
			$value             = $options->get( $key[0], $key[1] );
			$settings[ $name ] = $this->normalize( $name, $value );
		}

		return $settings;
	}

	/**
	 * Same type as the schema, to compare before and after.
	 *
	 * @param string $name  Setting name.
	 * @param mixed  $value Stored value.
	 * @return mixed
	 */
	private function normalize( $name, $value ) {
		$type = $this->get_schema()[ $name ]['type'];
		if ( 'bool' === $type ) {
			return (bool) $value;
		}
		if ( 'int' === $type ) {
			return (int) $value;
		}

		return (string) $value;
	}

	protected function check_values( $clean, $target ) {
		$errors  = array();
		$options = $this->options();
		foreach ( array_keys( $clean ) as $name ) {
			$key = self::MAP[ $name ];
			if ( $options->is_const_defined( $key[0], $key[1] ) ) {
				$errors[ $name ] = __( 'Ce réglage est fixé par une constante dans wp-config.php : il ne peut pas être modifié ici.', 'eo-tools' );
			}
		}

		return $errors;
	}

	protected function write_settings( $clean, $target ) {
		$data = array();
		foreach ( $clean as $name => $value ) {
			$key                         = self::MAP[ $name ];
			$data[ $key[0] ][ $key[1] ] = $value;
		}
		$this->options()->set( $data, false, false );

		$notes    = array();
		$settings = $this->read_settings( $target );
		if ( 'smtp' === $settings['mailer'] ) {
			$expected = array(
				465 => 'ssl',
				587 => 'tls',
			);
			if ( isset( $expected[ $settings['smtp_port'] ] ) && $expected[ $settings['smtp_port'] ] !== $settings['smtp_encryption'] ) {
				/* translators: 1: port, 2: encryption */
				$notes[] = sprintf( __( 'Le port %1$d s\'utilise d\'habitude avec le chiffrement "%2$s".', 'eo-tools' ), $settings['smtp_port'], $expected[ $settings['smtp_port'] ] );
			}
			$notes[] = sprintf(
				/* translators: %s: URL */
				__( 'Une fois le mot de passe saisi, demandez à l\'utilisateur d\'envoyer un e-mail de test : %s', 'eo-tools' ),
				$this->test_url()
			);
		}

		return $notes;
	}

	protected function get_status() {
		return array(
			'password_filled' => $this->has_secret( 'smtp_password' ),
			'test_url'        => $this->test_url(),
		);
	}

	/**
	 * Screen where a human sends a test e-mail.
	 *
	 * @return string
	 */
	private function test_url() {
		return admin_url( 'admin.php?page=easy-wp-smtp-tools&tab=test' );
	}

	/* ---------------------------------------------------------------------
	 * SMTP password, typed by a human
	 * ------------------------------------------------------------------ */

	public function get_secrets() {
		return array(
			'smtp_password' => array(
				'label' => __( 'Mot de passe SMTP', 'eo-tools' ),
				'help'  => __( 'Le mot de passe du compte e-mail utilisé pour l\'envoi. Il est chiffré par Easy WP SMTP.', 'eo-tools' ),
			),
		);
	}

	public function has_secret( $name ) {
		return 'smtp_password' === $name && '' !== (string) $this->options()->get( 'smtp', 'pass' );
	}

	/**
	 * The password is only needed for an SMTP server with authentication.
	 *
	 * @param string $name Secret name.
	 * @return bool
	 */
	public function is_secret_needed( $name ) {
		$options = $this->options();

		return 'smtp_password' === $name
			&& 'smtp' === $options->get( 'mail', 'mailer' )
			&& $options->get( 'smtp', 'auth' )
			&& ! $options->is_const_defined( 'smtp', 'pass' )
			&& ! $this->has_secret( $name );
	}

	public function save_secret( $name, $value ) {
		if ( 'smtp_password' !== $name ) {
			return parent::save_secret( $name, $value );
		}
		if ( $this->options()->is_const_defined( 'smtp', 'pass' ) ) {
			return new \WP_Error( 'eo_tools_secret_constant', __( 'Ce mot de passe est fixé par une constante dans wp-config.php.', 'eo-tools' ) );
		}
		// Easy WP SMTP only encrypts the password of the mailer in use.
		if ( 'smtp' !== $this->options()->get( 'mail', 'mailer' ) ) {
			return new \WP_Error( 'eo_tools_secret_mailer', __( 'Choisissez d\'abord l\'envoi par serveur SMTP (votre IA peut le faire), puis saisissez le mot de passe.', 'eo-tools' ) );
		}

		// Encrypted by the plugin.
		$this->options()->set( array( 'smtp' => array( 'pass' => $value ) ), false, false );

		return true;
	}
}
