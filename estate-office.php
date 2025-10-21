<?php
/**
 * Plugin Name:       Estate Office CRM
 * Plugin URI:        https://example.com/estate-office
 * Description:       Estate Office to zaawansowany CRM dla biur nieruchomości integrujący zarządzanie nieruchomościami, klientami i umowami.
 * Version:           0.6.0
 * Author:            Estate Office Team
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       estate-office
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ESTATE_OFFICE_VERSION', '0.6.0' );
define( 'ESTATE_OFFICE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ESTATE_OFFICE_URL', plugin_dir_url( __FILE__ ) );
define( 'ESTATE_OFFICE_FILE', __FILE__ );

autoload_estate_office();

/**
 * Registers the autoloader for the Estate Office plugin.
 *
 * @return void
 */
function autoload_estate_office() {
    static $registered = false;

    if ( $registered ) {
        return;
    }

    spl_autoload_register(
        static function ( $class ) {
            if ( strpos( $class, 'EstateOffice\\' ) !== 0 ) {
                return;
            }

            $relative = substr( $class, strlen( 'EstateOffice\\' ) );
            $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
            $path     = ESTATE_OFFICE_PATH . 'includes/' . $relative . '.php';

            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    );

    $registered = true;
}

EstateOffice\Plugin::instance();
