<?php
namespace EstateOffice\Admin;

use EstateOffice\Roles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {
    /**
     * Singleton instance.
     *
     * @var Admin
     */
    private static $instance;

    /**
     * Retrieve singleton instance.
     *
     * @return Admin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->hooks();
        }

        return self::$instance;
    }

    /**
     * Register hooks.
     */
    private function hooks() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    /**
     * Register Estate Office menu structure.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Estate Office CRM', 'estate-office' ),
            __( 'Estate Office CRM', 'estate-office' ),
            Roles::CAPABILITY,
            'estate-office-crm',
            array( $this, 'render_dashboard' ),
            'dashicons-building',
            58
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Pulpit', 'estate-office' ),
            __( 'Pulpit', 'estate-office' ),
            Roles::CAPABILITY,
            'estate-office-crm',
            array( $this, 'render_dashboard' )
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Agenci', 'estate-office' ),
            __( 'Agenci', 'estate-office' ),
            Roles::AGENT_MANAGE_CAP,
            'estate-office-agents',
            array( $this, 'render_agents' )
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Ustawienia', 'estate-office' ),
            __( 'Ustawienia', 'estate-office' ),
            Roles::CAPABILITY,
            'estate-office-settings',
            array( $this, 'render_settings' )
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'About', 'estate-office' ),
            __( 'About', 'estate-office' ),
            Roles::CAPABILITY,
            'estate-office-about',
            array( $this, 'render_about' )
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Licencja', 'estate-office' ),
            __( 'Licencja', 'estate-office' ),
            Roles::CAPABILITY,
            'estate-office-license',
            array( $this, 'render_license' )
        );
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard() {
        include __DIR__ . '/views/dashboard.php';
    }

    /**
     * Render agents page.
     */
    public function render_agents() {
        require_once __DIR__ . '/class-estateoffice-admin-agents.php';
        $page = new Agents_Page();
        $page->render();
    }

    /**
     * Render settings page.
     */
    public function render_settings() {
        require_once __DIR__ . '/class-estateoffice-admin-settings.php';
        $page = new Settings_Page();
        $page->render();
    }

    /**
     * Render about page.
     */
    public function render_about() {
        include __DIR__ . '/views/about.php';
    }

    /**
     * Render license placeholder.
     */
    public function render_license() {
        include __DIR__ . '/views/license.php';
    }
}
