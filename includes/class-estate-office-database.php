<?php
/**
 * Database CRUD helper.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EstateOffice_Database
 */
class EstateOffice_Database {

    /**
     * Holds singleton instance.
     *
     * @var EstateOffice_Database|null
     */
    protected static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return EstateOffice_Database
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Save agreement data.
     *
     * @param array $data Agreement data.
     *
     * @return int Agreement ID.
     */
    public function save_agreement( array $data ) {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_agreements';

        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE contract_number = %s", $data['contract_number'] ) );
        if ( $existing ) {
            wp_die( esc_html__( 'Umowa o podanym numerze już istnieje.', 'estate-office' ) );
        }

        $now = current_time( 'mysql' );

        $wpdb->insert(
            $table,
            [
                'contract_number'   => $data['contract_number'],
                'transaction_type'  => $data['transaction_type'],
                'start_date'        => $data['start_date'],
                'end_date'          => $data['open_ended'] ? null : $data['end_date'],
                'open_ended'        => $data['open_ended'],
                'commission_amount' => $data['commission_amount'],
                'commission_unit'   => $data['commission_unit'],
                'stage'             => 'umowa_posrednictwa',
                'stage_history'     => wp_json_encode(
                    [
                        [
                            'stage' => 'umowa_posrednictwa',
                            'date'  => $data['start_date'],
                        ],
                    ]
                ),
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Save client data.
     *
     * @param array $data Client data.
     *
     * @return int Client ID.
     */
    public function save_client( array $data ) {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_clients';
        $now   = current_time( 'mysql' );

        if ( ! empty( $data['id'] ) ) {
            $wpdb->update(
                $table,
                [
                    'type'           => $data['type'],
                    'first_name'     => $data['first_name'],
                    'last_name'      => $data['last_name'],
                    'company_name'   => $data['company_name'],
                    'representative' => $data['representative'],
                    'phone'          => $data['phone'],
                    'email'          => $data['email'],
                    'website'        => $data['website'],
                    'identification' => $data['identification'],
                    'address'        => $data['address'],
                    'correspondence' => $data['correspondence'],
                    'updated_at'     => $now,
                ],
                [ 'id' => (int) $data['id'] ],
                [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );

            return (int) $data['id'];
        }

        $wpdb->insert(
            $table,
            [
                'type'           => $data['type'],
                'first_name'     => $data['first_name'],
                'last_name'      => $data['last_name'],
                'company_name'   => $data['company_name'],
                'representative' => $data['representative'],
                'phone'          => $data['phone'],
                'email'          => $data['email'],
                'website'        => $data['website'],
                'identification' => $data['identification'],
                'address'        => $data['address'],
                'correspondence' => $data['correspondence'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Save property data.
     *
     * @param array $data Property data.
     *
     * @return int Property ID.
     */
    public function save_property( array $data ) {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_properties';
        $now   = current_time( 'mysql' );

        $wpdb->insert(
            $table,
            [
                'agreement_id'     => $data['agreement_id'],
                'transaction_type' => $data['transaction_type'],
                'property_type'    => $data['property_type'],
                'address'          => $data['address'],
                'legal_status'     => $data['legal_status'],
                'registry_number'  => $data['registry_number'],
                'price'            => $data['price'],
                'rent'             => $data['rent'],
                'area'             => $data['area'],
                'price_per_sqm'    => $data['price_per_sqm'],
                'details'          => $data['details'],
                'description'      => $data['description'],
                'amenities'        => $data['amenities'],
                'surfaces'         => $data['surfaces'],
                'media'            => $data['media'],
                'tags'             => $data['tags'],
                'export_www'       => $data['export_www'],
                'export_portals'   => $data['export_portals'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Attach client to agreement.
     *
     * @param int $agreement_id Agreement ID.
     * @param int $client_id    Client ID.
     *
     * @return void
     */
    public function attach_client_to_agreement( $agreement_id, $client_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_agreement_clients';

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE agreement_id = %d AND client_id = %d",
                $agreement_id,
                $client_id
            )
        );

        if ( $exists ) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'agreement_id' => $agreement_id,
                'client_id'    => $client_id,
            ],
            [ '%d', '%d' ]
        );
    }

    /**
     * Retrieve agreement by ID.
     *
     * @param int $agreement_id Agreement ID.
     *
     * @return array|null
     */
    public function get_agreement( $agreement_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_agreements';

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $agreement_id ), ARRAY_A );

        return $row ?: null;
    }

    /**
     * Save search data.
     *
     * @param array $data Search data.
     *
     * @return int Search ID.
     */
    public function save_search( array $data ) {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_searches';
        $now   = current_time( 'mysql' );

        $wpdb->insert(
            $table,
            [
                'agreement_id'     => $data['agreement_id'],
                'transaction_type' => $data['transaction_type'],
                'criteria'         => $data['criteria'],
                'description'      => $data['description'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }
}
