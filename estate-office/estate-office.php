<?php
/**
 * Plugin Name: EstateOffice CRM
 * Description: CRM dla biur nieruchomości z modułami nieruchomości, umów, klientów i agentów.
 * Version: 0.0.5
 * Author: EstateOffice
 * Text Domain: estate-office
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ESTATEOFFICE_VERSION', '0.0.5');
define('ESTATEOFFICE_PLUGIN_FILE', __FILE__);
define('ESTATEOFFICE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ESTATEOFFICE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ESTATEOFFICE_PLUGIN_DIR . 'includes/class-eo-activator.php';
require_once ESTATEOFFICE_PLUGIN_DIR . 'includes/class-eo-deactivator.php';
require_once ESTATEOFFICE_PLUGIN_DIR . 'includes/class-eo-plugin.php';

register_activation_hook(__FILE__, array('EstateOffice_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('EstateOffice_Deactivator', 'deactivate'));

function estateoffice_run_plugin()
{
    $plugin = new EstateOffice_Plugin();
    $plugin->run();
}

estateoffice_run_plugin();
