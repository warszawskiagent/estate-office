<?php
/**
 * Repository for properties.
 *
 * @package EstateOfficeCRM\Database\Repositories
 */

namespace EstateOfficeCRM\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Manage property records.
 */
class Properties_Repository extends Base_Repository {
    protected function get_table_name(): string {
        return 'eo_properties';
    }

    protected function get_columns(): array {
        return [
            'id',
            'agent_id',
            'contract_id',
            'transaction_type',
            'property_type',
            'title',
            'address',
            'legal_status',
            'price',
            'administration_fee',
            'size_total',
            'price_per_sqm',
            'rooms',
            'bedrooms',
            'bathrooms',
            'floors',
            'storey',
            'build_year',
            'lot_shape',
            'lot_dimensions',
            'attributes',
            'description',
            'media',
            'tags',
            'export_web',
            'export_portals',
            'created_at',
            'updated_at',
        ];
    }

    protected function format_item( object $item ): array {
        $data = parent::format_item( $item );

        $data['address']        = $data['address'] ? json_decode( $data['address'], true ) : [];
        $data['attributes']     = $data['attributes'] ? json_decode( $data['attributes'], true ) : [];
        $data['media']          = $data['media'] ? json_decode( $data['media'], true ) : [];
        $data['tags']           = $data['tags'] ? json_decode( $data['tags'], true ) : [];
        $data['lot_dimensions'] = $data['lot_dimensions'] ? json_decode( $data['lot_dimensions'], true ) : [];

        return $data;
    }
}
