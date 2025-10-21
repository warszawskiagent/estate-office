<?php
namespace EstateOffice;

/**
 * Registers custom post types and taxonomies.
 */
class Post_Types {
/**
 * Constructor.
 */
public function __construct() {
add_action( 'init', array( $this, 'register_post_types' ) );
add_action( 'init', array( $this, 'register_taxonomies' ) );
}

/**
 * Register custom post types used by plugin.
 *
 * @return void
 */
public function register_post_types() {
$this->register_contract_post_type();
$this->register_client_post_type();
$this->register_property_post_type();
$this->register_requirement_post_type();
}

/**
 * Register Estate Contract post type.
 *
 * @return void
 */
protected function register_contract_post_type() {
register_post_type(
'estate_contract',
array(
'labels' => array(
'name'               => __( 'Umowy', 'estate-office' ),
'singular_name'      => __( 'Umowa', 'estate-office' ),
'add_new_item'       => __( 'Dodaj nową umowę', 'estate-office' ),
'edit_item'          => __( 'Edytuj umowę', 'estate-office' ),
'new_item'           => __( 'Nowa umowa', 'estate-office' ),
'view_item'          => __( 'Zobacz umowę', 'estate-office' ),
'view_items'         => __( 'Zobacz umowy', 'estate-office' ),
'not_found'          => __( 'Nie znaleziono umów', 'estate-office' ),
'menu_name'          => __( 'Umowy', 'estate-office' ),
),
'supports'            => array( 'title', 'editor' ),
'show_ui'             => false,
'capability_type'     => array( 'estate_contract', 'estate_contracts' ),
'map_meta_cap'        => true,
'show_in_rest'        => false,
'public'              => false,
'rewrite'             => false,
)
);
}

/**
 * Register Estate Client post type.
 *
 * @return void
 */
protected function register_client_post_type() {
register_post_type(
'estate_client',
array(
'labels' => array(
'name'               => __( 'Klienci', 'estate-office' ),
'singular_name'      => __( 'Klient', 'estate-office' ),
'add_new_item'       => __( 'Dodaj nowego klienta', 'estate-office' ),
'edit_item'          => __( 'Edytuj klienta', 'estate-office' ),
'view_item'          => __( 'Zobacz klienta', 'estate-office' ),
'not_found'          => __( 'Nie znaleziono klientów', 'estate-office' ),
),
'supports'            => array( 'title', 'editor' ),
'show_ui'             => false,
'capability_type'     => array( 'estate_client', 'estate_clients' ),
'map_meta_cap'        => true,
'show_in_rest'        => false,
'public'              => false,
'rewrite'             => false,
)
);
}

/**
 * Register Estate Property post type.
 *
 * @return void
 */
protected function register_property_post_type() {
register_post_type(
'estate_property',
array(
'labels' => array(
'name'               => __( 'Nieruchomości', 'estate-office' ),
'singular_name'      => __( 'Nieruchomość', 'estate-office' ),
'add_new_item'       => __( 'Dodaj nieruchomość', 'estate-office' ),
'edit_item'          => __( 'Edytuj nieruchomość', 'estate-office' ),
'view_item'          => __( 'Zobacz nieruchomość', 'estate-office' ),
'not_found'          => __( 'Nie znaleziono nieruchomości', 'estate-office' ),
),
'supports'            => array( 'title', 'editor', 'thumbnail' ),
'show_ui'             => false,
'capability_type'     => array( 'estate_property', 'estate_properties' ),
'map_meta_cap'        => true,
'public'              => true,
'has_archive'         => false,
'show_in_rest'        => true,
'rewrite'             => array( 'slug' => 'oferty' ),
)
);
}

/**
 * Register Estate Requirement post type.
 *
 * @return void
 */
protected function register_requirement_post_type() {
register_post_type(
'estate_requirement',
array(
'labels' => array(
'name'          => __( 'Poszukiwania', 'estate-office' ),
'singular_name' => __( 'Poszukiwanie', 'estate-office' ),
),
'supports'        => array( 'title', 'editor' ),
'show_ui'         => false,
'capability_type' => array( 'estate_requirement', 'estate_requirements' ),
'map_meta_cap'    => true,
'public'          => false,
'rewrite'         => false,
)
);
}

/**
 * Register taxonomies for property categorisation.
 *
 * @return void
 */
public function register_taxonomies() {
register_taxonomy(
'estate_transaction_type',
'estate_property',
array(
'label'        => __( 'Typ transakcji', 'estate-office' ),
'public'       => true,
'hierarchical' => true,
)
);

register_taxonomy(
'estate_property_type',
'estate_property',
array(
'label'        => __( 'Rodzaj nieruchomości', 'estate-office' ),
'public'       => true,
'hierarchical' => true,
)
);

register_taxonomy(
'estate_city',
'estate_property',
array(
'label'        => __( 'Miasto', 'estate-office' ),
'public'       => true,
'hierarchical' => true,
)
);

register_taxonomy(
'estate_district',
'estate_property',
array(
'label'        => __( 'Dzielnica', 'estate-office' ),
'public'       => true,
'hierarchical' => true,
)
);
}
}
