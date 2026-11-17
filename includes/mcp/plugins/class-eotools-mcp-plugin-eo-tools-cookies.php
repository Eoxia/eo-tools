<?php
/**
 * Plugin adapter: the cookie manager of EO Tools (EO Tools > Cookies > Réglages).
 *
 * Manager on/off (consent banner and blocking of third-party cookies before
 * consent), lifetime of the visitor choices, banner icons. Same option as the
 * settings screen; the other keys of the option are kept.
 *
 * The scan and the cookie list are separate abilities (eo-tools/scan-cookies,
 * eo-tools/update-cookies, class Eotools_Mcp_Cookies).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Plugins;

use EoTools\Includes\Mcp\Eotools_Mcp_Cookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugin_Eo_Tools_Cookies extends Eotools_Mcp_Plugin_Adapter {

	const OPTION = 'eo_tools_cookies_settings';

	/**
	 * Settings keyed by EO Tools name => key of the option.
	 */
	const MAP = array(
		'active'                  => 'active',
		'consent_duration_months' => 'duration',
		'icon_full'               => 'icon_full',
		'icon_partial'            => 'icon_partial',
	);

	/**
	 * Other cookie banners: two banners must not run together.
	 */
	const OTHER_BANNERS = array(
		'webtoffee-cookie-consent/webtoffee-cookie-consent.php' => 'GDPR Cookie Consent (WebToffee)',
		'cookie-law-info/cookie-law-info.php'                   => 'CookieYes',
		'complianz-gdpr/complianz-gpdr.php'                     => 'Complianz',
		'cookie-notice/cookie-notice.php'                       => 'Cookie Notice',
	);

	public function get_slug() {
		return 'eo-tools-cookies';
	}

	public function get_label() {
		return 'EO Tools – Cookies';
	}

	public function is_available() {
		return true;
	}

	public function get_schema() {
		return array(
			'active'                  => array(
				'type'  => 'bool',
				'label' => __( 'Gestionnaire de cookies activé : affiche le bandeau de consentement et bloque les cookies tiers avant consentement', 'eo-tools' ),
			),
			'consent_duration_months' => array(
				'type'  => 'int',
				'min'   => 1,
				'max'   => 12,
				'label' => __( 'Durée de conservation des choix du visiteur, en mois (6 conseillé)', 'eo-tools' ),
			),
			'icon_full'               => array(
				'type'  => 'url',
				'label' => __( 'Icône du bouton de gestion quand tout est accepté (adresse d\'image, vide = icône par défaut)', 'eo-tools' ),
			),
			'icon_partial'            => array(
				'type'  => 'url',
				'label' => __( 'Icône du bouton de gestion quand le consentement est partiel (adresse d\'image, vide = icône par défaut)', 'eo-tools' ),
			),
		);
	}

	/**
	 * @return array
	 */
	private function get_option() {
		$settings = get_option( self::OPTION, array() );

		return is_array( $settings ) ? $settings : array();
	}

	protected function read_settings( $target ) {
		$settings = $this->get_option();

		return array(
			'active'                  => ! empty( $settings['active'] ),
			'consent_duration_months' => isset( $settings['duration'] ) ? (int) $settings['duration'] : 12,
			'icon_full'               => isset( $settings['icon_full'] ) ? (string) $settings['icon_full'] : '',
			'icon_partial'            => isset( $settings['icon_partial'] ) ? (string) $settings['icon_partial'] : '',
		);
	}

	protected function write_settings( $clean, $target ) {
		$settings = $this->get_option();
		foreach ( $clean as $name => $value ) {
			$settings[ self::MAP[ $name ] ] = $value;
		}
		if ( ! isset( $settings['duration'] ) ) {
			$settings['duration'] = 12;
		}
		update_option( self::OPTION, $settings );

		$notes = array();
		if ( ! empty( $settings['active'] ) ) {
			foreach ( $this->get_other_banners() as $label ) {
				/* translators: %s: plugin name */
				$notes[] = sprintf( __( '%s est aussi actif : deux bandeaux de cookies s\'afficheraient. Gardez-en un seul (désactivation avec eo-tools/request-elevation).', 'eo-tools' ), $label );
			}
			if ( ! Eotools_Mcp_Cookies::get_list() ) {
				$notes[] = __( 'La liste des cookies est vide : lancez le scan avec eo-tools/scan-cookies.', 'eo-tools' );
			} elseif ( ! Eotools_Mcp_Cookies::is_validated() ) {
				$notes[] = __( 'La liste des cookies n\'est pas encore validée : eo-tools/update-cookies avec "validate": true.', 'eo-tools' );
			}
		}

		return $notes;
	}

	/**
	 * Other cookie banner plugins that are active.
	 *
	 * @return string[]
	 */
	private function get_other_banners() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = array();
		foreach ( self::OTHER_BANNERS as $file => $label ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $label;
			}
		}

		return $active;
	}

	protected function get_status() {
		return array(
			'cookies'          => count( Eotools_Mcp_Cookies::get_list() ),
			'list_validated'   => Eotools_Mcp_Cookies::is_validated(),
			'other_banners'    => $this->get_other_banners(),
			'scan_and_cookies' => __( 'Scan : eo-tools/scan-cookies. Liste des cookies et validation : eo-tools/update-cookies.', 'eo-tools' ),
		);
	}
}
