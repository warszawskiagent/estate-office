<?php
namespace EstateOffice;

use EstateOffice\Admin\Agents_Page;
use EstateOffice\Admin\Menu;
use EstateOffice\Admin\Settings;
use EstateOffice\Metadata;
use EstateOffice\Post_Types;
use EstateOffice\Rest\Router;
use EstateOffice\Roles;

/**
 * Core plugin class.
 */
class Plugin {
/**
 * Singleton instance.
 *
 * @var Plugin
 */
protected static $instance;

/**
 * Plugin constructor.
 */
protected function __construct() {
$this->load_dependencies();
$this->register_hooks();
}

/**
 * Get singleton instance.
 *
 * @return Plugin
 */
public static function get_instance() {
if ( null === self::$instance ) {
self::$instance = new self();
}

return self::$instance;
}

/**
 * Load required classes.
 *
 * @return void
 */
protected function load_dependencies() {
new Roles();
new Metadata();
new Post_Types();
new Menu();
new Settings();
new Agents_Page();
new Router();
}

/**
 * Register plugin wide hooks.
 *
 * @return void
 */
protected function register_hooks() {
add_action( 'init', array( $this, 'load_textdomain' ) );
add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
}

/**
 * Load translations.
 *
 * @return void
 */
public function load_textdomain() {
load_plugin_textdomain( 'estate-office', false, dirname( plugin_basename( ESTATE_OFFICE_PATH . 'estate-office.php' ) ) . '/languages' );
}

/**
 * Enqueue admin styles and scripts.
 *
 * @param string $hook Hook suffix.
 *
 * @return void
 */
public function enqueue_admin_assets( $hook ) {
if ( false === strpos( $hook, 'estate-office' ) ) {
return;
}

wp_enqueue_media();

wp_enqueue_style(
'estate-office-admin',
ESTATE_OFFICE_URL . 'assets/css/admin.css',
array(),
estate_office_asset_version( 'assets/css/admin.css' )
);

wp_enqueue_script(
'estate-office-admin',
ESTATE_OFFICE_URL . 'assets/js/admin.js',
array( 'jquery', 'wp-api-fetch' ),
estate_office_asset_version( 'assets/js/admin.js' ),
true
);

wp_localize_script(
'estate-office-admin',
'estateOffice',
array(
'nonce'           => wp_create_nonce( 'estate_office_nonce' ),
'api'             => rest_url( 'estate-office/v1' ),
'i18n'            => array(
'step'              => __( 'Krok', 'estate-office' ),
'next'              => __( 'Dalej', 'estate-office' ),
'previous'          => __( 'Wstecz', 'estate-office' ),
'save'              => __( 'Zapisz', 'estate-office' ),
'searchPlaceholder' => __( 'Wyszukaj klienta...', 'estate-office' ),
'noResults'         => __( 'Brak wyników', 'estate-office' ),
),
'contractStages' => Contract::get_stages(),
)
);
}
}
