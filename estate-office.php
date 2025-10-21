<?php
/**
 * Plugin Name:       EstateOffice CRM
 * Plugin URI:        https://example.com/estate-office
 * Description:       Kompleksowy CRM dla biur nieruchomości z zarządzaniem nieruchomościami, umowami, klientami i agentami.
 * Version:           0.0.1
 * Author:            EstateOffice
 * Author URI:        https://example.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       estate-office
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'ESTATE_OFFICE_VERSION' ) ) {
    define( 'ESTATE_OFFICE_VERSION', '0.0.1' );
}

define( 'ESTATE_OFFICE_PLUGIN_FILE', __FILE__ );
define( 'ESTATE_OFFICE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ESTATE_OFFICE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

autoload_estate_office();
require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/template-functions.php';

/**
 * Simple PSR-4 compatible autoloader for plugin classes.
 *
 * @return void
 */
function autoload_estate_office() {
    spl_autoload_register(
        static function ( $class ) {
            if ( 0 !== strpos( $class, 'EstateOffice_' ) ) {
                return;
            }

            $relative_class = strtolower( str_replace( 'EstateOffice_', '', $class ) );
            $relative_class = str_replace( '\\', '/', $relative_class );
            $relative_class = str_replace( '_', '-', $relative_class );

            $file = ESTATE_OFFICE_PLUGIN_DIR . 'includes/class-estate-office-' . $relative_class . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    );
}

/**
 * The code that runs during plugin activation.
 */
function activate_estate_office() {
    EstateOffice_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_estate_office() {
    EstateOffice_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_estate_office' );
register_deactivation_hook( __FILE__, 'deactivate_estate_office' );

/**
 * Begins execution of the plugin.
 */
function run_estate_office() {
    $plugin = new EstateOffice_Plugin();
    $plugin->run();
}

run_estate_office();
