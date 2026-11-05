<?php
/**
 * MCP admin page for EO Tools.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Admin;

use EoTools\Includes\Mcp\Eotools_Mcp_Abilities;
use EoTools\Includes\Mcp\Eotools_Mcp_Adapter;
use EoTools\Includes\Mcp\Eotools_Mcp_Connections;
use EoTools\Includes\Mcp\Eotools_Mcp_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Page {

	const PAGE_SLUG = 'eo-tools-mcp';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_eo_tools_mcp_install_adapter', array( $this, 'ajax_install_adapter' ) );
		add_action( 'wp_ajax_eo_tools_mcp_create_connection', array( $this, 'ajax_create_connection' ) );
		add_action( 'wp_ajax_eo_tools_mcp_get_connections', array( $this, 'ajax_get_connections' ) );
		add_action( 'wp_ajax_eo_tools_mcp_revoke_connection', array( $this, 'ajax_revoke_connection' ) );
		add_action( 'wp_ajax_eo_tools_mcp_toggle_abilities', array( $this, 'ajax_toggle_abilities' ) );
	}

	/**
	 * Returns the supported AI clients.
	 *
	 * - format:   which snippet the admin script shows ("url", "claude-code" or "offline").
	 * - oauth:    the client connects by itself through the OAuth screen (no secret to copy).
	 * - password: the client needs an Application Password ("optional" = advanced fallback).
	 * - steps:    short instructions, displayed as a numbered list.
	 *
	 * @return array[]
	 */
	public static function get_clients() {
		return array(
			'claude'      => array(
				'label'    => __( 'Claude', 'eo-tools' ),
				'subtitle' => __( 'Web, Desktop et mobile', 'eo-tools' ),
				'format'   => 'url',
				'oauth'    => true,
				'password' => false,
				'steps'    => array(
					__( 'Copiez l\'adresse du connecteur ci-dessous.', 'eo-tools' ),
					__( 'Dans Claude, ouvrez Paramètres > Connecteurs > Ajouter un connecteur personnalisé, puis collez l\'adresse.', 'eo-tools' ),
					__( 'Cliquez sur « Se connecter », puis sur « Autoriser » sur la page de votre site.', 'eo-tools' ),
				),
				'link'     => array(
					'url'   => 'https://claude.ai/settings/connectors',
					'label' => __( 'Ouvrir les connecteurs Claude', 'eo-tools' ),
				),
			),
			'chatgpt'     => array(
				'label'    => __( 'ChatGPT', 'eo-tools' ),
				'subtitle' => __( 'Web et Desktop', 'eo-tools' ),
				'format'   => 'url',
				'oauth'    => true,
				'password' => false,
				'steps'    => array(
					__( 'Copiez l\'adresse du connecteur ci-dessous.', 'eo-tools' ),
					__( 'Dans ChatGPT, ouvrez Paramètres > Applications et connecteurs, activez le mode développeur dans les paramètres avancés, puis créez un connecteur avec cette adresse (authentification OAuth).', 'eo-tools' ),
					__( 'Cliquez sur « Se connecter », puis sur « Autoriser » sur la page de votre site.', 'eo-tools' ),
				),
				'link'     => null,
			),
			'claude-code' => array(
				'label'    => __( 'Claude Code', 'eo-tools' ),
				'subtitle' => __( 'Terminal', 'eo-tools' ),
				'format'   => 'claude-code',
				'oauth'    => true,
				'password' => false,
				'steps'    => array(
					__( 'Copiez la commande ci-dessous et lancez-la dans votre terminal.', 'eo-tools' ),
					__( 'Dans Claude Code, tapez /mcp, choisissez le serveur EO Tools puis « Authenticate ».', 'eo-tools' ),
					__( 'Cliquez sur « Autoriser » sur la page de votre site qui s\'ouvre.', 'eo-tools' ),
				),
				'link'     => null,
			),
			'other'       => array(
				'label'    => __( 'Autre client', 'eo-tools' ),
				'subtitle' => __( 'Cursor, VS Code…', 'eo-tools' ),
				'format'   => 'url',
				'oauth'    => true,
				'password' => 'optional',
				'steps'    => array(
					__( 'Ajoutez un serveur MCP « HTTP » dans votre client et collez l\'adresse ci-dessous.', 'eo-tools' ),
					__( 'Si votre client gère la connexion OAuth, il vous envoie sur votre site : cliquez sur « Autoriser ».', 'eo-tools' ),
					__( 'Sinon, générez un mot de passe d\'application avec le bouton ci-dessous et utilisez l\'en-tête « Authorization » fourni.', 'eo-tools' ),
				),
				'link'     => null,
			),
			'offline'     => array(
				'label'    => __( 'Site local', 'eo-tools' ),
				'subtitle' => __( 'Hors ligne', 'eo-tools' ),
				'format'   => 'offline',
				'oauth'    => false,
				'password' => true,
				'steps'    => array(
					__( 'Cliquez sur « Générer ma configuration ».', 'eo-tools' ),
					__( 'Claude Desktop : ouvrez Paramètres > Développeur > Modifier la config, collez le bloc, enregistrez et redémarrez Claude (Node.js doit être installé).', 'eo-tools' ),
					__( 'Claude Code : lancez la commande dans votre terminal.', 'eo-tools' ),
				),
				'link'     => null,
			),
		);
	}

	/**
	 * Client selected by default: claude.ai and ChatGPT cannot reach a local or non-HTTPS site.
	 *
	 * @return string
	 */
	public static function get_recommended_client() {
		return self::is_site_reachable_online() ? 'claude' : 'offline';
	}

	/**
	 * Whether online AI services (claude.ai, ChatGPT) can reach this site.
	 *
	 * @return bool
	 */
	public static function is_site_reachable_online() {
		return ! Eotools_Mcp_Connections::is_local_site() && wp_is_using_https();
	}

	public function add_submenu() {
		add_submenu_page(
			'eo-tools',
			__( 'MCP', 'eo-tools' ),
			__( 'MCP', 'eo-tools' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'page_view' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'eo-tools-mcp-admin-css', EO_TOOLS_URL . 'assets/css/mcp-admin.css', array(), EO_TOOLS_VERSION );
		wp_enqueue_script( 'eo-tools-mcp-admin-js', EO_TOOLS_URL . 'assets/js/mcp-admin.js', array( 'jquery' ), EO_TOOLS_VERSION, true );

		$server_name = sanitize_title( get_bloginfo( 'name' ) );

		$oauth_warning = '';
		if ( ! self::is_site_reachable_online() ) {
			$oauth_warning = __( 'Claude et ChatGPT se connectent depuis Internet : votre site doit être accessible publiquement en HTTPS, ce qui n\'est pas le cas de ce site. Choisissez plutôt « Site local ».', 'eo-tools' );
		}

		wp_localize_script( 'eo-tools-mcp-admin-js', 'eoToolsMcpAdmin', array(
			'ajaxUrl'                 => admin_url( 'admin-ajax.php' ),
			'nonce'                   => wp_create_nonce( 'eo_tools_mcp_admin_nonce' ),
			'serverName'              => 'eo-tools' . ( $server_name ? '-' . $server_name : '' ),
			'endpoint'                => Eotools_Mcp_Server::get_endpoint_url(),
			'clients'                 => self::get_clients(),
			'connections'             => current_user_can( 'manage_options' ) ? Eotools_Mcp_Connections::get_all() : array(),
			'appPasswordsUnavailable' => (string) Eotools_Mcp_Connections::get_unavailable_reason(),
			'oauthWarning'            => $oauth_warning,
			'labels'                  => array(
				'installing'    => __( 'Installation en cours…', 'eo-tools' ),
				'generating'    => __( 'Génération et test de la connexion…', 'eo-tools' ),
				'connected'     => __( 'Votre IA est connectée ! Demandez-lui par exemple : « Liste les dernières pages de mon site ».', 'eo-tools' ),
				'terminal'      => __( 'Terminal', 'eo-tools' ),
				'genConfig'     => __( 'Générer ma configuration', 'eo-tools' ),
				'genPassword'   => __( 'Générer un mot de passe d\'application', 'eo-tools' ),
				'desktopConfig' => __( 'Claude Desktop – claude_desktop_config.json', 'eo-tools' ),
				'codeCommand'   => __( 'Claude Code – commande à lancer', 'eo-tools' ),
				'error'         => __( 'Une erreur est survenue.', 'eo-tools' ),
				'copy'          => __( 'Copier', 'eo-tools' ),
				'copied'        => __( 'Copié !', 'eo-tools' ),
				'revoke'        => __( 'Révoquer', 'eo-tools' ),
				'revokeConfirm' => __( 'Révoquer cette connexion ? L\'IA qui l\'utilise ne pourra plus accéder au site.', 'eo-tools' ),
				'noConnection'  => __( 'Aucune connexion pour le moment.', 'eo-tools' ),
				'endpoint'      => __( 'Adresse du serveur MCP', 'eo-tools' ),
				'connectorUrl'  => __( 'Adresse du connecteur', 'eo-tools' ),
				'username'      => __( 'Identifiant', 'eo-tools' ),
				'password'      => __( 'Mot de passe d\'application', 'eo-tools' ),
				'header'        => __( 'En-tête HTTP', 'eo-tools' ),
				'stdioConfig'   => __( 'Configuration pour un client local (via Node.js)', 'eo-tools' ),
				'saved'         => __( 'Enregistré. Reconnectez votre IA pour qu\'elle voie la nouvelle liste d\'outils.', 'eo-tools' ),
				/* translators: 1: enabled abilities, 2: abilities of the level. */
				'levelCount'    => __( '%1$d / %2$d actives', 'eo-tools' ),
			),
		) );
	}

	/**
	 * Common checks for every MCP admin AJAX request.
	 */
	private function check_ajax_request() {
		check_ajax_referer( 'eo_tools_mcp_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'eo-tools' ) ), 403 );
		}
	}

	public function ajax_install_adapter() {
		$this->check_ajax_request();

		$result = Eotools_Mcp_Adapter::install_and_activate();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'status' => Eotools_Mcp_Adapter::get_status() ) );
	}

	public function ajax_create_connection() {
		$this->check_ajax_request();

		$clients   = self::get_clients();
		$client_id = isset( $_POST['client'] ) ? sanitize_key( wp_unslash( $_POST['client'] ) ) : '';

		// OAuth-only clients connect by themselves, they do not need an Application Password.
		if ( ! isset( $clients[ $client_id ] ) || ! $clients[ $client_id ]['password'] ) {
			wp_send_json_error( array( 'message' => __( 'Client IA inconnu.', 'eo-tools' ) ) );
		}

		if ( Eotools_Mcp_Adapter::STATUS_ACTIVE !== Eotools_Mcp_Adapter::get_status() ) {
			wp_send_json_error( array( 'message' => __( 'Installez et activez d\'abord MCP Adapter.', 'eo-tools' ) ) );
		}

		$connection = Eotools_Mcp_Connections::create( $clients[ $client_id ]['label'] );

		if ( is_wp_error( $connection ) ) {
			wp_send_json_error( array( 'message' => $connection->get_error_message() ) );
		}

		$connection['test']        = Eotools_Mcp_Connections::test( $connection['authorization'] );
		$connection['connections'] = Eotools_Mcp_Connections::get_all();

		wp_send_json_success( $connection );
	}

	public function ajax_get_connections() {
		$this->check_ajax_request();

		wp_send_json_success( array( 'connections' => Eotools_Mcp_Connections::get_all() ) );
	}

	public function ajax_revoke_connection() {
		$this->check_ajax_request();

		$uuid   = isset( $_POST['uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['uuid'] ) ) : '';
		$result = Eotools_Mcp_Connections::revoke( $uuid );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'connections' => Eotools_Mcp_Connections::get_all() ) );
	}

	public function ajax_toggle_abilities() {
		$this->check_ajax_request();

		$names   = isset( $_POST['names'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['names'] ) ) : array();
		$enabled = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );

		Eotools_Mcp_Abilities::set_enabled( $names, $enabled );

		$states = array();
		foreach ( Eotools_Mcp_Abilities::get_all() as $name => $ability ) {
			$states[ $name ] = Eotools_Mcp_Abilities::is_enabled( $ability );
		}

		wp_send_json_success( array( 'states' => $states ) );
	}

	public function page_view() {
		include EO_TOOLS_PATH . 'includes/admin/views/html-admin-page-mcp.php';
	}
}
