<?php
/**
 * Plugin Name:       EO Tools
 * Description:       Affichez des pages d'atterrissage personnalisées : Prochainement, Maintenance et 404.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Eoxia
 * Author URI:        https://www.eoxia.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       eo-tools
 * Domain Path:       /languages
 *
 * @package EoTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'EO_TOOLS_BASEFILE', __FILE__ );
define( 'EO_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'EO_TOOLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'EO_TOOLS_VERSION', '1.0.0' );

/**
 * Autoload the plugin classes.
 */
require_once EO_TOOLS_PATH . 'includes/autoload.php';

// Load AJAX API endpoints for the landing pages.
require_once EO_TOOLS_PATH . 'includes/api-eo-landing-pages.php';

use EoTools\Includes\Eotools;

// Translations are loaded automatically by WordPress since 4.6 for plugins
// hosted on WordPress.org, so no manual load_plugin_textdomain() is required.
new Eotools();
