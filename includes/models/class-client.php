<?php
/**
 * Client model.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Represents a CRM client.
 */
class Client {
    /**
     * Sanitize client data.
     *
     * @param array<string,mixed> $data Raw data.
     *
     * @return array<string,mixed>
     */
    public function sanitize( array $data ): array {
        return [
            'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
            'email'      => sanitize_email( $data['email'] ?? '' ),
            'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
            'notes'      => wp_kses_post( $data['notes'] ?? '' ),
        ];
    }
}
