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
