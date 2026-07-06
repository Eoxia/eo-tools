<?php
/**
 * Plugin Name:       EO Tools
 * Description:       A collection of tools and utilities for WordPress made by Eoxia
 * Requires at least: 7.0.0
 * Requires PHP:      7.0
 * Version:           1.0.0
 * Author:            Eoxia
 * Author URI:        https://www.eoxia.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       eo-tools
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
 * Autoload the php files.
 */
require_once EO_TOOLS_PATH . 'includes/autoload.php';

// Load AJAX API endpoints
require_once EO_TOOLS_PATH . 'includes/api-eo-landing-pages.php';
require_once EO_TOOLS_PATH . 'includes/api-eo-cookies.php';
require_once EO_TOOLS_PATH . 'includes/api-eo-detailed-scan.php';

use EoTools\Includes\Eotools;

$eotools = new Eotools();
