<?php
/**
 * Plugin adapter: Contact Form 7.
 *
 * Per form: title, form template, e-mail sent to the site (recipient, sender,
 * subject, body, headers), auto-reply on/off, captcha.
 * Site-wide: reCAPTCHA v3 and Cloudflare Turnstile site keys. Their secret keys
 * are typed by a human.
 *
 * Captcha: "really-simple-captcha" adds an image captcha to the form (needs the
 * Really Simple CAPTCHA plugin); "recaptcha" and "turnstile" protect every form
 * of the site once their keys are set.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugin_Cf7 extends Eotools_Mcp_Plugin_Adapter {

	const CAPTCHAS = array( 'none', 'really-simple-captcha', 'recaptcha', 'turnstile' );

	/**
	 * Services keyed by CF7 option name.
	 */
	const SERVICES = array(
		'recaptcha' => 'reCAPTCHA v3',
		'turnstile' => 'Cloudflare Turnstile',
	);

	public function get_slug() {
		return 'contact-form-7';
	}

	public function get_label() {
		return 'Contact Form 7';
	}

	public function is_available() {
		return class_exists( 'WPCF7_ContactForm' ) && class_exists( 'WPCF7' );
	}

	public function get_schema() {
		return array(
			'title'                   => array(
				'type'  => 'string',
				'label' => __( 'Titre du formulaire (usage interne)', 'eo-tools' ),
			),
			'mail_recipient'          => array(
				'type'  => 'string',
				'label' => __( 'Destinataire(s) des messages, séparés par des virgules (adresse e-mail ou [_site_admin_email])', 'eo-tools' ),
			),
			'mail_sender'             => array(
				'type'  => 'string',
				'label' => __( 'Expéditeur, ex. "Mon site <wordpress@mondomaine.fr>" (adresse du domaine du site, sinon les e-mails risquent d\'être refusés)', 'eo-tools' ),
			),
			'mail_subject'            => array(
				'type'  => 'string',
				'label' => __( 'Objet de l\'e-mail (peut contenir des balises de champ, ex. [your-subject])', 'eo-tools' ),
			),
			'mail_body'               => array(
				'type'  => 'text',
				'label' => __( 'Corps de l\'e-mail (balises de champ possibles)', 'eo-tools' ),
			),
			'mail_additional_headers' => array(
				'type'  => 'text',
				'label' => __( 'En-têtes supplémentaires, ex. "Reply-To: [your-email]"', 'eo-tools' ),
			),
			'mail_2_active'           => array(
				'type'  => 'bool',
				'label' => __( 'Envoyer aussi une réponse automatique au visiteur (e-mail 2)', 'eo-tools' ),
			),
			'form'                    => array(
				'type'  => 'text',
				'label' => __( 'Modèle du formulaire (HTML et balises CF7, ex. [text* your-name])', 'eo-tools' ),
			),
			'captcha'                 => array(
				'type'    => 'enum',
				'choices' => self::CAPTCHAS,
				'label'   => __( 'Captcha. "really-simple-captcha" : image à recopier dans ce formulaire (extension Really Simple CAPTCHA active). "recaptcha" / "turnstile" : protège tous les formulaires du site, demande la clé de site (ci-dessous) et une clé secrète saisie par un humain.', 'eo-tools' ),
			),
			'recaptcha_site_key'      => array(
				'type'  => 'string',
				'label' => __( 'Clé de site reCAPTCHA v3 (publique, réglage commun à tous les formulaires)', 'eo-tools' ),
			),
			'turnstile_site_key'      => array(
				'type'  => 'string',
				'label' => __( 'Clé de site Cloudflare Turnstile (publique, réglage commun à tous les formulaires)', 'eo-tools' ),
			),
		);
	}

	public function get_secrets() {
		return array(
			'recaptcha_secret' => array(
				'label' => __( 'Clé secrète reCAPTCHA v3', 'eo-tools' ),
				'help'  => __( 'Dans la console Google reCAPTCHA, avec la clé de site déjà renseignée.', 'eo-tools' ),
			),
			'turnstile_secret' => array(
				'label' => __( 'Clé secrète Cloudflare Turnstile', 'eo-tools' ),
				'help'  => __( 'Dans le tableau de bord Cloudflare (Turnstile), avec la clé de site déjà renseignée.', 'eo-tools' ),
			),
		);
	}

	/* ---------------------------------------------------------------------
	 * Site-wide keys
	 * ------------------------------------------------------------------ */

	/**
	 * Site key and secret of a service, as stored by CF7 (site key => secret).
	 *
	 * @param string $service "recaptcha" or "turnstile".
	 * @return array { sitekey, secret }.
	 */
	private function get_keys( $service ) {
		$keys = \WPCF7::get_option( $service );
		if ( ! is_array( $keys ) || ! $keys ) {
			return array(
				'sitekey' => '',
				'secret'  => '',
			);
		}

		$sitekey = (string) key( $keys );

		return array(
			'sitekey' => $sitekey,
			'secret'  => (string) $keys[ $sitekey ],
		);
	}

	/**
	 * @param string $service "recaptcha" or "turnstile".
	 * @return bool
	 */
	private function service_supported( $service ) {
		return 'recaptcha' === $service || class_exists( 'WPCF7_Turnstile' );
	}

	/**
	 * @param string $service "recaptcha" or "turnstile".
	 * @return bool Site key and secret set.
	 */
	private function service_active( $service ) {
		$keys = $this->get_keys( $service );

		return '' !== $keys['sitekey'] && '' !== $keys['secret'];
	}

	public function has_secret( $name ) {
		$service = str_replace( '_secret', '', $name );
		if ( ! isset( self::SERVICES[ $service ] ) ) {
			return false;
		}
		$keys = $this->get_keys( $service );

		return '' !== $keys['secret'];
	}

	public function save_secret( $name, $value ) {
		$service = str_replace( '_secret', '', $name );
		if ( ! isset( self::SERVICES[ $service ] ) || ! $this->service_supported( $service ) ) {
			return new \WP_Error( 'eo_tools_no_secret', __( 'Clé inconnue.', 'eo-tools' ) );
		}
		if ( ! preg_match( '/^[A-Za-z0-9_.\-]{10,200}$/', $value ) ) {
			return new \WP_Error( 'eo_tools_secret_format', __( 'Cette clé secrète n\'a pas le format attendu.', 'eo-tools' ) );
		}

		$keys = $this->get_keys( $service );
		if ( '' === $keys['sitekey'] ) {
			return new \WP_Error( 'eo_tools_secret_no_sitekey', __( 'Renseignez d\'abord la clé de site (votre IA peut le faire), puis la clé secrète.', 'eo-tools' ) );
		}

		\WPCF7::update_option( $service, array( $keys['sitekey'] => $value ) );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Forms
	 * ------------------------------------------------------------------ */

	/**
	 * Form to work on.
	 *
	 * @param string $target Form ID, empty for the first form.
	 * @return \WPCF7_ContactForm|\WP_Error
	 */
	private function get_form( $target ) {
		if ( '' !== (string) $target ) {
			$form = \WPCF7_ContactForm::get_instance( (int) $target );
			return $form ? $form : new \WP_Error( 'eo_tools_cf7_form', __( 'Aucun formulaire Contact Form 7 avec cet identifiant.', 'eo-tools' ) );
		}

		$forms = $this->get_forms();
		if ( ! $forms ) {
			return new \WP_Error( 'eo_tools_cf7_no_form', __( 'Aucun formulaire Contact Form 7 sur ce site : créez-en un dans Contact > Ajouter.', 'eo-tools' ) );
		}

		return reset( $forms );
	}

	/**
	 * @return \WPCF7_ContactForm[]
	 */
	private function get_forms() {
		return \WPCF7_ContactForm::find(
			array(
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'posts_per_page' => 50,
			)
		);
	}

	/**
	 * Captcha used by a form.
	 *
	 * @param string $template Form template.
	 * @return string One of self::CAPTCHAS.
	 */
	private function current_captcha( $template ) {
		if ( preg_match( '/\[captchar\b/', $template ) ) {
			return 'really-simple-captcha';
		}
		foreach ( array_keys( self::SERVICES ) as $service ) {
			if ( $this->get_keys( $service )['sitekey'] ) {
				return $service;
			}
		}

		return 'none';
	}

	/**
	 * Settings of a form, as exposed to the AI.
	 *
	 * @param \WPCF7_ContactForm $form Form.
	 * @return array
	 */
	private function read_settings( $form ) {
		$mail   = (array) $form->prop( 'mail' );
		$mail_2 = (array) $form->prop( 'mail_2' );

		return array(
			'title'                   => $form->title(),
			'mail_recipient'          => isset( $mail['recipient'] ) ? (string) $mail['recipient'] : '',
			'mail_sender'             => isset( $mail['sender'] ) ? (string) $mail['sender'] : '',
			'mail_subject'            => isset( $mail['subject'] ) ? (string) $mail['subject'] : '',
			'mail_body'               => isset( $mail['body'] ) ? (string) $mail['body'] : '',
			'mail_additional_headers' => isset( $mail['additional_headers'] ) ? (string) $mail['additional_headers'] : '',
			'mail_2_active'           => ! empty( $mail_2['active'] ),
			'form'                    => (string) $form->prop( 'form' ),
			'captcha'                 => $this->current_captcha( (string) $form->prop( 'form' ) ),
			'recaptcha_site_key'      => $this->get_keys( 'recaptcha' )['sitekey'],
			'turnstile_site_key'      => $this->get_keys( 'turnstile' )['sitekey'],
		);
	}

	public function get( $target = '' ) {
		$form = $this->get_form( $target );
		if ( is_wp_error( $form ) ) {
			return $form;
		}

		$forms = array();
		foreach ( $this->get_forms() as $item ) {
			$forms[] = array(
				'id'    => $item->id(),
				'title' => $item->title(),
			);
		}

		return array(
			'forms'                 => $forms,
			'target'                => (string) $form->id(),
			'shortcode'             => method_exists( $form, 'shortcode' ) ? $form->shortcode() : '[contact-form-7 id="' . $form->id() . '"]',
			'settings'              => $this->read_settings( $form ),
			'really_simple_captcha' => class_exists( 'ReallySimpleCaptcha' ),
			'captcha_active'        => array(
				'recaptcha' => $this->service_active( 'recaptcha' ),
				'turnstile' => $this->service_active( 'turnstile' ),
			),
		);
	}

	/* ---------------------------------------------------------------------
	 * Changes
	 * ------------------------------------------------------------------ */

	public function update( $values, $target = '' ) {
		$form = $this->get_form( $target );
		if ( is_wp_error( $form ) ) {
			return $form;
		}

		$schema = $this->get_schema();
		$errors = array();
		$clean  = array();

		foreach ( $values as $name => $value ) {
			if ( ! isset( $schema[ $name ] ) ) {
				$errors[ $name ] = __( 'Réglage inconnu (voir "schema" dans eo-tools/get-plugin-settings).', 'eo-tools' );
				continue;
			}
			$checked = $this->validate( $name, $value, $schema[ $name ] );
			if ( is_wp_error( $checked ) ) {
				$errors[ $name ] = $checked->get_error_message();
				continue;
			}
			$clean[ $name ] = $checked;
		}

		if ( isset( $clean['captcha'] ) ) {
			$error = $this->check_captcha( $clean['captcha'], $clean );
			if ( is_wp_error( $error ) ) {
				$errors['captcha'] = $error->get_error_message();
			}
		}

		if ( $errors ) {
			$lines = array();
			foreach ( $errors as $name => $reason ) {
				$lines[] = $name . ' : ' . $reason;
			}

			return new \WP_Error( 'eo_tools_invalid_settings', __( 'Aucun réglage n\'a été modifié, corrigez les valeurs refusées :', 'eo-tools' ) . "\n" . implode( "\n", $lines ) );
		}

		$before = $this->read_settings( $form );
		$notes  = array();

		// Site-wide keys: a new site key needs its own secret.
		foreach ( array_keys( self::SERVICES ) as $service ) {
			$key = $service . '_site_key';
			if ( isset( $clean[ $key ] ) && $clean[ $key ] !== $before[ $key ] ) {
				\WPCF7::update_option( $service, '' === $clean[ $key ] ? array() : array( $clean[ $key ] => '' ) );
			}
		}

		$mail = (array) $form->prop( 'mail' );
		foreach ( array( 'recipient', 'sender', 'subject', 'body', 'additional_headers' ) as $field ) {
			if ( isset( $clean[ 'mail_' . $field ] ) ) {
				$mail[ $field ] = $clean[ 'mail_' . $field ];
			}
		}

		$mail_2 = (array) $form->prop( 'mail_2' );
		if ( isset( $clean['mail_2_active'] ) ) {
			$mail_2['active'] = $clean['mail_2_active'];
		}

		$template = isset( $clean['form'] ) ? $clean['form'] : (string) $form->prop( 'form' );
		if ( isset( $clean['captcha'] ) ) {
			$template = $this->remove_image_captcha( $template );
			if ( 'really-simple-captcha' === $clean['captcha'] ) {
				$template = $this->add_image_captcha( $template );
			}
			if ( in_array( $clean['captcha'], array( 'none', 'really-simple-captcha' ), true ) ) {
				foreach ( self::SERVICES as $service => $service_label ) {
					if ( $this->get_keys( $service )['sitekey'] ) {
						$notes[] = sprintf(
							/* translators: %s: captcha service */
							__( '%s reste actif sur tous les formulaires du site : retirez ses clés dans Contact > Intégration si vous n\'en voulez plus.', 'eo-tools' ),
							$service_label
						);
					}
				}
			}
		}

		$form->set_properties(
			array(
				'form'   => $template,
				'mail'   => $mail,
				'mail_2' => $mail_2,
			)
		);
		if ( isset( $clean['title'] ) ) {
			$form->set_title( $clean['title'] );
		}
		$form->save();

		// Read again from the database.
		$saved   = \WPCF7_ContactForm::get_instance( $form->id() );
		$after   = $this->read_settings( $saved ? $saved : $form );
		$changed = array();
		foreach ( $after as $name => $value ) {
			if ( $value !== $before[ $name ] ) {
				$changed[] = array(
					'name'   => $name,
					'before' => $before[ $name ],
					'after'  => $value,
				);
			}
		}

		if ( isset( $clean['captcha'] ) && in_array( $clean['captcha'], array_keys( self::SERVICES ), true ) && ! $this->service_active( $clean['captcha'] ) ) {
			$notes[] = __( 'Le captcha ne fonctionnera qu\'une fois la clé secrète saisie.', 'eo-tools' );
		}

		return array(
			'target'  => (string) $form->id(),
			'changed' => $changed,
			'notes'   => $notes,
		);
	}

	/**
	 * Checks and normalizes a value.
	 *
	 * @param string $name   Setting name.
	 * @param mixed  $value  Value sent by the AI.
	 * @param array  $schema Setting schema.
	 * @return mixed|\WP_Error
	 */
	private function validate( $name, $value, $schema ) {
		if ( 'bool' === $schema['type'] ) {
			return is_bool( $value ) ? $value : new \WP_Error( 'invalid', __( 'Valeur attendue : true ou false.', 'eo-tools' ) );
		}
		if ( ! is_string( $value ) ) {
			return new \WP_Error( 'invalid', __( 'Texte attendu.', 'eo-tools' ) );
		}

		switch ( $name ) {
			case 'captcha':
				return in_array( $value, self::CAPTCHAS, true ) ? $value : new \WP_Error( 'invalid', implode( ', ', self::CAPTCHAS ) );

			case 'mail_recipient':
				$parts = array_filter( array_map( 'trim', explode( ',', $value ) ) );
				if ( ! $parts ) {
					return new \WP_Error( 'invalid', __( 'Indiquez au moins un destinataire.', 'eo-tools' ) );
				}
				foreach ( $parts as $part ) {
					if ( ! is_email( $part ) && ! preg_match( '/^\[[a-z0-9_\-]+\]$/i', $part ) ) {
						/* translators: %s: value */
						return new \WP_Error( 'invalid', sprintf( __( '« %s » n\'est ni une adresse e-mail ni une balise.', 'eo-tools' ), $part ) );
					}
				}
				return implode( ', ', $parts );

			case 'recaptcha_site_key':
			case 'turnstile_site_key':
				$value = trim( $value );
				if ( '' !== $value && ! preg_match( '/^[A-Za-z0-9_.\-]{10,200}$/', $value ) ) {
					return new \WP_Error( 'invalid', __( 'Cette clé de site n\'a pas le format attendu.', 'eo-tools' ) );
				}
				if ( 'turnstile_site_key' === $name && ! $this->service_supported( 'turnstile' ) ) {
					return new \WP_Error( 'invalid', __( 'Cette version de Contact Form 7 ne gère pas Turnstile.', 'eo-tools' ) );
				}
				return $value;

			case 'form':
			case 'mail_body':
				return wp_kses_post( $value );

			case 'mail_additional_headers':
				return sanitize_textarea_field( $value );

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Checks that the chosen captcha can work.
	 *
	 * @param string $captcha Chosen captcha.
	 * @param array  $clean   Checked values of the same call.
	 * @return true|\WP_Error
	 */
	private function check_captcha( $captcha, $clean ) {
		if ( 'really-simple-captcha' === $captcha && ! class_exists( 'ReallySimpleCaptcha' ) ) {
			return new \WP_Error( 'invalid', __( 'L\'extension Really Simple CAPTCHA doit être installée et active.', 'eo-tools' ) );
		}
		if ( isset( self::SERVICES[ $captcha ] ) ) {
			if ( ! $this->service_supported( $captcha ) ) {
				return new \WP_Error( 'invalid', __( 'Cette version de Contact Form 7 ne gère pas ce captcha.', 'eo-tools' ) );
			}
			$key = $captcha . '_site_key';
			if ( empty( $clean[ $key ] ) && '' === $this->get_keys( $captcha )['sitekey'] ) {
				/* translators: %s: setting name */
				return new \WP_Error( 'invalid', sprintf( __( 'Indiquez aussi la clé de site (%s), demandée à l\'utilisateur.', 'eo-tools' ), $key ) );
			}
		}

		return true;
	}

	/**
	 * Adds the Really Simple CAPTCHA fields before the submit button.
	 *
	 * @param string $template Form template.
	 * @return string
	 */
	private function add_image_captcha( $template ) {
		$block = '<label> ' . __( 'Recopiez les caractères ci-dessous', 'eo-tools' ) . "\n    [captchac captcha-1] [captchar captcha-1] </label>\n\n";

		if ( preg_match( '/\[submit\b/', $template, $match, PREG_OFFSET_CAPTURE ) ) {
			return substr( $template, 0, $match[0][1] ) . $block . substr( $template, $match[0][1] );
		}

		return rtrim( $template ) . "\n\n" . $block;
	}

	/**
	 * Removes the Really Simple CAPTCHA fields, with their label.
	 *
	 * @param string $template Form template.
	 * @return string
	 */
	private function remove_image_captcha( $template ) {
		$template = preg_replace( '#<label>(?:(?!</label>).)*\[captcha[cr]\b(?:(?!</label>).)*</label>\s*#s', '', $template );

		return preg_replace( '/\[captcha[cr]\b[^\]]*\]\s*/', '', $template );
	}
}
