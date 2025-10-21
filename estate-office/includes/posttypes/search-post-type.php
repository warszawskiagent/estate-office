<?php
/**
 * Search criteria post type.
 *
 * @package EstateOffice\PostTypes
 */

namespace EstateOffice\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers search post type.
 */
class SearchPostType {
/**
 * Register post type.
 */
public static function register() {
$labels = [
'name'               => __( 'Poszukiwania', 'estateoffice' ),
'singular_name'      => __( 'Poszukiwanie', 'estateoffice' ),
'add_new'            => __( 'Dodaj nowe', 'estateoffice' ),
'add_new_item'       => __( 'Dodaj poszukiwanie', 'estateoffice' ),
'edit_item'          => __( 'Edytuj poszukiwanie', 'estateoffice' ),
'new_item'           => __( 'Nowe poszukiwanie', 'estateoffice' ),
'view_item'          => __( 'Zobacz poszukiwanie', 'estateoffice' ),
'search_items'       => __( 'Szukaj poszukiwań', 'estateoffice' ),
'not_found'          => __( 'Nie znaleziono poszukiwań', 'estateoffice' ),
'not_found_in_trash' => __( 'Nie znaleziono poszukiwań w koszu', 'estateoffice' ),
'all_items'          => __( 'Wszystkie poszukiwania', 'estateoffice' ),
];

$args = [
'labels'          => $labels,
'public'          => false,
'show_ui'         => true,
'show_in_menu'    => false,
'supports'        => [ 'title', 'editor' ],
'capability_type' => [ 'estate_search', 'estate_searches' ],
'map_meta_cap'    => true,
];

register_post_type( 'estate_search', $args );
}
}
