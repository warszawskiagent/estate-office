<?php
namespace EstateOffice\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles public agent profile pages.
 */
class Agents {
    /**
     * Listings helper.
     *
     * @var Listings
     */
    private $listings;

    /**
     * Constructor.
     *
     * @param Listings $listings Listings instance.
     */
    public function __construct( Listings $listings ) {
        $this->listings = $listings;

        add_action( 'init', [ self::class, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
        add_action( 'template_include', [ $this, 'maybe_render_agent_template' ] );
    }

    /**
     * Registers rewrite rules for agent profile pages.
     */
    public static function add_rewrite_rules(): void {
        add_rewrite_tag( '%estate_office_agent%', '([^&]+)' );
        add_rewrite_rule( '^agent/([^/]+)/?$', 'index.php?estate_office_agent=$matches[1]', 'top' );
    }

    /**
     * Adds query var for agent slug.
     *
     * @param array<int, string> $vars Query vars.
     * @return array<int, string>
     */
    public function register_query_vars( array $vars ): array {
        $vars[] = 'estate_office_agent';

        return $vars;
    }

    /**
     * Determines whether to use custom agent template.
     *
     * @param string $template Current template.
     * @return string
     */
    public function maybe_render_agent_template( string $template ): string {
        $slug = get_query_var( 'estate_office_agent' );
        if ( empty( $slug ) ) {
            return $template;
        }

        $profile = $this->get_agent_profile( sanitize_title_for_query( $slug ) );
        if ( ! $profile ) {
            global $wp_query;
            if ( $wp_query ) {
                $wp_query->set_404();
            }
            status_header( 404 );

            return get_404_template() ?: $template;
        }

        wp_enqueue_style( 'estate-office-listings' );
        set_query_var( 'estate_office_agent_data', $profile );

        return ESTATE_OFFICE_PLUGIN_DIR . 'includes/frontend/views/single-agent.php';
    }

    /**
     * Builds full agent profile data array.
     *
     * @param string $slug Agent slug.
     * @return array<string, mixed>|null
     */
    private function get_agent_profile( string $slug ): ?array {
        $user = get_user_by( 'slug', $slug );
        if ( ! $user || ! in_array( 'estate_agent', (array) $user->roles, true ) ) {
            return null;
        }

        $phone     = get_user_meta( $user->ID, '_estate_office_phone', true );
        $bio       = get_user_meta( $user->ID, '_estate_office_bio', true );
        $avatar_id = (int) get_user_meta( $user->ID, '_estate_office_avatar_id', true );

        $avatar_html = $avatar_id
            ? wp_get_attachment_image( $avatar_id, 'large', false, [ 'class' => 'estate-office-agent-avatar' ] )
            : get_avatar( $user->ID, 256, '', $user->display_name, [ 'class' => 'estate-office-agent-avatar' ] );

        $avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'large' ) : get_avatar_url( $user->ID );
        $avatar_alt = $avatar_id ? get_post_meta( $avatar_id, '_wp_attachment_image_alt', true ) : $user->display_name;

        $offers = $this->get_agent_offers( $user->ID );

        return [
            'name'                => $user->display_name,
            'email'               => $user->user_email,
            'phone'               => $phone,
            'bio'                 => $bio,
            'avatar_html'         => $avatar_html,
            'avatar_url'          => $avatar_url,
            'avatar_alt'          => $avatar_alt,
            'offers'              => $offers,
            'listings'            => $this->listings,
            'transaction_labels'  => $this->get_transaction_labels( array_keys( $offers ) ),
        ];
    }

    /**
     * Returns offers grouped by transaction type for a given agent.
     *
     * @param int $agent_id Agent ID.
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function get_agent_offers( int $agent_id ): array {
        $args = [
            'post_type'      => 'estate_property',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => 'estate_office_flags',
                    'value'   => 'export_www',
                    'compare' => 'LIKE',
                ],
                [
                    'key'   => 'estate_office_agent_id',
                    'value' => $agent_id,
                ],
            ],
        ];

        $posts   = get_posts( $args );
        $grouped = [];

        foreach ( $posts as $post ) {
            $prepared    = $this->listings->prepare_card_entry( $post );
            $transaction = $prepared['transaction'];
            $grouped[ $transaction ][] = $prepared['entry'];
        }

        return $grouped;
    }

    /**
     * Maps transaction labels for template usage.
     *
     * @param array<int, string> $transactions Transaction keys.
     * @return array<string, string>
     */
    private function get_transaction_labels( array $transactions ): array {
        $labels = [];

        foreach ( array_unique( $transactions ) as $transaction ) {
            $labels[ $transaction ] = $this->listings->get_transaction_label( $transaction );
        }

        return $labels;
    }
}
