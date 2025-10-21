<?php
namespace EstateOffice\Rest;

/**
 * REST API routes for Estate Office CRM.
 */
class Router {
/**
 * Constructor.
 */
public function __construct() {
add_action( 'rest_api_init', array( $this, 'register_routes' ) );
}

/**
 * Register REST routes.
 *
 * @return void
 */
public function register_routes() {
register_rest_route(
'estate-office/v1',
'/ping',
array(
'callback'            => array( $this, 'ping' ),
'permission_callback' => '__return_true',
)
);
}

/**
 * Simple ping endpoint for diagnostics.
 *
 * @return array
 */
public function ping() {
return array(
'message' => 'ok',
'version' => ESTATE_OFFICE_VERSION,
);
}
}
