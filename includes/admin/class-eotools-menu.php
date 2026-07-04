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
		
		$timestamp = isset( $_POST['timestamp'] ) ? floatval( $_POST['timestamp'] ) : 0;
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$names = isset( $_POST['names'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['names'] ) ) : array();
		
		if ( ! $timestamp && empty( $date ) ) {
			wp_send_json_error( 'Missing timestamp or date' );
		}
		
		// 1. Update the scan in history to validated
		$history = get_option( 'eo_tools_scan_history', array() );
		$updated = false;
		$target_scan = null;
		
		foreach ( $history as &$scan ) {
			if ( $timestamp && isset( $scan['timestamp'] ) && floatval( $scan['timestamp'] ) === $timestamp ) {
				$scan['validated'] = true;
				$scan['validatedDate'] = current_time( 'Y-m-d H:i:s' );
				$updated = true;
				$target_scan = $scan;
				break;
			} elseif ( ! empty( $date ) && isset( $scan['date'] ) && $scan['date'] === $date ) {
				$scan['validated'] = true;
				$scan['validatedDate'] = current_time( 'Y-m-d H:i:s' );
				$updated = true;
				$target_scan = $scan;
				break;
			}
		}
		
		// Auto-validate other scans with the exact same modifications
		if ( $target_scan ) {
			$del = isset( $target_scan['deletedNames'] ) ? $target_scan['deletedNames'] : array();
			$add = isset( $target_scan['addedNames'] ) ? $target_scan['addedNames'] : array();
			
			foreach ( $history as &$scan ) {
				if ( empty( $scan['validated'] ) ) {
					$s_del = isset( $scan['deletedNames'] ) ? $scan['deletedNames'] : array();
					$s_add = isset( $scan['addedNames'] ) ? $scan['addedNames'] : array();
					
					if ( $s_del === $del && $s_add === $add ) {
						$scan['validated'] = true;
						$scan['validatedDate'] = current_time( 'Y-m-d H:i:s' );
						$updated = true;
					}
				}
			}
		}

		if ( $updated ) {
			update_option( 'eo_tools_scan_history', $history );
		}
		
		// 2. Insert into log table
		global $wpdb;
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
		
		wp_send_json_success( $history );
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
		$history = get_option( 'eo_tools_scan_history', array() );
		wp_send_json_success( $history );
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
		
		$history = get_option( 'eo_tools_scan_history', array() );
		array_unshift( $history, $result );
		
		// Keep last 20
		$history = array_slice( $history, 0, 20 );
		
		update_option( 'eo_tools_scan_history', $history );
		wp_send_json_success( $history );
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
