<?php
/**
 * Client post type.
 *
 * @package EstateOffice\PostTypes
 */

namespace EstateOffice\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers client post type.
 */
class ClientPostType {
/**
 * Register post type.
 */
public static function register() {
$labels = [
'name'               => __( 'Klienci', 'estateoffice' ),
'singular_name'      => __( 'Klient', 'estateoffice' ),
'add_new'            => __( 'Dodaj nowego', 'estateoffice' ),
'add_new_item'       => __( 'Dodaj klienta', 'estateoffice' ),
'edit_item'          => __( 'Edytuj klienta', 'estateoffice' ),
'new_item'           => __( 'Nowy klient', 'estateoffice' ),
'view_item'          => __( 'Zobacz klienta', 'estateoffice' ),
'search_items'       => __( 'Szukaj klientów', 'estateoffice' ),
'not_found'          => __( 'Nie znaleziono klientów', 'estateoffice' ),
'not_found_in_trash' => __( 'Nie znaleziono klientów w koszu', 'estateoffice' ),
'all_items'          => __( 'Wszyscy klienci', 'estateoffice' ),
];

$args = [
'labels'          => $labels,
'public'          => false,
'show_ui'         => true,
'show_in_menu'    => false,
'supports'        => [ 'title', 'editor' ],
'capability_type' => [ 'estate_client', 'estate_clients' ],
'map_meta_cap'    => true,
];

register_post_type( 'estate_client', $args );
}
}
