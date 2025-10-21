<?php
/**
 * Handles scripts and styles.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EstateOffice_Assets
 */
class EstateOffice_Assets {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register_hooks() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_assets' ] );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     *
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        if ( false === strpos( $hook, 'estate-office' ) ) {
            return;
        }

        wp_enqueue_style(
            'estate-office-admin',
            ESTATE_OFFICE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ESTATE_OFFICE_VERSION
        );

        wp_enqueue_script(
            'estate-office-admin',
            ESTATE_OFFICE_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery', 'wp-i18n', 'wp-api-fetch', 'wp-util' ],
            ESTATE_OFFICE_VERSION,
            true
        );

        wp_set_script_translations( 'estate-office-admin', 'estate-office', ESTATE_OFFICE_PLUGIN_DIR . 'languages' );

        wp_localize_script(
            'estate-office-admin',
            'estateOfficeSettings',
            [
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'restUrl'   => esc_url_raw( rest_url( 'estate-office/v1' ) ),
                'nonce'     => wp_create_nonce( 'wp_rest' ),
                'i18n'      => [
                    'next'       => __( 'Dalej', 'estate-office' ),
                    'previous'   => __( 'Wstecz', 'estate-office' ),
                    'add'        => __( 'Dodaj', 'estate-office' ),
                    'remove'     => __( 'Usuń', 'estate-office' ),
                    'confirmRemove' => __( 'Czy na pewno chcesz usunąć?', 'estate-office' ),
                ],
            ]
        );
    }

    /**
     * Enqueue front assets.
     *
     * @return void
     */
    public function enqueue_front_assets() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        wp_enqueue_style(
            'estate-office-front',
            ESTATE_OFFICE_PLUGIN_URL . 'assets/css/front.css',
            [],
            ESTATE_OFFICE_VERSION
        );

        wp_enqueue_script(
            'estate-office-front',
            ESTATE_OFFICE_PLUGIN_URL . 'assets/js/front.js',
            [ 'jquery', 'wp-i18n', 'wp-api-fetch', 'wp-util' ],
            ESTATE_OFFICE_VERSION,
            true
        );

        wp_localize_script(
            'estate-office-front',
            'estateOfficeFront',
            [
                'restUrl' => esc_url_raw( rest_url( 'estate-office/v1' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ]
        );
    }
}
