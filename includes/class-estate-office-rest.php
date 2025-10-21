<?php
/**
 * REST API endpoints.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EstateOffice_Rest
 */
class EstateOffice_Rest {

    /**
     * Settings handler.
     *
     * @var EstateOffice_Settings
     */
    protected $settings;

    /**
     * EstateOffice_Rest constructor.
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
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            'estate-office/v1',
            '/clients/search',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'search_clients' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => [
                    'query' => [
                        'sanitize_callback' => 'sanitize_text_field',
                        'required'          => false,
                    ],
                ],
            ]
        );

        register_rest_route(
            'estate-office/v1',
            '/agents',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_agents' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );

        register_rest_route(
            'estate-office/v1',
            '/agreements/(?P<id>\d+)/stages',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'update_stage' ],
                'permission_callback' => [ $this, 'check_edit_permissions' ],
                'args'                => [
                    'stage' => [
                        'sanitize_callback' => 'sanitize_text_field',
                        'required'          => true,
                    ],
                    'date'  => [
                        'sanitize_callback' => 'sanitize_text_field',
                        'required'          => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Permission callback for viewing data.
     *
     * @return bool
     */
    public function check_permissions() {
        return current_user_can( 'view_estate_office' );
    }

    /**
     * Permission callback for editing data.
     *
     * @return bool
     */
    public function check_edit_permissions() {
        return current_user_can( 'edit_estate_office' );
    }

    /**
     * Search clients.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function search_clients( WP_REST_Request $request ) {
        global $wpdb;

        $query = '%' . $wpdb->esc_like( $request->get_param( 'query' ) ) . '%';

        $table = $wpdb->prefix . 'eo_clients';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, type, first_name, last_name, company_name, phone, email FROM $table
                WHERE first_name LIKE %s OR last_name LIKE %s OR company_name LIKE %s OR phone LIKE %s OR email LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                $query,
                $query,
                $query,
                $query,
                $query
            ),
            ARRAY_A
        );

        return new WP_REST_Response( $results );
    }

    /**
     * List agents.
     *
     * @return WP_REST_Response
     */
    public function list_agents() {
        $users = get_users(
            [
                'role'    => 'estate_agent',
                'orderby' => 'display_name',
                'order'   => 'ASC',
                'fields'  => [ 'ID', 'display_name', 'user_email' ],
            ]
        );

        $data = array_map(
            static function ( $user ) {
                return [
                    'id'    => $user->ID,
                    'name'  => $user->display_name,
                    'email' => $user->user_email,
                ];
            },
            $users
        );

        return new WP_REST_Response( $data );
    }

    /**
     * Update agreement stage.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function update_stage( WP_REST_Request $request ) {
        global $wpdb;

        $id    = (int) $request['id'];
        $stage = sanitize_text_field( $request->get_param( 'stage' ) );
        $date  = sanitize_text_field( $request->get_param( 'date' ) );

        $table = $wpdb->prefix . 'eo_agreements';

        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT stage_history FROM $table WHERE id = %d", $id ) );
        if ( ! $existing ) {
            return new WP_REST_Response( [ 'message' => __( 'Umowa nie została znaleziona.', 'estate-office' ) ], 404 );
        }

        $history = json_decode( $existing->stage_history, true );
        if ( ! is_array( $history ) ) {
            $history = [];
        }

        $history[] = [
            'stage' => $stage,
            'date'  => $date,
        ];

        $wpdb->update(
            $table,
            [
                'stage'         => $stage,
                'stage_history' => wp_json_encode( $history ),
                'updated_at'    => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        return new WP_REST_Response( [ 'success' => true, 'history' => $history ] );
    }
}
