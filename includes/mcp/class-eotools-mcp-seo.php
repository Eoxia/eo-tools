<?php
/**
 * SEO data for the AI: site settings, indexing, published contents and 404 errors.
 *
 * EO Tools only collects facts and flags objective problems; the connected AI
 * analyses them and writes the recommendations. Works with WordPress alone and
 * reads the Yoast SEO data when Yoast is active.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

use EoTools\Includes\Mcp\Abilities\Eotools_Mcp_Ability_List_Posts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Seo {

	const CACHE_KEY   = 'eo_tools_mcp_seo_scan';
	const CACHE_TTL   = 900;
	const MAX_POSTS   = 3000;
	const THIN_WORDS  = 300;
	const TITLE_MIN   = 30;
	const TITLE_MAX   = 65;
	const DESC_MIN    = 120;
	const DESC_MAX    = 160;
	const SECTIONS    = array( 'settings', 'indexing', 'content', 'errors' );
	const SEVERITIES  = array( 'critical', 'warning', 'info' );

	/**
	 * @return bool
	 */
	public static function has_yoast() {
		return defined( 'WPSEO_VERSION' ) && function_exists( 'wpseo_replace_vars' ) && class_exists( '\WPSEO_Options' );
	}

	/**
	 * Yoast option, or a default when Yoast is missing.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function yoast_option( $key, $default = '' ) {
		return self::has_yoast() ? \WPSEO_Options::get( $key, $default ) : $default;
	}

	/**
	 * Content types visitors (and search engines) can reach.
	 *
	 * @return string[]
	 */
	public static function get_post_types() {
		return array_values( array_filter( Eotools_Mcp_Ability_List_Posts::get_post_types(), 'is_post_type_viewable' ) );
	}

	/**
	 * Problems the content scan can find, with what they mean.
	 *
	 * @return array[] Label, severity and description keyed by issue type.
	 */
	public static function get_issue_types() {
		$types = array(
			'missing_description'   => array(
				'severity'    => 'warning',
				'label'       => __( 'Méta description absente', 'eo-tools' ),
				'description' => __( 'Ni méta description propre au contenu, ni modèle Yoast pour ce type de contenu : Google choisit lui-même un extrait.', 'eo-tools' ),
				'yoast'       => true,
			),
			'description_length'    => array(
				'severity'    => 'info',
				/* translators: 1: minimum length, 2: maximum length. */
				'label'       => sprintf( __( 'Méta description hors de %1$d à %2$d caractères', 'eo-tools' ), self::DESC_MIN, self::DESC_MAX ),
				'description' => __( 'Trop courte, elle est peu incitative ; trop longue, elle est coupée dans les résultats.', 'eo-tools' ),
				'yoast'       => true,
			),
			'duplicate_description' => array(
				'severity'    => 'warning',
				'label'       => __( 'Méta description en double', 'eo-tools' ),
				'description' => __( 'Plusieurs contenus indexables ont la même méta description.', 'eo-tools' ),
				'yoast'       => true,
			),
			'duplicate_title'       => array(
				'severity'    => 'warning',
				'label'       => __( 'Titre SEO en double', 'eo-tools' ),
				'description' => __( 'Plusieurs contenus indexables ont le même titre SEO : les moteurs ne savent pas lequel afficher.', 'eo-tools' ),
			),
			'title_length'          => array(
				'severity'    => 'info',
				/* translators: 1: minimum length, 2: maximum length. */
				'label'       => sprintf( __( 'Titre SEO hors de %1$d à %2$d caractères', 'eo-tools' ), self::TITLE_MIN, self::TITLE_MAX ),
				'description' => __( 'Trop court, il est peu descriptif ; trop long, il est coupé dans les résultats.', 'eo-tools' ),
			),
			'missing_focus_keyword' => array(
				'severity'    => 'info',
				'label'       => __( 'Pas de requête cible Yoast', 'eo-tools' ),
				'description' => __( 'Aucune requête cible n\'est renseignée : Yoast ne peut pas analyser l\'optimisation du contenu.', 'eo-tools' ),
				'yoast'       => true,
			),
			'low_seo_score'         => array(
				'severity'    => 'warning',
				'label'       => __( 'Score SEO Yoast faible', 'eo-tools' ),
				'description' => __( 'Yoast a calculé un score SEO « à améliorer » (40/100 ou moins) pour la requête cible.', 'eo-tools' ),
				'yoast'       => true,
			),
			'thin_content'          => array(
				'severity'    => 'warning',
				/* translators: %d: number of words. */
				'label'       => sprintf( __( 'Contenu court (moins de %d mots)', 'eo-tools' ), self::THIN_WORDS ),
				'description' => __( 'Un contenu court a peu de chances de bien se positionner. Le décompte est approximatif (texte enregistré du contenu, sans les compositions ni les blocs réutilisables).', 'eo-tools' ),
			),
			'h1_in_content'         => array(
				'severity'    => 'info',
				'label'       => __( 'Titre H1 dans le contenu', 'eo-tools' ),
				'description' => __( 'Le thème affiche en général déjà le titre du contenu en H1 : un H1 de plus dans le contenu en fait deux. À vérifier avec eo-tools/check-render.', 'eo-tools' ),
			),
			'images_without_alt'    => array(
				'severity'    => 'warning',
				'label'       => __( 'Images sans texte alternatif', 'eo-tools' ),
				'description' => __( 'Images du contenu sans texte alternatif : moins bonne accessibilité et pas de référencement dans Google Images.', 'eo-tools' ),
			),
			'orphan'                => array(
				'severity'    => 'warning',
				'label'       => __( 'Contenu orphelin', 'eo-tools' ),
				'description' => __( 'Aucun autre contenu, menu ou modèle du site ne fait de lien vers ce contenu (les listes d\'articles et les archives ne comptent pas) : les moteurs lui donnent peu d\'importance.', 'eo-tools' ),
			),
			'no_internal_links'     => array(
				'severity'    => 'info',
				'label'       => __( 'Aucun lien interne sortant', 'eo-tools' ),
				'description' => __( 'Le contenu ne fait aucun lien vers d\'autres pages du site.', 'eo-tools' ),
			),
			'noindex'               => array(
				'severity'    => 'info',
				'label'       => __( 'Exclu des moteurs (noindex)', 'eo-tools' ),
				'description' => __( 'Contenu publié mais exclu des résultats de recherche. Normal pour certaines pages (mentions légales, remerciements…), à vérifier pour les autres.', 'eo-tools' ),
				'yoast'       => true,
			),
			'canonical_elsewhere'   => array(
				'severity'    => 'info',
				'label'       => __( 'URL canonique vers une autre page', 'eo-tools' ),
				'description' => __( 'Le contenu indique aux moteurs qu\'une autre adresse est la version de référence : il ne sera pas indexé lui-même.', 'eo-tools' ),
				'yoast'       => true,
			),
		);

		if ( ! self::has_yoast() ) {
			$types = array_filter(
				$types,
				function ( $type ) {
					return empty( $type['yoast'] );
				}
			);
		}

		return $types;
	}

	/**
	 * Site-wide audit.
	 *
	 * @param string[] $sections Sections to include.
	 * @param int      $examples Number of example contents per content issue.
	 * @param bool     $refresh  Rescan the contents instead of using the cache.
	 * @return array
	 */
	public static function audit_site( $sections, $examples, $refresh ) {
		$sections = array_values( array_intersect( self::SECTIONS, (array) $sections ) );
		if ( ! $sections ) {
			$sections = self::SECTIONS;
		}

		$result = array(
			'site'        => home_url( '/' ),
			'seo_plugin'  => self::has_yoast() ? 'Yoast SEO ' . WPSEO_VERSION : null,
			'generated'   => gmdate( 'c' ),
			'summary'     => array_fill_keys( self::SEVERITIES, 0 ),
		);

		foreach ( $sections as $section ) {
			switch ( $section ) {
				case 'settings':
					$data = self::audit_settings();
					break;
				case 'indexing':
					$data = self::audit_indexing();
					break;
				case 'content':
					$data = self::audit_content( $examples, $refresh );
					break;
				default:
					$data = self::audit_errors();
			}

			foreach ( $data['findings'] as $finding ) {
				$result['summary'][ $finding['severity'] ] += 1;
			}
			$result[ $section ] = $data;
		}

		return $result;
	}

	/**
	 * @param string $severity One of SEVERITIES.
	 * @param string $message  Finding.
	 * @return array
	 */
	private static function finding( $severity, $message ) {
		return array(
			'severity' => $severity,
			'message'  => $message,
		);
	}

	/**
	 * WordPress and SEO plugin settings.
	 *
	 * @return array
	 */
	public static function audit_settings() {
		$findings = array();

		$public = '1' === (string) get_option( 'blog_public' );
		if ( ! $public ) {
			$findings[] = self::finding( 'critical', __( 'Réglages > Lecture : « Demander aux moteurs de recherche de ne pas indexer ce site » est coché. Le site n\'apparaît pas dans Google.', 'eo-tools' ) );
		}

		$permalinks = (string) get_option( 'permalink_structure' );
		if ( '' === $permalinks ) {
			$findings[] = self::finding( 'warning', __( 'Les permaliens sont « simples » (?p=123) : choisir une structure lisible, par exemple « Titre de la publication » (Réglages > Permaliens).', 'eo-tools' ) );
		}

		$name        = get_bloginfo( 'name' );
		$description = get_bloginfo( 'description' );
		if ( '' === trim( $name ) ) {
			$findings[] = self::finding( 'warning', __( 'Le titre du site est vide (Réglages > Général).', 'eo-tools' ) );
		}
		if ( in_array( trim( $description ), array( 'Just another WordPress site', 'Un site utilisant WordPress', 'Un blog utilisant WordPress', 'Juste un autre site WordPress' ), true ) ) {
			$findings[] = self::finding( 'warning', __( 'Le slogan du site est celui installé par défaut par WordPress.', 'eo-tools' ) );
		}

		$https = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
		if ( ! $https ) {
			$findings[] = self::finding( 'critical', __( 'Le site n\'est pas en HTTPS : Google le signale comme « non sécurisé ».', 'eo-tools' ) );
		}

		if ( ! has_site_icon() ) {
			$findings[] = self::finding( 'info', __( 'Pas d\'icône du site (favicon), affichée par Google à côté des résultats.', 'eo-tools' ) );
		}

		$front = array( 'shows' => get_option( 'show_on_front' ) );
		if ( 'page' === $front['shows'] ) {
			$front['page_on_front']  = (int) get_option( 'page_on_front' );
			$front['page_for_posts'] = (int) get_option( 'page_for_posts' );
		}

		$plugins  = self::get_seo_plugins();
		$settings = array(
			'search_engines_allowed' => $public,
			'permalink_structure'    => $permalinks,
			'site_title'             => self::clean( $name ),
			'tagline'                => self::clean( $description ),
			'language'               => get_locale(),
			'https'                  => $https,
			'site_icon'              => has_site_icon(),
			'front_page'             => $front,
			'seo_plugins_active'     => $plugins,
		);

		if ( ! $plugins ) {
			$findings[] = self::finding( 'warning', __( 'Aucune extension SEO active : impossible de définir des méta descriptions, et les titres SEO sont ceux de WordPress. Yoast SEO est recommandé (eo-tools/search-plugins, eo-tools/install-plugin).', 'eo-tools' ) );
		} elseif ( count( $plugins ) > 1 ) {
			/* translators: %s: plugin names. */
			$findings[] = self::finding( 'critical', sprintf( __( 'Plusieurs extensions SEO sont actives (%s) : balises en double, à garder une seule.', 'eo-tools' ), implode( ', ', $plugins ) ) );
		}

		if ( self::has_yoast() ) {
			$yoast = self::get_yoast_settings();

			if ( ! $yoast['xml_sitemap'] ) {
				$findings[] = self::finding( 'warning', __( 'Le plan de site XML de Yoast est désactivé (Yoast SEO > Réglages > Fonctionnalités du site).', 'eo-tools' ) );
			}
			if ( '' === $yoast['organization']['name'] ) {
				$findings[] = self::finding( 'info', __( 'Yoast : le nom de l\'organisation ou de la personne derrière le site n\'est pas renseigné (données structurées, Knowledge Graph).', 'eo-tools' ) );
			}
			if ( 'company' === $yoast['organization']['type'] && ! $yoast['organization']['logo'] ) {
				$findings[] = self::finding( 'info', __( 'Yoast : pas de logo d\'organisation (données structurées).', 'eo-tools' ) );
			}
			foreach ( $yoast['post_types'] as $type => $config ) {
				if ( $config['noindex'] ) {
					/* translators: %s: content type. */
					$findings[] = self::finding( 'info', sprintf( __( 'Yoast : le type de contenu « %s » est exclu des moteurs (noindex) pour tout le site.', 'eo-tools' ), $type ) );
				}
			}
			$settings['yoast'] = $yoast;
		}

		return array(
			'data'     => $settings,
			'findings' => $findings,
		);
	}

	/**
	 * Active SEO plugins.
	 *
	 * @return string[]
	 */
	private static function get_seo_plugins() {
		$known = array(
			'Yoast SEO'           => defined( 'WPSEO_VERSION' ),
			'Rank Math'           => defined( 'RANK_MATH_VERSION' ),
			'All in One SEO'      => defined( 'AIOSEO_VERSION' ),
			'SEOPress'            => defined( 'SEOPRESS_VERSION' ),
			'The SEO Framework'   => defined( 'THE_SEO_FRAMEWORK_VERSION' ),
			'Slim SEO'            => defined( 'SLIM_SEO_VER' ),
		);

		return array_keys( array_filter( $known ) );
	}

	/**
	 * Yoast settings that matter for an audit.
	 *
	 * @return array
	 */
	private static function get_yoast_settings() {
		$post_types = array();
		foreach ( self::get_post_types() as $type ) {
			$post_types[ $type ] = array(
				'title_template'       => (string) self::yoast_option( 'title-' . $type ),
				'description_template' => (string) self::yoast_option( 'metadesc-' . $type ),
				'noindex'              => (bool) self::yoast_option( 'noindex-' . $type, false ),
			);
		}

		$type = (string) self::yoast_option( 'company_or_person', 'company' );

		return array(
			'title_separator'  => (string) self::yoast_option( 'separator' ),
			'website_name'     => (string) self::yoast_option( 'website_name' ),
			'xml_sitemap'      => (bool) self::yoast_option( 'enable_xml_sitemap', true ),
			'keyword_analysis' => (bool) self::yoast_option( 'keyword_analysis_active', true ),
			'organization'     => array(
				'type' => $type,
				'name' => 'company' === $type ? (string) self::yoast_option( 'company_name' ) : (string) get_the_author_meta( 'display_name', (int) self::yoast_option( 'company_or_person_user_id', 0 ) ),
				'logo' => '' !== (string) self::yoast_option( 'company_logo' ),
			),
			'verification'     => array(
				'google' => '' !== (string) self::yoast_option( 'googleverify' ),
				'bing'   => '' !== (string) self::yoast_option( 'msverify' ),
			),
			'post_types'       => $post_types,
		);
	}

	/**
	 * Fetches a page of the site.
	 *
	 * @param string $url  Address.
	 * @param array  $args Request arguments.
	 * @return array|\WP_Error
	 */
	private static function fetch( $url, $args = array() ) {
		return wp_remote_get(
			$url,
			array_merge(
				array(
					'timeout'   => 20,
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				),
				$args
			)
		);
	}

	/**
	 * robots.txt, XML sitemap, HTTPS redirection and the home page as search engines see them.
	 *
	 * @return array
	 */
	public static function audit_indexing() {
		$findings = array();
		$data     = array();

		// robots.txt.
		// Search engines only read robots.txt at the root of the domain.
		$robots_url = preg_replace( '#^(https?://[^/]+).*$#i', '$1', home_url() ) . '/robots.txt';
		$response   = self::fetch( $robots_url );
		$robots     = array( 'url' => $robots_url );
		if ( is_wp_error( $response ) ) {
			$robots['error'] = $response->get_error_message();
			$findings[]      = self::finding( 'warning', __( 'Impossible de lire le fichier robots.txt.', 'eo-tools' ) );
		} else {
			$body               = (string) wp_remote_retrieve_body( $response );
			$robots['status']   = (int) wp_remote_retrieve_response_code( $response );
			$is_text            = false !== stripos( (string) wp_remote_retrieve_header( $response, 'content-type' ), 'text/plain' );
			$body               = $is_text ? $body : '';
			$robots['content']  = mb_substr( $body, 0, 3000 );
			$robots['sitemaps'] = preg_match_all( '/^\s*sitemap:\s*(\S+)/mi', $body, $matches ) ? $matches[1] : array();

			if ( 200 !== $robots['status'] ) {
				/* translators: %d: HTTP status code. */
				$findings[] = self::finding( 'warning', sprintf( __( 'Le fichier robots.txt répond avec le code HTTP %d.', 'eo-tools' ), $robots['status'] ) );
			} elseif ( self::robots_blocks_all( $body ) ) {
				$findings[] = self::finding( 'critical', __( 'robots.txt interdit tout le site aux moteurs (« Disallow: / » pour tous les robots).', 'eo-tools' ) );
			}
			if ( 200 === $robots['status'] && ! $robots['sitemaps'] ) {
				$findings[] = self::finding( 'info', __( 'robots.txt n\'indique pas l\'adresse du plan de site (ligne « Sitemap: »).', 'eo-tools' ) );
			}
		}
		$data['robots_txt'] = $robots;

		// XML sitemap.
		$sitemap = self::get_sitemap();
		if ( ! $sitemap['url'] ) {
			$findings[] = self::finding( 'warning', __( 'Aucun plan de site XML n\'est actif.', 'eo-tools' ) );
		} else {
			$response = self::fetch( $sitemap['url'] );
			if ( is_wp_error( $response ) ) {
				$sitemap['error'] = $response->get_error_message();
				$findings[]       = self::finding( 'warning', __( 'Impossible de lire le plan de site XML.', 'eo-tools' ) );
			} else {
				$body                 = (string) wp_remote_retrieve_body( $response );
				$sitemap['status']    = (int) wp_remote_retrieve_response_code( $response );
				$sitemap['sitemaps']  = preg_match_all( '#<sitemap>\s*<loc>([^<]+)</loc>#i', $body, $matches ) ? array_slice( array_map( 'html_entity_decode', $matches[1] ), 0, 50 ) : array();
				if ( ! $sitemap['sitemaps'] ) {
					$sitemap['url_count'] = preg_match_all( '#<url>#i', $body );
				}
				if ( 200 !== $sitemap['status'] ) {
					/* translators: %d: HTTP status code. */
					$findings[] = self::finding( 'critical', sprintf( __( 'Le plan de site XML répond avec le code HTTP %d.', 'eo-tools' ), $sitemap['status'] ) );
				}
			}
		}
		$data['sitemap'] = $sitemap;

		// HTTP to HTTPS redirection.
		if ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
			$http_url = set_url_scheme( home_url( '/' ), 'http' );
			$response = self::fetch(
				$http_url,
				array(
					'redirection' => 0,
					'timeout'     => 10,
				)
			);
			$redirect = array( 'url' => $http_url );
			if ( is_wp_error( $response ) ) {
				$redirect['error'] = $response->get_error_message();
			} else {
				$redirect['status']   = (int) wp_remote_retrieve_response_code( $response );
				$redirect['location'] = (string) wp_remote_retrieve_header( $response, 'location' );
				if ( ! in_array( $redirect['status'], array( 301, 308 ), true ) || 0 !== strpos( $redirect['location'], 'https://' ) ) {
					$findings[] = self::finding( 'warning', __( 'La version http:// du site n\'est pas redirigée de façon permanente (301) vers https:// : risque de contenu en double.', 'eo-tools' ) );
				}
			}
			$data['http_redirect'] = $redirect;
		}

		// Home page.
		$response = self::fetch( home_url( '/' ) );
		if ( is_wp_error( $response ) ) {
			$data['home_page'] = array( 'error' => $response->get_error_message() );
			$findings[]        = self::finding( 'critical', __( 'Impossible de charger la page d\'accueil.', 'eo-tools' ) );
		} else {
			$home              = self::analyze_html( (string) wp_remote_retrieve_body( $response ) );
			$home['status']    = (int) wp_remote_retrieve_response_code( $response );
			$data['home_page'] = $home;
			$findings          = array_merge( $findings, self::html_findings( $home, __( 'Page d\'accueil', 'eo-tools' ) ) );
		}

		return array(
			'data'     => $data,
			'findings' => $findings,
		);
	}

	/**
	 * Whether robots.txt disallows the whole site for every robot.
	 *
	 * @param string $robots robots.txt content.
	 * @return bool
	 */
	private static function robots_blocks_all( $robots ) {
		// A group = consecutive "User-agent" lines followed by its rules.
		$agents   = array();
		$in_rules = false;
		foreach ( preg_split( '/\R/', $robots ) as $line ) {
			$line = trim( preg_replace( '/#.*$/', '', $line ) );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^user-agent:\s*(.+)$/i', $line, $match ) ) {
				if ( $in_rules ) {
					$agents   = array();
					$in_rules = false;
				}
				$agents[] = trim( $match[1] );
				continue;
			}
			$in_rules = true;
			if ( in_array( '*', $agents, true ) && preg_match( '#^disallow:\s*/$#i', $line ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Active XML sitemap.
	 *
	 * @return array
	 */
	private static function get_sitemap() {
		if ( self::has_yoast() && self::yoast_option( 'enable_xml_sitemap', true ) ) {
			return array(
				'provider' => 'Yoast SEO',
				'url'      => home_url( '/sitemap_index.xml' ),
			);
		}

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
	 * SEO tags of a rendered page.
	 *
	 * @param string $html Page HTML.
	 * @return array
	 */
	public static function analyze_html( $html ) {
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );

		$first = function ( $query, $attribute = null ) use ( $xpath ) {
			$node = $xpath->query( $query )->item( 0 );
			if ( ! $node ) {
				return null;
			}

			return trim( preg_replace( '/\s+/u', ' ', $attribute ? $node->getAttribute( $attribute ) : $node->textContent ) );
		};

		$schema = array();
		foreach ( $xpath->query( '//script[@type="application/ld+json"]' ) as $script ) {
			$json = json_decode( $script->textContent, true );
			if ( is_array( $json ) ) {
				$nodes = isset( $json['@graph'] ) && is_array( $json['@graph'] ) ? $json['@graph'] : array( $json );
				foreach ( $nodes as $node ) {
					if ( isset( $node['@type'] ) ) {
						$schema = array_merge( $schema, (array) $node['@type'] );
					}
				}
			}
		}

		$h1 = array();
		foreach ( $xpath->query( '//body//h1' ) as $heading ) {
			$h1[] = mb_substr( trim( preg_replace( '/\s+/u', ' ', $heading->textContent ) ), 0, 150 );
		}

		return array(
			'lang'           => $first( '//html', 'lang' ),
			'title'          => $first( '//head/title' ),
			'description'    => $first( '//head/meta[@name="description"]', 'content' ),
			'robots'         => $first( '//head/meta[@name="robots"]', 'content' ),
			'canonical'      => $first( '//head/link[@rel="canonical"]', 'href' ),
			'og_title'       => $first( '//head/meta[@property="og:title"]', 'content' ),
			'og_description' => $first( '//head/meta[@property="og:description"]', 'content' ),
			'og_image'       => $first( '//head/meta[@property="og:image"]', 'content' ),
			'viewport'       => null !== $first( '//head/meta[@name="viewport"]', 'content' ),
			'h1'             => $h1,
			'schema_types'   => array_values( array_unique( $schema ) ),
		);
	}

	/**
	 * Findings on the SEO tags of a rendered page.
	 *
	 * @param array  $page  Result of analyze_html() plus "status".
	 * @param string $label Page name used in the messages.
	 * @return array[]
	 */
	public static function html_findings( $page, $label ) {
		$findings = array();

		if ( isset( $page['status'] ) && 200 !== $page['status'] ) {
			/* translators: 1: page name, 2: HTTP status code. */
			$findings[] = self::finding( 'critical', sprintf( __( '%1$s : la page répond avec le code HTTP %2$d.', 'eo-tools' ), $label, $page['status'] ) );
		}
		if ( empty( $page['title'] ) ) {
			/* translators: %s: page name. */
			$findings[] = self::finding( 'critical', sprintf( __( '%s : pas de balise <title>.', 'eo-tools' ), $label ) );
		}
		if ( empty( $page['description'] ) ) {
			/* translators: %s: page name. */
			$findings[] = self::finding( 'warning', sprintf( __( '%s : pas de méta description.', 'eo-tools' ), $label ) );
		}
		if ( ! empty( $page['robots'] ) && false !== stripos( $page['robots'], 'noindex' ) ) {
			/* translators: %s: page name. */
			$findings[] = self::finding( 'critical', sprintf( __( '%s : la page est exclue des moteurs (meta robots noindex).', 'eo-tools' ), $label ) );
		}
		if ( empty( $page['canonical'] ) ) {
			/* translators: %s: page name. */
			$findings[] = self::finding( 'info', sprintf( __( '%s : pas d\'URL canonique.', 'eo-tools' ), $label ) );
		}
		if ( empty( $page['h1'] ) ) {
			/* translators: %s: page name. */
			$findings[] = self::finding( 'warning', sprintf( __( '%s : aucun titre H1.', 'eo-tools' ), $label ) );
		} elseif ( count( $page['h1'] ) > 1 ) {
			/* translators: 1: page name, 2: number of H1. */
			$findings[] = self::finding( 'info', sprintf( __( '%1$s : %2$d titres H1 (un seul recommandé).', 'eo-tools' ), $label, count( $page['h1'] ) ) );
		}
		if ( empty( $page['og_image'] ) ) {
			/* translators: %s: page name. */
			$findings[] = self::finding( 'info', sprintf( __( '%s : pas d\'image de partage (og:image) pour les réseaux sociaux.', 'eo-tools' ), $label ) );
		}
		if ( empty( $page['lang'] ) ) {
			/* translators: %s: page name. */
			$findings[] = self::finding( 'info', sprintf( __( '%s : la langue de la page n\'est pas déclarée (attribut lang).', 'eo-tools' ), $label ) );
		}
		if ( empty( $page['viewport'] ) ) {
			/* translators: %s: page name. */
			$findings[] = self::finding( 'warning', sprintf( __( '%s : pas de balise viewport, la page n\'est pas adaptée aux mobiles.', 'eo-tools' ), $label ) );
		}

		return $findings;
	}

	/**
	 * Overview of the published contents: counts, and a few examples per issue.
	 *
	 * @param int  $examples Examples per issue.
	 * @param bool $refresh  Rescan.
	 * @return array
	 */
	public static function audit_content( $examples, $refresh ) {
		$scan     = self::scan( $refresh );
		$types    = self::get_issue_types();
		$findings = array();
		$issues   = array();

		foreach ( $types as $type => $config ) {
			$ids = isset( $scan['issues'][ $type ] ) ? $scan['issues'][ $type ] : array();
			if ( ! $ids ) {
				continue;
			}
			$issues[ $type ] = array(
				'label'    => $config['label'],
				'severity' => $config['severity'],
				'count'    => count( $ids ),
				'examples' => array_map(
					function ( $id ) use ( $scan ) {
						return self::public_item( $scan['posts'][ $id ] );
					},
					array_slice( $ids, 0, $examples )
				),
			);
			/* translators: 1: number of contents, 2: issue label. */
			$findings[] = self::finding( $config['severity'], sprintf( _n( '%1$d contenu : %2$s.', '%1$d contenus : %2$s.', count( $ids ), 'eo-tools' ), count( $ids ), $config['label'] ) );
		}

		if ( ! self::has_yoast() ) {
			$findings[] = self::finding( 'warning', __( 'Sans extension SEO, aucun contenu n\'a de méta description.', 'eo-tools' ) );
		}
		if ( $scan['images_without_alt_in_library'] ) {
			/* translators: %d: number of images. */
			$findings[] = self::finding( 'info', sprintf( __( '%d image(s) de la médiathèque sans texte alternatif.', 'eo-tools' ), $scan['images_without_alt_in_library'] ) );
		}
		if ( $scan['truncated'] ) {
			/* translators: %d: number of contents. */
			$findings[] = self::finding( 'info', sprintf( __( 'Gros site : seuls les %d contenus modifiés le plus récemment ont été analysés.', 'eo-tools' ), self::MAX_POSTS ) );
		}

		return array(
			'data'     => array(
				'scanned_at'                    => $scan['scanned_at'],
				'published'                     => $scan['counts'],
				'analyzed'                      => count( $scan['posts'] ),
				'truncated'                     => $scan['truncated'],
				'images_without_alt_in_library' => $scan['images_without_alt_in_library'],
				'issues'                        => $issues,
				'more'                          => __( 'Liste complète d\'un problème : eo-tools/list-seo-issues avec son type (clé de "issues").', 'eo-tools' ),
			),
			'findings' => $findings,
		);
	}

	/**
	 * Contents affected by one issue, paginated.
	 *
	 * @param string $issue     Issue type.
	 * @param string $post_type Content type, or empty for all.
	 * @param int    $page      Page.
	 * @param int    $per_page  Items per page.
	 * @param bool   $refresh   Rescan.
	 * @return array|\WP_Error
	 */
	public static function list_issue( $issue, $post_type, $page, $per_page, $refresh ) {
		$types = self::get_issue_types();
		if ( ! isset( $types[ $issue ] ) ) {
			return new \WP_Error( 'eo_tools_seo_issue', __( 'Type de problème inconnu, ou indisponible sans Yoast SEO.', 'eo-tools' ) );
		}

		$scan = self::scan( $refresh );
		$ids  = isset( $scan['issues'][ $issue ] ) ? $scan['issues'][ $issue ] : array();
		if ( $post_type ) {
			$ids = array_values(
				array_filter(
					$ids,
					function ( $id ) use ( $scan, $post_type ) {
						return $scan['posts'][ $id ]['type'] === $post_type;
					}
				)
			);
		}

		$total = count( $ids );
		$items = array();
		foreach ( array_slice( $ids, ( $page - 1 ) * $per_page, $per_page ) as $id ) {
			$item = self::public_item( $scan['posts'][ $id ] );
			if ( in_array( $issue, array( 'duplicate_title', 'duplicate_description' ), true ) ) {
				$item['same_as'] = $scan['posts'][ $id ][ $issue ];
			}
			$items[] = $item;
		}

		return array(
			'issue'       => $issue,
			'label'       => $types[ $issue ]['label'],
			'severity'    => $types[ $issue ]['severity'],
			'description' => $types[ $issue ]['description'],
			'scanned_at'  => $scan['scanned_at'],
			'items'       => $items,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Content as returned to the AI (internal fields removed).
	 *
	 * @param array $post Scanned content.
	 * @return array
	 */
	private static function public_item( $post ) {
		unset( $post['duplicate_title'], $post['duplicate_description'] );

		return $post;
	}

	/**
	 * Scans the published contents, cached for a few minutes.
	 *
	 * @param bool $refresh Ignore the cache.
	 * @return array
	 */
	public static function scan( $refresh = false ) {
		if ( ! $refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$types  = self::get_post_types();
		$counts = array();
		foreach ( $types as $type ) {
			$counts[ $type ] = (int) wp_count_posts( $type )->publish;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'has_password'           => false,
				'posts_per_page'         => self::MAX_POSTS,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);

		$context = self::get_context();

		$posts    = array();
		$paths    = array();
		$contents = array();
		foreach ( $query->posts as $post ) {
			$item = self::build_item( $post, $context );

			$posts[ $post->ID ]                            = $item;
			$paths[ self::normalize_path( $item['link'] ) ] = $post->ID;
			$contents[ $post->ID ]                         = $post->post_content;
		}

		// Internal links between contents, menus and templates.
		$inbound  = array();
		$outbound = array();
		foreach ( $contents as $id => $content ) {
			$targets         = self::link_targets( $content, $paths, $posts );
			unset( $targets[ $id ] );
			$outbound[ $id ] = count( $targets );
			foreach ( array_keys( $targets ) as $target ) {
				$inbound[ $target ] = true;
			}
		}
		foreach ( self::navigation_targets( $paths, $posts ) as $target => $unused ) {
			$inbound[ $target ] = true;
		}

		// Issues.
		$issues       = array_fill_keys( array_keys( self::get_issue_types() ), array() );
		$titles       = array();
		$descriptions = array();
		foreach ( $posts as $id => &$item ) {
			$item['internal_links_out'] = $outbound[ $id ];
			$item['linked_from_site']   = isset( $inbound[ $id ] ) || $id === $context['front_page'];

			foreach ( self::content_issues( $item, $context ) as $issue ) {
				$issues[ $issue ][] = $id;
			}

			if ( self::is_indexable( $item ) ) {
				$titles[ mb_strtolower( $item['seo_title'] ) ][] = $id;
				if ( ! empty( $item['meta_description'] ) ) {
					$descriptions[ mb_strtolower( $item['meta_description'] ) ][] = $id;
				}
			}
		}
		unset( $item );

		foreach ( array(
			'duplicate_title'       => $titles,
			'duplicate_description' => $descriptions,
		) as $issue => $groups ) {
			if ( ! isset( $issues[ $issue ] ) ) {
				continue;
			}
			foreach ( $groups as $ids ) {
				if ( count( $ids ) < 2 ) {
					continue;
				}
				foreach ( $ids as $id ) {
					$issues[ $issue ][]       = $id;
					$posts[ $id ][ $issue ] = array_values( array_diff( $ids, array( $id ) ) );
				}
			}
		}

		$scan = array(
			'scanned_at'                    => gmdate( 'c' ),
			'counts'                        => $counts,
			'truncated'                     => $query->found_posts > self::MAX_POSTS,
			'images_without_alt_in_library' => self::count_library_images_without_alt(),
			'posts'                         => $posts,
			'issues'                        => array_filter( $issues ),
		);

		set_transient( self::CACHE_KEY, $scan, self::CACHE_TTL );

		return $scan;
	}

	/**
	 * Site settings the content analysis depends on.
	 *
	 * @return array Keys: yoast, keywords, front_page, posts_page.
	 */
	private static function get_context() {
		$yoast = self::has_yoast();
		$pages = 'page' === get_option( 'show_on_front' );

		return array(
			'yoast'      => $yoast,
			'keywords'   => $yoast && self::yoast_option( 'keyword_analysis_active', true ),
			'front_page' => $pages ? (int) get_option( 'page_on_front' ) : 0,
			'posts_page' => $pages ? (int) get_option( 'page_for_posts' ) : 0,
		);
	}

	/**
	 * SEO data of one content (without the links between contents).
	 *
	 * @param \WP_Post $post    Content.
	 * @param array    $context Result of get_context().
	 * @return array
	 */
	private static function build_item( $post, $context ) {
		$item = array(
			'id'    => $post->ID,
			'type'  => $post->post_type,
			'title' => $post->post_title,
			'link'  => get_permalink( $post ),
		);

		$item['seo_title'] = self::seo_title( $post, $post->ID === $context['front_page'] );
		if ( $context['yoast'] ) {
			$item['meta_description'] = self::meta_description( $post );
			$item['focus_keyword']    = (string) get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );
			$score                    = get_post_meta( $post->ID, '_yoast_wpseo_linkdex', true );
			$item['seo_score']        = '' === $score ? null : (int) $score;
			$item['noindex']          = self::is_noindex( $post );
			$item['canonical']        = (string) get_post_meta( $post->ID, '_yoast_wpseo_canonical', true );
		} else {
			$item['noindex'] = false;
		}
		$item['words']              = self::count_words( self::plain_text( $post->post_content ) );
		$item['h1_in_content']      = preg_match_all( '/<h1[\s>]/i', $post->post_content );
		$item['images_without_alt'] = self::count_images_without_alt( $post->post_content );

		return $item;
	}

	/**
	 * @param array $item Result of build_item().
	 * @return bool Whether search engines may index the content.
	 */
	private static function is_indexable( $item ) {
		return ! $item['noindex'] && ( empty( $item['canonical'] ) || self::normalize_path( $item['canonical'] ) === self::normalize_path( $item['link'] ) );
	}

	/**
	 * Issues of one content, except the duplicates (they need the whole site).
	 *
	 * @param array $item    Result of build_item() plus "internal_links_out" and "linked_from_site".
	 * @param array $context Result of get_context().
	 * @return string[] Issue types.
	 */
	private static function content_issues( $item, $context ) {
		$issues = array();
		$id     = $item['id'];

		if ( $item['noindex'] ) {
			$issues[] = 'noindex';
		}
		if ( ! self::is_indexable( $item ) ) {
			if ( ! $item['noindex'] ) {
				$issues[] = 'canonical_elsewhere';
			}

			return $issues;
		}

		$length = mb_strlen( $item['seo_title'] );
		if ( $length < self::TITLE_MIN || $length > self::TITLE_MAX ) {
			$issues[] = 'title_length';
		}

		if ( $context['yoast'] ) {
			$length = mb_strlen( $item['meta_description'] );
			if ( 0 === $length ) {
				$issues[] = 'missing_description';
			} elseif ( $length < self::DESC_MIN || $length > self::DESC_MAX ) {
				$issues[] = 'description_length';
			}
			if ( $context['keywords'] && '' === $item['focus_keyword'] && $id !== $context['posts_page'] ) {
				$issues[] = 'missing_focus_keyword';
			}
			if ( null !== $item['seo_score'] && '' !== $item['focus_keyword'] && $item['seo_score'] <= 40 ) {
				$issues[] = 'low_seo_score';
			}
		}

		// The home page is often built in its template: it is audited in "indexing".
		$listing = in_array( $id, array( $context['front_page'], $context['posts_page'] ), true );
		if ( $item['words'] < self::THIN_WORDS && ! $listing ) {
			$issues[] = 'thin_content';
		}
		if ( $item['h1_in_content'] ) {
			$issues[] = 'h1_in_content';
		}
		if ( $item['images_without_alt'] ) {
			$issues[] = 'images_without_alt';
		}
		if ( ! $item['linked_from_site'] ) {
			$issues[] = 'orphan';
		}
		if ( ! $item['internal_links_out'] && ! $listing ) {
			$issues[] = 'no_internal_links';
		}

		return $issues;
	}

	/**
	 * @param string $content Content HTML (or block markup).
	 * @return string Visible text.
	 */
	private static function plain_text( $content ) {
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( strip_shortcodes( $content ) ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * @param string $text Plain text.
	 * @return int
	 */
	private static function count_words( $text ) {
		return '' === $text ? 0 : count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) );
	}

	/* ---------------------------------------------------------------------
	 * Audit of one content
	 * ------------------------------------------------------------------ */

	/**
	 * Finds the content of an address of the site.
	 *
	 * @param string $url Address.
	 * @return \WP_Post|null
	 */
	public static function find_post_by_url( $url ) {
		if ( self::normalize_path( $url ) === self::normalize_path( home_url( '/' ) ) ) {
			$front = self::get_context()['front_page'];

			return $front ? get_post( $front ) : null;
		}

		$id = url_to_postid( $url );

		return $id ? get_post( $id ) : null;
	}

	/**
	 * Detailed SEO audit of one content (published, draft or pending).
	 *
	 * @param \WP_Post $post    Content.
	 * @param string   $keyword Target query to check, instead of the Yoast one.
	 * @param bool     $refresh Rescan the site (used for duplicates) instead of using the cache.
	 * @return array
	 */
	public static function audit_post( $post, $keyword = '', $refresh = false ) {
		$context   = self::get_context();
		$types     = self::get_issue_types();
		$published = 'publish' === $post->post_status;
		$findings  = array();

		$item = self::build_item( $post, $context );
		$html = self::render_content( $post );

		// Links.
		$inbound  = self::inbound_links( $post, $item['link'] );
		$outbound = self::outbound_links( $html, $post->ID );

		$item['internal_links_out'] = count( $outbound['internal'] );
		$item['linked_from_site']   = $inbound['in_navigation'] || $inbound['contents'] || $post->ID === $context['front_page'];

		// Same issues as the site audit.
		foreach ( self::content_issues( $item, $context ) as $issue ) {
			if ( isset( $types[ $issue ] ) ) {
				$findings[] = self::finding( $types[ $issue ]['severity'], $types[ $issue ]['label'] . ' : ' . $types[ $issue ]['description'] );
			}
		}

		// Duplicates among the other published contents.
		$duplicates = array();
		if ( self::is_indexable( $item ) ) {
			$scan = self::scan( $refresh );
			foreach ( array( 'seo_title' => 'duplicate_title', 'meta_description' => 'duplicate_description' ) as $field => $issue ) {
				if ( empty( $item[ $field ] ) || ! isset( $types[ $issue ] ) ) {
					continue;
				}
				$same = array();
				foreach ( $scan['posts'] as $other ) {
					if ( $other['id'] !== $post->ID && isset( $other[ $field ] ) && mb_strtolower( $other[ $field ] ) === mb_strtolower( $item[ $field ] ) && self::is_indexable( $other ) ) {
						$same[] = array(
							'id'    => $other['id'],
							'title' => $other['title'],
							'link'  => $other['link'],
						);
					}
				}
				if ( $same ) {
					$duplicates[ $field ] = array_slice( $same, 0, 10 );
					$findings[]           = self::finding( $types[ $issue ]['severity'], $types[ $issue ]['label'] . ' : ' . $types[ $issue ]['description'] );
				}
			}
		}

		// Headings.
		$outline = self::outline( $html );
		$level   = 1;
		foreach ( $outline as $heading ) {
			if ( $heading['level'] > $level + 1 ) {
				/* translators: 1: heading level, 2: heading text, 3: expected level. */
				$findings[] = self::finding( 'info', sprintf( __( 'Niveau de titre sauté : H%1$d « %2$s » après un H%3$d.', 'eo-tools' ), $heading['level'], $heading['text'], $level ) );
				break;
			}
			$level = $heading['level'];
		}
		if ( $item['words'] >= 600 && ! in_array( 2, wp_list_pluck( $outline, 'level' ), true ) ) {
			$findings[] = self::finding( 'info', __( 'Contenu long sans sous-titre H2 : il est plus difficile à parcourir.', 'eo-tools' ) );
		}

		// Links.
		if ( $outbound['to_unpublished'] ) {
			/* translators: %d: number of links. */
			$findings[] = self::finding( 'warning', sprintf( _n( '%d lien interne vers un contenu non publié : il mènera à une erreur 404 pour les visiteurs.', '%d liens internes vers des contenus non publiés : ils mèneront à une erreur 404 pour les visiteurs.', count( $outbound['to_unpublished'] ), 'eo-tools' ), count( $outbound['to_unpublished'] ) ) );
		}
		if ( $outbound['unknown'] ) {
			/* translators: %d: number of links. */
			$findings[] = self::finding( 'info', sprintf( _n( '%d lien interne vers une adresse qui ne correspond à aucun contenu (archive, fichier ou lien cassé, à vérifier).', '%d liens internes vers des adresses qui ne correspondent à aucun contenu (archives, fichiers ou liens cassés, à vérifier).', count( $outbound['unknown'] ), 'eo-tools' ), count( $outbound['unknown'] ) ) );
		}

		// Featured image.
		$thumbnail = get_post_thumbnail_id( $post );
		$featured  = null;
		if ( $thumbnail ) {
			$featured = array(
				'id'  => $thumbnail,
				'url' => wp_get_attachment_image_url( $thumbnail, 'full' ),
				'alt' => (string) get_post_meta( $thumbnail, '_wp_attachment_image_alt', true ),
			);
		} elseif ( post_type_supports( $post->post_type, 'thumbnail' ) ) {
			$findings[] = self::finding( 'info', __( 'Pas d\'image mise en avant : elle sert souvent d\'image de partage sur les réseaux sociaux et dans les listes d\'articles.', 'eo-tools' ) );
		}

		// Target query.
		$keyword = '' !== trim( $keyword ) ? trim( $keyword ) : ( isset( $item['focus_keyword'] ) ? $item['focus_keyword'] : '' );
		$keyword_report = '' === $keyword ? null : self::keyword_report( $keyword, $post, $item, $html, $outline );
		if ( $keyword_report ) {
			foreach ( $keyword_report['missing_in'] as $place ) {
				/* translators: 1: target query, 2: place. */
				$findings[] = self::finding( 'info', sprintf( __( 'La requête « %1$s » n\'apparaît pas dans : %2$s.', 'eo-tools' ), $keyword, $place ) );
			}
		}

		// The page as visitors and search engines see it.
		$rendered = null;
		if ( post_password_required( $post ) ) {
			$findings[] = self::finding( 'warning', __( 'Contenu protégé par mot de passe : les moteurs n\'en voient pas le texte.', 'eo-tools' ) );
		} elseif ( is_post_type_viewable( $post->post_type ) ) {
			$response = self::fetch( $published ? $item['link'] : Eotools_Mcp_Preview::create_link( $post ) );
			if ( is_wp_error( $response ) ) {
				$rendered = array( 'error' => $response->get_error_message() );
			} else {
				$rendered           = self::analyze_html( (string) wp_remote_retrieve_body( $response ) );
				$rendered['status'] = (int) wp_remote_retrieve_response_code( $response );
				$page_findings      = self::html_findings( $rendered, __( 'Page', 'eo-tools' ) );
				if ( ! $published ) {
					// The preview of a draft is always "noindex": this is not a problem of the content.
					$rendered['robots'] = null;
					$page_findings      = array_filter(
						$page_findings,
						function ( $finding ) {
							return false === strpos( $finding['message'], 'noindex' );
						}
					);
				}
				$findings = array_merge( $findings, array_values( $page_findings ) );
			}
		}

		if ( ! $published ) {
			/* translators: %s: post status. */
			$findings[] = self::finding( 'info', sprintf( __( 'Contenu non publié (%s) : les moteurs ne le verront qu\'après publication. Les liens entrants et les doublons seront à revérifier ensuite.', 'eo-tools' ), $post->post_status ) );
		}

		$summary = array_fill_keys( self::SEVERITIES, 0 );
		foreach ( $findings as $finding ) {
			$summary[ $finding['severity'] ] += 1;
		}

		$data = array(
			'id'               => $post->ID,
			'type'             => $post->post_type,
			'status'           => $post->post_status,
			'title'            => $post->post_title,
			'link'             => $item['link'],
			'slug'             => $post->post_name,
			'modified'         => get_post_modified_time( 'c', true, $post ),
			'seo_title'        => $item['seo_title'],
			'seo_title_length' => mb_strlen( $item['seo_title'] ),
		);
		if ( $context['yoast'] ) {
			$readability = get_post_meta( $post->ID, '_yoast_wpseo_content_score', true );

			$data['meta_description']        = $item['meta_description'];
			$data['meta_description_length'] = mb_strlen( $item['meta_description'] );
			$data['focus_keyword']           = $item['focus_keyword'];
			$data['seo_score']               = $item['seo_score'];
			$data['readability_score']       = '' === $readability ? null : (int) $readability;
			$data['cornerstone']             = '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_is_cornerstone', true );
			$data['noindex']                 = $item['noindex'];
			$data['canonical']               = $item['canonical'];
		}
		$data['excerpt']            = $post->post_excerpt;
		$data['words']              = $item['words'];
		$data['outline']            = $outline;
		$data['images_without_alt'] = $item['images_without_alt'];
		$data['featured_image']     = $featured;
		$data['links']              = array(
			'linked_from'     => $inbound['contents'],
			'in_navigation'   => $inbound['in_navigation'],
			'internal'        => array_slice( $outbound['internal'], 0, 50 ),
			'to_unpublished'  => $outbound['to_unpublished'],
			'unknown'         => array_slice( $outbound['unknown'], 0, 20 ),
			'external'        => array_slice( $outbound['external'], 0, 30 ),
			'external_count'  => count( $outbound['external'] ),
		);
		$data['duplicates'] = $duplicates;
		$data['keyword']    = $keyword_report;
		$data['rendered']   = $rendered;

		return array(
			'site'       => home_url( '/' ),
			'seo_plugin' => $context['yoast'] ? 'Yoast SEO ' . WPSEO_VERSION : null,
			'generated'  => gmdate( 'c' ),
			'summary'    => $summary,
			'content'    => $data,
			'findings'   => $findings,
			'next_steps' => __( 'Ce sont des constats : fais-en un bilan priorisé et des recommandations concrètes. Pour corriger : eo-tools/get-post, puis eo-tools/update-draft (brouillon) ou eo-tools/propose-update (contenu publié).', 'eo-tools' ),
		);
	}

	/**
	 * Content as displayed (blocks, patterns and shortcodes rendered).
	 *
	 * @param \WP_Post $post Content.
	 * @return string
	 */
	private static function render_content( $post ) {
		global $wp_query;

		$previous_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
		setup_postdata( $post );

		$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		$GLOBALS['post'] = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $previous_post ) {
			setup_postdata( $previous_post );
		} elseif ( $wp_query ) {
			$wp_query->reset_postdata();
		}

		return (string) $html;
	}

	/**
	 * Headings of a content, in order.
	 *
	 * @param string $html Rendered content.
	 * @return array[] Level and text.
	 */
	private static function outline( $html ) {
		$outline = array();
		if ( preg_match_all( '#<h([1-6])\b[^>]*>(.*?)</h\1>#is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( array_slice( $matches, 0, 100 ) as $match ) {
				$outline[] = array(
					'level' => (int) $match[1],
					'text'  => mb_substr( self::clean( $match[2] ), 0, 150 ),
				);
			}
		}

		return $outline;
	}

	/**
	 * Contents, menus and templates that link to a content.
	 *
	 * @param \WP_Post $post Content.
	 * @param string   $link Its address.
	 * @return array Keys "contents" (up to 20) and "in_navigation".
	 */
	private static function inbound_links( $post, $link ) {
		global $wpdb;

		$path  = self::normalize_path( $link );
		$paths = '/' === $path ? array() : array( $path => $post->ID );
		$posts = array(
			$post->ID => array( 'type' => $post->post_type ),
		);

		// Candidates found with a cheap text search, then checked link by link.
		$like   = array( '%page_id=' . $post->ID . '%', '%?p=' . $post->ID . '%', '%"id":' . $post->ID . '%' );
		if ( $paths ) {
			$like[] = '%' . $wpdb->esc_like( wp_parse_url( $link, PHP_URL_PATH ) ) . '%';
		}
		$where = implode( ' OR ', array_fill( 0, count( $like ), 'post_content LIKE %s' ) );
		$types = self::get_post_types();
		$in    = implode( ', ', array_fill( 0, count( $types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders built above.
		$candidates = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND ID <> %d AND post_type IN ( $in ) AND ( $where ) LIMIT 300", array_merge( array( $post->ID ), $types, $like ) ) );

		$contents = array();
		foreach ( $candidates as $candidate ) {
			if ( self::link_targets( $candidate->post_content, $paths, $posts ) ) {
				$contents[] = array(
					'id'    => (int) $candidate->ID,
					'type'  => $candidate->post_type,
					'title' => $candidate->post_title,
					'link'  => get_permalink( (int) $candidate->ID ),
				);
			}
		}

		return array(
			'contents'      => array_slice( $contents, 0, 20 ),
			'in_navigation' => (bool) self::navigation_targets( $paths, $posts ),
		);
	}

	/**
	 * Links of a content, sorted by target.
	 *
	 * @param string $html    Rendered content.
	 * @param int    $post_id Content ID (links to itself are ignored).
	 * @return array Keys "internal", "to_unpublished", "unknown" and "external".
	 */
	private static function outbound_links( $html, $post_id ) {
		$home   = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$result = array(
			'internal'       => array(),
			'to_unpublished' => array(),
			'unknown'        => array(),
			'external'       => array(),
		);
		$seen   = array();

		if ( ! preg_match_all( '#<a\b[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $html, $matches, PREG_SET_ORDER ) ) {
			return $result;
		}

		foreach ( $matches as $match ) {
			$href = html_entity_decode( trim( $match[1] ), ENT_QUOTES, 'UTF-8' );
			if ( '' === $href || '#' === $href[0] || preg_match( '/^(mailto|tel|javascript):/i', $href ) || isset( $seen[ $href ] ) ) {
				continue;
			}
			$seen[ $href ] = true;
			$anchor        = mb_substr( self::clean( $match[2] ), 0, 100 );
			$host          = wp_parse_url( $href, PHP_URL_HOST );

			if ( $host && strtolower( $host ) !== $home ) {
				$result['external'][] = array(
					'url'    => $href,
					'anchor' => $anchor,
				);
				continue;
			}

			if ( $host ) {
				$url = $href;
			} elseif ( '/' === $href[0] ) {
				// Absolute path: from the root of the domain, even when WordPress is in a sub-folder.
				$url = preg_replace( '#^(https?://[^/]+).*$#i', '$1', home_url() ) . $href;
			} else {
				$url = home_url( '/' . $href );
			}
			$target = self::find_post_by_url( $url );
			if ( $target && $target->ID === $post_id ) {
				continue;
			}
			if ( ! $target ) {
				$result['unknown'][] = array(
					'url'    => $href,
					'anchor' => $anchor,
				);
				continue;
			}

			$link = array(
				'url'    => $href,
				'anchor' => $anchor,
				'id'     => $target->ID,
				'title'  => $target->post_title,
				'status' => $target->post_status,
			);
			if ( 'publish' === $target->post_status ) {
				$result['internal'][] = $link;
			} else {
				$result['to_unpublished'][] = $link;
			}
		}

		return $result;
	}

	/**
	 * Where a target query appears in a content.
	 *
	 * @param string   $keyword Target query.
	 * @param \WP_Post $post    Content.
	 * @param array    $item    Result of build_item().
	 * @param string   $html    Rendered content.
	 * @param array[]  $outline Headings.
	 * @return array
	 */
	private static function keyword_report( $keyword, $post, $item, $html, $outline ) {
		$normalize = function ( $text ) {
			return mb_strtolower( remove_accents( (string) $text ) );
		};
		$needle    = $normalize( $keyword );

		$text  = self::plain_text( $html );
		$words = self::count_words( $text );
		$count = '' === $needle ? 0 : substr_count( $normalize( $text ), $needle );

		$alts = preg_match_all( '/<img\b[^>]*\balt\s*=\s*["\']([^"\']*)["\']/i', $html, $matches ) ? implode( ' ', $matches[1] ) : '';
		$subs = implode(
			' ',
			wp_list_pluck(
				array_filter(
					$outline,
					function ( $heading ) {
						return $heading['level'] > 1;
					}
				),
				'text'
			)
		);

		$places = array(
			'seo_title'      => array( __( 'le titre SEO', 'eo-tools' ), $item['seo_title'] ),
			'title'          => array( __( 'le titre du contenu', 'eo-tools' ), $post->post_title ),
			'slug'           => array( __( 'l\'adresse (slug)', 'eo-tools' ), str_replace( '-', ' ', $post->post_name ) ),
			'introduction'   => array( __( 'l\'introduction (100 premiers mots)', 'eo-tools' ), implode( ' ', array_slice( preg_split( '/\s+/u', $text ), 0, 100 ) ) ),
			'subheadings'    => array( __( 'les sous-titres', 'eo-tools' ), $subs ),
			'image_alts'     => array( __( 'les textes alternatifs des images', 'eo-tools' ), $alts ),
		);
		if ( array_key_exists( 'meta_description', $item ) ) {
			$places['meta_description'] = array( __( 'la méta description', 'eo-tools' ), $item['meta_description'] );
		}

		$found   = array();
		$missing = array();
		foreach ( $places as $key => $place ) {
			$found[ $key ] = '' !== $needle && false !== strpos( $normalize( $place[1] ), $needle );
			if ( ! $found[ $key ] && ( 'image_alts' !== $key || '' !== $alts ) ) {
				$missing[] = $place[0];
			}
		}

		return array(
			'keyword'     => $keyword,
			'occurrences' => $count,
			'density'     => $words ? round( 100 * $count * max( 1, count( preg_split( '/\s+/u', $needle ) ) ) / $words, 2 ) : 0,
			'found_in'    => $found,
			'missing_in'  => $missing,
		);
	}

	/**
	 * Title shown in search results.
	 *
	 * @param \WP_Post $post       Content.
	 * @param bool     $front_page Whether the content is the home page.
	 * @return string
	 */
	private static function seo_title( $post, $front_page = false ) {
		if ( self::has_yoast() ) {
			$template = (string) get_post_meta( $post->ID, '_yoast_wpseo_title', true );
			if ( '' === $template ) {
				$template = (string) self::yoast_option( 'title-' . $post->post_type );
			}
			if ( '' !== $template ) {
				return self::clean( wpseo_replace_vars( $template, $post ) );
			}
		}

		// Same parts as wp_get_document_title().
		$separator = apply_filters( 'document_title_separator', '-' );
		$site_name = get_bloginfo( 'name', 'display' );
		$tagline   = get_bloginfo( 'description', 'display' );
		$parts     = $front_page ? array( $site_name, $tagline ) : array( $post->post_title, $site_name );

		return self::clean( implode( ' ' . $separator . ' ', array_filter( $parts, 'strlen' ) ) );
	}

	/**
	 * Meta description (Yoast only).
	 *
	 * @param \WP_Post $post Content.
	 * @return string
	 */
	private static function meta_description( $post ) {
		$template = (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		if ( '' === $template ) {
			$template = (string) self::yoast_option( 'metadesc-' . $post->post_type );
		}

		return '' === $template ? '' : self::clean( wpseo_replace_vars( $template, $post ) );
	}

	/**
	 * Whether Yoast excludes the content from search engines.
	 *
	 * @param \WP_Post $post Content.
	 * @return bool
	 */
	private static function is_noindex( $post ) {
		$value = (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true );
		if ( '1' === $value ) {
			return true;
		}
		if ( '2' === $value ) {
			return false;
		}

		return (bool) self::yoast_option( 'noindex-' . $post->post_type, false );
	}

	/**
	 * @param string $text Text with tags or entities.
	 * @return string
	 */
	private static function clean( $text ) {
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * @param string $content Content HTML.
	 * @return int
	 */
	private static function count_images_without_alt( $content ) {
		$count = 0;
		if ( preg_match_all( '/<img\b[^>]*>/i', $content, $images ) ) {
			foreach ( $images[0] as $image ) {
				if ( ! preg_match( '/\balt\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $image, $alt ) || '' === trim( $alt[2] . ( isset( $alt[3] ) ? $alt[3] : '' ) ) ) {
					$count += false !== stripos( $image, 'aria-hidden="true"' ) ? 0 : 1;
				}
			}
		}

		return $count;
	}

	/**
	 * @return int Images of the media library without alternative text.
	 */
	private static function count_library_images_without_alt() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' AND p.post_mime_type <> 'image/svg+xml'
			AND ( m.meta_value IS NULL OR TRIM( m.meta_value ) = '' )"
		);
	}

	/**
	 * Path used to match links with contents.
	 *
	 * @param string $url Address.
	 * @return string
	 */
	private static function normalize_path( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = strtolower( rawurldecode( untrailingslashit( $path ) ) );

		return '' === $path ? '/' : $path;
	}

	/**
	 * Contents of the site a piece of HTML links to.
	 *
	 * @param string $html  HTML (or block markup).
	 * @param int[]  $paths Content IDs keyed by path.
	 * @param array  $posts Scanned contents.
	 * @return array Content IDs as keys.
	 */
	private static function link_targets( $html, $paths, $posts ) {
		static $home = null;
		if ( null === $home ) {
			$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		}

		$targets = array();
		if ( ! preg_match_all( '/href\s*=\s*(?:\\\\?["\'])([^"\'\\\\]+)/i', $html, $matches ) ) {
			return $targets;
		}

		foreach ( $matches[1] as $href ) {
			$href = html_entity_decode( trim( $href ), ENT_QUOTES, 'UTF-8' );
			if ( '' === $href || '#' === $href[0] || preg_match( '/^(mailto|tel|javascript):/i', $href ) ) {
				continue;
			}
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( $host && strtolower( $host ) !== $home ) {
				continue;
			}

			$query = (string) wp_parse_url( $href, PHP_URL_QUERY );
			if ( preg_match( '/(?:^|&)(?:p|page_id)=(\d+)/', $query, $id ) && isset( $posts[ (int) $id[1] ] ) ) {
				$targets[ (int) $id[1] ] = true;
				continue;
			}

			$path = self::normalize_path( $href );
			if ( isset( $paths[ $path ] ) ) {
				$targets[ $paths[ $path ] ] = true;
			}
		}

		return $targets;
	}

	/**
	 * Contents reached from the menus, the navigation blocks, the templates and the synced patterns.
	 *
	 * @param int[] $paths Content IDs keyed by path.
	 * @param array $posts Scanned contents.
	 * @return array Content IDs as keys.
	 */
	private static function navigation_targets( $paths, $posts ) {
		$targets = array();

		foreach ( wp_get_nav_menus() as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu ) as $menu_item ) {
				if ( 'post_type' === $menu_item->type && isset( $posts[ (int) $menu_item->object_id ] ) ) {
					$targets[ (int) $menu_item->object_id ] = true;
				} elseif ( $menu_item->url ) {
					$targets += self::link_targets( 'href="' . $menu_item->url . '"', $paths, $posts );
				}
			}
		}

		$sources = get_posts(
			array(
				'post_type'        => array( 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_block' ),
				'post_status'      => 'publish',
				'numberposts'      => 300,
				'suppress_filters' => true,
			)
		);
		$markup  = wp_list_pluck( $sources, 'post_content' );

		// Templates of the theme that were never customised.
		if ( function_exists( 'get_block_templates' ) && wp_is_block_theme() ) {
			foreach ( array( 'wp_template_part', 'wp_template' ) as $template_type ) {
				foreach ( get_block_templates( array(), $template_type ) as $template ) {
					$markup[] = $template->content;
				}
			}
		}

		foreach ( $markup as $content ) {
			$targets += self::link_targets( $content, $paths, $posts );
			if ( preg_match_all( '/<!-- wp:(?:navigation-link|navigation-submenu|page-list-item)\s+\{[^}]*"id":(\d+)/', $content, $ids ) ) {
				foreach ( $ids[1] as $id ) {
					if ( isset( $posts[ (int) $id ] ) ) {
						$targets[ (int) $id ] = true;
					}
				}
			}
			// The page list block links to every published page.
			if ( false !== strpos( $content, '<!-- wp:page-list' ) ) {
				foreach ( $posts as $id => $post ) {
					if ( 'page' === $post['type'] ) {
						$targets[ $id ] = true;
					}
				}
			}
		}

		return $targets;
	}

	/**
	 * 404 errors logged by EO Tools and redirections.
	 *
	 * @return array
	 */
	public static function audit_errors() {
		global $wpdb;

		$logs         = $wpdb->prefix . 'eotools_404_logs';
		$redirections = $wpdb->prefix . 'eotools_redirections';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs ) ) !== $logs ) {
			return array(
				'data'     => array( 'available' => false ),
				'findings' => array(),
			);
		}

		// The redirections table may be missing on old installs.
		$has_redirections = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $redirections ) ) === $redirections;
		$join             = $has_redirections ? "LEFT JOIN $redirections r ON r.url = l.url AND r.status IN ('redirected', 'ignored')" : '';
		$untreated_only   = $has_redirections ? 'AND r.id IS NULL' : '';
		// Previews of drafts (EO Tools checks, editors) are not real visitor errors.
		$untreated_only  .= " AND l.url NOT LIKE '%%preview=true%%'";

		$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		$hits  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $logs l WHERE l.created_at >= %s AND l.url NOT LIKE '%%preview=true%%'", $since ) );
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.url, COUNT(*) AS hits, MAX(l.created_at) AS last_seen
				FROM $logs l $join
				WHERE l.created_at >= %s $untreated_only
				GROUP BY l.url ORDER BY hits DESC, last_seen DESC LIMIT 1000",
				$since
			),
			ARRAY_A
		);
		$statuses = $has_redirections ? $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM $redirections GROUP BY status", ARRAY_A ) : array();
		// phpcs:enable

		// Robots probing for weak spots (phpinfo.php, .env…) are not broken links.
		$untreated = array();
		$probes    = 0;
		foreach ( (array) $rows as $row ) {
			if ( self::is_probe( $row['url'] ) ) {
				++$probes;
				continue;
			}
			$untreated[] = array(
				'url'       => $row['url'],
				'hits'      => (int) $row['hits'],
				'last_seen' => $row['last_seen'],
			);
		}

		$findings = array();
		if ( $untreated ) {
			/* translators: %d: number of addresses. */
			$findings[] = self::finding( 'warning', sprintf( __( '%d adresse(s) en erreur 404 ces 30 derniers jours sans redirection : liens cassés ou anciennes adresses, à rediriger depuis EO Tools > 404-Erreur.', 'eo-tools' ), count( $untreated ) ) );
		}
		if ( $probes ) {
			/* translators: %d: number of addresses. */
			$findings[] = self::finding( 'info', sprintf( __( '%d adresse(s) en 404 sont des tentatives de robots cherchant des failles (fichiers .php, .env…) : sans effet sur le SEO, ne pas les rediriger.', 'eo-tools' ), $probes ) );
		}

		return array(
			'data'     => array(
				'available'      => true,
				'period_days'    => 30,
				'hits_404'       => $hits,
				'untreated_urls' => count( $untreated ),
				'top_untreated'  => array_slice( $untreated, 0, 20 ),
				'robot_probes'   => $probes,
				'redirections'   => wp_list_pluck( (array) $statuses, 'total', 'status' ),
				'admin_page'     => admin_url( 'admin.php?page=eo-tools-404-errors' ),
			),
			'findings' => $findings,
		);
	}

	/**
	 * Whether a 404 address looks like a robot probing for weak spots.
	 *
	 * @param string $url Requested address.
	 * @return bool
	 */
	private static function is_probe( $url ) {
		$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );

		return (bool) preg_match( '#\.(php\d?|asp|aspx|jsp|cgi|env|ini|log|sql|bak|old|swp|zip|tar|gz|rar|7z|ya?ml|config|xml\.bak)$|/[._]|/(wp-admin|wp-includes|wp-login|xmlrpc|cgi-bin|phpmyadmin|pma|adminer|server-status|server-info|actuator|vendor|\.git|\.well-known/security)\b|phpinfo|/info$#', $path );
	}
}
