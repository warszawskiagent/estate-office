<?php
/**
 * Plugin Name:       EstateOffice CRM
 * Plugin URI:        https://example.com/estateoffice
 * Description:       Kompleksowy CRM dla biur nieruchomości. Zarządzaj nieruchomościami, klientami, umowami i agentami w WordPress.
 * Version:           0.0.1
 * Author:            EstateOffice
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       estateoffice
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'ESTATEOFFICE_VERSION', '0.0.1' );
define( 'ESTATEOFFICE_FILE', __FILE__ );
define( 'ESTATEOFFICE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ESTATEOFFICE_URL', plugin_dir_url( __FILE__ ) );

require_once ESTATEOFFICE_DIR . 'includes/helpers.php';
require_once ESTATEOFFICE_DIR . 'includes/class-estateoffice-plugin.php';

\EstateOffice\Plugin::instance();
