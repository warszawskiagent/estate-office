<?php
/**
 * Plugin Name:       Estate Office CRM
 * Plugin URI:        https://warszawskiagent.pl
 * Description:       Kompleksowy CRM dla biur nieruchomości zintegrowany z WordPress.
 * Version:           0.1.0
 * Author:            Tomasz Obarski
 * Author URI:        https://warszawskiagent.pl
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       estate-office
 * Domain Path:       /languages
 * Requires at least: 6.8.3
 * Requires PHP:      8.3
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'ESTATE_OFFICE_VERSION' ) ) {
    define( 'ESTATE_OFFICE_VERSION', '0.1.0' );
}

if ( ! defined( 'ESTATE_OFFICE_PLUGIN_FILE' ) ) {
    define( 'ESTATE_OFFICE_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ESTATE_OFFICE_PLUGIN_DIR' ) ) {
    define( 'ESTATE_OFFICE_PLUGIN_DIR', __DIR__ );
}

require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/class-estate-office.php';
require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/class-activator.php';
require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/class-deactivator.php';

register_activation_hook( ESTATE_OFFICE_PLUGIN_FILE, [ '\\EstateOffice\\Activator', 'activate' ] );
register_deactivation_hook( ESTATE_OFFICE_PLUGIN_FILE, [ '\\EstateOffice\\Deactivator', 'deactivate' ] );

/**
 * Begins execution of the plugin.
 *
 * @return void
 */
function estate_office_run(): void {
    $plugin = new \EstateOffice\Estate_Office();
    $plugin->run();
}

estate_office_run();
