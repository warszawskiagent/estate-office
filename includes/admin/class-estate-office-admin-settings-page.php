<?php
/**
 * Ekran ustawień EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Admin_Settings_Page
 */
class Estate_Office_Admin_Settings_Page {

    private const PAGE_SLUG  = 'estate-office-settings';
    private const OPTION_KEY = 'estate_office_settings';

    /**
     * Rejestruje hooki formularzy ustawień.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_post_estate_office_save_settings', [ $this, 'handle_save_settings' ] );
    }

    /**
     * Renderuje stronę ustawień.
     *
     * @return void
     */
    public function render_page() : void {
        if ( ! current_user_can( 'manage_estate_office_settings' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do modyfikowania ustawień.', 'estate-office' ) );
        }

        $settings = $this->get_settings();

        echo '<div class="wrap estate-office-settings">';
        echo '<h1>' . esc_html__( 'Ustawienia EstateOffice', 'estate-office' ) . '</h1>';

        if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Ustawienia zostały zapisane.', 'estate-office' ) . '</p></div>';
        }

        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'estate_office_save_settings' );
        echo '<input type="hidden" name="action" value="estate_office_save_settings" />';

        echo '<h2 class="title">' . esc_html__( 'Integracje', 'estate-office' ) . '</h2>';
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="google_maps_api_key">' . esc_html__( 'Klucz API Map Google', 'estate-office' ) . '</label></th><td>';
        echo '<input name="google_maps_api_key" id="google_maps_api_key" type="text" class="regular-text" value="' . esc_attr( $settings['google_maps_api_key'] ) . '" />';
        echo '<p class="description">' . esc_html__( 'Wprowadź klucz API, aby aktywować integrację z Mapami Google w formularzach nieruchomości.', 'estate-office' ) . '</p>';
        echo '</td></tr>';
        echo '</table>';

        echo '<h2 class="title">' . esc_html__( 'Branding', 'estate-office' ) . '</h2>';
        echo '<table class="form-table">';

        echo '<tr><th scope="row">' . esc_html__( 'Znak wodny', 'estate-office' ) . '</th><td>';
        if ( $settings['watermark_id'] ) {
            echo wp_get_attachment_image( $settings['watermark_id'], 'medium', false, [ 'style' => 'max-width:150px;height:auto;' ] );
            echo '<p><label><input type="checkbox" name="remove_watermark" value="1" /> ' . esc_html__( 'Usuń znak wodny', 'estate-office' ) . '</label></p>';
        }
        echo '<input type="file" name="watermark_file" accept="image/png,image/jpeg" />';
        echo '<p class="description">' . esc_html__( 'Prześlij obraz znaku wodnego, który będzie automatycznie nanoszony na zdjęcia nieruchomości.', 'estate-office' ) . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__( 'Logo biura', 'estate-office' ) . '</th><td>';
        if ( $settings['logo_id'] ) {
            echo wp_get_attachment_image( $settings['logo_id'], 'medium', false, [ 'style' => 'max-width:150px;height:auto;' ] );
            echo '<p><label><input type="checkbox" name="remove_logo" value="1" /> ' . esc_html__( 'Usuń logo', 'estate-office' ) . '</label></p>';
        }
        echo '<input type="file" name="logo_file" accept="image/png,image/jpeg, image/svg+xml" />';
        echo '<p class="description">' . esc_html__( 'Logo będzie wykorzystywane w panelu oraz przy eksporcie ofert.', 'estate-office' ) . '</p>';
        echo '</td></tr>';

        echo '</table>';

        echo '<h2 class="title">' . esc_html__( 'Dynamiczne pola', 'estate-office' ) . '</h2>';
        echo '<p>' . esc_html__( 'Dodaj niestandardowe pola, jedno w linii. Pola zostaną wykorzystane w odpowiednich formularzach.', 'estate-office' ) . '</p>';
        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="property_fields">' . esc_html__( 'Pola nieruchomości', 'estate-office' ) . '</label></th><td>';
        echo '<textarea name="property_fields" id="property_fields" class="large-text code" rows="5">' . esc_textarea( implode( "\n", $settings['property_fields'] ) ) . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="contract_fields">' . esc_html__( 'Pola umów', 'estate-office' ) . '</label></th><td>';
        echo '<textarea name="contract_fields" id="contract_fields" class="large-text code" rows="5">' . esc_textarea( implode( "\n", $settings['contract_fields'] ) ) . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="client_fields">' . esc_html__( 'Pola klientów', 'estate-office' ) . '</label></th><td>';
        echo '<textarea name="client_fields" id="client_fields" class="large-text code" rows="5">' . esc_textarea( implode( "\n", $settings['client_fields'] ) ) . '</textarea>';
        echo '</td></tr>';

        echo '</table>';

        submit_button( __( 'Zapisz ustawienia', 'estate-office' ) );
        echo '</form>';
        echo '</div>';
    }

    /**
     * Obsługuje zapis ustawień.
     *
     * @return void
     */
    public function handle_save_settings() : void {
        if ( ! current_user_can( 'manage_estate_office_settings' ) ) {
            wp_die( esc_html__( 'Brak wymaganych uprawnień.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_settings' );

        $settings = $this->get_settings();

        $settings['google_maps_api_key'] = sanitize_text_field( wp_unslash( $_POST['google_maps_api_key'] ?? '' ) );
        $settings['property_fields']     = $this->normalize_fields( wp_unslash( $_POST['property_fields'] ?? '' ) );
        $settings['contract_fields']     = $this->normalize_fields( wp_unslash( $_POST['contract_fields'] ?? '' ) );
        $settings['client_fields']       = $this->normalize_fields( wp_unslash( $_POST['client_fields'] ?? '' ) );
        $settings['watermark_id']        = $this->maybe_handle_upload( 'watermark_file', (int) $settings['watermark_id'], ! empty( $_POST['remove_watermark'] ) );
        $settings['logo_id']             = $this->maybe_handle_upload( 'logo_file', (int) $settings['logo_id'], ! empty( $_POST['remove_logo'] ) );
        $settings['updated_at']          = current_time( 'mysql' );

        update_option( self::OPTION_KEY, $settings );

        Estate_Office_Plugin::log_debug( 'Zaktualizowano ustawienia EstateOffice.', [ 'updated_at' => $settings['updated_at'] ] );

        $redirect_url = add_query_arg(
            [
                'page'    => self::PAGE_SLUG,
                'updated' => 'true',
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Pobiera ustawienia wraz z domyślnymi wartościami.
     *
     * @return array<string,mixed>
     */
    private function get_settings() : array {
        $defaults = [
            'google_maps_api_key' => '',
            'watermark_id'        => 0,
            'logo_id'             => 0,
            'property_fields'     => [],
            'contract_fields'     => [],
            'client_fields'       => [],
            'updated_at'          => '',
        ];

        $stored = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        $settings = wp_parse_args( $stored, $defaults );

        $settings['property_fields'] = array_values( array_filter( array_map( 'strval', $settings['property_fields'] ) ) );
        $settings['contract_fields'] = array_values( array_filter( array_map( 'strval', $settings['contract_fields'] ) ) );
        $settings['client_fields']   = array_values( array_filter( array_map( 'strval', $settings['client_fields'] ) ) );

        return $settings;
    }

    /**
     * Normalizuje pola tekstowe na tablicę wartości.
     *
     * @param string $input Dane z formularza.
     *
     * @return array<int,string>
     */
    private function normalize_fields( string $input ) : array {
        $input = str_replace( [ "\r\n", "\r" ], "\n", $input );
        $lines = explode( "\n", $input );

        $clean = [];
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) {
                continue;
            }
            $clean[] = sanitize_text_field( $line );
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * Obsługuje przesyłanie plików i usuwanie istniejących.
     *
     * @param string $field_name Nazwa pola formularza.
     * @param int    $existing_id Aktualny identyfikator.
     * @param bool   $remove      Czy usunąć plik.
     *
     * @return int
     */
    private function maybe_handle_upload( string $field_name, int $existing_id, bool $remove ) : int {
        if ( $remove ) {
            return 0;
        }

        if ( empty( $_FILES[ $field_name ]['name'] ?? '' ) ) {
            return $existing_id;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( $field_name, 0 );

        if ( is_wp_error( $attachment_id ) ) {
            Estate_Office_Plugin::log_debug( 'Błąd podczas zapisywania pliku ustawień.', [ 'field' => $field_name, 'error' => $attachment_id->get_error_message() ] );
            return $existing_id;
        }

        return (int) $attachment_id;
    }
}
