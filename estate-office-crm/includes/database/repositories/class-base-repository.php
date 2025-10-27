<?php
/**
 * Base repository with shared CRUD helpers.
 *
 * @package EstateOfficeCRM\Database\Repositories
 */

namespace EstateOfficeCRM\Database\Repositories;

defined( 'ABSPATH' ) || exit;

use wpdb;

/**
 * Provide baseline CRUD operations for plugin entities.
 */
abstract class Base_Repository {
    protected wpdb $db;

    protected string $table;

    public function __construct() {
        global $wpdb;

        $this->db    = $wpdb;
        $this->table = $this->db->prefix . $this->get_table_name();
    }

    /**
     * Table suffix without prefix.
     */
    abstract protected function get_table_name(): string;

    /**
     * Database columns allowed to be persisted.
     */
    abstract protected function get_columns(): array;

    /**
     * Fetch all records ordered by latest update.
     */
    public function all(): array {
        $results = $this->db->get_results( "SELECT * FROM {$this->table} ORDER BY updated_at DESC" );

        return array_map( [ $this, 'format_item' ], $results ?: [] );
    }

    /**
     * Find a single record by id.
     */
    public function find( int $id ): ?array {
        if ( $id <= 0 ) {
            return null;
        }

        $sql    = $this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );
        $result = $this->db->get_row( $sql );

        return $result ? $this->format_item( $result ) : null;
    }

    /**
     * Persist new record.
     */
    public function create( array $data ): int {
        $prepared = $this->filter_columns( $data );

        $this->db->insert( $this->table, $prepared );

        return (int) $this->db->insert_id;
    }

    /**
     * Update record.
     */
    public function update( int $id, array $data ): bool {
        if ( $id <= 0 ) {
            return false;
        }

        $prepared = $this->filter_columns( $data );

        return false !== $this->db->update( $this->table, $prepared, [ 'id' => $id ] );
    }

    /**
     * Delete record.
     */
    public function delete( int $id ): bool {
        if ( $id <= 0 ) {
            return false;
        }

        return false !== $this->db->delete( $this->table, [ 'id' => $id ] );
    }

    /**
     * Count all records.
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) FROM {$this->table}";

        return (int) $this->db->get_var( $sql );
    }

    /**
     * Format database row.
     *
     * @param object $item Row.
     */
    protected function format_item( object $item ): array {
        return (array) $item;
    }

    /**
     * Ensure only known columns are written.
     */
    protected function filter_columns( array $data ): array {
        $columns = array_flip( $this->get_columns() );

        return array_intersect_key( $data, $columns );
    }
}
