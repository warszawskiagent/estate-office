<?php
/**
 * Contract model.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Represents a contract entry.
 */
class Contract {
    /**
     * Sanitize contract data.
     *
     * @param array<string,mixed> $data Raw data.
     *
     * @return array<string,mixed>
     */
    public function sanitize( array $data ): array {
        return [
            'client_id'   => absint( $data['client_id'] ?? 0 ),
            'property_id' => absint( $data['property_id'] ?? 0 ),
            'type'        => sanitize_text_field( $data['type'] ?? '' ),
            'valid_from'  => sanitize_text_field( $data['valid_from'] ?? '' ),
            'valid_to'    => sanitize_text_field( $data['valid_to'] ?? '' ),
            'status'      => sanitize_text_field( $data['status'] ?? 'draft' ),
        ];
    }
}
