<?php
/**
 * Contract controller.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Controllers;

use EstateOffice\Database\DB;
use EstateOffice\Models\Contract;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles CRUD for contracts.
 */
class Contract_Controller {
    /**
     * Create a contract.
     *
     * @param array<string,mixed> $data Contract data.
     *
     * @return int Inserted ID.
     */
    public function create( array $data ): int {
        global $wpdb;

        $model = new Contract();
        $sanitized = $model->sanitize( $data );

        $format = [ '%d', '%d', '%s', '%s', '%s', '%s' ];
        $inserted = $wpdb->insert( DB::table( 'contracts' ), $sanitized, $format );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }
}
