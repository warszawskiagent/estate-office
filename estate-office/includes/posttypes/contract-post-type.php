<?php
/**
 * Contract post type.
 *
 * @package EstateOffice\PostTypes
 */

namespace EstateOffice\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers contract post type.
 */
class ContractPostType {
/**
 * Register post type.
 */
public static function register() {
$labels = [
'name'               => __( 'Umowy', 'estateoffice' ),
'singular_name'      => __( 'Umowa', 'estateoffice' ),
'add_new'            => __( 'Dodaj nową', 'estateoffice' ),
'add_new_item'       => __( 'Dodaj nową umowę', 'estateoffice' ),
'edit_item'          => __( 'Edytuj umowę', 'estateoffice' ),
'new_item'           => __( 'Nowa umowa', 'estateoffice' ),
'view_item'          => __( 'Zobacz umowę', 'estateoffice' ),
'search_items'       => __( 'Szukaj umów', 'estateoffice' ),
'not_found'          => __( 'Nie znaleziono umów', 'estateoffice' ),
'not_found_in_trash' => __( 'Nie znaleziono umów w koszu', 'estateoffice' ),
'all_items'          => __( 'Wszystkie umowy', 'estateoffice' ),
];

$args = [
'labels'          => $labels,
'public'          => false,
'show_ui'         => true,
'show_in_menu'    => false,
'supports'        => [ 'title', 'editor' ],
'capability_type' => [ 'estate_contract', 'estate_contracts' ],
'map_meta_cap'    => true,
];

register_post_type( 'estate_contract', $args );
}
}
