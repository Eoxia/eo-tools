<?php
/**
 * AJAX endpoints for managing landing pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_ajax_eo_save_landing_page_settings', 'eo_tools_landing_pages_ajax_save_settings' );
add_action( 'wp_ajax_eo_get_login_logs', 'eo_tools_landing_pages_ajax_get_login_logs' );
add_action( 'wp_ajax_eo_clear_login_logs', 'eo_tools_landing_pages_ajax_clear_login_logs' );

function eo_tools_landing_pages_ajax_save_settings() {
	check_ajax_referer( 'eo_tools_landing_pages_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Vous n\'avez pas la permission de faire cela.', 'eo-tools' ) ), 403 );
	}

	$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
	if ( ! in_array( $type, array( 'coming_soon', 'maintenance', 'login', 'register', '404' ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Type de page invalide.', 'eo-tools' ) ) );
	}

	$settings = get_option( 'eo_tools_landing_pages_settings', array() );

	// Toggling active state only (fast toggle from list)
	if ( isset( $_POST['active_toggle'] ) ) {
		$active = !empty( $_POST['active'] ) && ( $_POST['active'] === 'true' || $_POST['active'] === '1' );
		
		// If turning Coming Soon or Maintenance to ON, make sure the other is turned OFF
		if ( $active ) {
			if ( 'coming_soon' === $type ) {
				$settings['maintenance']['active'] = false;
			} elseif ( 'maintenance' === $type ) {
				$settings['coming_soon']['active'] = false;
			}
		}

		$settings[$type]['active'] = $active;

		update_option( 'eo_tools_landing_pages_settings', $settings );
		wp_send_json_success( array(
			'message'  => __( 'État mis à jour avec succès.', 'eo-tools' ),
			'settings' => $settings,
		) );
	}

	// Toggling email filtering state only (fast toggle from card)
	if ( isset( $_POST['email_filter_toggle'] ) ) {
		$active = !empty( $_POST['active'] ) && ( $_POST['active'] === 'true' || $_POST['active'] === '1' );
		if ( 'login' === $type || 'register' === $type ) {
			$settings[$type]['email_filtering_active'] = $active;
		}

		update_option( 'eo_tools_landing_pages_settings', $settings );
		wp_send_json_success( array(
			'message'  => __( 'Filtrage e-mails mis à jour.', 'eo-tools' ),
			'settings' => $settings,
		) );
	}

	// Full details save
	$title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$description  = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
	$style        = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] ) : 'minimalist';
	$bg_color     = isset( $_POST['bg_color'] ) ? sanitize_hex_color( $_POST['bg_color'] ) : '';
	$text_color   = isset( $_POST['text_color'] ) ? sanitize_hex_color( $_POST['text_color'] ) : '';
	$accent_color = isset( $_POST['accent_color'] ) ? sanitize_hex_color( $_POST['accent_color'] ) : '';
	$active       = isset( $_POST['active'] ) && ( $_POST['active'] === 'true' || $_POST['active'] === '1' );

	if ( $active ) {
		if ( 'coming_soon' === $type ) {
			$settings['maintenance']['active'] = false;
		} elseif ( 'maintenance' === $type ) {
			$settings['coming_soon']['active'] = false;
		}
	}

	$settings[$type] = array(
		'active'       => $active,
		'title'        => $title,
		'description'  => $description,
		'style'        => $style,
		'bg_color'     => $bg_color,
		'text_color'   => $text_color,
		'accent_color' => $accent_color,
	);

	if ( 'login' === $type || 'register' === $type ) {
		$email_filtering_active = isset( $_POST['email_filtering_active'] ) && ( $_POST['email_filtering_active'] === 'true' || $_POST['email_filtering_active'] === '1' );
		$email_rules            = isset( $_POST['email_rules'] ) ? sanitize_textarea_field( wp_unslash( $_POST['email_rules'] ) ) : '';

		$ip_rules = array();
		if ( isset( $_POST['ip_rules'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['ip_rules'] ), true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $rule ) {
					if ( isset( $rule['ip'] ) && isset( $rule['action'] ) ) {
						$ip_rules[] = array(
							'ip'     => sanitize_text_field( $rule['ip'] ),
							'action' => in_array( $rule['action'], array( 'allow', 'block' ) ) ? $rule['action'] : 'block',
						);
					}
				}
			}
		}

		$settings[$type]['email_filtering_active'] = $email_filtering_active;
		$settings[$type]['email_rules']            = $email_rules;
		$settings[$type]['ip_rules']               = $ip_rules;

		if ( 'login' === $type ) {
			$log_limit = isset( $_POST['log_limit'] ) ? intval( $_POST['log_limit'] ) : 1000;
			if ( $log_limit <= 0 ) {
				$log_limit = 1000;
			}
			$settings['login']['log_limit'] = $log_limit;
		} elseif ( 'register' === $type ) {
			$inherit_login_rules = isset( $_POST['inherit_login_rules'] ) && ( $_POST['inherit_login_rules'] === 'true' || $_POST['inherit_login_rules'] === '1' );
			$settings['register']['inherit_login_rules'] = $inherit_login_rules;
			
			$success_action = isset( $_POST['success_action'] ) ? sanitize_text_field( $_POST['success_action'] ) : 'none';
			if ( ! in_array( $success_action, array( 'none', 'timer', 'tictactoe', 'flappybird' ) ) ) {
				$success_action = 'none';
			}
			$settings['register']['success_action'] = $success_action;
		}
	}

	update_option( 'eo_tools_landing_pages_settings', $settings );

	wp_send_json_success( array(
		'message'  => __( 'Paramètres enregistrés avec succès.', 'eo-tools' ),
		'settings' => $settings,
	) );
}

function eo_tools_landing_pages_ajax_get_login_logs() {
	check_ajax_referer( 'eo_tools_landing_pages_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Vous n\'avez pas la permission de faire cela.', 'eo-tools' ) ), 403 );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'eo_login_attempts';

	// Check if table exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
		wp_send_json_success( array( 'logs' => array() ) );
	}

	$logs = $wpdb->get_results( "SELECT id, time, ip, username, status, user_agent FROM $table_name ORDER BY id DESC LIMIT 100", ARRAY_A );

	foreach ( $logs as &$log ) {
		switch ( $log['status'] ) {
			case 'success':
				$log['status_label'] = __( 'Succès', 'eo-tools' );
				break;
			case 'failed':
				$log['status_label'] = __( 'Échec', 'eo-tools' );
				break;
			case 'blocked_ip':
				$log['status_label'] = __( 'IP Bloquée', 'eo-tools' );
				break;
			case 'blocked_email':
				$log['status_label'] = __( 'E-mail non autorisé', 'eo-tools' );
				break;
			default:
				$log['status_label'] = esc_html( $log['status'] );
		}
		$log['formatted_time'] = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log['time'] );
	}

	wp_send_json_success( array( 'logs' => $logs ) );
}

function eo_tools_landing_pages_ajax_clear_login_logs() {
	check_ajax_referer( 'eo_tools_landing_pages_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Vous n\'avez pas la permission de faire cela.', 'eo-tools' ) ), 403 );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'eo_login_attempts';

	$result = $wpdb->query( "TRUNCATE TABLE $table_name" );
	if ( false === $result ) {
		$wpdb->query( "DELETE FROM $table_name" );
	}

	wp_send_json_success( array( 'message' => __( 'Journal vidé avec succès.', 'eo-tools' ) ) );
}
