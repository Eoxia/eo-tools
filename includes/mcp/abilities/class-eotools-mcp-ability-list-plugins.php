<?php
/**
 * Ability: list the installed plugins.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Abilities;

use EoTools\Includes\Mcp\Eotools_Mcp_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Ability_List_Plugins extends Eotools_Mcp_Ability {

	public function get_slug() {
		return 'list-plugins';
	}

	public function get_label() {
		return __( 'Lister les extensions installées', 'eo-tools' );
	}

	public function get_description() {
		return __( 'Liste les extensions installées sur le site : nom, version, active ou non, mise à jour disponible, et si elle a été installée par une IA. Consultez-la avant de proposer une nouvelle extension ou du code : une extension déjà présente fait peut-être déjà ce qui est demandé.', 'eo-tools' );
	}

	public function get_group() {
		return 'plugins';
	}

	public function get_risk() {
		return self::RISK_READ;
	}

	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'status' => array(
					'type'    => 'string',
					'enum'    => array( 'all', 'active', 'inactive' ),
					'default' => 'all',
				),
			),
			'additionalProperties' => false,
		);
	}

	public function has_permission( $input ) {
		return current_user_can( 'activate_plugins' );
	}

	public function execute( $input ) {
		return Eotools_Mcp_Plugins::get_installed( isset( $input['status'] ) ? $input['status'] : 'all' );
	}
}
