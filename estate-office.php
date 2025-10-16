<?php
/**
 * Plugin Name: EstateOffice CRM
 * Plugin URI: http://warszawskiagent.pl
 * Description: EstateOffice to zaawansowany CRM dla biur nieruchomości, zarządzający nieruchomościami, klientami, umowami i agentami.
 * Version: 0.9.0
 * Author: Tomasz Obarski
 * Author URI: http://warszawskiagent.pl
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: estate-office
 * Domain Path: /languages
 * Requires at least: 6.8.3
 * Requires PHP: 8.3
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'ESTATE_OFFICE_VERSION' ) ) {
    // Prevent double loading if multiple copies are active.
    return;
}

const ESTATE_OFFICE_VERSION = '0.9.0';
const ESTATE_OFFICE_MINIMUM_WP = '6.8.3';
const ESTATE_OFFICE_MINIMUM_PHP = '8.3';

define( 'ESTATE_OFFICE_FILE', __FILE__ );
define( 'ESTATE_OFFICE_PATH', plugin_dir_path( ESTATE_OFFICE_FILE ) );
define( 'ESTATE_OFFICE_URL', plugin_dir_url( ESTATE_OFFICE_FILE ) );
define( 'ESTATE_OFFICE_BASENAME', plugin_basename( ESTATE_OFFICE_FILE ) );

define( 'ESTATE_OFFICE_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );

estate_office_register_autoloader();

/**
 * Register autoloader for plugin classes using prefixed class names.
 *
 * @return void
 */
function estate_office_register_autoloader() : void {
    spl_autoload_register( static function ( string $class ) : void {
        if ( ! str_starts_with( $class, 'Estate_Office_' ) ) {
            return;
        }

        $normalized = strtolower( str_replace( '_', '-', $class ) );
        $base_dir   = ESTATE_OFFICE_PATH . 'includes/';
        $file_name  = 'class-' . $normalized . '.php';

        if ( str_contains( $normalized, 'estate-office-admin-' ) ) {
            $path = $base_dir . 'admin/' . $file_name;
        } elseif ( str_contains( $normalized, 'estate-office-database-' ) ) {
            $path = $base_dir . 'database/' . $file_name;
        } elseif ( str_contains( $normalized, 'estate-office-agent-' ) ) {
            $path = $base_dir . 'agents/' . $file_name;
        } elseif ( str_contains( $normalized, 'estate-office-property-' ) ) {
            $path = $base_dir . 'properties/' . $file_name;
        } elseif ( str_contains( $normalized, 'estate-office-search-' ) ) {
            $path = $base_dir . 'searches/' . $file_name;
        } elseif ( str_contains( $normalized, 'estate-office-client-' ) ) {
            $path = $base_dir . 'clients/' . $file_name;
        } elseif ( str_contains( $normalized, 'estate-office-contract-' ) ) {
            $path = $base_dir . 'contracts/' . $file_name;
        } elseif ( str_contains( $normalized, 'estate-office-settings-' ) ) {
            $path = $base_dir . 'settings/' . $file_name;
        } elseif ( str_contains( $normalized, 'estate-office-frontend-' ) ) {
            $path = $base_dir . 'frontend/' . $file_name;
        } elseif ( str_contains( $normalized, 'estate-office-license-' ) ) {
            $path = $base_dir . 'license/' . $file_name;
        } else {
            $path = $base_dir . $file_name;
        }

        if ( file_exists( $path ) ) {
            require_once $path;
        }
    } );
}

require_once ESTATE_OFFICE_PATH . 'includes/class-estate-office-plugin.php';

register_activation_hook( ESTATE_OFFICE_FILE, [ 'Estate_Office_Plugin', 'activate' ] );
register_deactivation_hook( ESTATE_OFFICE_FILE, [ 'Estate_Office_Plugin', 'deactivate' ] );

add_action(
    'plugins_loaded',
    static function () : void {
        if ( ! Estate_Office_Plugin::requirements_met() ) {
            return;
        }

        Estate_Office_Plugin::instance()->boot();
    }
);
