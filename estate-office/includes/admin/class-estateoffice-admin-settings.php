<?php
namespace EstateOffice\Admin;

use EstateOffice\Roles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings_Page {
    /**
     * Settings option key.
     */
    const OPTION_KEY = 'estate_office_settings';

    /**
     * Render settings page.
     */
    public function render() {
        if ( ! current_user_can( Roles::CAPABILITY ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do zarządzania ustawieniami.', 'estate-office' ) );
        }

        wp_enqueue_media();

        $settings = $this->get_settings();
        $message  = '';
        $error    = '';

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            check_admin_referer( 'estate_office_save_settings', 'estate_office_settings_nonce' );

            $result = $this->save_settings();

            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $message  = $result;
                $settings = $this->get_settings();
            }
        }

        include __DIR__ . '/views/settings.php';
    }

    /**
     * Retrieve plugin settings.
     *
     * @return array
     */
    public function get_settings() {
        $defaults = array(
            'google_maps_api_key'   => '',
            'watermark_attachment'  => 0,
            'logo_attachment'       => 0,
            'property_fields'       => array( 'numer_oferty', 'adres', 'cena', 'metraz' ),
            'contract_fields'       => array( 'numer_umowy', 'typ_transakcji', 'data_zawarcia' ),
            'client_fields'         => array( 'imie', 'nazwisko', 'email', 'telefon' ),
        );

        $settings = get_option( self::OPTION_KEY, array() );

        if ( empty( $settings ) || ! is_array( $settings ) ) {
            $settings = array();
        }

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Save submitted settings.
     *
     * @return string|\WP_Error
     */
    private function save_settings() {
        $settings = array(
            'google_maps_api_key'  => isset( $_POST['google_maps_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['google_maps_api_key'] ) ) : '',
            'watermark_attachment' => isset( $_POST['watermark_attachment'] ) ? absint( $_POST['watermark_attachment'] ) : 0,
            'logo_attachment'      => isset( $_POST['logo_attachment'] ) ? absint( $_POST['logo_attachment'] ) : 0,
            'property_fields'      => $this->sanitize_fields( isset( $_POST['property_fields'] ) ? (array) $_POST['property_fields'] : array() ),
            'contract_fields'      => $this->sanitize_fields( isset( $_POST['contract_fields'] ) ? (array) $_POST['contract_fields'] : array() ),
            'client_fields'        => $this->sanitize_fields( isset( $_POST['client_fields'] ) ? (array) $_POST['client_fields'] : array() ),
        );

        update_option( self::OPTION_KEY, $settings );

        return __( 'Ustawienia zostały zapisane.', 'estate-office' );
    }

    /**
     * Sanitize dynamic field list.
     *
     * @param array $fields
     *
     * @return array
     */
    private function sanitize_fields( array $fields ) {
        $sanitized = array();

        foreach ( $fields as $field ) {
            $field = sanitize_key( wp_unslash( $field ) );

            if ( empty( $field ) ) {
                continue;
            }

            $sanitized[] = $field;
        }

        return array_values( array_unique( $sanitized ) );
    }
}
