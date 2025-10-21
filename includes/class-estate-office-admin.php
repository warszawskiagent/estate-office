<?php
/**
 * Admin menu and page handler.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EstateOffice_Admin
 */
class EstateOffice_Admin {

    /**
     * Settings handler.
     *
     * @var EstateOffice_Settings
     */
    protected $settings;

    /**
     * EstateOffice_Admin constructor.
     *
     * @param EstateOffice_Settings $settings Settings handler instance.
     */
    public function __construct( EstateOffice_Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Register admin hooks.
     *
     * @return void
     */
    public function register_hooks() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings_sections' ] );
        add_action( 'admin_post_estate_office_save_settings', [ $this, 'handle_settings_save' ] );
    }

    /**
     * Register admin menu structure.
     *
     * @return void
     */
    public function register_menu() {
        $capability = 'manage_estate_office';

        add_menu_page(
            __( 'Estate Office CRM', 'estate-office' ),
            __( 'Estate Office CRM', 'estate-office' ),
            $capability,
            'estate-office-crm',
            [ $this, 'render_dashboard_page' ],
            'dashicons-admin-multisite',
            58
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Pulpit', 'estate-office' ),
            __( 'Pulpit', 'estate-office' ),
            $capability,
            'estate-office-crm',
            [ $this, 'render_dashboard_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Nieruchomości', 'estate-office' ),
            __( 'Nieruchomości', 'estate-office' ),
            $capability,
            'estate-office-properties',
            [ $this, 'render_properties_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Poszukiwania', 'estate-office' ),
            __( 'Poszukiwania', 'estate-office' ),
            $capability,
            'estate-office-searches',
            [ $this, 'render_searches_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Umowy', 'estate-office' ),
            __( 'Umowy', 'estate-office' ),
            $capability,
            'estate-office-agreements',
            [ $this, 'render_agreements_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Klienci', 'estate-office' ),
            __( 'Klienci', 'estate-office' ),
            $capability,
            'estate-office-clients',
            [ $this, 'render_clients_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Agenci', 'estate-office' ),
            __( 'Agenci', 'estate-office' ),
            $capability,
            'estate-office-agents',
            [ $this, 'render_agents_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Ustawienia', 'estate-office' ),
            __( 'Ustawienia', 'estate-office' ),
            $capability,
            'estate-office-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'Licencja', 'estate-office' ),
            __( 'Licencja', 'estate-office' ),
            $capability,
            'estate-office-license',
            [ $this, 'render_license_page' ]
        );

        add_submenu_page(
            'estate-office-crm',
            __( 'O wtyczce', 'estate-office' ),
            __( 'About', 'estate-office' ),
            $capability,
            'estate-office-about',
            [ $this, 'render_about_page' ]
        );
    }

    /**
     * Register settings sections.
     *
     * @return void
     */
    public function register_settings_sections() {
        register_setting( 'estate_office_settings', 'estate_office_google_maps_api_key' );
        register_setting( 'estate_office_settings', 'estate_office_watermark_attachment_id', 'intval' );
        register_setting( 'estate_office_settings', 'estate_office_office_logo_attachment_id', 'intval' );
        register_setting( 'estate_office_settings', 'estate_office_property_dynamic_fields', [ $this, 'sanitize_dynamic_fields' ] );
        register_setting( 'estate_office_settings', 'estate_office_agreement_dynamic_fields', [ $this, 'sanitize_dynamic_fields' ] );
        register_setting( 'estate_office_settings', 'estate_office_client_dynamic_fields', [ $this, 'sanitize_dynamic_fields' ] );
    }

    /**
     * Sanitize dynamic fields array.
     *
     * @param array|string $value Value to sanitize.
     *
     * @return array
     */
    public function sanitize_dynamic_fields( $value ) {
        if ( is_string( $value ) ) {
            $value = wp_unslash( $value );
            $value = json_decode( $value, true );
        }

        if ( ! is_array( $value ) ) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static function ( $field ) {
                        if ( empty( $field['key'] ) || empty( $field['label'] ) ) {
                            return null;
                        }

                        return [
                            'key'   => sanitize_key( $field['key'] ),
                            'label' => sanitize_text_field( $field['label'] ),
                            'type'  => isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text',
                        ];
                    },
                    $value
                )
            )
        );
    }

    /**
     * Handle settings save for media fields.
     *
     * @return void
     */
    public function handle_settings_save() {
        if ( ! current_user_can( 'manage_estate_office' ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_settings', 'estate_office_nonce' );

        $options = [
            'estate_office_google_maps_api_key'      => sanitize_text_field( wp_unslash( $_POST['google_maps_api_key'] ?? '' ) ),
            'estate_office_watermark_attachment_id'  => absint( $_POST['watermark_attachment_id'] ?? 0 ),
            'estate_office_office_logo_attachment_id'=> absint( $_POST['office_logo_attachment_id'] ?? 0 ),
        ];

        foreach ( $options as $name => $value ) {
            update_option( $name, $value );
        }

        if ( isset( $_POST['property_dynamic_fields'] ) ) {
            update_option( 'estate_office_property_dynamic_fields', $this->sanitize_dynamic_fields( wp_unslash( $_POST['property_dynamic_fields'] ) ) );
        }

        if ( isset( $_POST['agreement_dynamic_fields'] ) ) {
            update_option( 'estate_office_agreement_dynamic_fields', $this->sanitize_dynamic_fields( wp_unslash( $_POST['agreement_dynamic_fields'] ) ) );
        }

        if ( isset( $_POST['client_dynamic_fields'] ) ) {
            update_option( 'estate_office_client_dynamic_fields', $this->sanitize_dynamic_fields( wp_unslash( $_POST['client_dynamic_fields'] ) ) );
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=estate-office-settings&updated=true' ) );
        exit;
    }

    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render_dashboard_page() {
        $this->render_template( 'admin/dashboard', [] );
    }

    /**
     * Render properties page.
     *
     * @return void
     */
    public function render_properties_page() {
        $this->render_template( 'admin/properties', [] );
    }

    /**
     * Render searches page.
     *
     * @return void
     */
    public function render_searches_page() {
        $this->render_template( 'admin/searches', [] );
    }

    /**
     * Render agreements page.
     *
     * @return void
     */
    public function render_agreements_page() {
        $this->render_template( 'admin/agreements', [] );
    }

    /**
     * Render clients page.
     *
     * @return void
     */
    public function render_clients_page() {
        $this->render_template( 'admin/clients', [] );
    }

    /**
     * Render agents page.
     *
     * @return void
     */
    public function render_agents_page() {
        $this->render_template( 'admin/agents', [] );
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        $data = [
            'google_maps_api_key'      => get_option( 'estate_office_google_maps_api_key', '' ),
            'watermark_attachment_id'  => (int) get_option( 'estate_office_watermark_attachment_id', 0 ),
            'office_logo_attachment_id'=> (int) get_option( 'estate_office_office_logo_attachment_id', 0 ),
            'property_fields'          => get_option( 'estate_office_property_dynamic_fields', [] ),
            'agreement_fields'         => get_option( 'estate_office_agreement_dynamic_fields', [] ),
            'client_fields'            => get_option( 'estate_office_client_dynamic_fields', [] ),
        ];

        wp_enqueue_media();
        $this->render_template( 'admin/settings', $data );
    }

    /**
     * Render license page placeholder.
     *
     * @return void
     */
    public function render_license_page() {
        $this->render_template( 'admin/license', [] );
    }

    /**
     * Render about page.
     *
     * @return void
     */
    public function render_about_page() {
        $data = [
            'roadmap' => get_option( 'estate_office_roadmap', [] ),
        ];

        $this->render_template( 'admin/about', $data );
    }

    /**
     * Render template helper.
     *
     * @param string $template Template path relative to templates directory.
     * @param array  $data     Data passed to template.
     *
     * @return void
     */
    protected function render_template( $template, array $data ) {
        if ( ! current_user_can( 'view_estate_office' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do przeglądania tej strony.', 'estate-office' ) );
        }

        $template_file = ESTATE_OFFICE_PLUGIN_DIR . 'templates/' . $template . '.php';

        if ( ! file_exists( $template_file ) ) {
            printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Brak szablonu widoku.', 'estate-office' ) );
            return;
        }

        $data = apply_filters( 'estate_office_admin_template_data', $data, $template );

        extract( $data, EXTR_SKIP );

        include $template_file;
    }
}
