<?php
/**
 * SEO data from Yoast SEO.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Seo_Provider_Yoast extends Eotools_Mcp_Seo_Provider {

	public function get_id() {
		return 'yoast';
	}

	public function get_name() {
		return 'Yoast SEO';
	}

	public function get_version() {
		return defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : '';
	}

	public function is_active() {
		return defined( 'WPSEO_VERSION' ) && function_exists( 'wpseo_replace_vars' ) && class_exists( '\WPSEO_Options' );
	}

	/**
	 * Yoast's own ability writing the SEO data of a post (Yoast SEO 28 and later).
	 */
	const UPDATE_ABILITY = 'yoast-seo/update-post-seo-data';

	/**
	 * Fields of Yoast's update ability, keyed by EO Tools field.
	 */
	const ABILITY_FIELDS = array(
		'title'          => 'seo_title',
		'description'    => 'meta_description',
		'canonical'      => 'canonical',
		'og_title'       => 'open_graph_title',
		'og_description' => 'open_graph_description',
		'cornerstone'    => 'is_cornerstone',
		'noindex'        => 'noindex',
	);

	public function get_meta_keys() {
		return array(
			'title'          => '_yoast_wpseo_title',
			'description'    => '_yoast_wpseo_metadesc',
			'focus_keyword'  => '_yoast_wpseo_focuskw',
			'noindex'        => '_yoast_wpseo_meta-robots-noindex',
			'canonical'      => '_yoast_wpseo_canonical',
			'og_title'       => '_yoast_wpseo_opengraph-title',
			'og_description' => '_yoast_wpseo_opengraph-description',
			'og_image_id'    => '_yoast_wpseo_opengraph-image-id',
			'cornerstone'    => '_yoast_wpseo_is_cornerstone',
		);
	}

	/**
	 * Whether the content type is excluded from search engines by default.
	 *
	 * @param int $post_id Content ID.
	 * @return bool
	 */
	private function type_noindex( $post_id ) {
		return (bool) $this->option( 'noindex-' . get_post_type( $post_id ), false );
	}

	/**
	 * Yoast stores "1" (noindex), "2" (index) or nothing (default of the content type).
	 */
	protected function read_field( $post_id, $field, $key ) {
		if ( 'noindex' === $field ) {
			return '1' === (string) get_post_meta( $post_id, $key, true );
		}

		return parent::read_field( $post_id, $field, $key );
	}

	protected function write_field( $post_id, $field, $key, $value ) {
		if ( 'noindex' === $field ) {
			if ( $value ) {
				update_post_meta( $post_id, $key, '1' );
			} elseif ( $this->type_noindex( $post_id ) ) {
				// Back to "index" although the content type is excluded by default.
				update_post_meta( $post_id, $key, '2' );
			} else {
				delete_post_meta( $post_id, $key );
			}

			return;
		}

		parent::write_field( $post_id, $field, $key, $value );

		// Yoast also keeps the address of the sharing image.
		if ( 'og_image_id' === $field ) {
			$url = $value ? wp_get_attachment_image_url( $value, 'full' ) : '';
			if ( $url ) {
				update_post_meta( $post_id, '_yoast_wpseo_opengraph-image', wp_slash( $url ) );
			} else {
				delete_post_meta( $post_id, '_yoast_wpseo_opengraph-image' );
			}
		}
	}

	/**
	 * On a published content, writes again through Yoast's own ability.
	 *
	 * A post meta write alone leaves Yoast's index (what it outputs on the
	 * front) out of date until the next save. Yoast's ability writes the
	 * values the same way as its editor and rebuilds its index from all the
	 * post meta, including the fields it does not handle itself (target
	 * query, sharing image). Without it (older Yoast), the post meta are
	 * written and Yoast refreshes on the next save.
	 */
	protected function after_write( $post_id, $fields ) {
		if ( ! in_array( get_post_status( $post_id ), array( 'publish', 'private' ), true ) || ! function_exists( 'wp_get_ability' ) ) {
			return;
		}

		$ability = wp_get_ability( self::UPDATE_ABILITY );
		if ( ! $ability ) {
			return;
		}

		$values = $this->get_seo_values( $post_id );
		$input  = array( 'post_id' => (int) $post_id );
		foreach ( self::ABILITY_FIELDS as $field => $name ) {
			if ( ! in_array( $field, $fields, true ) ) {
				continue;
			}
			$value = $values[ $field ];
			if ( 'noindex' === $field ) {
				// Tri-state for Yoast: null is the default of the content type.
				$value = $value ? true : ( $this->type_noindex( $post_id ) ? false : null );
			}
			$input[ $name ] = $value;
		}

		$ability->execute( $input );
	}

	/**
	 * Yoast keeps the SEO data of terms in its own option (wpseo_taxonomy_meta), keyed by field here.
	 */
	public function get_term_meta_keys() {
		if ( ! class_exists( '\WPSEO_Taxonomy_Meta' ) ) {
			return array();
		}

		return array(
			'title'          => 'wpseo_title',
			'description'    => 'wpseo_desc',
			'focus_keyword'  => 'wpseo_focuskw',
			'noindex'        => 'wpseo_noindex',
			'canonical'      => 'wpseo_canonical',
			'og_title'       => 'wpseo_opengraph-title',
			'og_description' => 'wpseo_opengraph-description',
			'og_image_id'    => 'wpseo_opengraph-image-id',
		);
	}

	/**
	 * @param \WP_Term $term Term.
	 * @return array Yoast data of the term, keyed by Yoast key.
	 */
	private function term_data( $term ) {
		$data = \WPSEO_Taxonomy_Meta::get_term_meta( $term, $term->taxonomy );

		return is_array( $data ) ? $data : array();
	}

	public function get_term_seo_values( $term ) {
		$data   = $this->term_data( $term );
		$values = array();
		foreach ( $this->get_term_meta_keys() as $field => $key ) {
			$raw              = isset( $data[ $key ] ) ? $data[ $key ] : '';
			$values[ $field ] = 'noindex' === $field ? 'noindex' === $raw : self::typed_value( $field, $raw );
		}

		return $values;
	}

	/**
	 * Writes with Yoast's own API, then saves the term so that Yoast rebuilds its index.
	 *
	 * Every current value is passed again: Yoast resets the fields that are missing.
	 */
	public function set_term_seo( $term, $values, &$warnings = array() ) {
		$keys  = $this->get_term_meta_keys();
		$clean = $this->clean_values( $values, array_keys( $keys ), $warnings );
		if ( ! $clean ) {
			return array();
		}

		$data = $this->term_data( $term );
		foreach ( $clean as $field => $value ) {
			if ( 'noindex' === $field ) {
				$by_default = (bool) $this->option( 'noindex-tax-' . $term->taxonomy, false );
				$value      = $value ? 'noindex' : ( $by_default ? 'index' : 'default' );
			} elseif ( 'og_image_id' === $field ) {
				$data['wpseo_opengraph-image'] = $value ? (string) wp_get_attachment_image_url( $value, 'full' ) : '';
				$value                         = $value ? (string) $value : '';
			} elseif ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			}
			$data[ $keys[ $field ] ] = $value;
		}

		\WPSEO_Taxonomy_Meta::set_values( $term->term_id, $term->taxonomy, $data );
		wp_update_term( $term->term_id, $term->taxonomy, array() );

		return array_keys( $clean );
	}

	/**
	 * @param \WP_Term $term     Term.
	 * @param string   $template Template stored for the term, or empty.
	 * @param string   $option   Default template option.
	 * @return string Template with the variables replaced.
	 */
	private function term_template( $term, $template, $option ) {
		if ( '' === $template ) {
			$template = (string) $this->option( $option . '-tax-' . $term->taxonomy );
		}

		return '' === $template ? '' : self::clean( wpseo_replace_vars( $template, $term ) );
	}

	public function get_term_seo_title( $term ) {
		$data  = $this->term_data( $term );
		$title = $this->term_template( $term, isset( $data['wpseo_title'] ) ? (string) $data['wpseo_title'] : '', 'title' );

		return '' !== $title ? $title : parent::get_term_seo_title( $term );
	}

	public function has_custom_term_title( $term ) {
		$data = $this->term_data( $term );

		return ! empty( $data['wpseo_title'] );
	}

	public function get_term_meta_description( $term ) {
		$data = $this->term_data( $term );

		return $this->term_template( $term, isset( $data['wpseo_desc'] ) ? (string) $data['wpseo_desc'] : '', 'metadesc' );
	}

	public function is_term_noindex( $term ) {
		$data  = $this->term_data( $term );
		$value = isset( $data['wpseo_noindex'] ) ? $data['wpseo_noindex'] : 'default';
		if ( 'default' !== $value ) {
			return 'noindex' === $value;
		}

		return (bool) $this->option( 'noindex-tax-' . $term->taxonomy, false );
	}

	public function supports( $feature ) {
		return in_array( $feature, array( 'meta_description', 'focus_keyword', 'seo_score', 'noindex', 'canonical' ), true );
	}

	/**
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function option( $key, $default = '' ) {
		return \WPSEO_Options::get( $key, $default );
	}

	public function get_seo_title( $post, $front_page = false ) {
		$template = (string) get_post_meta( $post->ID, '_yoast_wpseo_title', true );
		if ( '' === $template ) {
			$template = (string) $this->option( 'title-' . $post->post_type );
		}
		if ( '' !== $template ) {
			return self::clean( wpseo_replace_vars( $template, $post ) );
		}

		return parent::get_seo_title( $post, $front_page );
	}

	public function has_custom_title( $post ) {
		return '' !== (string) get_post_meta( $post->ID, '_yoast_wpseo_title', true );
	}

	public function get_meta_description( $post ) {
		$template = (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		if ( '' === $template ) {
			$template = (string) $this->option( 'metadesc-' . $post->post_type );
		}

		return '' === $template ? '' : self::clean( wpseo_replace_vars( $template, $post ) );
	}

	public function has_custom_description( $post ) {
		return '' !== (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
	}

	public function get_focus_keyword( $post ) {
		return (string) get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );
	}

	public function expects_focus_keyword() {
		return (bool) $this->option( 'keyword_analysis_active', true );
	}

	public function get_seo_score( $post ) {
		$score = get_post_meta( $post->ID, '_yoast_wpseo_linkdex', true );

		return '' === $score ? null : (int) $score;
	}

	public function is_noindex( $post ) {
		$value = (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true );
		if ( '1' === $value ) {
			return true;
		}
		if ( '2' === $value ) {
			return false;
		}

		return (bool) $this->option( 'noindex-' . $post->post_type, false );
	}

	public function get_canonical( $post ) {
		return (string) get_post_meta( $post->ID, '_yoast_wpseo_canonical', true );
	}

	public function get_post_details( $post ) {
		$readability = get_post_meta( $post->ID, '_yoast_wpseo_content_score', true );

		$analysis = Eotools_Mcp_Seo_Analysis::get( $post );
		$findings = array();
		if ( ! empty( $analysis['stale'] ) ) {
			$findings[] = array(
				'severity' => 'info',
				'message'  => __( 'Les scores et critères Yoast SEO datent d\'avant la dernière modification du contenu ou de ses métadonnées SEO : ils seront recalculés au prochain enregistrement dans l\'éditeur.', 'eo-tools' ),
			);
		}

		return array(
			'data'     => array(
				'readability_score' => '' === $readability ? null : (int) $readability,
				'cornerstone'       => '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_is_cornerstone', true ),
				'analysis'          => $analysis,
			),
			'findings' => $findings,
		);
	}

	public function get_sitemap() {
		if ( $this->option( 'enable_xml_sitemap', true ) ) {
			return array(
				'provider' => 'Yoast SEO',
				'url'      => home_url( '/sitemap_index.xml' ),
			);
		}

		return parent::get_sitemap();
	}

	public function get_settings( $post_types ) {
		$findings = array();
		$types    = array();
		foreach ( $post_types as $type ) {
			$types[ $type ] = array(
				'title_template'       => (string) $this->option( 'title-' . $type ),
				'description_template' => (string) $this->option( 'metadesc-' . $type ),
				'noindex'              => (bool) $this->option( 'noindex-' . $type, false ),
			);
		}

		$owner_type = (string) $this->option( 'company_or_person', 'company' );
		$settings   = array(
			'title_separator'  => (string) $this->option( 'separator' ),
			'website_name'     => (string) $this->option( 'website_name' ),
			'xml_sitemap'      => (bool) $this->option( 'enable_xml_sitemap', true ),
			'keyword_analysis' => (bool) $this->option( 'keyword_analysis_active', true ),
			'organization'     => array(
				'type' => $owner_type,
				'name' => 'company' === $owner_type ? (string) $this->option( 'company_name' ) : (string) get_the_author_meta( 'display_name', (int) $this->option( 'company_or_person_user_id', 0 ) ),
				'logo' => '' !== (string) $this->option( 'company_logo' ),
			),
			'verification'     => array(
				'google' => '' !== (string) $this->option( 'googleverify' ),
				'bing'   => '' !== (string) $this->option( 'msverify' ),
			),
			'post_types'       => $types,
		);

		if ( ! $settings['xml_sitemap'] ) {
			$findings[] = self::finding( 'warning', __( 'Le plan de site XML de Yoast est désactivé (Yoast SEO > Réglages > Fonctionnalités du site).', 'eo-tools' ) );
		}
		if ( '' === $settings['organization']['name'] ) {
			$findings[] = self::finding( 'info', __( 'Yoast : le nom de l\'organisation ou de la personne derrière le site n\'est pas renseigné (données structurées, Knowledge Graph).', 'eo-tools' ) );
		}
		if ( 'company' === $owner_type && ! $settings['organization']['logo'] ) {
			$findings[] = self::finding( 'info', __( 'Yoast : pas de logo d\'organisation (données structurées).', 'eo-tools' ) );
		}
		foreach ( $types as $type => $config ) {
			if ( $config['noindex'] ) {
				/* translators: %s: content type. */
				$findings[] = self::finding( 'info', sprintf( __( 'Yoast : le type de contenu « %s » est exclu des moteurs (noindex) pour tout le site.', 'eo-tools' ), $type ) );
			}
		}

		return array(
			'data'     => $settings,
			'findings' => $findings,
		);
	}
}
