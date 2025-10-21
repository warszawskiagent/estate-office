<?php
namespace EstateOffice;

/**
 * Handle custom roles and capabilities.
 */
class Roles {
/**
 * Agent role name.
 */
const ROLE_AGENT = 'estate_agent';

/**
 * Capabilities map.
 *
 * @var array
 */
protected $capabilities = array(
'estate_office_access'            => true,
'estate_office_manage_agents'     => false,
'edit_estate_contract'            => true,
'edit_estate_contracts'           => true,
'edit_others_estate_contracts'    => false,
'publish_estate_contracts'        => true,
'edit_estate_property'            => true,
'edit_estate_properties'          => true,
'edit_others_estate_properties'   => false,
'publish_estate_properties'       => true,
'edit_estate_client'              => true,
'edit_estate_clients'             => true,
'edit_others_estate_clients'      => false,
'publish_estate_clients'          => true,
'edit_estate_requirement'         => true,
'edit_estate_requirements'        => true,
'edit_others_estate_requirements' => false,
'publish_estate_requirements'     => true,
'delete_estate_contract'          => false,
'delete_estate_property'          => false,
'delete_estate_client'            => false,
'delete_estate_requirement'       => false,
);

/**
 * Constructor.
 */
public function __construct() {
add_action( 'init', array( $this, 'register_roles' ) );
}

/**
 * Register custom roles.
 *
 * @return void
 */
public function register_roles() {
if ( ! get_role( self::ROLE_AGENT ) ) {
add_role(
self::ROLE_AGENT,
__( 'Agent nieruchomości', 'estate-office' ),
$this->capabilities
);
}

// Ensure administrator retains all capabilities.
$admin = get_role( 'administrator' );

if ( $admin ) {
foreach ( $this->capabilities as $cap => $grant ) {
if ( $grant ) {
$admin->add_cap( $cap );
}
}
$admin->add_cap( 'estate_office_manage_agents' );
}
}

/**
 * Get capabilities array.
 *
 * @return array
 */
public function get_capabilities() {
return $this->capabilities;
}
}
