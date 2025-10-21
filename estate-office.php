<?php
/**
 * Plugin Name:       EstateOffice CRM
 * Plugin URI:        https://example.com/estateoffice
 * Description:       Kompleksowy CRM dla biur nieruchomości. Zarządzaj nieruchomościami, umowami, klientami i agentami oraz eksportuj oferty na stronę WWW.
 * Version:           0.6.0
 * Author:            EstateOffice Team
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       estate-office
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'ESTATE_OFFICE_VERSION' ) ) {
    define( 'ESTATE_OFFICE_VERSION', '0.6.0' );
}

if ( ! defined( 'ESTATE_OFFICE_PLUGIN_FILE' ) ) {
    define( 'ESTATE_OFFICE_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ESTATE_OFFICE_PLUGIN_DIR' ) ) {
    define( 'ESTATE_OFFICE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ESTATE_OFFICE_PLUGIN_URL' ) ) {
    define( 'ESTATE_OFFICE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/class-estateoffice.php';

/**
 * Returns the main plugin instance.
 *
 * @return \EstateOffice\Plugin
 */
function estate_office() {
    return \EstateOffice\Plugin::instance();
}

estate_office();
