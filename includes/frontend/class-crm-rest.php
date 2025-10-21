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
            '/contracts/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_contract' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );

        register_rest_route(
            'estate-office/v1',
            '/contracts/(?P<id>\d+)/stage',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_contract_stage' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => [
                    'stage'      => [ 'required' => true ],
                    'stage_date' => [ 'required' => true ],
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
            '/clients/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_client' ],
                'permission_callback' => [ $this, 'check_permissions' ],
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
            '/properties/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_property' ],
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

        register_rest_route(
            'estate-office/v1',
            '/searches/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_search' ],
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
     * Returns a formatted contract profile.
     */
    public function get_contract( WP_REST_Request $request ) {
        $post_id = $this->sanitize_int( $request['id'] );
        $post    = get_post( $post_id );

        if ( ! $post || 'estate_contract' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Nie znaleziono umowy.', 'estate-office' ), [ 'status' => 404 ] );
        }

        $meta = $this->get_meta_values( $post_id );
        $data = $this->prepare_contract_profile( $post, $meta );

        return new WP_REST_Response( [ 'data' => $data ] );
    }

    /**
     * Updates the contract stage and timeline history.
     */
    public function update_contract_stage( WP_REST_Request $request ) {
        $post_id = $this->sanitize_int( $request['id'] );
        $post    = get_post( $post_id );

        if ( ! $post || 'estate_contract' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Nie znaleziono umowy.', 'estate-office' ), [ 'status' => 404 ] );
        }

        $stage_key = is_string( $request['stage'] ) ? sanitize_key( $request['stage'] ) : '';
        $stage_map = $this->get_contract_stage_map();

        if ( ! array_key_exists( $stage_key, $stage_map ) ) {
            return new WP_Error( 'invalid_stage', __( 'Nieprawidłowy etap umowy.', 'estate-office' ), [ 'status' => 400 ] );
        }

        $stage_date = $this->sanitize_date( $request['stage_date'] );
        if ( ! $stage_date ) {
            return new WP_Error( 'invalid_date', __( 'Podaj prawidłową datę etapu.', 'estate-office' ), [ 'status' => 400 ] );
        }

        $notes = $this->sanitize_text( $request['notes'] ?? '' );

        update_post_meta( $post_id, 'estate_office_stage', $stage_key );
        update_post_meta( $post_id, 'estate_office_stage_date', $stage_date );

        $history   = get_post_meta( $post_id, 'estate_office_stage_history', true );
        $history   = is_array( $history ) ? $history : [];
        $history[] = [
            'date'  => $stage_date,
            'stage' => $stage_key,
            'notes' => $notes,
        ];

        update_post_meta( $post_id, 'estate_office_stage_history', $history );

        $meta    = $this->get_meta_values( $post_id );
        $profile = $this->prepare_contract_profile( $post, $meta );

        return new WP_REST_Response(
            [
                'summary'        => $profile['summary'],
                'sections'       => $profile['sections'],
                'timeline'       => $profile['timeline'],
                'timeline_title' => $profile['timeline_title'] ?? '',
                'stage'          => $profile['stage'],
                'message'        => __( 'Etap umowy został zaktualizowany.', 'estate-office' ),
            ]
        );
    }

    /**
     * Returns a formatted client profile.
     */
    public function get_client( WP_REST_Request $request ) {
        $post_id = $this->sanitize_int( $request['id'] );
        $post    = get_post( $post_id );

        if ( ! $post || 'estate_client' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Nie znaleziono klienta.', 'estate-office' ), [ 'status' => 404 ] );
        }

        $meta       = $this->get_meta_values( $post_id );
        $client_type = $this->translate_client_type( $meta['client_type'] ?? 'osoba' );
        $agent       = $this->get_agent_label( (int) ( $meta['agent_id'] ?? 0 ) );
        $phone       = $meta['phone'] ?? '';
        $email       = $meta['email'] ?? '';
        $address     = isset( $meta['address'] ) && is_array( $meta['address'] ) ? $meta['address'] : [];
        $correspondence = isset( $meta['correspondence'] ) && is_array( $meta['correspondence'] ) ? $meta['correspondence'] : [];
        $same_address   = ! empty( $meta['same_correspondence'] );

        if ( $same_address ) {
            $correspondence = $address;
        }

        $summary = [
            $this->summary_item( __( 'Typ klienta', 'estate-office' ), $client_type ),
            $this->summary_item( __( 'Telefon', 'estate-office' ), $phone ),
            $this->summary_item( __( 'E-mail', 'estate-office' ), $email ),
            $this->summary_item( __( 'Opiekun', 'estate-office' ), $agent ),
        ];

        $sections = [
            [
                'title' => __( 'Dane podstawowe', 'estate-office' ),
                'items' => array_filter(
                    [
                        $this->summary_item( __( 'Imię', 'estate-office' ), $meta['first_name'] ?? '' ),
                        $this->summary_item( __( 'Nazwisko', 'estate-office' ), $meta['last_name'] ?? '' ),
                        $this->summary_item( __( 'Nazwa firmy', 'estate-office' ), $meta['company_name'] ?? '' ),
                        $this->summary_item( __( 'Reprezentant firmy', 'estate-office' ), $meta['company_representative'] ?? '' ),
                    ]
                ),
            ],
            [
                'title' => __( 'Dane identyfikacyjne', 'estate-office' ),
                'items' => array_filter(
                    [
                        $this->summary_item( __( 'PESEL', 'estate-office' ), $meta['pesel'] ?? '' ),
                        $this->summary_item( __( 'Rodzaj dokumentu', 'estate-office' ), $this->translate_document_type( $meta['document_type'] ?? '' ) ),
                        $this->summary_item( __( 'Numer dokumentu', 'estate-office' ), $meta['document_number'] ?? '' ),
                        $this->summary_item( __( 'NIP', 'estate-office' ), $meta['nip'] ?? '' ),
                        $this->summary_item( __( 'KRS', 'estate-office' ), $meta['krs'] ?? '' ),
                        $this->summary_item( __( 'REGON', 'estate-office' ), $meta['regon'] ?? '' ),
                    ]
                ),
            ],
            [
                'title' => __( 'Adres zamieszkania/rejestrowy', 'estate-office' ),
                'items' => $this->format_address_items( $address ),
            ],
            [
                'title' => __( 'Adres korespondencyjny', 'estate-office' ),
                'items' => $this->format_address_items( $correspondence ),
            ],
        ];

        $relations      = [];
        $contract_ids   = isset( $meta['contracts'] ) && is_array( $meta['contracts'] ) ? array_map( 'intval', $meta['contracts'] ) : [];
        $relations[]    = $this->relation_section( __( 'Powiązane umowy', 'estate-office' ), $this->map_posts_to_relations( $contract_ids, 'contract' ) );
        $property_items = $this->gather_properties_from_contracts( $contract_ids );
        $search_items   = $this->gather_searches_from_contracts( $contract_ids );
        $relations[]    = $this->relation_section( __( 'Powiązane nieruchomości', 'estate-office' ), $property_items );
        $relations[]    = $this->relation_section( __( 'Powiązane poszukiwania', 'estate-office' ), $search_items );

        $actions = [];
        $this->maybe_add_action( $actions, get_edit_post_link( $post_id, 'raw' ), __( 'Edytuj w kokpicie', 'estate-office' ) );

        $data = [
            'id'          => $post_id,
            'type'        => 'client',
            'title'       => get_the_title( $post ),
            'subtitle'    => $client_type,
            'summary'     => $summary,
            'sections'    => array_values( array_filter( $sections, static function ( $section ) {
                return ! empty( $section['items'] );
            } ) ),
            'relations'   => $relations,
            'actions'     => $actions,
            'description' => $this->prepare_description( $post->post_content ),
        ];

        return new WP_REST_Response( [ 'data' => $data ] );
    }

    /**
     * Returns a formatted property profile.
     */
    public function get_property( WP_REST_Request $request ) {
        $post_id = $this->sanitize_int( $request['id'] );
        $post    = get_post( $post_id );

        if ( ! $post || 'estate_property' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Nie znaleziono nieruchomości.', 'estate-office' ), [ 'status' => 404 ] );
        }

        $meta           = $this->get_meta_values( $post_id );
        $transaction    = $this->translate_transaction( $meta['transaction_type'] ?? '' );
        $property_type  = $this->translate_property_type( $meta['property_type'] ?? '' );
        $agent          = $this->get_agent_label( (int) ( $meta['agent_id'] ?? 0 ) );
        $price          = $this->format_money( $meta['price'] ?? '' );
        $rent           = $this->format_money( $meta['rent'] ?? '' );
        $area           = $this->format_area_value( $meta['area'] ?? '' );
        $price_sqm      = $this->format_money_per_sqm( $meta['price_sqm'] ?? '' );
        $rooms          = $this->format_plain_value( $meta['rooms'] ?? '' );
        $flags          = isset( $meta['flags'] ) && is_array( $meta['flags'] ) ? $this->translate_flags( $meta['flags'], $meta['transaction_type'] ?? '' ) : [];

        $summary = [
            $this->summary_item( __( 'Numer oferty', 'estate-office' ), $meta['offer_number'] ?? $post->post_title ),
            $this->summary_item( __( 'Cena', 'estate-office' ), $price ),
            $this->summary_item( __( 'Cena za m²', 'estate-office' ), $price_sqm ),
            $this->summary_item( __( 'Metraż', 'estate-office' ), $area ),
            $this->summary_item( __( 'Liczba pokoi', 'estate-office' ), $rooms ),
            $this->summary_item( __( 'Opiekun', 'estate-office' ), $agent ),
        ];

        $sections = [
            [
                'title' => __( 'Dane adresowe', 'estate-office' ),
                'items' => $this->build_property_address_items( $meta ),
            ],
            [
                'title' => __( 'Dane nieruchomości', 'estate-office' ),
                'items' => $this->build_property_details_items( $meta ),
            ],
            [
                'title' => __( 'Media i udogodnienia', 'estate-office' ),
                'items' => $this->build_property_facilities_items( $meta ),
            ],
            [
                'title' => __( 'Powierzchnie dodatkowe', 'estate-office' ),
                'items' => $this->build_property_additional_areas( $meta ),
            ],
            [
                'title' => __( 'Multimedia', 'estate-office' ),
                'items' => $this->build_property_media_items( $meta ),
            ],
        ];

        $relations = [];
        $contract_id = isset( $meta['related_contract'] ) ? (int) $meta['related_contract'] : 0;
        $relations[] = $this->relation_section( __( 'Powiązana umowa', 'estate-office' ), $contract_id ? $this->map_posts_to_relations( [ $contract_id ], 'contract' ) : [] );
        $client_items = $contract_id ? $this->map_posts_to_relations( $this->get_contract_clients( $contract_id ), 'client' ) : [];
        $relations[] = $this->relation_section( __( 'Powiązani klienci', 'estate-office' ), $client_items );

        $actions = [];
        $this->maybe_add_action( $actions, get_edit_post_link( $post_id, 'raw' ), __( 'Edytuj w kokpicie', 'estate-office' ) );

        $data = [
            'id'          => $post_id,
            'type'        => 'property',
            'title'       => get_the_title( $post ),
            'subtitle'    => $this->build_subtitle( [ $property_type, $transaction ] ),
            'summary'     => $summary,
            'sections'    => array_values( array_filter( $sections, static function ( $section ) {
                return ! empty( $section['items'] );
            } ) ),
            'relations'   => $relations,
            'actions'     => $actions,
            'badges'      => $flags,
            'description' => $this->prepare_description( $post->post_content ),
        ];

        return new WP_REST_Response( [ 'data' => $data ] );
    }

    /**
     * Returns a formatted search profile.
     */
    public function get_search( WP_REST_Request $request ) {
        $post_id = $this->sanitize_int( $request['id'] );
        $post    = get_post( $post_id );

        if ( ! $post || 'estate_search' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Nie znaleziono profilu poszukiwania.', 'estate-office' ), [ 'status' => 404 ] );
        }

        $meta          = $this->get_meta_values( $post_id );
        $transaction   = $this->translate_transaction( $meta['transaction_type'] ?? '' );
        $property_type = $this->translate_property_type( $meta['property_type'] ?? '' );
        $agent         = $this->get_agent_label( (int) ( $meta['agent_id'] ?? 0 ) );
        $budget        = $this->format_range_value( $meta['budget_min'] ?? '', $meta['budget_max'] ?? '', 'PLN' );
        $area          = $this->format_range_value( $meta['area_min'] ?? '', $meta['area_max'] ?? '', 'm²' );

        $summary = [
            $this->summary_item( __( 'Numer poszukiwania', 'estate-office' ), $meta['search_number'] ?? $post->post_title ),
            $this->summary_item( __( 'Typ transakcji', 'estate-office' ), $transaction ),
            $this->summary_item( __( 'Rodzaj nieruchomości', 'estate-office' ), $property_type ),
            $this->summary_item( __( 'Budżet', 'estate-office' ), $budget ),
            $this->summary_item( __( 'Metraż', 'estate-office' ), $area ),
            $this->summary_item( __( 'Preferowana lokalizacja', 'estate-office' ), $meta['location'] ?? '' ),
            $this->summary_item( __( 'Opiekun', 'estate-office' ), $agent ),
        ];

        $sections = [
            [
                'title' => __( 'Kryteria dodatkowe', 'estate-office' ),
                'items' => array_filter(
                    [
                        $this->summary_item( __( 'Liczba pokoi od', 'estate-office' ), $this->format_plain_value( $meta['rooms_min'] ?? '' ) ),
                        $this->summary_item( __( 'Liczba pokoi do', 'estate-office' ), $this->format_plain_value( $meta['rooms_max'] ?? '' ) ),
                        $this->summary_item( __( 'Preferowane piętro', 'estate-office' ), $meta['preferred_floor'] ?? '' ),
                        $this->summary_item( __( 'Preferowane ogrzewanie', 'estate-office' ), $meta['preferred_heating'] ?? '' ),
                    ]
                ),
            ],
            [
                'title' => __( 'Udogodnienia', 'estate-office' ),
                'items' => $this->build_checklist_items( $meta['facilities'] ?? [], $this->facility_labels() ),
            ],
            [
                'title' => __( 'Wyposażenie', 'estate-office' ),
                'items' => $this->build_checklist_items( $meta['equipment'] ?? [], $this->equipment_labels() ),
            ],
        ];

        $relations = [];
        $contract_id = isset( $meta['related_contract'] ) ? (int) $meta['related_contract'] : 0;
        $relations[] = $this->relation_section( __( 'Powiązana umowa', 'estate-office' ), $contract_id ? $this->map_posts_to_relations( [ $contract_id ], 'contract' ) : [] );
        $client_items = $contract_id ? $this->map_posts_to_relations( $this->get_contract_clients( $contract_id ), 'client' ) : [];
        $relations[] = $this->relation_section( __( 'Powiązani klienci', 'estate-office' ), $client_items );

        $actions = [];
        $this->maybe_add_action( $actions, get_edit_post_link( $post_id, 'raw' ), __( 'Edytuj w kokpicie', 'estate-office' ) );

        $data = [
            'id'          => $post_id,
            'type'        => 'search',
            'title'       => get_the_title( $post ),
            'subtitle'    => $this->build_subtitle( [ $transaction, $property_type ] ),
            'summary'     => $summary,
            'sections'    => array_values( array_filter( $sections, static function ( $section ) {
                return ! empty( $section['items'] );
            } ) ),
            'relations'   => $relations,
            'actions'     => $actions,
            'description' => $this->prepare_description( $post->post_content ),
        ];

        return new WP_REST_Response( [ 'data' => $data ] );
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

    private function get_meta_values( int $post_id ): array {
        $raw  = get_post_meta( $post_id );
        $meta = [];

        foreach ( $raw as $key => $values ) {
            if ( 0 !== strpos( $key, 'estate_office_' ) ) {
                continue;
            }

            $meta_key          = substr( $key, strlen( 'estate_office_' ) );
            $meta[ $meta_key ] = maybe_unserialize( $values[0] );
        }

        return $meta;
    }

    private function translate_transaction( string $key ): string {
        $map = [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];

        return $map[ $key ] ?? '';
    }

    private function translate_property_type( string $key ): string {
        $map = [
            'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
            'dom'        => __( 'Dom', 'estate-office' ),
            'dzialka'    => __( 'Działka', 'estate-office' ),
            'lokal'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
        ];

        return $map[ $key ] ?? '';
    }

    private function translate_contract_stage( string $key ): string {
        $map = $this->get_contract_stage_map();

        return $map[ $key ] ?? $key;
    }

    private function get_contract_stage_map(): array {
        return [
            ''            => __( 'Umowa pośrednictwa', 'estate-office' ),
            'mls'         => __( 'Publikacja w MLS', 'estate-office' ),
            'preparation' => __( 'Przygotowanie oferty', 'estate-office' ),
            'publication' => __( 'Publikacja oferty', 'estate-office' ),
            'marketing'   => __( 'Marketing i prezentacje', 'estate-office' ),
            'offer'       => __( 'Oferta kupna', 'estate-office' ),
            'negotiations'=> __( 'Negocjacje', 'estate-office' ),
            'preliminary' => __( 'Umowa przedwstępna', 'estate-office' ),
            'final'       => __( 'Umowa przyrzeczona', 'estate-office' ),
            'handover'    => __( 'Przekazanie lokalu', 'estate-office' ),
            'completed'   => __( 'Umowa zakończona', 'estate-office' ),
        ];
    }

    private function format_commission( $value, string $unit ): string {
        if ( '' === $value || null === $value ) {
            return $this->format_plain_value( '' );
        }

        $number = (float) $value;

        switch ( $unit ) {
            case 'percent':
                return sprintf( '%s %%', number_format_i18n( $number, 2 ) );
            case 'eur':
                return sprintf( '%s EUR', number_format_i18n( $number, 2 ) );
            case 'usd':
                return sprintf( '%s USD', number_format_i18n( $number, 2 ) );
            case 'pln':
            default:
                return sprintf( '%s PLN', number_format_i18n( $number, 2 ) );
        }
    }

    private function get_agent_label( int $user_id ): string {
        if ( ! $user_id ) {
            return $this->format_plain_value( '' );
        }

        $user = get_user_by( 'id', $user_id );

        return $user ? $user->display_name : $this->format_plain_value( '' );
    }

    private function summary_item( string $label, $value ): array {
        return [
            'label' => $label,
            'value' => $this->format_plain_value( $value ),
        ];
    }

    private function format_boolean( bool $value ): string {
        return $value ? __( 'Tak', 'estate-office' ) : __( 'Nie', 'estate-office' );
    }

    private function relation_section( string $title, array $items ): array {
        return [
            'title' => $title,
            'items' => array_values( $items ),
        ];
    }

    private function map_posts_to_relations( array $ids, string $type ): array {
        $items = [];

        foreach ( array_unique( array_filter( $ids ) ) as $id ) {
            $post = get_post( $id );
            if ( ! $post ) {
                continue;
            }

            $item = [
                'id'        => (string) $post->ID,
                'type'      => $type,
                'label'     => get_the_title( $post ),
                'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
            ];

            $meta = $this->get_meta_values( $post->ID );

            if ( 'contract' === $type ) {
                $item['subtitle'] = $meta['contract_number'] ?? '';
            }

            if ( 'property' === $type ) {
                $item['subtitle'] = $this->format_property_address_line( $meta );
            }

            if ( 'client' === $type ) {
                $item['subtitle'] = $meta['email'] ?? ( $meta['phone'] ?? '' );
            }

            if ( 'search' === $type ) {
                $item['subtitle'] = $meta['location'] ?? '';
            }

            $items[] = $item;
        }

        return $items;
    }

    private function prepare_contract_profile( \WP_Post $post, array $meta ): array {
        $post_id          = $post->ID;
        $transaction      = $this->translate_transaction( $meta['transaction_type'] ?? '' );
        $property_type    = $this->translate_property_type( $meta['property_type'] ?? '' );
        $start_date       = $this->format_date_value( $meta['start_date'] ?? '' );
        $end_date         = ! empty( $meta['no_end_date'] ) ? __( 'Bezterminowa', 'estate-office' ) : $this->format_date_value( $meta['end_date'] ?? '' );
        $stage_label      = $this->translate_contract_stage( $meta['stage'] ?? '' );
        $commission_value = $this->format_commission( $meta['commission'] ?? '', $meta['commission_unit'] ?? 'percent' );
        $agent            = $this->get_agent_label( (int) ( $meta['agent_id'] ?? 0 ) );

        $summary = [
            $this->summary_item( __( 'Numer umowy', 'estate-office' ), $meta['contract_number'] ?? $post->post_title ),
            $this->summary_item( __( 'Data zawarcia', 'estate-office' ), $start_date ),
            $this->summary_item( __( 'Data zakończenia', 'estate-office' ), $end_date ),
            $this->summary_item( __( 'Aktualny etap', 'estate-office' ), $stage_label ),
            $this->summary_item( __( 'Opiekun', 'estate-office' ), $agent ),
        ];

        $sections = [
            [
                'title' => __( 'Szczegóły umowy', 'estate-office' ),
                'items' => [
                    $this->summary_item( __( 'Typ transakcji', 'estate-office' ), $transaction ),
                    $this->summary_item( __( 'Rodzaj nieruchomości', 'estate-office' ), $property_type ),
                    $this->summary_item( __( 'Umowa bezterminowa', 'estate-office' ), $this->format_boolean( ! empty( $meta['no_end_date'] ) ) ),
                ],
            ],
            [
                'title' => __( 'Prowizja', 'estate-office' ),
                'items' => [
                    $this->summary_item( __( 'Wysokość prowizji', 'estate-office' ), $commission_value ),
                    $this->summary_item( __( 'Data aktualizacji etapu', 'estate-office' ), $this->format_date_value( $meta['stage_date'] ?? '' ) ),
                ],
            ],
        ];

        $relations        = [];
        $related_clients  = isset( $meta['related_clients'] ) && is_array( $meta['related_clients'] ) ? array_map( 'intval', $meta['related_clients'] ) : [];
        $relations[]      = $this->relation_section( __( 'Powiązani klienci', 'estate-office' ), $this->map_posts_to_relations( $related_clients, 'client' ) );
        $property_id      = isset( $meta['related_property'] ) ? (int) $meta['related_property'] : 0;
        $relations[]      = $this->relation_section( __( 'Powiązana nieruchomość', 'estate-office' ), $property_id ? $this->map_posts_to_relations( [ $property_id ], 'property' ) : [] );
        $search_id        = isset( $meta['related_search'] ) ? (int) $meta['related_search'] : 0;
        $relations[]      = $this->relation_section( __( 'Powiązane poszukiwanie', 'estate-office' ), $search_id ? $this->map_posts_to_relations( [ $search_id ], 'search' ) : [] );
        $timeline         = $this->build_stage_timeline( $post_id );
        $actions          = [];
        $this->maybe_add_action( $actions, get_edit_post_link( $post_id, 'raw' ), __( 'Edytuj w kokpicie', 'estate-office' ) );

        return [
            'id'             => $post_id,
            'type'           => 'contract',
            'title'          => get_the_title( $post ),
            'subtitle'       => $this->build_subtitle( [ $transaction, $property_type ] ),
            'summary'        => $summary,
            'sections'       => $sections,
            'relations'      => $relations,
            'timeline'       => $timeline,
            'timeline_title' => __( 'Historia etapów', 'estate-office' ),
            'actions'        => $actions,
            'badges'         => ! empty( $meta['no_end_date'] ) ? [ __( 'Bezterminowa', 'estate-office' ) ] : [],
            'description'    => $this->prepare_description( $post->post_content ),
            'stage'          => $this->build_stage_payload( $post_id, $meta ),
        ];
    }

    private function build_stage_timeline( int $post_id ): array {
        $history = get_post_meta( $post_id, 'estate_office_stage_history', true );
        $history = is_array( $history ) ? $history : [];
        $timeline = [];

        foreach ( $history as $entry ) {
            $timeline[] = [
                'date'  => $this->format_date_value( $entry['date'] ?? '' ),
                'label' => $this->translate_contract_stage( $entry['stage'] ?? '' ),
                'notes' => isset( $entry['notes'] ) ? wp_strip_all_tags( (string) $entry['notes'] ) : '',
            ];
        }

        return $timeline;
    }

    private function build_stage_payload( int $post_id, array $meta ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            return [ 'enabled' => false ];
        }

        $map     = $this->get_contract_stage_map();
        $current = isset( $meta['stage'] ) ? sanitize_key( (string) $meta['stage'] ) : '';
        if ( ! array_key_exists( $current, $map ) ) {
            $current = '';
        }

        $date = $this->sanitize_date( $meta['stage_date'] ?? '' );
        if ( ! $date ) {
            $date = current_time( 'Y-m-d' );
        }

        return [
            'enabled' => true,
            'current' => $current,
            'date'    => $date,
            'options' => $this->get_contract_stage_options(),
        ];
    }

    private function get_contract_stage_options(): array {
        $options = [];

        foreach ( $this->get_contract_stage_map() as $value => $label ) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }

    private function maybe_add_action( array &$actions, $url, string $label, string $style = 'primary' ): void {
        if ( ! $url ) {
            return;
        }

        $actions[] = [
            'url'   => esc_url_raw( $url ),
            'label' => $label,
            'style' => $style,
        ];
    }

    private function build_subtitle( array $parts ): string {
        $filtered = array_filter( array_map( 'trim', $parts ) );

        return implode( ' · ', $filtered );
    }

    private function prepare_description( string $content ): string {
        if ( '' === trim( $content ) ) {
            return '';
        }

        return wp_kses_post( wpautop( $content ) );
    }

    private function format_address_items( array $address ): array {
        if ( empty( $address ) ) {
            return [];
        }

        $map = [
            'street'      => __( 'Ulica', 'estate-office' ),
            'number'      => __( 'Numer', 'estate-office' ),
            'unit'        => __( 'Lokal', 'estate-office' ),
            'postal_code' => __( 'Kod pocztowy', 'estate-office' ),
            'city'        => __( 'Miasto', 'estate-office' ),
            'country'     => __( 'Kraj', 'estate-office' ),
        ];

        $items = [];

        foreach ( $map as $key => $label ) {
            if ( isset( $address[ $key ] ) ) {
                $items[] = $this->summary_item( $label, $address[ $key ] );
            }
        }

        return array_values( array_filter( $items, static function ( $item ) {
            return '—' !== $item['value'];
        } ) );
    }

    private function gather_properties_from_contracts( array $contracts ): array {
        $properties = [];

        foreach ( $contracts as $contract_id ) {
            $related = (int) get_post_meta( $contract_id, 'estate_office_related_property', true );
            if ( $related ) {
                $properties[] = $related;
            }
        }

        return $this->map_posts_to_relations( $properties, 'property' );
    }

    private function gather_searches_from_contracts( array $contracts ): array {
        $searches = [];

        foreach ( $contracts as $contract_id ) {
            $related = (int) get_post_meta( $contract_id, 'estate_office_related_search', true );
            if ( $related ) {
                $searches[] = $related;
            }
        }

        return $this->map_posts_to_relations( $searches, 'search' );
    }

    private function build_property_address_items( array $meta ): array {
        $map = [
            'street'            => __( 'Ulica', 'estate-office' ),
            'building_number'   => __( 'Numer', 'estate-office' ),
            'unit_number'       => __( 'Lokal', 'estate-office' ),
            'postal_code'       => __( 'Kod pocztowy', 'estate-office' ),
            'district'          => __( 'Dzielnica', 'estate-office' ),
            'city'              => __( 'Miasto', 'estate-office' ),
            'county'            => __( 'Powiat', 'estate-office' ),
            'district_area'     => __( 'Obręb', 'estate-office' ),
            'plot_number'       => __( 'Numer działki', 'estate-office' ),
            'land_and_mortgage_register' => __( 'Numer księgi wieczystej', 'estate-office' ),
        ];

        $items = [];

        foreach ( $map as $key => $label ) {
            if ( isset( $meta[ $key ] ) ) {
                $items[] = $this->summary_item( $label, $meta[ $key ] );
            }
        }

        if ( ! empty( $meta['no_lmr'] ) ) {
            $items[] = $this->summary_item( __( 'Brak księgi wieczystej', 'estate-office' ), __( 'Tak', 'estate-office' ) );
        }

        return array_values( array_filter( $items, static function ( $item ) {
            return '—' !== $item['value'];
        } ) );
    }

    private function build_property_details_items( array $meta ): array {
        $items = [];

        $items[] = $this->summary_item( __( 'Czynsz administracyjny', 'estate-office' ), $this->format_money( $meta['rent'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Rok budowy', 'estate-office' ), $this->format_plain_value( $meta['year_built'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Piętro', 'estate-office' ), $this->format_plain_value( $meta['floor'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Liczba pięter', 'estate-office' ), $this->format_plain_value( $meta['floors'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Liczba sypialni', 'estate-office' ), $this->format_plain_value( $meta['bedrooms'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Liczba łazienek', 'estate-office' ), $this->format_plain_value( $meta['bathrooms'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Liczba toalet', 'estate-office' ), $this->format_plain_value( $meta['toilets'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Stan prawny', 'estate-office' ), $this->translate_legal_status( $meta['legal_status'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Typ domu', 'estate-office' ), $this->translate_house_type( $meta['house_type'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Kształt działki', 'estate-office' ), $this->translate_lot_shape( $meta['lot_shape'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Kuchnia', 'estate-office' ), $this->translate_kitchen_type( $meta['kitchen_type'] ?? '' ) );
        $items[] = $this->summary_item( __( 'Poddasze', 'estate-office' ), $this->format_boolean( ! empty( $meta['attic'] ) ) );
        $items[] = $this->summary_item( __( 'Wielopoziomowe', 'estate-office' ), $this->format_boolean( ! empty( $meta['multi_level'] ) ) );
        $items[] = $this->summary_item( __( 'Miejsce parkingowe', 'estate-office' ), $this->format_boolean( ! empty( $meta['parking_available'] ) ) );

        if ( ! empty( $meta['parking_options'] ) && is_array( $meta['parking_options'] ) ) {
            $items[] = $this->summary_item( __( 'Rodzaj parkingu', 'estate-office' ), implode( ', ', $this->translate_parking_options( $meta['parking_options'] ) ) );
        }

        if ( isset( $meta['lot_shape'] ) && 'regularny' === $meta['lot_shape'] ) {
            $items[] = $this->summary_item( __( 'Długość działki', 'estate-office' ), $this->format_plain_value( $meta['lot_length'] ?? '' ) );
            $items[] = $this->summary_item( __( 'Szerokość działki', 'estate-office' ), $this->format_plain_value( $meta['lot_width'] ?? '' ) );
        }

        if ( isset( $meta['lot_shape'] ) && 'nieregularny' === $meta['lot_shape'] && ! empty( $meta['lot_notes'] ) ) {
            $items[] = $this->summary_item( __( 'Opis działki', 'estate-office' ), wp_strip_all_tags( (string) $meta['lot_notes'] ) );
        }

        return array_values( array_filter( $items, static function ( $item ) {
            return '—' !== $item['value'];
        } ) );
    }

    private function build_property_facilities_items( array $meta ): array {
        $items = [];

        $items[] = $this->summary_item( __( 'Ogrzewanie', 'estate-office' ), $meta['heating'] ?? '' );
        $items[] = $this->summary_item( __( 'Woda', 'estate-office' ), $meta['water'] ?? '' );
        $items[] = $this->summary_item( __( 'Kanalizacja', 'estate-office' ), $meta['sewage'] ?? '' );
        $items[] = $this->summary_item( __( 'Gaz', 'estate-office' ), $this->format_boolean( ! empty( $meta['gaz'] ) ) );

        if ( ! empty( $meta['exposure'] ) && is_array( $meta['exposure'] ) ) {
            $items[] = $this->summary_item( __( 'Ekspozycja', 'estate-office' ), implode( ', ', $this->translate_exposure( $meta['exposure'] ) ) );
        }

        if ( ! empty( $meta['view'] ) && is_array( $meta['view'] ) ) {
            $items[] = $this->summary_item( __( 'Widok', 'estate-office' ), implode( ', ', $this->translate_view( $meta['view'] ) ) );
        }

        if ( ! empty( $meta['layout'] ) && is_array( $meta['layout'] ) ) {
            $items[] = $this->summary_item( __( 'Rozkład', 'estate-office' ), implode( ', ', $this->translate_layout( $meta['layout'] ) ) );
        }

        if ( ! empty( $meta['facilities'] ) ) {
            $items[] = $this->summary_item( __( 'Udogodnienia', 'estate-office' ), implode( ', ', $this->translate_facilities( (array) $meta['facilities'] ) ) );
        }

        if ( ! empty( $meta['equipment'] ) ) {
            $items[] = $this->summary_item( __( 'Wyposażenie', 'estate-office' ), implode( ', ', $this->translate_equipment( (array) $meta['equipment'] ) ) );
        }

        return array_values( array_filter( $items, static function ( $item ) {
            return '—' !== $item['value'];
        } ) );
    }

    private function build_property_additional_areas( array $meta ): array {
        $keys = [
            'balcony' => __( 'Balkon', 'estate-office' ),
            'terrace' => __( 'Taras', 'estate-office' ),
            'basement' => __( 'Piwnica', 'estate-office' ),
            'storage' => __( 'Komórka lokatorska', 'estate-office' ),
            'garden'  => __( 'Ogródek', 'estate-office' ),
        ];

        $items = [];

        foreach ( $keys as $key => $label ) {
            if ( empty( $meta[ $key ]['enabled'] ) ) {
                continue;
            }

            $details = [];
            if ( isset( $meta[ $key ]['count'] ) && '' !== $meta[ $key ]['count'] ) {
                $details[] = sprintf( _n( '%s szt.', '%s szt.', (int) $meta[ $key ]['count'], 'estate-office' ), $meta[ $key ]['count'] );
            }
            if ( isset( $meta[ $key ]['area'] ) && '' !== $meta[ $key ]['area'] ) {
                $details[] = sprintf( '%s m²', $meta[ $key ]['area'] );
            }

            $items[] = $this->summary_item( $label, implode( ', ', $details ) ?: __( 'Tak', 'estate-office' ) );
        }

        return $items;
    }

    private function build_property_media_items( array $meta ): array {
        $items = [];

        if ( ! empty( $meta['gallery'] ) ) {
            $ids   = array_filter( array_map( 'intval', explode( ',', (string) $meta['gallery'] ) ) );
            $count = count( $ids );
            if ( $count ) {
                $items[] = $this->summary_item( __( 'Galeria zdjęć', 'estate-office' ), sprintf( _n( '%d zdjęcie', '%d zdjęć', $count, 'estate-office' ), $count ) );
            }
        }

        if ( ! empty( $meta['plan2d'] ) ) {
            $items[] = $this->summary_item( __( 'Rzut 2D', 'estate-office' ), wp_get_attachment_url( (int) $meta['plan2d'] ) ?: sprintf( __( 'Załącznik #%d', 'estate-office' ), (int) $meta['plan2d'] ) );
        }

        if ( ! empty( $meta['plan3d'] ) ) {
            $items[] = $this->summary_item( __( 'Rzut 3D', 'estate-office' ), wp_get_attachment_url( (int) $meta['plan3d'] ) ?: sprintf( __( 'Załącznik #%d', 'estate-office' ), (int) $meta['plan3d'] ) );
        }

        if ( ! empty( $meta['video_url'] ) ) {
            $items[] = $this->summary_item( __( 'Link do filmu', 'estate-office' ), esc_url_raw( $meta['video_url'] ) );
        }

        if ( ! empty( $meta['virtual_tour_url'] ) ) {
            $items[] = $this->summary_item( __( 'Wirtualny spacer', 'estate-office' ), esc_url_raw( $meta['virtual_tour_url'] ) );
        }

        return $items;
    }

    private function get_contract_clients( int $contract_id ): array {
        $clients = get_post_meta( $contract_id, 'estate_office_related_clients', true );

        return is_array( $clients ) ? array_map( 'intval', $clients ) : [];
    }

    private function facility_labels(): array {
        return [
            'winda'                 => __( 'Winda', 'estate-office' ),
            'umeblowanie_pelne'     => __( 'Umeblowanie - tak', 'estate-office' ),
            'umeblowanie_czesciowe' => __( 'Umeblowanie - częściowe', 'estate-office' ),
            'klimatyzacja'          => __( 'Klimatyzacja', 'estate-office' ),
            'monitoring'            => __( 'Monitoring/Ochrona', 'estate-office' ),
            'recepcja'              => __( 'Recepcja', 'estate-office' ),
            'teren_zamkniety'       => __( 'Teren zamknięty', 'estate-office' ),
            'domofon'               => __( 'Domofon', 'estate-office' ),
        ];
    }

    private function equipment_labels(): array {
        return [
            'pralka'    => __( 'Pralka', 'estate-office' ),
            'zmywarka'  => __( 'Zmywarka', 'estate-office' ),
            'lodowka'   => __( 'Lodówka', 'estate-office' ),
            'kuchenka'  => __( 'Kuchenka', 'estate-office' ),
            'piekarnik' => __( 'Piekarnik', 'estate-office' ),
            'telewizor' => __( 'Telewizor', 'estate-office' ),
            'mikrofala' => __( 'Mikrofala', 'estate-office' ),
        ];
    }

    private function build_checklist_items( $selected, array $labels ): array {
        if ( ! is_array( $selected ) || empty( $selected ) ) {
            return [];
        }

        $values = [];
        foreach ( $selected as $key ) {
            $key = sanitize_key( $key );
            if ( isset( $labels[ $key ] ) ) {
                $values[] = $labels[ $key ];
            }
        }

        if ( empty( $values ) ) {
            return [];
        }

        return [ $this->summary_item( __( 'Wybrane opcje', 'estate-office' ), implode( ', ', $values ) ) ];
    }

    private function format_money( $value ): string {
        if ( '' === $value || null === $value ) {
            return $this->format_plain_value( '' );
        }

        return sprintf( '%s PLN', number_format_i18n( (float) $value, 2 ) );
    }

    private function format_money_per_sqm( $value ): string {
        if ( '' === $value || null === $value ) {
            return $this->format_plain_value( '' );
        }

        return sprintf( '%s PLN/m²', number_format_i18n( (float) $value, 2 ) );
    }

    private function format_area_value( $value ): string {
        if ( '' === $value || null === $value ) {
            return $this->format_plain_value( '' );
        }

        return sprintf( '%s m²', number_format_i18n( (float) $value, 2 ) );
    }

    private function format_plain_value( $value ): string {
        if ( null === $value ) {
            return '—';
        }

        $string = (string) $value;

        return '' === trim( $string ) ? '—' : $string;
    }

    private function format_range_value( $min, $max, string $unit ): string {
        $min = '' === $min ? null : $min;
        $max = '' === $max ? null : $max;

        if ( null === $min && null === $max ) {
            return $this->format_plain_value( '' );
        }

        if ( null !== $min && null !== $max ) {
            return sprintf( '%s - %s %s', number_format_i18n( (float) $min, 2 ), number_format_i18n( (float) $max, 2 ), $unit );
        }

        if ( null !== $min ) {
            return sprintf( __( 'Od %s %s', 'estate-office' ), number_format_i18n( (float) $min, 2 ), $unit );
        }

        return sprintf( __( 'Do %s %s', 'estate-office' ), number_format_i18n( (float) $max, 2 ), $unit );
    }

    private function translate_client_type( string $key ): string {
        $map = [
            'osoba' => __( 'Osoba fizyczna', 'estate-office' ),
            'firma' => __( 'Firma', 'estate-office' ),
        ];

        return $map[ $key ] ?? $key;
    }

    private function translate_document_type( string $key ): string {
        $map = [
            'dowod'        => __( 'Dowód osobisty', 'estate-office' ),
            'paszport'     => __( 'Paszport', 'estate-office' ),
            'karta_pobytu' => __( 'Karta pobytu', 'estate-office' ),
        ];

        return $map[ $key ] ?? $this->format_plain_value( '' );
    }

    private function translate_flags( array $flags, string $transaction ): array {
        $labels = $this->flag_labels();
        $items  = [];

        foreach ( $flags as $flag ) {
            $flag = sanitize_key( $flag );

            if ( 'sold' === $flag && 'sprzedaz' !== $transaction ) {
                continue;
            }

            if ( 'rented' === $flag && 'wynajem' !== $transaction ) {
                continue;
            }

            if ( isset( $labels[ $flag ] ) ) {
                $items[] = $labels[ $flag ];
            }
        }

        return $items;
    }

    private function flag_labels(): array {
        return [
            'new_offer'    => __( 'Nowa oferta', 'estate-office' ),
            'exclusive'    => __( 'Wyłączność', 'estate-office' ),
            'sold'         => __( 'Sprzedane', 'estate-office' ),
            'rented'       => __( 'Wynajęte', 'estate-office' ),
            'new_price'    => __( 'Nowa cena', 'estate-office' ),
            'no_commision' => __( 'Bez prowizji', 'estate-office' ),
            'mls'          => __( 'Oferta MLS', 'estate-office' ),
            'premium'      => __( 'Premium', 'estate-office' ),
            'export_www'   => __( 'Eksport na WWW', 'estate-office' ),
        ];
    }

    private function translate_legal_status( string $key ): string {
        $map = [
            'wlasnosc'      => __( 'Własność', 'estate-office' ),
            'wspolwlasnosc' => __( 'Współwłasność', 'estate-office' ),
            'spoldzielcze'  => __( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ),
            'dzierzawa'     => __( 'Dzierżawa', 'estate-office' ),
            'inne'          => __( 'Inne', 'estate-office' ),
        ];

        return $map[ $key ] ?? $this->format_plain_value( '' );
    }

    private function translate_house_type( string $key ): string {
        $map = [
            'wolnostojacy'  => __( 'Wolnostojący', 'estate-office' ),
            'blizniak'      => __( 'Bliźniak', 'estate-office' ),
            'szeregowiec'   => __( 'Szeregowiec', 'estate-office' ),
            'wielorodzinny' => __( 'Wielorodzinny', 'estate-office' ),
        ];

        return $map[ $key ] ?? $this->format_plain_value( '' );
    }

    private function translate_lot_shape( string $key ): string {
        $map = [
            'regularny'   => __( 'Regularny', 'estate-office' ),
            'nieregularny'=> __( 'Nieregularny', 'estate-office' ),
        ];

        return $map[ $key ] ?? $this->format_plain_value( '' );
    }

    private function translate_kitchen_type( string $key ): string {
        $map = [
            'aneks'     => __( 'Aneks', 'estate-office' ),
            'oddzielna' => __( 'Oddzielna', 'estate-office' ),
            'z_salonem' => __( 'Z salonem', 'estate-office' ),
        ];

        return $map[ $key ] ?? $this->format_plain_value( '' );
    }

    private function translate_parking_options( array $options ): array {
        $map = [
            'naziemne' => __( 'Naziemne', 'estate-office' ),
            'podziemne'=> __( 'Podziemne', 'estate-office' ),
            'garaz'    => __( 'Garaż', 'estate-office' ),
        ];

        $values = [];

        foreach ( $options as $option ) {
            $option = sanitize_key( $option );
            if ( isset( $map[ $option ] ) ) {
                $values[] = $map[ $option ];
            }
        }

        return $values;
    }

    private function translate_exposure( array $values ): array {
        $map = [
            'polnoc'  => __( 'Północ', 'estate-office' ),
            'poludnie'=> __( 'Południe', 'estate-office' ),
            'wschod'  => __( 'Wschód', 'estate-office' ),
            'zachod'  => __( 'Zachód', 'estate-office' ),
        ];

        return $this->map_multiple_values( $values, $map );
    }

    private function translate_view( array $values ): array {
        $map = [
            'miasto'   => __( 'Miasto', 'estate-office' ),
            'panorama' => __( 'Panorama', 'estate-office' ),
            'park'     => __( 'Park', 'estate-office' ),
            'inne'     => __( 'Inne', 'estate-office' ),
        ];

        return $this->map_multiple_values( $values, $map );
    }

    private function translate_layout( array $values ): array {
        $map = [
            'dwustronne'      => __( 'Dwustronne', 'estate-office' ),
            'jednostronne'    => __( 'Jednostronne', 'estate-office' ),
            'przechodnie'     => __( 'Przechodnie', 'estate-office' ),
            'otwarta_kuchnia' => __( 'Otwarta kuchnia', 'estate-office' ),
        ];

        return $this->map_multiple_values( $values, $map );
    }

    private function translate_facilities( array $values ): array {
        return $this->map_multiple_values( $values, $this->facility_labels() );
    }

    private function translate_equipment( array $values ): array {
        return $this->map_multiple_values( $values, $this->equipment_labels() );
    }

    private function map_multiple_values( array $values, array $map ): array {
        $output = [];

        foreach ( $values as $value ) {
            $value = sanitize_key( $value );
            if ( isset( $map[ $value ] ) ) {
                $output[] = $map[ $value ];
            }
        }

        return $output;
    }

    private function format_property_address_line( array $meta ): string {
        $parts = [];

        if ( ! empty( $meta['street'] ) ) {
            $parts[] = $meta['street'] . ( ! empty( $meta['building_number'] ) ? ' ' . $meta['building_number'] : '' );
        }

        if ( ! empty( $meta['city'] ) ) {
            $parts[] = $meta['city'];
        }

        return implode( ', ', $parts );
    }

    private function format_date_value( $value ): string {
        $value = $this->sanitize_text( $value );

        if ( ! $value ) {
            return '—';
        }

        $timestamp = strtotime( $value );

        return $timestamp ? date_i18n( get_option( 'date_format', 'Y-m-d' ), $timestamp ) : $value;
    }
}
