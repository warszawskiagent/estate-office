<?php
/**
 * Instalator i aktualizator bazy danych dla EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Installer
 */
class Estate_Office_Installer {

    private const DB_VERSION_OPTION = 'estate_office_db_version';
    private const DB_VERSION        = '0.2.0';

    /**
     * Definicje schematu.
     *
     * @var Estate_Office_Database_Schema
     */
    private Estate_Office_Database_Schema $schema;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Database_Schema|null $schema Opcjonalna injekcja schematu.
     */
    public function __construct( ?Estate_Office_Database_Schema $schema = null ) {
        $this->schema = $schema ?? new Estate_Office_Database_Schema();
    }

    /**
     * Uruchamia pełną instalację schematu.
     *
     * @return void
     */
    public function install() : void {
        $this->run_schema_updates();
    }

    /**
     * Sprawdza, czy konieczna jest aktualizacja schematu.
     *
     * @return void
     */
    public function maybe_upgrade() : void {
        $installed_version = get_option( self::DB_VERSION_OPTION );

        if ( self::DB_VERSION === $installed_version ) {
            return;
        }

        $this->run_schema_updates();
    }

    /**
     * Wykonuje sekwencję zapytań aktualizujących strukturę tabel.
     *
     * @return void
     */
    private function run_schema_updates() : void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $this->schema->get_schema() as $sql ) {
            dbDelta( $sql );
        }

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

        Estate_Office_Plugin::log_debug(
            'Zaktualizowano schemat bazy danych EstateOffice.',
            [
                'db_version' => self::DB_VERSION,
                'tables'     => $this->list_tables(),
            ]
        );
    }

    /**
     * Zwraca listę tabel wtyczki dostępnych w bazie.
     *
     * @global wpdb $wpdb
     *
     * @return string[]
     */
    private function list_tables() : array {
        global $wpdb;

        $like   = $wpdb->esc_like( $wpdb->prefix . 'estate_office_' ) . '%';
        $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

        if ( empty( $tables ) ) {
            return [];
        }

        return array_map( static fn( $table ) => (string) $table, $tables );
    }
}
