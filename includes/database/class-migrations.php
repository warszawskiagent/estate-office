<?php
/**
 * Database migrations.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Runs plugin database migrations.
 */
class Migrations {
    private const OPTION_KEY = 'estate_office_db_version';
    private const DB_VERSION = '0.1.0';

    /**
     * Maybe run migrations.
     *
     * @param bool $force Force execution.
     *
     * @return void
     */
    public function maybe_run( bool $force = false ): void {
        $installed_version = get_option( self::OPTION_KEY );

        if ( $force || version_compare( (string) $installed_version, self::DB_VERSION, '<' ) ) {
            $this->migrate();
            update_option( self::OPTION_KEY, self::DB_VERSION );
        }
    }

    /**
     * Create or update tables.
     *
     * @return void
     */
    private function migrate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE " . DB::table( 'clients' ) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(190) DEFAULT '' NOT NULL,
            phone VARCHAR(50) DEFAULT '' NOT NULL,
            notes LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY email (email)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE " . DB::table( 'properties' ) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            title VARCHAR(190) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'draft',
            price DECIMAL(15,2) DEFAULT 0,
            city VARCHAR(120) DEFAULT '' NOT NULL,
            street VARCHAR(190) DEFAULT '' NOT NULL,
            agent_id BIGINT UNSIGNED NULL,
            export_web TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY agent_id (agent_id),
            KEY export_web (export_web)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE " . DB::table( 'contracts' ) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            client_id BIGINT UNSIGNED NOT NULL,
            property_id BIGINT UNSIGNED NULL,
            type VARCHAR(50) NOT NULL,
            valid_from DATE NULL,
            valid_to DATE NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'draft',
            PRIMARY KEY  (id),
            KEY client_id (client_id),
            KEY property_id (property_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE " . DB::table( 'searches' ) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            client_id BIGINT UNSIGNED NOT NULL,
            budget_min DECIMAL(15,2) DEFAULT 0,
            budget_max DECIMAL(15,2) DEFAULT 0,
            location VARCHAR(190) DEFAULT '' NOT NULL,
            property_type VARCHAR(80) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id),
            KEY client_id (client_id)
        ) $charset_collate;";

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }
}
