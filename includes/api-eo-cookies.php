<?php
namespace EoTools\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Cookies_API {
	public static function init() {
		add_action( 'wp_ajax_eo_tools_cookie_stats', array( __CLASS__, 'record_stats' ) );
		add_action( 'wp_ajax_nopriv_eo_tools_cookie_stats', array( __CLASS__, 'record_stats' ) );
	}

	public static function record_stats() {
		check_ajax_referer( 'eo_tools_cookie_nonce', 'security' );

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		
		if ( ! in_array( $type, array( 'view', 'accept_all', 'refuse_all', 'custom' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Type de statistique invalide.', 'eo-tools' ), 'code' => 'EOT_ERR_002' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'eotools_cookie_stats';
		$today = current_time( 'Y-m-d' );

		// Ensure record exists for today
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table_name WHERE stat_date = %s", $today ) );

		if ( ! $row ) {
			$wpdb->insert(
				$table_name,
				array(
					'stat_date' => $today,
					'views'     => ( 'view' === $type ) ? 1 : 0,
					'accepts'   => ( 'accept_all' === $type ) ? 1 : 0,
					'refusals'  => ( 'refuse_all' === $type ) ? 1 : 0,
					'customs'   => ( 'custom' === $type ) ? 1 : 0,
				)
			);
		} else {
			$column = '';
			switch ( $type ) {
				case 'view':
					$column = 'views';
					break;
				case 'accept_all':
					$column = 'accepts';
					break;
				case 'refuse_all':
					$column = 'refusals';
					break;
				case 'custom':
					$column = 'customs';
					break;
			}
			
			if ( $column ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET $column = $column + 1 WHERE stat_date = %s", $today ) );
			}
		}

		wp_send_json_success();
	}
}

Eotools_Cookies_API::init();
