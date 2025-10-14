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
    private const COLUMN_FORMATS = [
        'first_name' => '%s',
        'last_name'  => '%s',
        'email'      => '%s',
        'phone'      => '%s',
        'notes'      => '%s',
    ];

    /**
     * Create a client record.
     *
     * @param array<string,mixed> $data Client data.
     *
     * @return int Inserted ID.
     */
    public function create( array $data ): int {
        global $wpdb;

        $model     = new Client();
        $sanitized = $model->sanitize( $data );

        $inserted = $wpdb->insert( DB::table( 'clients' ), $sanitized, array_values( self::COLUMN_FORMATS ) );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Retrieve a client by ID.
     *
     * @param int $id Client ID.
     *
     * @return array<string,mixed>
     */
    public function get( int $id ): array {
        global $wpdb;

        $query = $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'clients' ) . ' WHERE id = %d LIMIT 1', $id );
        $row   = $wpdb->get_row( $query, ARRAY_A );

        return $row ? $this->prepare_row( $row ) : [];
    }

    /**
     * List clients with pagination.
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
        $search   = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';

        $where  = 'WHERE 1=1';
        $params = [];

        if ( '' !== $search ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $where  .= ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)';
            $params  = array_merge( $params, [ $like, $like, $like ] );
        }

        $table = DB::table( 'clients' );
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
     * Update a client record.
     *
     * @param int                   $id   Client ID.
     * @param array<string,mixed> $data Client data.
     *
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        $model       = new Client();
        $sanitized   = $model->sanitize( $data );
        $columns     = array_intersect_key( $sanitized, $data );
        $column_keys = array_keys( $columns );

        if ( empty( $columns ) ) {
            return true;
        }

        $formats = array_map( fn( $key ) => self::COLUMN_FORMATS[ $key ] ?? '%s', $column_keys );

        $updated = $wpdb->update( DB::table( 'clients' ), $columns, [ 'id' => $id ], $formats, [ '%d' ] );

        return false !== $updated;
    }

    /**
     * Delete client entry.
     *
     * @param int $id Client ID.
     *
     * @return bool
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $deleted = $wpdb->delete( DB::table( 'clients' ), [ 'id' => $id ], [ '%d' ] );

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
            'id'         => (int) $row['id'],
            'first_name' => (string) $row['first_name'],
            'last_name'  => (string) $row['last_name'],
            'email'      => (string) $row['email'],
            'phone'      => (string) $row['phone'],
            'notes'      => (string) $row['notes'],
            'created_at' => isset( $row['created_at'] ) ? mysql_to_rfc3339( $row['created_at'] ) : null,
            'updated_at' => isset( $row['updated_at'] ) ? mysql_to_rfc3339( $row['updated_at'] ) : null,
        ];
    }
}
