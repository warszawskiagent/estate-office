<?php
/**
 * CRM functionality for admin and front-end views.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EstateOffice_CRM
 */
class EstateOffice_CRM {

    /**
     * Settings handler.
     *
     * @var EstateOffice_Settings
     */
    protected $settings;

    /**
     * EstateOffice_CRM constructor.
     *
     * @param EstateOffice_Settings $settings Settings handler instance.
     */
    public function __construct( EstateOffice_Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register_hooks() {
        add_action( 'admin_post_estate_office_save_agreement', [ $this, 'handle_save_agreement' ] );
        add_action( 'admin_post_estate_office_save_client', [ $this, 'handle_save_client' ] );
        add_action( 'admin_post_estate_office_save_property', [ $this, 'handle_save_property' ] );
        add_action( 'admin_post_estate_office_save_search', [ $this, 'handle_save_search' ] );
    }

    /**
     * Register shortcodes.
     *
     * @return void
     */
    public function register_shortcodes() {
        add_shortcode( 'estate_office_crm', [ $this, 'render_crm_shortcode' ] );
    }

    /**
     * Render CRM shortcode.
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string
     */
    public function render_crm_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Zaloguj się, aby uzyskać dostęp do CRM.', 'estate-office' ) . '</p>';
        }

        if ( ! current_user_can( 'view_estate_office' ) ) {
            return '<p>' . esc_html__( 'Nie masz uprawnień do przeglądania CRM.', 'estate-office' ) . '</p>';
        }

        ob_start();
        include ESTATE_OFFICE_PLUGIN_DIR . 'templates/frontend/crm.php';
        return ob_get_clean();
    }

    /**
     * Handle saving agreement data.
     *
     * @return void
     */
    public function handle_save_agreement() {
        $this->verify_permissions();
        $this->verify_nonce( 'estate_office_agreement_nonce' );

        $data = [
            'contract_number'   => sanitize_text_field( wp_unslash( $_POST['contract_number'] ?? '' ) ),
            'transaction_type'  => sanitize_text_field( wp_unslash( $_POST['transaction_type'] ?? '' ) ),
            'start_date'        => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
            'end_date'          => sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) ),
            'open_ended'        => isset( $_POST['open_ended'] ) ? 1 : 0,
            'commission_amount' => floatval( wp_unslash( $_POST['commission_amount'] ?? 0 ) ),
            'commission_unit'   => sanitize_text_field( wp_unslash( $_POST['commission_unit'] ?? '%' ) ),
        ];

        $agreement_id = EstateOffice_Database::instance()->save_agreement( $data );

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'         => 'estate-office-agreements',
                    'action'       => 'add-client',
                    'agreement_id' => $agreement_id,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Handle saving client data.
     *
     * @return void
     */
    public function handle_save_client() {
        $this->verify_permissions();
        $this->verify_nonce( 'estate_office_client_nonce' );

        $agreement_id = absint( $_POST['agreement_id'] ?? 0 );
        $existing_id  = absint( $_POST['existing_client_id'] ?? 0 );
        $add_another  = isset( $_POST['add_another'] ) && 'yes' === $_POST['add_another'];

        if ( $existing_id ) {
            $client_id = $existing_id;
        } else {
            $client = $this->prepare_client_data( $_POST );
            $client_id = EstateOffice_Database::instance()->save_client( $client );
        }

        if ( $agreement_id && $client_id ) {
            EstateOffice_Database::instance()->attach_client_to_agreement( $agreement_id, $client_id );
        }

        if ( $add_another ) {
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'         => 'estate-office-agreements',
                        'action'       => 'add-client',
                        'agreement_id' => $agreement_id,
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        $agreement = EstateOffice_Database::instance()->get_agreement( $agreement_id );

        if ( $agreement ) {
            $transaction_type = strtoupper( $agreement['transaction_type'] );
            if ( in_array( $transaction_type, [ 'SPRZEDAŻ', 'WYNAJEM' ], true ) ) {
                $redirect_action = 'add-property';
            } else {
                $redirect_action = 'add-search';
            }

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'         => 'estate-office-agreements',
                        'action'       => $redirect_action,
                        'agreement_id' => $agreement_id,
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        wp_safe_redirect( $this->get_redirect_url( 'clients', $client_id ) );
        exit;
    }

    /**
     * Prepare client data from request.
     *
     * @param array $source Source data.
     *
     * @return array
     */
    protected function prepare_client_data( $source ) {
        $type = sanitize_text_field( wp_unslash( $source['client_type'] ?? 'person' ) );

        $basic = [
            'type'           => $type,
            'first_name'     => 'person' === $type ? sanitize_text_field( wp_unslash( $source['first_name'] ?? '' ) ) : '',
            'last_name'      => 'person' === $type ? sanitize_text_field( wp_unslash( $source['last_name'] ?? '' ) ) : '',
            'company_name'   => 'company' === $type ? sanitize_text_field( wp_unslash( $source['company_name'] ?? '' ) ) : '',
            'representative' => 'company' === $type ? sanitize_text_field( wp_unslash( $source['representative'] ?? '' ) ) : '',
            'phone'          => sanitize_text_field( wp_unslash( $source['phone'] ?? '' ) ),
            'email'          => sanitize_email( wp_unslash( $source['email'] ?? '' ) ),
            'website'        => isset( $source['website'] ) ? esc_url_raw( $source['website'] ) : '',
        ];

        $identification = $this->prepare_json_field( $source['identification'] ?? [] );
        $address        = $this->prepare_json_field( $source['address'] ?? [] );
        $correspondence = $this->prepare_json_field( $source['correspondence'] ?? [] );

        return array_merge(
            $basic,
            [
                'identification' => $identification,
                'address'        => $address,
                'correspondence' => $correspondence,
            ]
        );
    }

    /**
     * Handle saving property data.
     *
     * @return void
     */
    public function handle_save_property() {
        $this->verify_permissions();
        $this->verify_nonce( 'estate_office_property_nonce' );

        $data = [
            'agreement_id'     => absint( $_POST['agreement_id'] ?? 0 ),
            'transaction_type' => sanitize_text_field( wp_unslash( $_POST['transaction_type'] ?? '' ) ),
            'property_type'    => sanitize_text_field( wp_unslash( $_POST['property_type'] ?? '' ) ),
            'address'          => $this->prepare_json_field( $_POST['address'] ?? [] ),
            'legal_status'     => sanitize_text_field( wp_unslash( $_POST['legal_status'] ?? '' ) ),
            'registry_number'  => sanitize_text_field( wp_unslash( $_POST['registry_number'] ?? '' ) ),
            'price'            => floatval( wp_unslash( $_POST['price'] ?? 0 ) ),
            'rent'             => floatval( wp_unslash( $_POST['rent'] ?? 0 ) ),
            'area'             => floatval( wp_unslash( $_POST['area'] ?? 0 ) ),
            'price_per_sqm'    => floatval( wp_unslash( $_POST['price_per_sqm'] ?? 0 ) ),
            'details'          => $this->prepare_json_field( $_POST['details'] ?? [] ),
            'description'      => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'amenities'        => $this->prepare_json_field( $_POST['amenities'] ?? [] ),
            'surfaces'         => $this->prepare_json_field( $_POST['surfaces'] ?? [] ),
            'media'            => $this->prepare_json_field( $_POST['media'] ?? [] ),
            'tags'             => $this->prepare_json_field( $_POST['tags'] ?? [] ),
            'export_www'       => isset( $_POST['export_www'] ) ? 1 : 0,
            'export_portals'   => isset( $_POST['export_portals'] ) ? 1 : 0,
        ];

        $property_id = EstateOffice_Database::instance()->save_property( $data );

        wp_safe_redirect( $this->get_redirect_url( 'properties', $property_id ) );
        exit;
    }

    /**
     * Handle saving search data.
     *
     * @return void
     */
    public function handle_save_search() {
        $this->verify_permissions();
        $this->verify_nonce( 'estate_office_search_nonce' );

        $data = [
            'agreement_id'     => absint( $_POST['agreement_id'] ?? 0 ),
            'transaction_type' => sanitize_text_field( wp_unslash( $_POST['transaction_type'] ?? '' ) ),
            'criteria'         => $this->prepare_json_field( $_POST['criteria'] ?? [] ),
            'description'      => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
        ];

        $search_id = EstateOffice_Database::instance()->save_search( $data );

        wp_safe_redirect( $this->get_redirect_url( 'searches', $search_id ) );
        exit;
    }

    /**
     * Convert structured array into JSON string.
     *
     * @param array $value Array value.
     *
     * @return string
     */
    protected function prepare_json_field( $value ) {
        if ( empty( $value ) ) {
            return wp_json_encode( [] );
        }

        $sanitized = [];
        foreach ( $value as $key => $field ) {
            if ( is_array( $field ) ) {
                $nested = json_decode( $this->prepare_json_field( $field ), true );
                if ( ! is_array( $nested ) ) {
                    $nested = [];
                }
                $sanitized[ sanitize_key( $key ) ] = $nested;
            } else {
                $sanitized[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $field ) );
            }
        }

        return wp_json_encode( $sanitized );
    }

    /**
     * Verify user permissions.
     *
     * @return void
     */
    protected function verify_permissions() {
        if ( ! current_user_can( 'edit_estate_office' ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'estate-office' ) );
        }
    }

    /**
     * Verify nonce.
     *
     * @param string $nonce_field Nonce field name.
     *
     * @return void
     */
    protected function verify_nonce( $nonce_field ) {
        if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_field ) ) {
            wp_die( esc_html__( 'Niepoprawny token bezpieczeństwa.', 'estate-office' ) );
        }
    }

    /**
     * Generate redirect URL after saving data.
     *
     * @param string $endpoint Endpoint slug.
     * @param int    $id       Saved entity ID.
     *
     * @return string
     */
    protected function get_redirect_url( $endpoint, $id ) {
        $url = admin_url( 'admin.php?page=estate-office-' . $endpoint );

        if ( $id ) {
            $args = [ 'updated' => 'true' ];

            if ( in_array( $endpoint, [ 'properties', 'searches', 'agreements' ], true ) ) {
                $args['view'] = (int) $id;
            } else {
                $args['id'] = (int) $id;
            }

            $url = add_query_arg( $args, $url );
        }

        return $url;
    }
}
