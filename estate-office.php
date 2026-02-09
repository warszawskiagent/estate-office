<?php
/**
 * Plugin Name: EstateOffice CRM
 * Description: CRM dla biur nieruchomości z własnymi bazami danych i formularzami.
 * Version: 0.1.0
 * Author: EstateOffice
 * Text Domain: estateoffice
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EstateOffice_CRM_Plugin {
    const VERSION = '0.1.0';
    const OPTION_PAGES = 'estateoffice_crm_pages';

    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );
    }

    public function activate() {
        $this->create_tables();
        $this->create_roles();
        $this->create_pages();
    }

    private function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array();

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(20) NOT NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            company_name VARCHAR(190) NULL,
            representative_name VARCHAR(190) NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(190) NULL,
            website VARCHAR(190) NULL,
            pesel VARCHAR(20) NULL,
            document_type VARCHAR(50) NULL,
            document_number VARCHAR(50) NULL,
            nip VARCHAR(20) NULL,
            krs VARCHAR(20) NULL,
            regon VARCHAR(20) NULL,
            address_street VARCHAR(190) NULL,
            address_number VARCHAR(50) NULL,
            address_unit VARCHAR(50) NULL,
            address_postcode VARCHAR(20) NULL,
            address_city VARCHAR(100) NULL,
            address_country VARCHAR(100) NULL,
            corr_same TINYINT(1) NOT NULL DEFAULT 1,
            corr_street VARCHAR(190) NULL,
            corr_number VARCHAR(50) NULL,
            corr_unit VARCHAR(50) NULL,
            corr_postcode VARCHAR(20) NULL,
            corr_city VARCHAR(100) NULL,
            corr_country VARCHAR(100) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_contracts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(100) NOT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            indefinite TINYINT(1) NOT NULL DEFAULT 0,
            commission_amount DECIMAL(12,2) NULL,
            commission_currency VARCHAR(10) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY number (number)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_properties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NULL,
            transaction_type VARCHAR(20) NOT NULL,
            property_type VARCHAR(30) NOT NULL,
            address_street VARCHAR(190) NULL,
            address_number VARCHAR(50) NULL,
            address_unit VARCHAR(50) NULL,
            address_postcode VARCHAR(20) NULL,
            address_city VARCHAR(100) NULL,
            address_district VARCHAR(100) NULL,
            price DECIMAL(14,2) NULL,
            area DECIMAL(12,2) NULL,
            price_per_m2 DECIMAL(14,2) NULL,
            rooms SMALLINT NULL,
            agent_id BIGINT UNSIGNED NULL,
            export_www TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_searches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NULL,
            transaction_type VARCHAR(20) NOT NULL,
            property_type VARCHAR(30) NULL,
            budget_min DECIMAL(14,2) NULL,
            budget_max DECIMAL(14,2) NULL,
            location VARCHAR(190) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_agents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            phone VARCHAR(50) NULL,
            bio TEXT NULL,
            photo_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_contract_clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(50) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contract_id (contract_id),
            KEY client_id (client_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}eo_contract_stages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NOT NULL,
            stage VARCHAR(100) NOT NULL,
            stage_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contract_id (contract_id)
        ) $charset_collate;";

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }

    private function create_roles() {
        if ( ! get_role( 'estateoffice_agent' ) ) {
            add_role(
                'estateoffice_agent',
                'Agent Nieruchomości',
                array(
                    'read' => true,
                )
            );
        }
    }

    private function create_pages() {
        if ( get_option( self::OPTION_PAGES ) ) {
            return;
        }

        $pages = array();

        $parent_id = wp_insert_post(
            array(
                'post_title'   => 'CRM',
                'post_name'    => 'estateoffice-crm',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '[estateoffice_crm view="dashboard"]',
            )
        );

        if ( $parent_id && ! is_wp_error( $parent_id ) ) {
            $pages['crm'] = $parent_id;

            $pages['dashboard'] = $this->create_child_page( 'Pulpit', 'crm-pulpit', 'dashboard', $parent_id );
            $pages['properties'] = $this->create_child_page( 'Nieruchomości', 'crm-nieruchomosci', 'properties', $parent_id );
            $pages['searches'] = $this->create_child_page( 'Poszukiwania', 'crm-poszukiwania', 'searches', $parent_id );
            $pages['contracts'] = $this->create_child_page( 'Umowy', 'crm-umowy', 'contracts', $parent_id );
            $pages['clients'] = $this->create_child_page( 'Klienci', 'crm-klienci', 'clients', $parent_id );
        }

        update_option( self::OPTION_PAGES, $pages, false );
    }

    private function create_child_page( $title, $slug, $view, $parent_id ) {
        return wp_insert_post(
            array(
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => $parent_id,
                'post_content' => sprintf( '[estateoffice_crm view="%s"]', esc_attr( $view ) ),
            )
        );
    }

    public function register_shortcodes() {
        add_shortcode( 'estateoffice_crm', array( $this, 'render_crm_shortcode' ) );
    }

    public function render_crm_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Aby korzystać z CRM musisz być zalogowany.</p>';
        }

        $atts = shortcode_atts(
            array(
                'view' => 'dashboard',
            ),
            $atts,
            'estateoffice_crm'
        );

        $pages = get_option( self::OPTION_PAGES, array() );
        $links = array(
            'dashboard'  => isset( $pages['dashboard'] ) ? get_permalink( $pages['dashboard'] ) : '#',
            'properties' => isset( $pages['properties'] ) ? get_permalink( $pages['properties'] ) : '#',
            'searches'   => isset( $pages['searches'] ) ? get_permalink( $pages['searches'] ) : '#',
            'contracts'  => isset( $pages['contracts'] ) ? get_permalink( $pages['contracts'] ) : '#',
            'clients'    => isset( $pages['clients'] ) ? get_permalink( $pages['clients'] ) : '#',
        );

        ob_start();
        ?>
        <div class="estateoffice-crm">
            <nav class="estateoffice-crm__nav">
                <ul>
                    <li><a href="<?php echo esc_url( $links['dashboard'] ); ?>">Pulpit</a></li>
                    <li><a href="<?php echo esc_url( $links['properties'] ); ?>">Nieruchomości</a></li>
                    <li><a href="<?php echo esc_url( $links['searches'] ); ?>">Poszukiwania</a></li>
                    <li><a href="<?php echo esc_url( $links['contracts'] ); ?>">Umowy</a></li>
                    <li><a href="<?php echo esc_url( $links['clients'] ); ?>">Klienci</a></li>
                </ul>
            </nav>
            <section class="estateoffice-crm__content">
                <h2><?php echo esc_html( $this->get_view_label( $atts['view'] ) ); ?></h2>
                <p>Widok przygotowany pod rozwój funkcjonalności zgodnie z harmonogramem wersji 0.1 → 1.0.</p>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_view_label( $view ) {
        $labels = array(
            'dashboard'  => 'Pulpit',
            'properties' => 'Nieruchomości',
            'searches'   => 'Poszukiwania',
            'contracts'  => 'Umowy',
            'clients'    => 'Klienci',
        );

        return $labels[ $view ] ?? 'CRM';
    }
}

new EstateOffice_CRM_Plugin();
