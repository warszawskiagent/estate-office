<?php
namespace EstateOffice\Frontend;

use EstateOffice\Meta\Keys;
use WP_Post;
use WP_Query;
use WP_User;

/**
 * Handles public facing agent profiles.
 */
class Agents {
    public const QUERY_VAR = 'estate_agent';
    public const PROFILE_BASE = 'agenci';

    /**
     * Context passed to the template.
     *
     * @var array<string,mixed>
     */
    private array $current_context = [];

    /**
     * Registers rewrite tags and rules for agent profile URLs.
     */
    public function register_routes(): void {
        add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
        add_rewrite_rule(
            '^' . self::PROFILE_BASE . '/([^/]+)/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    /**
     * Registers custom query var for agent profiles.
     */
    public function register_query_var( array $vars ): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * Provides template for agent profile pages.
     */
    public function template_loader( string $template ): string {
        $slug = get_query_var( self::QUERY_VAR );

        if ( ! $slug ) {
            return $template;
        }

        $user = $this->get_agent_by_slug( sanitize_title_for_query( $slug ) );

        if ( ! $user ) {
            global $wp_query;

            if ( $wp_query ) {
                $wp_query->set_404();
            }

            status_header( 404 );
            return get_query_template( '404' );
        }

        $this->current_context = [
            'agent'      => $this->build_agent_context( $user, true ),
            'properties' => $this->query_agent_properties( $user->ID ),
        ];

        global $estate_office_agent_context;
        $estate_office_agent_context = $this->current_context;

        return ESTATE_OFFICE_PATH . 'templates/agent-profile.php';
    }

    /**
     * Enqueues front-end assets when viewing agent profiles.
     */
    public function enqueue_assets(): void {
        if ( $this->is_agent_profile_request() ) {
            wp_enqueue_style( 'estate-office-frontend', ESTATE_OFFICE_URL . 'assets/css/frontend.css', [], ESTATE_OFFICE_VERSION );
        }
    }

    /**
     * Returns the currently prepared template context.
     *
     * @return array<string,mixed>
     */
    public function get_current_context(): array {
        return $this->current_context;
    }

    /**
     * Builds agent data for external consumers.
     *
     * @return array<string,string>
     */
    public function get_agent_context( int $user_id ): array {
        $user = get_user_by( 'ID', $user_id );

        if ( ! $user ) {
            return [];
        }

        $include_profile = in_array( 'estate_office_agent', (array) $user->roles, true );

        return $this->build_agent_context( $user, $include_profile );
    }

    /**
     * Resolves public profile URL for the provided agent.
     */
    public static function profile_url_from_user( WP_User $user ): string {
        if ( ! $user instanceof WP_User ) {
            return '';
        }

        if ( ! in_array( 'estate_office_agent', (array) $user->roles, true ) ) {
            return '';
        }

        $slug = $user->user_nicename ?: sanitize_title( $user->display_name ?: $user->user_login );

        if ( ! $slug ) {
            return '';
        }

        return home_url( trailingslashit( self::PROFILE_BASE . '/' . $slug ) );
    }

    /**
     * Determines whether the current request targets an agent profile.
     */
    private function is_agent_profile_request(): bool {
        return (bool) get_query_var( self::QUERY_VAR );
    }

    /**
     * Retrieves agent user by URL slug.
     */
    private function get_agent_by_slug( string $slug ): ?WP_User {
        if ( '' === $slug ) {
            return null;
        }

        $user = get_user_by( 'slug', $slug );

        if ( ! $user || ! in_array( 'estate_office_agent', (array) $user->roles, true ) ) {
            return null;
        }

        return $user;
    }

    /**
     * Builds base agent context.
     *
     * @return array<string,string>
     */
    private function build_agent_context( WP_User $user, bool $include_profile ): array {
        $name = trim( $user->first_name . ' ' . $user->last_name );

        if ( '' === $name ) {
            $name = $user->display_name ?: $user->user_login;
        }

        $phone    = (string) get_user_meta( $user->ID, Keys::AGENT_PHONE, true );
        $bio      = (string) get_user_meta( $user->ID, Keys::AGENT_BIO, true );
        $photo_id = (int) get_user_meta( $user->ID, Keys::AGENT_PHOTO_ID, true );
        $photo    = $photo_id ? wp_get_attachment_image_url( $photo_id, 'large' ) : '';

        return [
            'id'         => (string) $user->ID,
            'name'       => $name,
            'email'      => $user->user_email,
            'phone'      => $phone,
            'phone_href' => $this->format_phone_href( $phone ),
            'bio'        => $bio,
            'photo'      => $photo ?: '',
            'profile_url'=> $include_profile ? self::profile_url_from_user( $user ) : '',
        ];
    }

    /**
     * Queries properties assigned to the agent for presentation on the profile.
     *
     * @return array<int,array<string,string|int>>
     */
    private function query_agent_properties( int $user_id ): array {
        $query = new WP_Query(
            [
                'post_type'      => 'estate_property',
                'post_status'    => 'publish',
                'author'         => $user_id,
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

        $properties = [];

        foreach ( $query->posts as $post ) {
            if ( ! $post instanceof WP_Post ) {
                continue;
            }

            $badges = get_post_meta( $post->ID, Keys::PROPERTY_BADGES, true );
            $badges = is_array( $badges ) ? array_map( 'sanitize_key', $badges ) : [];

            if ( ! in_array( 'export_www', $badges, true ) ) {
                continue;
            }

            $address_meta = get_post_meta( $post->ID, Keys::PROPERTY_ADDRESS, true );
            $address_meta = is_array( $address_meta ) ? $address_meta : [];

            $address = [
                'street'      => $address_meta['street'] ?? '',
                'number'      => $address_meta['number'] ?? '',
                'unit'        => $address_meta['unit'] ?? '',
                'postal_code' => $address_meta['postal_code'] ?? '',
                'city'        => $address_meta['city'] ?? '',
                'district'    => $address_meta['district'] ?? '',
            ];

            $properties[] = [
                'reference' => (string) get_post_meta( $post->ID, Keys::PROPERTY_REFERENCE, true ),
                'address'   => $this->format_address_label( $address ),
                'price'     => $this->format_price( (float) get_post_meta( $post->ID, Keys::PROPERTY_PRICE, true ) ),
                'area'      => $this->format_area( (float) get_post_meta( $post->ID, Keys::PROPERTY_AREA, true ) ),
                'rooms'     => (int) get_post_meta( $post->ID, Keys::PROPERTY_ROOMS, true ),
                'permalink' => $this->resolve_offer_permalink( $post ),
            ];
        }

        wp_reset_postdata();

        return $properties;
    }

    /**
     * Resolves offer permalink for a property.
     */
    private function resolve_offer_permalink( WP_Post $property ): string {
        $offer_id = (int) get_post_meta( $property->ID, Keys::PROPERTY_OFFER_POST, true );

        if ( $offer_id ) {
            $permalink = get_permalink( $offer_id );

            if ( $permalink ) {
                return $permalink;
            }
        }

        return get_permalink( $property );
    }

    /**
     * Formats numeric price to label.
     */
    private function format_price( float $price ): string {
        if ( $price <= 0 ) {
            return __( 'Do uzgodnienia', 'estate-office' );
        }

        return number_format_i18n( $price, 0 ) . ' PLN';
    }

    /**
     * Formats numeric area to label.
     */
    private function format_area( float $area ): string {
        if ( $area <= 0 ) {
            return '—';
        }

        return number_format_i18n( $area, 2 ) . ' m²';
    }

    /**
     * Formats address array to string.
     */
    private function format_address_label( array $address ): string {
        $parts = [];

        if ( $address['street'] ) {
            $street = $address['street'];

            if ( $address['number'] ) {
                $street .= ' ' . $address['number'];
            }

            if ( $address['unit'] ) {
                $street .= '/' . $address['unit'];
            }

            $parts[] = $street;
        }

        if ( $address['postal_code'] || $address['city'] ) {
            $parts[] = trim( $address['postal_code'] . ' ' . $address['city'] );
        }

        if ( $address['district'] ) {
            $parts[] = $address['district'];
        }

        return implode( ', ', array_filter( $parts ) );
    }

    /**
     * Prepares sanitized phone href value.
     */
    private function format_phone_href( string $phone ): string {
        $phone = preg_replace( '/[^0-9+]/', '', $phone );
        return $phone ?: '';
    }
}
