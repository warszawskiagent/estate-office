<?php
/**
 * Admin functionality.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Admin;

use EstateOffice\Database\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles WordPress admin integration.
 */
class Admin {
    private const MENU_SLUG = 'estate-office';

    /**
     * Register plugin menu pages.
     *
     * @return void
     */
    public function register_menu_pages(): void {
        add_menu_page(
            __( 'Estate Office CRM', 'estate-office' ),
            __( 'Estate Office', 'estate-office' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_about_page' ],
            'dashicons-building',
            26
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'O wtyczce', 'estate-office' ),
            __( 'O wtyczce', 'estate-office' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_about_page' ]
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Ustawienia', 'estate-office' ),
            __( 'Ustawienia', 'estate-office' ),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Licencja', 'estate-office' ),
            __( 'Licencja', 'estate-office' ),
            'manage_options',
            self::MENU_SLUG . '-license',
            [ $this, 'render_license_page' ]
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Agenci', 'estate-office' ),
            __( 'Agenci', 'estate-office' ),
            'edit_users',
            self::MENU_SLUG . '-agents',
            [ $this, 'render_agents_page' ]
        );
    }

    /**
     * Register settings fields.
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting( 'estate_office_settings', 'estate_office_google_api_key', [ $this, 'sanitize_api_key' ] );
        register_setting( 'estate_office_settings', 'estate_office_watermark', [ $this, 'sanitize_media_id' ] );
        register_setting( 'estate_office_settings', 'estate_office_logo', [ $this, 'sanitize_media_id' ] );
    }

    /**
     * Sanitize API key option.
     *
     * @param mixed $value Submitted value.
     *
     * @return string
     */
    public function sanitize_api_key( $value ): string {
        return sanitize_text_field( (string) $value );
    }

    /**
     * Sanitize media IDs.
     *
     * @param mixed $value Submitted value.
     *
     * @return int
     */
    public function sanitize_media_id( $value ): int {
        return absint( $value );
    }

    /**
     * Render about page.
     *
     * @return void
     */
    public function render_about_page(): void {
        $this->render_template( 'about' );
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page(): void {
        $this->render_template( 'settings' );
    }

    /**
     * Render license page.
     *
     * @return void
     */
    public function render_license_page(): void {
        $this->render_template( 'license' );
    }

    /**
     * Render agents page.
     *
     * @return void
     */
    public function render_agents_page(): void {
        $this->render_template( 'agents' );
    }

    /**
     * Shared renderer.
     *
     * @param string $view View name.
     *
     * @return void
     */
    private function render_template( string $view ): void {
        $file = ESTATE_OFFICE_PLUGIN_DIR . '/admin/partials/' . $view . '.php';

        if ( ! file_exists( $file ) ) {
            esc_html_e( 'Widok nie został odnaleziony.', 'estate-office' );
            return;
        }

        include $file;
    }

    /**
     * Register default nonce on admin pages.
     *
     * @return void
     */
    public static function print_admin_nonce(): void {
        wp_nonce_field( 'estate_office_admin_view', 'estate_office_nonce' );
    }

    /**
     * Run migrations manually from admin if needed.
     *
     * @return void
     */
    public static function maybe_run_migrations(): void {
        $migrations = new Migrations();
        $migrations->maybe_run();
    }
}
