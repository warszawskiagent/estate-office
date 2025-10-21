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
    }
}
