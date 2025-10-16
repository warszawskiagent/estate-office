<?php
/**
 * Plugin Name:       Estate Office CRM
 * Plugin URI:        http://warszawskiagent.pl
 * Description:       EstateOffice to zaawansowany CRM dla biur nieruchomości. Zarządzaj nieruchomościami, umowami, klientami i agentami, integruj oferty z mapami i eksportuj na portale.
 * Version:           0.1.0
 * Author:            Tomasz Obarski
 * Author URI:        http://warszawskiagent.pl
 * Text Domain:       estate-office
 * Domain Path:       /languages
 * Requires at least: 6.8
 * Requires PHP:      8.3
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ESTATE_OFFICE_VERSION', '0.1.0');
define('ESTATE_OFFICE_PLUGIN_FILE', __FILE__);
define('ESTATE_OFFICE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ESTATE_OFFICE_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!class_exists('EstateOffice')) {
    /**
     * Główna klasa wtyczki EstateOffice.
     */
    final class EstateOffice
    {
        /**
         * Instancja singletonu.
         *
         * @var EstateOffice|null
         */
        private static ?EstateOffice $instance = null;

        /**
         * Uruchamia logikę wtyczki.
         */
        private function __construct()
        {
            $this->define_hooks();
        }

        /**
         * Zwraca instancję wtyczki.
         */
        public static function get_instance(): EstateOffice
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Rejestracja hooków.
         */
        private function define_hooks(): void
        {
            require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/class-estate-office-admin.php';

            $admin = new Estate_Office_Admin();
            add_action('admin_menu', [$admin, 'register_admin_menus']);
        }

        /**
         * Włączenie wtyczki.
         */
        public static function activate(): void
        {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EstateOffice] Wtyczka została aktywowana.');
            }
        }

        /**
         * Wyłączenie wtyczki.
         */
        public static function deactivate(): void
        {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EstateOffice] Wtyczka została dezaktywowana.');
            }
        }
    }
}

register_activation_hook(ESTATE_OFFICE_PLUGIN_FILE, ['EstateOffice', 'activate']);
register_deactivation_hook(ESTATE_OFFICE_PLUGIN_FILE, ['EstateOffice', 'deactivate']);

EstateOffice::get_instance();
