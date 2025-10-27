<?php
/**
 * Database installation and maintenance tasks.
 *
 * @package EstateOfficeCRM\Database
 */

namespace EstateOfficeCRM\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Handle plugin activation and schema management.
 */
class Install {
    /**
     * Constructor hooks.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
    }

    /**
     * Activation callback.
     */
    public static function activate(): void {
        self::create_roles();
        self::create_tables();
        update_option( 'eo_crm_version', EO_CRM_VERSION );
    }

    /**
     * Deactivation callback.
     */
    public static function deactivate(): void {
        // Keep data persistent for safety. Future version may add cleanup routine.
    }

    /**
     * Perform database upgrades if needed.
     */
    public function maybe_upgrade(): void {
        $version = get_option( 'eo_crm_version', '0.0.0' );

        if ( version_compare( $version, EO_CRM_VERSION, '<' ) ) {
            self::create_tables();
            update_option( 'eo_crm_version', EO_CRM_VERSION );
        }
    }

    /**
     * Register custom user role for agents.
     */
    private static function create_roles(): void {
        add_role(
            'estate_agent',
            __( 'Agent nieruchomości', 'estate-office-crm' ),
            [
                'read'           => true,
                'upload_files'   => true,
                'edit_posts'     => false,
                'delete_posts'   => false,
                'publish_posts'  => false,
                'list_users'     => false,
                'promote_users'  => false,
                'delete_users'   => false,
                'create_users'   => false,
                'edit_users'     => false,
                'assign_terms'   => false,
            ]
        );
    }

    /**
     * Create database tables using dbDelta.
     */
    private static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            self::get_agents_table_sql( $charset_collate ),
            self::get_clients_table_sql( $charset_collate ),
            self::get_contracts_table_sql( $charset_collate ),
            self::get_properties_table_sql( $charset_collate ),
            self::get_searches_table_sql( $charset_collate ),
            self::get_contract_clients_table_sql( $charset_collate ),
            self::get_contract_assets_table_sql( $charset_collate ),
        ];

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }

    /**
     * Agents table SQL.
     */
    private static function get_agents_table_sql( string $charset_collate ): string {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_agents';

        return "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(50) NULL,
            avatar_id BIGINT UNSIGNED NULL,
            biography LONGTEXT NULL,
            address_line1 VARCHAR(255) NULL,
            address_line2 VARCHAR(255) NULL,
            city VARCHAR(120) NULL,
            postal_code VARCHAR(20) NULL,
            country VARCHAR(120) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
    }

    /**
     * Clients table SQL.
     */
    private static function get_clients_table_sql( string $charset_collate ): string {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_clients';

        return "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_type VARCHAR(20) NOT NULL,
            primary_name VARCHAR(190) NOT NULL,
            contact_phone VARCHAR(50) NULL,
            contact_email VARCHAR(190) NULL,
            company_name VARCHAR(190) NULL,
            representative VARCHAR(190) NULL,
            meta LONGTEXT NULL,
            address LONGTEXT NULL,
            correspondence LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
    }

    /**
     * Contracts table SQL.
     */
    private static function get_contracts_table_sql( string $charset_collate ): string {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_contracts';

        return "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agent_id BIGINT UNSIGNED NULL,
            contract_number VARCHAR(60) NOT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            is_open_ended TINYINT(1) NOT NULL DEFAULT 0,
            commission_amount DECIMAL(14,2) NULL,
            commission_unit VARCHAR(10) NULL,
            current_stage VARCHAR(60) NULL,
            stage_history LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY contract_number (contract_number),
            KEY agent_id (agent_id)
        ) $charset_collate;";
    }

    /**
     * Properties table SQL.
     */
    private static function get_properties_table_sql( string $charset_collate ): string {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_properties';

        return "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agent_id BIGINT UNSIGNED NULL,
            contract_id BIGINT UNSIGNED NULL,
            transaction_type VARCHAR(20) NOT NULL,
            property_type VARCHAR(40) NOT NULL,
            title VARCHAR(190) NULL,
            address LONGTEXT NULL,
            legal_status VARCHAR(60) NULL,
            price DECIMAL(18,2) NULL,
            administration_fee DECIMAL(18,2) NULL,
            size_total DECIMAL(12,2) NULL,
            price_per_sqm DECIMAL(18,2) NULL,
            rooms SMALLINT NULL,
            bedrooms SMALLINT NULL,
            bathrooms SMALLINT NULL,
            floors SMALLINT NULL,
            storey SMALLINT NULL,
            build_year SMALLINT NULL,
            lot_shape VARCHAR(40) NULL,
            lot_dimensions LONGTEXT NULL,
            attributes LONGTEXT NULL,
            description LONGTEXT NULL,
            media LONGTEXT NULL,
            tags LONGTEXT NULL,
            export_web TINYINT(1) NOT NULL DEFAULT 0,
            export_portals TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY agent_id (agent_id),
            KEY contract_id (contract_id)
        ) $charset_collate;";
    }

    /**
     * Searches table SQL.
     */
    private static function get_searches_table_sql( string $charset_collate ): string {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_searches';

        return "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agent_id BIGINT UNSIGNED NULL,
            contract_id BIGINT UNSIGNED NULL,
            transaction_type VARCHAR(20) NOT NULL,
            budget_min DECIMAL(18,2) NULL,
            budget_max DECIMAL(18,2) NULL,
            size_min DECIMAL(12,2) NULL,
            size_max DECIMAL(12,2) NULL,
            rooms_min SMALLINT NULL,
            rooms_max SMALLINT NULL,
            criteria LONGTEXT NULL,
            description LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contract_id (contract_id)
        ) $charset_collate;";
    }

    /**
     * Pivot table linking contracts to clients.
     */
    private static function get_contract_clients_table_sql( string $charset_collate ): string {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_contract_clients';

        return "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY contract_client (contract_id, client_id)
        ) $charset_collate;";
    }

    /**
     * Pivot table linking contracts to assets (properties or searches).
     */
    private static function get_contract_assets_table_sql( string $charset_collate ): string {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_contract_assets';

        return "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NOT NULL,
            asset_type VARCHAR(20) NOT NULL,
            asset_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY contract_asset (contract_id, asset_type, asset_id)
        ) $charset_collate;";
    }
}
