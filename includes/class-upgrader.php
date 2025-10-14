<?php
/**
 * Handles plugin upgrade routines.
 *
 * @package EstateOffice
 */

namespace EstateOffice;

use EstateOffice\Database\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Runs upgrade routines when plugin version changes.
 */
class Upgrader {
    private const OPTION_KEY = 'estate_office_plugin_version';

    /**
     * Check stored version and perform necessary upgrades.
     *
     * @return void
     */
    public function maybe_upgrade(): void {
        $installed_version = get_option( self::OPTION_KEY );

        if ( false === $installed_version ) {
            update_option( self::OPTION_KEY, ESTATE_OFFICE_VERSION );

            return;
        }

        if ( version_compare( (string) $installed_version, ESTATE_OFFICE_VERSION, '>=' ) ) {
            return;
        }

        $migrations = new Migrations();
        $migrations->maybe_run( true );

        flush_rewrite_rules();

        update_option( self::OPTION_KEY, ESTATE_OFFICE_VERSION );
    }
}
