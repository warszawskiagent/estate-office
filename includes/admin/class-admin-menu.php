<?php
namespace EstateOffice\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin menu registration.
 */
class Admin_Menu {
    /**
     * Settings handler instance.
     *
     * @var Settings
     */
    private $settings;

    /**
     * Agents page handler.
     *
     * @var Agents_Page
     */
    private $agents_page;

    /**
     * Constructor.
     */
    public function __construct( Settings $settings, Agents_Page $agents_page ) {
        $this->settings    = $settings;
        $this->agents_page = $agents_page;

        add_action( 'admin_menu', [ $this, 'register_menu' ] );
    }

    /**
     * Registers plugin admin menu.
     */
    public function register_menu(): void {
        $capability = 'manage_options';

        add_menu_page(
            __( 'Estate Office CRM', 'estate-office' ),
            __( 'Estate Office CRM', 'estate-office' ),
            $capability,
            'estate-office-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-building',
            25
        );

        add_submenu_page(
            'estate-office-dashboard',
            __( 'Pulpit', 'estate-office' ),
            __( 'Pulpit', 'estate-office' ),
            $capability,
            'estate-office-dashboard',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'estate-office-dashboard',
            __( 'Licencja', 'estate-office' ),
            __( 'Licencja', 'estate-office' ),
            $capability,
            'estate-office-license',
            [ $this, 'render_license' ]
        );

        add_submenu_page(
            'estate-office-dashboard',
            __( 'Agenci', 'estate-office' ),
            __( 'Agenci', 'estate-office' ),
            'list_users',
            'estate-office-agents',
            [ $this, 'render_agents' ]
        );

        add_submenu_page(
            'estate-office-dashboard',
            __( 'Ustawienia', 'estate-office' ),
            __( 'Ustawienia', 'estate-office' ),
            $capability,
            'estate-office-settings',
            [ $this->settings, 'render_settings_page' ]
        );

        add_submenu_page(
            'estate-office-dashboard',
            __( 'O wtyczce', 'estate-office' ),
            __( 'About', 'estate-office' ),
            $capability,
            'estate-office-about',
            [ $this, 'render_about' ]
        );
    }

    /**
     * Renders dashboard placeholder.
     */
    public function render_dashboard(): void {
        include ESTATE_OFFICE_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
    }

    /**
     * Renders license placeholder.
     */
    public function render_license(): void {
        include ESTATE_OFFICE_PLUGIN_DIR . 'includes/admin/views/license.php';
    }

    /**
     * Renders agents page.
     */
    public function render_agents(): void {
        $this->agents_page->render_page();
    }

    /**
     * Renders about page.
     */
    public function render_about(): void {
        include ESTATE_OFFICE_PLUGIN_DIR . 'includes/admin/views/about.php';
    }
}
