<?php
/**
 * Admin menu handler.
 *
 * @package EstateOffice\Admin
 */

namespace EstateOffice\Admin;

use EstateOffice\Admin\Pages\AboutPage;
use EstateOffice\Admin\Pages\SettingsPage;

defined( 'ABSPATH' ) || exit;

/**
 * Registers plugin admin menu.
 */
class Menu {
/**
 * Register menu and submenu pages.
 */
public static function register() {
$capability = 'manage_options';
$slug       = 'estateoffice-dashboard';

add_menu_page(
__( 'Estate Office CRM', 'estateoffice' ),
__( 'Estate Office CRM', 'estateoffice' ),
$capability,
$slug,
[ self::class, 'render_dashboard' ],
'dashicons-building'
);

add_submenu_page(
$slug,
__( 'Pulpit', 'estateoffice' ),
__( 'Pulpit', 'estateoffice' ),
$capability,
$slug,
[ self::class, 'render_dashboard' ]
);

add_submenu_page(
$slug,
__( 'Agenci', 'estateoffice' ),
__( 'Agenci', 'estateoffice' ),
$capability,
'estateoffice-agents',
[ self::class, 'render_agents' ]
);

add_submenu_page(
$slug,
__( 'Ustawienia', 'estateoffice' ),
__( 'Ustawienia', 'estateoffice' ),
$capability,
'estateoffice-settings',
[ SettingsPage::class, 'render' ]
);

add_submenu_page(
$slug,
__( 'About', 'estateoffice' ),
__( 'About', 'estateoffice' ),
$capability,
'estateoffice-about',
[ AboutPage::class, 'render' ]
);

add_submenu_page(
$slug,
__( 'Licencja', 'estateoffice' ),
__( 'Licencja', 'estateoffice' ),
$capability,
'estateoffice-license',
[ self::class, 'render_license' ]
);
}

/**
 * Render CRM dashboard placeholder.
 */
public static function render_dashboard() {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'Brak uprawnień do przeglądania tej strony.', 'estateoffice' ) );
}

printf(
'<div class="wrap"><h1>%s</h1><p>%s</p></div>',
esc_html__( 'Estate Office CRM', 'estateoffice' ),
esc_html__( 'Panel zostanie rozbudowany w kolejnych wersjach.', 'estateoffice' )
);
}

/**
 * Render agents placeholder.
 */
public static function render_agents() {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'Brak uprawnień do przeglądania tej strony.', 'estateoffice' ) );
}

printf(
'<div class="wrap"><h1>%s</h1><p>%s</p></div>',
esc_html__( 'Agenci', 'estateoffice' ),
esc_html__( 'Zarządzanie agentami zostanie wdrożone w kolejnych wersjach.', 'estateoffice' )
);
}

/**
 * Render license placeholder.
 */
public static function render_license() {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'Brak uprawnień do przeglądania tej strony.', 'estateoffice' ) );
}

printf(
'<div class="wrap"><h1>%s</h1><p>%s</p></div>',
esc_html__( 'Licencja', 'estateoffice' ),
esc_html__( 'Funkcjonalność zostanie dodana na końcu procesu tworzenia wtyczki.', 'estateoffice' )
);
}
}
