<?php
namespace EstateOffice\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin settings.
 */
class Settings {
    /**
     * Option key.
     */
    const OPTION_KEY = 'estate_office_settings';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Registers plugin settings sections and fields.
     */
    public function register_settings(): void {
        register_setting( 'estate_office_settings', self::OPTION_KEY, [ $this, 'sanitize_settings' ] );

        add_settings_section(
            'estate_office_api_section',
            __( 'Integracje', 'estate-office' ),
            function () {
                echo '<p>' . esc_html__( 'Konfiguracja integracji z zewnętrznymi usługami.', 'estate-office' ) . '</p>';
            },
            'estate_office_settings'
        );

        add_settings_field(
            'google_maps_api_key',
            __( 'Klucz API Map Google', 'estate-office' ),
            [ $this, 'render_text_field' ],
            'estate_office_settings',
            'estate_office_api_section',
            [
                'label_for'   => 'google_maps_api_key',
                'option_name' => self::OPTION_KEY,
                'placeholder' => __( 'Wprowadź klucz API', 'estate-office' ),
            ]
        );

        add_settings_section(
            'estate_office_branding_section',
            __( 'Branding', 'estate-office' ),
            function () {
                echo '<p>' . esc_html__( 'Zarządzaj znakiem wodnym i logotypem biura.', 'estate-office' ) . '</p>';
            },
            'estate_office_settings'
        );

        add_settings_field(
            'watermark_url',
            __( 'Znak wodny', 'estate-office' ),
            [ $this, 'render_media_field' ],
            'estate_office_settings',
            'estate_office_branding_section',
            [
                'label_for'   => 'watermark_url',
                'option_name' => self::OPTION_KEY,
                'button_text' => __( 'Wybierz znak wodny', 'estate-office' ),
            ]
        );

        add_settings_field(
            'office_logo_url',
            __( 'Logo biura', 'estate-office' ),
            [ $this, 'render_media_field' ],
            'estate_office_settings',
            'estate_office_branding_section',
            [
                'label_for'   => 'office_logo_url',
                'option_name' => self::OPTION_KEY,
                'button_text' => __( 'Wybierz logo', 'estate-office' ),
            ]
        );

        add_settings_section(
            'estate_office_fields_section',
            __( 'Pola danych', 'estate-office' ),
            function () {
                echo '<p>' . esc_html__( 'Zdefiniuj dodatkowe pola dla nieruchomości, umów i klientów. Jedno pole na linię.', 'estate-office' ) . '</p>';
            },
            'estate_office_settings'
        );

        add_settings_field(
            'property_fields',
            __( 'Pola nieruchomości', 'estate-office' ),
            [ $this, 'render_textarea_field' ],
            'estate_office_settings',
            'estate_office_fields_section',
            [
                'label_for'   => 'property_fields',
                'option_name' => self::OPTION_KEY,
                'rows'        => 5,
            ]
        );

        add_settings_field(
            'contract_fields',
            __( 'Pola umów', 'estate-office' ),
            [ $this, 'render_textarea_field' ],
            'estate_office_settings',
            'estate_office_fields_section',
            [
                'label_for'   => 'contract_fields',
                'option_name' => self::OPTION_KEY,
                'rows'        => 5,
            ]
        );

        add_settings_field(
            'client_fields',
            __( 'Pola klientów', 'estate-office' ),
            [ $this, 'render_textarea_field' ],
            'estate_office_settings',
            'estate_office_fields_section',
            [
                'label_for'   => 'client_fields',
                'option_name' => self::OPTION_KEY,
                'rows'        => 5,
            ]
        );
    }

    /**
     * Sanitizes the settings array.
     *
     * @param array $input Raw settings.
     *
     * @return array
     */
    public function sanitize_settings( array $input ): array {
        $output = [];
        $output['google_maps_api_key'] = isset( $input['google_maps_api_key'] ) ? sanitize_text_field( $input['google_maps_api_key'] ) : '';
        $output['watermark_url']       = isset( $input['watermark_url'] ) ? esc_url_raw( $input['watermark_url'] ) : '';
        $output['office_logo_url']     = isset( $input['office_logo_url'] ) ? esc_url_raw( $input['office_logo_url'] ) : '';
        $output['property_fields']     = isset( $input['property_fields'] ) ? $this->sanitize_multiline_text( $input['property_fields'] ) : '';
        $output['contract_fields']     = isset( $input['contract_fields'] ) ? $this->sanitize_multiline_text( $input['contract_fields'] ) : '';
        $output['client_fields']       = isset( $input['client_fields'] ) ? $this->sanitize_multiline_text( $input['client_fields'] ) : '';

        return $output;
    }

    /**
     * Sanitizes multiline text.
     */
    private function sanitize_multiline_text( string $value ): string {
        $lines = array_map( 'sanitize_text_field', preg_split( '/\r?\n/', $value ) );
        $lines = array_filter( $lines );

        return implode( "\n", $lines );
    }

    /**
     * Renders a text field.
     */
    public function render_text_field( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';

        printf(
            '<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" placeholder="%4$s" />',
            esc_attr( $args['label_for'] ),
            esc_attr( $args['option_name'] ),
            esc_attr( $value ),
            esc_attr( $placeholder )
        );
    }

    /**
     * Renders a media upload field.
     */
    public function render_media_field( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $value   = isset( $options[ $args['label_for'] ] ) ? esc_url( $options[ $args['label_for'] ] ) : '';
        $button  = isset( $args['button_text'] ) ? $args['button_text'] : __( 'Wybierz plik', 'estate-office' );

        printf(
            '<div class="estate-office-media-field"><input type="url" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" /> <button type="button" class="button estate-office-media-button" data-target="%1$s">%4$s</button></div>',
            esc_attr( $args['label_for'] ),
            esc_attr( $args['option_name'] ),
            esc_attr( $value ),
            esc_html( $button )
        );

        if ( $value ) {
            printf( '<p><img src="%1$s" alt="" style="max-width: 160px; height: auto;" /></p>', esc_url( $value ) );
        }
    }

    /**
     * Renders a textarea field.
     */
    public function render_textarea_field( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
        $rows    = isset( $args['rows'] ) ? (int) $args['rows'] : 5;

        printf(
            '<textarea id="%1$s" name="%2$s[%1$s]" rows="%3$d" class="large-text code">%4$s</textarea>',
            esc_attr( $args['label_for'] ),
            esc_attr( $args['option_name'] ),
            $rows,
            esc_textarea( $value )
        );
    }

    /**
     * Renders settings page.
     */
    public function render_settings_page(): void {
        ?>
        <div class="wrap estate-office-settings">
            <h1><?php esc_html_e( 'Ustawienia Estate Office', 'estate-office' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'estate_office_settings' );
                do_settings_sections( 'estate_office_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
