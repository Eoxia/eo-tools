<?php
/**
 * Base class of a plugin adapter: reads and changes the settings of one plugin.
 *
 * An adapter only exposes the settings it knows, through the plugin's own API.
 * There is no generic "write any option" access. Secrets (passwords, API secret
 * keys) are never read or written by the AI: a human types them in WordPress.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Eotools_Mcp_Plugin_Adapter {

	/**
	 * wordpress.org slug of the plugin (e.g. "contact-form-7").
	 *
	 * @return string
	 */
	abstract public function get_slug();

	/**
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Whether the plugin is active and its API loaded.
	 *
	 * @return bool
	 */
	abstract public function is_available();

	/**
	 * Current settings, as shown to the AI.
	 *
	 * @param string $target Object the settings belong to (e.g. a form ID), empty for the default one.
	 * @return array|\WP_Error
	 */
	abstract public function get( $target = '' );

	/**
	 * Description of the settings the AI may change, for the tool description.
	 *
	 * @return array[] Keyed by setting name: type, label, choices…
	 */
	abstract public function get_schema();

	/**
	 * Changes settings. Every value is checked first: when one is invalid, nothing is changed.
	 *
	 * @param array  $values New values keyed by setting name.
	 * @param string $target Object the settings belong to, empty for the default one.
	 * @return array|\WP_Error { changed: array[] (name, before, after), notes: string[], target: string }.
	 */
	abstract public function update( $values, $target = '' );

	/**
	 * Secrets a human must type in WordPress.
	 *
	 * @return array[] Keyed by secret name: label, help.
	 */
	public function get_secrets() {
		return array();
	}

	/**
	 * Whether a secret is filled in.
	 *
	 * @param string $name Secret name.
	 * @return bool
	 */
	public function has_secret( $name ) {
		return false;
	}

	/**
	 * Saves a secret typed by a human.
	 *
	 * @param string $name  Secret name.
	 * @param string $value Secret.
	 * @return true|\WP_Error
	 */
	public function save_secret( $name, $value ) {
		return new \WP_Error( 'eo_tools_no_secret', __( 'Clé inconnue.', 'eo-tools' ) );
	}

	/**
	 * Secrets with their state and the page where a human types them.
	 *
	 * @return array[]
	 */
	public function describe_secrets() {
		$list = array();
		foreach ( $this->get_secrets() as $name => $secret ) {
			$list[ $name ] = array(
				'label'  => $secret['label'],
				'filled' => $this->has_secret( $name ),
				'url'    => \EoTools\Includes\Mcp\Eotools_Mcp_Plugin_Settings::secret_url( $this->get_slug(), $name ),
			);
		}

		return $list;
	}
}
