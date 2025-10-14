<?php
/**
 * Register REST controllers.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Rest;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Binds plugin REST controllers to WordPress.
 */
class Rest_API {
    /**
     * Register routes on rest_api_init.
     *
     * @return void
     */
    public function register_routes(): void {
        $controllers = [
            new Properties_Controller(),
            new Clients_Controller(),
            new Contracts_Controller(),
            new Searches_Controller(),
        ];

        foreach ( $controllers as $controller ) {
            $controller->register_routes();
        }
    }
}
