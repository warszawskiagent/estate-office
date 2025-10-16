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
}
