<?php
/**
 * Contracts REST controller.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Rest;

use EstateOffice\Controllers\Contract_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Exposes contract CRUD endpoints.
 */
class Contracts_Controller extends Base_Controller {
    /**
     * Contract controller instance.
     *
     * @var Contract_Controller
     */
    private Contract_Controller $controller;

    public function __construct() {
        $this->controller = new Contract_Controller();
    }

    /**
     * {@inheritdoc}
     */
    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/contracts',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => fn() => $this->ensure_capability( 'read' ),
                    'args'                => [
                        'status'    => [ 'sanitize_callback' => 'sanitize_text_field' ],
                        'client_id' => [ 'sanitize_callback' => 'absint' ],
                        'page'      => [ 'sanitize_callback' => 'absint' ],
                        'per_page'  => [ 'sanitize_callback' => 'absint' ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_item' ],
                    'permission_callback' => fn() => $this->ensure_capability( 'edit_posts' ),
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/contracts/(?P<id>\\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => fn() => $this->ensure_capability( 'read' ),
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => fn() => $this->ensure_capability( 'edit_posts' ),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => fn() => $this->ensure_capability( 'delete_posts' ),
                ],
            ]
        );
    }

    /**
     * List contracts.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return \WP_REST_Response|WP_Error
     */
    public function get_items( WP_REST_Request $request ) {
        $results = $this->controller->all(
            [
                'status'    => $request->get_param( 'status' ) ?? '',
                'client_id' => $request->get_param( 'client_id' ) ?? 0,
                'page'      => $request->get_param( 'page' ) ?? 1,
                'per_page'  => $request->get_param( 'per_page' ) ?? 20,
            ]
        );

        return $this->respond( $results );
    }

    /**
     * Retrieve a contract entry.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return \WP_REST_Response|WP_Error
     */
    public function get_item( WP_REST_Request $request ) {
        $item = $this->controller->get( (int) $request['id'] );

        if ( empty( $item ) ) {
            return new WP_Error( 'estate_office_not_found', __( 'Umowa nie została znaleziona.', 'estate-office' ), [ 'status' => 404 ] );
        }

        return $this->respond( $item );
    }

    /**
     * Create contract entry.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return \WP_REST_Response|WP_Error
     */
    public function create_item( WP_REST_Request $request ) {
        $data = $this->get_request_data( $request );
        $id   = $this->controller->create( $data );

        if ( ! $id ) {
            return new WP_Error( 'estate_office_create_failed', __( 'Nie udało się utworzyć umowy.', 'estate-office' ), [ 'status' => 500 ] );
        }

        $item = $this->controller->get( $id );

        return $this->respond( $item, 201 );
    }

    /**
     * Update contract entry.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return \WP_REST_Response|WP_Error
     */
    public function update_item( WP_REST_Request $request ) {
        $id   = (int) $request['id'];
        $data = $this->get_request_data( $request );

        if ( ! $this->controller->update( $id, $data ) ) {
            return new WP_Error( 'estate_office_update_failed', __( 'Nie udało się zaktualizować umowy.', 'estate-office' ), [ 'status' => 500 ] );
        }

        $item = $this->controller->get( $id );

        return $this->respond( $item );
    }

    /**
     * Delete contract entry.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return \WP_REST_Response|WP_Error
     */
    public function delete_item( WP_REST_Request $request ) {
        $id = (int) $request['id'];

        if ( ! $this->controller->delete( $id ) ) {
            return new WP_Error( 'estate_office_delete_failed', __( 'Nie udało się usunąć umowy.', 'estate-office' ), [ 'status' => 500 ] );
        }

        return $this->respond( [ 'deleted' => true ] );
    }
}
