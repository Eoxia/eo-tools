<?php
/**
 * AJAX endpoints for managing the landing pages.
 *
 * @package EoTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_ajax_eo_save_landing_page_settings', 'eo_tools_landing_pages_ajax_save_settings' );

/**
 * Save the settings of a landing page (or toggle its active state).
 */
function eo_tools_landing_pages_ajax_save_settings() {
	check_ajax_referer( 'eo_tools_landing_pages_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Vous n\'avez pas la permission de faire cela.', 'eo-tools' ) ), 403 );
	}

	$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	if ( ! in_array( $type, array( 'coming_soon', 'maintenance', '404' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Type de page invalide.', 'eo-tools' ) ) );
	}

	$settings = get_option( 'eo_tools_landing_pages_settings', array() );

	// Fast toggle of the active state from the cards list.
	if ( isset( $_POST['active_toggle'] ) ) {
		$active = isset( $_POST['active'] ) && in_array( wp_unslash( $_POST['active'] ), array( 'true', '1' ), true );

		// Coming Soon and Maintenance are mutually exclusive.
		if ( $active ) {
			if ( 'coming_soon' === $type ) {
				$settings['maintenance']['active'] = false;
			} elseif ( 'maintenance' === $type ) {
				$settings['coming_soon']['active'] = false;
			}
		}

		$settings[ $type ]['active'] = $active;

		update_option( 'eo_tools_landing_pages_settings', $settings );
		wp_send_json_success(
			array(
				'message'  => __( 'État mis à jour avec succès.', 'eo-tools' ),
				'settings' => $settings,
			)
		);
	}

	// Full details save.
	$title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$description  = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
	$style        = isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : 'minimalist';
	$bg_color     = isset( $_POST['bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['bg_color'] ) ) : '';
	$text_color   = isset( $_POST['text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['text_color'] ) ) : '';
	$accent_color = isset( $_POST['accent_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['accent_color'] ) ) : '';
	$active       = isset( $_POST['active'] ) && in_array( wp_unslash( $_POST['active'] ), array( 'true', '1' ), true );

	if ( ! in_array( $style, array( 'minimalist', 'gradient', 'glassmorphism' ), true ) ) {
		$style = 'minimalist';
	}

	if ( $active ) {
		if ( 'coming_soon' === $type ) {
			$settings['maintenance']['active'] = false;
		} elseif ( 'maintenance' === $type ) {
			$settings['coming_soon']['active'] = false;
		}
	}

	$settings[ $type ] = array(
		'active'       => $active,
		'title'        => $title,
		'description'  => $description,
		'style'        => $style,
		'bg_color'     => $bg_color,
		'text_color'   => $text_color,
		'accent_color' => $accent_color,
	);

	update_option( 'eo_tools_landing_pages_settings', $settings );

	wp_send_json_success(
		array(
			'message'  => __( 'Paramètres enregistrés avec succès.', 'eo-tools' ),
			'settings' => $settings,
		)
	);
}
