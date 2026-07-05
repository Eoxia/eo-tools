<?php
/**
 * Admin settings menu for EO Tools.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Admin;

if (!defined('ABSPATH')) {
	exit;
}

class Eotools_Menu {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		add_action( 'wp_ajax_eo_tools_get_cookie_registry', array( $this, 'ajax_get_registry' ) );
		add_action( 'wp_ajax_eo_tools_save_cookie_registry', array( $this, 'ajax_save_registry' ) );
		
		add_action( 'wp_ajax_eo_tools_get_scan_history', array( $this, 'ajax_get_scan_history' ) );
		add_action( 'wp_ajax_eo_tools_save_scan_result', array( $this, 'ajax_save_scan_result' ) );
		add_action( 'wp_ajax_eo_tools_validate_scan', array( $this, 'ajax_validate_scan' ) );
		add_action( 'wp_ajax_eo_tools_scan_frontend_cookies', array( $this, 'ajax_scan_frontend_cookies' ) );
	}

	public function ajax_validate_scan() {
		check_ajax_referer( 'eo_tools_cookie_registry_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		
		$scan_id = isset( $_POST['timestamp'] ) ? intval( $_POST['timestamp'] ) : 0;
		$names = isset( $_POST['names'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['names'] ) ) : array();
		
		if ( ! $scan_id ) {
			wp_send_json_error( 'Missing scan id' );
		}
		
		global $wpdb;
		$table_scans = $wpdb->prefix . 'eotools_scan';
		
		$wpdb->update( $table_scans, array( 'requires_validation' => 0 ), array( 'id' => $scan_id ) );

		// 2. Insert into log table
		$table_log = $wpdb->prefix . 'eotools_cookie_log';
		$comments_text = implode( ', ', $names );
		$admin_consent_id = hash( 'sha256', $comments_text );
		
		$wpdb->insert(
			$table_log,
			array(
				'consent_id'     => $admin_consent_id,
				'consent_status' => 'ADMIN_VALIDATION',
				'comments'       => $comments_text,
				'time'           => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
		
		$scans = $wpdb->get_results( "SELECT * FROM $table_scans ORDER BY id DESC LIMIT 50", ARRAY_A );
		
		$formatted_history = array();
		foreach ( $scans as $scan ) {
			$new_cookies = $scan['new_cookies'] ? json_decode( $scan['new_cookies'], true ) : array();
			$added_names = array();
			if ( is_array( $new_cookies ) ) {
				foreach ( $new_cookies as $c ) {
					if ( isset( $c['cookie']['name'] ) ) {
						$added_names[] = $c['cookie']['name'];
					}
				}
			}

			$formatted_history[] = array(
				'id'         => $scan['id'],
				'ref'        => $scan['ref'],
				'type'       => $scan['type'],
				'date'       => date( 'd/m/Y H:i:s', strtotime( $scan['date_end'] !== '0000-00-00 00:00:00' ? $scan['date_end'] : $scan['date_start'] ) ),
				'status'     => strtoupper( $scan['status'] ),
				'totalFound' => $scan['total_cookies_found'],
				'added'      => count( $added_names ),
				'addedNames' => $added_names,
				'addedCookies' => $new_cookies,
				'validated'  => $scan['requires_validation'] ? false : true,
				'timestamp'  => $scan['id'],
				'batch_id'   => $scan['id'],
				'source'     => $scan['type']
			);
		}
		
		wp_send_json_success( $formatted_history );
	}

	public function ajax_get_registry() {
		check_ajax_referer( 'eo_tools_cookie_registry_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		$registry = get_option( 'eo_tools_cookie_registry', array() );
		wp_send_json_success( $registry );
	}

	public function ajax_get_scan_history() {
		check_ajax_referer( 'eo_tools_cookie_registry_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$table_scans = $wpdb->prefix . 'eotools_scan';
		
		$scans = $wpdb->get_results( "SELECT * FROM $table_scans ORDER BY id DESC LIMIT 50", ARRAY_A );
		
		// Reformater pour le frontend (qui s'attendait Ã  la structure wp_options)
		$formatted_history = array();
		foreach ( $scans as $scan ) {
			$new_cookies = $scan['new_cookies'] ? json_decode( $scan['new_cookies'], true ) : array();
			$added_names = array();
			if ( is_array( $new_cookies ) ) {
				foreach ( $new_cookies as $c ) {
					if ( isset( $c['cookie']['name'] ) ) {
						$added_names[] = $c['cookie']['name'];
					}
				}
			}

			$formatted_history[] = array(
				'id'         => $scan['id'],
				'ref'        => $scan['ref'],
				'type'       => $scan['type'],
				'date'       => date( 'd/m/Y H:i:s', strtotime( $scan['date_end'] !== '0000-00-00 00:00:00' ? $scan['date_end'] : $scan['date_start'] ) ),
				'status'     => strtoupper( $scan['status'] ),
				'totalFound' => $scan['total_cookies_found'],
				'added'      => count( $added_names ),
				'addedNames' => $added_names,
				'addedCookies' => $new_cookies,
				'validated'  => $scan['requires_validation'] ? false : true,
				'timestamp'  => $scan['id'],
				'batch_id'   => $scan['id'],
				'source'     => $scan['type']
			);
		}

		wp_send_json_success( $formatted_history );
	}

	public function ajax_save_scan_result() {
		check_ajax_referer( 'eo_tools_cookie_registry_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		
		$result_post = isset( $_POST['result'] ) ? wp_unslash( $_POST['result'] ) : '';
		
		if ( is_string( $result_post ) ) {
			$result = json_decode( $result_post, true );
		} else {
			$result = $result_post;
		}
		
		if ( ! is_array( $result ) ) {
			wp_send_json_error( 'Invalid data' );
		}
		
		global $wpdb;
		$table_scans = $wpdb->prefix . 'eotools_scan';
		
		$prefix = 'SC' . current_time('ym') . '-';
		$last_ref = $wpdb->get_var( $wpdb->prepare( "SELECT ref FROM $table_scans WHERE ref LIKE %s ORDER BY id DESC LIMIT 1", $prefix . '%' ) );
		if ( $last_ref ) {
			$num = intval( substr( $last_ref, -5 ) ) + 1;
		} else {
			$num = 1;
		}
		$ref = $prefix . str_pad( $num, 5, '0', STR_PAD_LEFT );

		$new_cookies_json = isset( $result['addedCookies'] ) ? wp_json_encode( $result['addedCookies'] ) : null;
		
		$wpdb->insert( $table_scans, array(
			'ref' => $ref,
			'type' => isset( $result['source'] ) ? $result['source'] : 'quick_scan',
			'status' => 'completed',
			'date_start' => current_time( 'mysql' ),
			'date_end' => current_time( 'mysql' ),
			'total_urls' => 1,
			'scanned_urls' => 1,
			'total_cookies_found' => isset( $result['found'] ) ? intval( $result['found'] ) : 0,
			'requires_validation' => empty( $result['validated'] ) ? 1 : 0,
			'new_cookies' => $new_cookies_json
		) );
		
		wp_send_json_success( array() );
	}

	public function ajax_save_registry() {
		check_ajax_referer( 'eo_tools_cookie_registry_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		
		$registry_post = isset( $_POST['registry'] ) ? wp_unslash( $_POST['registry'] ) : array();
		
		if ( is_string( $registry_post ) ) {
			$registry = json_decode( $registry_post, true );
		} else {
			$registry = $registry_post;
		}
		
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}
		
		update_option( 'eo_tools_cookie_registry', $registry );

		// Log the changes
		$change_log = isset( $_POST['change_log'] ) ? wp_unslash( $_POST['change_log'] ) : array();
		if ( is_array( $change_log ) && ! empty( $change_log ) ) {
			global $wpdb;
			$table_log = $wpdb->prefix . 'eotools_cookie_log';
			$comments_text = implode( ', ', array_map( 'sanitize_text_field', $change_log ) );
			$wpdb->insert(
				$table_log,
				array(
					'consent_id'     => md5( uniqid( '', true ) ),
					'consent_status' => 'VALIDATION ADMIN',
					'comments'       => $comments_text,
					'time'           => current_time( 'mysql' )
				)
			);
		}

		wp_send_json_success( $registry );
	}

	public function ajax_scan_frontend_cookies() {
		check_ajax_referer( 'eo_tools_cookie_registry_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$found_cookies = array();
		$response = wp_remote_get( home_url() );

		if ( is_wp_error( $response ) ) {
			wp_send_json_success( array( 'cookies' => $found_cookies ) );
		}

		// 1. Check headers for Set-Cookie
		$headers = wp_remote_retrieve_headers( $response );
		if ( isset( $headers['set-cookie'] ) ) {
			$set_cookies = (array) $headers['set-cookie'];
			foreach ( $set_cookies as $cookie_header ) {
				$parts = explode( ';', $cookie_header );
				$name_value = explode( '=', $parts[0], 2 );
				if ( ! empty( $name_value[0] ) ) {
					$found_cookies[] = trim( $name_value[0] );
				}
			}
		}

		// 2. Parse HTML body for known scripts to infer third-party cookies
		$body = wp_remote_retrieve_body( $response );
		
		// Add all published posts content and widgets to find embedded scripts on specific pages
		global $wpdb;
		$posts_content = $wpdb->get_col( "SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish'" );
		if ( ! empty( $posts_content ) ) {
			$body .= ' ' . implode( ' ', $posts_content );
		}
		
		$widget_content = $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'widget_%'" );
		if ( ! empty( $widget_content ) ) {
			$body .= ' ' . implode( ' ', $widget_content );
		}

		$inferred_cookies = array();

		// Google Analytics
		if ( strpos( $body, 'google-analytics.com' ) !== false || strpos( $body, 'googletagmanager.com' ) !== false || strpos( $body, 'gtag(' ) !== false ) {
			$inferred_cookies = array_merge( $inferred_cookies, array( '_ga', '_gid', '_gat', '_gcl_au' ) );
		}
		
		// YouTube
		if ( strpos( $body, 'youtube.com/embed' ) !== false || strpos( $body, 'youtube.com/watch' ) !== false || strpos( $body, 'youtu.be' ) !== false || strpos( $body, 'wp:core-embed/youtube' ) !== false ) {
			$inferred_cookies = array_merge( $inferred_cookies, array( 'VISITOR_PRIVACY_METADATA', 'VISITOR_INFO1_LIVE', 'YSC', 'ytidb::LAST_RESULT_ENTRY_KEY', '__Secure-YNID', '__Secure-ROLLOUT_TOKEN', '__Secure-YEC' ) );
		}

		// Cloudflare
		if ( strpos( $body, 'cloudflare.com' ) !== false ) {
			$inferred_cookies = array_merge( $inferred_cookies, array( '__cf_bm', '__cfduid', 'cf_clearance' ) );
		}

		// Calendly
		if ( strpos( $body, 'calendly.com' ) !== false ) {
			$inferred_cookies = array_merge( $inferred_cookies, array( '_cfuvid', '_calendly_session' ) );
		}

		// Hotjar
		if ( strpos( $body, 'hotjar.com' ) !== false ) {
			$inferred_cookies = array_merge( $inferred_cookies, array( '_hjSessionUser_', '_hjSession_', '_hjTLDTest', '_hjFirstSeen' ) );
		}

		// Recaptcha
		if ( strpos( $body, 'google.com/recaptcha' ) !== false ) {
			$inferred_cookies = array_merge( $inferred_cookies, array( 'rc::a', 'rc::c', 'rc::b' ) );
		}

		// Stripe
		if ( strpos( $body, 'js.stripe.com' ) !== false ) {
			$inferred_cookies = array_merge( $inferred_cookies, array( 'm', '__stripe_mid', '__stripe_sid' ) );
		}
		
		// Facebook Pixel
		if ( strpos( $body, 'connect.facebook.net' ) !== false || strpos( $body, 'facebook.com/tr' ) !== false ) {
			$inferred_cookies = array_merge( $inferred_cookies, array( '_fbp', 'fr' ) );
		}

		// OneTrust / Consent
		if ( strpos( $body, 'onetrust.com' ) !== false || strpos( $body, 'optanon' ) !== false ) {
			$inferred_cookies = array_merge( $inferred_cookies, array( 'OptanonConsent', 'OptanonAlertBoxClosed' ) );
		}

		$found_cookies = array_unique( array_merge( $found_cookies, $inferred_cookies ) );

		wp_send_json_success( array( 'cookies' => array_values( $found_cookies ) ) );
	}



	public function add_admin_menu() {
		// Parent top-level menu
		add_menu_page(
			__('EO Tools', 'eo-tools'),
			__('EO Tools', 'eo-tools'),
			'manage_options',
			'eo-tools',
			[ $this, 'main_page_view' ],
			'dashicons-admin-tools',
			81
		);

		// Submenu pointing to Landing Pages manager
		add_submenu_page(
			'eo-tools',
			__('Pages d\'atterrissage', 'eo-tools'),
			__('Pages d\'atterrissage', 'eo-tools'),
			'manage_options',
			'eo-tools-landing-pages',
			[ $this, 'landing_pages_page_view' ]
		);

		// Submenu pointing to Cookie Manager
		add_submenu_page(
			'eo-tools',
			__('Gestion des cookies', 'eo-tools'),
			__('Gestion des cookies', 'eo-tools'),
			'manage_options',
			'eo-tools-cookies',
			[ $this, 'cookies_page_view' ]
		);
	}

	public function main_page_view() {
		echo '<div class="wrap"><h1>' . esc_html__( 'EO Tools', 'eo-tools' ) . '</h1><p>' . esc_html__( 'Bienvenue dans EO Tools. Sélectionnez un outil dans le menu de gauche.', 'eo-tools' ) . '</p></div>';
	}

	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'eo-tools-landing-pages' ) !== false ) {
			wp_enqueue_style( 'eo-tools-landing-pages-admin-css', EO_TOOLS_URL . 'assets/css/landing-pages-admin.css', array(), time() );
			wp_enqueue_script( 'eo-tools-landing-pages-admin-js', EO_TOOLS_URL . 'assets/js/landing-pages-admin.js', array( 'jquery' ), time(), true );

			wp_localize_script( 'eo-tools-landing-pages-admin-js', 'eoToolsLandingPagesAdmin', array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'eo_tools_landing_pages_admin_nonce' ),
				'adminUrl' => admin_url( 'admin.php?page=eo-tools-landing-pages' ),
				'labels'   => array(
					'both'        => __( 'Modes Prochainement & Maintenance actifs', 'eo-tools' ),
					'coming_soon' => __( 'Mode Prochainement actif', 'eo-tools' ),
					'maintenance' => __( 'Mode Maintenance actif', 'eo-tools' ),
				),
			) );
		}

		if ( strpos( $hook, 'eo-tools-cookies' ) !== false ) {
			wp_enqueue_style( 'eo-tools-cookies-admin-css', EO_TOOLS_URL . 'assets/css/cookies-admin.css', array(), time() );
			wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.0.0', true );
			wp_enqueue_script( 'eo-tools-cookies-admin-js', EO_TOOLS_URL . 'assets/js/cookies-admin.js', array( 'jquery', 'wp-i18n', 'chart-js' ), time(), true );
			wp_enqueue_script( 'eo-tools-cookies-detailed-scan-js', EO_TOOLS_URL . 'assets/js/cookies-detailed-scan.js', array( 'jquery' ), time(), true );
			$settings = get_option( 'eo_tools_cookies_settings', array( 'active' => false, 'duration' => 12 ) );
			wp_localize_script( 'eo-tools-cookies-detailed-scan-js', 'eo_tools_admin_vars', array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'eo_tools_detailed_scan_nonce' ),
				'batch_delay' => isset( $settings['batch_delay'] ) && $settings['batch_delay'] !== '' ? intval( $settings['batch_delay'] ) : 200
			) );
			wp_set_script_translations( 'eo-tools-cookies-admin-js', 'eo-tools' );
			wp_localize_script( 'eo-tools-cookies-admin-js', 'eoToolsCookiesAdmin', array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'pluginUrl' => EO_TOOLS_URL,
				'nonce'     => wp_create_nonce( 'eo_tools_cookie_registry_nonce' ),
				'registry'  => get_option( 'eo_tools_cookie_registry', array() ),
			) );
		}
	}

	public function landing_pages_page_view() {
		include EO_TOOLS_PATH . 'includes/admin/views/html-admin-page-landing-pages.php';
	}

	public function cookies_page_view() {
		include EO_TOOLS_PATH . 'includes/admin/views/html-admin-page-cookies.php';
	}
}
