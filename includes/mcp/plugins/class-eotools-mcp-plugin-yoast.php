<?php
/**
 * Plugin adapter: Yoast SEO, site-wide settings.
 *
 * The settings of the first-time configuration: site representation
 * (organization or person, name, logo), website name, social profiles, usage
 * tracking, and the configuration marked as done. Saved with WPSEO_Options,
 * which applies the Yoast checks.
 *
 * SEO title, meta description and focus keyword of a page are not here: they
 * are the "seo" field of eo-tools/update-draft and eo-tools/propose-update,
 * written through the SEO provider (includes/mcp/seo/).
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugin_Yoast extends Eotools_Mcp_Plugin_Adapter {

	/**
	 * Yoast options keyed by EO Tools name.
	 */
	const MAP = array(
		'site_represents'        => 'company_or_person',
		'organization_name'      => 'company_name',
		'organization_logo_id'   => 'company_logo_id',
		'person_user_id'         => 'company_or_person_user_id',
		'website_name'           => 'website_name',
		'alternate_website_name' => 'alternate_website_name',
		'facebook_url'           => 'facebook_site',
		'x_username'             => 'twitter_site',
		'other_social_urls'      => 'other_social_urls',
		'usage_tracking'         => 'tracking',
	);

	/**
	 * Steps of the first-time configuration (the Yoast screen marks it done with three of them).
	 */
	const CONFIGURATION_STEPS = array( 'siteRepresentation', 'socialProfiles', 'personalPreferences' );

	public function get_slug() {
		return 'wordpress-seo';
	}

	public function get_label() {
		return 'Yoast SEO';
	}

	public function is_available() {
		return class_exists( '\WPSEO_Options' ) && defined( 'WPSEO_VERSION' );
	}

	public function get_schema() {
		return array(
			'site_represents'          => array(
				'type'    => 'enum',
				'choices' => array( 'company', 'person' ),
				'label'   => __( 'Le site représente une organisation ("company") ou une personne ("person")', 'eo-tools' ),
			),
			'organization_name'        => array(
				'type'  => 'string',
				'label' => __( 'Nom de l\'organisation', 'eo-tools' ),
			),
			'organization_logo_id'     => array(
				'type'  => 'int',
				'label' => __( 'Logo de l\'organisation : identifiant d\'une image de la médiathèque (eo-tools/import-image), 0 pour le retirer', 'eo-tools' ),
			),
			'person_user_id'           => array(
				'type'  => 'int',
				'label' => __( 'Personne représentée : identifiant de son compte utilisateur', 'eo-tools' ),
			),
			'website_name'             => array(
				'type'  => 'string',
				'label' => __( 'Nom du site affiché par Google (vide : titre du site)', 'eo-tools' ),
			),
			'alternate_website_name'   => array(
				'type'  => 'string',
				'label' => __( 'Autre nom du site (sigle, nom court)', 'eo-tools' ),
			),
			'facebook_url'             => array(
				'type'  => 'url',
				'label' => __( 'Page Facebook', 'eo-tools' ),
			),
			'x_username'               => array(
				'type'  => 'string',
				'label' => __( 'Compte X (Twitter), sans @', 'eo-tools' ),
			),
			'other_social_urls'        => array(
				'type'  => 'urls',
				'label' => __( 'Autres profils (LinkedIn, Instagram, YouTube…) : liste d\'adresses, remplace la liste actuelle', 'eo-tools' ),
			),
			'usage_tracking'           => array(
				'type'  => 'bool',
				'label' => __( 'Partager des données d\'utilisation avec Yoast (false par défaut)', 'eo-tools' ),
			),
			'configuration_done'       => array(
				'type'  => 'bool',
				'label' => __( 'Configuration initiale terminée (true une fois les réglages ci-dessus faits : retire le rappel de Yoast)', 'eo-tools' ),
			),
		);
	}

	protected function read_settings( $target ) {
		$settings = array();
		foreach ( self::MAP as $name => $key ) {
			$value = \WPSEO_Options::get( $key );
			switch ( $this->get_schema()[ $name ]['type'] ) {
				case 'bool':
					$settings[ $name ] = (bool) $value;
					break;
				case 'int':
					$settings[ $name ] = (int) $value;
					break;
				case 'urls':
					$settings[ $name ] = array_values( (array) $value );
					break;
				default:
					$settings[ $name ] = (string) $value;
			}
		}
		$settings['configuration_done'] = ! array_diff( self::CONFIGURATION_STEPS, (array) \WPSEO_Options::get( 'configuration_finished_steps', array() ) );

		return $settings;
	}

	protected function check_values( $clean, $target ) {
		$errors = array();
		if ( ! empty( $clean['organization_logo_id'] ) && ! wp_attachment_is_image( $clean['organization_logo_id'] ) ) {
			$errors['organization_logo_id'] = __( 'Ce média n\'est pas une image.', 'eo-tools' );
		}
		if ( ! empty( $clean['person_user_id'] ) && ! get_userdata( $clean['person_user_id'] ) ) {
			$errors['person_user_id'] = __( 'Aucun utilisateur avec cet identifiant.', 'eo-tools' );
		}
		if ( isset( $clean['x_username'] ) && '' !== $clean['x_username'] && ! preg_match( '/^@?[A-Za-z0-9_]{1,25}$/', $clean['x_username'] ) ) {
			$errors['x_username'] = __( 'Nom de compte attendu (lettres, chiffres et _), sans adresse.', 'eo-tools' );
		}

		return $errors;
	}

	protected function write_settings( $clean, $target ) {
		foreach ( $clean as $name => $value ) {
			switch ( $name ) {
				case 'configuration_done':
					\WPSEO_Options::set( 'configuration_finished_steps', $value ? self::CONFIGURATION_STEPS : array() );
					if ( $value ) {
						\WPSEO_Options::set( 'first_time_install', false );
					}
					break;

				case 'organization_logo_id':
					// Yoast drops the logo ID when the logo URL changes: URL first.
					\WPSEO_Options::set( 'company_logo', $value ? (string) wp_get_attachment_url( $value ) : '' );
					\WPSEO_Options::set( 'company_logo_id', $value );
					break;

				case 'x_username':
					\WPSEO_Options::set( 'twitter_site', ltrim( $value, '@' ) );
					break;

				default:
					\WPSEO_Options::set( self::MAP[ $name ], $value );
			}
		}

		$notes    = array();
		$settings = $this->read_settings( $target );
		if ( 'company' === $settings['site_represents'] && ( '' === $settings['organization_name'] || ! $settings['organization_logo_id'] ) ) {
			$notes[] = __( 'Yoast attend un nom et un logo pour l\'organisation (données structurées).', 'eo-tools' );
		}

		return $notes;
	}

	protected function get_status() {
		return array(
			'sitemap_url'       => \WPSEO_Options::get( 'enable_xml_sitemap', true ) ? home_url( '/sitemap_index.xml' ) : '',
			'configuration_url' => admin_url( 'admin.php?page=wpseo_dashboard#/first-time-configuration' ),
			'page_meta'         => __( 'Titre SEO, méta description et requête cible d\'une page : champ "seo" de eo-tools/update-draft (brouillon) ou eo-tools/propose-update (page publiée).', 'eo-tools' ),
		);
	}
}
