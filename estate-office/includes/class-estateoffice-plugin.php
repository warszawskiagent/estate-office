<?php
namespace EstateOffice;

use EstateOffice\Admin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    /**
     * Singleton instance.
     *
     * @var Plugin
     */
    private static $instance;

    /**
     * Option key used to store plugin version in database.
     */
    const VERSION_OPTION = 'estate_office_version';

    /**
     * Instantiate the plugin.
     */
    private function __construct() {
        $this->includes();
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Retrieve singleton instance.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/class-estateoffice-roles.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/class-estateoffice-install.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/admin/class-estateoffice-admin.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/class-estateoffice-assets.php';
    }

    /**
     * Initialize plugin functionality.
     */
    public function init() {
        load_plugin_textdomain( 'estate-office', false, dirname( plugin_basename( ESTATE_OFFICE_PLUGIN_FILE ) ) . '/languages/' );

        Assets::instance();

        if ( is_admin() ) {
            Admin::instance();
        }
    }

    /**
     * Handle plugin activation.
     */
    public static function activate() {
        Install::activate();
        Roles::add_roles();
        update_option( self::VERSION_OPTION, ESTATE_OFFICE_VERSION );
    }

    /**
     * Handle plugin deactivation.
     */
    public static function deactivate() {
        Roles::remove_caps();
    }
}
