<?php
/**
 * Repository for contracts.
 *
 * @package EstateOfficeCRM\Database\Repositories
 */

namespace EstateOfficeCRM\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD helper for contracts.
 */
class Contracts_Repository extends Base_Repository {
    protected function get_table_name(): string {
        return 'eo_contracts';
    }

    protected function get_columns(): array {
        return [
            'id',
            'agent_id',
            'contract_number',
            'transaction_type',
            'start_date',
            'end_date',
            'is_open_ended',
            'commission_amount',
            'commission_unit',
            'current_stage',
            'stage_history',
            'created_at',
            'updated_at',
        ];
    }

    protected function format_item( object $item ): array {
        $data = parent::format_item( $item );

        $data['stage_history'] = $data['stage_history'] ? json_decode( $data['stage_history'], true ) : [];

        return $data;
    }

    /**
     * Find contract by number.
     */
    public function find_by_number( string $number, ?int $exclude_id = null ): ?array {
        if ( '' === $number ) {
            return null;
        }

        $sql  = "SELECT * FROM {$this->table} WHERE contract_number = %s";
        $args = [ $number ];

        if ( $exclude_id ) {
            $sql   .= ' AND id != %d';
            $args[] = $exclude_id;
        }

        $prepared = $this->db->prepare( $sql, $args );
        $result   = $this->db->get_row( $prepared );

        return $result ? $this->format_item( $result ) : null;
    }

    public function delete( int $id ): bool {
        $deleted = parent::delete( $id );

        if ( $deleted ) {
            $this->db->delete( $this->db->prefix . 'eo_contract_clients', [ 'contract_id' => $id ] );
            $this->db->delete( $this->db->prefix . 'eo_contract_assets', [ 'contract_id' => $id ] );
        }

        return $deleted;
    }
}
