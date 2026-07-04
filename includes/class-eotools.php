<?php
namespace EoTools\Includes;

use EoTools\Includes\Admin\Eotools_Menu;

if (!defined('ABSPATH')) {
	exit;
}

class Eotools {
	private static $initiated = false;

	public function __construct() {
		$eotools_menu = new Eotools_Menu();

		if ( ! self::$initiated ) {
			$this->init_hooks();
		}
	}

	public function init_hooks() {
		self::$initiated = true;

		add_action( 'init', array( $this, 'eo_tools_create_tables' ) );
		
		// Cookie Interceptor
		\EoTools\Includes\Eotools_Cookie_Interceptor::init();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		
		// Landing pages hooks
		add_action( 'template_redirect', array( $this, 'intercept_frontend' ) );
		add_action( 'template_redirect', array( $this, 'intercept_404' ) );
		add_action( 'login_init', array( $this, 'intercept_login' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_badge' ), 999 );
		add_action( 'wp_login_failed', array( $this, 'login_failed_redirect' ) );
		add_action( 'wp_login_failed', array( $this, 'log_login_failed' ), 10, 2 );
		add_action( 'wp_login', array( $this, 'log_login_success' ), 10, 2 );
		add_filter( 'authenticate', array( $this, 'check_email_login_filter' ), 25, 3 );
		add_action( 'admin_head', array( $this, 'enqueue_admin_bar_styles' ) );
		add_action( 'wp_head', array( $this, 'enqueue_admin_bar_styles' ) );
	}

	public function enqueue_frontend_scripts() {
		$settings = get_option( 'eo_tools_cookies_settings', array( 'active' => false, 'duration' => 12 ) );
		if ( ! empty( $settings['active'] ) ) {
			wp_enqueue_style( 'eo-tools-cookies', EO_TOOLS_URL . 'assets/css/eo-tools-cookies.css', array(), EO_TOOLS_VERSION );
			wp_enqueue_script( 'eo-tools-cookies', EO_TOOLS_URL . 'assets/js/eo-tools-cookies.js', array( 'wp-i18n' ), EO_TOOLS_VERSION, true );
			wp_set_script_translations( 'eo-tools-cookies', 'eo-tools' );
			
			$registry = get_option( 'eo_tools_cookie_registry', array() );
			
			wp_localize_script( 'eo-tools-cookies', 'eoToolsCookieData', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'eo_tools_cookie_nonce' ),
				'durationDays' => intval( $settings['duration'] ) * 30,
				'iconFull'    => ! empty( $settings['icon_full'] ) ? $settings['icon_full'] : '',
				'iconPartial' => ! empty( $settings['icon_partial'] ) ? $settings['icon_partial'] : '',
				'cookieRegistry' => $registry
			) );
		}
	}

	/**
	 * Intercept frontend requests for Maintenance or Coming Soon modes
	 */
	public function intercept_frontend() {
		// Admin bar preview check first
		if ( current_user_can( 'manage_options' ) && isset( $_GET['eo_preview_landing_page'] ) ) {
			$type = sanitize_text_field( $_GET['eo_preview_landing_page'] );
			if ( in_array( $type, array( 'coming_soon', 'maintenance', 'login', 'register', '404' ) ) ) {
				$this->render_landing_page( $type );
				exit;
			}
		}

		// Administrators bypass maintenance and coming soon
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't intercept admin panel or standard login actions
		if ( is_admin() || in_array( $GLOBALS['pagenow'] ?? '', array( 'wp-login.php', 'wp-register.php' ) ) ) {
			return;
		}

		$settings = get_option( 'eo_tools_landing_pages_settings', array() );
		$coming_soon_active = !empty( $settings['coming_soon']['active'] );
		$maintenance_active = !empty( $settings['maintenance']['active'] );

		// Maintenance has priority
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
	 * Intercept 404 requests to display custom 404 page
	 */
	public function intercept_404() {
		if ( is_404() ) {
			$settings = get_option( 'eo_tools_landing_pages_settings', array() );
			$status_404_active = !empty( $settings['404']['active'] );
			if ( $status_404_active ) {
				status_header( 404 );
				$this->render_landing_page( '404' );
				exit;
			}
		}
	}

	public function intercept_login() {
		if ( is_user_logged_in() || ! empty( $_REQUEST['interim-login'] ) ) {
			return;
		}

		$settings = get_option( 'eo_tools_landing_pages_settings', array() );
		$login_active = !empty( $settings['login']['active'] );
		$register_active = !empty( $settings['register']['active'] );

		$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'login';

		if ( 'login' === $action && $login_active ) {
			// Check IP access restriction first
			$ip_rules = $settings['login']['ip_rules'] ?? array();
			if ( ! $this->check_ip_access( $ip_rules ) ) {
				$this->log_login_attempt( '', 'blocked_ip' );
				status_header( 403 );
				wp_die( __( 'Accès refusé. Votre adresse IP n\'est pas autorisée à se connecter.', 'eo-tools' ), __( 'Accès Refusé', 'eo-tools' ), array( 'response' => 403 ) );
			}

			if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
				$this->render_landing_page( 'login' );
				exit;
			}
		}

		if ( 'register' === $action && $register_active ) {
			// Check IP access restriction for register
			$inherit = !empty( $settings['register']['inherit_login_rules'] );
			$ip_rules = $inherit ? ( $settings['login']['ip_rules'] ?? array() ) : ( $settings['register']['ip_rules'] ?? array() );
			
			if ( ! $this->check_ip_access( $ip_rules ) ) {
				status_header( 403 );
				wp_die( __( 'Accès refusé. Votre adresse IP n\'est pas autorisée à s\'inscrire.', 'eo-tools' ), __( 'Accès Refusé', 'eo-tools' ), array( 'response' => 403 ) );
			}

			if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
				$this->render_landing_page( 'register' );
				exit;
			}
			
			if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['user_email'] ) ) {
				$this->process_custom_registration( $settings );
				exit;
			}
		}
	}

	private function process_custom_registration( $settings ) {
		$email = sanitize_email( wp_unslash( $_POST['user_email'] ) );

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_redirect( add_query_arg( 'register_error', 'invalid_email', wp_login_url() . '?action=register' ) );
			exit;
		}

		// Email filtering check
		$inherit = !empty( $settings['register']['inherit_login_rules'] );
		$email_filtering_active = $inherit ? !empty( $settings['login']['email_filtering_active'] ) : !empty( $settings['register']['email_filtering_active'] );
		$email_rules = $inherit ? ( $settings['login']['email_rules'] ?? '' ) : ( $settings['register']['email_rules'] ?? '' );

		if ( $email_filtering_active ) {
			if ( ! $this->is_email_allowed_php( $email, $email_rules ) ) {
				wp_redirect( add_query_arg( 'register_error', 'email_not_allowed', wp_login_url() . '?action=register' ) );
				exit;
			}
		}

		if ( email_exists( $email ) ) {
			wp_redirect( add_query_arg( 'register_error', 'email_exists', wp_login_url() . '?action=register' ) );
			exit;
		}

		// Generate username from email
		$parts = explode( '@', $email );
		$base_username = sanitize_user( current( $parts ), true );
		if ( empty( $base_username ) ) {
			$base_username = 'user';
		}

		$username = $base_username;
		$i = 1;
		while ( username_exists( $username ) ) {
			$username = $base_username . $i;
			$i++;
		}

		$errors = register_new_user( $username, $email );

		if ( is_wp_error( $errors ) ) {
			wp_redirect( add_query_arg( 'register_error', 'registration_failed', wp_login_url() . '?action=register' ) );
			exit;
		}

		wp_redirect( add_query_arg( 'checkemail', 'registered', wp_login_url() ) );
		exit;
	}

	/**
	 * Render the custom landing page template
	 * @param string $type Page type: coming_soon, maintenance, login, 404
	 */
	public function render_landing_page( $type ) {
		$settings_all = get_option( 'eo_tools_landing_pages_settings', array() );
		
		// Load default values if not configured
		$defaults = array(
			'coming_soon' => array(
				'title'       => __( 'Bientôt disponible', 'eo-tools' ),
				'description' => __( 'Notre nouveau site est en cours de création. Restez à l\'écoute !', 'eo-tools' ),
				'style'       => 'minimalist',
				'bg_color'    => '#0f172a',
				'text_color'  => '#f8fafc',
				'accent_color'=> '#f59e0b',
			),
			'maintenance' => array(
				'title'       => __( 'Site en maintenance', 'eo-tools' ),
				'description' => __( 'Nous effectuons actuellement des opérations de maintenance. Nous serons de retour très rapidement.', 'eo-tools' ),
				'style'       => 'gradient',
				'bg_color'    => '#1e1b4b',
				'text_color'  => '#f8fafc',
				'accent_color'=> '#6366f1',
			),
			'login' => array(
				'title'       => __( 'Connexion', 'eo-tools' ),
				'description' => __( 'Veuillez vous connecter pour accéder au site.', 'eo-tools' ),
				'style'       => 'glassmorphism',
				'bg_color'    => '#0f172a',
				'text_color'  => '#f8fafc',
				'accent_color'=> '#06b6d4',
			),
			'register' => array(
				'title'       => __( 'Inscription', 'eo-tools' ),
				'description' => __( 'Créez votre compte pour accéder à nos services.', 'eo-tools' ),
				'style'       => 'glassmorphism',
				'bg_color'    => '#0f172a',
				'text_color'  => '#f8fafc',
				'accent_color'=> '#10b981',
			),
			'404' => array(
				'title'       => __( 'Page non trouvée', 'eo-tools' ),
				'description' => __( 'Désolé, la page que vous recherchez n\'existe pas ou a été déplacée.', 'eo-tools' ),
				'style'       => 'minimalist',
				'bg_color'    => '#0f172a',
				'text_color'  => '#f8fafc',
				'accent_color'=> '#3b82f6',
			),
		);

		$page_settings = isset( $settings_all[$type] ) ? array_merge( $defaults[$type], $settings_all[$type] ) : $defaults[$type];

		// Disable caching
		nocache_headers();

		include EO_TOOLS_PATH . '/includes/templates/landing-page-template.php';
	}
	public function add_admin_bar_badge( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( 'eo_tools_landing_pages_settings', array() );
		$coming_soon_active = !empty( $settings['coming_soon']['active'] );
		$maintenance_active = !empty( $settings['maintenance']['active'] );

		if ( $coming_soon_active || $maintenance_active ) {
			$label = '';
			$badge_class = 'eo-landing-pages-alert-badge';

			if ( $coming_soon_active && $maintenance_active ) {
				$label = __( 'Modes Prochainement & Maintenance actifs', 'eo-tools' );
				$badge_class .= ' eo-alert-red';
			} elseif ( $coming_soon_active ) {
				$label = __( 'Mode Prochainement actif', 'eo-tools' );
				$badge_class .= ' eo-alert-orange';
			} else {
				$label = __( 'Mode Maintenance actif', 'eo-tools' );
				$badge_class .= ' eo-alert-red';
			}

			$wp_admin_bar->add_node( array(
				'id'     => 'eo-landing-pages-status',
				'parent' => 'top-secondary',
				'title'  => esc_html( $label ),
				'href'   => admin_url( 'admin.php?page=eo-blocks-landing-pages' ),
				'meta'   => array(
					'title' => $label,
					'class' => $badge_class,
				),
			) );
		}
	}

	/**
	 * Redirect failed login attempts back to custom login page with error arg
	 */
	public function login_failed_redirect( $username ) {
		$settings = get_option( 'eo_tools_landing_pages_settings', array() );
		$login_active = !empty( $settings['login']['active'] );

		if ( $login_active ) {
			$referrer = wp_get_referer();
			if ( $referrer && strpos( $referrer, 'wp-login.php' ) !== false ) {
				// Redirect back with login_error flag
				wp_redirect( add_query_arg( 'login_error', '1', $referrer ) );
				exit;
			}
		}
	}

	/**
	 * Output admin bar badge styles in head
	 */
	public function enqueue_admin_bar_styles() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<style>
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
				#wpadminbar .eo-landing-pages-alert-badge.eo-alert-blue > .ab-item {
					background-color: #3b82f6 !important;
					animation: eo-blue-pulse 2s infinite;
				}
				#wpadminbar .eo-landing-pages-alert-badge.eo-alert-blue:hover > .ab-item {
					background-color: #1d4ed8 !important;
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
				@keyframes eo-blue-pulse {
					0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); }
					70% { box-shadow: 0 0 0 6px rgba(59, 130, 246, 0); }
					100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
				}
			</style>';
	}

	/**
	 * Create Custom Tables according to WordPress standards
	 */
	public function eo_tools_create_tables() {
		global $wpdb;
		$table_login = $wpdb->prefix . 'eo_login_attempts';
		$table_cookies = $wpdb->prefix . 'eotools_cookie_stats';
		$table_log = $wpdb->prefix . 'eotools_cookie_log';
		$db_version = get_option( 'eo_tools_db_version', '0' );
		
		if ( version_compare( $db_version, '1.2.0', '<' ) || $wpdb->get_var( "SHOW TABLES LIKE '$table_login'" ) !== $table_login ) {
			$charset_collate = $wpdb->get_charset_collate();
			
			$sql = "CREATE TABLE $table_login (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				ip varchar(100) NOT NULL,
				username varchar(255) NOT NULL,
				status varchar(50) NOT NULL,
				user_agent text NOT NULL,
				PRIMARY KEY  (id),
				KEY ip (ip),
				KEY time (time)
			) $charset_collate;
			CREATE TABLE $table_cookies (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				stat_date date NOT NULL,
				views int(11) DEFAULT 0 NOT NULL,
				accepts int(11) DEFAULT 0 NOT NULL,
				refusals int(11) DEFAULT 0 NOT NULL,
				customs int(11) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY stat_date (stat_date)
			) $charset_collate;
			CREATE TABLE $table_log (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				consent_id varchar(100) NOT NULL,
				consent_status varchar(50) NOT NULL,
				comments text NULL,
				time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				PRIMARY KEY  (id),
				KEY consent_id (consent_id),
				KEY time (time)
			) $charset_collate;";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			update_option( 'eo_tools_db_version', '1.2.0' );
		}
	}

	/**
	 * Log a login attempt in the database
	 */
	public function log_login_attempt( $username, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'eo_login_attempts';
		
		// Ensure the table exists
		$this->eo_tools_create_tables();

		$ip = $this->get_visitor_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

		$wpdb->insert(
			$table_name,
			array(
				'ip'         => $ip ? $ip : '0.0.0.0',
				'username'   => sanitize_text_field( $username ),
				'status'     => sanitize_text_field( $status ),
				'user_agent' => $user_agent,
				'time'       => current_time( 'mysql' ),
			)
		);

		$this->eo_tools_purge_login_attempts();
	}

	/**
	 * Purge oldest logs according to configured limit
	 */
	public function eo_tools_purge_login_attempts() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'eo_login_attempts';
		
		$settings = get_option( 'eo_tools_landing_pages_settings', array() );
		$limit = isset( $settings['login']['log_limit'] ) ? intval( $settings['login']['log_limit'] ) : 1000;
		
		if ( $limit <= 0 ) {
			return;
		}

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		if ( $count > $limit ) {
			$offset = $count - $limit;
			$boundary_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name ORDER BY id ASC LIMIT 1 OFFSET %d", $offset - 1 ) );
			if ( $boundary_id ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id <= %d", $boundary_id ) );
			}
		}
	}

	/**
	 * Get visitor IP address
	 */
	private function get_visitor_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			$ips = explode( ',', $ip );
			$ip = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Check if IP matches CIDR block or single IP
	 */
	private function ip_matches_cidr( $ip, $cidr ) {
		$cidr = trim( $cidr );
		if ( strpos( $cidr, '/' ) === false ) {
			return $ip === $cidr;
		}

		list( $subnet, $mask ) = explode( '/', $cidr );
		$mask = intval( $mask );

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$ip_long = ip2long( $ip );
			$subnet_long = ip2long( $subnet );
			$mask_dec = ~ ( ( 1 << ( 32 - $mask ) ) - 1 );
			return ( $ip_long & $mask_dec ) === ( $subnet_long & $mask_dec );
		}

		return false;
	}

	/**
	 * Verify if visitor IP is authorized
	 */
	private function check_ip_access( $ip_rules ) {
		if ( empty( $ip_rules ) ) {
			return true;
		}

		$visitor_ip = $this->get_visitor_ip();
		if ( ! $visitor_ip ) {
			return true;
		}

		$has_allow_rules = false;
		$ip_allowed = false;

		foreach ( $ip_rules as $rule ) {
			$rule_ip = $rule['ip'];
			$action = $rule['action'];

			if ( 'allow' === $action ) {
				$has_allow_rules = true;
			}

			if ( $this->ip_matches_cidr( $visitor_ip, $rule_ip ) ) {
				if ( 'block' === $action ) {
					return false;
				} elseif ( 'allow' === $action ) {
					$ip_allowed = true;
				}
			}
		}

		if ( $has_allow_rules ) {
			return $ip_allowed;
		}

		return true;
	}

	/**
	 * Hook on authenticate filter to check if email is authorized
	 */
	public function check_email_login_filter( $user, $username, $password ) {
		$settings = get_option( 'eo_tools_landing_pages_settings', array() );
		$login_active = !empty( $settings['login']['active'] );
		$email_filtering_active = !empty( $settings['login']['email_filtering_active'] );

		if ( $login_active && $email_filtering_active && ! empty( $username ) ) {
			$email_rules = $settings['login']['email_rules'] ?? '';
			if ( ! $this->is_email_allowed_php( $username, $email_rules ) ) {
				$this->log_login_attempt( $username, 'blocked_email' );
				return new \WP_Error( 'email_not_allowed', __( 'Cette adresse e-mail n\'est pas autorisée à se connecter sur ce site.', 'eo-tools' ) );
			}
		}
		return $user;
	}

	/**
	 * Convert wildcard/domain rule to regular expression in PHP
	 */
	private function rule_to_regex( $rule ) {
		if ( strpos( $rule, '!' ) === 0 ) {
			$rule = substr( $rule, 1 );
		}
		$rule = trim( $rule );

		if ( strpos( $rule, '*' ) !== false ) {
			$escaped = preg_quote( $rule, '/' );
			$pattern = str_replace( '\\*', '.*', $escaped );
			return '/^' . $pattern . '$/i';
		}

		if ( strpos( $rule, '@' ) === 0 ) {
			$domain_rule = substr( $rule, 1 );
			if ( strpos( $domain_rule, '.' ) !== false ) {
				return '/@' . preg_quote( $domain_rule, '/' ) . '$/i';
			} else {
				return '/@' . preg_quote( $domain_rule, '/' ) . '(\..+)?$/i';
			}
		}

		return '/^' . preg_quote( $rule, '/' ) . '$/i';
	}

	/**
	 * Verify if email matches allowed domain rules in PHP
	 */
	private function is_email_allowed_php( $email, $rules_str ) {
		if ( empty( $rules_str ) ) {
			return true;
		}

		$email = strtolower( trim( $email ) );
		if ( strpos( $email, '@' ) === false ) {
			return false;
		}

		$rules = array_filter( array_map( 'trim', explode( ',', $rules_str ) ) );
		
		$has_allow_rules = false;
		$email_allowed = false;

		foreach ( $rules as $rule ) {
			if ( empty( $rule ) ) {
				continue;
			}

			$is_block_rule = ( strpos( $rule, '!' ) === 0 );
			if ( ! $is_block_rule ) {
				$has_allow_rules = true;
			}

			$regex = $this->rule_to_regex( $rule );
			if ( preg_match( $regex, $email ) ) {
				if ( $is_block_rule ) {
					return false;
				} else {
					$email_allowed = true;
				}
			}
		}

		if ( $has_allow_rules ) {
			return $email_allowed;
		}

		return true;
	}

	/**
	 * Log successful connection
	 */
	public function log_login_success( $user_login, $user ) {
		$this->log_login_attempt( $user_login, 'success' );
	}

	/**
	 * Log failed connection
	 */
	public function log_login_failed( $username, $error = null ) {
		if ( is_wp_error( $error ) && 'email_not_allowed' === $error->get_error_code() ) {
			return; // Avoid duplicate logging (already logged in authenticate)
		}
		$this->log_login_attempt( $username, 'failed' );
	}

}
