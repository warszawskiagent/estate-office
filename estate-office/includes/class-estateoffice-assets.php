<?php
namespace EstateOffice;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets {
    /**
     * Singleton instance.
     *
     * @var Assets
     */
    private static $instance;

    /**
     * Retrieve singleton instance.
     *
     * @return Assets
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->hooks();
        }

        return self::$instance;
    }

    /**
     * Register WordPress hooks.
     */
    private function hooks() {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix
     */
    public function admin_assets( $hook_suffix ) {
        if ( false === strpos( $hook_suffix, 'estate-office' ) ) {
            return;
        }

        wp_enqueue_style(
            'estate-office-admin',
            plugins_url( 'assets/css/admin.css', ESTATE_OFFICE_PLUGIN_FILE ),
            array(),
            ESTATE_OFFICE_VERSION
        );

        wp_enqueue_script(
            'estate-office-admin',
            plugins_url( 'assets/js/admin.js', ESTATE_OFFICE_PLUGIN_FILE ),
            array( 'jquery', 'wp-util' ),
            ESTATE_OFFICE_VERSION,
            true
        );

        wp_localize_script(
            'estate-office-admin',
            'EstateOfficeAdmin',
            array(
                'i18n' => array(
                    'selectImage' => __( 'Wybierz obraz', 'estate-office' ),
                    'useImage'    => __( 'Użyj wybranego obrazu', 'estate-office' ),
                ),
            )
        );
    }
}
