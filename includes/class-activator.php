<?php
/**
 * Fired during plugin activation.
 *
 * @package EstateOffice
 */

namespace EstateOffice;

use EstateOffice\Database\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Activation hook handler.
 */
class Activator {
    /**
     * Run activation logic.
     *
     * @return void
     */
    public static function activate(): void {
        require_once ESTATE_OFFICE_PLUGIN_DIR . '/includes/database/class-migrations.php';

        $migrations = new Migrations();
        $migrations->maybe_run( true );
        flush_rewrite_rules();
    }
}
