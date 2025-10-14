<?php
/**
 * Property controller.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Controllers;

use EstateOffice\Database\DB;
use EstateOffice\Models\Property;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles CRUD for properties.
 */
class Property_Controller {
    /**
     * Create property record.
     *
     * @param array<string,mixed> $data Property data.
     *
     * @return int Inserted ID.
     */
    public function create( array $data ): int {
        global $wpdb;

        $model = new Property();
        $sanitized = $model->sanitize( $data );

        $format = [ '%s', '%s', '%f', '%s', '%s', '%d', '%d' ];
        $inserted = $wpdb->insert( DB::table( 'properties' ), $sanitized, $format );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }
}
