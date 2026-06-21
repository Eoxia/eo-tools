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
	}

	public function ajax_get_registry() {
		check_ajax_referer( 'eo_tools_cookie_registry_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		$registry = get_option( 'eo_tools_cookie_registry', array() );
		wp_send_json_success( $registry );
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
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'eo_tools_cookie_registry_nonce' )
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
