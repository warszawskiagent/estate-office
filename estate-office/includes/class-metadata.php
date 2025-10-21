<?php
namespace EstateOffice;

/**
 * Registers meta fields used across plugin entities.
 */
class Metadata {
/**
 * Constructor.
 */
public function __construct() {
add_action( 'init', array( $this, 'register_user_meta' ) );
}

/**
 * Register user meta for agents.
 *
 * @return void
 */
public function register_user_meta() {
register_meta(
'user',
'estate_office_phone',
array(
'show_in_rest'       => true,
'single'             => true,
'type'               => 'string',
'sanitize_callback'  => 'sanitize_text_field',
'auth_callback'      => function( $allowed, $meta_key, $user_id, $cap ) {
return current_user_can( 'estate_office_manage_agents' );
},
)
);

register_meta(
'user',
'estate_office_bio',
array(
'show_in_rest'       => true,
'single'             => true,
'type'               => 'string',
'sanitize_callback'  => 'wp_kses_post',
'auth_callback'      => function() {
return current_user_can( 'estate_office_manage_agents' );
},
)
);

register_meta(
'user',
'estate_office_avatar',
array(
'show_in_rest'       => true,
'single'             => true,
'type'               => 'integer',
'sanitize_callback'  => 'absint',
'auth_callback'      => function() {
return current_user_can( 'estate_office_manage_agents' );
},
)
);
}
}
