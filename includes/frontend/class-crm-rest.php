<?php
namespace EstateOffice\Frontend;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers REST API endpoints that power the CRM wizard.
 */
class CRM_REST {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Registers custom routes.
     */
    public function register_routes(): void {
        register_rest_route(
            'estate-office/v1',
            '/contracts',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_contract' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => [
                    'contract_number'  => [ 'required' => true ],
                    'transaction_type' => [ 'required' => true ],
                    'start_date'       => [ 'required' => false ],
                ],
            ]
        );

        register_rest_route(
            'estate-office/v1',
            '/contracts/(?P<id>\d+)/clients',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'assign_client_to_contract' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );

        register_rest_route(
            'estate-office/v1',
            '/clients',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'search_clients' ],
                    'permission_callback' => [ $this, 'check_permissions' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_client' ],
                    'permission_callback' => [ $this, 'check_permissions' ],
                ],
            ]
        );

        register_rest_route(
            'estate-office/v1',
            '/properties',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_property' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );

        register_rest_route(
            'estate-office/v1',
            '/searches',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_search' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );
    }

    /**
     * Ensures that the current user can manage CRM data.
     */
    public function check_permissions(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Creates a new contract post.
     */
    public function create_contract( WP_REST_Request $request ) {
        $number          = $this->sanitize_text( $request['contract_number'] );
        $transaction     = $this->sanitize_choice( $request['transaction_type'], [ 'sprzedaz', 'kupno', 'wynajem', 'najem' ] );
        $start_date      = $this->sanitize_date( $request['start_date'] );
        $end_date        = $this->sanitize_date( $request['end_date'] );
        $no_end_date     = (bool) $request['no_end_date'];
        $commission      = $this->sanitize_number( $request['commission'] );
        $commission_unit = $this->sanitize_choice( $request['commission_unit'], [ 'percent', 'pln', 'eur', 'usd' ], 'percent' );
        $property_type   = $this->sanitize_choice( $request['property_type'], [ 'mieszkanie', 'dom', 'dzialka', 'lokal', '' ], '' );
        $agent_id        = $this->sanitize_int( $request['agent_id'] );

        if ( empty( $number ) ) {
            return new WP_Error( 'missing_number', __( 'Numer umowy jest wymagany.', 'estate-office' ), [ 'status' => 400 ] );
        }

        if ( ! $transaction ) {
            return new WP_Error( 'invalid_transaction', __( 'Nieprawidłowy typ transakcji.', 'estate-office' ), [ 'status' => 400 ] );
        }

        $existing = get_posts(
            [
                'post_type'      => 'estate_contract',
                'posts_per_page' => 1,
                'post_status'    => [ 'publish', 'draft' ],
                'meta_query'     => [
                    [
                        'key'   => 'estate_office_contract_number',
                        'value' => $number,
                    ],
                ],
                'fields'         => 'ids',
            ]
        );

        if ( $existing ) {
            return new WP_Error( 'duplicate_number', __( 'Umowa o podanym numerze już istnieje.', 'estate-office' ), [ 'status' => 409 ] );
        }

        $post_id = wp_insert_post(
            [
                'post_type'   => 'estate_contract',
                'post_status' => 'draft',
                'post_title'  => sprintf( __( 'Umowa %s', 'estate-office' ), $number ),
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $meta = [
            'contract_number' => $number,
            'transaction_type'=> $transaction,
            'start_date'      => $start_date,
            'end_date'        => $no_end_date ? '' : $end_date,
            'no_end_date'     => $no_end_date ? 1 : 0,
            'commission'      => $commission,
            'commission_unit' => $commission_unit,
            'property_type'   => $property_type,
            'agent_id'        => $agent_id,
            'stage'           => '',
            'stage_date'      => $start_date ?: current_time( 'Y-m-d' ),
        ];

        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, 'estate_office_' . $key, $value );
        }

        update_post_meta(
            $post_id,
            'estate_office_stage_history',
            [
                [
                    'date'  => $start_date ?: current_time( 'Y-m-d' ),
                    'stage' => '',
                    'notes' => '',
                ],
            ]
        );

        return new WP_REST_Response(
            [
                'id'               => $post_id,
                'edit_link'        => get_edit_post_link( $post_id, 'raw' ),
                'transaction_type' => $transaction,
                'property_type'    => $property_type,
            ],
            201
        );
    }

    /**
     * Assigns a client to an existing contract.
     */
    public function assign_client_to_contract( WP_REST_Request $request ) {
        $contract_id = $this->sanitize_int( $request['id'] );
        $client_id   = $this->sanitize_int( $request['client_id'] );

        if ( ! $contract_id || ! get_post( $contract_id ) ) {
            return new WP_Error( 'invalid_contract', __( 'Nie znaleziono umowy.', 'estate-office' ), [ 'status' => 404 ] );
        }

        if ( ! $client_id || ! get_post( $client_id ) ) {
            return new WP_Error( 'invalid_client', __( 'Nie znaleziono klienta.', 'estate-office' ), [ 'status' => 404 ] );
        }

        $related = get_post_meta( $contract_id, 'estate_office_related_clients', true );
        $related = is_array( $related ) ? $related : [];

        if ( ! in_array( $client_id, $related, true ) ) {
            $related[] = $client_id;
            update_post_meta( $contract_id, 'estate_office_related_clients', $related );
        }

        $client_contracts = get_post_meta( $client_id, 'estate_office_contracts', true );
        $client_contracts = is_array( $client_contracts ) ? $client_contracts : [];
        if ( ! in_array( $contract_id, $client_contracts, true ) ) {
            $client_contracts[] = $contract_id;
            update_post_meta( $client_id, 'estate_office_contracts', $client_contracts );
        }

        return new WP_REST_Response(
            [
                'success' => true,
            ],
            200
        );
    }

    /**
     * Returns clients matching the provided query.
     */
    public function search_clients( WP_REST_Request $request ) {
        $search = $this->sanitize_text( $request['search'] ?? '' );

        $args = [
            'post_type'      => 'estate_client',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => 10,
        ];

        if ( $search ) {
            $args['s'] = $search;
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'estate_office_phone',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'estate_office_email',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
            ];
        }

        $query = new WP_Query( $args );
        $items = [];

        foreach ( $query->posts as $post ) {
            $items[] = [
                'id'    => $post->ID,
                'title' => get_the_title( $post ),
                'phone' => get_post_meta( $post->ID, 'estate_office_phone', true ),
                'email' => get_post_meta( $post->ID, 'estate_office_email', true ),
            ];
        }

        return new WP_REST_Response( [ 'items' => $items ] );
    }

    /**
     * Creates a new client record.
     */
    public function create_client( WP_REST_Request $request ) {
        $client_type = $this->sanitize_choice( $request['client_type'], [ 'osoba', 'firma' ], 'osoba' );
        $first_name  = $this->sanitize_text( $request['first_name'] );
        $last_name   = $this->sanitize_text( $request['last_name'] );
        $company     = $this->sanitize_text( $request['company_name'] );
        $represent   = $this->sanitize_text( $request['company_representative'] );
        $phone       = $this->sanitize_text( $request['phone'] );
        $email       = sanitize_email( $request['email'] );
        $website     = esc_url_raw( $request['website'] ?? '' );
        $pesel       = $this->sanitize_text( $request['pesel'] );
        $document    = $this->sanitize_choice( $request['document_type'], [ '', 'dowod', 'paszport', 'karta_pobytu' ], '' );
        $doc_number  = $this->sanitize_text( $request['document_number'] );
        $nip         = $this->sanitize_text( $request['nip'] );
        $krs         = $this->sanitize_text( $request['krs'] );
        $regon       = $this->sanitize_text( $request['regon'] );
        $agent_id    = $this->sanitize_int( $request['agent_id'] );

        $address        = $this->sanitize_address( $request['address'] ?? [] );
        $correspondence = $this->sanitize_address( $request['correspondence'] ?? [] );
        $same_address   = (bool) $request['same_correspondence'];

        if ( 'osoba' === $client_type && ( empty( $first_name ) || empty( $last_name ) ) ) {
            return new WP_Error( 'missing_name', __( 'Imię i nazwisko są wymagane.', 'estate-office' ), [ 'status' => 400 ] );
        }

        if ( 'firma' === $client_type && empty( $company ) ) {
            return new WP_Error( 'missing_company', __( 'Nazwa firmy jest wymagana.', 'estate-office' ), [ 'status' => 400 ] );
        }

        $title = 'osoba' === $client_type ? trim( $first_name . ' ' . $last_name ) : $company;

        $post_id = wp_insert_post(
            [
                'post_type'   => 'estate_client',
                'post_status' => 'draft',
                'post_title'  => $title,
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $meta = [
            'client_type'          => $client_type,
            'first_name'           => $first_name,
            'last_name'            => $last_name,
            'company_name'         => $company,
            'company_representative' => $represent,
            'phone'                => $phone,
            'email'                => $email,
            'website'              => $website,
            'pesel'                => $pesel,
            'document_type'        => $document,
            'document_number'      => $doc_number,
            'nip'                  => $nip,
            'krs'                  => $krs,
            'regon'                => $regon,
            'agent_id'             => $agent_id,
            'address'              => $address,
            'same_correspondence'  => $same_address ? 1 : 0,
        ];

        if ( ! $same_address ) {
            $meta['correspondence'] = $correspondence;
        }

        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, 'estate_office_' . $key, $value );
        }

        return new WP_REST_Response(
            [
                'id'        => $post_id,
                'title'     => get_the_title( $post_id ),
                'edit_link' => get_edit_post_link( $post_id, 'raw' ),
            ],
            201
        );
    }

    /**
     * Creates a property associated with a contract.
     */
    public function create_property( WP_REST_Request $request ) {
        $contract_id = $this->sanitize_int( $request['contract_id'] );
        $transaction = $this->sanitize_choice( $request['transaction_type'], [ 'sprzedaz', 'wynajem' ] );
        $property_type = $this->sanitize_choice( $request['property_type'], [ 'mieszkanie', 'dom', 'dzialka', 'lokal' ] );

        if ( ! $contract_id || ! get_post( $contract_id ) ) {
            return new WP_Error( 'invalid_contract', __( 'Nie znaleziono umowy.', 'estate-office' ), [ 'status' => 404 ] );
        }

        if ( ! $transaction ) {
            return new WP_Error( 'invalid_transaction', __( 'Nieprawidłowy typ transakcji.', 'estate-office' ), [ 'status' => 400 ] );
        }

        if ( ! $property_type ) {
            return new WP_Error( 'invalid_property_type', __( 'Nieprawidłowy rodzaj nieruchomości.', 'estate-office' ), [ 'status' => 400 ] );
        }

        $offer_number = $this->sanitize_text( $request['offer_number'] );
        $price        = $this->sanitize_number( $request['price'] );
        $rent         = $this->sanitize_number( $request['rent'] );
        $area         = $this->sanitize_number( $request['area'] );
        $rooms        = $this->sanitize_int( $request['rooms'] );
        $agent_id     = $this->sanitize_int( $request['agent_id'] );
        $description  = wp_kses_post( $request['description'] ?? '' );
        $flags        = $this->sanitize_array( $request['flags'] ?? [] );
        $location     = $this->sanitize_location( $request['location'] ?? [] );
        $legal        = $this->sanitize_choice( $request['legal_status'], [ '', 'wlasnosc', 'wspolwlasnosc', 'spoldzielcze', 'dzierzawa', 'inne' ], '' );
        $house_type   = $this->sanitize_choice( $request['house_type'], [ '', 'wolnostojacy', 'blizniak', 'szeregowiec', 'wielorodzinny' ], '' );
        $lot_shape    = $this->sanitize_choice( $request['lot_shape'], [ '', 'regularny', 'nieregularny' ], '' );
        $lot_length   = $this->sanitize_text( $request['lot_length'] );
        $lot_width    = $this->sanitize_text( $request['lot_width'] );
        $lot_notes    = isset( $request['lot_notes'] ) ? wp_kses_post( $request['lot_notes'] ) : '';

        $post_title = $offer_number ? sprintf( __( 'Oferta %s', 'estate-office' ), $offer_number ) : __( 'Nowa nieruchomość', 'estate-office' );

        $post_id = wp_insert_post(
            [
                'post_type'   => 'estate_property',
                'post_status' => 'draft',
                'post_title'  => $post_title,
                'post_content'=> $description,
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $meta = [
            'offer_number'     => $offer_number,
            'transaction_type' => $transaction,
            'property_type'    => $property_type,
            'agent_id'         => $agent_id,
            'price'            => $price,
            'rent'             => $rent,
            'area'             => $area,
            'rooms'            => $rooms,
            'street'           => $location['street'] ?? '',
            'building_number'  => $location['number'] ?? '',
            'unit_number'      => $location['unit'] ?? '',
            'postal_code'      => $location['postal_code'] ?? '',
            'district'         => $location['district'] ?? '',
            'city'             => $location['city'] ?? '',
            'county'           => $location['county'] ?? '',
            'district_area'    => $location['district_area'] ?? '',
            'plot_number'      => $location['plot_number'] ?? '',
            'land_and_mortgage_register' => $this->sanitize_text( $request['land_and_mortgage_register'] ),
            'no_lmr'           => ! empty( $request['no_lmr'] ) ? 1 : 0,
            'legal_status'     => $legal,
            'house_type'       => $house_type,
            'lot_shape'        => $lot_shape,
            'related_contract' => $contract_id,
            'flags'            => $flags,
        ];

        if ( $area && $price ) {
            $meta['price_sqm'] = round( $price / max( $area, 0.0001 ), 2 );
        }

        if ( $lot_shape === 'regularny' ) {
            if ( $lot_length ) {
                $meta['lot_length'] = $lot_length;
            }
            if ( $lot_width ) {
                $meta['lot_width'] = $lot_width;
            }
        }

        if ( $lot_shape === 'nieregularny' && $lot_notes ) {
            $meta['lot_notes'] = $lot_notes;
        }

        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, 'estate_office_' . $key, $value );
        }

        update_post_meta( $contract_id, 'estate_office_related_property', $post_id );

        return new WP_REST_Response(
            [
                'id'        => $post_id,
                'edit_link' => get_edit_post_link( $post_id, 'raw' ),
            ],
            201
        );
    }

    /**
     * Creates a search profile for buyer/tenant contracts.
     */
    public function create_search( WP_REST_Request $request ) {
        $contract_id = $this->sanitize_int( $request['contract_id'] );
        $transaction = $this->sanitize_choice( $request['transaction_type'], [ 'kupno', 'najem' ] );
        $property_type = $this->sanitize_choice( $request['property_type'], [ 'mieszkanie', 'dom', 'dzialka', 'lokal' ] );

        if ( ! $contract_id || ! get_post( $contract_id ) ) {
            return new WP_Error( 'invalid_contract', __( 'Nie znaleziono umowy.', 'estate-office' ), [ 'status' => 404 ] );
        }

        if ( ! $transaction ) {
            return new WP_Error( 'invalid_transaction', __( 'Nieprawidłowy typ transakcji.', 'estate-office' ), [ 'status' => 400 ] );
        }

        $number       = $this->sanitize_text( $request['search_number'] );
        $budget_min   = $this->sanitize_number( $request['budget_min'] );
        $budget_max   = $this->sanitize_number( $request['budget_max'] );
        $area_min     = $this->sanitize_number( $request['area_min'] );
        $area_max     = $this->sanitize_number( $request['area_max'] );
        $rooms_min    = $this->sanitize_int( $request['rooms_min'] );
        $rooms_max    = $this->sanitize_int( $request['rooms_max'] );
        $location     = $this->sanitize_text( $request['location'] );
        $description  = wp_kses_post( $request['description'] ?? '' );
        $agent_id     = $this->sanitize_int( $request['agent_id'] );

        $post_id = wp_insert_post(
            [
                'post_type'    => 'estate_search',
                'post_status'  => 'draft',
                'post_title'   => $number ? sprintf( __( 'Poszukiwanie %s', 'estate-office' ), $number ) : __( 'Nowe poszukiwanie', 'estate-office' ),
                'post_content' => $description,
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $meta = [
            'search_number'    => $number,
            'transaction_type' => $transaction,
            'property_type'    => $property_type,
            'budget_min'       => $budget_min,
            'budget_max'       => $budget_max,
            'area_min'         => $area_min,
            'area_max'         => $area_max,
            'rooms_min'        => $rooms_min,
            'rooms_max'        => $rooms_max,
            'location'         => $location,
            'agent_id'         => $agent_id,
            'related_contract' => $contract_id,
        ];

        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, 'estate_office_' . $key, $value );
        }

        update_post_meta( $contract_id, 'estate_office_related_search', $post_id );

        return new WP_REST_Response(
            [
                'id'        => $post_id,
                'edit_link' => get_edit_post_link( $post_id, 'raw' ),
            ],
            201
        );
    }

    /**
     * Sanitizes a generic text value.
     */
    private function sanitize_text( $value ): string {
        return is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
    }

    /**
     * Sanitizes numbers.
     */
    private function sanitize_number( $value ): float {
        return is_numeric( $value ) ? (float) $value : 0.0;
    }

    /**
     * Sanitizes integer values.
     */
    private function sanitize_int( $value ): int {
        return is_numeric( $value ) ? (int) $value : 0;
    }

    /**
     * Sanitizes enum values.
     */
    private function sanitize_choice( $value, array $choices, $default = '' ) {
        $value = is_string( $value ) ? sanitize_key( $value ) : '';

        if ( in_array( $value, $choices, true ) ) {
            return $value;
        }

        return $default;
    }

    /**
     * Sanitizes Y-m-d dates.
     */
    private function sanitize_date( $value ): string {
        $value = $this->sanitize_text( $value );

        if ( ! $value ) {
            return '';
        }

        $date = date_create_from_format( 'Y-m-d', $value );

        return $date ? $date->format( 'Y-m-d' ) : '';
    }

    /**
     * Sanitizes address structure.
     */
    private function sanitize_address( $data ): array {
        if ( ! is_array( $data ) ) {
            return [];
        }

        $keys = [ 'street', 'number', 'unit', 'postal_code', 'city', 'country' ];
        $output = [];

        foreach ( $keys as $key ) {
            $output[ $key ] = $this->sanitize_text( $data[ $key ] ?? '' );
        }

        return $output;
    }

    /**
     * Sanitizes property location data.
     */
    private function sanitize_location( $data ): array {
        if ( ! is_array( $data ) ) {
            return [];
        }

        $keys = [ 'street', 'number', 'unit', 'postal_code', 'district', 'city', 'county', 'district_area', 'plot_number' ];
        $output = [];

        foreach ( $keys as $key ) {
            $output[ $key ] = $this->sanitize_text( $data[ $key ] ?? '' );
        }

        return $output;
    }

    /**
     * Sanitizes lot details payload.
     */
    /**
     * Sanitizes a simple list of values.
     */
    private function sanitize_array( $values ): array {
        if ( ! is_array( $values ) ) {
            return [];
        }

        return array_values( array_filter( array_map( 'sanitize_key', $values ) ) );
    }
}
