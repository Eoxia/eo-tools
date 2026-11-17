<?php
/**
 * Base class of a plugin adapter: reads and changes the settings of one plugin.
 *
 * An adapter only exposes the settings it knows, through the plugin's own API.
 * There is no generic "write any option" access. Secrets (passwords, API secret
 * keys) are never read or written by the AI: a human types them in WordPress.
 *
 * Writing an adapter (one file per plugin in this folder, registered in
 * Eotools_Mcp_Plugin_Settings::get_adapters()):
 * - get_slug(), get_label(), is_available(), get_schema();
 * - read_settings( $target ): current values, keyed like the schema;
 * - write_settings( $clean, $target ): saves the checked values with the plugin API.
 * Optional: check_values() for rules between settings, get_status() for read-only
 * information (state, links to screens a human must use), the secret methods.
 * Values are checked against the schema by validate_value(): override it for
 * special cases and call the parent for the rest.
 *
 * Schema types: bool, int (min, max), enum (choices), string, text, email,
 * emails (comma-separated list), url, urls (list of URLs).
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
	 * Description of the settings the AI may change.
	 *
	 * @return array[] Keyed by setting name: type, label, choices, min, max.
	 */
	abstract public function get_schema();

	/* ---------------------------------------------------------------------
	 * Reading and writing
	 * ------------------------------------------------------------------ */

	/**
	 * Current values, keyed like the schema.
	 *
	 * @param string $target Object the settings belong to (e.g. a form ID), empty for the default one.
	 * @return array|\WP_Error
	 */
	protected function read_settings( $target ) {
		return new \WP_Error( 'eo_tools_plugin_read', __( 'Cette extension ne peut pas être lue.', 'eo-tools' ) );
	}

	/**
	 * Saves checked values with the plugin API.
	 *
	 * @param array  $clean  Checked values keyed by setting name.
	 * @param string $target Object the settings belong to.
	 * @return string[]|\WP_Error Notes for the AI.
	 */
	protected function write_settings( $clean, $target ) {
		return new \WP_Error( 'eo_tools_plugin_write', __( 'Cette extension ne peut pas être modifiée.', 'eo-tools' ) );
	}

	/**
	 * Rules between settings, checked before anything is saved.
	 *
	 * @param array  $clean  Checked values of the call.
	 * @param string $target Object the settings belong to.
	 * @return string[] Reasons keyed by setting name, empty when everything is fine.
	 */
	protected function check_values( $clean, $target ) {
		return array();
	}

	/**
	 * Read-only information shown with the settings: state, links to screens a human must use…
	 *
	 * @return array
	 */
	protected function get_status() {
		return array();
	}

	/**
	 * Current settings, as shown to the AI.
	 *
	 * @param string $target Object the settings belong to, empty for the default one.
	 * @return array|\WP_Error { settings, status? }.
	 */
	public function get( $target = '' ) {
		$settings = $this->read_settings( $target );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		$result = array( 'settings' => $settings );
		$status = $this->get_status();
		if ( $status ) {
			$result['status'] = $status;
		}

		return $result;
	}

	/**
	 * Changes settings. Every value is checked first: when one is invalid, nothing is changed.
	 *
	 * @param array  $values New values keyed by setting name.
	 * @param string $target Object the settings belong to, empty for the default one.
	 * @return array|\WP_Error { target, changed: array[] (name, before, after), notes: string[] }.
	 */
	public function update( $values, $target = '' ) {
		$clean = $this->validate_values( $values );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$errors = $this->check_values( $clean, $target );
		if ( $errors ) {
			return self::invalid( $errors );
		}

		$before = $this->read_settings( $target );
		if ( is_wp_error( $before ) ) {
			return $before;
		}

		$notes = $this->write_settings( $clean, $target );
		if ( is_wp_error( $notes ) ) {
			return $notes;
		}

		$after = $this->read_settings( $target );

		return array(
			'target'  => (string) $target,
			'changed' => is_wp_error( $after ) ? array() : self::diff( $before, $after ),
			'notes'   => (array) $notes,
		);
	}

	/* ---------------------------------------------------------------------
	 * Checks
	 * ------------------------------------------------------------------ */

	/**
	 * Checks every value against the schema.
	 *
	 * @param array $values Values sent by the AI.
	 * @return array|\WP_Error Checked values, or the list of refused values.
	 */
	protected function validate_values( $values ) {
		$schema = $this->get_schema();
		$errors = array();
		$clean  = array();

		foreach ( (array) $values as $name => $value ) {
			if ( ! isset( $schema[ $name ] ) ) {
				$errors[ $name ] = __( 'Réglage inconnu (voir "schema" dans eo-tools/get-plugin-settings).', 'eo-tools' );
				continue;
			}
			$checked = $this->validate_value( $name, $value, $schema[ $name ] );
			if ( is_wp_error( $checked ) ) {
				$errors[ $name ] = $checked->get_error_message();
				continue;
			}
			$clean[ $name ] = $checked;
		}

		return $errors ? self::invalid( $errors ) : $clean;
	}

	/**
	 * Checks and normalizes one value according to its schema type.
	 *
	 * @param string $name   Setting name.
	 * @param mixed  $value  Value sent by the AI.
	 * @param array  $schema Setting schema.
	 * @return mixed|\WP_Error
	 */
	protected function validate_value( $name, $value, $schema ) {
		switch ( $schema['type'] ) {
			case 'bool':
				return is_bool( $value ) ? $value : new \WP_Error( 'invalid', __( 'Valeur attendue : true ou false.', 'eo-tools' ) );

			case 'int':
				if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
					return new \WP_Error( 'invalid', __( 'Nombre entier attendu.', 'eo-tools' ) );
				}
				$value = (int) $value;
				$min   = isset( $schema['min'] ) ? $schema['min'] : 0;
				$max   = isset( $schema['max'] ) ? $schema['max'] : PHP_INT_MAX;
				if ( $value < $min || $value > $max ) {
					/* translators: 1: minimum, 2: maximum */
					return new \WP_Error( 'invalid', sprintf( __( 'Valeur attendue entre %1$d et %2$d.', 'eo-tools' ), $min, $max ) );
				}
				return $value;

			case 'enum':
				return in_array( $value, $schema['choices'], true ) ? $value : new \WP_Error( 'invalid', __( 'Valeurs possibles : ', 'eo-tools' ) . implode( ', ', $schema['choices'] ) );

			case 'urls':
				if ( ! is_array( $value ) ) {
					return new \WP_Error( 'invalid', __( 'Liste d\'adresses attendue.', 'eo-tools' ) );
				}
				$urls = array();
				foreach ( $value as $url ) {
					$checked = $this->validate_value( $name, $url, array( 'type' => 'url' ) );
					if ( is_wp_error( $checked ) ) {
						return $checked;
					}
					if ( '' !== $checked ) {
						$urls[] = $checked;
					}
				}
				return array_values( array_unique( $urls ) );
		}

		if ( ! is_string( $value ) ) {
			return new \WP_Error( 'invalid', __( 'Texte attendu.', 'eo-tools' ) );
		}

		switch ( $schema['type'] ) {
			case 'email':
				$value = trim( $value );
				if ( '' !== $value && ! is_email( $value ) ) {
					return new \WP_Error( 'invalid', __( 'Adresse e-mail attendue.', 'eo-tools' ) );
				}
				return sanitize_email( $value );

			case 'emails':
				$emails = array_filter( array_map( 'trim', explode( ',', $value ) ) );
				foreach ( $emails as $email ) {
					if ( ! is_email( $email ) ) {
						/* translators: %s: value */
						return new \WP_Error( 'invalid', sprintf( __( '« %s » n\'est pas une adresse e-mail.', 'eo-tools' ), $email ) );
					}
				}
				return implode( ',', array_map( 'sanitize_email', $emails ) );

			case 'url':
				$value = trim( $value );
				if ( '' !== $value && ! wp_http_validate_url( $value ) ) {
					/* translators: %s: value */
					return new \WP_Error( 'invalid', sprintf( __( '« %s » n\'est pas une adresse web valide.', 'eo-tools' ), $value ) );
				}
				return esc_url_raw( $value );

			case 'text':
				return sanitize_textarea_field( $value );

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Error listing the refused values.
	 *
	 * @param string[] $errors Reasons keyed by setting name.
	 * @return \WP_Error
	 */
	protected static function invalid( $errors ) {
		$lines = array();
		foreach ( $errors as $name => $reason ) {
			$lines[] = ( '' !== (string) $name ? $name . ' : ' : '' ) . $reason;
		}

		return new \WP_Error( 'eo_tools_invalid_settings', __( 'Aucun réglage n\'a été modifié, corrigez les valeurs refusées :', 'eo-tools' ) . "\n" . implode( "\n", $lines ) );
	}

	/**
	 * Settings that changed.
	 *
	 * @param array $before Values before.
	 * @param array $after  Values after.
	 * @return array[] { name, before, after }.
	 */
	protected static function diff( $before, $after ) {
		$changed = array();
		foreach ( $after as $name => $value ) {
			$previous = isset( $before[ $name ] ) ? $before[ $name ] : null;
			if ( $value !== $previous ) {
				$changed[] = array(
					'name'   => $name,
					'before' => $previous,
					'after'  => $value,
				);
			}
		}

		return $changed;
	}

	/* ---------------------------------------------------------------------
	 * Secrets, typed by a human
	 * ------------------------------------------------------------------ */

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
	 * Whether a missing secret blocks something the site uses (otherwise it is not worth asking for).
	 *
	 * @param string $name Secret name.
	 * @return bool
	 */
	public function is_secret_needed( $name ) {
		return ! $this->has_secret( $name );
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
				'needed' => $this->is_secret_needed( $name ),
				'url'    => \EoTools\Includes\Mcp\Eotools_Mcp_Plugin_Settings::secret_url( $this->get_slug(), $name ),
			);
		}

		return $list;
	}
}
