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

class EstateOffice {

    /**
     * Loader for admin logic.
     *
     * @var EstateOffice_Admin
     */
    protected $admin;

    /**
     * Initialize plugin pieces.
     */
    public function __construct() {
        $this->admin = new EstateOffice_Admin();
    }

    /**
     * Register hooks.
     */
    public function run(): void {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        if ( is_admin() ) {
            $this->admin->hooks();
        }
    }

    /**
     * Load localization files.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'estate-office', false, dirname( plugin_basename( ESTATE_OFFICE_FILE ) ) . '/languages/' );
    }
}
