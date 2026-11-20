<?php
/**
 * Ability provided by another plugin, relayed by EO Tools.
 *
 * Another plugin registers its abilities itself with the Abilities API and opts
 * in to EO Tools by declaring `meta.eo_tools` (see Eotools_Mcp_Abilities::get_third_party()).
 * This wrapper gives it the same face as the EO Tools abilities, so that the
 * admin toggles, the risk levels and the EO Tools MCP server handle it the same way.
 * EO Tools never registers nor executes it: its own plugin does.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_Third_Party extends Eotools_Mcp_Ability {

	/**
	 * Group prefix of the abilities of other plugins (followed by their ability category).
	 */
	const GROUP_PREFIX = 'ext:';

	/**
	 * @var \WP_Ability
	 */
	private $ability;

	/**
	 * @var array `meta.eo_tools` of the ability.
	 */
	private $eo_tools_meta;

	/**
	 * @param \WP_Ability $ability Ability registered by another plugin.
	 */
	public function __construct( \WP_Ability $ability ) {
		$this->ability = $ability;

		$meta                = $ability->get_meta();
		$this->eo_tools_meta = isset( $meta['eo_tools'] ) && is_array( $meta['eo_tools'] ) ? $meta['eo_tools'] : array();
	}

	/**
	 * Whether an ability opts in to EO Tools.
	 *
	 * @param \WP_Ability $ability Ability.
	 * @return bool
	 */
	public static function is_relayed( \WP_Ability $ability ) {
		$meta = $ability->get_meta();

		return 0 !== strpos( $ability->get_name(), self::NAMESPACE_PREFIX )
			&& isset( $meta['eo_tools'] ) && is_array( $meta['eo_tools'] );
	}

	/**
	 * Full name, as registered by its plugin (e.g. "wpshop/sync").
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->ability->get_name();
	}

	/**
	 * Name usable in HTML IDs.
	 *
	 * @return string
	 */
	public function get_slug() {
		return sanitize_title( str_replace( '/', '-', $this->ability->get_name() ) );
	}

	public function get_label() {
		return $this->ability->get_label();
	}

	public function get_description() {
		return $this->ability->get_description();
	}

	/**
	 * One group per ability category: "ext:<category>".
	 *
	 * @return string
	 */
	public function get_group() {
		return self::GROUP_PREFIX . $this->ability->get_category();
	}

	/**
	 * Label of the group: label of the ability category.
	 *
	 * @return string
	 */
	public function get_group_label() {
		$slug     = $this->ability->get_category();
		$category = function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( $slug ) ? wp_get_ability_category( $slug ) : null;

		return $category ? $category->get_label() : $this->ability->get_category();
	}

	/**
	 * Risk declared by the plugin. An unknown or missing level counts as sensitive.
	 *
	 * @return string
	 */
	public function get_risk() {
		$risk = isset( $this->eo_tools_meta['risk'] ) ? (string) $this->eo_tools_meta['risk'] : '';

		return in_array( $risk, array( self::RISK_READ, self::RISK_WRITE, self::RISK_SENSITIVE ), true ) ? $risk : self::RISK_SENSITIVE;
	}

	/**
	 * Name of the plugin that provides the ability (`meta.eo_tools.provider`, else the group label).
	 *
	 * @return string
	 */
	public function get_provider() {
		return ! empty( $this->eo_tools_meta['provider'] ) ? (string) $this->eo_tools_meta['provider'] : $this->get_group_label();
	}

	/**
	 * Short description for the administrator (`meta.eo_tools.summary`).
	 *
	 * @return string
	 */
	public function get_summary() {
		if ( ! empty( $this->eo_tools_meta['summary'] ) ) {
			return (string) $this->eo_tools_meta['summary'];
		}

		return parent::get_summary();
	}

	public function get_input_schema() {
		return $this->ability->get_input_schema();
	}

	public function get_output_schema() {
		return $this->ability->get_output_schema();
	}

	/**
	 * Runs the ability through its plugin (permission checks included).
	 *
	 * @param array $input Input.
	 * @return mixed|\WP_Error
	 */
	public function execute( $input ) {
		return $this->ability->execute( $input );
	}

	public function has_permission( $input ) {
		return true === $this->ability->check_permissions( $input );
	}

	/**
	 * EO Tools never registers another plugin's ability.
	 *
	 * @param string $category Ignored.
	 * @return array
	 */
	public function get_registration_args( $category ) {
		return array();
	}
}
