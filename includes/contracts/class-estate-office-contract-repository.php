<?php
/**
 * Repozytorium umów EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Contract_Repository
 */
class Estate_Office_Contract_Repository {

    private const STAGE_DEFAULT = 'umowa_posrednictwa';

    /**
     * @var wpdb
     */
    private wpdb $wpdb;

    private string $contracts_table;

    private string $contract_clients_table;

    private string $contract_properties_table;

    private string $contract_stages_table;

    private string $clients_table;

    private string $properties_table;

    private string $searches_table;

    /**
     * @var array<string,string>
     */
    private array $field_formats = [
        'contract_number'   => '%s',
        'transaction_type'  => '%s',
        'start_date'        => '%s',
        'end_date'          => '%s',
        'is_open_ended'     => '%d',
        'commission_amount' => '%f',
        'commission_unit'   => '%s',
        'status'            => '%s',
        'current_stage'     => '%s',
        'current_stage_date'=> '%s',
        'stage_notes'       => '%s',
        'updated_at'        => '%s',
    ];

    /**
     * Konstruktor.
     *
     * @param wpdb|null $wpdb_instance Opcjonalny obiekt bazy danych.
     */
    public function __construct( ?wpdb $wpdb_instance = null ) {
        global $wpdb;

        $this->wpdb                     = $wpdb_instance ?? $wpdb;
        $prefix                         = $this->wpdb->prefix . 'estate_office_';
        $this->contracts_table          = $prefix . 'contracts';
        $this->contract_clients_table   = $prefix . 'contract_clients';
        $this->contract_properties_table= $prefix . 'contract_properties';
        $this->contract_stages_table    = $prefix . 'contract_stages';
        $this->clients_table            = $prefix . 'clients';
        $this->properties_table         = $prefix . 'properties';
        $this->searches_table           = $prefix . 'searches';
    }

    /**
     * Lista umów z paginacją.
     *
     * @param array<string,mixed> $args Argumenty.
     *
     * @return array<string,mixed>
     */
    public function paginate( array $args ) : array {
        $defaults = [
            'paged'           => 1,
            'per_page'        => 20,
            'search'          => '',
            'transaction_type'=> '',
            'status'          => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $paged    = max( 1, (int) $args['paged'] );
        $per_page = max( 1, (int) $args['per_page'] );

        $where_clauses = [];
        $params        = [];

        if ( ! empty( $args['search'] ) ) {
            $search_like     = '%' . $this->wpdb->esc_like( (string) $args['search'] ) . '%';
            $where_clauses[] = '(contract_number LIKE %s OR transaction_type LIKE %s OR status LIKE %s)';
            $params[]        = $search_like;
            $params[]        = $search_like;
            $params[]        = $search_like;
        }

        if ( ! empty( $args['transaction_type'] ) ) {
            $where_clauses[] = 'transaction_type = %s';
            $params[]        = (string) $args['transaction_type'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where_clauses[] = 'status = %s';
            $params[]        = (string) $args['status'];
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $select_sql = "SELECT * FROM {$this->contracts_table} {$where_sql} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d";
        $count_sql  = "SELECT COUNT(*) FROM {$this->contracts_table} {$where_sql}";

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
     * Znajduje umowę po ID.
     *
     * @param int $contract_id ID umowy.
     *
     * @return array<string,mixed>|null
     */
    public function find( int $contract_id ) : ?array {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->contracts_table} WHERE id = %d",
            $contract_id
        );

        $result = $this->wpdb->get_row( $query, ARRAY_A );

        return $result ?: null;
    }

    /**
     * Sprawdza, czy istnieje umowa o danym numerze.
     *
     * @param string $contract_number Numer umowy.
     *
     * @return bool
     */
    public function exists_by_number( string $contract_number ) : bool {
        $query = $this->wpdb->prepare(
            "SELECT id FROM {$this->contracts_table} WHERE contract_number = %s",
            $contract_number
        );

        $result = $this->wpdb->get_var( $query );

        return ! empty( $result );
    }

    /**
     * Pobiera umowę po numerze.
     *
     * @param string $contract_number Numer.
     *
     * @return array<string,mixed>|null
     */
    public function find_by_number( string $contract_number ) : ?array {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->contracts_table} WHERE contract_number = %s",
            $contract_number
        );

        $result = $this->wpdb->get_row( $query, ARRAY_A );

        return $result ?: null;
    }

    /**
     * Generuje unikalny numer umowy.
     *
     * @return string
     */
    public function generate_contract_number() : string {
        $prefix = 'CON-' . gmdate( 'ymd' );
        $index  = 1;

        do {
            $candidate = sprintf( '%s-%03d', $prefix, $index );
            $index++;
        } while ( $this->exists_by_number( $candidate ) );

        return $candidate;
    }

    /**
     * Tworzy umowę.
     *
     * @param array<string,mixed> $data Dane wejściowe.
     *
     * @return int
     */
    public function create( array $data ) : int {
        $data = $this->prepare_data( $data, true );

        if ( empty( $data['current_stage'] ) ) {
            $data['current_stage'] = self::STAGE_DEFAULT;
        }

        if ( empty( $data['current_stage_date'] ) ) {
            $data['current_stage_date'] = $data['start_date'] ?? current_time( 'mysql' );
        }

        $this->wpdb->insert( $this->contracts_table, $data, $this->resolve_formats( array_keys( $data ) ) );

        $contract_id = (int) $this->wpdb->insert_id;

        if ( $contract_id > 0 ) {
            $this->add_stage(
                $contract_id,
                $data['current_stage'],
                $data['current_stage_date'],
                get_current_user_id(),
                __( 'Automatycznie utworzony etap początkowy.', 'estate-office' )
            );
        }

        return $contract_id;
    }

    /**
     * Aktualizuje umowę.
     *
     * @param int                 $contract_id ID umowy.
     * @param array<string,mixed> $data        Dane.
     *
     * @return bool
     */
    public function update( int $contract_id, array $data ) : bool {
        $data = $this->prepare_data( $data, false );

        if ( empty( $data ) ) {
            return false;
        }

        $updated = $this->wpdb->update(
            $this->contracts_table,
            $data,
            [ 'id' => $contract_id ],
            $this->resolve_formats( array_keys( $data ) ),
            [ '%d' ]
        );

        return false !== $updated;
    }

    /**
     * Usuwa umowę oraz powiązania.
     *
     * @param int $contract_id ID umowy.
     *
     * @return bool
     */
    public function delete( int $contract_id ) : bool {
        $this->wpdb->delete( $this->contract_clients_table, [ 'contract_id' => $contract_id ], [ '%d' ] );
        $this->wpdb->delete( $this->contract_properties_table, [ 'contract_id' => $contract_id ], [ '%d' ] );
        $this->wpdb->delete( $this->contract_stages_table, [ 'contract_id' => $contract_id ], [ '%d' ] );

        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->properties_table} SET contract_id = NULL WHERE contract_id = %d",
                $contract_id
            )
        );

        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->searches_table} SET contract_id = NULL WHERE contract_id = %d",
                $contract_id
            )
        );

        $deleted = $this->wpdb->delete( $this->contracts_table, [ 'id' => $contract_id ], [ '%d' ] );

        return false !== $deleted;
    }

    /**
     * Dodaje klienta do umowy.
     *
     * @param int    $contract_id ID umowy.
     * @param int    $client_id   ID klienta.
     * @param string $role        Rola klienta.
     *
     * @return bool
     */
    public function attach_client( int $contract_id, int $client_id, string $role = '' ) : bool {
        $role     = sanitize_key( $role );
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->contract_clients_table} WHERE contract_id = %d AND client_id = %d AND role = %s",
                $contract_id,
                $client_id,
                $role
            )
        );

        if ( $existing ) {
            return true;
        }

        $data = [
            'contract_id' => $contract_id,
            'client_id'   => $client_id,
            'role'        => $role,
        ];

        $result = $this->wpdb->insert( $this->contract_clients_table, $data, [ '%d', '%d', '%s' ] );

        return false !== $result;
    }

    /**
     * Usuwa klienta z umowy.
     *
     * @param int    $contract_id ID umowy.
     * @param int    $client_id   ID klienta.
     * @param string $role        Opcjonalna rola.
     *
     * @return void
     */
    public function detach_client( int $contract_id, int $client_id, string $role = '' ) : void {
        $where  = [
            'contract_id' => $contract_id,
            'client_id'   => $client_id,
        ];
        $format = [ '%d', '%d' ];

        if ( ! empty( $role ) ) {
            $where['role'] = sanitize_key( $role );
            $format[]      = '%s';
        }

        $this->wpdb->delete( $this->contract_clients_table, $where, $format );
    }

    /**
     * Pobiera klientów powiązanych z umową.
     *
     * @param int $contract_id ID umowy.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_clients( int $contract_id ) : array {
        $sql = $this->wpdb->prepare(
            "SELECT c.*, cc.role FROM {$this->clients_table} c "
            . "INNER JOIN {$this->contract_clients_table} cc ON c.id = cc.client_id "
            . 'WHERE cc.contract_id = %d ORDER BY c.updated_at DESC, c.id DESC',
            $contract_id
        );

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        return is_array( $results ) ? $results : [];
    }

    /**
     * Dodaje etap umowy.
     *
     * @param int         $contract_id ID umowy.
     * @param string      $stage       Etap.
     * @param string      $date        Data.
     * @param int|null    $author_id   Autor.
     * @param string|null $notes       Notatki.
     *
     * @return int
     */
    public function add_stage( int $contract_id, string $stage, string $date, ?int $author_id = null, ?string $notes = null ) : int {
        $data = [
            'contract_id' => $contract_id,
            'stage'       => sanitize_key( $stage ),
            'stage_date'  => $date,
            'author_id'   => $author_id,
            'notes'       => is_null( $notes ) ? null : wp_kses_post( $notes ),
        ];

        $formats = [ '%d', '%s', '%s', '%d', '%s' ];

        $this->wpdb->insert( $this->contract_stages_table, $data, $formats );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Zwraca historię etapów.
     *
     * @param int $contract_id ID umowy.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_stages( int $contract_id ) : array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->contract_stages_table} WHERE contract_id = %d ORDER BY stage_date DESC, id DESC",
            $contract_id
        );

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        return is_array( $results ) ? $results : [];
    }

    /**
     * Zapisuje powiązanie nieruchomości.
     *
     * @param int    $contract_id ID umowy.
     * @param int    $property_id ID nieruchomości.
     * @param string $relation    Typ relacji.
     *
     * @return bool
     */
    public function attach_property( int $contract_id, int $property_id, string $relation = '' ) : bool {
        $data = [
            'contract_id'  => $contract_id,
            'property_id'  => $property_id,
            'relation_type'=> sanitize_key( $relation ),
        ];

        $result = $this->wpdb->insert( $this->contract_properties_table, $data, [ '%d', '%d', '%s' ] );

        return false !== $result;
    }

    /**
     * Usuwa powiązanie nieruchomości z umową.
     *
     * @param int $contract_id ID umowy.
     * @param int $property_id ID nieruchomości.
     *
     * @return void
     */
    public function detach_property( int $contract_id, int $property_id ) : void {
        $this->wpdb->delete(
            $this->contract_properties_table,
            [
                'contract_id' => $contract_id,
                'property_id' => $property_id,
            ],
            [ '%d', '%d' ]
        );
    }

    /**
     * Zwraca nieruchomości przypisane do umowy.
     *
     * @param int $contract_id ID umowy.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_properties( int $contract_id ) : array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->properties_table} WHERE contract_id = %d ORDER BY COALESCE(updated_at, created_at) DESC, id DESC",
            $contract_id
        );

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        return is_array( $results ) ? $results : [];
    }

    /**
     * Zwraca poszukiwania powiązane z umową.
     *
     * @param int $contract_id ID umowy.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_searches( int $contract_id ) : array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->searches_table} WHERE contract_id = %d ORDER BY COALESCE(updated_at, created_at) DESC, id DESC",
            $contract_id
        );

        $results = $this->wpdb->get_results( $sql, ARRAY_A );

        return is_array( $results ) ? $results : [];
    }

    /**
     * Aktualizuje etap bieżący umowy.
     *
     * @param int    $contract_id ID umowy.
     * @param string $stage       Etap.
     * @param string $date        Data.
     * @param string $notes       Notatki.
     *
     * @return bool
     */
    public function update_current_stage( int $contract_id, string $stage, string $date, string $notes = '' ) : bool {
        $data = [
            'current_stage'      => sanitize_key( $stage ),
            'current_stage_date' => $date,
            'stage_notes'        => $notes,
        ];

        return $this->update( $contract_id, $data );
    }

    /**
     * Przygotowuje dane do zapisu.
     *
     * @param array<string,mixed> $data  Dane.
     * @param bool                $is_new Czy nowy rekord.
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
                case 'is_open_ended':
                    $prepared[ $field ] = ! empty( $value ) ? 1 : 0;
                    break;
                case 'commission_amount':
                    if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
                        continue 2;
                    }

                    $prepared[ $field ] = (float) $value;
                    break;
                case 'end_date':
                    if ( '' === $value || null === $value ) {
                        continue 2;
                    }

                    $prepared[ $field ] = sanitize_text_field( (string) $value );
                    break;
                case 'stage_notes':
                    $prepared[ $field ] = wp_kses_post( (string) $value );
                    break;
                default:
                    $prepared[ $field ] = sanitize_text_field( (string) $value );
                    break;
            }
        }

        if ( ! $is_new ) {
            $prepared['updated_at'] = current_time( 'mysql' );
        }

        return $prepared;
    }

    /**
     * Dopasowuje formaty do pól.
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
