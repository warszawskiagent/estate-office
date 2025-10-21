<?php
/**
 * Plugin Name:       EstateOffice CRM
 * Plugin URI:        https://example.com/estateoffice
 * Description:       EstateOffice to zaawansowany CRM dla biur nieruchomości integrujący zarządzanie nieruchomościami, klientami, umowami i agentami.
 * Version:           0.0.7
 * Author:            EstateOffice Team
 * Author URI:        https://example.com
 * Text Domain:       estate-office
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'ESTATE_OFFICE_VERSION' ) ) {
    define( 'ESTATE_OFFICE_VERSION', '0.0.7' );
}

define( 'ESTATE_OFFICE_FILE', __FILE__ );
define( 'ESTATE_OFFICE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ESTATE_OFFICE_URL', plugin_dir_url( __FILE__ ) );
define( 'ESTATE_OFFICE_MIN_CAPABILITY', 'manage_options' );

require_once ESTATE_OFFICE_PATH . 'includes/class-estateoffice-activator.php';
require_once ESTATE_OFFICE_PATH . 'includes/class-estateoffice-deactivator.php';
require_once ESTATE_OFFICE_PATH . 'includes/class-estateoffice.php';

register_activation_hook( __FILE__, [ 'EstateOffice_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'EstateOffice_Deactivator', 'deactivate' ] );

function estate_office_run() {
    $plugin = new EstateOffice();
    $plugin->run();
}
estate_office_run();
