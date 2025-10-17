<?php
/**
 * Helper for dynamic custom fields configured in settings.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Dynamic_Fields
 */
class Estate_Office_Dynamic_Fields {

    private const OPTION_KEY = 'estate_office_settings';

    /**
     * Map form contexts to option keys.
     *
     * @var array<string,string>
     */
    private const CONTEXT_OPTION_KEYS = [
        'property' => 'property_fields',
        'contract' => 'contract_fields',
        'client'   => 'client_fields',
    ];

    /**
     * Cached field maps per context.
     *
     * @var array<string,array<string,string>>
     */
    private static array $field_cache = [];

    /**
     * Returns associative array of field keys mapped to labels.
     *
     * @param string $context Context identifier (property|contract|client).
     *
     * @return array<string,string>
     */
    public static function get_field_map( string $context ) : array {
        if ( isset( self::$field_cache[ $context ] ) ) {
            return self::$field_cache[ $context ];
        }

        if ( ! isset( self::CONTEXT_OPTION_KEYS[ $context ] ) ) {
            return [];
        }

        $settings = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $option_key = self::CONTEXT_OPTION_KEYS[ $context ];
        $raw_fields = $settings[ $option_key ] ?? [];
        if ( ! is_array( $raw_fields ) ) {
            $raw_fields = [];
        }

        $fields   = [];
        $existing = [];

        foreach ( $raw_fields as $label ) {
            if ( ! is_string( $label ) ) {
                continue;
            }

            $label = trim( $label );
            if ( '' === $label ) {
                continue;
            }

            $key = sanitize_title( $label );
            if ( '' === $key ) {
                $key = substr( md5( $label ), 0, 12 );
            }

            $original_key = $key;
            $suffix       = 2;
            while ( in_array( $key, $existing, true ) ) {
                $key = $original_key . '-' . $suffix;
                $suffix++;
            }

            $existing[]     = $key;
            $fields[ $key ] = $label;
        }

        self::$field_cache[ $context ] = $fields;

        return $fields;
    }

    /**
     * Prepare stored values for form usage with defaults for missing fields.
     *
     * @param string               $context Context identifier.
     * @param array<string,string> $values  Stored values indexed by field key.
     *
     * @return array<string,string>
     */
    public static function merge_defaults( string $context, array $values ) : array {
        $fields = self::get_field_map( $context );
        if ( empty( $fields ) ) {
            return [];
        }

        $normalized = [];
        foreach ( $values as $key => $value ) {
            if ( ! is_string( $key ) ) {
                continue;
            }

            if ( is_scalar( $value ) ) {
                $normalized[ $key ] = (string) $value;
            }
        }

        $merged = [];
        foreach ( $fields as $key => $label ) {
            $merged[ $key ] = isset( $normalized[ $key ] ) ? (string) $normalized[ $key ] : '';
        }

        return $merged;
    }

    /**
     * Sanitize incoming request values for storage.
     *
     * @param string               $context Context identifier.
     * @param array<string,string> $values  Raw request values.
     *
     * @return array<string,string>
     */
    public static function sanitize_values( string $context, array $values ) : array {
        $fields = self::get_field_map( $context );
        if ( empty( $fields ) ) {
            return [];
        }

        $sanitized = [];
        foreach ( $fields as $key => $label ) {
            if ( ! array_key_exists( $key, $values ) ) {
                continue;
            }

            $value = $values[ $key ];
            if ( ! is_scalar( $value ) ) {
                continue;
            }

            $clean = sanitize_text_field( (string) $value );
            if ( '' === $clean ) {
                continue;
            }

            $sanitized[ $key ] = $clean;
        }

        return $sanitized;
    }

    /**
     * Prepare values for display with human-readable labels.
     *
     * @param string               $context Context identifier.
     * @param array<string,string> $values  Stored values indexed by key.
     *
     * @return array<string,string>
     */
    public static function format_for_display( string $context, array $values ) : array {
        $fields = self::get_field_map( $context );
        if ( empty( $fields ) ) {
            return [];
        }

        $display = [];
        foreach ( $fields as $key => $label ) {
            if ( ! isset( $values[ $key ] ) ) {
                continue;
            }

            $value = (string) $values[ $key ];
            if ( '' === trim( $value ) ) {
                continue;
            }

            $display[ $label ] = sanitize_text_field( $value );
        }

        return $display;
    }

    /**
     * Reset cached map – useful in tests.
     *
     * @return void
     */
    public static function clear_cache() : void {
        self::$field_cache = [];
    }
}
