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
			'preview' => __( 'Aperçu et vérification', 'eo-tools' ),
			'media'   => __( 'Médias', 'eo-tools' ),
			'blocks'  => __( 'Blocs', 'eo-tools' ),
			'seo'     => __( 'SEO', 'eo-tools' ),
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
			Abilities\Eotools_Mcp_Ability_Check_Render::class,
			Abilities\Eotools_Mcp_Ability_Screenshot::class,
			Abilities\Eotools_Mcp_Ability_Search_Images::class,
			Abilities\Eotools_Mcp_Ability_Import_Image::class,
			Abilities\Eotools_Mcp_Ability_List_Blocks::class,
			Abilities\Eotools_Mcp_Ability_Get_Block::class,
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
