<?php
/**
 * Admin bootstrap.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ESTATE_OFFICE_PATH . 'includes/class-estateoffice-admin-page.php';
require_once ESTATE_OFFICE_PATH . 'includes/Admin/class-estateoffice-admin-dashboard.php';
require_once ESTATE_OFFICE_PATH . 'includes/Admin/class-estateoffice-admin-contracts.php';
require_once ESTATE_OFFICE_PATH . 'includes/Admin/class-estateoffice-admin-clients.php';
require_once ESTATE_OFFICE_PATH . 'includes/Admin/class-estateoffice-admin-properties.php';
require_once ESTATE_OFFICE_PATH . 'includes/Admin/class-estateoffice-admin-searches.php';
require_once ESTATE_OFFICE_PATH . 'includes/Admin/class-estateoffice-admin-agents.php';
require_once ESTATE_OFFICE_PATH . 'includes/Admin/class-estateoffice-admin-settings.php';
require_once ESTATE_OFFICE_PATH . 'includes/Admin/class-estateoffice-admin-about.php';

class EstateOffice_Admin {

    /**
     * Slug for top-level menu.
     */
    public const MENU_SLUG = 'estate-office-crm';

    /**
     * Admin pages collection.
     *
     * @var EstateOffice_Admin_Page[]
     */
    protected $pages = [];

    /**
     * Register hooks used by admin.
     */
    public function hooks(): void {
        add_action( 'admin_menu', [ $this, 'register_pages' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_estate_office_save_agent', [ $this, 'handle_save_agent' ] );
        add_action( 'admin_post_estate_office_delete_agent', [ $this, 'handle_delete_agent' ] );
        add_action( 'admin_post_estate_office_save_settings', [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_estate_office_save_client', [ $this, 'handle_save_client' ] );
        add_action( 'admin_post_estate_office_delete_client', [ $this, 'handle_delete_client' ] );
        add_action( 'admin_post_estate_office_save_contract', [ $this, 'handle_save_contract' ] );
        add_action( 'admin_post_estate_office_delete_contract', [ $this, 'handle_delete_contract' ] );
        add_action( 'admin_post_estate_office_save_property', [ $this, 'handle_save_property' ] );
        add_action( 'admin_post_estate_office_delete_property', [ $this, 'handle_delete_property' ] );
        add_action( 'admin_post_estate_office_save_search', [ $this, 'handle_save_search' ] );
        add_action( 'admin_post_estate_office_delete_search', [ $this, 'handle_delete_search' ] );
    }

    /**
     * Register menu pages.
     */
    public function register_pages(): void {
        EstateOffice_Activator::ensure_role_capabilities();

        $dashboard = new EstateOffice_Admin_Dashboard( self::MENU_SLUG );
        $this->maybe_allow_administrator_fallback( $dashboard );
        $dashboard->register();

        $this->pages['dashboard'] = $dashboard;

        $contracts = new EstateOffice_Admin_Contracts( self::MENU_SLUG );
        $this->maybe_allow_administrator_fallback( $contracts );
        $contracts->register();
        $this->pages['contracts'] = $contracts;

        $properties = new EstateOffice_Admin_Properties( self::MENU_SLUG );
        $this->maybe_allow_administrator_fallback( $properties );
        $properties->register();
        $this->pages['properties'] = $properties;

        $searches = new EstateOffice_Admin_Searches( self::MENU_SLUG );
        $this->maybe_allow_administrator_fallback( $searches );
        $searches->register();
        $this->pages['searches'] = $searches;

        $clients = new EstateOffice_Admin_Clients( self::MENU_SLUG );
        $this->maybe_allow_administrator_fallback( $clients );
        $clients->register();
        $this->pages['clients'] = $clients;

        $agents = new EstateOffice_Admin_Agents( self::MENU_SLUG );
        $this->maybe_allow_administrator_fallback( $agents );
        $agents->register();
        $this->pages['agents'] = $agents;

        $settings = new EstateOffice_Admin_Settings( self::MENU_SLUG );
        $this->maybe_allow_administrator_fallback( $settings );
        $settings->register();
        $this->pages['settings'] = $settings;

        $about = new EstateOffice_Admin_About( self::MENU_SLUG );
        $this->maybe_allow_administrator_fallback( $about );
        $about->register();
        $this->pages['about'] = $about;
    }

    /**
     * Ensure administrators without synced caps still see the menu.
     */
    protected function maybe_allow_administrator_fallback( EstateOffice_Admin_Page $page ): void {
        if ( current_user_can( $page->get_capability() ) ) {
            return;
        }

        if ( current_user_can( 'manage_options' ) ) {
            $page->set_capability( 'manage_options' );
        }
    }

    /**
     * Enqueue backend assets.
     */
    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, self::MENU_SLUG ) ) {
            return;
        }

        wp_enqueue_style( 'estate-office-admin', ESTATE_OFFICE_URL . 'assets/css/admin.css', [], ESTATE_OFFICE_VERSION );
        wp_enqueue_script( 'estate-office-admin', ESTATE_OFFICE_URL . 'assets/js/admin.js', [ 'jquery', 'wp-util' ], ESTATE_OFFICE_VERSION, true );
        wp_localize_script(
            'estate-office-admin',
            'EstateOfficeData',
            [
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'estate_office_ajax' ),
                'stages'          => EstateOffice_Admin_Contracts::get_stages(),
                'propertyFields'  => EstateOffice_Admin_Settings::get_dynamic_fields( 'property' ),
                'contractFields'  => EstateOffice_Admin_Settings::get_dynamic_fields( 'contract' ),
                'clientFields'    => EstateOffice_Admin_Settings::get_dynamic_fields( 'client' ),
            ]
        );
    }

    /**
     * Persist agent.
     */
    public function handle_save_agent(): void {
        if ( ! current_user_can( 'eo_manage_agents' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu agenta.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_agent' );

        $post     = wp_unslash( $_POST );

        $agent_id = isset( $post['agent_id'] ) ? absint( $post['agent_id'] ) : 0;
        $data     = [
            'first_name'   => sanitize_text_field( $post['first_name'] ?? '' ),
            'last_name'    => sanitize_text_field( $post['last_name'] ?? '' ),
            'phone'        => sanitize_text_field( $post['phone'] ?? '' ),
            'email'        => sanitize_email( $post['email'] ?? '' ),
            'description'  => wp_kses_post( $post['description'] ?? '' ),
            'contact_data' => $this->prepare_json( $post['contact_data'] ?? [] ),
            'photo_id'     => isset( $post['photo_id'] ) ? absint( $post['photo_id'] ) : 0,
        ];

        $result = $this->save_table_record( 'eo_agents', $data, $agent_id );

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Agents::SLUG,
                'status' => $result ? 'saved' : 'error',
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Remove agent.
     */
    public function handle_delete_agent(): void {
        if ( ! current_user_can( 'eo_manage_agents' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do usunięcia agenta.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_delete_agent' );
        $agent_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        if ( $agent_id > 0 ) {
            $this->delete_table_record( 'eo_agents', $agent_id );
        }

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Agents::SLUG,
                'status' => 'deleted',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Persist global settings.
     */
    public function handle_save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu ustawień.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_settings' );

        $post = wp_unslash( $_POST );

        $options = [
            'estate_office_google_maps_api_key' => sanitize_text_field( $post['google_maps_api_key'] ?? '' ),
            'estate_office_watermark_attachment' => isset( $post['watermark_attachment'] ) ? absint( $post['watermark_attachment'] ) : 0,
            'estate_office_office_logo_attachment' => isset( $post['office_logo_attachment'] ) ? absint( $post['office_logo_attachment'] ) : 0,
        ];

        foreach ( $options as $name => $value ) {
            update_option( $name, $value );
        }

        $field_groups = [
            'property' => $post['property_custom_fields'] ?? [],
            'contract' => $post['contract_custom_fields'] ?? [],
            'client'   => $post['client_custom_fields'] ?? [],
        ];

        foreach ( $field_groups as $group => $fields ) {
            EstateOffice_Admin_Settings::save_dynamic_fields( $group, $fields );
        }

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Settings::SLUG,
                'status' => 'saved',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Persist client.
     */
    public function handle_save_client(): void {
        if ( ! current_user_can( 'eo_manage_clients' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu klienta.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_client' );

        $post = wp_unslash( $_POST );

        $client_id   = isset( $post['client_id'] ) ? absint( $post['client_id'] ) : 0;
        $client_type = isset( $post['client_type'] ) && 'company' === $post['client_type'] ? 'company' : 'individual';
        $identification = [
            'pesel'          => sanitize_text_field( $post['pesel'] ?? '' ),
            'document_type'  => sanitize_text_field( $post['document_type'] ?? '' ),
            'document_no'    => sanitize_text_field( $post['document_number'] ?? '' ),
            'nip'            => sanitize_text_field( $post['nip'] ?? '' ),
            'krs'            => sanitize_text_field( $post['krs'] ?? '' ),
            'regon'          => sanitize_text_field( $post['regon'] ?? '' ),
        ];

        $data = [
            'client_type'          => $client_type,
            'first_name'           => sanitize_text_field( $post['first_name'] ?? '' ),
            'last_name'            => sanitize_text_field( $post['last_name'] ?? '' ),
            'company_name'         => sanitize_text_field( $post['company_name'] ?? '' ),
            'representative_name'  => sanitize_text_field( $post['representative_name'] ?? '' ),
            'phone'                => sanitize_text_field( $post['phone'] ?? '' ),
            'email'                => sanitize_email( $post['email'] ?? '' ),
            'website'              => esc_url_raw( $post['website'] ?? '' ),
            'identification'       => wp_json_encode( $identification ),
            'address'              => $this->prepare_json( $post['address'] ?? [] ),
            'correspondence_address' => $this->prepare_json( $post['correspondence_address'] ?? [] ),
        ];

        $dynamic = EstateOffice_Admin_Settings::filter_dynamic_submission( 'client', $post['custom_fields'] ?? [] );
        if ( ! empty( $dynamic ) ) {
            $data['custom_data'] = wp_json_encode( $dynamic );
        }

        $result = $this->save_table_record( 'eo_clients', $data, $client_id );

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Clients::SLUG,
                'status' => $result ? 'saved' : 'error',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Delete client.
     */
    public function handle_delete_client(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Tylko administrator może usuwać klientów.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_delete_client' );
        $client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
        if ( $client_id > 0 ) {
            $this->delete_table_record( 'eo_clients', $client_id );
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . 'eo_contract_clients', [ 'client_id' => $client_id ], [ '%d' ] );
        }

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Clients::SLUG,
                'status' => 'deleted',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Persist contract with related entities.
     */
    public function handle_save_contract(): void {
        if ( ! current_user_can( 'eo_manage_contracts' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu umowy.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_contract' );

        global $wpdb;

        $post         = wp_unslash( $_POST );
        if ( isset( $post['stage_history_json'] ) ) {
            $history = json_decode( $post['stage_history_json'], true );
            if ( is_array( $history ) ) {
                $post['stage_history'] = $history;
            }
        }
        $contract_id = isset( $post['contract_id'] ) ? absint( $post['contract_id'] ) : 0;

        $contract_number = sanitize_text_field( $post['contract_number'] ?? '' );
        if ( empty( $contract_number ) ) {
            wp_die( esc_html__( 'Numer umowy jest wymagany.', 'estate-office' ) );
        }

        $transaction_type  = sanitize_text_field( $post['transaction_type'] ?? 'SPRZEDAŻ' );
        $start_date        = sanitize_text_field( $post['start_date'] ?? '' );
        $end_date          = sanitize_text_field( $post['end_date'] ?? '' );
        $indefinite        = isset( $post['indefinite'] ) ? 1 : 0;
        $commission_amount = isset( $post['commission_amount'] ) ? floatval( $post['commission_amount'] ) : null;
        $commission_unit   = sanitize_text_field( $post['commission_unit'] ?? '' );
        $stage             = sanitize_text_field( $post['stage'] ?? 'umowa_posrednictwa' );

        $contract_data = [
            'contract_number'   => $contract_number,
            'transaction_type'  => $transaction_type,
            'start_date'        => $start_date,
            'end_date'          => $indefinite ? null : $end_date,
            'indefinite'        => $indefinite,
            'commission_amount' => $commission_amount,
            'commission_unit'   => $commission_unit,
            'stage'             => $stage,
            'stage_history'     => $this->prepare_json( $post['stage_history'] ?? [] ),
        ];

        $result = $this->save_table_record( 'eo_contracts', $contract_data, $contract_id );

        if ( ! $result ) {
            $redirect = add_query_arg(
                [ 'page' => EstateOffice_Admin_Contracts::SLUG, 'status' => 'error' ],
                admin_url( 'admin.php' )
            );
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( ! $contract_id ) {
            $contract_id = $result;
        }

        $client_ids = array_map( 'absint', (array) ( $post['contract_clients'] ?? [] ) );
        $wpdb->delete( $wpdb->prefix . 'eo_contract_clients', [ 'contract_id' => $contract_id ], [ '%d' ] );
        foreach ( $client_ids as $client_id ) {
            if ( $client_id > 0 ) {
                $wpdb->insert(
                    $wpdb->prefix . 'eo_contract_clients',
                    [
                        'contract_id' => $contract_id,
                        'client_id'   => $client_id,
                    ],
                    [ '%d', '%d' ]
                );
            }
        }

        if ( isset( $post['property'] ) && ! empty( $post['property']['property_type'] ?? '' ) ) {
            $property_data = $post['property'];
            $property_data['contract_id']      = $contract_id;
            $property_data['transaction_type'] = $transaction_type;
            $this->persist_property_from_contract( $property_data );
        }

        if ( isset( $post['search'] ) && ! empty( $post['search']['transaction_type'] ?? '' ) ) {
            $search_data = $post['search'];
            $search_data['contract_id'] = $contract_id;
            $search_data['transaction_type'] = $transaction_type;
            $this->persist_search_from_contract( $search_data );
        }

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Contracts::SLUG,
                'status' => 'saved',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Remove contract.
     */
    public function handle_delete_contract(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Tylko administrator może usuwać umowy.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_delete_contract' );
        $contract_id = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
        if ( $contract_id ) {
            $this->delete_table_record( 'eo_contracts', $contract_id );
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . 'eo_contract_clients', [ 'contract_id' => $contract_id ], [ '%d' ] );
            $wpdb->delete( $wpdb->prefix . 'eo_properties', [ 'contract_id' => $contract_id ], [ '%d' ] );
            $wpdb->delete( $wpdb->prefix . 'eo_searches', [ 'contract_id' => $contract_id ], [ '%d' ] );
        }

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Contracts::SLUG,
                'status' => 'deleted',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Standalone property saving.
     */
    public function handle_save_property(): void {
        if ( ! current_user_can( 'eo_manage_properties' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu nieruchomości.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_property' );
        $post = wp_unslash( $_POST );
        $this->persist_property_from_contract( $post );

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Properties::SLUG,
                'status' => 'saved',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Delete property.
     */
    public function handle_delete_property(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Tylko administrator może usuwać nieruchomości.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_delete_property' );
        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        if ( $property_id ) {
            $this->delete_table_record( 'eo_properties', $property_id );
        }
        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Properties::SLUG,
                'status' => 'deleted',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Persist search.
     */
    public function handle_save_search(): void {
        if ( ! current_user_can( 'eo_manage_searches' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu poszukiwania.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_search' );
        $post = wp_unslash( $_POST );
        $this->persist_search_from_contract( $post );

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Searches::SLUG,
                'status' => 'saved',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Delete search.
     */
    public function handle_delete_search(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Tylko administrator może usuwać poszukiwania.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_delete_search' );
        $search_id = isset( $_POST['search_id'] ) ? absint( $_POST['search_id'] ) : 0;
        if ( $search_id ) {
            $this->delete_table_record( 'eo_searches', $search_id );
        }
        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Searches::SLUG,
                'status' => 'deleted',
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Persist property from posted array.
     */
    protected function persist_property_from_contract( array $data ) {
        $property_id = isset( $data['property_id'] ) ? absint( $data['property_id'] ) : 0;
        $property_type = sanitize_text_field( $data['property_type'] ?? '' );
        if ( empty( $property_type ) ) {
            return false;
        }

        $address = $this->sanitize_recursive( $data['address'] ?? [] );
        $legal   = $this->sanitize_recursive( $data['legal'] ?? [] );
        $details = $this->sanitize_recursive( $data['details'] ?? [] );
        $tags    = $this->sanitize_recursive( $data['tags'] ?? [] );

        if ( isset( $details['price'] ) && isset( $details['area'] ) ) {
            $price = (float) $details['price'];
            $area  = (float) $details['area'];
            if ( $price > 0 && $area > 0 ) {
                $details['price_m2'] = round( $price / $area, 2 );
            }
        }

        $dynamic = EstateOffice_Admin_Settings::filter_dynamic_submission( 'property', $data['custom_fields'] ?? [] );
        if ( ! empty( $dynamic ) ) {
            $details = array_merge( $details, $dynamic );
        }

        $record = [
            'contract_id'      => isset( $data['contract_id'] ) ? absint( $data['contract_id'] ) : null,
            'transaction_type' => sanitize_text_field( $data['transaction_type'] ?? '' ),
            'property_type'    => $property_type,
            'address'          => ! empty( $address ) ? wp_json_encode( $address ) : null,
            'legal'            => ! empty( $legal ) ? wp_json_encode( $legal ) : null,
            'details'          => ! empty( $details ) ? wp_json_encode( $details ) : null,
            'description'      => wp_kses_post( $data['description'] ?? '' ),
            'tags'             => ! empty( $tags ) ? wp_json_encode( $tags ) : null,
            'export_www'       => ! empty( $data['export_www'] ) ? 1 : 0,
            'export_portals'   => ! empty( $data['export_portals'] ) ? 1 : 0,
        ];

        return $this->save_table_record( 'eo_properties', $record, $property_id );
    }

    /**
     * Persist search.
     */
    protected function persist_search_from_contract( array $data ) {
        $search_id = isset( $data['search_id'] ) ? absint( $data['search_id'] ) : 0;
        $transaction_type = sanitize_text_field( $data['transaction_type'] ?? '' );
        if ( empty( $transaction_type ) ) {
            return false;
        }

        $criteria = $this->sanitize_recursive( $data['criteria'] ?? [] );

        $dynamic = EstateOffice_Admin_Settings::filter_dynamic_submission( 'contract', $data['custom_fields'] ?? [] );
        if ( ! empty( $dynamic ) ) {
            $criteria = array_merge( $criteria, $dynamic );
        }

        $record = [
            'contract_id'      => isset( $data['contract_id'] ) ? absint( $data['contract_id'] ) : null,
            'transaction_type' => $transaction_type,
            'criteria'         => ! empty( $criteria ) ? wp_json_encode( $criteria ) : null,
            'description'      => wp_kses_post( $data['description'] ?? '' ),
        ];

        return $this->save_table_record( 'eo_searches', $record, $search_id );
    }

    /**
     * Save or update record for given table.
     */
    protected function save_table_record( string $table, array $data, int $id = 0 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        $now        = current_time( 'mysql' );

        if ( $id > 0 ) {
            $data['updated_at'] = $now;
            $result             = $wpdb->update( $table_name, $data, [ 'id' => $id ] );
            return false === $result ? false : $id;
        }

        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $wpdb->insert( $table_name, $data );
        if ( $wpdb->insert_id ) {
            return (int) $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Delete row by id.
     */
    protected function delete_table_record( string $table, int $id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . $table, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Prepare json encoded value from array.
     */
    protected function prepare_json( $value ): ?string {
        if ( empty( $value ) ) {
            return null;
        }
        if ( is_string( $value ) ) {
            return $value;
        }
        return wp_json_encode( $this->sanitize_recursive( $value ) );
    }

    /**
     * Merge existing JSON data with new values.
     */
    protected function merge_json_fields( ?string $json, array $additional ): string {
        $decoded = [];
        if ( $json ) {
            $decoded = json_decode( $json, true );
            if ( ! is_array( $decoded ) ) {
                $decoded = [];
            }
        }
        $decoded = array_merge( $decoded, $this->sanitize_recursive( $additional ) );
        return wp_json_encode( $decoded );
    }

    /**
     * Sanitize recursively.
     */
    protected function sanitize_recursive( $value ) {
        if ( is_array( $value ) ) {
            $sanitized = [];
            foreach ( $value as $key => $item ) {
                if ( is_int( $key ) ) {
                    $sanitized[ $key ] = $this->sanitize_recursive( $item );
                } else {
                    $sanitized[ sanitize_key( $key ) ] = $this->sanitize_recursive( $item );
                }
            }
            return $sanitized;
        }

        if ( is_scalar( $value ) ) {
            return sanitize_text_field( wp_unslash( (string) $value ) );
        }

        return $value;
    }
}
