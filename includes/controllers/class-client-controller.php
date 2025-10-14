<?php
/**
 * Client controller.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Controllers;

use EstateOffice\Database\DB;
use EstateOffice\Models\Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles CRUD for clients.
 */
class Client_Controller {
    /**
     * Create a client record.
     *
     * @param array<string,mixed> $data Client data.
     *
     * @return int Inserted ID.
     */
    public function create( array $data ): int {
        global $wpdb;

        $model = new Client();
        $sanitized = $model->sanitize( $data );

        $inserted = $wpdb->insert( DB::table( 'clients' ), $sanitized, [ '%s', '%s', '%s', '%s', '%s' ] );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }
}
