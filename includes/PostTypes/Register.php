<?php
namespace EstateOffice\PostTypes;

/**
 * Registers the custom post types used by the CRM.
 */
class Register {
    /**
     * Registers all custom post types and taxonomies.
     */
    public function register(): void {
        $this->register_properties();
        $this->register_contracts();
        $this->register_clients();
        $this->register_searches();
        $this->register_taxonomies();
    }

    private function register_properties(): void {
        register_post_type(
            'estate_property',
            [
                'label'               => __( 'Nieruchomości', 'estate-office' ),
                'labels'              => [
                    'name'          => __( 'Nieruchomości', 'estate-office' ),
                    'singular_name' => __( 'Nieruchomość', 'estate-office' ),
                    'add_new_item'  => __( 'Dodaj nieruchomość', 'estate-office' ),
                ],
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => 'estate-office-crm',
                'supports'            => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'show_in_rest'        => true,
                'rewrite'             => false,
            ]
        );
    }

    private function register_contracts(): void {
        register_post_type(
            'estate_contract',
            [
                'label'           => __( 'Umowy', 'estate-office' ),
                'labels'          => [
                    'name'          => __( 'Umowy', 'estate-office' ),
                    'singular_name' => __( 'Umowa', 'estate-office' ),
                    'add_new_item'  => __( 'Dodaj umowę', 'estate-office' ),
                ],
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => 'estate-office-crm',
                'supports'        => [ 'title', 'custom-fields' ],
                'capability_type' => 'post',
                'map_meta_cap'    => true,
                'show_in_rest'    => true,
                'rewrite'         => false,
            ]
        );
    }

    private function register_clients(): void {
        register_post_type(
            'estate_client',
            [
                'label'           => __( 'Klienci', 'estate-office' ),
                'labels'          => [
                    'name'          => __( 'Klienci', 'estate-office' ),
                    'singular_name' => __( 'Klient', 'estate-office' ),
                    'add_new_item'  => __( 'Dodaj klienta', 'estate-office' ),
                ],
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => 'estate-office-crm',
                'supports'        => [ 'title', 'editor', 'custom-fields' ],
                'capability_type' => 'post',
                'map_meta_cap'    => true,
                'show_in_rest'    => true,
                'rewrite'         => false,
            ]
        );
    }

    private function register_searches(): void {
        register_post_type(
            'estate_search',
            [
                'label'           => __( 'Poszukiwania', 'estate-office' ),
                'labels'          => [
                    'name'          => __( 'Poszukiwania', 'estate-office' ),
                    'singular_name' => __( 'Poszukiwanie', 'estate-office' ),
                    'add_new_item'  => __( 'Dodaj poszukiwanie', 'estate-office' ),
                ],
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => 'estate-office-crm',
                'supports'        => [ 'title', 'editor', 'custom-fields' ],
                'capability_type' => 'post',
                'map_meta_cap'    => true,
                'show_in_rest'    => true,
                'rewrite'         => false,
            ]
        );
    }

    private function register_taxonomies(): void {
        register_taxonomy(
            'estate_property_transaction',
            [ 'estate_property' ],
            [
                'labels'            => [
                    'name'          => __( 'Typ transakcji', 'estate-office' ),
                    'singular_name' => __( 'Typ transakcji', 'estate-office' ),
                ],
                'public'            => false,
                'show_ui'           => true,
                'show_in_menu'      => false,
                'show_in_nav_menus' => false,
                'show_admin_column' => true,
                'show_tagcloud'     => false,
            ]
        );

        register_taxonomy(
            'estate_property_kind',
            [ 'estate_property' ],
            [
                'labels'            => [
                    'name'          => __( 'Rodzaj nieruchomości', 'estate-office' ),
                    'singular_name' => __( 'Rodzaj nieruchomości', 'estate-office' ),
                ],
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_tagcloud'     => false,
            ]
        );

        register_taxonomy(
            'estate_property_city',
            [ 'estate_property' ],
            [
                'labels'            => [
                    'name'          => __( 'Miasto', 'estate-office' ),
                    'singular_name' => __( 'Miasto', 'estate-office' ),
                ],
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_tagcloud'     => false,
            ]
        );

        register_taxonomy(
            'estate_property_district',
            [ 'estate_property' ],
            [
                'labels'            => [
                    'name'          => __( 'Dzielnica', 'estate-office' ),
                    'singular_name' => __( 'Dzielnica', 'estate-office' ),
                ],
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_tagcloud'     => false,
            ]
        );
    }
}
