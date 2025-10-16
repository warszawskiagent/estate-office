<?php
/**
 * Definicja schematu bazy danych dla EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klasa przechowująca definicje tabel potrzebnych wtyczce.
 */
class Estate_Office_Database_Schema {

    /**
     * Zwraca listę poleceń SQL potrzebnych do utworzenia/aktualizacji tabel.
     *
     * @global wpdb $wpdb Bieżące połączenie z bazą danych WordPressa.
     *
     * @return string[]
     */
    public function get_schema() : array {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix . 'estate_office_';

        $schema = [];

        $schema[] = "CREATE TABLE {$prefix}agents (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            phone VARCHAR(50) NOT NULL DEFAULT '',
            email VARCHAR(100) NOT NULL DEFAULT '',
            photo_id BIGINT(20) UNSIGNED DEFAULT NULL,
            title VARCHAR(150) NOT NULL DEFAULT '',
            bio LONGTEXT NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY email (email),
            KEY phone (phone)
        ) {$charset_collate};";

        $schema[] = "CREATE TABLE {$prefix}clients (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            client_type VARCHAR(20) NOT NULL,
            first_name VARCHAR(100) NOT NULL DEFAULT '',
            last_name VARCHAR(100) NOT NULL DEFAULT '',
            company_name VARCHAR(150) NOT NULL DEFAULT '',
            representative_name VARCHAR(150) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            email VARCHAR(100) NOT NULL DEFAULT '',
            website VARCHAR(150) NOT NULL DEFAULT '',
            document_type VARCHAR(30) NOT NULL DEFAULT '',
            document_number VARCHAR(60) NOT NULL DEFAULT '',
            pesel VARCHAR(20) NOT NULL DEFAULT '',
            nip VARCHAR(20) NOT NULL DEFAULT '',
            krs VARCHAR(20) NOT NULL DEFAULT '',
            regon VARCHAR(20) NOT NULL DEFAULT '',
            address_street VARCHAR(150) NOT NULL DEFAULT '',
            address_number VARCHAR(20) NOT NULL DEFAULT '',
            address_unit VARCHAR(20) NOT NULL DEFAULT '',
            address_postal_code VARCHAR(20) NOT NULL DEFAULT '',
            address_city VARCHAR(100) NOT NULL DEFAULT '',
            address_district VARCHAR(100) NOT NULL DEFAULT '',
            address_country VARCHAR(100) NOT NULL DEFAULT '',
            correspondence_same TINYINT(1) NOT NULL DEFAULT 1,
            correspondence_street VARCHAR(150) NOT NULL DEFAULT '',
            correspondence_number VARCHAR(20) NOT NULL DEFAULT '',
            correspondence_unit VARCHAR(20) NOT NULL DEFAULT '',
            correspondence_postal_code VARCHAR(20) NOT NULL DEFAULT '',
            correspondence_city VARCHAR(100) NOT NULL DEFAULT '',
            correspondence_country VARCHAR(100) NOT NULL DEFAULT '',
            notes LONGTEXT NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY phone (phone)
        ) {$charset_collate};";

        $schema[] = "CREATE TABLE {$prefix}contracts (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_number VARCHAR(60) NOT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            is_open_ended TINYINT(1) NOT NULL DEFAULT 0,
            commission_amount DECIMAL(15,2) NULL,
            commission_unit VARCHAR(10) NOT NULL DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            current_stage VARCHAR(60) NOT NULL DEFAULT 'umowa_posrednictwa',
            current_stage_date DATE NULL,
            stage_notes LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_number (contract_number),
            KEY transaction_type (transaction_type)
        ) {$charset_collate};";

        $schema[] = "CREATE TABLE {$prefix}contract_stages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT(20) UNSIGNED NOT NULL,
            stage VARCHAR(60) NOT NULL,
            stage_date DATE NOT NULL,
            author_id BIGINT(20) UNSIGNED NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contract_id (contract_id),
            KEY stage_date (stage_date)
        ) {$charset_collate};";

        $schema[] = "CREATE TABLE {$prefix}properties (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT(20) UNSIGNED NULL,
            listing_number VARCHAR(60) NOT NULL,
            title VARCHAR(200) NOT NULL DEFAULT '',
            transaction_type VARCHAR(20) NOT NULL,
            property_type VARCHAR(30) NOT NULL,
            ownership_status VARCHAR(60) NOT NULL DEFAULT '',
            price DECIMAL(15,2) NULL,
            price_per_sqm DECIMAL(15,2) NULL,
            price_currency CHAR(3) NOT NULL DEFAULT 'PLN',
            price_period VARCHAR(20) NOT NULL DEFAULT '',
            administrative_rent DECIMAL(15,2) NULL,
            area_total DECIMAL(10,2) NULL,
            area_plot DECIMAL(10,2) NULL,
            rooms SMALLINT(5) UNSIGNED NULL,
            bedrooms SMALLINT(5) UNSIGNED NULL,
            bathrooms SMALLINT(5) UNSIGNED NULL,
            toilets SMALLINT(5) UNSIGNED NULL,
            year_built SMALLINT(4) UNSIGNED NULL,
            floor SMALLINT(5) UNSIGNED NULL,
            total_floors SMALLINT(5) UNSIGNED NULL,
            plot_shape VARCHAR(30) NOT NULL DEFAULT '',
            plot_length DECIMAL(10,2) NULL,
            plot_width DECIMAL(10,2) NULL,
            plot_dimensions_note TEXT NULL,
            land_register_number VARCHAR(60) NOT NULL DEFAULT '',
            street VARCHAR(150) NOT NULL DEFAULT '',
            street_number VARCHAR(30) NOT NULL DEFAULT '',
            apartment_number VARCHAR(30) NOT NULL DEFAULT '',
            postal_code VARCHAR(20) NOT NULL DEFAULT '',
            district VARCHAR(100) NOT NULL DEFAULT '',
            city VARCHAR(100) NOT NULL DEFAULT '',
            voivodeship VARCHAR(100) NOT NULL DEFAULT '',
            county VARCHAR(100) NOT NULL DEFAULT '',
            precinct VARCHAR(100) NOT NULL DEFAULT '',
            plot_number VARCHAR(60) NOT NULL DEFAULT '',
            latitude DECIMAL(10,8) NULL,
            longitude DECIMAL(11,8) NULL,
            description LONGTEXT NULL,
            building_details LONGTEXT NULL,
            media LONGTEXT NULL,
            amenities LONGTEXT NULL,
            equipment LONGTEXT NULL,
            additional_areas LONGTEXT NULL,
            labels LONGTEXT NULL,
            export_web TINYINT(1) NOT NULL DEFAULT 0,
            export_portals TINYINT(1) NOT NULL DEFAULT 0,
            new_offer TINYINT(1) NOT NULL DEFAULT 0,
            new_offer_until DATETIME NULL DEFAULT NULL,
            exclusive_offer TINYINT(1) NOT NULL DEFAULT 0,
            sold_offer TINYINT(1) NOT NULL DEFAULT 0,
            rented_offer TINYINT(1) NOT NULL DEFAULT 0,
            new_price TINYINT(1) NOT NULL DEFAULT 0,
            commission_free TINYINT(1) NOT NULL DEFAULT 0,
            mls_offer TINYINT(1) NOT NULL DEFAULT 0,
            premium_offer TINYINT(1) NOT NULL DEFAULT 0,
            video_url VARCHAR(255) NOT NULL DEFAULT '',
            virtual_tour_url VARCHAR(255) NOT NULL DEFAULT '',
            google_place_id VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY listing_number (listing_number),
            KEY contract_id (contract_id),
            KEY city (city),
            KEY transaction_type (transaction_type),
            KEY property_type (property_type)
        ) {$charset_collate};";

        $schema[] = "CREATE TABLE {$prefix}searches (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT(20) UNSIGNED NULL,
            transaction_type VARCHAR(20) NOT NULL,
            price_min DECIMAL(15,2) NULL,
            price_max DECIMAL(15,2) NULL,
            area_min DECIMAL(10,2) NULL,
            area_max DECIMAL(10,2) NULL,
            rooms_min SMALLINT(5) UNSIGNED NULL,
            rooms_max SMALLINT(5) UNSIGNED NULL,
            description LONGTEXT NULL,
            criteria LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY contract_id (contract_id),
            KEY transaction_type (transaction_type)
        ) {$charset_collate};";

        $schema[] = "CREATE TABLE {$prefix}contract_clients (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT(20) UNSIGNED NOT NULL,
            client_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(60) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_client_unique (contract_id, client_id, role),
            KEY client_id (client_id)
        ) {$charset_collate};";

        $schema[] = "CREATE TABLE {$prefix}contract_properties (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT(20) UNSIGNED NOT NULL,
            property_id BIGINT(20) UNSIGNED NOT NULL,
            relation_type VARCHAR(30) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_property_unique (contract_id, property_id, relation_type),
            KEY property_id (property_id)
        ) {$charset_collate};";

        return $schema;
    }
}
