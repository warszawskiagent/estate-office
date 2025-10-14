<?php
/**
 * Database helper.
 *
 * @package EstateOffice
 */

namespace EstateOffice\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides access to plugin tables.
 */
class DB {
    /**
     * Get full table name with WordPress prefix.
     *
     * @param string $name Table suffix.
     *
     * @return string
     */
    public static function table( string $name ): string {
        global $wpdb;

        return $wpdb->prefix . 'estate_office_' . $name;
    }
}
