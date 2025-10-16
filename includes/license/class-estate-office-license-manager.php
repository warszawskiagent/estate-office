<?php
/**
 * Menedżer licencji dla EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_License_Manager
 */
class Estate_Office_License_Manager {

    private const OPTION_KEY = 'estate_office_license';
    private const CRON_HOOK  = 'estate_office_license_scheduled_check';

    /**
     * Rejestruje hooki licencji.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( self::CRON_HOOK, [ $this, 'cron_refresh_status' ] );
    }

    /**
     * Zaplanuje cykliczną weryfikację licencji.
     *
     * @return void
     */
    public function schedule_events() : void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
        }
    }

    /**
     * Czyści zaplanowane zdarzenia.
     *
     * @return void
     */
    public function clear_scheduled_events() : void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( false !== $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Pobiera aktualne dane licencji z bazy.
     *
     * @return array<string,mixed>
     */
    public function get_license() : array {
        $defaults = [
            'license_key'  => '',
            'status'       => 'inactive',
            'last_checked' => '',
            'last_error'   => '',
            'last_message' => '',
            'expires_at'   => '',
            'activations'  => [],
        ];

        $stored = get_option( self::OPTION_KEY, [] );

        return wp_parse_args( is_array( $stored ) ? $stored : [], $defaults );
    }

    /**
     * Aktywuje licencję na podstawie wprowadzonego klucza.
     *
     * @param string $license_key Klucz licencyjny.
     *
     * @return array<string,mixed>
     */
    public function activate( string $license_key ) : array {
        $sanitized_key = $this->sanitize_key( $license_key );

        if ( '' === $sanitized_key ) {
            $license = $this->get_license();
            $license['status']       = 'invalid';
            $license['last_error']   = __( 'Klucz licencyjny jest wymagany.', 'estate-office' );
            $license['last_message'] = '';
            $license['last_checked'] = current_time( 'mysql' );

            update_option( self::OPTION_KEY, $license );

            return $license;
        }

        $response = $this->remote_request( 'activate', $sanitized_key );

        return $this->persist_response( $sanitized_key, $response );
    }

    /**
     * Dezaktywuje licencję i usuwa klucz z konfiguracji.
     *
     * @return array<string,mixed>
     */
    public function deactivate() : array {
        $license       = $this->get_license();
        $license_key   = $license['license_key'];
        $response      = [];
        $sanitized_key = $this->sanitize_key( $license_key );

        if ( '' !== $sanitized_key ) {
            $response = $this->remote_request( 'deactivate', $sanitized_key );
        }

        $license['license_key']  = '';
        $license['status']       = 'inactive';
        $license['last_checked'] = current_time( 'mysql' );
        $license['last_message'] = $response['message'] ?? __( 'Licencja została dezaktywowana.', 'estate-office' );
        $license['last_error']   = $response['error'] ?? '';
        $license['expires_at']   = '';
        $license['activations']  = [];

        update_option( self::OPTION_KEY, $license );

        Estate_Office_Plugin::log_debug( 'Dezaktywowano licencję EstateOffice.' );

        return $license;
    }

    /**
     * Ręcznie odświeża status licencji.
     *
     * @return array<string,mixed>
     */
    public function check_status() : array {
        $license     = $this->get_license();
        $license_key = $this->sanitize_key( $license['license_key'] );

        if ( '' === $license_key ) {
            return $license;
        }

        $response = $this->remote_request( 'status', $license_key );

        return $this->persist_response( $license_key, $response );
    }

    /**
     * Wywoływane cyklicznie poprzez WP-Cron.
     *
     * @return void
     */
    public function cron_refresh_status() : void {
        $license = $this->get_license();
        if ( 'active' !== $license['status'] && 'pending' !== $license['status'] ) {
            return;
        }

        $updated = $this->check_status();
        Estate_Office_Plugin::log_debug( 'Automatyczna kontrola licencji EstateOffice.', [ 'status' => $updated['status'] ] );
    }

    /**
     * Sanitizes and normalizes the license key.
     *
     * @param string $license_key Klucz licencyjny.
     *
     * @return string
     */
    private function sanitize_key( string $license_key ) : string {
        $normalized = strtoupper( preg_replace( '/[^A-Z0-9\-]/', '', $license_key ) ?? '' );

        return trim( $normalized );
    }

    /**
     * Utrwala odpowiedź usługi licencjonującej.
     *
     * @param string              $license_key Klucz licencyjny.
     * @param array<string,mixed> $response    Dane zwrócone przez usługę.
     *
     * @return array<string,mixed>
     */
    private function persist_response( string $license_key, array $response ) : array {
        $license                 = $this->get_license();
        $license['license_key']  = $license_key;
        $license['status']       = sanitize_key( $response['status'] ?? 'inactive' );
        $license['last_message'] = isset( $response['message'] ) ? sanitize_text_field( $response['message'] ) : '';
        $license['last_error']   = isset( $response['error'] ) ? sanitize_text_field( $response['error'] ) : '';
        $license['expires_at']   = isset( $response['expires_at'] ) ? sanitize_text_field( $response['expires_at'] ) : '';
        $license['activations']  = $this->sanitize_activations( $response['activations'] ?? [] );
        $license['last_checked'] = current_time( 'mysql' );

        update_option( self::OPTION_KEY, $license );

        Estate_Office_Plugin::log_debug( 'Zapisano dane licencji EstateOffice.', [
            'status'     => $license['status'],
            'expires_at' => $license['expires_at'],
        ] );

        return $license;
    }

    /**
     * Wykonuje żądanie HTTP do serwera licencji.
     *
     * @param string $action      Akcja (activate|status|deactivate).
     * @param string $license_key Klucz licencyjny.
     *
     * @return array<string,mixed>
     */
    private function remote_request( string $action, string $license_key ) : array {
        $endpoint = apply_filters( 'estate_office_license_endpoint', 'https://warszawskiagent.pl/wp-json/estateoffice/v1/license' );

        if ( empty( $endpoint ) ) {
            return $this->offline_response( $license_key );
        }

        $payload = [
            'timeout' => 15,
            'body'    => [
                'license_key' => $license_key,
                'site_url'    => home_url(),
                'action'      => $action,
                'version'     => ESTATE_OFFICE_VERSION,
            ],
        ];

        $response = wp_remote_post( esc_url_raw( $endpoint ), $payload );

        if ( is_wp_error( $response ) ) {
            return $this->offline_response( $license_key, $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || ! is_array( $body ) ) {
            return $this->offline_response( $license_key, __( 'Nieoczekiwana odpowiedź serwera licencji.', 'estate-office' ) );
        }

        $status = isset( $body['status'] ) ? sanitize_key( $body['status'] ) : 'inactive';

        return [
            'status'      => $status,
            'message'     => isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '',
            'error'       => '',
            'expires_at'  => isset( $body['expires_at'] ) ? sanitize_text_field( $body['expires_at'] ) : '',
            'activations' => $this->sanitize_activations( $body['activations'] ?? [] ),
        ];
    }

    /**
     * Fallback offline w przypadku braku połączenia z serwerem licencji.
     *
     * @param string $license_key Klucz licencji.
     * @param string $error       Opcjonalna wiadomość błędu.
     *
     * @return array<string,mixed>
     */
    private function offline_response( string $license_key, string $error = '' ) : array {
        if ( ! $this->is_key_format_valid( $license_key ) ) {
            return [
                'status'  => 'invalid',
                'message' => __( 'Klucz licencji ma nieprawidłowy format.', 'estate-office' ),
                'error'   => $error,
            ];
        }

        return [
            'status'     => 'active',
            'message'    => __( 'Licencję aktywowano w trybie offline. Zalecana ponowna weryfikacja po przywróceniu połączenia.', 'estate-office' ),
            'error'      => $error,
            'expires_at' => '',
        ];
    }

    /**
     * Waliduje format klucza licencyjnego.
     *
     * @param string $license_key Klucz licencji.
     *
     * @return bool
     */
    private function is_key_format_valid( string $license_key ) : bool {
        return (bool) preg_match( '/^EO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license_key );
    }

    /**
     * Sanityzuje listę aktywacji.
     *
     * @param mixed $activations Lista aktywacji.
     *
     * @return array<int,array<string,string>>
     */
    private function sanitize_activations( $activations ) : array {
        if ( ! is_array( $activations ) ) {
            return [];
        }

        $sanitized = [];

        foreach ( $activations as $activation ) {
            if ( ! is_array( $activation ) ) {
                continue;
            }

            $sanitized[] = [
                'site_url'     => isset( $activation['site_url'] ) ? esc_url_raw( $activation['site_url'] ) : '',
                'activated_at' => isset( $activation['activated_at'] ) ? sanitize_text_field( $activation['activated_at'] ) : '',
                'status'       => isset( $activation['status'] ) ? sanitize_key( $activation['status'] ) : '',
            ];
        }

        return $sanitized;
    }
}
