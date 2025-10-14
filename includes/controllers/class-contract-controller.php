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
    private const COLUMN_FORMATS = [
        'client_id'   => '%d',
        'property_id' => '%d',
        'type'        => '%s',
        'valid_from'  => '%s',
        'valid_to'    => '%s',
        'status'      => '%s',
    ];

    /**
     * Create a contract.
     *
     * @param array<string,mixed> $data Contract data.
     *
     * @return int Inserted ID.
     */
    public function create( array $data ): int {
        global $wpdb;

        $model     = new Contract();
        $sanitized = $model->sanitize( $data );

        $inserted = $wpdb->insert( DB::table( 'contracts' ), $sanitized, array_values( self::COLUMN_FORMATS ) );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Retrieve contract by ID.
     *
     * @param int $id Contract ID.
     *
     * @return array<string,mixed>
     */
    public function get( int $id ): array {
        global $wpdb;

        $query = $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'contracts' ) . ' WHERE id = %d LIMIT 1', $id );
        $row   = $wpdb->get_row( $query, ARRAY_A );

        return $row ? $this->prepare_row( $row ) : [];
    }

    /**
     * List contracts with pagination and optional filtering by status or client.
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
        $status   = isset( $args['status'] ) ? sanitize_text_field( (string) $args['status'] ) : '';
        $client   = isset( $args['client_id'] ) ? absint( $args['client_id'] ) : 0;

        $where  = 'WHERE 1=1';
        $params = [];

        if ( '' !== $status ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }

        if ( $client > 0 ) {
            $where   .= ' AND client_id = %d';
            $params[] = $client;
        }

        $table = DB::table( 'contracts' );
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
     * Update contract entry.
     *
     * @param int                   $id   Contract ID.
     * @param array<string,mixed> $data Contract data.
     *
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        $model       = new Contract();
        $sanitized   = $model->sanitize( $data );
        $columns     = array_intersect_key( $sanitized, $data );
        $column_keys = array_keys( $columns );

        if ( empty( $columns ) ) {
            return true;
        }

        $formats = array_map( fn( $key ) => self::COLUMN_FORMATS[ $key ] ?? '%s', $column_keys );

        $updated = $wpdb->update( DB::table( 'contracts' ), $columns, [ 'id' => $id ], $formats, [ '%d' ] );

        return false !== $updated;
    }

    /**
     * Delete contract entry.
     *
     * @param int $id Contract ID.
     *
     * @return bool
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $deleted = $wpdb->delete( DB::table( 'contracts' ), [ 'id' => $id ], [ '%d' ] );

        return (bool) $deleted;
    }

    /**
     * Normalize database row for API output.
     *
     * @param array<string,mixed> $row Database row.
     *
     * @return array<string,mixed>
     */
    private function prepare_row( array $row ): array {
        return [
            'id'          => (int) $row['id'],
            'client_id'   => (int) $row['client_id'],
            'property_id' => isset( $row['property_id'] ) ? (int) $row['property_id'] : 0,
            'type'        => (string) $row['type'],
            'valid_from'  => (string) ( $row['valid_from'] ?? '' ),
            'valid_to'    => (string) ( $row['valid_to'] ?? '' ),
            'status'      => (string) $row['status'],
            'created_at'  => isset( $row['created_at'] ) ? mysql_to_rfc3339( $row['created_at'] ) : null,
            'updated_at'  => isset( $row['updated_at'] ) ? mysql_to_rfc3339( $row['updated_at'] ) : null,
        ];
    }
}
