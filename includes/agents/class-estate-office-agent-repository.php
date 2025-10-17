<?php
/**
 * Repozytorium agentów EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Agent_Repository
 */
class Estate_Office_Agent_Repository {

    /**
     * Połączenie z bazą danych WordPress.
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * Nazwa tabeli agentów.
     *
     * @var string
     */
    private string $table;

    /**
     * Mapowanie pól na formaty zapytań.
     *
     * @var array<string,string>
     */
    private array $field_formats = [
        'user_id'    => '%d',
        'phone'      => '%s',
        'email'      => '%s',
        'photo_id'   => '%d',
        'title'      => '%s',
        'bio'        => '%s',
        'meta'       => '%s',
        'created_at' => '%s',
        'updated_at' => '%s',
    ];

    /**
     * Bufor dostępnych opcji wyświetlania agentów.
     *
     * @var array<int,string>|null
     */
    private ?array $display_name_cache = null;

    /**
     * Konstruktor repozytorium.
     *
     * @param wpdb|null $wpdb Opcjonalna injekcja obiektu bazy danych.
     */
    public function __construct( ?wpdb $wpdb_instance = null ) {
        global $wpdb;

        $this->wpdb  = $wpdb_instance ?? $wpdb;
        $this->table = $this->wpdb->prefix . 'estate_office_agents';
    }

    /**
     * Zwraca wszystkich agentów.
     *
     * @return array<int,object>
     */
    public function all() : array {
        $query = "SELECT * FROM {$this->table} ORDER BY created_at DESC";

        $results = $this->wpdb->get_results( $query );

        return is_array( $results ) ? $results : [];
    }

    /**
     * Wyszukuje agenta po ID.
     *
     * @param int $agent_id ID agenta.
     *
     * @return object|null
     */
    public function find( int $agent_id ) : ?object {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $agent_id
        );

        $result = $this->wpdb->get_row( $query );

        return $result ?: null;
    }

    /**
     * Wyszukuje agenta po ID użytkownika WordPressa.
     *
     * @param int $user_id ID użytkownika.
     *
     * @return object|null
     */
    public function find_by_user_id( int $user_id ) : ?object {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d",
            $user_id
        );

        $result = $this->wpdb->get_row( $query );

        return $result ?: null;
    }

    /**
     * Sprawdza, czy agent istnieje.
     *
     * @param int $agent_id ID agenta.
     *
     * @return bool
     */
    public function exists( int $agent_id ) : bool {
        if ( $agent_id <= 0 ) {
            return false;
        }

        $query = $this->wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE id = %d",
            $agent_id
        );

        return (bool) $this->wpdb->get_var( $query );
    }

    /**
     * Zwraca mapę ID => nazwa do pól wyboru.
     *
     * @return array<int,string>
     */
    public function get_dropdown_options() : array {
        if ( null !== $this->display_name_cache ) {
            return $this->display_name_cache;
        }

        $users_table = $this->wpdb->users;
        $query       = "SELECT a.id, a.title, a.user_id, u.display_name, u.user_nicename, u.user_login FROM {$this->table} a "
            . "LEFT JOIN {$users_table} u ON a.user_id = u.ID ORDER BY COALESCE(NULLIF(a.title,''), u.display_name, u.user_nicename, u.user_login, CONCAT('Agent #', a.id)) ASC";

        $results = $this->wpdb->get_results( $query );

        $options = [];

        if ( is_array( $results ) ) {
            foreach ( $results as $row ) {
                $options[ (int) $row->id ] = $this->resolve_display_name( $row );
            }
        }

        $this->display_name_cache = $options;

        return $options;
    }

    /**
     * Zwraca etykiety dla wskazanych agentów.
     *
     * @param array<int,int> $agent_ids Lista ID agentów.
     *
     * @return array<int,string>
     */
    public function get_display_names_for_ids( array $agent_ids ) : array {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $agent_ids ) ) ) );

        if ( empty( $ids ) ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $users_table  = $this->wpdb->users;
        $query        = $this->wpdb->prepare(
            "SELECT a.id, a.title, a.user_id, u.display_name, u.user_nicename, u.user_login FROM {$this->table} a "
            . "LEFT JOIN {$users_table} u ON a.user_id = u.ID WHERE a.id IN ({$placeholders})",
            $ids
        );

        $results = $this->wpdb->get_results( $query );

        if ( ! is_array( $results ) ) {
            return [];
        }

        $labels = [];

        foreach ( $results as $row ) {
            $labels[ (int) $row->id ] = $this->resolve_display_name( $row );
        }

        return $labels;
    }

    /**
     * Tworzy rekord agenta.
     *
     * @param array<string,mixed> $data Dane rekordu.
     *
     * @return int ID utworzonego rekordu.
     */
    public function create( array $data ) : int {
        $data = $this->prepare_data( $data, true );

        $this->wpdb->insert( $this->table, $data, $this->resolve_formats( array_keys( $data ) ) );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Aktualizuje dane agenta.
     *
     * @param int                  $agent_id ID rekordu.
     * @param array<string,mixed>  $data     Dane do zapisania.
     *
     * @return bool
     */
    public function update( int $agent_id, array $data ) : bool {
        $data      = $this->prepare_data( $data, false );
        $formatted = $this->resolve_formats( array_keys( $data ) );

        $updated = $this->wpdb->update(
            $this->table,
            $data,
            [ 'id' => $agent_id ],
            $formatted,
            [ '%d' ]
        );

        return false !== $updated;
    }

    /**
     * Normalizuje dane do zapisu.
     *
     * @param array<string,mixed> $data  Dane wejściowe.
     * @param bool                $is_new Czy tworzony jest nowy rekord.
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

            if ( '%d' === $format ) {
                $filtered[ $field ] = (int) $value;
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
     * Ustala formaty pól dla zapytań bazodanowych.
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
     * Określa nazwę wyświetlaną agenta.
     *
     * @param object $row Wiersz z bazy.
     *
     * @return string
     */
    private function resolve_display_name( object $row ) : string {
        $candidates = [
            is_string( $row->title ?? '' ) ? trim( (string) $row->title ) : '',
            is_string( $row->display_name ?? '' ) ? trim( (string) $row->display_name ) : '',
            is_string( $row->user_nicename ?? '' ) ? trim( (string) $row->user_nicename ) : '',
            is_string( $row->user_login ?? '' ) ? trim( (string) $row->user_login ) : '',
        ];

        foreach ( $candidates as $candidate ) {
            if ( '' !== $candidate ) {
                return $candidate;
            }
        }

        return sprintf( __( 'Agent #%d', 'estate-office' ), (int) $row->id );
    }
}
