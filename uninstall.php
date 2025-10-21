<?php
/**
 * Fired when plugin is uninstalled.
 *
 * @package EstateOffice
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$option_keys = [
    'estate_office_google_maps_api_key',
    'estate_office_watermark_attachment',
    'estate_office_office_logo_attachment',
    'estate_office_property_custom_fields',
    'estate_office_contract_custom_fields',
    'estate_office_client_custom_fields',
];

foreach ( $option_keys as $option ) {
    delete_option( $option );
}
