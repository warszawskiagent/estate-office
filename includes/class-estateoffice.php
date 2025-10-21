<?php
namespace EstateOffice;

use EstateOffice\Admin\Admin_Menu;
use EstateOffice\Admin\Settings;
use EstateOffice\Admin\Agents_Page;
use EstateOffice\Post_Types\Registrar;
use EstateOffice\Setup\Activator;
use EstateOffice\Setup\Deactivator;
use EstateOffice\Frontend\CRM_Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class.
 */
final class Plugin {
    /**
     * Plugin instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Admin menu handler.
     *
     * @var Admin_Menu
     */
    private $admin_menu;

    /**
     * Settings handler.
     *
     * @var Settings
     */
    private $settings;

    /**
     * Agents admin page handler.
     *
     * @var Agents_Page
     */
    private $agents_page;

    /**
     * Post type registrar.
     *
     * @var Registrar
     */
    private $post_type_registrar;

    /**
     * Front-end CRM shortcode handler.
     *
     * @var CRM_Shortcode
     */
    private $crm_shortcode;

    /**
     * Plugin bootstrap.
     */
    private function __construct() {
        $this->define_constants();
        $this->include_dependencies();
        $this->register_hooks();
    }

    /**
     * Returns plugin instance.
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Define runtime constants.
     */
    private function define_constants(): void {
        if ( ! defined( 'ESTATE_OFFICE_ASSETS_URL' ) ) {
            define( 'ESTATE_OFFICE_ASSETS_URL', ESTATE_OFFICE_PLUGIN_URL . 'assets/' );
        }
    }

    /**
     * Autoload plugin files.
     */
    private function include_dependencies(): void {
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/setup/class-activator.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/setup/class-deactivator.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/post-types/class-registrar.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/admin/class-settings.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/admin/class-agents-page.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/frontend/class-crm-shortcode.php';
    }

    /**
     * Register hooks.
     */
    private function register_hooks(): void {
        register_activation_hook( ESTATE_OFFICE_PLUGIN_FILE, [ Activator::class, 'activate' ] );
        register_deactivation_hook( ESTATE_OFFICE_PLUGIN_FILE, [ Deactivator::class, 'deactivate' ] );

        add_action( 'init', [ $this, 'init' ] );
        add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
    }

    /**
     * Init hook.
     */
    public function init(): void {
        $this->post_type_registrar = new Registrar();
        $this->post_type_registrar->register();
    }

    /**
     * Plugins loaded hook.
     */
    public function plugins_loaded(): void {
        load_plugin_textdomain( 'estate-office', false, dirname( plugin_basename( ESTATE_OFFICE_PLUGIN_FILE ) ) . '/languages' );

        $this->settings    = new Settings();
        $this->agents_page  = new Agents_Page();
        $this->crm_shortcode = new CRM_Shortcode();
        $this->admin_menu   = new Admin_Menu( $this->settings, $this->agents_page );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_admin_assets(): void {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        if ( strpos( $screen->id, 'estate-office' ) === false ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'estate-office-admin', ESTATE_OFFICE_ASSETS_URL . 'css/admin.css', [], ESTATE_OFFICE_VERSION );
        wp_enqueue_script( 'estate-office-admin', ESTATE_OFFICE_ASSETS_URL . 'js/admin.js', [ 'jquery' ], ESTATE_OFFICE_VERSION, true );

        wp_localize_script(
            'estate-office-admin',
            'estateOfficeAdmin',
            [
                'nonce'          => wp_create_nonce( 'estate_office_admin' ),
                'strings'        => [
                    'confirmDelete' => esc_html__( 'Czy na pewno chcesz usunąć ten rekord?', 'estate-office' ),
                ],
            ]
        );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        wp_enqueue_style( 'estate-office-frontend', ESTATE_OFFICE_ASSETS_URL . 'css/frontend.css', [], ESTATE_OFFICE_VERSION );
        wp_enqueue_script( 'estate-office-frontend', ESTATE_OFFICE_ASSETS_URL . 'js/frontend.js', [ 'jquery' ], ESTATE_OFFICE_VERSION, true );

        wp_localize_script(
            'estate-office-frontend',
            'estateOfficeFrontend',
            [
                'nonce'   => wp_create_nonce( 'estate_office_frontend' ),
                'strings' => [
                    'searchPlaceholder' => esc_html__( 'Wyszukaj...', 'estate-office' ),
                ],
            ]
        );
    }
}
