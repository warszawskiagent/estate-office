<?php
namespace EstateOffice\Admin;

use EstateOffice\Roles;

/**
 * Registers admin menu.
 */
class Menu {
/**
 * Constructor.
 */
public function __construct() {
add_action( 'admin_menu', array( $this, 'register_menu' ) );
}

/**
 * Register admin menu pages.
 *
 * @return void
 */
public function register_menu() {
add_menu_page(
__( 'Estate Office CRM', 'estate-office' ),
__( 'Estate Office CRM', 'estate-office' ),
'estate_office_access',
'estate-office-crm',
array( $this, 'render_dashboard' ),
'dashicons-building',
25
);

add_submenu_page(
'estate-office-crm',
__( 'Pulpit', 'estate-office' ),
__( 'Pulpit', 'estate-office' ),
'estate_office_access',
'estate-office-crm',
array( $this, 'render_dashboard' )
);

add_submenu_page(
'estate-office-crm',
__( 'Agenci', 'estate-office' ),
__( 'Agenci', 'estate-office' ),
'estate_office_manage_agents',
'estate-office-agents',
array( $this, 'render_agents' )
);

add_submenu_page(
'estate-office-crm',
__( 'Ustawienia', 'estate-office' ),
__( 'Ustawienia', 'estate-office' ),
'manage_options',
'estate-office-settings',
array( $this, 'render_settings' )
);

add_submenu_page(
'estate-office-crm',
__( 'About', 'estate-office' ),
__( 'About', 'estate-office' ),
'estate_office_access',
'estate-office-about',
array( $this, 'render_about' )
);
}

/**
 * Capability wrapper for general access.
 *
 * @return string
 */
protected function get_general_cap() {
return current_user_can( 'manage_options' ) ? 'manage_options' : 'estate_office_access';
}

/**
 * Render dashboard view placeholder.
 *
 * @return void
 */
public function render_dashboard() {
require ESTATE_OFFICE_PATH . 'includes/admin/views/dashboard.php';
}

/**
 * Render agents page.
 *
 * @return void
 */
public function render_agents() {
require ESTATE_OFFICE_PATH . 'includes/admin/views/agents.php';
}

/**
 * Render settings page.
 *
 * @return void
 */
public function render_settings() {
require ESTATE_OFFICE_PATH . 'includes/admin/views/settings.php';
}

/**
 * Render about page.
 *
 * @return void
 */
public function render_about() {
require ESTATE_OFFICE_PATH . 'includes/admin/views/about.php';
}
}
