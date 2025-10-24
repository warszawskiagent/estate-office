<?php
/**
 * Handles plugin deactivation lifecycle events.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        // Keep data for compliance. Future cleanup can be added via uninstall.php.
        if ( class_exists( 'EstateOffice_Portal_Manager' ) ) {
            wp_unschedule_hook( EstateOffice_Portal_Manager::CRON_HOOK );
        }
    }
}
