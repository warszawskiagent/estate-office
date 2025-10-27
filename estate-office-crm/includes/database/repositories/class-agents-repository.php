<?php
/**
 * Repository for managing agents.
 *
 * @package EstateOfficeCRM\Database\Repositories
 */

namespace EstateOfficeCRM\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD operations for agents.
 */
class Agents_Repository extends Base_Repository {
    protected function get_table_name(): string {
        return 'eo_agents';
    }

    protected function get_columns(): array {
        return [
            'id',
            'user_id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'avatar_id',
            'biography',
            'address_line1',
            'address_line2',
            'city',
            'postal_code',
            'country',
            'created_at',
            'updated_at',
        ];
    }
}
