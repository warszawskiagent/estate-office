<?php
namespace EstateOffice;

use WP_Roles;

/**
 * Plugin activation handler.
 */
class Activator {
/**
 * Execute activation hooks.
 *
 * @return void
 */
public static function activate() {
self::create_roles();
flush_rewrite_rules();
}

/**
 * Create custom roles and capabilities.
 *
 * @return void
 */
protected static function create_roles() {
$roles = new Roles();
$roles->register_roles();

// Ensure capabilities are applied to administrator and editor roles.
$capabilities = $roles->get_capabilities();
$wp_roles     = wp_roles();

foreach ( array( 'administrator', 'editor' ) as $role_key ) {
$role = $wp_roles->get_role( $role_key );

if ( ! $role instanceof \WP_Role ) {
continue;
}

foreach ( $capabilities as $cap => $grant ) {
if ( $grant ) {
$role->add_cap( $cap );
}
}
}
}
}
