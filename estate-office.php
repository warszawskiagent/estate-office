<?php
/**
 * Plugin Name: EstateOffice CRM
 * Description: CRM dla biur nieruchomości z własnymi bazami danych i formularzami.
 * Version: 0.7.0
 * Author: EstateOffice
 * Text Domain: estateoffice
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EstateOffice_CRM_Plugin {
    const VERSION = '0.7.0';
    const OPTION_PAGES = 'estateoffice_crm_pages';

    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
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
            house_type VARCHAR(30) NULL,
            address_street VARCHAR(190) NULL,
            address_number VARCHAR(50) NULL,
            address_unit VARCHAR(50) NULL,
            address_postcode VARCHAR(20) NULL,
            address_city VARCHAR(100) NULL,
            address_district VARCHAR(100) NULL,
            county VARCHAR(100) NULL,
            precinct VARCHAR(100) NULL,
            plot_number VARCHAR(80) NULL,
            land_register_no VARCHAR(80) NULL,
            land_register_missing TINYINT(1) NOT NULL DEFAULT 0,
            legal_status VARCHAR(80) NULL,
            price DECIMAL(14,2) NULL,
            monthly_rent DECIMAL(14,2) NULL,
            area DECIMAL(12,2) NULL,
            price_per_m2 DECIMAL(14,2) NULL,
            build_year SMALLINT NULL,
            floor SMALLINT NULL,
            floors_total SMALLINT NULL,
            rooms SMALLINT NULL,
            bedrooms SMALLINT NULL,
            bathrooms SMALLINT NULL,
            toilets SMALLINT NULL,
            plot_shape VARCHAR(30) NULL,
            plot_dim_length DECIMAL(10,2) NULL,
            plot_dim_width DECIMAL(10,2) NULL,
            plot_dim_text VARCHAR(190) NULL,
            description LONGTEXT NULL,
            media_heating VARCHAR(50) NULL,
            media_water VARCHAR(50) NULL,
            media_sewage VARCHAR(50) NULL,
            media_gas TINYINT(1) NOT NULL DEFAULT 0,
            tags TEXT NULL,
            extra_details LONGTEXT NULL,
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

    public function enqueue_frontend_assets() {
        $handle = 'estateoffice-crm-styles';
        wp_register_style( $handle, false, array(), self::VERSION );
        wp_enqueue_style( $handle );
        wp_add_inline_style( $handle, $this->get_crm_styles() );
    }

    private function get_crm_styles() {
        return '
        .estateoffice-crm {
            font-family: "Inter", "Segoe UI", sans-serif;
            display: grid;
            gap: 24px;
        }
        .estateoffice-crm__nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .estateoffice-crm__nav a {
            text-decoration: none;
            background: #1e293b;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            display: inline-block;
        }
        .estateoffice-crm__content {
            background: #f8fafc;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .estateoffice-crm__section {
            margin-bottom: 24px;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .estateoffice-crm__section h3 {
            margin-top: 0;
        }
        .estateoffice-crm__section form {
            display: grid;
            gap: 12px;
        }
        .estateoffice-crm__section label {
            font-weight: 600;
            display: block;
        }
        .estateoffice-crm__section input,
        .estateoffice-crm__section select,
        .estateoffice-crm__section textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5f5;
            font-size: 14px;
        }
        .estateoffice-crm__section fieldset {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
        }
        .estateoffice-crm__section button {
            justify-self: start;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        .estateoffice-crm__section table {
            width: 100%;
            border-collapse: collapse;
        }
        .estateoffice-crm__section th,
        .estateoffice-crm__section td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .estateoffice-crm__section label input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }
        .estateoffice-crm__section a {
            color: #1d4ed8;
            font-weight: 600;
        }
        ';
    }

    public function register_admin_menu() {
        $capability = 'manage_options';

        add_menu_page(
            'EstateOffice CRM',
            'EstateOffice CRM',
            $capability,
            'estateoffice-crm',
            array( $this, 'render_admin_dashboard' ),
            'dashicons-building',
            30
        );

        add_submenu_page(
            'estateoffice-crm',
            'Licencja',
            'Licencja',
            $capability,
            'estateoffice-crm-license',
            array( $this, 'render_admin_license' )
        );

        add_submenu_page(
            'estateoffice-crm',
            'Agenci',
            'Agenci',
            $capability,
            'estateoffice-crm-agents',
            array( $this, 'render_admin_agents' )
        );

        add_submenu_page(
            'estateoffice-crm',
            'Ustawienia',
            'Ustawienia',
            $capability,
            'estateoffice-crm-settings',
            array( $this, 'render_admin_settings' )
        );

        add_submenu_page(
            'estateoffice-crm',
            'About',
            'About',
            $capability,
            'estateoffice-crm-about',
            array( $this, 'render_admin_about' )
        );
    }

    public function render_crm_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Aby korzystać z CRM musisz być zalogowany.</p>';
        }

        $this->handle_client_submission();
        $this->handle_contract_submission();
        $this->handle_property_submission();
        $this->handle_search_submission();

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
                <?php
                if ( 'clients' === $atts['view'] ) {
                    $this->render_clients_view();
                } elseif ( 'properties' === $atts['view'] ) {
                    $this->render_properties_view();
                } elseif ( 'searches' === $atts['view'] ) {
                    $this->render_searches_view();
                } elseif ( 'contracts' === $atts['view'] ) {
                    $this->render_contracts_view();
                } else {
                    ?>
                    <p>Widok przygotowany pod rozwój funkcjonalności zgodnie z harmonogramem wersji 0.1 → 1.0.</p>
                    <?php
                }
                ?>
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

    private function handle_client_submission() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( empty( $_POST['estateoffice_action'] ) || 'create_client' !== $_POST['estateoffice_action'] ) {
            return;
        }

        $nonce = isset( $_POST['estateoffice_client_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['estateoffice_client_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'estateoffice_create_client' ) ) {
            return;
        }

        $client_type = isset( $_POST['client_type'] ) ? sanitize_text_field( wp_unslash( $_POST['client_type'] ) ) : '';
        if ( ! in_array( $client_type, array( 'individual', 'company' ), true ) ) {
            $client_type = 'individual';
        }

        $data = array(
            'type'                 => $client_type,
            'first_name'           => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : null,
            'last_name'            => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : null,
            'company_name'         => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : null,
            'representative_name'  => isset( $_POST['representative_name'] ) ? sanitize_text_field( wp_unslash( $_POST['representative_name'] ) ) : null,
            'phone'                => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : null,
            'email'                => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : null,
            'website'              => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : null,
            'pesel'                => isset( $_POST['pesel'] ) ? sanitize_text_field( wp_unslash( $_POST['pesel'] ) ) : null,
            'document_type'        => isset( $_POST['document_type'] ) ? sanitize_text_field( wp_unslash( $_POST['document_type'] ) ) : null,
            'document_number'      => isset( $_POST['document_number'] ) ? sanitize_text_field( wp_unslash( $_POST['document_number'] ) ) : null,
            'nip'                  => isset( $_POST['nip'] ) ? sanitize_text_field( wp_unslash( $_POST['nip'] ) ) : null,
            'krs'                  => isset( $_POST['krs'] ) ? sanitize_text_field( wp_unslash( $_POST['krs'] ) ) : null,
            'regon'                => isset( $_POST['regon'] ) ? sanitize_text_field( wp_unslash( $_POST['regon'] ) ) : null,
            'address_street'       => isset( $_POST['address_street'] ) ? sanitize_text_field( wp_unslash( $_POST['address_street'] ) ) : null,
            'address_number'       => isset( $_POST['address_number'] ) ? sanitize_text_field( wp_unslash( $_POST['address_number'] ) ) : null,
            'address_unit'         => isset( $_POST['address_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['address_unit'] ) ) : null,
            'address_postcode'     => isset( $_POST['address_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['address_postcode'] ) ) : null,
            'address_city'         => isset( $_POST['address_city'] ) ? sanitize_text_field( wp_unslash( $_POST['address_city'] ) ) : null,
            'address_country'      => isset( $_POST['address_country'] ) ? sanitize_text_field( wp_unslash( $_POST['address_country'] ) ) : null,
            'corr_same'            => isset( $_POST['corr_same'] ) ? 1 : 0,
            'corr_street'          => isset( $_POST['corr_street'] ) ? sanitize_text_field( wp_unslash( $_POST['corr_street'] ) ) : null,
            'corr_number'          => isset( $_POST['corr_number'] ) ? sanitize_text_field( wp_unslash( $_POST['corr_number'] ) ) : null,
            'corr_unit'            => isset( $_POST['corr_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['corr_unit'] ) ) : null,
            'corr_postcode'        => isset( $_POST['corr_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['corr_postcode'] ) ) : null,
            'corr_city'            => isset( $_POST['corr_city'] ) ? sanitize_text_field( wp_unslash( $_POST['corr_city'] ) ) : null,
            'corr_country'         => isset( $_POST['corr_country'] ) ? sanitize_text_field( wp_unslash( $_POST['corr_country'] ) ) : null,
        );

        if ( 1 === $data['corr_same'] ) {
            $data['corr_street'] = null;
            $data['corr_number'] = null;
            $data['corr_unit'] = null;
            $data['corr_postcode'] = null;
            $data['corr_city'] = null;
            $data['corr_country'] = null;
        }

        global $wpdb;
        $wpdb->insert( "{$wpdb->prefix}eo_clients", $data );
    }

    private function handle_contract_submission() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( empty( $_POST['estateoffice_action'] ) || 'create_contract' !== $_POST['estateoffice_action'] ) {
            return;
        }

        $nonce = isset( $_POST['estateoffice_contract_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['estateoffice_contract_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'estateoffice_create_contract' ) ) {
            return;
        }

        $number = isset( $_POST['contract_number'] ) ? sanitize_text_field( wp_unslash( $_POST['contract_number'] ) ) : '';
        $transaction_type = isset( $_POST['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_type'] ) ) : '';
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
        $indefinite = isset( $_POST['indefinite'] ) ? 1 : 0;
        $commission_amount = isset( $_POST['commission_amount'] ) ? floatval( wp_unslash( $_POST['commission_amount'] ) ) : null;
        $commission_currency = isset( $_POST['commission_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['commission_currency'] ) ) : null;

        if ( '' === $number || '' === $transaction_type || '' === $start_date ) {
            return;
        }

        $valid_transaction_types = array( 'sprzedaz', 'kupno', 'wynajem', 'najem' );
        if ( ! in_array( $transaction_type, $valid_transaction_types, true ) ) {
            return;
        }

        if ( 1 === $indefinite ) {
            $end_date = null;
        }

        global $wpdb;
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eo_contracts WHERE number = %s",
                $number
            )
        );

        if ( $exists ) {
            return;
        }

        $data = array(
            'number'             => $number,
            'transaction_type'   => $transaction_type,
            'start_date'         => $start_date,
            'end_date'           => $end_date,
            'indefinite'         => $indefinite,
            'commission_amount'  => null !== $commission_amount ? $commission_amount : null,
            'commission_currency'=> $commission_currency,
            'created_by'         => get_current_user_id(),
        );

        $wpdb->insert( "{$wpdb->prefix}eo_contracts", $data );
    }

    private function handle_property_submission() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( empty( $_POST['estateoffice_action'] ) || 'create_property' !== $_POST['estateoffice_action'] ) {
            return;
        }

        $nonce = isset( $_POST['estateoffice_property_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['estateoffice_property_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'estateoffice_create_property' ) ) {
            return;
        }

        $transaction_type = isset( $_POST['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_type'] ) ) : '';
        $property_type = isset( $_POST['property_type'] ) ? sanitize_text_field( wp_unslash( $_POST['property_type'] ) ) : '';
        $address_street = isset( $_POST['address_street'] ) ? sanitize_text_field( wp_unslash( $_POST['address_street'] ) ) : '';
        $address_number = isset( $_POST['address_number'] ) ? sanitize_text_field( wp_unslash( $_POST['address_number'] ) ) : '';
        $address_postcode = isset( $_POST['address_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['address_postcode'] ) ) : '';
        $address_city = isset( $_POST['address_city'] ) ? sanitize_text_field( wp_unslash( $_POST['address_city'] ) ) : '';

        $valid_transaction_types = array( 'sprzedaz', 'kupno', 'wynajem', 'najem' );
        $valid_property_types = array( 'mieszkanie', 'dom', 'dzialka', 'lokal_hu' );

        if ( ! in_array( $transaction_type, $valid_transaction_types, true ) || ! in_array( $property_type, $valid_property_types, true ) ) {
            return;
        }

        if ( '' === $address_street || '' === $address_number || '' === $address_postcode || '' === $address_city ) {
            return;
        }

        $price = isset( $_POST['price'] ) ? floatval( wp_unslash( $_POST['price'] ) ) : null;
        $area = isset( $_POST['area'] ) ? floatval( wp_unslash( $_POST['area'] ) ) : null;
        $price_per_m2 = null;
        if ( null !== $price && null !== $area && $area > 0 ) {
            $price_per_m2 = round( $price / $area, 2 );
        }

        $land_register_missing = isset( $_POST['land_register_missing'] ) ? 1 : 0;
        $land_register_no = isset( $_POST['land_register_no'] ) ? sanitize_text_field( wp_unslash( $_POST['land_register_no'] ) ) : null;
        if ( 1 === $land_register_missing ) {
            $land_register_no = null;
        }

        $plot_shape = isset( $_POST['plot_shape'] ) ? sanitize_text_field( wp_unslash( $_POST['plot_shape'] ) ) : null;
        $plot_dim_length = isset( $_POST['plot_dim_length'] ) ? floatval( wp_unslash( $_POST['plot_dim_length'] ) ) : null;
        $plot_dim_width = isset( $_POST['plot_dim_width'] ) ? floatval( wp_unslash( $_POST['plot_dim_width'] ) ) : null;
        $plot_dim_text = isset( $_POST['plot_dim_text'] ) ? sanitize_text_field( wp_unslash( $_POST['plot_dim_text'] ) ) : null;

        if ( 'regularny' === $plot_shape ) {
            $plot_dim_text = null;
        } elseif ( 'nieregularny' === $plot_shape ) {
            $plot_dim_length = null;
            $plot_dim_width = null;
        }

        $extra_details = array(
            'building_finish' => isset( $_POST['building_finish'] ) ? sanitize_text_field( wp_unslash( $_POST['building_finish'] ) ) : '',
            'exposition'      => isset( $_POST['exposition'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['exposition'] ) ) : array(),
            'parking'         => isset( $_POST['parking'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['parking'] ) ) : array(),
            'amenities'       => isset( $_POST['amenities'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['amenities'] ) ) : array(),
            'equipment'       => isset( $_POST['equipment'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['equipment'] ) ) : array(),
            'additional_areas'=> isset( $_POST['additional_areas'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['additional_areas'] ) ) : array(),
            'virtual_tour'    => isset( $_POST['virtual_tour'] ) ? esc_url_raw( wp_unslash( $_POST['virtual_tour'] ) ) : '',
            'video_link'      => isset( $_POST['video_link'] ) ? esc_url_raw( wp_unslash( $_POST['video_link'] ) ) : '',
        );

        $data = array(
            'transaction_type'      => $transaction_type,
            'property_type'         => $property_type,
            'house_type'            => isset( $_POST['house_type'] ) ? sanitize_text_field( wp_unslash( $_POST['house_type'] ) ) : null,
            'address_street'        => $address_street,
            'address_number'        => $address_number,
            'address_unit'          => isset( $_POST['address_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['address_unit'] ) ) : null,
            'address_postcode'      => $address_postcode,
            'address_city'          => $address_city,
            'address_district'      => isset( $_POST['address_district'] ) ? sanitize_text_field( wp_unslash( $_POST['address_district'] ) ) : null,
            'county'                => isset( $_POST['county'] ) ? sanitize_text_field( wp_unslash( $_POST['county'] ) ) : null,
            'precinct'              => isset( $_POST['precinct'] ) ? sanitize_text_field( wp_unslash( $_POST['precinct'] ) ) : null,
            'plot_number'           => isset( $_POST['plot_number'] ) ? sanitize_text_field( wp_unslash( $_POST['plot_number'] ) ) : null,
            'land_register_no'      => $land_register_no,
            'land_register_missing' => $land_register_missing,
            'legal_status'          => isset( $_POST['legal_status'] ) ? sanitize_text_field( wp_unslash( $_POST['legal_status'] ) ) : null,
            'price'                 => $price,
            'monthly_rent'          => isset( $_POST['monthly_rent'] ) ? floatval( wp_unslash( $_POST['monthly_rent'] ) ) : null,
            'area'                  => $area,
            'price_per_m2'          => $price_per_m2,
            'build_year'            => isset( $_POST['build_year'] ) ? intval( wp_unslash( $_POST['build_year'] ) ) : null,
            'floor'                 => isset( $_POST['floor'] ) ? intval( wp_unslash( $_POST['floor'] ) ) : null,
            'floors_total'          => isset( $_POST['floors_total'] ) ? intval( wp_unslash( $_POST['floors_total'] ) ) : null,
            'rooms'                 => isset( $_POST['rooms'] ) ? intval( wp_unslash( $_POST['rooms'] ) ) : null,
            'bedrooms'              => isset( $_POST['bedrooms'] ) ? intval( wp_unslash( $_POST['bedrooms'] ) ) : null,
            'bathrooms'             => isset( $_POST['bathrooms'] ) ? intval( wp_unslash( $_POST['bathrooms'] ) ) : null,
            'toilets'               => isset( $_POST['toilets'] ) ? intval( wp_unslash( $_POST['toilets'] ) ) : null,
            'plot_shape'            => $plot_shape,
            'plot_dim_length'       => $plot_dim_length,
            'plot_dim_width'        => $plot_dim_width,
            'plot_dim_text'         => $plot_dim_text,
            'description'           => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : null,
            'media_heating'         => isset( $_POST['media_heating'] ) ? sanitize_text_field( wp_unslash( $_POST['media_heating'] ) ) : null,
            'media_water'           => isset( $_POST['media_water'] ) ? sanitize_text_field( wp_unslash( $_POST['media_water'] ) ) : null,
            'media_sewage'          => isset( $_POST['media_sewage'] ) ? sanitize_text_field( wp_unslash( $_POST['media_sewage'] ) ) : null,
            'media_gas'             => isset( $_POST['media_gas'] ) ? 1 : 0,
            'tags'                  => isset( $_POST['tags'] ) ? implode( ',', array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['tags'] ) ) ) : null,
            'extra_details'         => wp_json_encode( $extra_details ),
            'agent_id'              => get_current_user_id(),
            'export_www'            => isset( $_POST['export_www'] ) ? 1 : 0,
        );

        global $wpdb;
        $wpdb->insert( "{$wpdb->prefix}eo_properties", $data );
    }

    private function handle_search_submission() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( empty( $_POST['estateoffice_action'] ) || 'create_search' !== $_POST['estateoffice_action'] ) {
            return;
        }

        $nonce = isset( $_POST['estateoffice_search_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['estateoffice_search_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'estateoffice_create_search' ) ) {
            return;
        }

        $transaction_type = isset( $_POST['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_type'] ) ) : '';
        $property_type = isset( $_POST['property_type'] ) ? sanitize_text_field( wp_unslash( $_POST['property_type'] ) ) : '';
        $budget_min = isset( $_POST['budget_min'] ) ? floatval( wp_unslash( $_POST['budget_min'] ) ) : null;
        $budget_max = isset( $_POST['budget_max'] ) ? floatval( wp_unslash( $_POST['budget_max'] ) ) : null;
        $location = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';

        $valid_transaction_types = array( 'sprzedaz', 'kupno', 'wynajem', 'najem' );
        if ( '' === $transaction_type || ! in_array( $transaction_type, $valid_transaction_types, true ) ) {
            return;
        }

        $data = array(
            'transaction_type' => $transaction_type,
            'property_type'    => $property_type,
            'budget_min'       => $budget_min,
            'budget_max'       => $budget_max,
            'location'         => $location,
        );

        global $wpdb;
        $wpdb->insert( "{$wpdb->prefix}eo_searches", $data );
    }

    private function render_clients_view() {
        global $wpdb;

        $clients = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}eo_clients ORDER BY created_at DESC LIMIT 50" );
        ?>
        <div class="estateoffice-crm__section">
            <h3>Dodaj klienta</h3>
            <form method="post">
                <?php wp_nonce_field( 'estateoffice_create_client', 'estateoffice_client_nonce' ); ?>
                <input type="hidden" name="estateoffice_action" value="create_client">
                <div>
                    <label for="client_type">Typ klienta</label>
                    <select id="client_type" name="client_type">
                        <option value="individual">Osoba fizyczna</option>
                        <option value="company">Firma</option>
                    </select>
                </div>

                <div class="estateoffice-client__individual">
                    <label for="first_name">Imię</label>
                    <input id="first_name" name="first_name" type="text">
                    <label for="last_name">Nazwisko</label>
                    <input id="last_name" name="last_name" type="text">
                </div>

                <div class="estateoffice-client__company" style="display: none;">
                    <label for="company_name">Nazwa firmy</label>
                    <input id="company_name" name="company_name" type="text">
                    <label for="representative_name">Imię i nazwisko reprezentanta</label>
                    <input id="representative_name" name="representative_name" type="text">
                </div>

                <fieldset>
                    <legend>Dane kontaktowe</legend>
                    <label for="phone">Telefon</label>
                    <input id="phone" name="phone" type="text">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email">
                    <label for="website">Strona WWW (firma)</label>
                    <input id="website" name="website" type="url">
                </fieldset>

                <fieldset class="estateoffice-client__individual">
                    <legend>Dane identyfikacyjne (osoba fizyczna)</legend>
                    <label for="pesel">PESEL</label>
                    <input id="pesel" name="pesel" type="text">
                    <label for="document_type">Rodzaj dokumentu</label>
                    <select id="document_type" name="document_type">
                        <option value="">Wybierz</option>
                        <option value="dowod">Dowód osobisty</option>
                        <option value="paszport">Paszport</option>
                        <option value="karta_pobytu">Karta pobytu</option>
                    </select>
                    <label for="document_number">Numer dokumentu</label>
                    <input id="document_number" name="document_number" type="text">
                </fieldset>

                <fieldset class="estateoffice-client__company" style="display: none;">
                    <legend>Dane identyfikacyjne (firma)</legend>
                    <label for="nip">NIP</label>
                    <input id="nip" name="nip" type="text">
                    <label for="krs">KRS</label>
                    <input id="krs" name="krs" type="text">
                    <label for="regon">REGON</label>
                    <input id="regon" name="regon" type="text">
                </fieldset>

                <fieldset>
                    <legend>Adres zamieszkania / rejestrowy</legend>
                    <label for="address_street">Ulica</label>
                    <input id="address_street" name="address_street" type="text">
                    <label for="address_number">Numer</label>
                    <input id="address_number" name="address_number" type="text">
                    <label for="address_unit">Lokal</label>
                    <input id="address_unit" name="address_unit" type="text">
                    <label for="address_postcode">Kod pocztowy</label>
                    <input id="address_postcode" name="address_postcode" type="text">
                    <label for="address_city">Miasto</label>
                    <input id="address_city" name="address_city" type="text">
                    <label for="address_country">Kraj</label>
                    <input id="address_country" name="address_country" type="text">
                </fieldset>

                <fieldset>
                    <legend>Adres korespondencyjny</legend>
                    <label>
                        <input id="corr_same" name="corr_same" type="checkbox" checked>
                        Adres korespondencyjny taki sam
                    </label>
                    <div class="estateoffice-client__corr" style="display: none;">
                        <label for="corr_street">Ulica</label>
                        <input id="corr_street" name="corr_street" type="text">
                        <label for="corr_number">Numer</label>
                        <input id="corr_number" name="corr_number" type="text">
                        <label for="corr_unit">Lokal</label>
                        <input id="corr_unit" name="corr_unit" type="text">
                        <label for="corr_postcode">Kod pocztowy</label>
                        <input id="corr_postcode" name="corr_postcode" type="text">
                        <label for="corr_city">Miasto</label>
                        <input id="corr_city" name="corr_city" type="text">
                        <label for="corr_country">Kraj</label>
                        <input id="corr_country" name="corr_country" type="text">
                    </div>
                </fieldset>

                <button type="submit">Dodaj klienta</button>
            </form>
        </div>

        <div class="estateoffice-crm__section">
            <h3>Lista klientów</h3>
            <?php if ( empty( $clients ) ) : ?>
                <p>Brak klientów.</p>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Imię i nazwisko / Nazwa</th>
                            <th>Telefon</th>
                            <th>E-mail</th>
                            <th>Miasto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $clients as $client ) : ?>
                            <tr>
                                <td><?php echo esc_html( $this->get_client_display_name( $client ) ); ?></td>
                                <td><?php echo esc_html( $client->phone ); ?></td>
                                <td><?php echo esc_html( $client->email ); ?></td>
                                <td><?php echo esc_html( $client->address_city ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <script>
            (function() {
                const clientType = document.getElementById('client_type');
                const corrSame = document.getElementById('corr_same');
                const individualBlocks = document.querySelectorAll('.estateoffice-client__individual');
                const companyBlocks = document.querySelectorAll('.estateoffice-client__company');
                const corrBlock = document.querySelector('.estateoffice-client__corr');

                function toggleClientType() {
                    const isCompany = clientType.value === 'company';
                    individualBlocks.forEach((block) => {
                        block.style.display = isCompany ? 'none' : 'block';
                    });
                    companyBlocks.forEach((block) => {
                        block.style.display = isCompany ? 'block' : 'none';
                    });
                }

                function toggleCorr() {
                    if (!corrBlock) {
                        return;
                    }
                    corrBlock.style.display = corrSame.checked ? 'none' : 'block';
                }

                if (clientType) {
                    clientType.addEventListener('change', toggleClientType);
                    toggleClientType();
                }
                if (corrSame) {
                    corrSame.addEventListener('change', toggleCorr);
                    toggleCorr();
                }
            })();
        </script>
        <?php
    }

    private function get_client_display_name( $client ) {
        if ( 'company' === $client->type && ! empty( $client->company_name ) ) {
            return $client->company_name;
        }

        $name = trim( sprintf( '%s %s', $client->first_name ?? '', $client->last_name ?? '' ) );
        return '' !== $name ? $name : 'Klient';
    }

    private function render_contracts_view() {
        global $wpdb;

        $contracts = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}eo_contracts ORDER BY created_at DESC LIMIT 50" );
        ?>
        <div class="estateoffice-crm__section">
            <h3>Dodaj umowę</h3>
            <form method="post">
                <?php wp_nonce_field( 'estateoffice_create_contract', 'estateoffice_contract_nonce' ); ?>
                <input type="hidden" name="estateoffice_action" value="create_contract">
                <label for="contract_number">Numer umowy</label>
                <input id="contract_number" name="contract_number" type="text" required>

                <label for="transaction_type">Typ transakcji</label>
                <select id="transaction_type" name="transaction_type" required>
                    <option value="">Wybierz</option>
                    <option value="sprzedaz">SPRZEDAŻ</option>
                    <option value="kupno">KUPNO</option>
                    <option value="wynajem">WYNAJEM</option>
                    <option value="najem">NAJEM</option>
                </select>

                <label for="start_date">Data zawarcia</label>
                <input id="start_date" name="start_date" type="date" required>

                <label for="end_date">Data zakończenia</label>
                <input id="end_date" name="end_date" type="date">

                <label>
                    <input id="indefinite" name="indefinite" type="checkbox">
                    Umowa bezterminowa
                </label>

                <fieldset>
                    <legend>Wysokość prowizji</legend>
                    <label for="commission_amount">Kwota</label>
                    <input id="commission_amount" name="commission_amount" type="number" step="0.01">
                    <label for="commission_currency">Jednostka</label>
                    <select id="commission_currency" name="commission_currency">
                        <option value="">Wybierz</option>
                        <option value="%">%</option>
                        <option value="PLN">PLN</option>
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                    </select>
                </fieldset>

                <button type="submit">Dodaj umowę</button>
            </form>
        </div>

        <div class="estateoffice-crm__section">
            <h3>Lista umów</h3>
            <?php if ( empty( $contracts ) ) : ?>
                <p>Brak umów.</p>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Numer umowy</th>
                            <th>Typ transakcji</th>
                            <th>Data zawarcia</th>
                            <th>Data zakończenia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $contracts as $contract ) : ?>
                            <tr>
                                <td><?php echo esc_html( $contract->number ); ?></td>
                                <td><?php echo esc_html( strtoupper( $contract->transaction_type ) ); ?></td>
                                <td><?php echo esc_html( $contract->start_date ); ?></td>
                                <td><?php echo esc_html( $contract->indefinite ? 'Bezterminowa' : $contract->end_date ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
            (function() {
                const indefinite = document.getElementById('indefinite');
                const endDate = document.getElementById('end_date');
                if (!indefinite || !endDate) {
                    return;
                }
                function toggleEndDate() {
                    endDate.disabled = indefinite.checked;
                    if (indefinite.checked) {
                        endDate.value = '';
                    }
                }
                indefinite.addEventListener('change', toggleEndDate);
                toggleEndDate();
            })();
        </script>
        <?php
    }

    private function render_properties_view() {
        global $wpdb;

        $properties = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}eo_properties ORDER BY created_at DESC LIMIT 50" );
        ?>
        <div class="estateoffice-crm__section">
            <h3>Dodaj nieruchomość</h3>
            <form method="post" id="estateoffice-property-form">
                <?php wp_nonce_field( 'estateoffice_create_property', 'estateoffice_property_nonce' ); ?>
                <input type="hidden" name="estateoffice_action" value="create_property">

                <fieldset>
                    <legend>Podstawowe dane oferty</legend>
                    <label for="transaction_type">Typ transakcji</label>
                    <select id="transaction_type" name="transaction_type" required>
                        <option value="">Wybierz</option>
                        <option value="sprzedaz">SPRZEDAŻ</option>
                        <option value="kupno">KUPNO</option>
                        <option value="wynajem">WYNAJEM</option>
                        <option value="najem">NAJEM</option>
                    </select>

                    <label for="property_type">Rodzaj nieruchomości</label>
                    <select id="property_type" name="property_type" required>
                        <option value="">Wybierz</option>
                        <option value="mieszkanie">MIESZKANIE</option>
                        <option value="dom">DOM</option>
                        <option value="dzialka">DZIAŁKA</option>
                        <option value="lokal_hu">LOKAL H/U</option>
                    </select>

                    <div id="house_type_wrap" style="display:none;">
                        <label for="house_type">Typ domu</label>
                        <select id="house_type" name="house_type">
                            <option value="">Wybierz</option>
                            <option value="wolnostojacy">WOLNOSTOJĄCY</option>
                            <option value="blizniak">BLIŹNIAK</option>
                            <option value="szeregowiec">SZEREGOWIEC</option>
                            <option value="wielorodzinny">WIELORODZINNY</option>
                        </select>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Dane adresowe</legend>
                    <label for="address_street">Ulica</label>
                    <input id="address_street" name="address_street" type="text" required>
                    <label for="address_number">Numer</label>
                    <input id="address_number" name="address_number" type="text" required>
                    <label for="address_unit">Lokal</label>
                    <input id="address_unit" name="address_unit" type="text">
                    <label for="address_postcode">Kod pocztowy</label>
                    <input id="address_postcode" name="address_postcode" type="text" required>
                    <label for="address_city">Miasto</label>
                    <input id="address_city" name="address_city" type="text" required>
                    <label for="address_district">Dzielnica</label>
                    <input id="address_district" name="address_district" type="text">

                    <div id="plot_address_fields" style="display:none;">
                        <label for="county">Powiat</label>
                        <input id="county" name="county" type="text">
                        <label for="precinct">Obręb</label>
                        <input id="precinct" name="precinct" type="text">
                        <label for="plot_number">Numer działki</label>
                        <input id="plot_number" name="plot_number" type="text">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Status prawny i KW</legend>
                    <label for="land_register_no">Numer Księgi Wieczystej</label>
                    <input id="land_register_no" name="land_register_no" type="text">
                    <label><input id="land_register_missing" name="land_register_missing" type="checkbox"> Brak KW</label>
                    <label for="legal_status">Stan prawny</label>
                    <select id="legal_status" name="legal_status">
                        <option value="">Wybierz</option>
                        <option value="wlasnosc">Własność</option>
                        <option value="wspolwlasnosc">Współwłasność</option>
                        <option value="spoldzielcze">Spółdzielcze Własnościowe Prawo do Lokalu</option>
                        <option value="dzierzawa">Dzierżawa</option>
                        <option value="inne">Inne</option>
                    </select>
                </fieldset>

                <fieldset>
                    <legend>Dane nieruchomości</legend>
                    <label for="price">Cena</label>
                    <input id="price" name="price" type="number" step="0.01">
                    <label for="monthly_rent">Czynsz administracyjny</label>
                    <input id="monthly_rent" name="monthly_rent" type="number" step="0.01">
                    <label for="area">Powierzchnia (m²)</label>
                    <input id="area" name="area" type="number" step="0.01">
                    <label for="price_per_m2">Cena za m²</label>
                    <input id="price_per_m2" name="price_per_m2" type="number" step="0.01" readonly>

                    <div id="building_fields">
                        <label for="build_year">Rok budowy</label>
                        <input id="build_year" name="build_year" type="number">
                        <label for="floor">Piętro</label>
                        <input id="floor" name="floor" type="number">
                        <label for="floors_total">Liczba pięter</label>
                        <input id="floors_total" name="floors_total" type="number" min="0">
                        <label for="rooms">Liczba pokoi</label>
                        <input id="rooms" name="rooms" type="number" min="0">
                        <label for="bedrooms">Liczba sypialni</label>
                        <input id="bedrooms" name="bedrooms" type="number" min="0">
                        <label for="bathrooms">Liczba łazienek</label>
                        <input id="bathrooms" name="bathrooms" type="number" min="0">
                        <label for="toilets">Liczba toalet</label>
                        <input id="toilets" name="toilets" type="number" min="0">
                    </div>

                    <div id="plot_fields" style="display:none;">
                        <label for="plot_shape">Kształt działki</label>
                        <select id="plot_shape" name="plot_shape">
                            <option value="">Wybierz</option>
                            <option value="regularny">Regularny</option>
                            <option value="nieregularny">Nieregularny</option>
                        </select>
                        <div id="plot_dimensions_regular" style="display:none;">
                            <label for="plot_dim_length">Długość działki</label>
                            <input id="plot_dim_length" name="plot_dim_length" type="number" step="0.01">
                            <label for="plot_dim_width">Szerokość działki</label>
                            <input id="plot_dim_width" name="plot_dim_width" type="number" step="0.01">
                        </div>
                        <div id="plot_dimensions_irregular" style="display:none;">
                            <label for="plot_dim_text">Wymiary działki (opis)</label>
                            <input id="plot_dim_text" name="plot_dim_text" type="text">
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Szczegóły i media</legend>
                    <label for="building_finish">Stan wykończenia</label>
                    <select id="building_finish" name="building_finish">
                        <option value="">Wybierz</option>
                        <option value="do_wprowadzenia">Do wprowadzenia</option>
                        <option value="do_remontu">Do remontu</option>
                        <option value="deweloperski">Deweloperski</option>
                    </select>

                    <label>Ekspozycja</label>
                    <label><input type="checkbox" name="exposition[]" value="polnoc"> Północ</label>
                    <label><input type="checkbox" name="exposition[]" value="poludnie"> Południe</label>
                    <label><input type="checkbox" name="exposition[]" value="wschod"> Wschód</label>
                    <label><input type="checkbox" name="exposition[]" value="zachod"> Zachód</label>

                    <label for="media_heating">Ogrzewanie</label>
                    <input id="media_heating" name="media_heating" type="text">
                    <label for="media_water">Woda</label>
                    <input id="media_water" name="media_water" type="text">
                    <label for="media_sewage">Kanalizacja</label>
                    <input id="media_sewage" name="media_sewage" type="text">
                    <label><input type="checkbox" name="media_gas" value="1"> Gaz</label>
                </fieldset>

                <fieldset>
                    <legend>Opis i znaczniki</legend>
                    <label for="description">Opis nieruchomości</label>
                    <textarea id="description" name="description" rows="5"></textarea>
                    <label for="video_link">Link do filmu</label>
                    <input id="video_link" name="video_link" type="url">
                    <label for="virtual_tour">Link do wirtualnego spaceru</label>
                    <input id="virtual_tour" name="virtual_tour" type="url">

                    <label>Znaczniki</label>
                    <label><input type="checkbox" name="tags[]" value="new_offer"> Nowa oferta</label>
                    <label><input type="checkbox" name="tags[]" value="exclusive"> Wyłączność</label>
                    <label><input type="checkbox" name="tags[]" value="sold"> Sprzedane</label>
                    <label><input type="checkbox" name="tags[]" value="rented"> Wynajęte</label>
                    <label><input type="checkbox" name="tags[]" value="new_price"> Nowa cena</label>
                    <label><input type="checkbox" name="tags[]" value="no_commission"> Bez prowizji</label>
                    <label><input type="checkbox" name="tags[]" value="mls"> Oferta MLS</label>
                    <label><input type="checkbox" name="tags[]" value="premium"> Premium</label>

                    <label><input id="export_www" name="export_www" type="checkbox"> Eksport na WWW</label>
                </fieldset>

                <button type="submit">Dodaj nieruchomość</button>
            </form>
        </div>

        <div class="estateoffice-crm__section">
            <h3>Lista nieruchomości</h3>
            <?php if ( empty( $properties ) ) : ?>
                <p>Brak nieruchomości.</p>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Numer oferty</th>
                            <th>Adres</th>
                            <th>Cena</th>
                            <th>Cena za m²</th>
                            <th>Metraż</th>
                            <th>Liczba pokoi</th>
                            <th>Opiekun</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $properties as $property ) : ?>
                            <tr>
                                <td><a href="#oferta-<?php echo esc_attr( $property->id ); ?>"><?php echo esc_html( $property->id ); ?></a></td>
                                <td><?php echo esc_html( trim( sprintf( '%s %s, %s', $property->address_street, $property->address_number, $property->address_city ) ) ); ?></td>
                                <td><?php echo esc_html( $property->price ); ?></td>
                                <td><?php echo esc_html( $property->price_per_m2 ); ?></td>
                                <td><?php echo esc_html( $property->area ); ?></td>
                                <td><?php echo esc_html( $property->rooms ); ?></td>
                                <td><?php echo esc_html( $this->get_agent_display_name( $property->agent_id ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
            (function() {
                const propertyType = document.getElementById('property_type');
                const houseTypeWrap = document.getElementById('house_type_wrap');
                const plotAddressFields = document.getElementById('plot_address_fields');
                const buildingFields = document.getElementById('building_fields');
                const plotFields = document.getElementById('plot_fields');
                const plotShape = document.getElementById('plot_shape');
                const regularDims = document.getElementById('plot_dimensions_regular');
                const irregularDims = document.getElementById('plot_dimensions_irregular');
                const landRegisterMissing = document.getElementById('land_register_missing');
                const landRegisterNo = document.getElementById('land_register_no');
                const price = document.getElementById('price');
                const area = document.getElementById('area');
                const pricePerM2 = document.getElementById('price_per_m2');

                function updatePricePerM2() {
                    const priceValue = parseFloat(price?.value || '');
                    const areaValue = parseFloat(area?.value || '');
                    if (!isNaN(priceValue) && !isNaN(areaValue) && areaValue > 0) {
                        pricePerM2.value = (priceValue / areaValue).toFixed(2);
                    } else {
                        pricePerM2.value = '';
                    }
                }

                function togglePropertySections() {
                    const value = propertyType?.value;
                    const isPlot = value === 'dzialka';
                    const isHouse = value === 'dom';

                    if (houseTypeWrap) houseTypeWrap.style.display = isHouse ? 'block' : 'none';
                    if (plotAddressFields) plotAddressFields.style.display = (isHouse || isPlot) ? 'block' : 'none';
                    if (plotFields) plotFields.style.display = isPlot ? 'block' : 'none';
                    if (buildingFields) buildingFields.style.display = isPlot ? 'none' : 'block';
                }

                function togglePlotShape() {
                    const shape = plotShape?.value;
                    if (regularDims) regularDims.style.display = shape === 'regularny' ? 'block' : 'none';
                    if (irregularDims) irregularDims.style.display = shape === 'nieregularny' ? 'block' : 'none';
                }

                function toggleLandRegister() {
                    if (!landRegisterNo || !landRegisterMissing) return;
                    landRegisterNo.disabled = landRegisterMissing.checked;
                    if (landRegisterMissing.checked) landRegisterNo.value = '';
                }

                propertyType?.addEventListener('change', togglePropertySections);
                plotShape?.addEventListener('change', togglePlotShape);
                landRegisterMissing?.addEventListener('change', toggleLandRegister);
                price?.addEventListener('input', updatePricePerM2);
                area?.addEventListener('input', updatePricePerM2);

                togglePropertySections();
                togglePlotShape();
                toggleLandRegister();
                updatePricePerM2();
            })();
        </script>
        <?php
    }

    private function get_agent_display_name( $user_id ) {
        $user = get_user_by( 'id', (int) $user_id );

        if ( ! $user ) {
            return '—';
        }

        $display_name = trim( $user->display_name );
        if ( '' !== $display_name ) {
            return $display_name;
        }

        return $user->user_login;
    }

    private function render_searches_view() {
        global $wpdb;

        $searches = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}eo_searches ORDER BY created_at DESC LIMIT 50" );
        ?>
        <div class="estateoffice-crm__section">
            <h3>Dodaj poszukiwanie</h3>
            <form method="post">
                <?php wp_nonce_field( 'estateoffice_create_search', 'estateoffice_search_nonce' ); ?>
                <input type="hidden" name="estateoffice_action" value="create_search">

                <label for="transaction_type">Typ transakcji</label>
                <select id="transaction_type" name="transaction_type" required>
                    <option value="">Wybierz</option>
                    <option value="sprzedaz">SPRZEDAŻ</option>
                    <option value="kupno">KUPNO</option>
                    <option value="wynajem">WYNAJEM</option>
                    <option value="najem">NAJEM</option>
                </select>

                <label for="property_type">Rodzaj nieruchomości</label>
                <select id="property_type" name="property_type">
                    <option value="">Wybierz</option>
                    <option value="mieszkanie">MIESZKANIE</option>
                    <option value="dom">DOM</option>
                    <option value="dzialka">DZIAŁKA</option>
                    <option value="lokal_hu">LOKAL H/U</option>
                </select>

                <fieldset>
                    <legend>Budżet</legend>
                    <label for="budget_min">Od</label>
                    <input id="budget_min" name="budget_min" type="number" step="0.01">
                    <label for="budget_max">Do</label>
                    <input id="budget_max" name="budget_max" type="number" step="0.01">
                </fieldset>

                <label for="location">Lokalizacja</label>
                <input id="location" name="location" type="text">

                <button type="submit">Dodaj poszukiwanie</button>
            </form>
        </div>

        <div class="estateoffice-crm__section">
            <h3>Lista poszukiwań</h3>
            <?php if ( empty( $searches ) ) : ?>
                <p>Brak poszukiwań.</p>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Rodzaj nieruchomości</th>
                            <th>Budżet</th>
                            <th>Lokalizacja</th>
                            <th>Typ transakcji</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $searches as $search ) : ?>
                            <tr>
                                <td><?php echo esc_html( strtoupper( $search->property_type ) ); ?></td>
                                <td><?php echo esc_html( trim( sprintf( '%s - %s', $search->budget_min, $search->budget_max ) ) ); ?></td>
                                <td><?php echo esc_html( $search->location ); ?></td>
                                <td><?php echo esc_html( strtoupper( $search->transaction_type ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_admin_section( $title, $description ) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <p><?php echo esc_html( $description ); ?></p>
        </div>
        <?php
    }

    public function render_admin_dashboard() {
        $this->render_admin_section(
            'EstateOffice CRM',
            'Panel administracyjny wtyczki. Sekcje będą rozwijane zgodnie z harmonogramem wersji 0.2 → 1.0.'
        );
    }

    public function render_admin_license() {
        $this->render_admin_section(
            'Licencja',
            'Moduł licencji zostanie dodany na końcowym etapie prac.'
        );
    }

    public function render_admin_agents() {
        $this->render_admin_section(
            'Agenci',
            'Zarządzanie agentami będzie dostępne w kolejnych wersjach.'
        );
    }

    public function render_admin_settings() {
        $this->render_admin_section(
            'Ustawienia',
            'Konfiguracja API Map Google, znaków wodnych i pól CRM pojawi się w następnych etapach.'
        );
    }

    public function render_admin_about() {
        $this->render_admin_section(
            'About',
            'Opis wtyczki i roadmapa będą uzupełniane w dalszych wersjach.'
        );
    }
}

new EstateOffice_CRM_Plugin();
