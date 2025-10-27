<?php
/**
 * Main plugin bootstrap.
 *
 * @package EstateOfficeCRM
 */

namespace EstateOfficeCRM;

defined( 'ABSPATH' ) || exit;

use EstateOfficeCRM\Admin\Admin_Menu;
use EstateOfficeCRM\Database\Install;

/**
 * Core plugin class.
 */
final class Plugin {
    /**
     * Plugin singleton instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Main admin menu handler.
     *
     * @var Admin_Menu
     */
    private Admin_Menu $admin_menu;

    /**
     * Retrieve singleton instance.
     */
    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Plugin constructor.
     */
    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->hooks();
    }

    /**
     * Define frequently used constants.
     */
    private function define_constants(): void {
        define( 'EO_CRM_VERSION', '0.0.1' );
        define( 'EO_CRM_DIR', dirname( __DIR__ ) );
        define( 'EO_CRM_FILE', EO_CRM_DIR . '/estate-office-crm.php' );
        define( 'EO_CRM_PATH', plugin_dir_path( EO_CRM_FILE ) );
        define( 'EO_CRM_URL', plugin_dir_url( EO_CRM_FILE ) );
    }

    /**
     * Load required files.
     */
    private function includes(): void {
        require_once __DIR__ . '/helpers/class-autoloader.php';
        Autoloader::init();

        // Core includes.
        new Install();
        $this->admin_menu = new Admin_Menu();
    }

    /**
     * Register hooks.
     */
    private function hooks(): void {
        register_activation_hook( EO_CRM_FILE, [ Install::class, 'activate' ] );
        register_deactivation_hook( EO_CRM_FILE, [ Install::class, 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'init' ] );
    }

    /**
     * Plugin init hook.
     */
    public function init(): void {
        // Future initialization logic (custom post types, REST routes etc.).
    }

    /**
     * Load localization files.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'estate-office-crm', false, dirname( plugin_basename( EO_CRM_FILE ) ) . '/languages' );
    }
}
