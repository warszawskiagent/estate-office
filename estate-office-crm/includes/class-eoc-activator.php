<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Activator {
    public static function activate(): void {
        self::create_tables();
        self::register_roles();
    }

    public static function deactivate(): void {
        self::remove_roles();
    }

    private static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $clients_table = $wpdb->prefix . 'eoc_clients';
        $contracts_table = $wpdb->prefix . 'eoc_contracts';
        $properties_table = $wpdb->prefix . 'eoc_properties';
        $searches_table = $wpdb->prefix . 'eoc_searches';
        $contract_clients_table = $wpdb->prefix . 'eoc_contract_clients';

        $clients_sql = "CREATE TABLE {$clients_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_type VARCHAR(20) NOT NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            company_name VARCHAR(190) NULL,
            representative_name VARCHAR(190) NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(190) NULL,
            website VARCHAR(190) NULL,
            id_number VARCHAR(190) NULL,
            id_type VARCHAR(50) NULL,
            tax_id VARCHAR(50) NULL,
            krs VARCHAR(50) NULL,
            regon VARCHAR(50) NULL,
            address_line_1 VARCHAR(190) NULL,
            address_line_2 VARCHAR(190) NULL,
            postal_code VARCHAR(20) NULL,
            city VARCHAR(190) NULL,
            country VARCHAR(190) NULL,
            mailing_address_line_1 VARCHAR(190) NULL,
            mailing_address_line_2 VARCHAR(190) NULL,
            mailing_postal_code VARCHAR(20) NULL,
            mailing_city VARCHAR(190) NULL,
            mailing_country VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY client_type (client_type),
            KEY email (email)
        ) {$charset_collate};";

        $contracts_sql = "CREATE TABLE {$contracts_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_number VARCHAR(100) NOT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            is_open_ended TINYINT(1) NOT NULL DEFAULT 0,
            commission_amount DECIMAL(12,2) NULL,
            commission_unit VARCHAR(10) NULL,
            status_stage VARCHAR(100) NULL,
            agent_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_number (contract_number),
            KEY transaction_type (transaction_type)
        ) {$charset_collate};";

        $properties_sql = "CREATE TABLE {$properties_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NULL,
            transaction_type VARCHAR(20) NOT NULL,
            property_type VARCHAR(30) NOT NULL,
            city VARCHAR(190) NULL,
            district VARCHAR(190) NULL,
            street VARCHAR(190) NULL,
            building_number VARCHAR(50) NULL,
            unit_number VARCHAR(50) NULL,
            postal_code VARCHAR(20) NULL,
            price DECIMAL(12,2) NULL,
            area DECIMAL(10,2) NULL,
            rooms SMALLINT NULL,
            manager_user_id BIGINT UNSIGNED NULL,
            export_to_website TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY transaction_type (transaction_type),
            KEY property_type (property_type)
        ) {$charset_collate};";

        $searches_sql = "CREATE TABLE {$searches_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NULL,
            transaction_type VARCHAR(20) NOT NULL,
            property_type VARCHAR(30) NULL,
            budget_min DECIMAL(12,2) NULL,
            budget_max DECIMAL(12,2) NULL,
            area_min DECIMAL(10,2) NULL,
            area_max DECIMAL(10,2) NULL,
            rooms_min SMALLINT NULL,
            rooms_max SMALLINT NULL,
            city VARCHAR(190) NULL,
            district VARCHAR(190) NULL,
            description LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY transaction_type (transaction_type)
        ) {$charset_collate};";

        $contract_clients_sql = "CREATE TABLE {$contract_clients_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_client (contract_id, client_id)
        ) {$charset_collate};";

        dbDelta($clients_sql);
        dbDelta($contracts_sql);
        dbDelta($properties_sql);
        dbDelta($searches_sql);
        dbDelta($contract_clients_sql);
    }

    private static function register_roles(): void {
        $capabilities = array(
            'read' => true,
            'eoc_access' => true,
        );

        add_role('estate_agent', __('Agent', 'estate-office-crm'), $capabilities);

        $admin_role = get_role('administrator');
        if ($admin_role instanceof WP_Role) {
            $admin_role->add_cap('eoc_access');
        }
    }

    private static function remove_roles(): void {
        remove_role('estate_agent');

        $admin_role = get_role('administrator');
        if ($admin_role instanceof WP_Role) {
            $admin_role->remove_cap('eoc_access');
        }
    }
}
