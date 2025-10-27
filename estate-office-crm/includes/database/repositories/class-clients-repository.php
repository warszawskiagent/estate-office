<?php
/**
 * Repository for clients.
 *
 * @package EstateOfficeCRM\Database\Repositories
 */

namespace EstateOfficeCRM\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD operations for clients entity.
 */
class Clients_Repository extends Base_Repository {
    protected function get_table_name(): string {
        return 'eo_clients';
    }

    protected function get_columns(): array {
        return [
            'id',
            'client_type',
            'primary_name',
            'contact_phone',
            'contact_email',
            'company_name',
            'representative',
            'meta',
            'address',
            'correspondence',
            'created_at',
            'updated_at',
        ];
    }

    protected function format_item( object $item ): array {
        $data = parent::format_item( $item );

        $data['meta']          = $data['meta'] ? json_decode( $data['meta'], true ) : [];
        $data['address']       = $data['address'] ? json_decode( $data['address'], true ) : [];
        $data['correspondence'] = $data['correspondence'] ? json_decode( $data['correspondence'], true ) : [];

        return $data;
    }
}
