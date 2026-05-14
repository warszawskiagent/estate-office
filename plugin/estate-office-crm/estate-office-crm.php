<?php
/**
 * Plugin Name: Estate Office CRM
 * Plugin URI: https://estateofficecrm.pl/
 * Description: Advanced CRM for real estate agencies: clients, agreements, properties, searches, and public offers export.
 * Version: 1.1725
 * Author: Tomasz Obarski
 * Author URI: https://estateofficecrm.pl/
 * Text Domain: estate-office-crm
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit;
}

define('EOCRM_VERSION', '1.1725');
define('EOCRM_FILE', __FILE__);
define('EOCRM_PATH', plugin_dir_path(__FILE__));
define('EOCRM_URL', plugin_dir_url(__FILE__));

require_once EOCRM_PATH . 'includes/class-estate-office-crm-db-schema.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-access.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-installer.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-numbering.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-stages.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-units.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-property-custom-fields.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-i18n.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-portal-export.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-pdf.php';
require_once EOCRM_PATH . 'includes/licensing/class-eolm-client.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-license.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-clients.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-agreements.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-properties.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-searches.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-transactions.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-pages.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-admin.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-plugin.php';
require_once EOCRM_PATH . 'includes/class-estate-office-crm-api.php';

register_activation_hook(EOCRM_FILE, ['EstateOfficeCRM_Installer', 'activate']);
register_deactivation_hook(EOCRM_FILE, ['EstateOfficeCRM_Installer', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    $plugin = new EstateOfficeCRM_Plugin();
    $plugin->run();
});

add_action('rest_api_init', static function (): void {
    $api = new EstateOfficeCRM_API();
    $api->register_routes();
});


