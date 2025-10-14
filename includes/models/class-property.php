<?php
/**
 * Property model.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Represents a property entry.
 */
class Property {
    /**
     * Sanitize property data.
     *
     * @param array<string,mixed> $data Raw data.
     *
     * @return array<string,mixed>
     */
    public function sanitize( array $data ): array {
        return [
            'title'      => sanitize_text_field( $data['title'] ?? '' ),
            'status'     => sanitize_text_field( $data['status'] ?? 'draft' ),
            'price'      => (float) ( $data['price'] ?? 0 ),
            'city'       => sanitize_text_field( $data['city'] ?? '' ),
            'street'     => sanitize_text_field( $data['street'] ?? '' ),
            'agent_id'   => absint( $data['agent_id'] ?? 0 ),
            'export_web' => ! empty( $data['export_web'] ) ? 1 : 0,
        ];
    }
}
