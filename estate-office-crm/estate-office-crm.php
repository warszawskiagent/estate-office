<?php
/**
 * Plugin Name: Estate Office CRM
 * Description: CRM plugin for real estate offices. Manage properties, contracts, clients, and agents.
 * Version: 0.0.1
 * Author: Estate Office
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: estate-office-crm
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EOC_PLUGIN_VERSION', '0.0.1');
define('EOC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EOC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EOC_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once EOC_PLUGIN_DIR . 'includes/class-eoc-activator.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-agents.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-admin-menu.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-clients.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-contracts.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-contract-stages.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-crm-pages.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-properties.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-searches.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-settings.php';
require_once EOC_PLUGIN_DIR . 'includes/class-eoc-plugin.php';

function eoc_run_plugin(): void {
    $plugin = new EOC_Plugin();
    $plugin->run();
}

eoc_run_plugin();
