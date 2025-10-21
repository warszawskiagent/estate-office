<?php
/**
 * Core plugin bootstrap.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ESTATE_OFFICE_PATH . 'includes/class-estateoffice-admin.php';
require_once ESTATE_OFFICE_PATH . 'includes/class-estateoffice-public.php';

class EstateOffice {

    /**
     * Loader for admin logic.
     *
     * @var EstateOffice_Admin
     */
    protected $admin;

    /**
     * Public module handler.
     *
     * @var EstateOffice_Public
     */
    protected $public;

    /**
     * Initialize plugin pieces.
     */
    public function __construct() {
        $this->admin  = new EstateOffice_Admin();
        $this->public = new EstateOffice_Public();
    }

    /**
     * Register hooks.
     */
    public function run(): void {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ 'EstateOffice_Activator', 'ensure_role_capabilities' ] );
        if ( is_admin() ) {
            $this->admin->hooks();
        }

        $this->public->hooks();
    }

    /**
     * Load localization files.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'estate-office', false, dirname( plugin_basename( ESTATE_OFFICE_FILE ) ) . '/languages/' );
    }
}
