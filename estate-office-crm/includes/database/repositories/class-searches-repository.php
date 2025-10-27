<?php
/**
 * Repository for searches.
 *
 * @package EstateOfficeCRM\Database\Repositories
 */

namespace EstateOfficeCRM\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Manage search criteria records.
 */
class Searches_Repository extends Base_Repository {
    protected function get_table_name(): string {
        return 'eo_searches';
    }

    protected function get_columns(): array {
        return [
            'id',
            'agent_id',
            'contract_id',
            'transaction_type',
            'budget_min',
            'budget_max',
            'size_min',
            'size_max',
            'rooms_min',
            'rooms_max',
            'criteria',
            'description',
            'created_at',
            'updated_at',
        ];
    }

    protected function format_item( object $item ): array {
        $data = parent::format_item( $item );

        $data['criteria'] = $data['criteria'] ? json_decode( $data['criteria'], true ) : [];

        return $data;
    }
}
