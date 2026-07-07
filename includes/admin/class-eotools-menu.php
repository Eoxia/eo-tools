<?php
/**
 * Admin settings menu for EO Tools - Landing Pages.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Menu {

	/**
	 * Top-level menu slug.
	 */
	const PARENT_SLUG = 'eo-tools';

	/**
	 * Hook suffix of the admin page (used to scope asset loading).
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the "EO Tools" menu with a "Pages d'atterrissage" submenu.
	 */
	public function add_admin_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'EO Tools', 'eo-tools' ),
			__( 'EO Tools', 'eo-tools' ),
			'manage_options',
			self::PARENT_SLUG,
			array( $this, 'landing_pages_page_view' ),
			'dashicons-admin-tools',
			81
		);

		// Reuse the parent slug so the auto-generated first submenu is relabeled.
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Pages d\'atterrissage', 'eo-tools' ),
			__( 'Pages d\'atterrissage', 'eo-tools' ),
			'manage_options',
			self::PARENT_SLUG,
			array( $this, 'landing_pages_page_view' )
		);
	}

	/**
	 * Enqueue the admin assets only on the plugin page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'eo-tools-landing-pages-admin',
			EO_TOOLS_URL . 'assets/css/landing-pages-admin.css',
			array(),
			EO_TOOLS_VERSION
		);

		wp_enqueue_script(
			'eo-tools-landing-pages-admin',
			EO_TOOLS_URL . 'assets/js/landing-pages-admin.js',
			array( 'jquery' ),
			EO_TOOLS_VERSION,
			true
		);

		wp_localize_script(
			'eo-tools-landing-pages-admin',
			'eoToolsLandingPagesAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'eo_tools_landing_pages_admin_nonce' ),
				'adminUrl' => admin_url( 'admin.php?page=' . self::PARENT_SLUG ),
				'homeUrl'  => home_url(),
				'i18n'     => array(
					'unsaved'        => __( 'Changements non enregistrés', 'eo-tools' ),
					'saving'         => __( 'Enregistrement...', 'eo-tools' ),
					'saved'          => __( 'Enregistré avec succès', 'eo-tools' ),
					'error'          => __( 'Une erreur est survenue', 'eo-tools' ),
					'serverError'    => __( 'Impossible de contacter le serveur.', 'eo-tools' ),
					'stateUpdated'   => __( 'État mis à jour', 'eo-tools' ),
					'active'         => __( 'ACTIF', 'eo-tools' ),
					'inactive'       => __( 'INACTIF', 'eo-tools' ),
					'updating'       => __( 'MAJ...', 'eo-tools' ),
					'accentButton'   => __( 'Couleur du bouton', 'eo-tools' ),
					'accentGradient' => __( 'Couleur de fin du dégradé', 'eo-tools' ),
					'accentGlass'    => __( 'Couleur secondaire (Effet verre)', 'eo-tools' ),
					'toggleError'    => __( 'Erreur lors de la modification de l\'état.', 'eo-tools' ),
					'savedFull'      => __( 'Paramètres enregistrés avec succès !', 'eo-tools' ),
					'configuration'  => __( 'Configuration', 'eo-tools' ),
				),
				'labels'   => array(
					'both'        => __( 'Modes Prochainement & Maintenance actifs', 'eo-tools' ),
					'coming_soon' => __( 'Mode Prochainement actif', 'eo-tools' ),
					'maintenance' => __( 'Mode Maintenance actif', 'eo-tools' ),
				),
			)
		);
	}

	/**
	 * Render the landing pages admin view.
	 */
	public function landing_pages_page_view() {
		include EO_TOOLS_PATH . 'includes/admin/views/html-admin-page-landing-pages.php';
	}
}
