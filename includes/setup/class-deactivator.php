<?php
namespace EstateOffice\Setup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin deactivation.
 */
class Deactivator {
    /**
     * Runs on plugin deactivation.
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
