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
    private const CRM_VIEW           = 'crm';

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
     * Register custom rewrite rules required for the CRM application.
     *
     * @return void
     */
    public function register_rewrite_rules(): void {
        add_rewrite_rule(
            '^crm/?$',
            'index.php?estate_office_view=' . self::CRM_VIEW,
            'top'
        );
    }

    /**
     * Expose the plugin query variables.
     *
     * @param array $vars Query vars.
     *
     * @return array
     */
    public function register_query_vars( array $vars ): array {
        $vars[] = 'estate_office_view';

        return $vars;
    }

    /**
     * Maybe render the CRM template.
     *
     * @param string $template Current template path.
     *
     * @return string
     */
    public function maybe_use_crm_template( string $template ): string {
        $view = get_query_var( 'estate_office_view' );

        if ( self::CRM_VIEW !== $view ) {
            return $template;
        }

        if ( ! is_user_logged_in() ) {
            auth_redirect();
        }

        if ( ! $this->user_can_access_crm() ) {
            wp_die( esc_html__( 'Nie masz uprawnień do wyświetlenia panelu CRM.', 'estate-office' ), 403 );
        }

        nocache_headers();

        return $this->get_template_path( 'crm.php' );
    }

    /**
     * Provide plugin single template for public properties.
     *
     * @param string $template Default template.
     *
     * @return string
     */
    public function filter_single_property_template( string $template ): string {
        if ( ! is_singular( self::PROPERTY_POST_TYPE ) ) {
            return $template;
        }

        return $this->get_template_path( 'single-property.php' );
    }

    /**
     * Provide plugin archive template for public properties.
     *
     * @param string $template Default template.
     *
     * @return string
     */
    public function filter_archive_property_template( string $template ): string {
        if ( ! is_post_type_archive( self::PROPERTY_POST_TYPE ) ) {
            return $template;
        }

        return $this->get_template_path( 'archive-property.php' );
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

    /**
     * Determine whether the current user can access the CRM.
     *
     * @return bool
     */
    private function user_can_access_crm(): bool {
        $can_access = current_user_can( 'manage_options' ) || current_user_can( 'access_estate_office_crm' );

        /**
         * Filters whether the logged in user can access the CRM view.
         *
         * @param bool $can_access Whether the user can access the CRM.
         */
        return (bool) apply_filters( 'estate_office_user_can_access_crm', $can_access );
    }

    /**
     * Retrieve a template path with theme override support.
     *
     * @param string $template Template file relative to the plugin templates directory.
     *
     * @return string
     */
    private function get_template_path( string $template ): string {
        $template = ltrim( $template, '/' );

        $located = locate_template(
            [
                'estate-office/' . $template,
                $template,
            ]
        );

        if ( ! empty( $located ) ) {
            return $located;
        }

        return ESTATE_OFFICE_PLUGIN_DIR . '/templates/' . $template;
    }
}
