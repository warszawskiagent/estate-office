<?php
namespace EstateOffice;

use EstateOffice\Admin\Admin_Menu;
use EstateOffice\Admin\Settings;
use EstateOffice\Admin\Agents_Page;
use EstateOffice\Admin\Meta_Boxes;
use EstateOffice\Post_Types\Registrar;
use EstateOffice\Setup\Activator;
use EstateOffice\Setup\Deactivator;
use EstateOffice\Frontend\CRM_Shortcode;
use EstateOffice\Frontend\CRM_REST;
use EstateOffice\Frontend\Listings;
use EstateOffice\Frontend\Agents;

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
     * Meta boxes handler.
     *
     * @var Meta_Boxes
     */
    private $meta_boxes;

    /**
     * Front-end listings handler.
     *
     * @var Listings
     */
    private $listings;

    /**
     * Public agent pages handler.
     *
     * @var Agents
     */
    private $agent_pages;

    /**
     * CRM REST endpoints handler.
     *
     * @var CRM_REST
     */
    private $crm_rest;

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
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/admin/class-meta-boxes.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/frontend/class-crm-shortcode.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/frontend/class-listings.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/frontend/class-agents.php';
        require_once ESTATE_OFFICE_PLUGIN_DIR . 'includes/frontend/class-crm-rest.php';
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
        $this->meta_boxes   = new Meta_Boxes();
        $this->crm_shortcode = new CRM_Shortcode();
        $this->listings      = new Listings();
        $this->agent_pages   = new Agents( $this->listings );
        $this->crm_rest      = new CRM_REST();
        $this->admin_menu    = new Admin_Menu( $this->settings, $this->agents_page );
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
                'nonce'  => wp_create_nonce( 'estate_office_frontend' ),
                'apiUrl' => esc_url_raw( rest_url( 'estate-office/v1' ) ),
                'strings' => [
                    'searchPlaceholder' => esc_html__( 'Wyszukaj...', 'estate-office' ),
                    'noResults'         => esc_html__( 'Brak wyników.', 'estate-office' ),
                    'contractEdit'      => esc_html__( 'Przejdź do umowy', 'estate-office' ),
                    'offerEdit'         => esc_html__( 'Otwórz rekord', 'estate-office' ),
                    'success'           => esc_html__( 'Proces został zakończony pomyślnie.', 'estate-office' ),
                    'clientRequired'    => esc_html__( 'Dodaj co najmniej jednego klienta.', 'estate-office' ),
                ],
                'profile' => [
                    'error'         => esc_html__( 'Nie udało się pobrać danych rekordu.', 'estate-office' ),
                    'openAdmin'     => esc_html__( 'Otwórz w kokpicie', 'estate-office' ),
                    'emptySection'  => esc_html__( 'Brak danych.', 'estate-office' ),
                    'noRelations'   => esc_html__( 'Brak powiązań.', 'estate-office' ),
                    'stage'         => [
                        'title'   => esc_html__( 'Aktualizuj etap umowy', 'estate-office' ),
                        'stage'   => esc_html__( 'Etap umowy', 'estate-office' ),
                        'date'    => esc_html__( 'Data etapu', 'estate-office' ),
                        'submit'  => esc_html__( 'Aktualizuj etap', 'estate-office' ),
                        'success' => esc_html__( 'Etap umowy został zaktualizowany.', 'estate-office' ),
                        'error'   => esc_html__( 'Nie udało się zapisać etapu umowy.', 'estate-office' ),
                    ],
                ],
            ]
        );
    }
}
