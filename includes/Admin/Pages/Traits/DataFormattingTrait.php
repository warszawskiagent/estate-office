<?php
namespace EstateOffice\Admin\Pages\Traits;

use EstateOffice\Meta\Keys;
use WP_Post;

/**
 * Shared formatting helpers for admin views.
 */
trait DataFormattingTrait {
    protected function format_address( $data ): string {
        if ( ! is_array( $data ) ) {
            return '';
        }

        $parts = [];

        if ( ! empty( $data['street'] ) ) {
            $street = $data['street'];
            if ( ! empty( $data['number'] ) ) {
                $street .= ' ' . $data['number'];
            }

            if ( ! empty( $data['unit'] ) ) {
                $street .= '/' . $data['unit'];
            }

            $parts[] = $street;
        }

        if ( ! empty( $data['postal_code'] ) || ! empty( $data['city'] ) ) {
            $parts[] = trim( ( $data['postal_code'] ?? '' ) . ' ' . ( $data['city'] ?? '' ) );
        }

        if ( ! empty( $data['district'] ) ) {
            $parts[] = $data['district'];
        }

        if ( ! empty( $data['county'] ) ) {
            $parts[] = $data['county'];
        }

        if ( ! empty( $data['country'] ) ) {
            $parts[] = $data['country'];
        }

        return implode( ', ', array_filter( $parts ) );
    }

    protected function format_money( $value, string $currency = 'PLN', bool $dash_when_empty = true ): string {
        $value = is_numeric( $value ) ? (float) $value : 0.0;

        if ( $value <= 0 ) {
            return $dash_when_empty ? '—' : '';
        }

        return number_format_i18n( $value, 2 ) . ' ' . $currency;
    }

    protected function format_area( $value ): string {
        $value = is_numeric( $value ) ? (float) $value : 0.0;

        if ( $value <= 0 ) {
            return '—';
        }

        return number_format_i18n( $value, 2 ) . ' m²';
    }

    protected function format_agent( WP_Post $post ): string {
        $user = get_userdata( $post->post_author );

        if ( ! $user ) {
            return '—';
        }

        $display = trim( $user->first_name . ' ' . $user->last_name );
        return $display ?: ( $user->display_name ?: $user->user_login );
    }

    protected function format_transaction_type( string $type ): string {
        $map = [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];

        $type = strtolower( $type );
        return $map[ $type ] ?? ucfirst( $type );
    }

    protected function format_property_kind( string $kind ): string {
        $map = [
            'mieszkanie' => __( 'Mieszkanie', 'estate-office' ),
            'dom'        => __( 'Dom', 'estate-office' ),
            'dzialka'    => __( 'Działka', 'estate-office' ),
            'lokal'      => __( 'Lokal H/U', 'estate-office' ),
        ];

        $kind = strtolower( $kind );
        return $map[ $kind ] ?? ucfirst( $kind );
    }

    protected function format_land_register( string $value, bool $missing ): string {
        if ( $missing ) {
            return __( 'Brak numeru KW (oświadczenie klienta)', 'estate-office' );
        }

        return $value ? $value : '—';
    }

    protected function format_stage( $stage ): string {
        if ( ! is_array( $stage ) ) {
            return '';
        }

        $name = $stage['name'] ?? '';
        $date = $stage['date'] ?? '';

        if ( ! $name ) {
            return '';
        }

        return $date ? sprintf( '%s (%s)', $name, $this->format_date( $date ) ) : $name;
    }

    protected function format_date( string $date ): string {
        if ( ! $date ) {
            return '';
        }

        $timestamp = strtotime( $date );
        if ( ! $timestamp ) {
            return $date;
        }

        return wp_date( get_option( 'date_format' ), $timestamp );
    }

    protected function format_client_name( WP_Post $post ): string {
        $type      = get_post_meta( $post->ID, Keys::CLIENT_TYPE, true );
        $first     = get_post_meta( $post->ID, Keys::CLIENT_FIRST_NAME, true );
        $last      = get_post_meta( $post->ID, Keys::CLIENT_LAST_NAME, true );
        $company   = get_post_meta( $post->ID, Keys::CLIENT_COMPANY_NAME, true );
        $full_name = trim( $first . ' ' . $last );

        if ( 'firma' === $type && $company ) {
            return $company;
        }

        return $full_name ?: $post->post_title;
    }

    protected function infer_client_agent( WP_Post $post ): string {
        $contracts = get_post_meta( $post->ID, Keys::CLIENT_CONTRACTS, true );

        if ( ! is_array( $contracts ) || empty( $contracts ) ) {
            return '—';
        }

        $contract_id = (int) current( $contracts );
        $contract    = get_post( $contract_id );

        if ( ! $contract ) {
            return '—';
        }

        return $this->format_agent( $contract );
    }

    protected function format_budget( array $criteria, string $currency = 'PLN' ): string {
        $min = isset( $criteria['price_min'] ) ? (float) $criteria['price_min'] : 0.0;
        $max = isset( $criteria['price_max'] ) ? (float) $criteria['price_max'] : 0.0;

        if ( $min <= 0 && $max <= 0 ) {
            return '';
        }

        if ( $min > 0 && $max > 0 ) {
            return number_format_i18n( $min, 0 ) . ' - ' . number_format_i18n( $max, 0 ) . ' ' . $currency;
        }

        if ( $min > 0 ) {
            return sprintf( __( 'Od %s %s', 'estate-office' ), number_format_i18n( $min, 0 ), $currency );
        }

        return sprintf( __( 'Do %s %s', 'estate-office' ), number_format_i18n( $max, 0 ), $currency );
    }

    protected function get_profile_url( string $type, int $id ): string {
        if ( $id <= 0 ) {
            return '';
        }

        $pages = [
            'contract' => 'estate-office-contract-profile',
            'property' => 'estate-office-property-profile',
            'client'   => 'estate-office-client-profile',
            'search'   => 'estate-office-search-profile',
        ];

        if ( ! isset( $pages[ $type ] ) ) {
            return '';
        }

        return add_query_arg(
            [
                'page' => $pages[ $type ],
                $type  => $id,
            ],
            admin_url( 'admin.php' )
        );
    }
}
