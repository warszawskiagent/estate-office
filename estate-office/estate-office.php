<?php
/**
 * Plugin Name: EstateOffice CRM
 * Plugin URI: https://example.com/estateoffice
 * Description: CRM dla biur nieruchomości – zarządzanie klientami, umowami, nieruchomościami i poszukiwaniami.
 * Version: 0.7.1
 * Author: EstateOffice
 * Text Domain: estateoffice
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (! defined('ABSPATH')) {
    exit;
}

define('ESTATEOFFICE_VERSION', '0.7.1');
define('ESTATEOFFICE_FILE', __FILE__);
define('ESTATEOFFICE_PATH', plugin_dir_path(__FILE__));
define('ESTATEOFFICE_URL', plugin_dir_url(__FILE__));

require_once ESTATEOFFICE_PATH . 'includes/class-estateoffice-activator.php';
require_once ESTATEOFFICE_PATH . 'includes/class-estateoffice-settings.php';
require_once ESTATEOFFICE_PATH . 'includes/class-estateoffice-clients.php';
require_once ESTATEOFFICE_PATH . 'includes/class-estateoffice-agreements.php';
require_once ESTATEOFFICE_PATH . 'includes/class-estateoffice-properties.php';
require_once ESTATEOFFICE_PATH . 'includes/class-estateoffice-searches.php';
require_once ESTATEOFFICE_PATH . 'includes/class-estateoffice-admin.php';
require_once ESTATEOFFICE_PATH . 'includes/class-estateoffice-crm-pages.php';

register_activation_hook(ESTATEOFFICE_FILE, ['EstateOffice_Activator', 'activate']);

add_action('plugins_loaded', static function (): void {
    EstateOffice_Admin::boot();
    EstateOffice_Clients::boot();
    EstateOffice_Agreements::boot();
    EstateOffice_Properties::boot();
    EstateOffice_Searches::boot();
    EstateOffice_CRM_Pages::boot();
});
