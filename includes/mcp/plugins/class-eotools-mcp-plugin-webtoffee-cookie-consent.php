<?php
/**
 * Plugin adapter: GDPR Cookie Consent by WebToffee (webtoffee-cookie-consent).
 *
 * Settings of the active (GDPR) banner: language, buttons, consent lifetime,
 * scripts before consent, consent log. Saved with the plugin classes, like its
 * own settings screen.
 *
 * Left to a human, with a link in "status": the cookie scan, which needs a
 * WebToffee account connection.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Plugins;

use WebToffee\CookieConsent\Lite\Admin\Modules\Banners\Includes\Banner;
use WebToffee\CookieConsent\Lite\Admin\Modules\Banners\Includes\Controller as Banner_Controller;
use WebToffee\CookieConsent\Lite\Admin\Modules\Settings\Includes\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugin_Webtoffee_Cookie_Consent extends Eotools_Mcp_Plugin_Adapter {

	/**
	 * Banner buttons keyed by EO Tools name.
	 */
	const BUTTONS = array(
		'accept_button'    => 'accept',
		'reject_button'    => 'reject',
		'customize_button' => 'settings',
	);

	public function get_slug() {
		return 'webtoffee-cookie-consent';
	}

	public function get_label() {
		return 'GDPR Cookie Consent (WebToffee)';
	}

	public function is_available() {
		return class_exists( Banner::class ) && class_exists( Banner_Controller::class ) && class_exists( Settings::class ) && defined( 'CLI_PLUGIN_BASEPATH' );
	}

	public function get_schema() {
		return array(
			'language'                      => array(
				'type'  => 'string',
				'label' => __( 'Langue du bandeau, code à 2 lettres ("fr")', 'eo-tools' ),
			),
			'accept_button'                 => array(
				'type'  => 'bool',
				'label' => __( 'Bouton « Tout accepter »', 'eo-tools' ),
			),
			'reject_button'                 => array(
				'type'  => 'bool',
				'label' => __( 'Bouton « Tout refuser »', 'eo-tools' ),
			),
			'customize_button'              => array(
				'type'  => 'bool',
				'label' => __( 'Bouton « Personnaliser »', 'eo-tools' ),
			),
			'consent_expiry_days'           => array(
				'type'  => 'int',
				'min'   => 1,
				'max'   => 395,
				'label' => __( 'Durée de conservation du consentement, en jours (6 mois = 180 ; 13 mois au plus selon la CNIL)', 'eo-tools' ),
			),
			'load_analytics_before_consent' => array(
				'type'  => 'bool',
				'label' => __( 'Charger les cookies de mesure d\'audience avant le consentement (false pour le RGPD : les scripts sont bloqués avant le consentement)', 'eo-tools' ),
			),
			'consent_log'                   => array(
				'type'  => 'bool',
				'label' => __( 'Journal des consentements', 'eo-tools' ),
			),
		);
	}

	/**
	 * Banner shown to visitors.
	 *
	 * @return Banner|\WP_Error
	 */
	private function get_banner() {
		$item = Banner_Controller::get_instance()->get_active_item();
		if ( empty( $item->banner_id ) ) {
			return new \WP_Error( 'eo_tools_cookie_banner', __( 'Aucun bandeau de cookies actif : ouvrez une fois l\'écran de l\'extension pour le créer.', 'eo-tools' ) );
		}

		return new Banner( $item );
	}

	protected function read_settings( $target ) {
		$banner = $this->get_banner();
		if ( is_wp_error( $banner ) ) {
			return $banner;
		}
		$config   = $banner->get_settings();
		$buttons  = isset( $config['config']['notice']['elements']['buttons']['elements'] ) ? $config['config']['notice']['elements']['buttons']['elements'] : array();
		$settings = Settings::get_instance();

		$values = array( 'language' => (string) $settings->get_default_language() );
		foreach ( self::BUTTONS as $name => $button ) {
			$values[ $name ] = ! empty( $buttons[ $button ]['status'] );
		}
		$expiry                                  = isset( $config['settings']['consentExpiry'] ) ? $config['settings']['consentExpiry'] : array();
		$values['consent_expiry_days']           = empty( $expiry['status'] ) ? 0 : (int) $expiry['value'];
		$values['load_analytics_before_consent'] = ! empty( $config['behaviours']['loadAnalyticsByDefault']['status'] );
		$values['consent_log']                   = (bool) $settings->get_consent_log_status();

		return $values;
	}

	protected function validate_value( $name, $value, $schema ) {
		$value = parent::validate_value( $name, $value, $schema );
		if ( 'language' !== $name || is_wp_error( $value ) ) {
			return $value;
		}

		$value = strtolower( trim( $value ) );
		if ( ! preg_match( '/^[a-z]{2}(-[a-z]{2})?$/', $value ) || ! file_exists( CLI_PLUGIN_BASEPATH . 'lite/admin/modules/banners/includes/contents/' . $value . '.json' ) ) {
			return new \WP_Error( 'invalid', __( 'Langue non traduite par l\'extension.', 'eo-tools' ) );
		}

		return $value;
	}

	protected function write_settings( $clean, $target ) {
		$banner = $this->get_banner();
		if ( is_wp_error( $banner ) ) {
			return $banner;
		}

		// Site-wide settings first: the banner texts follow the selected languages.
		$settings = Settings::get_instance();
		$data     = $settings->get();
		if ( isset( $clean['language'] ) ) {
			$data['languages'] = array(
				'default'  => $clean['language'],
				'selected' => array( $clean['language'] ),
			);
		}
		if ( isset( $clean['consent_log'] ) ) {
			$data['consent_logs']['status'] = $clean['consent_log'];
		}
		$settings->update( $data );

		$config = $banner->get_settings();
		if ( isset( $clean['language'] ) ) {
			$config['settings']['languages'] = $data['languages'];
		}
		foreach ( self::BUTTONS as $name => $button ) {
			if ( isset( $clean[ $name ] ) ) {
				$config['config']['notice']['elements']['buttons']['elements'][ $button ]['status'] = $clean[ $name ];
			}
		}
		if ( isset( $clean['consent_expiry_days'] ) ) {
			$config['settings']['consentExpiry'] = array(
				'status' => true,
				'value'  => (string) $clean['consent_expiry_days'],
			);
		}
		if ( isset( $clean['load_analytics_before_consent'] ) ) {
			$config['behaviours']['loadAnalyticsByDefault']['status'] = $clean['load_analytics_before_consent'];
		}
		$banner->set_settings( $config );
		$banner->save();

		return array();
	}

	protected function get_status() {
		$banner   = $this->get_banner();
		$settings = Settings::get_instance();

		return array(
			'law'                => is_wp_error( $banner ) ? '' : $banner->get_law(),
			'account_connected'  => (bool) $settings->get( 'account', 'connected' ),
			'cookie_scan_url'    => admin_url( 'admin.php?page=cookie-consent#/cookies' ),
			'cookie_scan_note'   => __( 'Le scan des cookies demande de connecter un compte WebToffee (gratuit) : un humain le lance depuis cet écran.', 'eo-tools' ),
			'privacy_policy_tip' => __( 'Pour la politique de confidentialité, cherchez le modèle de l\'extension avec eo-tools/search-source.', 'eo-tools' ),
		);
	}
}
