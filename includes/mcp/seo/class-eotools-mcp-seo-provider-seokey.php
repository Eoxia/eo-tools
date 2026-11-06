<?php
/**
 * SEO data from SEOKEY (free or PRO).
 *
 * SEOKEY stores the SEO title, the meta description, the visibility and the
 * main keyword in post meta. Without an entered title it uses the content
 * title; without an entered description it generates one from the content.
 * Its own audit is stored in the {base_prefix}seokey_audit table.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Seo_Provider_Seokey extends Eotools_Mcp_Seo_Provider {

	const META_TITLE       = 'seokey-metatitle';
	const META_DESCRIPTION = 'seokey-metadesc';
	const META_VISIBILITY  = 'seokey-content_visibility';
	const META_KEYWORD     = 'seokey-main-keyword';

	/**
	 * Priority levels of the SEOKEY audit.
	 */
	const PRIORITIES = array(
		'1' => 'critical',
		'2' => 'error',
		'3' => 'warning',
		'4' => 'information',
	);

	public function get_id() {
		return 'seokey';
	}

	public function get_name() {
		return defined( 'SEOKEY_NAME' ) ? SEOKEY_NAME : 'SEOKEY';
	}

	public function get_version() {
		return defined( 'SEOKEY_VERSION' ) ? SEOKEY_VERSION : '';
	}

	public function is_active() {
		return defined( 'SEOKEY_VERSION' ) && function_exists( 'seokey_meta_title_value' ) && function_exists( 'seokey_meta_desc_value' );
	}

	public function supports( $feature ) {
		return in_array( $feature, array( 'meta_description', 'auto_description', 'auto_title', 'focus_keyword', 'noindex' ), true );
	}

	/**
	 * SEOKEY's own recommended lengths, so that both audits agree.
	 */
	public function get_limits() {
		$limits = parent::get_limits();

		foreach ( array(
			'title_min'       => 'METATITLE_COUNTER_MIN',
			'title_max'       => 'METATITLE_COUNTER_MAX',
			'description_min' => 'METADESC_COUNTER_MIN',
			'description_max' => 'METADESC_COUNTER_MAX',
		) as $key => $constant ) {
			if ( defined( $constant ) ) {
				$limits[ $key ] = (int) constant( $constant );
			}
		}

		return $limits;
	}

	/**
	 * @param string $key     Option name, without the "seokey-field-" prefix.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function option( $key, $default = false ) {
		return get_option( 'seokey-field-' . $key, $default );
	}

	/**
	 * Content type as SEOKEY names it.
	 *
	 * @param \WP_Post $post Content.
	 * @return string
	 */
	private function type( $post ) {
		if ( 'page' === get_option( 'show_on_front' ) ) {
			if ( (int) get_option( 'page_on_front' ) === $post->ID ) {
				return 'front_page_static';
			}
			if ( (int) get_option( 'page_for_posts' ) === $post->ID ) {
				return 'home';
			}
		}

		return 'singular';
	}

	public function get_seo_title( $post, $front_page = false ) {
		// Same steps as seokey_head_get_meta_title(): SEOKEY only keeps the title part.
		$parts     = array( 'title' => html_entity_decode( seokey_meta_title_value( $this->type( $post ), (int) $post->ID ), ENT_QUOTES, 'UTF-8' ) );
		$parts     = apply_filters( 'document_title_parts', $parts ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		$separator = apply_filters( 'document_title_separator', '-' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		return self::clean( implode( ' ' . $separator . ' ', array_filter( (array) $parts ) ) );
	}

	public function has_custom_title( $post ) {
		return '' !== trim( (string) get_post_meta( $post->ID, self::META_TITLE, true ) );
	}

	public function get_meta_description( $post ) {
		return self::clean( seokey_meta_desc_value( $this->type( $post ), (int) $post->ID ) );
	}

	public function has_custom_description( $post ) {
		return '' !== trim( (string) get_post_meta( $post->ID, self::META_DESCRIPTION, true ) );
	}

	public function get_focus_keyword( $post ) {
		return (string) get_post_meta( $post->ID, self::META_KEYWORD, true );
	}

	public function is_noindex( $post ) {
		if ( (bool) get_post_meta( $post->ID, self::META_VISIBILITY, true ) ) {
			return true;
		}

		// Content type left out in the SEOKEY settings (same test as SEOKEY's meta robots).
		return ! empty( $this->option( 'cct-cpt', array() ) ) && function_exists( 'seokey_helper_is_global_checked' ) && seokey_helper_is_global_checked( 'posts', $post->post_type );
	}

	/**
	 * Issues found by the SEOKEY audit for this content.
	 */
	public function get_post_details( $post ) {
		global $wpdb;

		$data = array(
			'custom_title'       => $this->has_custom_title( $post ),
			'custom_description' => $this->has_custom_description( $post ),
		);

		if ( ! $this->has_audit_table() ) {
			return array(
				'data'     => $data,
				'findings' => array(),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT task, priority, sub_priority, datas FROM {$wpdb->base_prefix}seokey_audit WHERE item_id = %d AND item_type_global = 'post' ORDER BY priority ASC",
				$post->ID
			)
		);

		$names  = function_exists( 'seokey_audit_get_task_name' ) ? seokey_audit_get_task_name() : array();
		$issues = array();
		foreach ( $rows as $row ) {
			$details  = '' === (string) $row->datas ? array() : @unserialize( $row->datas, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- SEOKEY stores serialized arrays.
			$issues[] = array(
				'task'         => $row->task,
				'label'        => isset( $names[ $row->task ] ) ? wp_strip_all_tags( $names[ $row->task ] ) : $row->task,
				'priority'     => isset( self::PRIORITIES[ $row->priority ] ) ? self::PRIORITIES[ $row->priority ] : $row->priority,
				'sub_priority' => $row->sub_priority,
				'details'      => is_array( $details ) ? $details : array(),
			);
		}

		$data['seokey_audit'] = array(
			'last_update' => $this->get_audit_date(),
			'issues'      => $issues,
			'note'        => __( 'Problèmes relevés par le dernier audit SEOKEY (lancé depuis SEOKEY > Audit). Ils peuvent dater : ils ne tiennent pas compte des modifications faites depuis.', 'eo-tools' ),
		);

		return array(
			'data'     => $data,
			'findings' => array(),
		);
	}

	public function get_sitemap() {
		if ( function_exists( 'seokey_helpers_get_sitemap_base_url' ) && function_exists( 'seokey_helper_cache_data' ) ) {
			$languages = seokey_helper_cache_data( 'languages' );
			$language  = isset( $languages['site']['default_lang'] ) ? $languages['site']['default_lang'] : null;
			$base      = (string) seokey_helpers_get_sitemap_base_url( $language );

			if ( $language && '' !== $base ) {
				if ( ! preg_match( '#^https?://#i', $base ) ) {
					$base = preg_replace( '#^(https?://[^/]+).*$#i', '$1', home_url() ) . '/' . ltrim( $base, '/' );
				}

				return array(
					'provider' => $this->get_name(),
					'url'      => $base . 'sitemap-index-' . $language . '.xml',
				);
			}
		}

		return parent::get_sitemap();
	}

	public function get_settings( $post_types ) {
		global $wpdb;

		$findings = array();
		$indexed  = (array) $this->option( 'cct-cpt', array() );
		$context  = $this->option( 'schemaorg-context' );
		$gsc      = get_option( 'seokey-gsc-site' );

		$settings = array(
			// SEOKEY adds nothing to the pages (title, description, robots…) until its setup wizard is finished.
			'wizard_done'           => 'goodtogo' === get_option( 'seokey_option_first_wizard_seokey_notice_wizard' ),
			'configured'            => ! empty( $indexed ),
			'indexed_post_types'    => array_values( $indexed ),
			'indexed_taxonomies'    => array_values( (array) $this->option( 'cct-taxo', array() ) ),
			'website_owner'         => false === $context ? null : ( '0' === (string) $context ? 'none' : (string) $context ),
			'search_console'        => ! empty( $gsc ),
			'hide_noindexed_in_lists' => in_array( (string) $this->option( 'seooptimizations-hide-noindexed', 'yes' ), array( 'yes', '1' ), true ),
		);

		if ( ! $settings['wizard_done'] ) {
			$findings[] = self::finding( 'critical', __( 'SEOKEY est actif mais son assistant de configuration n\'est pas terminé : tant qu\'il ne l\'est pas, SEOKEY n\'ajoute aucune balise aux pages (titre SEO, méta description, robots, données structurées). Terminez l\'assistant depuis le menu SEOKEY.', 'eo-tools' ) );
		} elseif ( ! $settings['configured'] ) {
			$findings[] = self::finding( 'warning', __( 'SEOKEY : les types de contenu à indexer ne sont pas définis (SEOKEY > Réglages).', 'eo-tools' ) );
		} else {
			foreach ( $post_types as $type ) {
				if ( ! in_array( $type, $indexed, true ) ) {
					/* translators: %s: content type. */
					$findings[] = self::finding( 'info', sprintf( __( 'SEOKEY : le type de contenu « %s » est exclu des moteurs (noindex) pour tout le site.', 'eo-tools' ), $type ) );
				}
			}
		}
		if ( null === $settings['website_owner'] || 'none' === $settings['website_owner'] ) {
			$findings[] = self::finding( 'info', __( 'SEOKEY : la personne ou l\'entreprise derrière le site n\'est pas renseignée (Réglages > Qui êtes-vous ?, utilisé pour les données structurées).', 'eo-tools' ) );
		}
		if ( ! $settings['search_console'] ) {
			$findings[] = self::finding( 'info', __( 'SEOKEY n\'est pas connecté à la Search Console : pas de données de trafic ni de positions dans SEOKEY.', 'eo-tools' ) );
		}

		// Last SEOKEY audit.
		$audit = array(
			'last_update' => $this->get_audit_date(),
			'score'       => null,
			'issues'      => array(),
		);
		if ( $audit['last_update'] && $this->has_audit_table() ) {
			$audit['score'] = (int) get_option( 'seokey_audit_global_data_score_now' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( "SELECT task, priority, item_type_global, COUNT(*) AS count FROM {$wpdb->base_prefix}seokey_audit GROUP BY task, priority, item_type_global ORDER BY priority ASC, count DESC" );
			$names = function_exists( 'seokey_audit_get_task_name' ) ? seokey_audit_get_task_name() : array();
			foreach ( $rows as $row ) {
				$audit['issues'][] = array(
					'task'     => $row->task,
					'label'    => isset( $names[ $row->task ] ) ? wp_strip_all_tags( $names[ $row->task ] ) : $row->task,
					'priority' => isset( self::PRIORITIES[ $row->priority ] ) ? self::PRIORITIES[ $row->priority ] : $row->priority,
					'scope'    => $row->item_type_global,
					'count'    => (int) $row->count,
				);
			}
		} else {
			$findings[] = self::finding( 'info', __( 'L\'audit SEOKEY n\'a jamais été lancé (SEOKEY > Audit).', 'eo-tools' ) );
		}
		$settings['seokey_audit'] = $audit;

		return array(
			'data'     => $settings,
			'findings' => $findings,
		);
	}

	/**
	 * @return string|null Date of the last SEOKEY audit (ISO 8601).
	 */
	private function get_audit_date() {
		$timestamp = (int) get_option( 'seokey_audit_global_last_update' );

		// SEOKEY stores a local timestamp (current_time( 'timestamp' )).
		return $timestamp ? gmdate( 'Y-m-d\TH:i:s', $timestamp ) : null;
	}

	/**
	 * @return bool
	 */
	private function has_audit_table() {
		global $wpdb;

		$table = $wpdb->base_prefix . 'seokey_audit';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}
}
