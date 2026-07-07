<?php
/**
 * Uninstall routine for EO Tools - Landing Pages.
 *
 * Removes the plugin option when the plugin is deleted from WordPress.
 *
 * @package EoTools
 */

// Exit if uninstall is not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete the plugin option, with multisite support.
 */
function eo_tools_uninstall_cleanup() {
	delete_option( 'eo_tools_landing_pages_settings' );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		eo_tools_uninstall_cleanup();
		restore_current_blog();
	}
} else {
	eo_tools_uninstall_cleanup();
}
