<?php
/**
 * Search model.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Represents a saved search entry.
 */
class Search {
    /**
     * Sanitize search data.
     *
     * @param array<string,mixed> $data Raw data.
     *
     * @return array<string,mixed>
     */
    public function sanitize( array $data ): array {
        return [
            'client_id'     => absint( $data['client_id'] ?? 0 ),
            'budget_min'    => (float) ( $data['budget_min'] ?? 0 ),
            'budget_max'    => (float) ( $data['budget_max'] ?? 0 ),
            'location'      => sanitize_text_field( $data['location'] ?? '' ),
            'property_type' => sanitize_text_field( $data['property_type'] ?? '' ),
        ];
    }
}
