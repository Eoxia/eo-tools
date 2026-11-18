<?php
/**
 * SEO data source: WordPress alone, or an SEO plugin (Yoast SEO, SEOKEY…).
 *
 * The SEO audit only talks to a provider, never to a plugin directly. To
 * support another SEO plugin, extend this class and add it with the
 * "eo_tools_seo_providers" filter (see Eotools_Mcp_Seo::get_provider()).
 *
 * This base class describes WordPress without any SEO plugin.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Seo_Provider {

	/**
	 * Features a provider may support.
	 *
	 * - meta_description: contents can have a meta description.
	 * - auto_description: the plugin writes a description itself when none is entered.
	 * - auto_title:       the plugin uses the content title when no SEO title is entered.
	 * - focus_keyword:    contents can have a target query.
	 * - seo_score:        the plugin computes an SEO score per content.
	 * - noindex:          contents can be excluded from search engines.
	 * - canonical:        contents can have a custom canonical URL.
	 */
	const FEATURES = array( 'meta_description', 'auto_description', 'auto_title', 'focus_keyword', 'seo_score', 'noindex', 'canonical' );

	/**
	 * Every SEO field a provider may let the AI write on a content, with its JSON Schema.
	 *
	 * A provider declares the fields it can write with get_meta_keys(). Empty
	 * values ("", false, 0) remove the field: the plugin then uses its default.
	 *
	 * @return array[] JSON Schema keyed by field.
	 */
	public static function get_field_definitions() {
		return array(
			'title'          => array(
				'type'        => 'string',
				'description' => __( 'Titre SEO (balise title), environ 30 à 65 caractères.', 'eo-tools' ),
			),
			'description'    => array(
				'type'        => 'string',
				'description' => __( 'Méta description, 120 à 155 caractères.', 'eo-tools' ),
			),
			'focus_keyword'  => array(
				'type'        => 'string',
				'description' => __( 'Requête cible (mot-clé principal). Une seule page par requête, sinon les pages se concurrencent.', 'eo-tools' ),
			),
			'noindex'        => array(
				'type'        => 'boolean',
				'description' => __( 'true : exclure ce contenu des moteurs de recherche (mentions légales, remerciements, doublons…). false : revenir au réglage par défaut de son type de contenu.', 'eo-tools' ),
			),
			'canonical'      => array(
				'type'        => 'string',
				'description' => __( 'URL canonique, seulement si ce contenu est le doublon d\'une autre page. Chaîne vide : l\'adresse du contenu lui-même (cas normal).', 'eo-tools' ),
			),
			'og_title'       => array(
				'type'        => 'string',
				'description' => __( 'Titre de partage sur les réseaux sociaux (Open Graph). Vide : le titre SEO.', 'eo-tools' ),
			),
			'og_description' => array(
				'type'        => 'string',
				'description' => __( 'Description de partage sur les réseaux sociaux (Open Graph). Vide : la méta description.', 'eo-tools' ),
			),
			'og_image_id'    => array(
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => __( 'Image de partage sur les réseaux sociaux : identifiant d\'un média (1200 × 630 px conseillé). 0 : l\'image mise en avant.', 'eo-tools' ),
			),
			'cornerstone'    => array(
				'type'        => 'boolean',
				'description' => __( 'Contenu pilier : une des pages les plus importantes du site, vers laquelle les autres doivent faire des liens.', 'eo-tools' ),
			),
		);
	}

	/**
	 * SEO fields the AI may write on a content, with the post meta that stores each one.
	 *
	 * @return string[] Post meta keys keyed by field (see get_field_definitions()). Empty when the provider cannot write SEO fields.
	 */
	public function get_meta_keys() {
		return array();
	}

	/**
	 * @return string[] Fields the AI may write on a content.
	 */
	public function get_seo_fields() {
		return array_keys( $this->get_meta_keys() );
	}

	/**
	 * Values entered on a content (not the defaults computed by the plugin).
	 *
	 * @param int $post_id Content ID.
	 * @return array Values keyed by field.
	 */
	public function get_seo_values( $post_id ) {
		$values = array();
		foreach ( $this->get_meta_keys() as $field => $key ) {
			$values[ $field ] = $this->read_field( $post_id, $field, $key );
		}

		return $values;
	}

	/**
	 * Writes SEO fields on a content. Only the given fields change; an empty value removes the field.
	 *
	 * @param int      $post_id  Content ID.
	 * @param array    $values   Values keyed by field (see get_meta_keys()).
	 * @param string[] $warnings Warnings, completed by reference (invalid or unsupported fields).
	 * @return string[] Fields written.
	 */
	public function set_seo( $post_id, $values, &$warnings = array() ) {
		$keys    = $this->get_meta_keys();
		$written = array();

		foreach ( (array) $values as $field => $value ) {
			if ( ! isset( $keys[ $field ] ) ) {
				/* translators: 1: SEO field, 2: SEO plugin name. */
				$warnings[] = sprintf( __( 'Champ SEO « %1$s » ignoré : %2$s ne le gère pas.', 'eo-tools' ), $field, $this->get_name() );
				continue;
			}

			$value = $this->sanitize_field( $field, $value );
			if ( is_wp_error( $value ) ) {
				$warnings[] = $value->get_error_message();
				continue;
			}

			$this->write_field( $post_id, $field, $keys[ $field ], $value );
			$written[] = $field;
		}

		if ( $written ) {
			$this->after_write( $post_id, $written );
		}

		return $written;
	}

	/**
	 * Copies the SEO fields of a proposal onto the published content.
	 *
	 * @param int $from_id Proposal ID.
	 * @param int $to_id   Published content ID.
	 */
	public function copy_seo( $from_id, $to_id ) {
		$this->set_seo( $to_id, $this->get_seo_values( $from_id ) );
	}

	/**
	 * @param string $field SEO field.
	 * @return string JSON type of the field.
	 */
	protected static function field_type( $field ) {
		$definitions = self::get_field_definitions();

		return isset( $definitions[ $field ] ) ? $definitions[ $field ]['type'] : 'string';
	}

	/**
	 * @param string $field SEO field.
	 * @param mixed  $value Value given by the AI.
	 * @return mixed|\WP_Error Clean value.
	 */
	protected function sanitize_field( $field, $value ) {
		switch ( self::field_type( $field ) ) {
			case 'boolean':
				return (bool) rest_sanitize_boolean( $value );

			case 'integer':
				$id = absint( $value );
				if ( $id && 'og_image_id' === $field && ! wp_attachment_is_image( $id ) ) {
					/* translators: %d: attachment ID. */
					return new \WP_Error( 'eo_tools_seo_image', sprintf( __( 'Image de partage ignorée : le média %d n\'est pas une image.', 'eo-tools' ), $id ) );
				}

				return $id;
		}

		if ( 'canonical' === $field ) {
			$url = trim( (string) $value );
			if ( '' !== $url && ! wp_http_validate_url( $url ) ) {
				/* translators: %s: URL. */
				return new \WP_Error( 'eo_tools_seo_canonical', sprintf( __( 'URL canonique ignorée : « %s » n\'est pas une adresse web complète (https://…).', 'eo-tools' ), $url ) );
			}

			return esc_url_raw( $url );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * @param int    $post_id Content ID.
	 * @param string $field   SEO field.
	 * @param string $key     Post meta key.
	 * @return mixed Typed value.
	 */
	protected function read_field( $post_id, $field, $key ) {
		$raw = get_post_meta( $post_id, $key, true );

		switch ( self::field_type( $field ) ) {
			case 'boolean':
				return (bool) $raw;
			case 'integer':
				return (int) $raw;
		}

		return (string) $raw;
	}

	/**
	 * Stores one field. Empty values remove the post meta.
	 *
	 * @param int    $post_id Content ID.
	 * @param string $field   SEO field.
	 * @param string $key     Post meta key.
	 * @param mixed  $value   Clean value.
	 */
	protected function write_field( $post_id, $field, $key, $value ) {
		if ( '' === $value || false === $value || 0 === $value || null === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, wp_slash( true === $value ? '1' : $value ) );
		}
	}

	/**
	 * Called once the fields are written, so that the plugin can refresh its own data.
	 *
	 * @param int      $post_id Content ID.
	 * @param string[] $fields  Fields written.
	 */
	protected function after_write( $post_id, $fields ) {
	}

	/**
	 * @return string Short identifier (yoast, seokey…).
	 */
	public function get_id() {
		return 'wordpress';
	}

	/**
	 * @return string Name shown to the AI and in the findings.
	 */
	public function get_name() {
		return 'WordPress';
	}

	/**
	 * @return string Plugin version, or empty.
	 */
	public function get_version() {
		return '';
	}

	/**
	 * @return bool Whether the plugin is active and usable.
	 */
	public function is_active() {
		return true;
	}

	/**
	 * @param string $feature One of FEATURES.
	 * @return bool
	 */
	public function supports( $feature ) {
		return false;
	}

	/**
	 * Recommended lengths, in characters.
	 *
	 * @return int[] Keys title_min, title_max, description_min, description_max.
	 */
	public function get_limits() {
		return array(
			'title_min'       => 30,
			'title_max'       => 65,
			'description_min' => 120,
			'description_max' => 160,
		);
	}

	/**
	 * Title shown in search results.
	 *
	 * @param \WP_Post $post       Content.
	 * @param bool     $front_page Whether the content is the home page.
	 * @return string
	 */
	public function get_seo_title( $post, $front_page = false ) {
		// Same parts as wp_get_document_title().
		$separator = apply_filters( 'document_title_separator', '-' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		$site_name = get_bloginfo( 'name', 'display' );
		$tagline   = get_bloginfo( 'description', 'display' );
		$parts     = $front_page ? array( $site_name, $tagline ) : array( $post->post_title, $site_name );

		return self::clean( implode( ' ' . $separator . ' ', array_filter( $parts, 'strlen' ) ) );
	}

	/**
	 * Whether an SEO title was entered for this content.
	 *
	 * @param \WP_Post $post Content.
	 * @return bool
	 */
	public function has_custom_title( $post ) {
		return false;
	}

	/**
	 * Meta description shown to search engines (entered, from a template, or generated).
	 *
	 * @param \WP_Post $post Content.
	 * @return string
	 */
	public function get_meta_description( $post ) {
		return '';
	}

	/**
	 * Whether a meta description was entered for this content.
	 *
	 * @param \WP_Post $post Content.
	 * @return bool
	 */
	public function has_custom_description( $post ) {
		return false;
	}

	/**
	 * @param \WP_Post $post Content.
	 * @return string Target query.
	 */
	public function get_focus_keyword( $post ) {
		return '';
	}

	/**
	 * Whether contents are expected to have a target query.
	 *
	 * @return bool
	 */
	public function expects_focus_keyword() {
		return $this->supports( 'focus_keyword' );
	}

	/**
	 * @param \WP_Post $post Content.
	 * @return int|null SEO score out of 100.
	 */
	public function get_seo_score( $post ) {
		return null;
	}

	/**
	 * @param \WP_Post $post Content.
	 * @return bool Whether the content is excluded from search engines.
	 */
	public function is_noindex( $post ) {
		return false;
	}

	/**
	 * @param \WP_Post $post Content.
	 * @return string Custom canonical URL, or empty.
	 */
	public function get_canonical( $post ) {
		return '';
	}

	/**
	 * Plugin-specific data of a content, for the detailed audit (scores, plugin audit…).
	 *
	 * @param \WP_Post $post Content.
	 * @return array Keys "data" and "findings".
	 */
	public function get_post_details( $post ) {
		return array(
			'data'     => array(),
			'findings' => array(),
		);
	}

	/**
	 * Active XML sitemap.
	 *
	 * @return array Keys provider and url (null when there is none).
	 */
	public function get_sitemap() {
		$server = function_exists( 'wp_sitemaps_get_server' ) ? wp_sitemaps_get_server() : null;
		if ( $server && $server->sitemaps_enabled() ) {
			return array(
				'provider' => 'WordPress',
				'url'      => get_sitemap_url( 'index' ),
			);
		}

		return array(
			'provider' => null,
			'url'      => null,
		);
	}

	/**
	 * Plugin settings that matter for an audit.
	 *
	 * @param string[] $post_types Content types visitors can reach.
	 * @return array Keys "data" and "findings".
	 */
	public function get_settings( $post_types ) {
		return array(
			'data'     => array(),
			'findings' => array(),
		);
	}

	/**
	 * @param string $severity critical, warning or info.
	 * @param string $message  Finding.
	 * @return array
	 */
	protected static function finding( $severity, $message ) {
		return array(
			'severity' => $severity,
			'message'  => $message,
		);
	}

	/**
	 * @param string $text Text with tags or entities.
	 * @return string
	 */
	public static function clean( $text ) {
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) );
	}
}
