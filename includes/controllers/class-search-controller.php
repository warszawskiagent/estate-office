<?php
/**
 * Search controller.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Controllers;

use EstateOffice\Database\DB;
use EstateOffice\Models\Search;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles CRUD for saved searches.
 */
class Search_Controller {
    /**
     * Create search record.
     *
     * @param array<string,mixed> $data Search data.
     *
     * @return int Inserted ID.
     */
    public function create( array $data ): int {
        global $wpdb;

        $model = new Search();
        $sanitized = $model->sanitize( $data );

        $format = [ '%d', '%f', '%f', '%s', '%s' ];
        $inserted = $wpdb->insert( DB::table( 'searches' ), $sanitized, $format );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }
}
