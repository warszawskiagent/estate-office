<?php
/**
 * Helper sanitizing client request payloads.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Client_Request_Helper
 */
class Estate_Office_Client_Request_Helper {

    /**
     * Sanitize array data into repository compatible payload.
     *
     * @param array<string,mixed> $source Raw request data.
     *
     * @return array<string,mixed>
     */
    public static function sanitize_from_array( array $source ) : array {
        $client_type = isset( $source['client_type'] ) ? sanitize_text_field( wp_unslash( $source['client_type'] ) ) : 'person';
        $client_type = in_array( $client_type, [ 'person', 'company' ], true ) ? $client_type : 'person';

        $base_address = [
            'address_street'       => sanitize_text_field( wp_unslash( $source['address_street'] ?? '' ) ),
            'address_number'       => sanitize_text_field( wp_unslash( $source['address_number'] ?? '' ) ),
            'address_unit'         => sanitize_text_field( wp_unslash( $source['address_unit'] ?? '' ) ),
            'address_postal_code'  => sanitize_text_field( wp_unslash( $source['address_postal_code'] ?? '' ) ),
            'address_city'         => sanitize_text_field( wp_unslash( $source['address_city'] ?? '' ) ),
            'address_district'     => sanitize_text_field( wp_unslash( $source['address_district'] ?? '' ) ),
            'address_country'      => sanitize_text_field( wp_unslash( $source['address_country'] ?? '' ) ),
        ];

        $correspondence_same = isset( $source['correspondence_same'] ) ? 1 : 0;
        $correspondence      = $correspondence_same ? [
            'correspondence_street'      => $base_address['address_street'],
            'correspondence_number'      => $base_address['address_number'],
            'correspondence_unit'        => $base_address['address_unit'],
            'correspondence_postal_code' => $base_address['address_postal_code'],
            'correspondence_city'        => $base_address['address_city'],
            'correspondence_country'     => $base_address['address_country'],
        ] : [
            'correspondence_street'      => sanitize_text_field( wp_unslash( $source['correspondence_street'] ?? '' ) ),
            'correspondence_number'      => sanitize_text_field( wp_unslash( $source['correspondence_number'] ?? '' ) ),
            'correspondence_unit'        => sanitize_text_field( wp_unslash( $source['correspondence_unit'] ?? '' ) ),
            'correspondence_postal_code' => sanitize_text_field( wp_unslash( $source['correspondence_postal_code'] ?? '' ) ),
            'correspondence_city'        => sanitize_text_field( wp_unslash( $source['correspondence_city'] ?? '' ) ),
            'correspondence_country'     => sanitize_text_field( wp_unslash( $source['correspondence_country'] ?? '' ) ),
        ];

        $data = [
            'client_type'         => $client_type,
            'first_name'          => sanitize_text_field( wp_unslash( $source['first_name'] ?? '' ) ),
            'last_name'           => sanitize_text_field( wp_unslash( $source['last_name'] ?? '' ) ),
            'company_name'        => sanitize_text_field( wp_unslash( $source['company_name'] ?? '' ) ),
            'representative_name' => sanitize_text_field( wp_unslash( $source['representative_name'] ?? '' ) ),
            'phone'               => sanitize_text_field( wp_unslash( $source['phone'] ?? '' ) ),
            'email'               => sanitize_email( wp_unslash( $source['email'] ?? '' ) ),
            'website'             => esc_url_raw( wp_unslash( $source['website'] ?? '' ) ),
            'document_type'       => sanitize_key( wp_unslash( $source['document_type'] ?? '' ) ),
            'document_number'     => sanitize_text_field( wp_unslash( $source['document_number'] ?? '' ) ),
            'pesel'               => sanitize_text_field( wp_unslash( $source['pesel'] ?? '' ) ),
            'nip'                 => sanitize_text_field( wp_unslash( $source['nip'] ?? '' ) ),
            'krs'                 => sanitize_text_field( wp_unslash( $source['krs'] ?? '' ) ),
            'regon'               => sanitize_text_field( wp_unslash( $source['regon'] ?? '' ) ),
            'notes'               => wp_kses_post( wp_unslash( $source['notes'] ?? '' ) ),
            'correspondence_same' => $correspondence_same,
        ];

        return array_merge( $data, $base_address, $correspondence );
    }
}
