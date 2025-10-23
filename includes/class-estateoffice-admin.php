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
require_once ESTATE_OFFICE_PATH . 'includes/Admin/class-estateoffice-admin-offers.php';
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
        add_action( 'admin_post_estate_office_sync_offer', [ $this, 'handle_sync_offer' ] );
        add_action( 'admin_post_estate_office_sync_offers', [ $this, 'handle_sync_offers' ] );
    }

    /**
     * Register menu pages.
     */
    public function register_pages(): void {
        EstateOffice_Activator::ensure_role_capabilities();

        $dashboard = new EstateOffice_Admin_Dashboard();
        $this->maybe_allow_administrator_fallback( $dashboard );
        $dashboard->register();

        $this->pages['dashboard'] = $dashboard;

        $parent_slug = $dashboard->get_slug();

        $contracts = new EstateOffice_Admin_Contracts( $parent_slug );
        $this->maybe_allow_administrator_fallback( $contracts );
        $contracts->register();
        $this->pages['contracts'] = $contracts;

        $properties = new EstateOffice_Admin_Properties( $parent_slug );
        $this->maybe_allow_administrator_fallback( $properties );
        $properties->register();
        $this->pages['properties'] = $properties;

        $offers = new EstateOffice_Admin_Offers( $parent_slug );
        $this->maybe_allow_administrator_fallback( $offers );
        $offers->register();
        $this->pages['offers'] = $offers;

        $searches = new EstateOffice_Admin_Searches( $parent_slug );
        $this->maybe_allow_administrator_fallback( $searches );
        $searches->register();
        $this->pages['searches'] = $searches;

        $clients = new EstateOffice_Admin_Clients( $parent_slug );
        $this->maybe_allow_administrator_fallback( $clients );
        $clients->register();
        $this->pages['clients'] = $clients;

        $agents = new EstateOffice_Admin_Agents( $parent_slug );
        $this->maybe_allow_administrator_fallback( $agents );
        $agents->register();
        $this->pages['agents'] = $agents;

        $settings = new EstateOffice_Admin_Settings( $parent_slug );
        $this->maybe_allow_administrator_fallback( $settings );
        $settings->register();
        $this->pages['settings'] = $settings;

        $about = new EstateOffice_Admin_About( $parent_slug );
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

        wp_enqueue_media();
        wp_enqueue_style( 'estate-office-admin', ESTATE_OFFICE_URL . 'assets/css/admin.css', [], ESTATE_OFFICE_VERSION );

        $script_deps = [ 'jquery', 'wp-util' ];
        $maps_key    = get_option( 'estate_office_google_maps_api_key', '' );
        if ( $maps_key ) {
            wp_enqueue_script( 'estate-office-google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $maps_key ), [], null, true );
            $script_deps[] = 'estate-office-google-maps';
        }

        wp_enqueue_script( 'estate-office-admin', ESTATE_OFFICE_URL . 'assets/js/admin.js', $script_deps, ESTATE_OFFICE_VERSION, true );
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
                'mediaTitle'      => __( 'Wybierz plik', 'estate-office' ),
                'mediaButton'     => __( 'Użyj pliku', 'estate-office' ),
                'galleryTitle'    => __( 'Wybierz zdjęcia', 'estate-office' ),
                'galleryButton'   => __( 'Dodaj zdjęcia', 'estate-office' ),
                'removeImage'     => __( 'Usuń', 'estate-office' ),
                'clientsRequired' => __( 'Dodaj co najmniej jednego klienta do umowy.', 'estate-office' ),
                'removeClientLabel' => __( 'Usuń klienta %s', 'estate-office' ),
                'emptyStages'     => __( 'Brak historii etapów.', 'estate-office' ),
                'stageGuard'      => __( 'Nie możesz usunąć ostatniego etapu umowy.', 'estate-office' ),
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
        $data['slug'] = estate_office_generate_agent_slug( (string) ( $post['slug'] ?? '' ), $data['first_name'], $data['last_name'], $agent_id );

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

        $base_input = sanitize_title( $post['agent_slug_base'] ?? '' );
        if ( '' === $base_input ) {
            $base_input = 'agenci';
        }

        $old_base = get_option( 'estate_office_agent_slug_base', 'agenci' );

        $options = [
            'estate_office_google_maps_api_key' => sanitize_text_field( $post['google_maps_api_key'] ?? '' ),
            'estate_office_watermark_attachment' => isset( $post['watermark_attachment'] ) ? absint( $post['watermark_attachment'] ) : 0,
            'estate_office_office_logo_attachment' => isset( $post['office_logo_attachment'] ) ? absint( $post['office_logo_attachment'] ) : 0,
            'estate_office_agent_slug_base'      => $base_input,
        ];

        foreach ( $options as $name => $value ) {
            update_option( $name, $value );
        }

        if ( $old_base !== $base_input ) {
            update_option( 'estate_office_flush_rewrite', 1 );
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
        $agent_id    = isset( $post['agent_id'] ) ? absint( $post['agent_id'] ) : 0;
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
            'agent_id'             => $agent_id ?: null,
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
     * Create client from inline contract submission.
     */
    protected function persist_inline_client( array $data, int $agent_id = 0 ): int {
        $client_type = isset( $data['client_type'] ) && 'company' === $data['client_type'] ? 'company' : 'individual';

        $agent_id = absint( $agent_id );

        $first_name     = sanitize_text_field( $data['first_name'] ?? '' );
        $last_name      = sanitize_text_field( $data['last_name'] ?? '' );
        $company_name   = sanitize_text_field( $data['company_name'] ?? '' );
        $representative = sanitize_text_field( $data['representative_name'] ?? '' );
        $phone          = sanitize_text_field( $data['phone'] ?? '' );
        $email          = sanitize_email( $data['email'] ?? '' );
        $website        = esc_url_raw( $data['website'] ?? '' );

        if ( 'individual' === $client_type ) {
            if ( empty( $first_name ) || empty( $last_name ) ) {
                return 0;
            }
        } else {
            if ( empty( $company_name ) ) {
                return 0;
            }
        }

        if ( empty( $phone ) ) {
            return 0;
        }

        $address = $this->sanitize_recursive( $data['address'] ?? [] );
        if ( empty( $address['street'] ) || empty( $address['number'] ) || empty( $address['postal_code'] ) || empty( $address['city'] ) ) {
            return 0;
        }

        $correspondence_raw = $data['correspondence'] ?? [];
        $same_correspondence = ! empty( $correspondence_raw['same'] );
        if ( isset( $correspondence_raw['same'] ) ) {
            unset( $correspondence_raw['same'] );
        }
        $correspondence = $same_correspondence ? $address : $this->sanitize_recursive( $correspondence_raw );

        $identification_input = $data['identification'] ?? [];
        $identification       = [
            'pesel'         => sanitize_text_field( $identification_input['pesel'] ?? '' ),
            'document_type' => sanitize_text_field( $identification_input['document_type'] ?? '' ),
            'document_no'   => sanitize_text_field( $identification_input['document_no'] ?? '' ),
            'nip'           => sanitize_text_field( $identification_input['nip'] ?? '' ),
            'krs'           => sanitize_text_field( $identification_input['krs'] ?? '' ),
            'regon'         => sanitize_text_field( $identification_input['regon'] ?? '' ),
        ];

        $client_data = [
            'client_type'            => $client_type,
            'first_name'             => $first_name,
            'last_name'              => $last_name,
            'company_name'           => $company_name,
            'representative_name'    => $representative,
            'phone'                  => $phone,
            'email'                  => $email,
            'website'                => $website,
            'identification'         => wp_json_encode( $identification ),
            'address'                => $this->prepare_json( $address ),
            'correspondence_address' => $this->prepare_json( $correspondence ),
            'agent_id'               => $agent_id ?: null,
        ];

        $result = $this->save_table_record( 'eo_clients', $client_data );
        return $result ? (int) $result : 0;
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
        $existing_contract = $contract_id ? EstateOffice_Admin_Contracts::get_contract( $contract_id ) : null;

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
        $agent_id          = isset( $post['agent_id'] ) ? absint( $post['agent_id'] ) : 0;

        $stage_history = $this->normalize_stage_history( $post['stage_history'] ?? [], $stage, $start_date, $existing_contract );
        $post['stage_history'] = $stage_history;

        $table_name = $wpdb->prefix . 'eo_contracts';
        $duplicate_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE contract_number = %s AND id <> %d",
                $contract_number,
                $contract_id
            )
        );

        if ( $duplicate_id ) {
            $redirect = wp_get_referer();
            if ( ! $redirect ) {
                $args = [
                    'page' => EstateOffice_Admin_Contracts::SLUG,
                ];
                if ( $contract_id ) {
                    $args['action']   = 'edit';
                    $args['contract'] = $contract_id;
                } else {
                    $args['action'] = 'add';
                }
                $redirect = add_query_arg( $args, admin_url( 'admin.php' ) );
            }

            $redirect = remove_query_arg( [ 'status', 'duplicate_number' ], $redirect );
            $redirect = add_query_arg(
                [
                    'status'           => 'duplicate',
                    'duplicate_number' => $contract_number,
                ],
                $redirect
            );

            wp_safe_redirect( $redirect );
            exit;
        }

        $contract_data = [
            'contract_number'   => $contract_number,
            'transaction_type'  => $transaction_type,
            'start_date'        => $start_date,
            'end_date'          => $indefinite ? null : $end_date,
            'indefinite'        => $indefinite,
            'commission_amount' => $commission_amount,
            'commission_unit'   => $commission_unit,
            'stage'             => $stage,
            'stage_history'     => $this->prepare_json( $stage_history ),
            'agent_id'          => $agent_id ?: null,
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
        $new_client_ids = [];
        if ( isset( $post['new_clients'] ) && is_array( $post['new_clients'] ) ) {
            foreach ( $post['new_clients'] as $client_payload ) {
                if ( ! is_array( $client_payload ) ) {
                    continue;
                }
                $created_id = $this->persist_inline_client( $client_payload, $agent_id );
                if ( $created_id ) {
                    $new_client_ids[] = $created_id;
                }
            }
        }
        $client_ids = array_filter( array_unique( array_merge( $client_ids, $new_client_ids ) ) );
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
            if ( empty( $property_data['agent_id'] ) ) {
                $property_data['agent_id'] = $agent_id;
            }
            $this->persist_property_from_contract( $property_data );
        }

        if ( isset( $post['search'] ) && ! empty( $post['search']['transaction_type'] ?? '' ) ) {
            $search_data = $post['search'];
            $search_data['contract_id'] = $contract_id;
            $search_data['transaction_type'] = $transaction_type;
            if ( empty( $search_data['agent_id'] ) ) {
                $search_data['agent_id'] = $agent_id;
            }
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
            $property = EstateOffice_Admin_Properties::get_property( $property_id );
            if ( $property ) {
                estate_office_sync_property_page( $property_id, false, $property );
            }
            $this->delete_table_record( 'eo_properties', $property_id );
            $this->delete_property_media( $property_id );
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
     * Manually synchronize a single exported offer page.
     */
    public function handle_sync_offer(): void {
        if ( ! current_user_can( 'eo_manage_properties' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do synchronizacji ofert.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_sync_offer' );
        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $status      = 'error';

        if ( $property_id ) {
            $property = EstateOffice_Admin_Properties::get_property( $property_id );
            if ( $property && ! empty( $property->export_www ) ) {
                estate_office_sync_property_page( $property_id, true, $property );
                $status = 'synced';
            }
        }

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Offers::SLUG,
                'status' => $status,
            ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Synchronize all exported offers.
     */
    public function handle_sync_offers(): void {
        if ( ! current_user_can( 'eo_manage_properties' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do synchronizacji ofert.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_sync_offers' );

        $offers = EstateOffice_Admin_Offers::get_exported_properties();
        foreach ( $offers as $offer ) {
            estate_office_sync_property_page( (int) $offer->id, true, $offer );
        }

        $redirect = add_query_arg(
            [
                'page'   => EstateOffice_Admin_Offers::SLUG,
                'status' => 'synced',
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

        $existing_tags = [];
        if ( $property_id ) {
            $existing = EstateOffice_Admin_Properties::get_property( $property_id );
            if ( $existing && $existing->tags ) {
                $decoded = json_decode( $existing->tags, true );
                if ( is_array( $decoded ) ) {
                    $existing_tags = $decoded;
                }
            }
        }
        $tags = $this->normalize_property_tags( $tags, $existing_tags );

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

        $media_input = $data['media'] ?? [];
        $gallery     = [];
        if ( isset( $media_input['gallery'] ) && is_array( $media_input['gallery'] ) ) {
            foreach ( $media_input['gallery'] as $item ) {
                $id = absint( $item );
                if ( $id ) {
                    $gallery[] = $id;
                }
            }
        }
        $gallery_items = $this->prepare_gallery_media_entries( $gallery );
        $floor_2d    = isset( $media_input['floor_2d'] ) ? absint( $media_input['floor_2d'] ) : 0;
        $floor_3d    = isset( $media_input['floor_3d'] ) ? absint( $media_input['floor_3d'] ) : 0;
        $video_url   = isset( $media_input['video'] ) ? esc_url_raw( $media_input['video'] ) : '';
        $virtual_url = isset( $media_input['virtual'] ) ? esc_url_raw( $media_input['virtual'] ) : '';

        $contract_id      = isset( $data['contract_id'] ) ? absint( $data['contract_id'] ) : 0;
        $agent_id         = isset( $data['agent_id'] ) ? absint( $data['agent_id'] ) : 0;
        $transaction_type = '';
        if ( $contract_id ) {
            $contract = EstateOffice_Admin_Contracts::get_contract( $contract_id );
            if ( $contract ) {
                $transaction_type = $contract->transaction_type;
                if ( ! $agent_id && ! empty( $contract->agent_id ) ) {
                    $agent_id = (int) $contract->agent_id;
                }
            }
        }
        if ( empty( $transaction_type ) ) {
            $transaction_type = sanitize_text_field( $data['transaction_type'] ?? '' );
        }
        if ( empty( $transaction_type ) ) {
            return false;
        }

        $record = [
            'contract_id'      => $contract_id ?: null,
            'transaction_type' => $transaction_type,
            'property_type'    => $property_type,
            'address'          => ! empty( $address ) ? wp_json_encode( $address ) : null,
            'legal'            => ! empty( $legal ) ? wp_json_encode( $legal ) : null,
            'details'          => ! empty( $details ) ? wp_json_encode( $details ) : null,
            'description'      => wp_kses_post( $data['description'] ?? '' ),
            'tags'             => ! empty( $tags ) ? wp_json_encode( $tags ) : null,
            'export_www'       => ! empty( $data['export_www'] ) ? 1 : 0,
            'export_portals'   => ! empty( $data['export_portals'] ) ? 1 : 0,
            'agent_id'         => $agent_id ?: null,
        ];

        $saved_id = $this->save_table_record( 'eo_properties', $record, $property_id );
        if ( $saved_id ) {
            $this->sync_property_media(
                $saved_id,
                [
                    'gallery' => $gallery_items,
                    'floor_2d' => $floor_2d,
                    'floor_3d' => $floor_3d,
                    'video'    => $video_url,
                    'virtual'  => $virtual_url,
                ]
            );

            $stored_property = EstateOffice_Admin_Properties::get_property( $saved_id );
            if ( $stored_property ) {
                estate_office_sync_property_page( $saved_id, ! empty( $record['export_www'] ), $stored_property );
            }
        }

        return $saved_id;
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

        $contract_id      = isset( $data['contract_id'] ) ? absint( $data['contract_id'] ) : 0;
        $agent_id         = isset( $data['agent_id'] ) ? absint( $data['agent_id'] ) : 0;
        if ( $contract_id ) {
            $contract = EstateOffice_Admin_Contracts::get_contract( $contract_id );
            if ( $contract ) {
                $transaction_type = $contract->transaction_type;
                if ( ! $agent_id && ! empty( $contract->agent_id ) ) {
                    $agent_id = (int) $contract->agent_id;
                }
            }
        }
        if ( empty( $transaction_type ) ) {
            return false;
        }

        $record = [
            'contract_id'      => $contract_id ?: null,
            'transaction_type' => $transaction_type,
            'criteria'         => ! empty( $criteria ) ? wp_json_encode( $criteria ) : null,
            'description'      => wp_kses_post( $data['description'] ?? '' ),
            'agent_id'         => $agent_id ?: null,
        ];

        return $this->save_table_record( 'eo_searches', $record, $search_id );
    }

    /**
     * Prepare gallery media entries including watermarked copies when configured.
     *
     * @param array<int,int> $attachment_ids Attachment identifiers.
     * @return array<int,array<string,int>>
     */
    protected function prepare_gallery_media_entries( array $attachment_ids ): array {
        if ( empty( $attachment_ids ) ) {
            return [];
        }

        $watermark_id = estate_office_get_watermark_attachment_id();
        $entries      = [];

        foreach ( $attachment_ids as $attachment_id ) {
            $attachment_id = absint( $attachment_id );
            if ( ! $attachment_id ) {
                continue;
            }

            $entry = [
                'attachment_id'  => $attachment_id,
                'watermarked_id' => 0,
            ];

            if ( $watermark_id ) {
                $watermarked = estate_office_ensure_watermarked_attachment( $attachment_id, $watermark_id );
                if ( $watermarked ) {
                    $entry['watermarked_id'] = $watermarked;
                }
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Synchronize property media attachments and links.
     */
    protected function sync_property_media( int $property_id, array $media ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_property_media';
        $wpdb->delete( $table, [ 'property_id' => $property_id ], [ '%d' ] );

        $now = current_time( 'mysql' );

        if ( ! empty( $media['gallery'] ) && is_array( $media['gallery'] ) ) {
            foreach ( $media['gallery'] as $gallery_item ) {
                $attachment_id  = absint( $gallery_item['attachment_id'] ?? 0 );
                $watermarked_id = absint( $gallery_item['watermarked_id'] ?? 0 );
                if ( ! $attachment_id ) {
                    continue;
                }
                $wpdb->insert(
                    $table,
                    [
                        'property_id'    => $property_id,
                        'media_type'     => 'gallery',
                        'attachment_id'  => $attachment_id,
                        'watermarked_id' => $watermarked_id ?: null,
                        'media_url'      => null,
                        'created_at'     => $now,
                    ]
                );
            }
        }

        foreach ( [ 'floor_2d', 'floor_3d' ] as $key ) {
            if ( empty( $media[ $key ] ) ) {
                continue;
            }
            $wpdb->insert(
                $table,
                [
                    'property_id'   => $property_id,
                    'media_type'    => $key,
                    'attachment_id' => absint( $media[ $key ] ),
                    'media_url'     => null,
                    'created_at'    => $now,
                ]
            );
        }

        foreach ( [ 'video', 'virtual' ] as $key ) {
            if ( empty( $media[ $key ] ) ) {
                continue;
            }
            $wpdb->insert(
                $table,
                [
                    'property_id'   => $property_id,
                    'media_type'    => $key,
                    'attachment_id' => null,
                    'media_url'     => $media[ $key ],
                    'created_at'    => $now,
                ]
            );
        }
    }

    /**
     * Delete media linked to property.
     */
    protected function delete_property_media( int $property_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'eo_property_media', [ 'property_id' => $property_id ], [ '%d' ] );
    }

    /**
     * Normalize property tags payload preserving metadata.
     *
     * @param array<string,mixed> $input    Raw submitted tags.
     * @param array<string,mixed> $existing Previously stored tags.
     * @return array<string,mixed>
     */
    protected function normalize_property_tags( array $input, array $existing = [] ): array {
        $normalized = [];

        foreach ( $input as $key => $value ) {
            if ( empty( $value ) ) {
                continue;
            }

            if ( 'new_offer' === $key ) {
                $since = '';
                if ( isset( $existing['new_offer'] ) ) {
                    $previous = $existing['new_offer'];
                    if ( is_array( $previous ) ) {
                        $since = $previous['since'] ?? '';
                    } elseif ( is_scalar( $previous ) ) {
                        $since = (string) $previous;
                    }
                }

                if ( ! $since || ! strtotime( $since ) ) {
                    $since = current_time( 'mysql' );
                }

                $normalized['new_offer'] = [
                    'active' => 1,
                    'since'  => $since,
                ];
                continue;
            }

            $normalized[ $key ] = 1;
        }

        return $normalized;
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
     * Normalize stage history payload before persisting.
     */
    protected function normalize_stage_history( $history, string $current_stage, string $start_date, $existing_contract = null ): array {
        $normalized = [];

        if ( is_array( $history ) ) {
            foreach ( $history as $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }
                $stage = sanitize_key( $entry['stage'] ?? '' );
                if ( '' === $stage ) {
                    continue;
                }
                $date = $this->sanitize_stage_date( $entry['date'] ?? '' );
                $normalized[] = [
                    'stage' => $stage,
                    'date'  => $date,
                ];
            }
        }

        $sanitized_stage = sanitize_key( $current_stage ?: 'umowa_posrednictwa' );
        $start_value     = $this->sanitize_stage_date( $start_date );

        if ( empty( $normalized ) && $existing_contract && ! empty( $existing_contract->stage_history ) ) {
            $decoded = json_decode( $existing_contract->stage_history, true );
            if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                $first         = $decoded[0];
                $initial_stage = sanitize_key( $first['stage'] ?? $sanitized_stage );
                $initial_date  = $this->sanitize_stage_date( $first['date'] ?? $start_value );
                $normalized[]  = [
                    'stage' => $initial_stage ?: $sanitized_stage,
                    'date'  => $initial_date ?: $start_value,
                ];
            }
        }

        if ( empty( $normalized ) ) {
            $normalized[] = [
                'stage' => $sanitized_stage ?: 'umowa_posrednictwa',
                'date'  => $start_value,
            ];
        } else {
            if ( empty( $normalized[0]['stage'] ) ) {
                $normalized[0]['stage'] = $sanitized_stage ?: 'umowa_posrednictwa';
            }
            if ( $start_value ) {
                $normalized[0]['date'] = $start_value;
            }
        }

        $last_index = count( $normalized ) - 1;
        if ( $last_index >= 0 ) {
            if ( $normalized[ $last_index ]['stage'] !== $sanitized_stage ) {
                $normalized[] = [
                    'stage' => $sanitized_stage,
                    'date'  => $this->sanitize_stage_date( current_time( 'Y-m-d' ) ),
                ];
            } elseif ( empty( $normalized[ $last_index ]['date'] ) ) {
                $normalized[ $last_index ]['date'] = $this->sanitize_stage_date( current_time( 'Y-m-d' ) );
            }
        }

        return $normalized;
    }

    /**
     * Sanitize date string for stage history entries.
     */
    protected function sanitize_stage_date( $value ): string {
        $value = sanitize_text_field( (string) $value );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return $value;
        }
        return '';
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
