<?php
namespace EstateOffice\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers custom post types used by the plugin.
 */
class Registrar {
    /**
     * Registers all CPTs.
     */
    public function register(): void {
        add_action( 'estate_office_register_post_types', [ $this, 'register_post_types' ] );
        $this->register_post_types();
    }

    /**
     * Registers the custom post types.
     */
    public function register_post_types(): void {
        $this->register_properties();
        $this->register_contracts();
        $this->register_clients();
        $this->register_searches();
    }

    /**
     * Register property CPT.
     */
    private function register_properties(): void {
        $labels = [
            'name'               => __( 'Nieruchomości', 'estate-office' ),
            'singular_name'      => __( 'Nieruchomość', 'estate-office' ),
            'add_new'            => __( 'Dodaj nową', 'estate-office' ),
            'add_new_item'       => __( 'Dodaj nieruchomość', 'estate-office' ),
            'edit_item'          => __( 'Edytuj nieruchomość', 'estate-office' ),
            'new_item'           => __( 'Nowa nieruchomość', 'estate-office' ),
            'view_item'          => __( 'Zobacz nieruchomość', 'estate-office' ),
            'search_items'       => __( 'Szukaj nieruchomości', 'estate-office' ),
            'not_found'          => __( 'Nie znaleziono nieruchomości', 'estate-office' ),
            'not_found_in_trash' => __( 'Brak nieruchomości w koszu', 'estate-office' ),
            'menu_name'          => __( 'Nieruchomości', 'estate-office' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'supports'           => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
        ];

        register_post_type( 'estate_property', $args );
    }

    /**
     * Register contract CPT.
     */
    private function register_contracts(): void {
        $labels = [
            'name'               => __( 'Umowy', 'estate-office' ),
            'singular_name'      => __( 'Umowa', 'estate-office' ),
            'add_new'            => __( 'Dodaj nową', 'estate-office' ),
            'add_new_item'       => __( 'Dodaj umowę', 'estate-office' ),
            'edit_item'          => __( 'Edytuj umowę', 'estate-office' ),
            'new_item'           => __( 'Nowa umowa', 'estate-office' ),
            'view_item'          => __( 'Zobacz umowę', 'estate-office' ),
            'search_items'       => __( 'Szukaj umów', 'estate-office' ),
            'not_found'          => __( 'Nie znaleziono umów', 'estate-office' ),
            'not_found_in_trash' => __( 'Brak umów w koszu', 'estate-office' ),
            'menu_name'          => __( 'Umowy', 'estate-office' ),
        ];

        $args = [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'supports'        => [ 'title', 'editor', 'custom-fields' ],
            'has_archive'     => false,
            'rewrite'         => false,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ];

        register_post_type( 'estate_contract', $args );
    }

    /**
     * Register client CPT.
     */
    private function register_clients(): void {
        $labels = [
            'name'               => __( 'Klienci', 'estate-office' ),
            'singular_name'      => __( 'Klient', 'estate-office' ),
            'add_new'            => __( 'Dodaj nowego', 'estate-office' ),
            'add_new_item'       => __( 'Dodaj klienta', 'estate-office' ),
            'edit_item'          => __( 'Edytuj klienta', 'estate-office' ),
            'new_item'           => __( 'Nowy klient', 'estate-office' ),
            'view_item'          => __( 'Zobacz klienta', 'estate-office' ),
            'search_items'       => __( 'Szukaj klientów', 'estate-office' ),
            'not_found'          => __( 'Nie znaleziono klientów', 'estate-office' ),
            'not_found_in_trash' => __( 'Brak klientów w koszu', 'estate-office' ),
            'menu_name'          => __( 'Klienci', 'estate-office' ),
        ];

        $args = [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'supports'        => [ 'title', 'editor', 'custom-fields' ],
            'has_archive'     => false,
            'rewrite'         => false,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ];

        register_post_type( 'estate_client', $args );
    }

    /**
     * Register search CPT.
     */
    private function register_searches(): void {
        $labels = [
            'name'               => __( 'Poszukiwania', 'estate-office' ),
            'singular_name'      => __( 'Poszukiwanie', 'estate-office' ),
            'add_new'            => __( 'Dodaj nowe', 'estate-office' ),
            'add_new_item'       => __( 'Dodaj poszukiwanie', 'estate-office' ),
            'edit_item'          => __( 'Edytuj poszukiwanie', 'estate-office' ),
            'new_item'           => __( 'Nowe poszukiwanie', 'estate-office' ),
            'view_item'          => __( 'Zobacz poszukiwanie', 'estate-office' ),
            'search_items'       => __( 'Szukaj poszukiwań', 'estate-office' ),
            'not_found'          => __( 'Nie znaleziono poszukiwań', 'estate-office' ),
            'not_found_in_trash' => __( 'Brak poszukiwań w koszu', 'estate-office' ),
            'menu_name'          => __( 'Poszukiwania', 'estate-office' ),
        ];

        $args = [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'supports'        => [ 'title', 'editor', 'custom-fields' ],
            'has_archive'     => false,
            'rewrite'         => false,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ];

        register_post_type( 'estate_search', $args );
    }
}
