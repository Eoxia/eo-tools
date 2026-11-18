<?php
/**
 * Plugin adapter: SEOKEY (free or PRO), site-wide settings.
 *
 * SEOKEY is not an Eoxia plugin: this gateway never changes its code. It
 * writes the same options as the SEOKEY > Réglages screen (seokey-field-*),
 * with update_option(), so that SEOKEY's own hooks run (home page title
 * synchronisation, sitemaps, list of known content types).
 *
 * Stays with a human: the setup wizard (it creates files on the server), the
 * licence and the Search Console connection (accounts).
 *
 * SEO fields of a page or of a category are not here: "seo" field of
 * eo-tools/update-draft / eo-tools/propose-update, and eo-tools/propose-term-seo.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Plugin_Seokey extends Eotools_Mcp_Plugin_Adapter {

	const PREFIX = 'seokey-field-';

	/**
	 * Value SEOKEY keeps in its lists of content types, so that they are never empty.
	 */
	const DUMMY = 'i_am_a_dummy_value';

	/**
	 * "SEO optimizations" switches, keyed by EO Tools name. SEOKEY treats a missing option as "on".
	 */
	const OPTIMIZATIONS = array(
		'no_author_pagination'  => 'seooptimizations-pagination-authors',
		'no_comment_pagination' => 'seooptimizations-pagination-comments',
		'no_reply_links'        => 'seooptimizations-replylinks',
		'hide_noindexed'        => 'seooptimizations-hide-noindexed',
		'redirect_rss_feeds'    => 'seooptimizations-rss-secondary',
	);

	public function get_slug() {
		return 'seo-key-pro';
	}

	public function get_label() {
		return defined( 'SEOKEY_NAME' ) ? SEOKEY_NAME : 'SEOKEY';
	}

	public function is_available() {
		return defined( 'SEOKEY_VERSION' );
	}

	/**
	 * @return string[] Public content types (as in the SEOKEY screen, without attachments).
	 */
	private function get_post_types() {
		return array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
	}

	/**
	 * @return string[] Public taxonomies (as in the SEOKEY screen, without post formats).
	 */
	private function get_taxonomies() {
		return array_values( array_diff( get_taxonomies( array( 'public' => true ) ), array( 'post_format' ) ) );
	}

	public function get_schema() {
		return array(
			'indexed_post_types'    => array(
				'type'    => 'list',
				'choices' => $this->get_post_types(),
				'label'   => __( 'Types de contenus montrés aux moteurs : les autres sont en noindex et hors du plan de site. Remplace la liste actuelle.', 'eo-tools' ),
			),
			'indexed_taxonomies'    => array(
				'type'    => 'list',
				'choices' => $this->get_taxonomies(),
				'label'   => __( 'Taxonomies dont les archives sont montrées aux moteurs. Remplace la liste actuelle.', 'eo-tools' ),
			),
			'home_title'            => array(
				'type'  => 'string',
				'label' => __( 'Titre SEO de la page d\'accueil (avec une page d\'accueil statique, SEOKEY le synchronise avec cette page)', 'eo-tools' ),
			),
			'home_description'      => array(
				'type'  => 'text',
				'label' => __( 'Méta description de la page d\'accueil', 'eo-tools' ),
			),
			'website_owner'         => array(
				'type'    => 'enum',
				'choices' => array( 'none', 'person', 'local_business' ),
				'label'   => __( 'Qui est derrière le site, pour les données structurées : "person", "local_business" (entreprise, organisation, association) ou "none". Le détail (nom, adresse, horaires…) se saisit dans SEOKEY > Réglages', 'eo-tools' ),
			),
			'opengraph'             => array(
				'type'  => 'bool',
				'label' => __( 'Balises de partage Open Graph et Twitter Card ajoutées par SEOKEY (à désactiver si une autre extension les ajoute déjà)', 'eo-tools' ),
			),
			'no_author_pagination'  => array(
				'type'  => 'bool',
				'label' => __( 'Pas de pagination sur les pages d\'auteur', 'eo-tools' ),
			),
			'no_comment_pagination' => array(
				'type'  => 'bool',
				'label' => __( 'Pas de pagination des commentaires', 'eo-tools' ),
			),
			'no_reply_links'        => array(
				'type'  => 'bool',
				'label' => __( 'Pas de liens « Répondre » sous chaque commentaire', 'eo-tools' ),
			),
			'hide_noindexed'        => array(
				'type'  => 'bool',
				'label' => __( 'Retirer les contenus en noindex des listes (archives, blog)', 'eo-tools' ),
			),
			'redirect_rss_feeds'    => array(
				'type'  => 'bool',
				'label' => __( 'Désactiver les flux RSS secondaires (redirigés vers le flux principal)', 'eo-tools' ),
			),
		);
	}

	/**
	 * @param string $key     Option name without the prefix.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function option( $key, $default = false ) {
		return get_option( self::PREFIX . $key, $default );
	}

	/**
	 * @param string $key Option name without the prefix.
	 * @return string[] Content types or taxonomies of a SEOKEY list.
	 */
	private function read_list( $key ) {
		return array_values( array_diff( (array) $this->option( $key, array() ), array( self::DUMMY ) ) );
	}

	protected function read_settings( $target ) {
		$context  = (string) $this->option( 'schemaorg-context', '0' );
		$settings = array(
			'indexed_post_types' => $this->read_list( 'cct-cpt' ),
			'indexed_taxonomies' => $this->read_list( 'cct-taxo' ),
			'home_title'         => (string) $this->option( 'metas-metatitle', '' ),
			'home_description'   => (string) $this->option( 'metas-metadesc', '' ),
			'website_owner'      => in_array( $context, array( 'person', 'local_business' ), true ) ? $context : 'none',
			'opengraph'          => 1 === (int) $this->option( 'seooptimizations-opengraph' ),
		);
		foreach ( self::OPTIMIZATIONS as $name => $key ) {
			$value             = (string) $this->option( $key, 'yes' );
			$settings[ $name ] = 'yes' === $value || '1' === $value;
		}

		return $settings;
	}

	/**
	 * Lists: values among "choices".
	 */
	protected function validate_value( $name, $value, $schema ) {
		if ( 'list' !== $schema['type'] ) {
			return parent::validate_value( $name, $value, $schema );
		}

		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'invalid', __( 'Liste attendue.', 'eo-tools' ) );
		}
		$unknown = array_diff( $value, $schema['choices'] );
		if ( $unknown ) {
			return new \WP_Error( 'invalid', __( 'Valeurs possibles : ', 'eo-tools' ) . implode( ', ', $schema['choices'] ) );
		}

		return array_values( array_unique( array_map( 'strval', $value ) ) );
	}

	protected function check_values( $clean, $target ) {
		$errors = array();
		if ( isset( $clean['indexed_post_types'] ) && ! array_intersect( array( 'post', 'page' ), $clean['indexed_post_types'] ) ) {
			$errors['indexed_post_types'] = __( 'Ni les articles ni les pages ne seraient montrés aux moteurs : le site disparaîtrait de Google. Gardez au moins "page" ou "post".', 'eo-tools' );
		}

		return $errors;
	}

	protected function write_settings( $clean, $target ) {
		$notes = array();

		foreach ( $clean as $name => $value ) {
			switch ( $name ) {
				case 'indexed_post_types':
					update_option( self::PREFIX . 'cct-cpt', array_merge( array( self::DUMMY ), $value ), true );
					break;
				case 'indexed_taxonomies':
					update_option( self::PREFIX . 'cct-taxo', array_merge( array( self::DUMMY ), $value ), true );
					break;
				case 'home_title':
					update_option( self::PREFIX . 'metas-metatitle', $value, true );
					break;
				case 'home_description':
					update_option( self::PREFIX . 'metas-metadesc', $value, true );
					break;
				case 'website_owner':
					update_option( self::PREFIX . 'schemaorg-context', 'none' === $value ? '0' : $value, true );
					if ( 'none' !== $value ) {
						$notes[] = __( 'Le détail de la personne ou de l\'entreprise (nom, logo, adresse, horaires, réseaux sociaux) se saisit dans SEOKEY > Réglages > Schema.org.', 'eo-tools' );
					}
					break;
				case 'opengraph':
					update_option( self::PREFIX . 'seooptimizations-opengraph', $value ? '1' : '0', true );
					break;
				default:
					update_option( self::PREFIX . self::OPTIMIZATIONS[ $name ], $value ? '1' : '0', true );
			}
		}

		if ( isset( $clean['indexed_post_types'] ) || isset( $clean['indexed_taxonomies'] ) ) {
			$notes[] = __( 'Les contenus exclus sont maintenant en noindex et retirés du plan de site SEOKEY. Relancez eo-tools/audit-site-seo avec refresh=true pour vérifier.', 'eo-tools' );
		}

		return $notes;
	}

	protected function get_status() {
		$wizard_done = 'goodtogo' === get_option( 'seokey_option_first_wizard_seokey_notice_wizard' );

		return array(
			'wizard_done'    => $wizard_done,
			'wizard'         => $wizard_done ? '' : __( 'L\'assistant de démarrage de SEOKEY n\'est pas terminé : tant qu\'il ne l\'est pas, SEOKEY n\'ajoute aucune balise aux pages. Il crée des fichiers sur le serveur (robots.txt) : l\'utilisateur le termine lui-même avec le lien "wizard_url".', 'eo-tools' ),
			'wizard_url'     => admin_url( 'admin.php?page=seo-key-wizard' ),
			'settings_url'   => admin_url( 'admin.php?page=seo-key-settings' ),
			'search_console' => ! empty( get_option( 'seokey-gsc-site' ) ) ? __( 'Connectée', 'eo-tools' ) : __( 'Non connectée : l\'utilisateur la connecte dans SEOKEY > Réglages > Search Console (compte Google).', 'eo-tools' ),
			'page_meta'      => __( 'Titre SEO, méta description, requête cible et noindex d\'une page : champ "seo" de eo-tools/update-draft (brouillon) ou eo-tools/propose-update (page publiée). Catégories : eo-tools/propose-term-seo.', 'eo-tools' ),
		);
	}
}
