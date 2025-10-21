<?php
/**
 * Main plugin bootstrap class.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EstateOffice_Plugin
 */
class EstateOffice_Plugin {

    /**
     * Admin handler instance.
     *
     * @var EstateOffice_Admin
     */
    protected $admin;

    /**
     * Assets handler instance.
     *
     * @var EstateOffice_Assets
     */
    protected $assets;

    /**
     * Settings handler instance.
     *
     * @var EstateOffice_Settings
     */
    protected $settings;

    /**
     * REST API handler instance.
     *
     * @var EstateOffice_Rest
     */
    protected $rest;

    /**
     * CRM handler instance.
     *
     * @var EstateOffice_CRM
     */
    protected $crm;

    /**
     * EstateOffice_Plugin constructor.
     */
    public function __construct() {
        $this->settings = new EstateOffice_Settings();
        $this->admin    = new EstateOffice_Admin( $this->settings );
        $this->assets   = new EstateOffice_Assets();
        $this->rest     = new EstateOffice_Rest( $this->settings );
        $this->crm      = new EstateOffice_CRM( $this->settings );
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function run() {
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );

        $this->settings->register_hooks();
        $this->admin->register_hooks();
        $this->assets->register_hooks();
        $this->rest->register_hooks();
        $this->crm->register_hooks();
    }

    /**
     * Init hook.
     *
     * @return void
     */
    public function init() {
        $this->crm->register_shortcodes();
    }

    /**
     * plugins_loaded hook.
     *
     * @return void
     */
    public function plugins_loaded() {
        load_plugin_textdomain( 'estate-office', false, dirname( plugin_basename( ESTATE_OFFICE_PLUGIN_FILE ) ) . '/languages/' );
    }
}
