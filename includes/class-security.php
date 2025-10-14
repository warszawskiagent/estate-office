<?php
/**
 * Security utilities for the plugin.
 *
 * @package EstateOffice
 */

namespace EstateOffice;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Security hardening helper.
 */
class Security {
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg|jpeg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf',
    ];

    /**
     * Adds a strict Content Security Policy header for CRM pages.
     *
     * @return void
     */
    public function register_content_security_policy(): void {
        if ( $this->is_crm_request() ) {
            header( "Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline';" );
        }
    }

    /**
     * Restrict allowed mime types for uploads initiated from the plugin UI.
     *
     * @param array<string,string> $mimes Existing mime types.
     *
     * @return array<string,string>
     */
    public function restrict_mime_types( array $mimes ): array {
        if ( ! $this->is_estate_office_request() ) {
            return $mimes;
        }

        return array_merge( $mimes, self::ALLOWED_MIME_TYPES );
    }

    /**
     * Validate upload before handling.
     *
     * @param array<string,string> $file File data.
     *
     * @return array<string,string>
     */
    public function validate_upload( array $file ): array {
        if ( ! $this->is_estate_office_request() ) {
            return $file;
        }

        $mime = $file['type'] ?? '';
        if ( ! array_key_exists( $mime, self::ALLOWED_MIME_TYPES ) ) {
            $file['error'] = __( 'Ten typ pliku nie jest dozwolony ze względów bezpieczeństwa.', 'estate-office' );
        }

        return $file;
    }

    /**
     * Detect CRM requests to limit CSP header scope.
     *
     * @return bool
     */
    private function is_crm_request(): bool {
        if ( is_admin() ) {
            return false;
        }

        $request_uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );
        if ( null === $request_uri ) {
            return false;
        }

        return str_contains( $request_uri, 'crm' );
    }

    /**
     * Determine if current request originates from plugin UI.
     *
     * @return bool
     */
    private function is_estate_office_request(): bool {
        $nonce = filter_input( INPUT_POST, 'estate_office_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( $nonce && wp_verify_nonce( $nonce, 'estate_office_admin_view' ) ) {
            return true;
        }

        $referer = wp_get_referer();
        if ( is_string( $referer ) && str_contains( $referer, 'estate-office' ) ) {
            return true;
        }

        return false;
    }
}
