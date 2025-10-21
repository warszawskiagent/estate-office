<?php
/**
 * Handles plugin activation.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EstateOffice_Activator
 */
class EstateOffice_Activator {

    /**
     * Execute activation tasks.
     *
     * @return void
     */
    public static function activate() {
        self::create_roles();
        self::create_tables();
        self::add_default_options();
    }

    /**
     * Create custom roles and capabilities.
     *
     * @return void
     */
    protected static function create_roles() {
        add_role(
            'estate_agent',
            __( 'Agent nieruchomości', 'estate-office' ),
            [
                'read'                   => true,
                'edit_posts'             => false,
                'delete_posts'           => false,
                'upload_files'           => true,
                'manage_estate_office'   => true,
                'edit_estate_office'     => true,
                'view_estate_office'     => true,
                'delete_estate_office'   => false,
            ]
        );

        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            $caps = [
                'manage_estate_office',
                'edit_estate_office',
                'view_estate_office',
                'delete_estate_office',
            ];

            foreach ( $caps as $cap ) {
                $administrator->add_cap( $cap );
            }
        }
    }

    /**
     * Create database tables.
     *
     * @return void
     */
    protected static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(20) NOT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            company_name VARCHAR(191) DEFAULT NULL,
            representative VARCHAR(191) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(191) DEFAULT NULL,
            website VARCHAR(191) DEFAULT NULL,
            identification JSON DEFAULT NULL,
            address JSON DEFAULT NULL,
            correspondence JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY email (email)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_agreements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_number VARCHAR(100) NOT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE DEFAULT NULL,
            open_ended TINYINT(1) DEFAULT 0,
            commission_amount DECIMAL(18,2) DEFAULT 0,
            commission_unit VARCHAR(10) DEFAULT '%',
            stage VARCHAR(50) DEFAULT 'umowa_posrednictwa',
            stage_history JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY contract_number (contract_number),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_agreement_clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY agreement_id (agreement_id),
            KEY client_id (client_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_properties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_id BIGINT UNSIGNED NOT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            property_type VARCHAR(20) NOT NULL,
            address JSON DEFAULT NULL,
            legal_status VARCHAR(50) DEFAULT NULL,
            registry_number VARCHAR(191) DEFAULT NULL,
            price DECIMAL(18,2) DEFAULT 0,
            rent DECIMAL(18,2) DEFAULT NULL,
            area DECIMAL(10,2) DEFAULT NULL,
            price_per_sqm DECIMAL(18,2) DEFAULT NULL,
            details JSON DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            amenities JSON DEFAULT NULL,
            surfaces JSON DEFAULT NULL,
            media JSON DEFAULT NULL,
            tags JSON DEFAULT NULL,
            export_www TINYINT(1) DEFAULT 0,
            export_portals TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY agreement_id (agreement_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_property_media (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id BIGINT UNSIGNED NOT NULL,
            media_type VARCHAR(20) NOT NULL,
            attachment_id BIGINT UNSIGNED DEFAULT NULL,
            url VARCHAR(191) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY property_id (property_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_searches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_id BIGINT UNSIGNED NOT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            criteria JSON DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY agreement_id (agreement_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            option_key VARCHAR(100) NOT NULL,
            option_value LONGTEXT DEFAULT NULL,
            autoload VARCHAR(20) NOT NULL DEFAULT 'yes',
            PRIMARY KEY (id),
            UNIQUE KEY option_key (option_key)
        ) $charset_collate;";

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }

    /**
     * Add default options for settings.
     *
     * @return void
     */
    protected static function add_default_options() {
        $defaults = [
            'google_maps_api_key' => '',
            'watermark_attachment_id' => 0,
            'office_logo_attachment_id' => 0,
            'property_dynamic_fields' => [],
            'agreement_dynamic_fields' => [],
            'client_dynamic_fields' => [],
            'roadmap' => [
                [
                    'version' => '0.0.1',
                    'description' => __( 'Pierwsza wersja beta CRM EstateOffice.', 'estate-office' ),
                ],
            ],
        ];

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( 'estate_office_' . $key ) ) {
                add_option( 'estate_office_' . $key, $value );
            }
        }
    }
}
