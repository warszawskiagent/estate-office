<?php
/**
 * Plugin Name: EstateOffice CRM
 * Description: CRM dla biur nieruchomości z własnymi bazami danych i formularzami.
 * Version: 0.3.0
 * Author: EstateOffice
 * Text Domain: estateoffice
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EstateOffice_CRM_Plugin {
    const VERSION = '0.3.0';
    const OPTION_PAGES = 'estateoffice_crm_pages';

    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
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
