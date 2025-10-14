<?php
/**
 * Frontend functionality.
 *
 * @package EstateOffice
 */

namespace EstateOffice\PublicSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles CPT registrations and public hooks.
 */
class Frontend {
    private const PROPERTY_POST_TYPE = 'property_public';
    private const AGENT_POST_TYPE    = 'estate_agent';

    /**
     * Register custom post types used in public area.
     *
     * @return void
     */
    public function register_post_types(): void {
        $this->register_property_post_type();
        $this->register_agent_post_type();
    }

    /**
     * Register rewrite tags for CRM views.
     *
     * @return void
     */
    public function register_rewrite_tags(): void {
        add_rewrite_tag( '%estate_office_view%', '([a-z0-9-]+)' );
    }

    /**
     * Register property CPT.
     *
     * @return void
     */
    private function register_property_post_type(): void {
        register_post_type(
            self::PROPERTY_POST_TYPE,
            [
                'labels' => [
                    'name'          => __( 'Nieruchomości', 'estate-office' ),
                    'singular_name' => __( 'Nieruchomość', 'estate-office' ),
                ],
                'public'       => true,
                'has_archive'  => true,
                'show_in_rest' => true,
                'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
                'rewrite'      => [ 'slug' => 'nieruchomosci' ],
                'menu_icon'    => 'dashicons-admin-multisite',
            ]
        );
    }

    /**
     * Register agent CPT.
     *
     * @return void
     */
    private function register_agent_post_type(): void {
        register_post_type(
            self::AGENT_POST_TYPE,
            [
                'labels' => [
                    'name'          => __( 'Agenci', 'estate-office' ),
                    'singular_name' => __( 'Agent', 'estate-office' ),
                ],
                'public'       => false,
                'show_ui'      => true,
                'show_in_rest' => true,
                'supports'     => [ 'title', 'editor', 'thumbnail' ],
                'rewrite'      => false,
                'menu_icon'    => 'dashicons-businessman',
            ]
        );
    }
}
