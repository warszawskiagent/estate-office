<?php
/**
 * Handles plugin activation lifecycle events.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Activator {

    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        self::create_roles();
        self::create_tables();
        self::add_missing_indexes();
        self::seed_options();
        self::ensure_pages();
        self::ensure_agent_slugs();
        self::backfill_property_watermarks();
        update_option( 'estate_office_flush_rewrite', 1 );
        if ( defined( 'ESTATE_OFFICE_VERSION' ) ) {
            update_option( 'estate_office_db_version', ESTATE_OFFICE_VERSION );
        }
        flush_rewrite_rules();
    }

    /**
     * Register custom role and capabilities for agents.
     */
    protected static function create_roles(): void {
        $capabilities = self::get_capabilities();

        if ( null === get_role( 'estate_agent' ) ) {
            add_role( 'estate_agent', __( 'Agent nieruchomości', 'estate-office' ), $capabilities );
        } else {
            self::synchronize_role_capabilities( 'estate_agent', $capabilities );
        }

        self::grant_administrator_capabilities( $capabilities );
    }

    /**
     * Ensure custom role and administrator retain plugin capabilities.
     */
    public static function ensure_role_capabilities(): void {
        $capabilities = self::get_capabilities();

        if ( null === get_role( 'estate_agent' ) ) {
            add_role( 'estate_agent', __( 'Agent nieruchomości', 'estate-office' ), $capabilities );
        } else {
            self::synchronize_role_capabilities( 'estate_agent', $capabilities );
        }

        self::grant_administrator_capabilities( $capabilities );
    }

    /**
     * Ensure database schema matches current plugin version.
     */
    public static function maybe_upgrade_schema(): void {
        if ( ! defined( 'ESTATE_OFFICE_VERSION' ) ) {
            return;
        }

        $stored_version = get_option( 'estate_office_db_version', '' );
        if ( $stored_version && version_compare( $stored_version, ESTATE_OFFICE_VERSION, '>=' ) ) {
            return;
        }

        self::create_tables();
        self::add_missing_indexes();
        self::ensure_agent_slugs();
        self::backfill_property_watermarks();
        update_option( 'estate_office_flush_rewrite', 1 );
        update_option( 'estate_office_db_version', ESTATE_OFFICE_VERSION );
    }

    /**
     * Return canonical capabilities for estate agents.
     */
    protected static function get_capabilities(): array {
        return [
            'read'                 => true,
            'edit_posts'           => false,
            'delete_posts'         => false,
            'publish_posts'        => false,
            'upload_files'         => true,
            'eo_manage_crm'        => true,
            'eo_view_crm'          => true,
            'eo_manage_agents'     => false,
            'eo_manage_clients'    => true,
            'eo_manage_contracts'  => true,
            'eo_manage_properties' => true,
            'eo_manage_searches'   => true,
        ];
    }

    /**
     * Sync capabilities for a given role name.
     */
    protected static function synchronize_role_capabilities( string $role_name, array $capabilities ): void {
        $role = get_role( $role_name );
        if ( ! $role ) {
            return;
        }

        foreach ( $capabilities as $capability => $granted ) {
            if ( $granted ) {
                $role->add_cap( $capability );
            } else {
                $role->remove_cap( $capability );
            }
        }
    }

    /**
     * Add plugin capabilities to administrator role.
     */
    protected static function grant_administrator_capabilities( array $capabilities ): void {
        $administrator = get_role( 'administrator' );
        if ( ! $administrator ) {
            return;
        }

        foreach ( $capabilities as $capability => $granted ) {
            if ( $granted ) {
                $administrator->add_cap( $capability );
            }
        }

        $administrator->add_cap( 'eo_manage_agents' );
    }

    /**
     * Create plugin database tables.
     */
    protected static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_agents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            slug VARCHAR(200) NOT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(150) DEFAULT NULL,
            photo_id BIGINT UNSIGNED DEFAULT NULL,
            description LONGTEXT NULL,
            contact_data LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY user_id (user_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_type VARCHAR(20) NOT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            company_name VARCHAR(255) DEFAULT NULL,
            representative_name VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(150) DEFAULT NULL,
            website VARCHAR(150) DEFAULT NULL,
            identification LONGTEXT NULL,
            address LONGTEXT NULL,
            correspondence_address LONGTEXT NULL,
            custom_data LONGTEXT NULL,
            agent_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY agent_id (agent_id),
            KEY last_name (last_name),
            KEY company_name (company_name),
            KEY phone (phone),
            KEY email (email)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_contracts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_number VARCHAR(100) NOT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE DEFAULT NULL,
            indefinite TINYINT(1) DEFAULT 0,
            commission_amount DECIMAL(12,2) DEFAULT NULL,
            commission_unit VARCHAR(10) DEFAULT NULL,
            stage VARCHAR(100) DEFAULT 'umowa_posrednictwa',
            stage_history LONGTEXT NULL,
            agent_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY contract_number (contract_number),
            PRIMARY KEY  (id),
            KEY agent_id (agent_id),
            KEY transaction_type (transaction_type),
            KEY stage (stage),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_contract_clients (
            contract_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (contract_id, client_id),
            KEY client_id (client_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_properties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED DEFAULT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            property_type VARCHAR(30) NOT NULL,
            address LONGTEXT NULL,
            legal LONGTEXT NULL,
            details LONGTEXT NULL,
            description LONGTEXT NULL,
            tags LONGTEXT NULL,
            export_www TINYINT(1) DEFAULT 0,
            export_portals TINYINT(1) DEFAULT 0,
            export_page_id BIGINT UNSIGNED DEFAULT NULL,
            agent_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY contract_id (contract_id),
            KEY agent_id (agent_id),
            KEY export_page_id (export_page_id),
            KEY transaction_type (transaction_type),
            KEY property_type (property_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_property_media (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id BIGINT UNSIGNED NOT NULL,
            media_type VARCHAR(20) NOT NULL,
            attachment_id BIGINT UNSIGNED DEFAULT NULL,
            watermarked_id BIGINT UNSIGNED DEFAULT NULL,
            media_url VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY watermarked_id (watermarked_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_searches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED DEFAULT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            criteria LONGTEXT NULL,
            description LONGTEXT NULL,
            agent_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY contract_id (contract_id),
            KEY agent_id (agent_id),
            KEY transaction_type (transaction_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_custom_fields (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(30) NOT NULL,
            field_key VARCHAR(191) NOT NULL,
            field_label VARCHAR(191) NOT NULL,
            field_type VARCHAR(50) NOT NULL,
            field_config LONGTEXT NULL,
            is_required TINYINT(1) DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY field_unique (entity_type, field_key)
        ) $charset_collate;";

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }

    /**
     * Ensure auxiliary indexes exist for common search queries.
     */
    protected static function add_missing_indexes(): void {
        global $wpdb;

        $properties_table = $wpdb->prefix . 'eo_properties';
        $contracts_table  = $wpdb->prefix . 'eo_contracts';
        $clients_table    = $wpdb->prefix . 'eo_clients';
        $searches_table   = $wpdb->prefix . 'eo_searches';

        $definitions = [
            $properties_table => [
                'transaction_type' => "ALTER TABLE {$properties_table} ADD KEY transaction_type (transaction_type)",
                'property_type'    => "ALTER TABLE {$properties_table} ADD KEY property_type (property_type)",
                'created_at'       => "ALTER TABLE {$properties_table} ADD KEY created_at (created_at)",
            ],
            $contracts_table  => [
                'transaction_type' => "ALTER TABLE {$contracts_table} ADD KEY transaction_type (transaction_type)",
                'stage'            => "ALTER TABLE {$contracts_table} ADD KEY stage (stage)",
                'start_date'       => "ALTER TABLE {$contracts_table} ADD KEY start_date (start_date)",
                'end_date'         => "ALTER TABLE {$contracts_table} ADD KEY end_date (end_date)",
            ],
            $clients_table    => [
                'last_name'    => "ALTER TABLE {$clients_table} ADD KEY last_name (last_name)",
                'company_name' => "ALTER TABLE {$clients_table} ADD KEY company_name (company_name)",
                'phone'        => "ALTER TABLE {$clients_table} ADD KEY phone (phone)",
                'email'        => "ALTER TABLE {$clients_table} ADD KEY email (email)",
            ],
            $searches_table   => [
                'transaction_type' => "ALTER TABLE {$searches_table} ADD KEY transaction_type (transaction_type)",
                'created_at'       => "ALTER TABLE {$searches_table} ADD KEY created_at (created_at)",
            ],
        ];

        foreach ( $definitions as $table => $indexes ) {
            foreach ( $indexes as $name => $sql ) {
                self::maybe_add_index( $table, $name, $sql );
            }
        }
    }

    /**
     * Add an index if it does not yet exist.
     */
    protected static function maybe_add_index( string $table, string $index, string $sql ): void {
        global $wpdb;

        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $index ) );
        if ( $exists ) {
            return;
        }

        $wpdb->query( $sql );
    }

    /**
     * Seed plugin default options.
     */
    protected static function seed_options(): void {
        $defaults = [
            'google_maps_api_key' => '',
            'watermark_attachment' => 0,
            'office_logo_attachment' => 0,
            'property_custom_fields' => [],
            'contract_custom_fields' => [],
            'client_custom_fields' => [],
            'crm_page_id'           => 0,
            'sale_page_id'          => 0,
            'rent_page_id'          => 0,
            'agent_slug_base'       => 'agenci',
        ];

        foreach ( $defaults as $option => $value ) {
            $option_name = 'estate_office_' . $option;
            if ( false === get_option( $option_name, false ) ) {
                add_option( $option_name, $value );
            }
        }
    }

    /**
     * Create helper pages used by the plugin.
     */
    protected static function ensure_pages(): void {
        $pages = [
            'estate_office_crm_page_id'  => [
                'post_title'   => __( 'EstateOffice CRM', 'estate-office' ),
                'post_name'    => 'estate-office-crm',
                'post_content' => '[estate_office_crm]',
                'post_status'  => 'publish',
            ],
            'estate_office_sale_page_id' => [
                'post_title'   => __( 'Oferty na sprzedaż', 'estate-office' ),
                'post_name'    => 'oferty-na-sprzedaz',
                'post_content' => '[estate_office_offers transaction="SPRZEDAŻ"]',
                'post_status'  => 'publish',
            ],
            'estate_office_rent_page_id' => [
                'post_title'   => __( 'Oferty na wynajem', 'estate-office' ),
                'post_name'    => 'oferty-na-wynajem',
                'post_content' => '[estate_office_offers transaction="WYNAJEM"]',
                'post_status'  => 'publish',
            ],
            'estate_office_notary_page_id' => [
                'post_title'   => __( 'Kalkulator notarialny', 'estate-office' ),
                'post_name'    => 'kalkulator-notarialny',
                'post_content' => '[estate_office_notary_calculator]',
                'post_status'  => 'publish',
            ],
            'estate_office_mortgage_page_id' => [
                'post_title'   => __( 'Kalkulator kredytowy', 'estate-office' ),
                'post_name'    => 'kalkulator-kredytowy',
                'post_content' => '[estate_office_mortgage_calculator]',
                'post_status'  => 'publish',
            ],
        ];

        foreach ( $pages as $option => $page_args ) {
            $page_id = (int) get_option( $option );
            if ( $page_id && get_post( $page_id ) ) {
                continue;
            }

            $existing = get_page_by_path( $page_args['post_name'], OBJECT, 'page' );
            if ( $existing ) {
                $page_id = $existing->ID;
                if ( false === strpos( $existing->post_content, $page_args['post_content'] ) ) {
                    wp_update_post(
                        [
                            'ID'           => $existing->ID,
                            'post_content' => $existing->post_content . "\n\n" . $page_args['post_content'],
                        ]
                    );
                }
            } else {
                $page_id = wp_insert_post(
                    [
                        'post_title'   => $page_args['post_title'],
                        'post_name'    => $page_args['post_name'],
                        'post_type'    => 'page',
                        'post_content' => $page_args['post_content'],
                        'post_status'  => $page_args['post_status'],
                    ]
                );
            }

            if ( $page_id && ! is_wp_error( $page_id ) ) {
                update_option( $option, $page_id );
            }
        }
    }

    /**
     * Ensure every agent record has a unique slug.
     */
    protected static function ensure_agent_slugs(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'eo_agents';
        if ( $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'slug'" ) ) {
            $rows = $wpdb->get_results( "SELECT id, slug, first_name, last_name FROM {$table}" );
            if ( empty( $rows ) ) {
                return;
            }

            foreach ( $rows as $row ) {
                $generated = estate_office_generate_agent_slug(
                    (string) ( $row->slug ?? '' ),
                    (string) ( $row->first_name ?? '' ),
                    (string) ( $row->last_name ?? '' ),
                    (int) $row->id
                );

                if ( $generated !== $row->slug ) {
                    $wpdb->update(
                        $table,
                        [ 'slug' => $generated ],
                        [ 'id' => (int) $row->id ],
                        [ '%s' ],
                        [ '%d' ]
                    );
                }
            }
        }
    }

    /**
     * Ensure property media rows reference watermarked attachments when configured.
     */
    protected static function backfill_property_watermarks(): void {
        $watermark_id = estate_office_get_watermark_attachment_id();
        if ( ! $watermark_id ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'eo_property_media';
        $rows  = $wpdb->get_results( "SELECT id, attachment_id, watermarked_id FROM {$table} WHERE media_type = 'gallery'" );
        if ( empty( $rows ) ) {
            return;
        }

        foreach ( $rows as $row ) {
            $attachment_id = (int) ( $row->attachment_id ?? 0 );
            if ( ! $attachment_id ) {
                continue;
            }

            $current = (int) ( $row->watermarked_id ?? 0 );
            if ( $current && estate_office_is_attachment_available( $current ) ) {
                continue;
            }

            $generated = estate_office_ensure_watermarked_attachment( $attachment_id, $watermark_id );
            if ( ! $generated ) {
                continue;
            }

            $wpdb->update(
                $table,
                [ 'watermarked_id' => $generated ],
                [ 'id' => (int) $row->id ],
                [ '%d' ],
                [ '%d' ]
            );
        }
    }
}
