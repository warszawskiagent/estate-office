<?php
/**
 * Internationalization functionality.
 *
 * @package EstateOffice
 */

namespace EstateOffice;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles loading of translation files.
 */
class I18n {
    /**
     * Load plugin textdomain.
     *
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'estate-office', false, dirname( plugin_basename( ESTATE_OFFICE_PLUGIN_FILE ) ) . '/languages/' );
    }
}
