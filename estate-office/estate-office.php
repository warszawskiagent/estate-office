<?php
/**
 * Plugin Name:       Estate Office CRM
 * Plugin URI:        https://example.com/estate-office
 * Description:       EstateOffice to zaawansowana wtyczka CRM dla biur nieruchomości w WordPressie.
 * Version:           0.0.1
 * Author:            Estate Office
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       estate-office
 * Domain Path:       /languages
 */

define( 'ESTATE_OFFICE_VERSION', '0.0.1' );
define( 'ESTATE_OFFICE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ESTATE_OFFICE_URL', plugin_dir_url( __FILE__ ) );

require_once ESTATE_OFFICE_PATH . 'includes/helpers.php';

autoload();

/**
 * Autoloader for plugin classes.
 */
function autoload() {
if ( ! class_exists( 'EstateOffice\\Autoloader' ) ) {
require_once ESTATE_OFFICE_PATH . 'includes/class-autoloader.php';
}
EstateOffice\Autoloader::init();
}

add_action( 'plugins_loaded', 'estate_office_bootstrap' );

/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function estate_office_bootstrap() {
EstateOffice\Plugin::get_instance();
}

register_activation_hook( __FILE__, 'estate_office_activate' );
register_deactivation_hook( __FILE__, 'estate_office_deactivate' );

/**
 * Plugin activation callback.
 *
 * @return void
 */
function estate_office_activate() {
EstateOffice\Activator::activate();
}

/**
 * Plugin deactivation callback.
 *
 * @return void
 */
function estate_office_deactivate() {
EstateOffice\Deactivator::deactivate();
}
