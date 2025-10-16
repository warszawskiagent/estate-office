<?php
/**
 * Repozytorium klientów EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Client_Repository
 */
class Estate_Office_Client_Repository {

    /**
     * Obiekt bazy danych WordPress.
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * Nazwa tabeli klientów.
     *
     * @var string
     */
    private string $table;

    /**
     * Nazwa tabeli powiązań klientów z umowami.
     *
     * @var string
     */
    private string $contract_clients_table;

    /**
     * Nazwa tabeli umów.
     *
     * @var string
     */
    private string $contracts_table;

    /**
     * Mapowanie pól na formaty przygotowane do zapisu.
     *
     * @var array<string,string>
     */
    private array $field_formats = [
        'client_type'              => '%s',
        'first_name'               => '%s',
        'last_name'                => '%s',
        'company_name'             => '%s',
        'representative_name'      => '%s',
        'phone'                    => '%s',
        'email'                    => '%s',
        'website'                  => '%s',
        'document_type'            => '%s',
        'document_number'          => '%s',
        'pesel'                    => '%s',
        'nip'                      => '%s',
        'krs'                      => '%s',
        'regon'                    => '%s',
        'address_street'           => '%s',
        'address_number'           => '%s',
        'address_unit'             => '%s',
        'address_postal_code'      => '%s',
        'address_city'             => '%s',
        'address_district'         => '%s',
        'address_country'          => '%s',
        'correspondence_same'      => '%d',
        'correspondence_street'    => '%s',
        'correspondence_number'    => '%s',
        'correspondence_unit'      => '%s',
        'correspondence_postal_code' => '%s',
        'correspondence_city'      => '%s',
        'correspondence_country'   => '%s',
        'notes'                    => '%s',
        'meta'                     => '%s',
        'updated_at'               => '%s',
    ];

    /**
     * Konstruktor repozytorium.
     *
     * @param wpdb|null $wpdb_instance Opcjonalny obiekt bazy danych.
     */
    public function __construct( ?wpdb $wpdb_instance = null ) {
        global $wpdb;

        $this->wpdb                   = $wpdb_instance ?? $wpdb;
        $this->table                  = $this->wpdb->prefix . 'estate_office_clients';
        $this->contract_clients_table = $this->wpdb->prefix . 'estate_office_contract_clients';
        $this->contracts_table        = $this->wpdb->prefix . 'estate_office_contracts';
    }

    /**
     * Zwraca listę klientów wraz z paginacją.
     *
     * @param array<string,mixed> $args Argumenty wyszukiwania.
     *
     * @return array<string,mixed>
     */
    public function paginate( array $args ) : array {
        $defaults = [
            'paged'      => 1,
            'per_page'   => 20,
            'search'     => '',
            'client_type'=> '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $paged    = max( 1, (int) $args['paged'] );
        $per_page = max( 1, (int) $args['per_page'] );

        $where_clauses = [];
        $params        = [];

        if ( ! empty( $args['search'] ) ) {
            $search_like     = '%' . $this->wpdb->esc_like( (string) $args['search'] ) . '%';
            $where_clauses[] = '('
                . 'CONCAT(first_name, " ", last_name) LIKE %s'
                . ' OR company_name LIKE %s'
                . ' OR representative_name LIKE %s'
                . ' OR phone LIKE %s'
                . ' OR email LIKE %s'
                . ')';
            $params[]        = $search_like;
            $params[]        = $search_like;
            $params[]        = $search_like;
            $params[]        = $search_like;
            $params[]        = $search_like;
        }

        if ( ! empty( $args['client_type'] ) ) {
            $where_clauses[] = 'client_type = %s';
            $params[]        = (string) $args['client_type'];
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
     * Pobiera klienta po ID.
     *
     * @param int $client_id ID klienta.
     *
     * @return array<string,mixed>|null
     */
    public function find( int $client_id ) : ?array {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $client_id
        );

        $result = $this->wpdb->get_row( $query, ARRAY_A );

        return $result ?: null;
    }

    /**
     * Tworzy nowego klienta.
     *
     * @param array<string,mixed> $data Dane wejściowe.
     *
     * @return int
     */
    public function create( array $data ) : int {
        $data = $this->prepare_data( $data, true );

        $this->wpdb->insert( $this->table, $data, $this->resolve_formats( array_keys( $data ) ) );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Aktualizuje dane klienta.
     *
     * @param int                 $client_id ID klienta.
     * @param array<string,mixed> $data      Dane do zapisania.
     *
     * @return bool
     */
    public function update( int $client_id, array $data ) : bool {
        $data = $this->prepare_data( $data, false );

        if ( empty( $data ) ) {
            return false;
        }

        $updated = $this->wpdb->update(
            $this->table,
            $data,
            [ 'id' => $client_id ],
            $this->resolve_formats( array_keys( $data ) ),
            [ '%d' ]
        );

        return false !== $updated;
    }

    /**
     * Usuwa klienta.
     *
     * @param int $client_id ID klienta.
     *
     * @return bool
     */
    public function delete( int $client_id ) : bool {
        $deleted = $this->wpdb->delete( $this->table, [ 'id' => $client_id ], [ '%d' ] );

        return false !== $deleted;
    }

    /**
     * Wyszukuje klientów do szybkiej selekcji.
     *
     * @param string $term  Wyszukiwana fraza.
     * @param int    $limit Limit wyników.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search( string $term, int $limit = 20 ) : array {
        $term_like = '%' . $this->wpdb->esc_like( $term ) . '%';

        $sql = $this->wpdb->prepare(
            "SELECT id, client_type, first_name, last_name, company_name, phone, email FROM {$this->table} "
            . "WHERE CONCAT(first_name, ' ', last_name) LIKE %s OR company_name LIKE %s OR phone LIKE %s OR email LIKE %s "
            . 'ORDER BY COALESCE(updated_at, created_at) DESC LIMIT %d',
            $term_like,
            $term_like,
            $term_like,
            $term_like,
            max( 1, $limit )
        );

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        return is_array( $results ) ? $results : [];
    }

    /**
     * Zwraca umowy przypisane do klienta.
     *
     * @param int $client_id ID klienta.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_contracts_for_client( int $client_id ) : array {
        $sql = $this->wpdb->prepare(
            "SELECT c.* , cc.role FROM {$this->contracts_table} c "
            . "INNER JOIN {$this->contract_clients_table} cc ON c.id = cc.contract_id "
            . 'WHERE cc.client_id = %d ORDER BY COALESCE(c.updated_at, c.created_at) DESC, c.id DESC',
            $client_id
        );

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        return is_array( $results ) ? $results : [];
    }

    /**
     * Przygotowuje dane do zapisu w bazie.
     *
     * @param array<string,mixed> $data  Dane wejściowe.
     * @param bool                $is_new Czy tworzymy nowy rekord.
     *
     * @return array<string,mixed>
     */
    private function prepare_data( array $data, bool $is_new ) : array {
        $prepared = [];

        foreach ( $this->field_formats as $field => $format ) {
            if ( ! array_key_exists( $field, $data ) ) {
                continue;
            }

            $value = $data[ $field ];

            switch ( $field ) {
                case 'correspondence_same':
                    $prepared[ $field ] = ! empty( $value ) ? 1 : 0;
                    break;
                case 'notes':
                    $prepared[ $field ] = wp_kses_post( (string) $value );
                    break;
                case 'email':
                    $prepared[ $field ] = sanitize_email( (string) $value );
                    break;
                case 'website':
                    $prepared[ $field ] = esc_url_raw( (string) $value );
                    break;
                case 'meta':
                    if ( is_array( $value ) ) {
                        try {
                            $prepared[ $field ] = wp_json_encode( $value, JSON_THROW_ON_ERROR );
                        } catch ( JsonException $exception ) {
                            $prepared[ $field ] = '';
                        }
                    } else {
                        $prepared[ $field ] = sanitize_text_field( (string) $value );
                    }
                    break;
                default:
                    $prepared[ $field ] = sanitize_text_field( (string) $value );
                    break;
            }
        }

        $prepared['updated_at'] = current_time( 'mysql' );

        return $prepared;
    }

    /**
     * Dopasowuje formaty pól do zapytania.
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
            }
        }

        return $formats;
    }

    /**
     * Przygotowuje zapytanie SQL z parametrami.
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
