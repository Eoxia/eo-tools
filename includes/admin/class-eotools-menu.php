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
	}

	public function add_admin_menu() {
		// Parent top-level menu
		add_menu_page(
			__('EO Tools', 'eo-tools'),
			__('EO Tools', 'eo-tools'),
			'manage_options',
			'eo-tools-landing-pages',
			[ $this, 'landing_pages_page_view' ],
			'dashicons-admin-tools',
			81
		);

		// Submenu pointing to Landing Pages manager
		add_submenu_page(
			'eo-tools-landing-pages',
			__('Pages d\'atterrissage', 'eo-tools'),
			__('Pages d\'atterrissage', 'eo-tools'),
			'manage_options',
			'eo-tools-landing-pages',
			[ $this, 'landing_pages_page_view' ]
		);
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
	}

	public function landing_pages_page_view() {
		include EO_TOOLS_PATH . 'includes/admin/views/html-admin-page-landing-pages.php';
	}
}
