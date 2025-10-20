<?php
/**
 * Plugin Name: EstateOffice CRM
 * Plugin URI: http://warszawskiagent.pl
 * Description: EstateOffice to zaawansowany CRM dla biur nieruchomości zintegrowany z WordPress.
 * Version: 0.1.11
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Author: Tomasz Obarski
 * Author URI: http://warszawskiagent.pl
 * Text Domain: estate-office
 * Domain Path: /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const ESTATE_OFFICE_PLUGIN_FILE = __FILE__;
const ESTATE_OFFICE_PLUGIN_DIR = __DIR__ . '/';
const ESTATE_OFFICE_PLUGIN_VERSION = '0.1.11';

require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/class-autoloader.php';

EstateOffice\Autoloader::register();

EstateOffice\Plugin::instance();
