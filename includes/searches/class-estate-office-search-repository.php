<?php
/**
 * Repozytorium poszukiwań EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Search_Repository
 */
class Estate_Office_Search_Repository {

    /**
     * Obiekt bazy danych WordPress.
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * Nazwa tabeli poszukiwań.
     *
     * @var string
     */
    private string $table;

    /**
     * Mapowanie pól na formaty zapisu.
     *
     * @var array<string,string>
     */
    private array $field_formats = [
        'contract_id'           => '%d',
        'search_number'         => '%s',
        'transaction_type'      => '%s',
        'property_type'         => '%s',
        'location_city'         => '%s',
        'location_district'     => '%s',
        'location_voivodeship'  => '%s',
        'location_keywords'     => '%s',
        'price_min'             => '%f',
        'price_max'             => '%f',
        'area_min'              => '%f',
        'area_max'              => '%f',
        'rooms_min'             => '%d',
        'rooms_max'             => '%d',
        'description'           => '%s',
        'criteria'              => '%s',
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
        $this->table = $this->wpdb->prefix . 'estate_office_searches';
    }

    /**
     * Paginowana lista poszukiwań.
     *
     * @param array<string,mixed> $args Argumenty filtrów.
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

        $where_clauses = [];
        $params        = [];

        if ( ! empty( $args['search'] ) ) {
            $search_like     = '%' . $this->wpdb->esc_like( (string) $args['search'] ) . '%';
            $where_clauses[] = '(
                search_number LIKE %s OR
                location_city LIKE %s OR
                location_district LIKE %s OR
                location_keywords LIKE %s
            )';
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
            'items'      => array_map( [ $this, 'normalize_record' ], is_array( $items ) ? $items : [] ),
            'total'      => $total,
            'per_page'   => $per_page,
            'total_page' => (int) ceil( $total / $per_page ),
        ];
    }

    /**
     * Znajduje poszukiwanie po ID.
     *
     * @param int $search_id ID rekordu.
     *
     * @return array<string,mixed>|null
     */
    public function find( int $search_id ) : ?array {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $search_id
        );

        $result = $this->wpdb->get_row( $query, ARRAY_A );

        return $result ? $this->normalize_record( $result ) : null;
    }

    /**
     * Zwraca rekord na podstawie numeru poszukiwania.
     *
     * @param string $search_number Numer poszukiwania.
     *
     * @return array<string,mixed>|null
     */
    public function find_by_search_number( string $search_number ) : ?array {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE search_number = %s",
            $search_number
        );

        $result = $this->wpdb->get_row( $query, ARRAY_A );

        return $result ? $this->normalize_record( $result ) : null;
    }

    /**
     * Tworzy nowe poszukiwanie.
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
     * Aktualizuje istniejące poszukiwanie.
     *
     * @param int                 $search_id ID rekordu.
     * @param array<string,mixed> $data      Dane do zapisania.
     *
     * @return bool
     */
    public function update( int $search_id, array $data ) : bool {
        $data = $this->prepare_data( $data, false );

        if ( empty( $data ) ) {
            return false;
        }

        $updated = $this->wpdb->update(
            $this->table,
            $data,
            [ 'id' => $search_id ],
            $this->resolve_formats( array_keys( $data ) ),
            [ '%d' ]
        );

        return false !== $updated;
    }

    /**
     * Usuwa poszukiwanie.
     *
     * @param int $search_id ID rekordu.
     *
     * @return bool
     */
    public function delete( int $search_id ) : bool {
        $deleted = $this->wpdb->delete( $this->table, [ 'id' => $search_id ], [ '%d' ] );

        return false !== $deleted;
    }

    /**
     * Sprawdza, czy numer poszukiwania jest już zajęty.
     *
     * @param string $search_number Numer.
     *
     * @return bool
     */
    public function search_number_exists( string $search_number ) : bool {
        $query = $this->wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE search_number = %s",
            $search_number
        );

        return (bool) $this->wpdb->get_var( $query );
    }

    /**
     * Generuje nowy numer poszukiwania.
     *
     * @return string
     */
    public function generate_search_number() : string {
        $prefix = 'EOS-' . gmdate( 'ymd' );
        $index  = 1;

        do {
            $candidate = sprintf( '%s-%03d', $prefix, $index );
            $index++;
        } while ( $this->search_number_exists( $candidate ) );

        return $candidate;
    }

    /**
     * Normalizuje rekord z bazy danych.
     *
     * @param array<string,mixed> $record Dane.
     *
     * @return array<string,mixed>
     */
    private function normalize_record( array $record ) : array {
        if ( isset( $record['criteria'] ) ) {
            $decoded = json_decode( (string) $record['criteria'], true );
            if ( is_array( $decoded ) ) {
                $record['criteria'] = $decoded;
            }
        }

        return $record;
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

            if ( 'criteria' === $field ) {
                $filtered[ $field ] = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                continue;
            }

            if ( '%d' === $format ) {
                $filtered[ $field ] = (int) $value;
            } elseif ( '%f' === $format ) {
                $filtered[ $field ] = '' === $value ? null : (float) $value;
            } else {
                $filtered[ $field ] = (string) $value;
            }
        }

        $current_time = current_time( 'mysql' );

        if ( $is_new ) {
            $filtered['created_at'] = $current_time;
        }

        $filtered['updated_at'] = $current_time;

        return $filtered;
    }

    /**
     * Mapuje pola na formaty zapytań.
     *
     * @param array<int,string> $fields Lista pól.
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
     * Przygotowuje zapytanie SQL.
     *
     * @param string           $sql    Zapytanie.
     * @param array<int,mixed> $params Parametry.
     *
     * @return string
     */
    private function prepare_query( string $sql, array $params ) : string {
        if ( empty( $params ) ) {
            return $sql;
        }

        return $this->wpdb->prepare( $sql, $params );
    }
}
