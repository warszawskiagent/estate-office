<?php
/**
 * Agent role registration.
 *
 * @package EstateOffice\Roles
 */

namespace EstateOffice\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Handles agent role and capabilities.
 */
class AgentRole {
/**
 * Role key.
 */
const ROLE = 'estate_agent';

/**
 * Activation hook callback.
 */
public static function activate() {
self::register_role();
self::register_caps();
self::grant_admin_caps();
}

/**
 * Deactivation hook callback.
 */
public static function deactivate() {
remove_role( self::ROLE );
}

/**
 * Register custom role.
 */
public static function register_role() {
if ( get_role( self::ROLE ) ) {
return;
}

add_role(
self::ROLE,
__( 'Agent nieruchomości', 'estateoffice' ),
self::get_capabilities()
);
}

/**
 * Assign capabilities to role.
 */
public static function register_caps() {
$role = get_role( self::ROLE );
if ( ! $role ) {
return;
}

foreach ( self::get_capabilities() as $cap => $grant ) {
if ( $grant ) {
$role->add_cap( $cap );
} else {
$role->remove_cap( $cap );
}
}

self::grant_admin_caps();
}

/**
 * Ensure administrators have access to CRM entities.
 */
private static function grant_admin_caps() {
$admin = get_role( 'administrator' );
if ( ! $admin ) {
return;
}

foreach ( self::get_capabilities() as $cap => $grant ) {
if ( $grant ) {
$admin->add_cap( $cap );
}
}
}

/**
 * Returns capabilities map.
 *
 * @return array
 */
private static function get_capabilities() {
$capabilities = [
'read'                   => true,
'edit_posts'             => false,
'delete_posts'           => false,
'upload_files'           => true,
'edit_estate_properties' => true,
'edit_estate_contracts'  => true,
'edit_estate_clients'    => true,
'edit_estate_searches'   => true,
'delete_estate_records'  => false,
];

/**
 * Filter agent role capabilities.
 *
 * @param array $capabilities Default capabilities.
 */
return apply_filters( 'estateoffice_agent_capabilities', $capabilities );
}
}
