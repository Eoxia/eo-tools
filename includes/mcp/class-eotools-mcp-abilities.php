<?php
/**
 * Registry of EO Tools abilities.
 *
 * Holds every ability shipped by EO Tools, stores which ones the administrator
 * enabled, and registers only the enabled ones with the Abilities API.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

use EoTools\Includes\Mcp\Abilities\Eotools_Mcp_Ability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Abilities {

	const CATEGORY     = 'eo-tools';
	const STATE_OPTION = 'eo_tools_mcp_abilities_state';

	/**
	 * @var Eotools_Mcp_Ability[]|null Keyed by ability name.
	 */
	private static $abilities = null;

	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		add_filter( 'eo_tools_mcp_tools', array( __CLASS__, 'add_tools' ) );
	}

	/**
	 * Admin groups, in display order.
	 *
	 * @return array Labels keyed by group slug.
	 */
	public static function get_groups() {
		return array(
			'content' => __( 'Contenus', 'eo-tools' ),
			'site'    => __( 'Modèles du site', 'eo-tools' ),
			'styles'  => __( 'Styles et polices', 'eo-tools' ),
			'preview' => __( 'Aperçu et vérification', 'eo-tools' ),
			'media'   => __( 'Médias', 'eo-tools' ),
			'blocks'  => __( 'Blocs', 'eo-tools' ),
			'plugins' => __( 'Extensions', 'eo-tools' ),
			'updates' => __( 'Mises à jour', 'eo-tools' ),
			'code'    => __( 'Code (snippets)', 'eo-tools' ),
			'seo'     => __( 'SEO', 'eo-tools' ),
		);
	}

	/**
	 * Risk levels, from the safest to the most sensitive.
	 *
	 * Every ability belongs to exactly one level, shown in the admin so that an
	 * administrator can turn a whole level off (e.g. everything sensitive).
	 *
	 * @return array[] Label and description keyed by level.
	 */
	public static function get_risk_levels() {
		return array(
			Eotools_Mcp_Ability::RISK_READ      => array(
				'label'       => __( 'Lecture', 'eo-tools' ),
				'description' => __( 'L\'IA consulte le site. Elle ne modifie rien.', 'eo-tools' ),
			),
			Eotools_Mcp_Ability::RISK_WRITE     => array(
				'label'       => __( 'Écriture', 'eo-tools' ),
				'description' => __( 'L\'IA crée ou modifie des brouillons et ajoute des médias. Rien ne change pour vos visiteurs.', 'eo-tools' ),
			),
			Eotools_Mcp_Ability::RISK_SENSITIVE => array(
				'label'       => __( 'Sensible', 'eo-tools' ),
				'description' => __( 'L\'IA touche au site en ligne : elle prépare des modifications que vous validez (contenus publiés, modèles, styles, code), installe des extensions sans les activer, et fait les mises à jour avec retour automatique en cas de problème.', 'eo-tools' ),
			),
		);
	}

	/**
	 * Short descriptions shown on the admin cards, keyed by ability name.
	 *
	 * @return string[]
	 */
	public static function get_summaries() {
		return array(
			'eo-tools/list-posts'              => __( 'Parcourt vos articles et pages pour trouver un contenu.', 'eo-tools' ),
			'eo-tools/get-post'                => __( 'Lit un article ou une page, avec ses blocs.', 'eo-tools' ),
			'eo-tools/create-draft-post'       => __( 'Rédige un nouvel article, enregistré en brouillon.', 'eo-tools' ),
			'eo-tools/create-draft-page'       => __( 'Crée une nouvelle page, enregistrée en brouillon.', 'eo-tools' ),
			'eo-tools/update-draft'            => __( 'Modifie un brouillon. Les contenus publiés ne sont jamais touchés.', 'eo-tools' ),
			'eo-tools/propose-update'          => __( 'Propose une modification d\'un contenu publié, que vous appliquez ou rejetez.', 'eo-tools' ),
			'eo-tools/list-templates'          => __( 'Liste les modèles du site : pages, en-tête, pied de page…', 'eo-tools' ),
			'eo-tools/get-template'            => __( 'Lit le contenu d\'un modèle ou d\'une partie de modèle.', 'eo-tools' ),
			'eo-tools/propose-template-update' => __( 'Propose une modification d\'un modèle (en-tête, pied de page…), que vous validez.', 'eo-tools' ),
			'eo-tools/get-styles'              => __( 'Lit les styles du site : couleurs, polices, tailles, espacements.', 'eo-tools' ),
			'eo-tools/propose-styles-update'   => __( 'Propose de nouveaux styles (palette, typo…), que vous prévisualisez et validez.', 'eo-tools' ),
			'eo-tools/search-fonts'            => __( 'Cherche une police dans Google Fonts.', 'eo-tools' ),
			'eo-tools/install-font'            => __( 'Installe une police Google Fonts, hébergée sur le site (RGPD).', 'eo-tools' ),
			'eo-tools/check-render'            => __( 'Vérifie le rendu d\'une page et signale les erreurs visibles.', 'eo-tools' ),
			'eo-tools/search-images'           => __( 'Cherche des photos libres et gratuites sur Openverse.', 'eo-tools' ),
			'eo-tools/import-image'            => __( 'Ajoute une image à la médiathèque (Openverse, adresse, SVG, image générée).', 'eo-tools' ),
			'eo-tools/list-blocks'             => __( 'Liste les blocs disponibles (WordPress et EO Blocks).', 'eo-tools' ),
			'eo-tools/get-block'               => __( 'Lit le mode d\'emploi d\'un bloc et ses réglages.', 'eo-tools' ),
			'eo-tools/list-plugins'            => __( 'Liste les extensions installées et leur état.', 'eo-tools' ),
			'eo-tools/search-plugins'          => __( 'Cherche des extensions gratuites sur wordpress.org.', 'eo-tools' ),
			'eo-tools/install-plugin'          => __( 'Installe une extension de wordpress.org, sans l\'activer : vous l\'activez vous-même.', 'eo-tools' ),
			'eo-tools/check-updates'           => __( 'Liste les mises à jour disponibles (WordPress, extensions, thèmes).', 'eo-tools' ),
			'eo-tools/update-site'             => __( 'Met à jour le site, avec retour automatique à l\'ancienne version en cas de problème.', 'eo-tools' ),
			'eo-tools/list-snippets'           => __( 'Liste les snippets de code du site et leur état.', 'eo-tools' ),
			'eo-tools/get-snippet'             => __( 'Lit le code d\'un snippet.', 'eo-tools' ),
			'eo-tools/read-source'             => __( 'Lit le code du thème et des extensions, sans rien modifier.', 'eo-tools' ),
			'eo-tools/search-source'           => __( 'Cherche un texte dans le code du thème et des extensions.', 'eo-tools' ),
			'eo-tools/propose-snippet'         => __( 'Propose du code CSS, JS ou PHP en dernier recours, que vous activez vous-même.', 'eo-tools' ),
			'eo-tools/audit-site-seo'          => __( 'Fait le bilan SEO du site : réglages, indexation, contenus, erreurs 404.', 'eo-tools' ),
			'eo-tools/list-seo-issues'         => __( 'Liste les contenus touchés par un problème SEO (méta description absente, contenu orphelin…).', 'eo-tools' ),
		);
	}

	/**
	 * Abilities of a risk level.
	 *
	 * @param string $risk Risk level.
	 * @return Eotools_Mcp_Ability[]
	 */
	public static function get_by_risk( $risk ) {
		return array_filter(
			self::get_all(),
			function ( $ability ) use ( $risk ) {
				return $ability->get_risk() === $risk;
			}
		);
	}

	/**
	 * @return Eotools_Mcp_Ability[] Keyed by ability name.
	 */
	public static function get_all() {
		if ( null !== self::$abilities ) {
			return self::$abilities;
		}

		$classes = array(
			Abilities\Eotools_Mcp_Ability_List_Posts::class,
			Abilities\Eotools_Mcp_Ability_Get_Post::class,
			Abilities\Eotools_Mcp_Ability_Create_Draft_Post::class,
			Abilities\Eotools_Mcp_Ability_Create_Draft_Page::class,
			Abilities\Eotools_Mcp_Ability_Update_Draft::class,
			Abilities\Eotools_Mcp_Ability_Propose_Update::class,
			Abilities\Eotools_Mcp_Ability_List_Templates::class,
			Abilities\Eotools_Mcp_Ability_Get_Template::class,
			Abilities\Eotools_Mcp_Ability_Propose_Template_Update::class,
			Abilities\Eotools_Mcp_Ability_Get_Styles::class,
			Abilities\Eotools_Mcp_Ability_Propose_Styles_Update::class,
			Abilities\Eotools_Mcp_Ability_Search_Fonts::class,
			Abilities\Eotools_Mcp_Ability_Install_Font::class,
			Abilities\Eotools_Mcp_Ability_Check_Render::class,
			Abilities\Eotools_Mcp_Ability_Search_Images::class,
			Abilities\Eotools_Mcp_Ability_Import_Image::class,
			Abilities\Eotools_Mcp_Ability_List_Blocks::class,
			Abilities\Eotools_Mcp_Ability_Get_Block::class,
			Abilities\Eotools_Mcp_Ability_List_Plugins::class,
			Abilities\Eotools_Mcp_Ability_Search_Plugins::class,
			Abilities\Eotools_Mcp_Ability_Install_Plugin::class,
			Abilities\Eotools_Mcp_Ability_Check_Updates::class,
			Abilities\Eotools_Mcp_Ability_Update_Site::class,
			Abilities\Eotools_Mcp_Ability_List_Snippets::class,
			Abilities\Eotools_Mcp_Ability_Get_Snippet::class,
			Abilities\Eotools_Mcp_Ability_Read_Source::class,
			Abilities\Eotools_Mcp_Ability_Search_Source::class,
			Abilities\Eotools_Mcp_Ability_Propose_Snippet::class,
			Abilities\Eotools_Mcp_Ability_Audit_Site_Seo::class,
			Abilities\Eotools_Mcp_Ability_List_Seo_Issues::class,
		);

		/**
		 * Filters the ability classes provided by EO Tools.
		 *
		 * @param string[] $classes Class names extending Eotools_Mcp_Ability.
		 */
		$classes = (array) apply_filters( 'eo_tools_mcp_ability_classes', $classes );

		self::$abilities = array();
		foreach ( $classes as $class ) {
			if ( is_string( $class ) && is_subclass_of( $class, Eotools_Mcp_Ability::class ) ) {
				$ability                                = new $class();
				self::$abilities[ $ability->get_name() ] = $ability;
			}
		}

		return self::$abilities;
	}

	/**
	 * @param string $name Ability name.
	 * @return Eotools_Mcp_Ability|null
	 */
	public static function get( $name ) {
		$abilities = self::get_all();

		return isset( $abilities[ $name ] ) ? $abilities[ $name ] : null;
	}

	public static function is_enabled( Eotools_Mcp_Ability $ability ) {
		$state = get_option( self::STATE_OPTION, array() );

		if ( is_array( $state ) && isset( $state[ $ability->get_name() ] ) ) {
			return (bool) $state[ $ability->get_name() ];
		}

		return $ability->is_enabled_by_default();
	}

	/**
	 * Enables or disables abilities.
	 *
	 * @param string[] $names   Ability names.
	 * @param bool     $enabled New state.
	 */
	public static function set_enabled( $names, $enabled ) {
		$state = get_option( self::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();

		foreach ( (array) $names as $name ) {
			if ( null !== self::get( $name ) ) {
				$state[ $name ] = (bool) $enabled;
			}
		}

		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * @return Eotools_Mcp_Ability[]
	 */
	public static function get_enabled() {
		return array_filter( self::get_all(), array( __CLASS__, 'is_enabled' ) );
	}

	public static function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'EO Tools', 'eo-tools' ),
				'description' => __( 'Abilities fournies par EO Tools pour gérer les contenus du site.', 'eo-tools' ),
			)
		);
	}

	public static function register_abilities() {
		foreach ( self::get_enabled() as $ability ) {
			wp_register_ability( $ability->get_name(), $ability->get_registration_args( self::CATEGORY ) );
		}
	}

	/**
	 * Exposes the enabled abilities as tools on the EO Tools MCP server.
	 *
	 * @param string[] $tools Ability names.
	 * @return string[]
	 */
	public static function add_tools( $tools ) {
		foreach ( self::get_enabled() as $ability ) {
			if ( wp_has_ability( $ability->get_name() ) ) {
				$tools[] = $ability->get_name();
			}
		}

		return $tools;
	}
}
