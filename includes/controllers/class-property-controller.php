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
    private const COLUMN_FORMATS = [
        'title'      => '%s',
        'status'     => '%s',
        'price'      => '%f',
        'city'       => '%s',
        'street'     => '%s',
        'agent_id'   => '%d',
        'export_web' => '%d',
    ];

    /**
     * Create property record.
     *
     * @param array<string,mixed> $data Property data.
     *
     * @return int Inserted ID.
     */
    public function create( array $data ): int {
        global $wpdb;

        $model     = new Property();
        $sanitized = $model->sanitize( $data );

        $inserted = $wpdb->insert( DB::table( 'properties' ), $sanitized, array_values( self::COLUMN_FORMATS ) );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Retrieve property by ID.
     *
     * @param int $id Property ID.
     *
     * @return array<string,mixed>
     */
    public function get( int $id ): array {
        global $wpdb;

        $query = $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'properties' ) . ' WHERE id = %d LIMIT 1', $id );
        $row   = $wpdb->get_row( $query, ARRAY_A );

        return $row ? $this->prepare_row( $row ) : [];
    }

    /**
     * List properties with pagination and search.
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
            $where  .= ' AND (title LIKE %s OR city LIKE %s OR street LIKE %s)';
            $params  = array_merge( $params, [ $like, $like, $like ] );
        }

        $table = DB::table( 'properties' );
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
     * Update property entry.
     *
     * @param int                   $id   Property ID.
     * @param array<string,mixed> $data Property data.
     *
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        $model       = new Property();
        $sanitized   = $model->sanitize( $data );
        $columns     = array_intersect_key( $sanitized, $data );
        $column_keys = array_keys( $columns );

        if ( empty( $columns ) ) {
            return true;
        }

        $formats = array_map( fn( $key ) => self::COLUMN_FORMATS[ $key ] ?? '%s', $column_keys );

        $updated = $wpdb->update( DB::table( 'properties' ), $columns, [ 'id' => $id ], $formats, [ '%d' ] );

        return false !== $updated;
    }

    /**
     * Delete property entry.
     *
     * @param int $id Property ID.
     *
     * @return bool
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $deleted = $wpdb->delete( DB::table( 'properties' ), [ 'id' => $id ], [ '%d' ] );

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
            'title'      => (string) $row['title'],
            'status'     => (string) $row['status'],
            'price'      => isset( $row['price'] ) ? (float) $row['price'] : 0.0,
            'city'       => (string) $row['city'],
            'street'     => (string) $row['street'],
            'agent_id'   => isset( $row['agent_id'] ) ? (int) $row['agent_id'] : 0,
            'export_web' => ! empty( $row['export_web'] ),
            'created_at' => isset( $row['created_at'] ) ? mysql_to_rfc3339( $row['created_at'] ) : null,
            'updated_at' => isset( $row['updated_at'] ) ? mysql_to_rfc3339( $row['updated_at'] ) : null,
        ];
    }
}
