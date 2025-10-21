<?php
/**
 * Template helper functions.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'estate_office_format_address' ) ) {
    /**
     * Format address JSON into readable string.
     *
     * @param string $json JSON encoded address.
     *
     * @return string
     */
    function estate_office_format_address( $json ) {
        if ( empty( $json ) ) {
            return '';
        }

        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return '';
        }

        $parts = array_filter(
            [
                $data['street'] ?? '',
                $data['number'] ?? '',
                $data['city'] ?? '',
            ]
        );

        return implode( ', ', $parts );
    }
}
