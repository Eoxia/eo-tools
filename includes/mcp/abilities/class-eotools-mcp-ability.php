<?php
/**
 * Base class for EO Tools abilities.
 *
 * One ability = one class. The registry turns each enabled ability into a
 * WordPress ability (Abilities API), exposed as a tool on the EO Tools MCP server.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Eotools_Mcp_Ability {

	const NAMESPACE_PREFIX = 'eo-tools/';

	const RISK_READ      = 'read';
	const RISK_WRITE     = 'write';
	const RISK_SENSITIVE = 'sensitive';

	/**
	 * Short name, without namespace (e.g. "list-posts").
	 *
	 * @return string
	 */
	abstract public function get_slug();

	/**
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Description read by the AI to decide when and how to use the tool.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * Group used to sort abilities in the admin (see Eotools_Mcp_Abilities::get_groups()).
	 *
	 * @return string
	 */
	abstract public function get_group();

	/**
	 * @return string One of the RISK_* constants.
	 */
	abstract public function get_risk();

	/**
	 * @return array JSON Schema of the input.
	 */
	abstract public function get_input_schema();

	/**
	 * @param array $input Validated input.
	 * @return array|\WP_Error
	 */
	abstract public function execute( $input );

	/**
	 * Default permission check. Abilities working on a given object should refine it.
	 *
	 * @param array $input Input.
	 * @return bool
	 */
	public function has_permission( $input ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return array JSON Schema of the output.
	 */
	public function get_output_schema() {
		return array( 'type' => 'object' );
	}

	/**
	 * Full ability name (e.g. "eo-tools/list-posts").
	 *
	 * @return string
	 */
	public function get_name() {
		return self::NAMESPACE_PREFIX . $this->get_slug();
	}

	/**
	 * Every ability works out of the box: an administrator can turn any of them off.
	 *
	 * Sensitive abilities only prepare changes that a human applies.
	 *
	 * @return bool
	 */
	public function is_enabled_by_default() {
		return true;
	}

	/**
	 * Arguments for wp_register_ability().
	 *
	 * @param string $category Ability category slug.
	 * @return array
	 */
	public function get_registration_args( $category ) {
		$readonly = self::RISK_READ === $this->get_risk();

		return array(
			'label'               => $this->get_label(),
			'description'         => $this->get_description(),
			'category'            => $category,
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'execute_callback'    => function ( $input = array() ) {
				return $this->execute( is_array( $input ) ? $input : array() );
			},
			'permission_callback' => function ( $input = array() ) {
				return $this->has_permission( is_array( $input ) ? $input : array() );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => $readonly,
					'destructive' => self::RISK_SENSITIVE === $this->get_risk(),
					'idempotent'  => $readonly,
				),
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		);
	}
}
