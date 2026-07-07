<?php
/**
 * Core plugin bootstrap.
 *
 * Handles the public interception logic for the landing pages
 * (Coming Soon, Maintenance and 404).
 *
 * @package EoTools
 */

namespace EoTools\Includes;

use EoTools\Includes\Admin\Eotools_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools {

	private static $initiated = false;

	/**
	 * The landing page types handled by the plugin.
	 *
	 * @var string[]
	 */
	private $types = array( 'coming_soon', 'maintenance', '404' );

	public function __construct() {
		if ( is_admin() ) {
			new Eotools_Menu();
		}

		if ( ! self::$initiated ) {
			$this->init_hooks();
		}
	}

	public function init_hooks() {
		self::$initiated = true;

		// Landing pages front-end interception.
		add_action( 'template_redirect', array( $this, 'intercept_frontend' ) );
		add_action( 'template_redirect', array( $this, 'intercept_404' ) );

		// Admin bar status badge.
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_badge' ), 999 );
		add_action( 'admin_head', array( $this, 'enqueue_admin_bar_styles' ) );
		add_action( 'wp_head', array( $this, 'enqueue_admin_bar_styles' ) );
	}

	/**
	 * Intercept front-end requests for the Maintenance or Coming Soon modes.
	 */
	public function intercept_frontend() {
		// Administrator preview (from the admin bar / editor buttons).
		if ( current_user_can( 'manage_options' ) && isset( $_GET['eo_preview_landing_page'] ) ) {
			$type  = sanitize_key( wp_unslash( $_GET['eo_preview_landing_page'] ) );
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( in_array( $type, $this->types, true )
				&& wp_verify_nonce( $nonce, 'eo_preview_landing_page_' . $type ) ) {
				$this->render_landing_page( $type );
				exit;
			}
		}

		// Administrators bypass Maintenance and Coming Soon.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// Never intercept the admin panel or the login/register screens.
		if ( is_admin() || in_array( $GLOBALS['pagenow'] ?? '', array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			return;
		}

		$settings           = get_option( 'eo_tools_landing_pages_settings', array() );
		$coming_soon_active = ! empty( $settings['coming_soon']['active'] );
		$maintenance_active = ! empty( $settings['maintenance']['active'] );

		// Maintenance has priority over Coming Soon.
		if ( $maintenance_active ) {
			status_header( 503 );
			$this->render_landing_page( 'maintenance' );
			exit;
		} elseif ( $coming_soon_active ) {
			$this->render_landing_page( 'coming_soon' );
			exit;
		}
	}

	/**
	 * Intercept 404 requests to display the custom 404 page.
	 */
	public function intercept_404() {
		if ( ! is_404() ) {
			return;
		}

		$settings = get_option( 'eo_tools_landing_pages_settings', array() );
		if ( ! empty( $settings['404']['active'] ) ) {
			status_header( 404 );
			$this->render_landing_page( '404' );
			exit;
		}
	}

	/**
	 * Default configuration for each landing page type.
	 *
	 * @return array[]
	 */
	private function get_defaults() {
		return array(
			'coming_soon' => array(
				'title'        => __( 'Bientôt disponible', 'eo-tools' ),
				'description'  => __( 'Notre nouveau site est en cours de création. Restez à l\'écoute !', 'eo-tools' ),
				'style'        => 'minimalist',
				'bg_color'     => '#0f172a',
				'text_color'   => '#f8fafc',
				'accent_color' => '#f59e0b',
			),
			'maintenance' => array(
				'title'        => __( 'Site en maintenance', 'eo-tools' ),
				'description'  => __( 'Nous effectuons actuellement des opérations de maintenance. Nous serons de retour très rapidement.', 'eo-tools' ),
				'style'        => 'gradient',
				'bg_color'     => '#1e1b4b',
				'text_color'   => '#f8fafc',
				'accent_color' => '#6366f1',
			),
			'404'         => array(
				'title'        => __( 'Page non trouvée', 'eo-tools' ),
				'description'  => __( 'Désolé, la page que vous recherchez n\'existe pas ou a été déplacée.', 'eo-tools' ),
				'style'        => 'minimalist',
				'bg_color'     => '#0f172a',
				'text_color'   => '#f8fafc',
				'accent_color' => '#3b82f6',
			),
		);
	}

	/**
	 * Render the custom landing page template.
	 *
	 * @param string $type Page type: coming_soon, maintenance or 404.
	 */
	public function render_landing_page( $type ) {
		$defaults = $this->get_defaults();

		if ( ! isset( $defaults[ $type ] ) ) {
			return;
		}

		$settings_all  = get_option( 'eo_tools_landing_pages_settings', array() );
		$page_settings = isset( $settings_all[ $type ] )
			? array_merge( $defaults[ $type ], $settings_all[ $type ] )
			: $defaults[ $type ];

		// Disable caching for these dynamic screens.
		nocache_headers();

		include EO_TOOLS_PATH . 'includes/templates/landing-page-template.php';
	}

	/**
	 * Display an alert badge in the admin bar when a mode is active.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public function add_admin_bar_badge( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings           = get_option( 'eo_tools_landing_pages_settings', array() );
		$coming_soon_active = ! empty( $settings['coming_soon']['active'] );
		$maintenance_active = ! empty( $settings['maintenance']['active'] );

		if ( ! $coming_soon_active && ! $maintenance_active ) {
			return;
		}

		$badge_class = 'eo-landing-pages-alert-badge';

		if ( $coming_soon_active && $maintenance_active ) {
			$label        = __( 'Modes Prochainement & Maintenance actifs', 'eo-tools' );
			$badge_class .= ' eo-alert-red';
		} elseif ( $coming_soon_active ) {
			$label        = __( 'Mode Prochainement actif', 'eo-tools' );
			$badge_class .= ' eo-alert-orange';
		} else {
			$label        = __( 'Mode Maintenance actif', 'eo-tools' );
			$badge_class .= ' eo-alert-red';
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'eo-landing-pages-status',
				'parent' => 'top-secondary',
				'title'  => esc_html( $label ),
				'href'   => admin_url( 'admin.php?page=eo-tools' ),
				'meta'   => array(
					'title' => $label,
					'class' => $badge_class,
				),
			)
		);
	}

	/**
	 * Output the admin bar badge styles.
	 */
	public function enqueue_admin_bar_styles() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$css = '
			#wpadminbar .eo-landing-pages-alert-badge > .ab-item {
				color: #ffffff !important;
				font-weight: bold !important;
				border-radius: 4px !important;
				margin-top: 4px !important;
				height: 24px !important;
				line-height: 24px !important;
				padding: 0 10px !important;
				display: inline-block !important;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
				transition: background-color 0.25s ease, transform 0.15s ease !important;
			}
			#wpadminbar .eo-landing-pages-alert-badge:hover > .ab-item {
				transform: scale(1.05);
			}
			#wpadminbar .eo-landing-pages-alert-badge.eo-alert-red > .ab-item {
				background-color: #d63638 !important;
				animation: eo-red-pulse 2s infinite;
			}
			#wpadminbar .eo-landing-pages-alert-badge.eo-alert-red:hover > .ab-item {
				background-color: #b32424 !important;
			}
			#wpadminbar .eo-landing-pages-alert-badge.eo-alert-orange > .ab-item {
				background-color: #f59e0b !important;
				animation: eo-orange-pulse 2s infinite;
			}
			#wpadminbar .eo-landing-pages-alert-badge.eo-alert-orange:hover > .ab-item {
				background-color: #d97706 !important;
			}
			@keyframes eo-red-pulse {
				0% { box-shadow: 0 0 0 0 rgba(214, 54, 56, 0.7); }
				70% { box-shadow: 0 0 0 6px rgba(214, 54, 56, 0); }
				100% { box-shadow: 0 0 0 0 rgba(214, 54, 56, 0); }
			}
			@keyframes eo-orange-pulse {
				0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
				70% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
				100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
			}
		';

		echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline CSS.
	}
}
