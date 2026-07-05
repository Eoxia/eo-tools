<?php
/**
 * API pour le scan dÃ©taillÃ© des cookies
 * 
 * @package Eo_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EoTools_Detailed_Scan {

	public function __construct() {
		add_action( 'wp_ajax_eo_tools_start_detailed_scan', array( $this, 'ajax_start_detailed_scan' ) );
		add_action( 'wp_ajax_eo_tools_process_scan_batch', array( $this, 'ajax_process_scan_batch' ) );
		add_action( 'wp_ajax_eo_tools_get_detailed_scan_results', array( $this, 'ajax_get_detailed_scan_results' ) );

	}

	/**
	 * Initialise le scan dÃ©taillÃ© et retourne les statistiques des Ã©lÃ©ments Ã  scanner
	 */
	public function ajax_start_detailed_scan() {
		check_ajax_referer( 'eo_tools_detailed_scan_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;

		$table_scans = $wpdb->prefix . 'eotools_scan';
		$table_scan_lines = $wpdb->prefix . 'eotools_scan_lines';
		
		// Generation de la reference type SC2605-00001
		$prefix = 'SC' . current_time('ym') . '-';
		$last_ref = $wpdb->get_var( $wpdb->prepare( "SELECT ref FROM $table_scans WHERE ref LIKE %s ORDER BY id DESC LIMIT 1", $prefix . '%' ) );
		if ( $last_ref ) {
			$num = intval( substr( $last_ref, -5 ) ) + 1;
		} else {
			$num = 1;
		}
		$ref = $prefix . str_pad( $num, 5, '0', STR_PAD_LEFT );

		// 2. Compter les elements
		$counts = array(
			'posts' => $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'" ),
			'pages' => $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'" ),
			'attachments' => $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment'" ),
			'cpts' => $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type NOT IN ('post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'eo_landing_page') AND post_status = 'publish'" ),
			'headers_footers' => 2 // Placeholder pour le header/footer
		);
		$total = array_sum( $counts );

		// Insertion de l'entete
		$wpdb->insert( $table_scans, array(
			'ref' => $ref,
			'type' => 'detailed',
			'status' => 'pending',
			'date_start' => current_time( 'mysql' ),
			'total_urls' => $total
		) );
		$scan_id = $wpdb->insert_id;

		// 3. Peupler la file d'attente
		$wpdb->insert( $table_scan_lines, array( 'scan_id' => $scan_id, 'item_type' => 'header', 'item_id' => 0, 'url' => home_url(), 'status' => 'pending' ) );
		$wpdb->insert( $table_scan_lines, array( 'scan_id' => $scan_id, 'item_type' => 'footer', 'item_id' => 0, 'url' => home_url(), 'status' => 'pending' ) );

		$posts = $wpdb->get_results( "SELECT ID, post_type FROM {$wpdb->posts} WHERE (post_status = 'publish' OR (post_type = 'attachment' AND post_status = 'inherit')) AND post_type NOT IN ('revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'eo_landing_page')" );
		foreach ( $posts as $p ) {
			$url = get_permalink( $p->ID );
			if ( $url ) {
				$wpdb->insert( $table_scan_lines, array( 'scan_id' => $scan_id,
					'item_type' => $p->post_type,
					'item_id'   => $p->ID,
					'url'       => $url,
					'status'    => 'pending'
				) );
			}
		}

		wp_send_json_success( array(
			'counts'   => $counts,
			'scan_id' => $scan_id,
			'total'    => $total
		) );
	}

	/**
	 * Traite un lot d'URLs
	 */
	public function ajax_process_scan_batch() {
		check_ajax_referer( 'eo_tools_detailed_scan_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$table_scans = $wpdb->prefix . 'eotools_scan';
		$table_scan_lines = $wpdb->prefix . 'eotools_scan_lines';
		$settings = get_option( 'eo_tools_cookies_settings', array() );
		$batch_size = isset( $settings['batch_size'] ) && $settings['batch_size'] !== '' ? intval( $settings['batch_size'] ) : 5;
		if ( $batch_size <= 0 ) $batch_size = 5;
		$scan_id = isset( $_POST['batch_id'] ) ? intval( $_POST['batch_id'] ) : 0;

		// RÃ©cupÃ©rer un lot d'items en attente
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_scan_lines WHERE status = 'pending' AND scan_id = %d LIMIT %d", $scan_id, $batch_size ) );

		if ( empty( $items ) ) {
			// Synthèse de tous les cookies trouvés
			$all_scans = $wpdb->get_results( $wpdb->prepare( "SELECT cookies_found FROM $table_scan_lines WHERE status = 'completed' AND scan_id = %d AND cookies_found != '[]' AND cookies_found IS NOT NULL", $scan_id ) );
			
			$all_cookies = array();
			foreach ( $all_scans as $scan ) {
				$cookies = json_decode( $scan->cookies_found, true );
				if ( is_array( $cookies ) ) {
					foreach ( $cookies as $c ) {
						$all_cookies[] = $c;
					}
				}
			}
			$all_cookies = array_values( array_unique( $all_cookies ) );

			// Comparaison avec le registre existant
			$registry = get_option( 'eo_tools_cookie_registry', array() );
			$existing_cookie_names = array();
			foreach ( $registry as $cat => $list ) {
				if ( is_array( $list ) ) {
					foreach ( $list as $c ) {
						$existing_cookie_names[] = strtolower( $c['name'] );
					}
				}
			}

			$added_cookies = array();
			foreach ( $all_cookies as $c_name ) {
				if ( ! in_array( strtolower( $c_name ), $existing_cookie_names, true ) ) {
					$cat = 'others';
					if ( preg_match('/ga|matomo/i', $c_name) ) $cat = 'analytics';
					elseif ( preg_match('/ads|pixel|fbp/i', $c_name) ) $cat = 'marketing';
					elseif ( preg_match('/tw|li_|social/i', $c_name) ) $cat = 'social';
					elseif ( preg_match('/phpsessid|wordpress/i', $c_name) ) $cat = 'strictly-necessary';
					
					$added_cookies[] = array(
						'cat'    => $cat,
						'cookie' => array(
							'id'          => uniqid(),
							'name'        => $c_name,
							'domain'      => '',
							'date'        => '0',
							'comment'     => 'DÃ©tectÃ© lors du scan dÃ©taillÃ© du ' . current_time( 'd/m/Y' ),
							'active'      => true
						)
					);
				}
			}
			
			$requires_validation = count( $added_cookies ) > 0 ? 1 : 0;
			$new_cookies_json = wp_json_encode( $added_cookies );
			
			$wpdb->update( $table_scans, array(
				'status' => 'completed',
				'date_end' => current_time( 'mysql' ),
				'total_cookies_found' => count( $all_cookies ),
				'requires_validation' => $requires_validation,
				'new_cookies' => $new_cookies_json
			), array( 'id' => $scan_id ) );

			wp_send_json_success( array( 'status' => 'complete', 'message' => 'RÃ©sultats du batch.', 'items' => array(), 'batch_id' => $scan_id ) );
		} else {
			$patterns = array(
				'analytics'   => '/google-analytics\.com|googletagmanager\.com|analytics|matomo|piwik/i',
				'advertising' => '/doubleclick\.net|ads|pixel|fbp/i',
				'social'      => '/facebook\.net|twitter\.com|linkedin\.com/i'
			);

			$results = array();

			foreach ( $items as $item ) {
				// Mettre Ã  jour le statut en 'processing'
				$wpdb->update( $table_scan_lines, array( 'status' => 'processing' ), array( 'id' => $item->id ) );

				// Scanner l'URL
				$response = wp_remote_get( $item->url, array( 'timeout' => 10 ) );
				
				$found_cookies = array();
				
				if ( ! is_wp_error( $response ) ) {
					$body = wp_remote_retrieve_body( $response );
					$headers = wp_remote_retrieve_headers( $response );

					// VÃ©rifier les en-tÃªtes HTTP (Set-Cookie)
					if ( isset( $headers['set-cookie'] ) ) {
						$cookies = is_array( $headers['set-cookie'] ) ? $headers['set-cookie'] : array( $headers['set-cookie'] );
						foreach ( $cookies as $cookie ) {
							// Extraire le nom du cookie
							if ( preg_match( '/^([^=]+)=/', $cookie, $matches ) ) {
								$found_cookies[] = $matches[1];
							}
						}
					}

					// VÃ©rifier les empreintes dans le contenu (RegEx)
					foreach ( $patterns as $category => $regex ) {
						if ( preg_match( $regex, $body ) ) {
							$found_cookies[] = 'script_' . $category; // Tag gÃ©nÃ©rique si un script est trouvÃ©
						}
					}
				}

				// Mettre Ã  jour avec 'completed' et les cookies trouvÃ©s
				$wpdb->update( $table_scan_lines, array(
					'status'        => 'completed',
					'cookies_found' => wp_json_encode( array_values( array_unique( $found_cookies ) ) ),
					'scan_date'     => current_time( 'mysql' )
				), array( 'id' => $item->id ) );

				$results[] = array(
					'url'     => $item->url,
					'type'    => $item->item_type,
					'cookies' => count( array_unique( $found_cookies ) )
				);
			}

			// Statistiques
			$scanned_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE status = 'completed' AND scan_id = %d", $scan_id ) );
			$total_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE scan_id = %d", $scan_id ) );
			
			$wpdb->update( $table_scans, array( 'scanned_urls' => $scanned_count ), array( 'id' => $scan_id ) );

			$scanned_counts_by_type = array(
				'headers_footers' => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE status = 'completed' AND scan_id = %d AND item_type IN ('header', 'footer')", $scan_id ) ) ?: 0,
				'posts'           => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE status = 'completed' AND scan_id = %d AND item_type = 'post'", $scan_id ) ) ?: 0,
				'pages'           => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE status = 'completed' AND scan_id = %d AND item_type = 'page'", $scan_id ) ) ?: 0,
				'attachments'     => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE status = 'completed' AND scan_id = %d AND item_type = 'attachment'", $scan_id ) ) ?: 0,
				'cpts'            => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE status = 'completed' AND scan_id = %d AND item_type NOT IN ('header', 'footer', 'post', 'page', 'attachment')", $scan_id ) ) ?: 0,
			);

			wp_send_json_success( array(
				'status'         => 'processing',
				'scanned'        => $scanned_count,
				'total'          => $total_count,
				'scanned_counts' => $scanned_counts_by_type,
				'results'        => $results
			) );
		}
	}

	/**
	 * Récupère les résultats d'un scan détaillé passé
	 */
	public function ajax_get_detailed_scan_results() {
		check_ajax_referer( 'eo_tools_detailed_scan_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$scan_id = isset( $_POST['batch_id'] ) ? intval( $_POST['batch_id'] ) : 0;
		if ( empty( $scan_id ) ) {
			wp_send_json_error( 'Missing scan_id' );
		}

		global $wpdb;
		$table_scan_lines = $wpdb->prefix . 'eotools_scan_lines';

		$items = $wpdb->get_results( $wpdb->prepare( "SELECT url, item_type, cookies_found FROM $table_scan_lines WHERE scan_id = %d", $scan_id ) );

		$counts = array(
			'posts' => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE scan_id = %d AND item_type = 'post'", $scan_id ) ) ?: 0,
			'pages' => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE scan_id = %d AND item_type = 'page'", $scan_id ) ) ?: 0,
			'attachments' => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE scan_id = %d AND item_type = 'attachment'", $scan_id ) ) ?: 0,
			'cpts' => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE scan_id = %d AND item_type NOT IN ('header', 'footer', 'post', 'page', 'attachment')", $scan_id ) ) ?: 0,
			'headers_footers' => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_scan_lines WHERE scan_id = %d AND item_type IN ('header', 'footer')", $scan_id ) ) ?: 0
		);

		wp_send_json_success( array(
			'counts' => $counts,
			'items'  => $items
		) );
	}
}

new EoTools_Detailed_Scan();


