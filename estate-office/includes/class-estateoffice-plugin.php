<?php
/**
 * Main plugin bootstrap.
 *
 * @package EstateOffice
 */

namespace EstateOffice;

defined( 'ABSPATH' ) || exit;

use EstateOffice\Admin\Assets;
use EstateOffice\Admin\Menu;
use EstateOffice\Admin\Settings;
use EstateOffice\PostTypes\ClientPostType;
use EstateOffice\PostTypes\ContractPostType;
use EstateOffice\PostTypes\PropertyPostType;
use EstateOffice\PostTypes\SearchPostType;
use EstateOffice\Roles\AgentRole;

/**
 * Plugin orchestrator class.
 */
final class Plugin {
/**
 * The single instance of the class.
 *
 * @var Plugin|null
 */
private static $instance = null;

/**
 * Plugin constructor.
 */
private function __construct() {
$this->register_autoloader();
$this->init_hooks();
}

/**
 * Retrieve singleton instance.
 *
 * @return Plugin
 */
public static function instance() {
if ( null === self::$instance ) {
self::$instance = new self();
}

return self::$instance;
}

/**
 * Register autoloader for plugin classes.
 */
private function register_autoloader() {
spl_autoload_register(
function ( $class ) {
if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
return;
}

$relative = str_replace( __NAMESPACE__ . '\\', '', $class );
$relative = preg_replace( '/([a-z])([A-Z])/u', '$1-$2', $relative );
$relative = strtolower( str_replace( '_', '-', $relative ) );
$relative = str_replace( '\\', '/', $relative );
$path     = ESTATEOFFICE_DIR . 'includes/' . $relative . '.php';

if ( file_exists( $path ) ) {
require_once $path;
}
}
);
}

/**
 * Initialize hooks.
 */
private function init_hooks() {
register_activation_hook( ESTATEOFFICE_FILE, [ AgentRole::class, 'activate' ] );
register_deactivation_hook( ESTATEOFFICE_FILE, [ AgentRole::class, 'deactivate' ] );

add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
add_action( 'init', [ $this, 'register_post_types' ] );
add_action( 'init', [ AgentRole::class, 'register_caps' ] );
add_action( 'admin_menu', [ Menu::class, 'register' ] );
add_action( 'admin_enqueue_scripts', [ Assets::class, 'enqueue_admin_assets' ] );
add_action( 'admin_init', [ Settings::class, 'register_settings' ] );
}

/**
 * Load plugin translations.
 */
public function load_textdomain() {
load_plugin_textdomain( 'estateoffice', false, dirname( plugin_basename( ESTATEOFFICE_FILE ) ) . '/languages' );
}

/**
 * Register custom post types.
 */
public function register_post_types() {
PropertyPostType::register();
ContractPostType::register();
ClientPostType::register();
SearchPostType::register();
}
}
