<?php
/**
 * Clients REST controller.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Rest;

use EstateOffice\Controllers\Client_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Exposes client CRUD endpoints.
 */
class Clients_Controller extends Base_Controller {
    /**
     * Client controller instance.
     *
     * @var Client_Controller
     */
    private Client_Controller $controller;

    public function __construct() {
        $this->controller = new Client_Controller();
    }

    /**
     * {@inheritdoc}
     */
    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/clients',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => fn() => $this->ensure_capability( 'read' ),
                    'args'                => [
                        'search'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
                        'page'     => [ 'sanitize_callback' => 'absint' ],
                        'per_page' => [ 'sanitize_callback' => 'absint' ],
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
            '/clients/(?P<id>\d+)',
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
     * List clients.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function get_items( WP_REST_Request $request ) {
        $results = $this->controller->all(
            [
                'search'   => $request->get_param( 'search' ) ?? '',
                'page'     => $request->get_param( 'page' ) ?? 1,
                'per_page' => $request->get_param( 'per_page' ) ?? 20,
            ]
        );

        return $this->respond( $results );
    }

    /**
     * Retrieve a single client.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function get_item( WP_REST_Request $request ) {
        $item = $this->controller->get( (int) $request['id'] );

        if ( empty( $item ) ) {
            return new WP_Error( 'estate_office_not_found', __( 'Klient nie został znaleziony.', 'estate-office' ), [ 'status' => 404 ] );
        }

        return $this->respond( $item );
    }

    /**
     * Create client entry.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function create_item( WP_REST_Request $request ) {
        $data = $this->get_request_data( $request );
        $id   = $this->controller->create( $data );

        if ( ! $id ) {
            return new WP_Error( 'estate_office_create_failed', __( 'Nie udało się utworzyć klienta.', 'estate-office' ), [ 'status' => 500 ] );
        }

        $item = $this->controller->get( $id );

        return $this->respond( $item, 201 );
    }

    /**
     * Update client entry.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function update_item( WP_REST_Request $request ) {
        $id   = (int) $request['id'];
        $data = $this->get_request_data( $request );

        if ( ! $this->controller->update( $id, $data ) ) {
            return new WP_Error( 'estate_office_update_failed', __( 'Nie udało się zaktualizować klienta.', 'estate-office' ), [ 'status' => 500 ] );
        }

        $item = $this->controller->get( $id );

        return $this->respond( $item );
    }

    /**
     * Delete client entry.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item( WP_REST_Request $request ) {
        $id = (int) $request['id'];

        if ( ! $this->controller->delete( $id ) ) {
            return new WP_Error( 'estate_office_delete_failed', __( 'Nie udało się usunąć klienta.', 'estate-office' ), [ 'status' => 500 ] );
        }

        return $this->respond( [ 'deleted' => true ] );
    }
}
