<?php
/**
 * Serwis danych pulpitu EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Dashboard_Service
 */
class Estate_Office_Dashboard_Service {

    /**
     * @var wpdb
     */
    private wpdb $wpdb;

    private string $contracts_table;

    private string $contract_stages_table;

    private string $properties_table;

    private string $searches_table;

    private string $clients_table;

    /**
     * Konstruktor.
     *
     * @param wpdb|null $wpdb_instance Opcjonalny obiekt bazy.
     */
    public function __construct( ?wpdb $wpdb_instance = null ) {
        global $wpdb;

        $this->wpdb                 = $wpdb_instance ?? $wpdb;
        $prefix                     = $this->wpdb->prefix . 'estate_office_';
        $this->contracts_table      = $prefix . 'contracts';
        $this->contract_stages_table= $prefix . 'contract_stages';
        $this->properties_table     = $prefix . 'properties';
        $this->searches_table       = $prefix . 'searches';
        $this->clients_table        = $prefix . 'clients';
    }

    /**
     * Zwraca metryki pulpitu.
     *
     * @return array<string,int>
     */
    public function get_summary() : array {
        return [
            'contracts_total'  => $this->count_table( $this->contracts_table ),
            'contracts_active' => $this->count_table( $this->contracts_table, 'WHERE status = %s', [ 'active' ] ),
            'properties_total' => $this->count_table( $this->properties_table ),
            'searches_total'   => $this->count_table( $this->searches_table ),
            'clients_total'    => $this->count_table( $this->clients_table ),
        ];
    }

    /**
     * Ranking agentów na podstawie aktywności (etapów umów).
     *
     * @param int $limit Maksymalna liczba wyników.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_best_agents( int $limit = 5 ) : array {
        $limit = max( 1, $limit );

        $sql = $this->prepare(
            "SELECT author_id, COUNT(*) AS updates FROM {$this->contract_stages_table} "
            . 'WHERE author_id IS NOT NULL GROUP BY author_id ORDER BY updates DESC LIMIT %d',
            [ $limit ]
        );

        $rows = $this->wpdb->get_results( $sql, ARRAY_A );
        $rows = is_array( $rows ) ? $rows : [];

        if ( empty( $rows ) ) {
            $users = get_users(
                [
                    'role__in' => [ 'estate_office_agent' ],
                    'number'   => $limit,
                    'orderby'  => 'display_name',
                    'order'    => 'ASC',
                    'fields'   => [ 'ID', 'display_name' ],
                ]
            );

            return array_map(
                static function ( \WP_User $user ) : array {
                    return [
                        'user_id'        => (int) $user->ID,
                        'name'           => $user->display_name,
                        'activity_count' => 0,
                    ];
                },
                $users
            );
        }

        $user_ids = array_map( 'intval', wp_list_pluck( $rows, 'author_id' ) );
        $users    = get_users(
            [
                'include' => $user_ids,
                'fields'  => [ 'ID', 'display_name' ],
            ]
        );

        $user_map = [];
        foreach ( $users as $user ) {
            $user_map[ (int) $user->ID ] = $user->display_name;
        }

        return array_map(
            static function ( array $row ) use ( $user_map ) : array {
                $user_id = (int) $row['author_id'];

                return [
                    'user_id'        => $user_id,
                    'name'           => $user_map[ $user_id ] ?? __( 'Nieznany agent', 'estate-office' ),
                    'activity_count' => (int) $row['updates'],
                ];
            },
            $rows
        );
    }

    /**
     * Zwraca historię ostatnich etapów umów.
     *
     * @param int $limit Liczba rekordów.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_recent_stages( int $limit = 5 ) : array {
        $limit = max( 1, $limit );

        $sql = $this->prepare(
            "SELECT s.contract_id, s.stage, s.stage_date, s.author_id, s.created_at, c.contract_number, c.transaction_type "
            . "FROM {$this->contract_stages_table} s "
            . "INNER JOIN {$this->contracts_table} c ON c.id = s.contract_id "
            . 'ORDER BY s.created_at DESC, s.id DESC LIMIT %d',
            [ $limit ]
        );

        $rows = $this->wpdb->get_results( $sql, ARRAY_A );
        $rows = is_array( $rows ) ? $rows : [];

        if ( empty( $rows ) ) {
            return [];
        }

        $user_ids = array_filter( array_map( 'intval', wp_list_pluck( $rows, 'author_id' ) ) );
        $user_map = [];
        if ( ! empty( $user_ids ) ) {
            $users = get_users(
                [
                    'include' => array_unique( $user_ids ),
                    'fields'  => [ 'ID', 'display_name' ],
                ]
            );

            foreach ( $users as $user ) {
                $user_map[ (int) $user->ID ] = $user->display_name;
            }
        }

        $stage_labels       = $this->get_stage_labels();
        $transaction_labels = $this->get_transaction_labels();

        return array_map(
            static function ( array $row ) use ( $user_map, $stage_labels, $transaction_labels ) : array {
                $stage_key  = (string) $row['stage'];
                $stage_date = $row['stage_date'] ?? '';

                return [
                    'contract_id'      => (int) $row['contract_id'],
                    'contract_number'  => $row['contract_number'],
                    'transaction_type' => $transaction_labels[ $row['transaction_type'] ] ?? $row['transaction_type'],
                    'stage'            => $stage_key,
                    'stage_label'      => $stage_labels[ $stage_key ] ?? $stage_key,
                    'stage_date'       => $stage_date,
                    'author'           => isset( $row['author_id'], $user_map[ (int) $row['author_id'] ] )
                        ? $user_map[ (int) $row['author_id'] ]
                        : '',
                    'created_at'       => $row['created_at'],
                ];
            },
            $rows
        );
    }

    /**
     * Zwraca listę ostatnio utworzonych umów.
     *
     * @param int $limit Liczba wyników.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_recent_contracts( int $limit = 5 ) : array {
        $limit = max( 1, $limit );

        $sql = $this->prepare(
            "SELECT contract_number, transaction_type, status, created_at FROM {$this->contracts_table} "
            . 'ORDER BY created_at DESC, id DESC LIMIT %d',
            [ $limit ]
        );

        $rows = $this->wpdb->get_results( $sql, ARRAY_A );
        $rows = is_array( $rows ) ? $rows : [];

        if ( empty( $rows ) ) {
            return [];
        }

        $transaction_labels = $this->get_transaction_labels();
        $status_labels      = $this->get_status_labels();

        return array_map(
            static function ( array $row ) use ( $transaction_labels, $status_labels ) : array {
                $created_at = $row['created_at'] ?? '';

                return [
                    'contract_number'   => $row['contract_number'],
                    'transaction_label' => $transaction_labels[ $row['transaction_type'] ] ?? $row['transaction_type'],
                    'status_label'      => $status_labels[ $row['status'] ] ?? $row['status'],
                    'created_at'        => $created_at,
                    'created_human'     => $created_at ? mysql2date( get_option( 'date_format' ), $created_at ) : '',
                ];
            },
            $rows
        );
    }

    /**
     * Liczy rekordy w tabeli.
     *
     * @param string               $table  Nazwa tabeli.
     * @param string               $where  Klauzula WHERE.
     * @param array<int|string>    $params Parametry zapytania.
     *
     * @return int
     */
    private function count_table( string $table, string $where = '', array $params = [] ) : int {
        $sql = "SELECT COUNT(*) FROM {$table} {$where}";
        $sql = $this->prepare( $sql, $params );

        $count = $this->wpdb->get_var( $sql );

        return is_null( $count ) ? 0 : (int) $count;
    }

    /**
     * Bezpieczne przygotowanie zapytania.
     *
     * @param string            $query  Zapytanie SQL.
     * @param array<int,mixed> $params Parametry.
     *
     * @return string
     */
    private function prepare( string $query, array $params = [] ) : string {
        if ( empty( $params ) ) {
            return $query;
        }

        return $this->wpdb->prepare( $query, $params );
    }

    /**
     * Etykiety etapów.
     *
     * @return array<string,string>
     */
    private function get_stage_labels() : array {
        return [
            'umowa_posrednictwa' => __( 'Umowa Pośrednictwa', 'estate-office' ),
            'publikacja_mls'     => __( 'Publikacja w MLS', 'estate-office' ),
            'przygotowanie'      => __( 'Przygotowanie oferty', 'estate-office' ),
            'publikacja'         => __( 'Publikacja oferty', 'estate-office' ),
            'marketing'          => __( 'Marketing i prezentacje', 'estate-office' ),
            'oferta_kupna'       => __( 'Oferta kupna', 'estate-office' ),
            'negocjacje'         => __( 'Negocjacje', 'estate-office' ),
            'umowa_przedwstepna' => __( 'Umowa przedwstępna', 'estate-office' ),
            'umowa_przyrzeczona' => __( 'Umowa przyrzeczona', 'estate-office' ),
            'przekazanie_lokalu' => __( 'Przekazanie lokalu', 'estate-office' ),
            'umowa_zakonczona'   => __( 'Umowa zakończona', 'estate-office' ),
        ];
    }

    /**
     * Etykiety typów transakcji.
     *
     * @return array<string,string>
     */
    private function get_transaction_labels() : array {
        return [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];
    }

    /**
     * Etykiety statusów umów.
     *
     * @return array<string,string>
     */
    private function get_status_labels() : array {
        return [
            'draft'  => __( 'Szkic', 'estate-office' ),
            'active' => __( 'Aktywna', 'estate-office' ),
            'closed' => __( 'Zakończona', 'estate-office' ),
            'paused' => __( 'Wstrzymana', 'estate-office' ),
        ];
    }
}
