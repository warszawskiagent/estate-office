<?php

if (!defined('ABSPATH')) {
    exit;
}

class EstateOffice_Activator
{
    public static function activate()
    {
        self::add_roles();
        self::create_tables();
        self::create_pages();
        self::store_version();
    }

    private static function add_roles()
    {
        add_role(
            'estate_agent',
            'Agent Nieruchomości',
            array(
                'read' => true,
                'estateoffice_access_crm' => true,
            )
        );

        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('estateoffice_access_crm');
        }
    }

    private static function create_tables()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array(
            "{$wpdb->prefix}estateoffice_clients" => "CREATE TABLE {$wpdb->prefix}estateoffice_clients (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(20) NOT NULL DEFAULT 'individual',
                first_name VARCHAR(100) DEFAULT NULL,
                last_name VARCHAR(100) DEFAULT NULL,
                company_name VARCHAR(190) DEFAULT NULL,
                representative_name VARCHAR(190) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                email VARCHAR(190) DEFAULT NULL,
                website VARCHAR(190) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;",
            "{$wpdb->prefix}estateoffice_contracts" => "CREATE TABLE {$wpdb->prefix}estateoffice_contracts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contract_number VARCHAR(50) NOT NULL,
                transaction_type VARCHAR(20) NOT NULL,
                signed_date DATE NOT NULL,
                end_date DATE DEFAULT NULL,
                is_open_ended TINYINT(1) NOT NULL DEFAULT 0,
                commission_amount DECIMAL(12,2) DEFAULT NULL,
                commission_unit VARCHAR(10) DEFAULT NULL,
                status_stage VARCHAR(50) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY contract_number (contract_number),
                PRIMARY KEY  (id)
            ) $charset_collate;",
            "{$wpdb->prefix}estateoffice_properties" => "CREATE TABLE {$wpdb->prefix}estateoffice_properties (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contract_id BIGINT UNSIGNED DEFAULT NULL,
                offer_number VARCHAR(50) DEFAULT NULL,
                transaction_type VARCHAR(20) NOT NULL,
                property_type VARCHAR(20) NOT NULL,
                address_city VARCHAR(120) DEFAULT NULL,
                address_district VARCHAR(120) DEFAULT NULL,
                address_street VARCHAR(190) DEFAULT NULL,
                price DECIMAL(14,2) DEFAULT NULL,
                area DECIMAL(10,2) DEFAULT NULL,
                rooms SMALLINT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;",
            "{$wpdb->prefix}estateoffice_searches" => "CREATE TABLE {$wpdb->prefix}estateoffice_searches (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contract_id BIGINT UNSIGNED DEFAULT NULL,
                transaction_type VARCHAR(20) NOT NULL,
                property_type VARCHAR(20) NOT NULL,
                budget_min DECIMAL(14,2) DEFAULT NULL,
                budget_max DECIMAL(14,2) DEFAULT NULL,
                city VARCHAR(120) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;",
            "{$wpdb->prefix}estateoffice_contract_clients" => "CREATE TABLE {$wpdb->prefix}estateoffice_contract_clients (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contract_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;",
        );

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    private static function create_pages()
    {
        $pages = array(
            'estateoffice-crm' => array(
                'title' => 'CRM',
                'content' => '[estateoffice_crm]'
            ),
            'estateoffice-oferty' => array(
                'title' => 'Oferty',
                'content' => '[estateoffice_offers]'
            ),
        );

        foreach ($pages as $slug => $data) {
            $existing = get_page_by_path($slug);
            if (!$existing) {
                wp_insert_post(array(
                    'post_title' => $data['title'],
                    'post_name' => $slug,
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_content' => $data['content'],
                ));
            }
        }
    }

    private static function store_version()
    {
        update_option('estateoffice_version', ESTATEOFFICE_VERSION);
    }
}
