<?php
/**
 * Register admin menus for the plugin.
 *
 * @package EstateOfficeCRM\Admin
 */

namespace EstateOfficeCRM\Admin;

defined( 'ABSPATH' ) || exit;

use EstateOfficeCRM\Admin\Pages\About_Page;
use EstateOfficeCRM\Admin\Pages\Agents_Page;
use EstateOfficeCRM\Admin\Pages\Clients_Page;
use EstateOfficeCRM\Admin\Pages\Contracts_Page;
use EstateOfficeCRM\Admin\Pages\Properties_Page;
use EstateOfficeCRM\Admin\Pages\Searches_Page;
use EstateOfficeCRM\Admin\Pages\Settings_Page;
use EstateOfficeCRM\Capabilities;
use EstateOfficeCRM\Database\Repositories\Clients_Repository;
use EstateOfficeCRM\Database\Repositories\Contracts_Repository;
use EstateOfficeCRM\Database\Repositories\Properties_Repository;
use EstateOfficeCRM\Database\Repositories\Searches_Repository;

/**
 * Admin menu registration.
 */
class Admin_Menu {
    /**
     * Construct.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register the admin menu structure.
     */
    public function register_menu(): void {
        add_menu_page(
            __( 'Estate Office CRM', 'estate-office-crm' ),
            __( 'Estate Office CRM', 'estate-office-crm' ),
            Capabilities::ACCESS_DASHBOARD,
            'estate-office-crm',
            [ $this, 'render_dashboard_page' ],
            'dashicons-building'
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Pulpit', 'estate-office-crm' ),
            __( 'Pulpit', 'estate-office-crm' ),
            Capabilities::ACCESS_DASHBOARD,
            'estate-office-crm'
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Agenci', 'estate-office-crm' ),
            __( 'Agenci', 'estate-office-crm' ),
            Capabilities::MANAGE_AGENTS,
            'estate-office-crm-agents',
            [ $this, 'render_agents_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Klienci', 'estate-office-crm' ),
            __( 'Klienci', 'estate-office-crm' ),
            Capabilities::MANAGE_CLIENTS,
            'estate-office-crm-clients',
            [ $this, 'render_clients_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Umowy', 'estate-office-crm' ),
            __( 'Umowy', 'estate-office-crm' ),
            Capabilities::MANAGE_CONTRACTS,
            'estate-office-crm-contracts',
            [ $this, 'render_contracts_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Nieruchomości', 'estate-office-crm' ),
            __( 'Nieruchomości', 'estate-office-crm' ),
            Capabilities::MANAGE_PROPERTIES,
            'estate-office-crm-properties',
            [ $this, 'render_properties_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Poszukiwania', 'estate-office-crm' ),
            __( 'Poszukiwania', 'estate-office-crm' ),
            Capabilities::MANAGE_SEARCHES,
            'estate-office-crm-searches',
            [ $this, 'render_searches_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Ustawienia', 'estate-office-crm' ),
            __( 'Ustawienia', 'estate-office-crm' ),
            Capabilities::MANAGE_SETTINGS,
            'estate-office-crm-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'About', 'estate-office-crm' ),
            __( 'About', 'estate-office-crm' ),
            Capabilities::ACCESS_DASHBOARD,
            'estate-office-crm-about',
            [ $this, 'render_about_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Licencja', 'estate-office-crm' ),
            __( 'Licencja', 'estate-office-crm' ),
            Capabilities::MANAGE_SETTINGS,
            'estate-office-crm-license',
            [ $this, 'render_license_page' ]
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings(): void {
        register_setting( 'estate_office_crm_settings', 'estate_office_crm_settings', [ $this, 'sanitize_settings' ] );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input.
     */
    public function sanitize_settings( $input ): array { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
        $input     = is_array( $input ) ? $input : [];
        $sanitized = [];

        $sanitized['google_maps_api_key']  = sanitize_text_field( $input['google_maps_api_key'] ?? '' );
        $sanitized['watermark_media_id']   = isset( $input['watermark_media_id'] ) ? absint( $input['watermark_media_id'] ) : 0;
        $sanitized['office_logo_media_id'] = isset( $input['office_logo_media_id'] ) ? absint( $input['office_logo_media_id'] ) : 0;
        $sanitized['property_fields']      = $this->sanitize_dynamic_fields( $input['property_fields'] ?? [] );
        $sanitized['contract_fields']      = $this->sanitize_dynamic_fields( $input['contract_fields'] ?? [] );
        $sanitized['client_fields']        = $this->sanitize_dynamic_fields( $input['client_fields'] ?? [] );

        return $sanitized;
    }

    /**
     * Sanitize dynamic fields definition.
     *
     * @param array $fields Field definitions from form.
     */
    private function sanitize_dynamic_fields( array $fields ): array {
        $sanitized = [];

        foreach ( $fields as $field ) {
            if ( empty( $field['key'] ) ) {
                continue;
            }

            $sanitized[] = [
                'key'   => sanitize_key( $field['key'] ),
                'label' => sanitize_text_field( $field['label'] ?? '' ),
                'type'  => sanitize_text_field( $field['type'] ?? 'text' ),
            ];
        }

        return $sanitized;
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, 'estate-office-crm' ) ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'estate-office-crm-admin', EO_CRM_URL . 'assets/css/admin.css', [], EO_CRM_VERSION );
        wp_enqueue_script( 'estate-office-crm-admin', EO_CRM_URL . 'assets/js/admin.js', [ 'jquery', 'wp-util', 'wp-i18n', 'wp-components', 'wp-element', 'media-upload' ], EO_CRM_VERSION, true );
        wp_localize_script(
            'estate-office-crm-admin',
            'EstateOfficeCRM',
            [
                'mediaFrameTitle' => __( 'Wybierz grafikę', 'estate-office-crm' ),
                'nonce'           => wp_create_nonce( 'estate_office_crm' ),
            ]
        );
    }

    /**
     * Render main dashboard placeholder.
     */
    public function render_dashboard_page(): void {
        $clients_count   = ( new Clients_Repository() )->count();
        $contracts_count = ( new Contracts_Repository() )->count();
        $properties_count = ( new Properties_Repository() )->count();
        $searches_count  = ( new Searches_Repository() )->count();

        require __DIR__ . '/views/dashboard.php';
    }

    /**
     * Render agents page.
     */
    public function render_agents_page(): void {
        ( new Agents_Page() )->render();
    }

    /**
     * Render clients page.
     */
    public function render_clients_page(): void {
        ( new Clients_Page() )->render();
    }

    /**
     * Render contracts page.
     */
    public function render_contracts_page(): void {
        ( new Contracts_Page() )->render();
    }

    /**
     * Render properties page.
     */
    public function render_properties_page(): void {
        ( new Properties_Page() )->render();
    }

    /**
     * Render searches page.
     */
    public function render_searches_page(): void {
        ( new Searches_Page() )->render();
    }

    /**
     * Render settings page.
     */
    public function render_settings_page(): void {
        ( new Settings_Page() )->render();
    }

    /**
     * Render about page.
     */
    public function render_about_page(): void {
        ( new About_Page() )->render();
    }

    /**
     * Render license page placeholder.
     */
    public function render_license_page(): void {
        require __DIR__ . '/views/license.php';
    }
}
