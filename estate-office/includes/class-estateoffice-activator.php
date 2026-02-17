<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOffice_Activator
{
    public static function activate(): void
    {
        self::create_tables();
        self::register_agent_role();
        self::create_default_agent_user();
        self::create_crm_pages();
        flush_rewrite_rules();
    }

    private static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'eo_';

        $sql = [];

        $sql[] = "CREATE TABLE {$prefix}clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_type VARCHAR(30) NOT NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(120) NULL,
            company_name VARCHAR(191) NULL,
            representative_name VARCHAR(191) NULL,
            phone VARCHAR(40) NULL,
            email VARCHAR(190) NULL,
            website VARCHAR(190) NULL,
            pesel VARCHAR(20) NULL,
            document_type VARCHAR(30) NULL,
            document_number VARCHAR(100) NULL,
            nip VARCHAR(30) NULL,
            krs VARCHAR(30) NULL,
            regon VARCHAR(30) NULL,
            residential_address JSON NULL,
            correspondence_address JSON NULL,
            owner_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_client_email (email),
            KEY idx_client_phone (phone)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}agreements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_number VARCHAR(120) NOT NULL,
            transaction_type VARCHAR(30) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            is_open_ended TINYINT(1) NOT NULL DEFAULT 0,
            commission_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            commission_unit VARCHAR(10) NOT NULL,
            current_stage VARCHAR(60) NOT NULL DEFAULT 'Umowa Pośrednictwa',
            owner_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_agreement_number (agreement_number),
            KEY idx_transaction_type (transaction_type)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}agreement_stages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_id BIGINT UNSIGNED NOT NULL,
            stage_name VARCHAR(60) NOT NULL,
            stage_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_agreement_stage (agreement_id, stage_date)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}agreement_clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agreement_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_agreement_client (agreement_id, client_id)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}properties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_number VARCHAR(120) NOT NULL,
            agreement_id BIGINT UNSIGNED NULL,
            transaction_type VARCHAR(30) NOT NULL,
            property_type VARCHAR(40) NOT NULL,
            legal_status VARCHAR(80) NULL,
            address_data JSON NULL,
            location_data JSON NULL,
            pricing_data JSON NULL,
            details_data JSON NULL,
            media_data JSON NULL,
            amenities_data JSON NULL,
            equipment_data JSON NULL,
            extra_spaces_data JSON NULL,
            description LONGTEXT NULL,
            tags_data JSON NULL,
            export_www TINYINT(1) NOT NULL DEFAULT 0,
            export_portals TINYINT(1) NOT NULL DEFAULT 0,
            owner_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_offer_number (offer_number),
            KEY idx_property_type (property_type),
            KEY idx_property_transaction_type (transaction_type)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}property_media (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id BIGINT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NULL,
            media_type VARCHAR(40) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            external_url TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_property_media (property_id, media_type)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}searches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            search_number VARCHAR(120) NOT NULL,
            agreement_id BIGINT UNSIGNED NULL,
            transaction_type VARCHAR(30) NOT NULL,
            property_type VARCHAR(40) NOT NULL,
            budget_from DECIMAL(12,2) NULL,
            budget_to DECIMAL(12,2) NULL,
            area_from DECIMAL(10,2) NULL,
            area_to DECIMAL(10,2) NULL,
            rooms_from SMALLINT UNSIGNED NULL,
            rooms_to SMALLINT UNSIGNED NULL,
            criteria_data JSON NULL,
            description LONGTEXT NULL,
            owner_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_search_number (search_number),
            KEY idx_search_transaction (transaction_type)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(120) NOT NULL,
            setting_value LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_setting_key (setting_key)
        ) {$charsetCollate};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        add_option('estateoffice_db_version', ESTATEOFFICE_VERSION);
    }

    private static function register_agent_role(): void
    {
        add_role(
            'estateoffice_agent',
            __('Agent Nieruchomości', 'estateoffice'),
            [
                'read' => true,
                'upload_files' => true,
                'estateoffice_access_crm' => true,
                'estateoffice_manage_clients' => true,
                'estateoffice_manage_agreements' => true,
                'estateoffice_manage_properties' => true,
                'estateoffice_manage_searches' => true,
                'estateoffice_delete_records' => false,
            ]
        );

        $adminRole = get_role('administrator');
        if ($adminRole instanceof WP_Role) {
            $adminRole->add_cap('estateoffice_access_crm');
            $adminRole->add_cap('estateoffice_manage_clients');
            $adminRole->add_cap('estateoffice_manage_agreements');
            $adminRole->add_cap('estateoffice_manage_properties');
            $adminRole->add_cap('estateoffice_manage_searches');
            $adminRole->add_cap('estateoffice_delete_records');
        }
    }

    private static function create_default_agent_user(): void
    {
        $optionKey = 'estateoffice_default_agent_user_id';
        if ((int) get_option($optionKey) > 0) {
            return;
        }

        $email = 'agent@estateoffice.local';
        $userId = email_exists($email);
        if (! $userId) {
            $password = wp_generate_password(20, true, true);
            $userId = wp_insert_user([
                'user_login' => 'estateoffice-agent',
                'user_email' => $email,
                'user_pass' => $password,
                'first_name' => 'Domyślny',
                'last_name' => 'Agent',
                'role' => 'estateoffice_agent',
            ]);

            if (! is_wp_error($userId)) {
                update_user_meta($userId, 'estateoffice_must_change_password', '1');
                wp_mail(
                    get_option('admin_email'),
                    __('EstateOffice CRM - konto domyślne agenta', 'estateoffice'),
                    sprintf(
                        "Utworzono konto domyślnego agenta.\nLogin: %s\nE-mail: %s\nHasło tymczasowe: %s\nPo pierwszym logowaniu wymagana jest zmiana hasła.",
                        'estateoffice-agent',
                        $email,
                        $password
                    )
                );
            }
        }

        if (! is_wp_error($userId) && $userId) {
            update_option($optionKey, (int) $userId);
        }
    }

    private static function create_crm_pages(): void
    {
        $pages = [
            'estateoffice-crm-dashboard' => [
                'title' => 'CRM - Pulpit',
                'shortcode' => '[estateoffice_crm view="dashboard"]',
            ],
            'estateoffice-crm-nieruchomosci' => [
                'title' => 'CRM - Nieruchomości',
                'shortcode' => '[estateoffice_crm view="properties"]',
            ],
            'estateoffice-crm-poszukiwania' => [
                'title' => 'CRM - Poszukiwania',
                'shortcode' => '[estateoffice_crm view="searches"]',
            ],
            'estateoffice-crm-umowy' => [
                'title' => 'CRM - Umowy',
                'shortcode' => '[estateoffice_crm view="agreements"]',
            ],
            'estateoffice-crm-klienci' => [
                'title' => 'CRM - Klienci',
                'shortcode' => '[estateoffice_crm view="clients"]',
            ],
            'oferty-sprzedaz' => [
                'title' => 'Oferty na sprzedaż',
                'shortcode' => '[estateoffice_offers transaction="SPRZEDAŻ"]',
            ],
            'oferty-wynajem' => [
                'title' => 'Oferty na wynajem',
                'shortcode' => '[estateoffice_offers transaction="WYNAJEM"]',
            ],
        ];

        $createdPages = [];
        foreach ($pages as $slug => $config) {
            $existing = get_page_by_path($slug);
            if ($existing instanceof WP_Post) {
                $createdPages[$slug] = (int) $existing->ID;
                continue;
            }

            $pageId = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $config['title'],
                'post_name' => $slug,
                'post_content' => $config['shortcode'],
                'comment_status' => 'closed',
            ], true);

            if (! is_wp_error($pageId)) {
                $createdPages[$slug] = (int) $pageId;
            }
        }

        update_option('estateoffice_crm_pages', $createdPages);
    }
}
