<?php
/**
 * Plugin Name:       Estate Office CRM
 * Plugin URI:        https://example.com/estate-office-crm
 * Description:       EstateOffice to kompleksowy CRM dla biur nieruchomości integrujący zarządzanie nieruchomościami, klientami, agentami i umowami.
 * Version:           0.0.1
 * Author:            Estate Office
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       estate-office
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'ESTATE_OFFICE_PLUGIN_FILE' ) ) {
    define( 'ESTATE_OFFICE_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ESTATE_OFFICE_PLUGIN_DIR' ) ) {
    define( 'ESTATE_OFFICE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ESTATE_OFFICE_VERSION' ) ) {
    define( 'ESTATE_OFFICE_VERSION', '0.0.1' );
}

require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/class-estateoffice-plugin.php';

register_activation_hook( __FILE__, array( '\\EstateOffice\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\EstateOffice\\Plugin', 'deactivate' ) );

EstateOffice\Plugin::instance();
