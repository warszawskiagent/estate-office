<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package EstateOffice
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$option_keys = [
    'estate_office_db_version',
    'estate_office_google_api_key',
    'estate_office_watermark',
    'estate_office_logo',
];

foreach ( $option_keys as $key ) {
    delete_option( $key );
}
