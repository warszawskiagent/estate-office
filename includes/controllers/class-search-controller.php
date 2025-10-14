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
    private const COLUMN_FORMATS = [
        'client_id'     => '%d',
        'budget_min'    => '%f',
        'budget_max'    => '%f',
        'location'      => '%s',
        'property_type' => '%s',
    ];

    /**
     * Create search record.
     *
     * @param array<string,mixed> $data Search data.
     *
     * @return int Inserted ID.
     */
    public function create( array $data ): int {
        global $wpdb;

        $model     = new Search();
        $sanitized = $model->sanitize( $data );

        $inserted = $wpdb->insert( DB::table( 'searches' ), $sanitized, array_values( self::COLUMN_FORMATS ) );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Retrieve a search entry.
     *
     * @param int $id Search ID.
     *
     * @return array<string,mixed>
     */
    public function get( int $id ): array {
        global $wpdb;

        $query = $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'searches' ) . ' WHERE id = %d LIMIT 1', $id );
        $row   = $wpdb->get_row( $query, ARRAY_A );

        return $row ? $this->prepare_row( $row ) : [];
    }

    /**
     * List saved searches with pagination and optional client filter.
     *
     * @param array<string,mixed> $args Query args.
     *
     * @return array<string,mixed>
     */
    public function all( array $args = [] ): array {
        global $wpdb;

        $per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
        $page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
        $offset   = ( $page - 1 ) * $per_page;
        $client   = isset( $args['client_id'] ) ? absint( $args['client_id'] ) : 0;
        $location = isset( $args['location'] ) ? sanitize_text_field( (string) $args['location'] ) : '';

        $where  = 'WHERE 1=1';
        $params = [];

        if ( $client > 0 ) {
            $where   .= ' AND client_id = %d';
            $params[] = $client;
        }

        if ( '' !== $location ) {
            $where   .= ' AND location LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $location ) . '%';
        }

        $table = DB::table( 'searches' );
        $sql   = "SELECT SQL_CALC_FOUND_ROWS * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $query = $wpdb->prepare( $sql, $params );
        $rows  = $wpdb->get_results( $query, ARRAY_A );

        $total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

        return [
            'items'    => array_map( fn( $row ) => $this->prepare_row( $row ), $rows ?? [] ),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * Update a search entry.
     *
     * @param int                   $id   Search ID.
     * @param array<string,mixed> $data Search data.
     *
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        $model       = new Search();
        $sanitized   = $model->sanitize( $data );
        $columns     = array_intersect_key( $sanitized, $data );
        $column_keys = array_keys( $columns );

        if ( empty( $columns ) ) {
            return true;
        }

        $formats = array_map( fn( $key ) => self::COLUMN_FORMATS[ $key ] ?? '%s', $column_keys );

        $updated = $wpdb->update( DB::table( 'searches' ), $columns, [ 'id' => $id ], $formats, [ '%d' ] );

        return false !== $updated;
    }

    /**
     * Delete a search entry.
     *
     * @param int $id Search ID.
     *
     * @return bool
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $deleted = $wpdb->delete( DB::table( 'searches' ), [ 'id' => $id ], [ '%d' ] );

        return (bool) $deleted;
    }

    /**
     * Prepare row for API output.
     *
     * @param array<string,mixed> $row Database row.
     *
     * @return array<string,mixed>
     */
    private function prepare_row( array $row ): array {
        return [
            'id'           => (int) $row['id'],
            'client_id'    => (int) $row['client_id'],
            'budget_min'   => isset( $row['budget_min'] ) ? (float) $row['budget_min'] : 0.0,
            'budget_max'   => isset( $row['budget_max'] ) ? (float) $row['budget_max'] : 0.0,
            'location'     => (string) $row['location'],
            'property_type' => (string) $row['property_type'],
            'created_at'   => isset( $row['created_at'] ) ? mysql_to_rfc3339( $row['created_at'] ) : null,
            'updated_at'   => isset( $row['updated_at'] ) ? mysql_to_rfc3339( $row['updated_at'] ) : null,
        ];
    }
}
