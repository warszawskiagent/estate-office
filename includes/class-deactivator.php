<?php
/**
 * Fired during plugin deactivation.
 *
 * @package EstateOffice
 */

namespace EstateOffice;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Deactivation hook handler.
 */
class Deactivator {
    /**
     * Run deactivation logic.
     *
     * @return void
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
