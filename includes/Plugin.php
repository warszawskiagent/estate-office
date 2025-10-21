<?php
namespace EstateOffice;

use EstateOffice\Admin\Menu as AdminMenu;
use EstateOffice\Badges\Manager as BadgesManager;
use EstateOffice\Frontend\Agents as FrontendAgents;
use EstateOffice\Frontend\CRM as FrontendCRM;
use EstateOffice\Frontend\Offers as FrontendOffers;
use EstateOffice\Media\Watermark;
use EstateOffice\PostTypes\Register as PostTypesRegister;
use EstateOffice\Settings\Manager as SettingsManager;

/**
 * Main plugin bootstrap class.
 */
final class Plugin {
    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Admin menu handler.
     *
     * @var AdminMenu
     */
    private AdminMenu $admin_menu;

    /**
     * Post type registrar.
     *
     * @var PostTypesRegister
     */
    private PostTypesRegister $post_types;

    /**
     * Settings manager.
     *
     * @var SettingsManager
     */
    private SettingsManager $settings;

    /**
     * Front-end CRM handler.
     *
     * @var FrontendCRM
     */
    private FrontendCRM $frontend_crm;

    /**
     * Front-end offers handler.
     *
     * @var FrontendOffers
     */
    private FrontendOffers $offers;

    /**
     * Front-end agents handler.
     *
     * @var FrontendAgents
     */
    private FrontendAgents $agents;

    /**
     * Badge housekeeping manager.
     *
     * @var BadgesManager
     */
    private BadgesManager $badges;

    /**
     * Watermark manager.
     */
    private Watermark $watermark;

    /**
     * Retrieves singleton instance.
     */
    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor to maintain singleton.
     */
    private function __construct() {
        $this->post_types    = new PostTypesRegister();
        $this->admin_menu    = new AdminMenu();
        $this->settings      = new SettingsManager();
        $this->frontend_crm  = new FrontendCRM();
        $this->badges        = new BadgesManager();
        $this->agents        = new FrontendAgents();
        $this->offers        = new FrontendOffers( $this->badges, $this->agents );
        $this->watermark     = new Watermark( $this->settings );

        register_activation_hook( ESTATE_OFFICE_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( ESTATE_OFFICE_FILE, [ $this, 'deactivate' ] );

        add_action( 'init', [ $this->post_types, 'register' ], 5 );
        add_action( 'init', [ $this, 'register_roles' ], 6 );
        add_action( 'init', [ $this->offers, 'register_content_types' ], 6 );
        add_action( 'admin_init', [ $this->settings, 'register' ] );
        add_action( 'admin_menu', [ $this->admin_menu, 'register' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_assets' ] );
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

        add_action( 'init', [ $this->frontend_crm, 'register_shortcodes' ] );
        add_action( 'admin_init', [ $this->frontend_crm, 'maybe_restore_page' ] );
        add_action( 'init', [ $this, 'register_frontend_routes' ], 6 );
        add_filter( 'query_vars', [ $this->agents, 'register_query_var' ] );
        add_filter( 'template_include', [ $this->agents, 'template_loader' ] );
        add_action( 'init', [ $this->offers, 'register_shortcodes' ] );
        add_action( 'admin_init', [ $this->offers, 'ensure_listing_pages' ] );
        add_action( 'wp_enqueue_scripts', [ $this->offers, 'enqueue_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this->agents, 'enqueue_assets' ] );
        add_action( 'save_post_estate_property', [ $this->offers, 'sync_offer' ], 20, 3 );
        add_action( 'trashed_post', [ $this->offers, 'handle_property_trashed' ] );
        add_action( 'before_delete_post', [ $this->offers, 'handle_property_deleted' ] );
        add_action( 'estate_office_property_badges_updated', [ $this->offers, 'refresh_offer' ], 10 );
        add_action( 'estate_office_property_gallery_updated', [ $this->watermark, 'apply_to_gallery' ], 10, 2 );
        add_action( 'save_post_estate_property', [ $this->watermark, 'apply_on_save' ], 50, 3 );
        $this->offers->register_admin_actions();
        $this->badges->register();
    }

    /**
     * Registers rewrite rules required by front-end routes.
     */
    public function register_frontend_routes(): void {
        $this->agents->register_routes();
    }

    /**
     * Loads plugin text domain.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'estate-office', false, dirname( plugin_basename( ESTATE_OFFICE_PATH . 'estate-office.php' ) ) . '/languages/' );
    }

    /**
     * Performs activation logic.
     */
    public function activate(): void {
        $this->register_roles();
        $this->post_types->register();
        $this->offers->register_content_types();
        $this->register_frontend_routes();
        flush_rewrite_rules();
        $this->frontend_crm->ensure_page_exists();
        $this->offers->ensure_listing_pages();
        $this->badges->schedule();
    }

    /**
     * Performs deactivation logic.
     */
    public function deactivate(): void {
        flush_rewrite_rules();
        $this->badges->unschedule();
    }

    /**
     * Registers custom user roles and capabilities.
     */
    public function register_roles(): void {
        $capabilities = [
            'read'                   => true,
            'edit_posts'             => false,
            'delete_posts'           => false,
            'publish_posts'          => false,
            'upload_files'           => true,
            'list_users'             => false,
            'edit_others_posts'      => false,
            'delete_others_posts'    => false,
            'delete_published_posts' => false,
        ];

        add_role( 'estate_office_agent', __( 'Agent nieruchomości', 'estate-office' ), $capabilities );
    }

    /**
     * Enqueues admin-specific assets.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, 'estate-office' ) === false ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'estate-office-admin', ESTATE_OFFICE_URL . 'assets/css/admin.css', [], ESTATE_OFFICE_VERSION );
        wp_enqueue_script( 'estate-office-admin-settings', ESTATE_OFFICE_URL . 'assets/js/admin-settings.js', [], ESTATE_OFFICE_VERSION, true );
        wp_localize_script(
            'estate-office-admin-settings',
            'EstateOfficeSettings',
            [
                'nonce'      => wp_create_nonce( 'estate_office_settings' ),
                'field_i18n' => [
                    'fieldLabel' => __( 'Etykieta pola', 'estate-office' ),
                    'fieldType'  => __( 'Typ pola', 'estate-office' ),
                    'remove'     => __( 'Usuń', 'estate-office' ),
                ],
                'noMedia'    => __( 'Brak wybranego pliku', 'estate-office' ),
            ]
        );

        if ( isset( $_GET['page'] ) && in_array( $_GET['page'], [ 'estate-office-contract-wizard', 'estate-office-crm-panel' ], true ) ) {
            wp_enqueue_script( 'estate-office-contract-wizard', ESTATE_OFFICE_URL . 'assets/js/contract-wizard.js', [], ESTATE_OFFICE_VERSION, true );
        }
    }

    /**
     * Enqueues front-end assets.
     */
    public function enqueue_front_assets(): void {
        if ( ! is_page() ) {
            return;
        }

        $crm_page_id = $this->frontend_crm->get_page_id();

        if ( ! $crm_page_id || ! is_page( $crm_page_id ) ) {
            return;
        }

        wp_enqueue_style( 'estate-office-frontend', ESTATE_OFFICE_URL . 'assets/css/frontend.css', [], ESTATE_OFFICE_VERSION );
        wp_enqueue_script( 'estate-office-crm-app', ESTATE_OFFICE_URL . 'assets/js/crm-app.js', [], ESTATE_OFFICE_VERSION, true );
        wp_localize_script(
            'estate-office-crm-app',
            'EstateOfficeCRM',
            [
                'currentUser' => wp_get_current_user()->user_login,
                'restUrl'     => esc_url_raw( rest_url( 'estate-office/v1' ) ),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'translations' => [
                    'restricted' => __( 'Ta sekcja CRM jest dostępna wyłącznie dla administratorów.', 'estate-office' ),
                ],
            ]
        );
    }
}
