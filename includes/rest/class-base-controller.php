<?php
/**
 * Base REST controller utilities.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides helper methods for REST controllers.
 */
abstract class Base_Controller {
    protected const NAMESPACE = 'estate-office/v1';

    /**
     * Register controller routes.
     *
     * @return void
     */
    abstract public function register_routes(): void;

    /**
     * Ensure current user has requested capability.
     *
     * @param string $capability Capability name.
     *
     * @return bool|WP_Error
     */
    protected function ensure_capability( string $capability ) {
        if ( current_user_can( $capability ) ) {
            return true;
        }

        return new WP_Error(
            'estate_office_forbidden',
            __( 'Brak uprawnień do wykonania akcji.', 'estate-office' ),
            [ 'status' => 403 ]
        );
    }

    /**
     * Prepare a REST response.
     *
     * @param mixed $data Response data.
     * @param int   $status HTTP status code.
     *
     * @return WP_REST_Response
     */
    protected function respond( $data, int $status = 200 ): WP_REST_Response {
        $response = rest_ensure_response( $data );
        $response->set_status( $status );

        return $response;
    }

    /**
     * Extract sanitized request body.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return array<string,mixed>
     */
    protected function get_request_data( WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        if ( empty( $params ) ) {
            $params = $request->get_params();
        }

        return is_array( $params ) ? wp_unslash( $params ) : [];
    }
}
