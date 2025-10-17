<?php
/**
 * Repozytorium nieruchomości EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Property_Repository
 */
class Estate_Office_Property_Repository {

    /**
     * Obiekt bazy danych WordPress.
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * Pełna nazwa tabeli nieruchomości.
     *
     * @var string
     */
    private string $table;

    /**
     * Mapowanie pól na formaty.
     *
     * @var array<string,string>
     */
    private array $field_formats = [
        'contract_id'           => '%d',
        'listing_number'        => '%s',
        'title'                 => '%s',
        'transaction_type'      => '%s',
        'property_type'         => '%s',
        'ownership_status'      => '%s',
        'price'                 => '%f',
        'price_per_sqm'         => '%f',
        'price_currency'        => '%s',
        'price_period'          => '%s',
        'administrative_rent'   => '%f',
        'area_total'            => '%f',
        'area_plot'             => '%f',
        'rooms'                 => '%d',
        'bedrooms'              => '%d',
        'bathrooms'             => '%d',
        'toilets'               => '%d',
        'year_built'            => '%d',
        'floor'                 => '%d',
        'total_floors'          => '%d',
        'plot_shape'            => '%s',
        'plot_length'           => '%f',
        'plot_width'            => '%f',
        'plot_dimensions_note'  => '%s',
        'land_register_number'  => '%s',
        'street'                => '%s',
        'street_number'         => '%s',
        'apartment_number'      => '%s',
        'postal_code'           => '%s',
        'district'              => '%s',
        'city'                  => '%s',
        'voivodeship'           => '%s',
        'county'                => '%s',
        'precinct'              => '%s',
        'plot_number'           => '%s',
        'latitude'              => '%f',
        'longitude'             => '%f',
        'description'           => '%s',
        'building_details'      => '%s',
        'media'                 => '%s',
        'amenities'             => '%s',
        'equipment'             => '%s',
        'additional_areas'      => '%s',
        'gallery'               => '%s',
        'floor_plan_2d'         => '%s',
        'floor_plan_3d'         => '%s',
        'labels'                => '%s',
        'export_web'            => '%d',
        'export_portals'        => '%d',
        'new_offer'             => '%d',
        'new_offer_until'       => '%s',
        'exclusive_offer'       => '%d',
        'sold_offer'            => '%d',
        'rented_offer'          => '%d',
        'new_price'             => '%d',
        'commission_free'       => '%d',
        'mls_offer'             => '%d',
        'premium_offer'         => '%d',
        'video_url'             => '%s',
        'virtual_tour_url'      => '%s',
        'google_place_id'       => '%s',
        'updated_at'            => '%s',
    ];

    /**
     * Konstruktor repozytorium.
     *
     * @param wpdb|null $wpdb_instance Opcjonalny obiekt bazy danych.
     */
    public function __construct( ?wpdb $wpdb_instance = null ) {
        global $wpdb;

        $this->wpdb  = $wpdb_instance ?? $wpdb;
        $this->table = $this->wpdb->prefix . 'estate_office_properties';
    }

    /**
     * Zwraca listę nieruchomości w formie paginowanej.
     *
     * @param array<string,mixed> $args Argumenty wyszukiwania.
     *
     * @return array<string,mixed>
     */
    public function paginate( array $args ) : array {
        $defaults = [
            'paged'            => 1,
            'per_page'         => 20,
            'search'           => '',
            'transaction_type' => '',
            'property_type'    => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $paged    = max( 1, (int) $args['paged'] );
        $per_page = max( 1, (int) $args['per_page'] );

        $this->expire_new_offer_flags();

        $where_clauses = [];
        $params        = [];

        if ( ! empty( $args['search'] ) ) {
            $search_like     = '%' . $this->wpdb->esc_like( (string) $args['search'] ) . '%';
            $where_clauses[] = '(listing_number LIKE %s OR city LIKE %s OR title LIKE %s OR street LIKE %s)';
            $params[]        = $search_like;
            $params[]        = $search_like;
            $params[]        = $search_like;
            $params[]        = $search_like;
        }

        if ( ! empty( $args['transaction_type'] ) ) {
            $where_clauses[] = 'transaction_type = %s';
            $params[]        = (string) $args['transaction_type'];
        }

        if ( ! empty( $args['property_type'] ) ) {
            $where_clauses[] = 'property_type = %s';
            $params[]        = (string) $args['property_type'];
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $select_sql = "SELECT * FROM {$this->table} {$where_sql} ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT %d OFFSET %d";
        $count_sql  = "SELECT COUNT(*) FROM {$this->table} {$where_sql}";

        $query_params = array_merge( $params, [ $per_page, ( $paged - 1 ) * $per_page ] );

        $items = $this->wpdb->get_results(
            $this->prepare_query( $select_sql, $query_params ),
            ARRAY_A
        );

        $total = (int) $this->wpdb->get_var( $this->prepare_query( $count_sql, $params ) );

        return [
            'items'      => is_array( $items ) ? $items : [],
            'total'      => $total,
            'per_page'   => $per_page,
            'total_page' => (int) ceil( $total / $per_page ),
        ];
    }

    /**
     * Pobiera listę ofert przeznaczonych do eksportu na WWW.
     *
     * @param array<string,mixed> $args Argumenty filtrowania.
     *
     * @return array<string,mixed>
     */
    public function get_exported_offers( array $args ) : array {
        $defaults = [
            'transaction_types' => [],
            'property_type'     => '',
            'city'              => '',
            'district'          => '',
            'paged'             => 1,
            'per_page'          => 12,
        ];

        $args = wp_parse_args( $args, $defaults );

        $paged    = max( 1, (int) $args['paged'] );
        $per_page = max( 1, (int) $args['per_page'] );

        $this->expire_new_offer_flags();

        [ $where_sql, $params ] = $this->build_offer_where( $args );

        $order      = 'ORDER BY COALESCE(updated_at, created_at) DESC, id DESC';
        $limit_sql  = ' LIMIT %d OFFSET %d';
        $select_sql = "SELECT * FROM {$this->table} {$where_sql} {$order}{$limit_sql}";
        $count_sql  = "SELECT COUNT(*) FROM {$this->table} {$where_sql}";

        $items = $this->wpdb->get_results(
            $this->prepare_query( $select_sql, array_merge( $params, [ $per_page, ( $paged - 1 ) * $per_page ] ) ),
            ARRAY_A
        );

        $total = (int) $this->wpdb->get_var( $this->prepare_query( $count_sql, $params ) );

        return [
            'items'        => is_array( $items ) ? $items : [],
            'total'        => $total,
            'per_page'     => $per_page,
            'total_page'   => (int) ceil( $total / $per_page ),
            'current_page' => $paged,
        ];
    }

    /**
     * Zwraca dane przeznaczone do eksportu na portale.
     *
     * @param array<string,mixed> $filters Filtry portalu.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_portal_export_payload( array $filters = [] ) : array {
        $defaults = [
            'transaction_types' => [],
            'property_types'    => [],
            'cities'            => [],
            'districts'         => [],
        ];

        $filters = wp_parse_args( $filters, $defaults );

        $conditions = [ 'export_portals = 1' ];
        $params     = [];

        $map_filters = [
            'transaction_types' => 'transaction_type',
            'property_types'    => 'property_type',
            'cities'            => 'city',
            'districts'         => 'district',
        ];

        foreach ( $map_filters as $filter_key => $column ) {
            $values = $this->sanitize_filter_values( $filters[ $filter_key ] ?? [] );
            if ( ! empty( $values ) ) {
                $placeholders  = implode( ',', array_fill( 0, count( $values ), '%s' ) );
                $conditions[]  = "{$column} IN ({$placeholders})";
                $params        = array_merge( $params, $values );
            }
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $conditions );
        $query     = "SELECT * FROM {$this->table} {$where_sql} ORDER BY COALESCE(updated_at, created_at) DESC, id DESC";

        $items = $this->wpdb->get_results( $this->prepare_query( $query, $params ), ARRAY_A );
        if ( ! is_array( $items ) ) {
            return [];
        }

        return array_map( [ $this, 'normalize_portal_property' ], $items );
    }

    /**
     * Zwraca ofertę przeznaczoną do publikacji na WWW.
     *
     * @param int $property_id ID nieruchomości.
     *
     * @return array<string,mixed>|null
     */
    public function find_exported_offer( int $property_id ) : ?array {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND export_web = 1",
            $property_id
        );

        $result = $this->wpdb->get_row( $query, ARRAY_A );

        return $result ?: null;
    }

    /**
     * Zwraca listę wartości filtrów dostępnych dla ofert.
     *
     * @param array<string,mixed> $args Argumenty kontekstowe.
     *
     * @return array<string,array<int,string>>
     */
    public function get_offer_filters( array $args ) : array {
        $defaults = [
            'transaction_types' => [],
        ];

        $args = wp_parse_args( $args, $defaults );

        [ $where_sql, $params ] = $this->build_offer_where( $args );

        $filters = [
            'transaction_type' => [],
            'property_type'    => [],
            'city'             => [],
            'district'         => [],
        ];

        foreach ( $filters as $column => $list ) {
            $sql      = "SELECT DISTINCT {$column} FROM {$this->table} {$where_sql} AND {$column} <> '' ORDER BY {$column} ASC";
            $values   = $this->wpdb->get_col( $this->prepare_query( $sql, $params ) );
            $filters[ $column ] = array_values( array_unique( array_map( 'sanitize_text_field', array_filter( (array) $values ) ) ) );
        }

        return $filters;
    }

    /**
     * Wyszukuje nieruchomość po ID.
     *
     * @param int $property_id ID nieruchomości.
     *
     * @return array<string,mixed>|null
     */
    public function find( int $property_id ) : ?array {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $property_id
        );

        $result = $this->wpdb->get_row( $query, ARRAY_A );

        return $result ?: null;
    }

    /**
     * Tworzy nową nieruchomość.
     *
     * @param array<string,mixed> $data Dane.
     *
     * @return int
     */
    public function create( array $data ) : int {
        $data = $this->prepare_data( $data, true );

        $this->wpdb->insert( $this->table, $data, $this->resolve_formats( array_keys( $data ) ) );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Aktualizuje nieruchomość.
     *
     * @param int                 $property_id ID rekordu.
     * @param array<string,mixed> $data        Dane do zapisania.
     *
     * @return bool
     */
    public function update( int $property_id, array $data ) : bool {
        $data = $this->prepare_data( $data, false );

        if ( empty( $data ) ) {
            return false;
        }

        $updated = $this->wpdb->update(
            $this->table,
            $data,
            [ 'id' => $property_id ],
            $this->resolve_formats( array_keys( $data ) ),
            [ '%d' ]
        );

        return false !== $updated;
    }

    /**
     * Usuwa nieruchomość.
     *
     * @param int $property_id ID rekordu.
     *
     * @return bool
     */
    public function delete( int $property_id ) : bool {
        $deleted = $this->wpdb->delete( $this->table, [ 'id' => $property_id ], [ '%d' ] );

        return false !== $deleted;
    }

    /**
     * Generuje unikalny numer oferty.
     *
     * @return string
     */
    public function generate_listing_number() : string {
        $prefix = 'EO-' . gmdate( 'ymd' );
        $index  = 1;

        do {
            $candidate = sprintf( '%s-%03d', $prefix, $index );
            $index++;
        } while ( $this->listing_number_exists( $candidate ) );

        return $candidate;
    }

    /**
     * Dezaktualizuje flagę "nowa oferta" po upłynięciu terminu.
     *
     * @return void
     */
    public function expire_new_offer_flags() : void {
        $now = current_time( 'mysql' );

        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET new_offer = 0, new_offer_until = NULL WHERE new_offer = 1 AND new_offer_until IS NOT NULL AND new_offer_until < %s",
                $now
            )
        );
    }

    /**
     * Sprawdza, czy numer oferty już istnieje.
     *
     * @param string $listing_number Numer oferty.
     *
     * @return bool
     */
    public function listing_number_exists( string $listing_number ) : bool {
        $query = $this->wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE listing_number = %s",
            $listing_number
        );

        $result = $this->wpdb->get_var( $query );

        return ! empty( $result );
    }

    /**
     * Pobiera nieruchomość po numerze oferty.
     *
     * @param string $listing_number Numer oferty.
     *
     * @return array<string,mixed>|null
     */
    public function find_by_listing_number( string $listing_number ) : ?array {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE listing_number = %s",
            $listing_number
        );

        $result = $this->wpdb->get_row( $query, ARRAY_A );

        return $result ?: null;
    }

    /**
     * Przygotowuje dane do zapisu.
     *
     * @param array<string,mixed> $data   Dane wejściowe.
     * @param bool                $is_new Czy to nowy rekord.
     *
     * @return array<string,mixed>
     */
    private function prepare_data( array $data, bool $is_new ) : array {
        $filtered = [];

        foreach ( $this->field_formats as $field => $format ) {
            if ( ! array_key_exists( $field, $data ) ) {
                continue;
            }

            $value = $data[ $field ];

            if ( null === $value ) {
                $filtered[ $field ] = null;
                continue;
            }

            if ( in_array( $field, [ 'building_details', 'media', 'amenities', 'equipment', 'additional_areas', 'gallery', 'labels' ], true ) ) {
                $filtered[ $field ] = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                continue;
            }

            if ( '%d' === $format ) {
                $filtered[ $field ] = (int) $value;
            } elseif ( '%f' === $format ) {
                $filtered[ $field ] = null !== $value && '' !== $value ? (float) $value : null;
            } else {
                $filtered[ $field ] = (string) $value;
            }
        }

        if ( isset( $filtered['price'], $filtered['area_total'] ) && $filtered['price'] > 0 && $filtered['area_total'] > 0 ) {
            $filtered['price_per_sqm'] = round( $filtered['price'] / max( 0.01, $filtered['area_total'] ), 2 );
        }

        $current_time = current_time( 'mysql' );

        if ( $is_new ) {
            $filtered['created_at'] = $current_time;
        }

        $filtered['updated_at'] = $current_time;

        return $filtered;
    }

    /**
     * Mapuje listę pól na formaty.
     *
     * @param array<int,string> $fields Pola.
     *
     * @return array<int,string>
     */
    private function resolve_formats( array $fields ) : array {
        $formats = [];

        foreach ( $fields as $field ) {
            if ( isset( $this->field_formats[ $field ] ) ) {
                $formats[] = $this->field_formats[ $field ];
            } elseif ( 'created_at' === $field ) {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    /**
     * Przygotowuje zapytanie SQL z parametrami.
     *
     * @param string             $sql    Szablon SQL.
     * @param array<int,mixed>   $params Parametry.
     *
     * @return string
     */
    private function prepare_query( string $sql, array $params ) : string {
        if ( empty( $params ) ) {
            return $sql;
        }

        return $this->wpdb->prepare( $sql, $params );
    }

    /**
     * Buduje część WHERE dla zapytań ofertowych.
     *
     * @param array<string,mixed> $args Argumenty filtrowania.
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private function build_offer_where( array $args ) : array {
        $conditions = [ 'export_web = 1' ];
        $params     = [];

        if ( ! empty( $args['transaction_types'] ) && is_array( $args['transaction_types'] ) ) {
            $types = array_values( array_filter( array_map( 'strval', $args['transaction_types'] ) ) );
            if ( ! empty( $types ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
                $conditions[] = "transaction_type IN ({$placeholders})";
                $params       = array_merge( $params, $types );
            }
        }

        foreach ( [ 'property_type', 'city', 'district' ] as $field ) {
            if ( ! empty( $args[ $field ] ) ) {
                $conditions[] = "{$field} = %s";
                $params[]     = (string) $args[ $field ];
            }
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $conditions );

        return [ $where_sql, $params ];
    }

    /**
     * Normalizuje rekord nieruchomości do struktury eksportowej.
     *
     * @param array<string,mixed> $row Rekord bazy.
     *
     * @return array<string,mixed>
     */
    private function normalize_portal_property( array $row ) : array {
        foreach ( [ 'building_details', 'media', 'amenities', 'equipment', 'additional_areas', 'gallery', 'labels' ] as $field ) {
            if ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) ) {
                $decoded = json_decode( $row[ $field ], true );
                if ( null !== $decoded && JSON_ERROR_NONE === json_last_error() ) {
                    $row[ $field ] = $decoded;
                }
            }
        }

        $building  = is_array( $row['building_details'] ?? null ) ? $row['building_details'] : [];
        $media     = is_array( $row['media'] ?? null ) ? $row['media'] : [];
        $equipment = is_array( $row['equipment'] ?? null ) ? $row['equipment'] : [];
        $areas     = is_array( $row['additional_areas'] ?? null ) ? $row['additional_areas'] : [];
        $amenities = is_array( $row['amenities'] ?? null ) ? $row['amenities'] : [];
        $labels    = is_array( $row['labels'] ?? null ) ? $row['labels'] : [];

        $gallery_ids   = is_array( $row['gallery'] ?? null ) ? array_map( 'absint', $row['gallery'] ) : [];
        $gallery_items = [];
        foreach ( $gallery_ids as $attachment_id ) {
            $attachment = $this->normalize_attachment( $attachment_id );
            if ( null !== $attachment ) {
                $gallery_items[] = $attachment;
            }
        }

        $floor_plan_2d = $this->normalize_attachment( isset( $row['floor_plan_2d'] ) ? (int) $row['floor_plan_2d'] : 0 );
        $floor_plan_3d = $this->normalize_attachment( isset( $row['floor_plan_3d'] ) ? (int) $row['floor_plan_3d'] : 0 );

        $flags = [
            'new_offer'        => ! empty( $row['new_offer'] ),
            'exclusive_offer'  => ! empty( $row['exclusive_offer'] ),
            'sold_offer'       => ! empty( $row['sold_offer'] ),
            'rented_offer'     => ! empty( $row['rented_offer'] ),
            'new_price'        => ! empty( $row['new_price'] ),
            'commission_free'  => ! empty( $row['commission_free'] ),
            'mls_offer'        => ! empty( $row['mls_offer'] ),
            'premium_offer'    => ! empty( $row['premium_offer'] ),
            'export_web'       => ! empty( $row['export_web'] ),
            'export_portals'   => ! empty( $row['export_portals'] ),
        ];

        $pricing = [
            'amount'             => $this->to_float( $row['price'] ?? null ),
            'currency'           => (string) ( $row['price_currency'] ?? 'PLN' ),
            'period'             => (string) ( $row['price_period'] ?? '' ),
            'administrative_rent'=> $this->to_float( $row['administrative_rent'] ?? null ),
            'price_per_sqm'      => $this->to_float( $row['price_per_sqm'] ?? null ),
        ];

        $dimensions = [
            'area_total'   => $this->to_float( $row['area_total'] ?? null ),
            'area_plot'    => $this->to_float( $row['area_plot'] ?? null ),
            'rooms'        => $this->to_int( $row['rooms'] ?? null ),
            'bedrooms'     => $this->to_int( $row['bedrooms'] ?? null ),
            'bathrooms'    => $this->to_int( $row['bathrooms'] ?? null ),
            'toilets'      => $this->to_int( $row['toilets'] ?? null ),
            'floor'        => $this->to_int( $row['floor'] ?? null ),
            'total_floors' => $this->to_int( $row['total_floors'] ?? null ),
            'year_built'   => $this->to_int( $row['year_built'] ?? null ),
            'plot_shape'   => (string) ( $row['plot_shape'] ?? '' ),
            'plot_length'  => $this->to_float( $row['plot_length'] ?? null ),
            'plot_width'   => $this->to_float( $row['plot_width'] ?? null ),
        ];

        $address = [
            'street'           => (string) ( $row['street'] ?? '' ),
            'street_number'    => (string) ( $row['street_number'] ?? '' ),
            'apartment_number' => (string) ( $row['apartment_number'] ?? '' ),
            'postal_code'      => (string) ( $row['postal_code'] ?? '' ),
            'district'         => (string) ( $row['district'] ?? '' ),
            'city'             => (string) ( $row['city'] ?? '' ),
            'voivodeship'      => (string) ( $row['voivodeship'] ?? '' ),
            'county'           => (string) ( $row['county'] ?? '' ),
            'precinct'         => (string) ( $row['precinct'] ?? '' ),
            'plot_number'      => (string) ( $row['plot_number'] ?? '' ),
            'latitude'         => $this->to_float( $row['latitude'] ?? null ),
            'longitude'        => $this->to_float( $row['longitude'] ?? null ),
        ];

        $legal = [
            'land_register_number' => (string) ( $row['land_register_number'] ?? '' ),
            'land_register_missing'=> empty( $row['land_register_number'] ),
            'ownership_status'     => (string) ( $row['ownership_status'] ?? '' ),
        ];

        return [
            'id'               => (int) $row['id'],
            'contract_id'      => (int) $row['contract_id'],
            'listing_number'   => (string) ( $row['listing_number'] ?? '' ),
            'title'            => (string) ( $row['title'] ?: $row['listing_number'] ?? '' ),
            'transaction_type' => (string) ( $row['transaction_type'] ?? '' ),
            'property_type'    => (string) ( $row['property_type'] ?? '' ),
            'description'      => wp_strip_all_tags( (string) ( $row['description'] ?? '' ) ),
            'building'         => [
                'finish_state' => (string) ( $building['finish_state'] ?? '' ),
                'exposure'     => $this->sanitize_filter_values( $building['exposure'] ?? [] ),
                'view'         => $this->sanitize_filter_values( $building['view'] ?? [] ),
                'attic'        => ! empty( $building['attic'] ),
                'multi_level'  => ! empty( $building['multi_level'] ),
                'layout'       => $this->sanitize_filter_values( $building['layout'] ?? [] ),
                'kitchen_type' => (string) ( $building['kitchen_type'] ?? '' ),
                'parking'      => [
                    'available' => ! empty( $building['parking']['available'] ?? false ),
                    'types'     => $this->sanitize_filter_values( $building['parking']['types'] ?? [] ),
                ],
            ],
            'media'            => [
                'heating' => (string) ( $media['heating'] ?? '' ),
                'water'   => (string) ( $media['water'] ?? '' ),
                'sewage'  => (string) ( $media['sewage'] ?? '' ),
                'gas'     => ! empty( $media['gas'] ),
            ],
            'amenities'        => $this->sanitize_filter_values( $amenities ),
            'equipment'        => [
                'level' => (string) ( $equipment['level'] ?? '' ),
                'items' => $this->sanitize_filter_values( $equipment['items'] ?? [] ),
            ],
            'additional_areas' => $this->normalize_additional_areas( $areas ),
            'gallery'          => $gallery_items,
            'floor_plans'      => [
                'plan_2d' => $floor_plan_2d,
                'plan_3d' => $floor_plan_3d,
            ],
            'media_links'      => [
                'video'        => esc_url_raw( (string) ( $row['video_url'] ?? '' ) ),
                'virtual_tour' => esc_url_raw( (string) ( $row['virtual_tour_url'] ?? '' ) ),
            ],
            'pricing'          => $pricing,
            'dimensions'       => $dimensions,
            'address'          => $address,
            'legal'            => $legal,
            'flags'            => $flags,
            'flags_meta'       => [
                'new_offer_until' => (string) ( $row['new_offer_until'] ?? '' ),
            ],
            'labels'           => $this->sanitize_filter_values( $labels ),
            'google_place_id'  => (string) ( $row['google_place_id'] ?? '' ),
            'created_at'       => (string) ( $row['created_at'] ?? '' ),
            'updated_at'       => (string) ( $row['updated_at'] ?? '' ),
        ];
    }

    /**
     * Normalizuje strukturę powierzchni dodatkowych.
     *
     * @param array<string,mixed> $areas Dane powierzchni.
     *
     * @return array<string,mixed>
     */
    private function normalize_additional_areas( array $areas ) : array {
        $defaults = [
            'balcony' => [ 'enabled' => false, 'count' => '', 'area' => '' ],
            'terrace' => [ 'enabled' => false, 'count' => '', 'area' => '' ],
            'cellar'  => [ 'enabled' => false, 'area' => '' ],
            'storage' => [ 'enabled' => false, 'area' => '' ],
            'garden'  => [ 'enabled' => false, 'area' => '' ],
        ];

        $normalized = [];
        foreach ( $defaults as $key => $default ) {
            $current = is_array( $areas[ $key ] ?? null ) ? $areas[ $key ] : [];
            $merged  = wp_parse_args( $current, $default );
            $merged['enabled'] = ! empty( $merged['enabled'] );
            foreach ( [ 'count', 'area' ] as $sub_key ) {
                if ( isset( $merged[ $sub_key ] ) && '' !== $merged[ $sub_key ] ) {
                    $merged[ $sub_key ] = is_numeric( $merged[ $sub_key ] ) ? $merged[ $sub_key ] + 0 : sanitize_text_field( (string) $merged[ $sub_key ] );
                }
            }
            $normalized[ $key ] = $merged;
        }

        return $normalized;
    }

    /**
     * Konwertuje wartości filtrów do listy stringów.
     *
     * @param mixed $values Wartości.
     *
     * @return array<int,string>
     */
    private function sanitize_filter_values( $values ) : array {
        if ( is_string( $values ) ) {
            $values = [ $values ];
        }

        if ( ! is_array( $values ) ) {
            return [];
        }

        $clean = [];
        foreach ( $values as $value ) {
            $value = trim( (string) $value );
            if ( '' !== $value ) {
                $clean[] = $value;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * Konwertuje wartość do liczby zmiennoprzecinkowej.
     *
     * @param mixed $value Wartość wejściowa.
     *
     * @return float|null
     */
    private function to_float( $value ) : ?float {
        if ( null === $value || '' === $value ) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Konwertuje wartość do liczby całkowitej.
     *
     * @param mixed $value Wartość wejściowa.
     *
     * @return int|null
     */
    private function to_int( $value ) : ?int {
        if ( null === $value || '' === $value ) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Normalizuje załącznik na potrzeby eksportu.
     *
     * @param int $attachment_id ID załącznika.
     *
     * @return array<string,mixed>|null
     */
    private function normalize_attachment( int $attachment_id ) : ?array {
        if ( $attachment_id <= 0 ) {
            return null;
        }

        $url = wp_get_attachment_url( $attachment_id );
        if ( ! $url ) {
            return null;
        }

        $attachment = get_post( $attachment_id );

        return [
            'id'    => $attachment_id,
            'url'   => esc_url_raw( $url ),
            'title' => $attachment instanceof WP_Post ? $attachment->post_title : '',
        ];
    }
}
