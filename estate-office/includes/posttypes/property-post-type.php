<?php
/**
 * Property post type.
 *
 * @package EstateOffice\PostTypes
 */

namespace EstateOffice\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers estate property post type.
 */
class PropertyPostType {
/**
 * Register post type.
 */
public static function register() {
$labels = [
'name'               => __( 'Nieruchomości', 'estateoffice' ),
'singular_name'      => __( 'Nieruchomość', 'estateoffice' ),
'add_new'            => __( 'Dodaj nową', 'estateoffice' ),
'add_new_item'       => __( 'Dodaj nową nieruchomość', 'estateoffice' ),
'edit_item'          => __( 'Edytuj nieruchomość', 'estateoffice' ),
'new_item'           => __( 'Nowa nieruchomość', 'estateoffice' ),
'view_item'          => __( 'Zobacz nieruchomość', 'estateoffice' ),
'search_items'       => __( 'Szukaj nieruchomości', 'estateoffice' ),
'not_found'          => __( 'Nie znaleziono nieruchomości', 'estateoffice' ),
'not_found_in_trash' => __( 'Nie znaleziono nieruchomości w koszu', 'estateoffice' ),
'all_items'          => __( 'Wszystkie nieruchomości', 'estateoffice' ),
];

$args = [
'labels'              => $labels,
'public'              => false,
'show_ui'             => true,
'show_in_menu'        => false,
'supports'            => [ 'title', 'editor', 'thumbnail' ],
'capability_type'     => [ 'estate_property', 'estate_properties' ],
'map_meta_cap'        => true,
];

register_post_type( 'estate_property', $args );
}
}
