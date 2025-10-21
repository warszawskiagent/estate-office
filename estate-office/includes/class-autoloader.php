<?php
namespace EstateOffice;

/**
 * Simple PSR-4 like autoloader for the plugin classes.
 */
class Autoloader {
/**
 * Register autoloader.
 *
 * @return void
 */
public static function init() {
spl_autoload_register( array( self::class, 'autoload' ) );
}

/**
 * Autoload callback.
 *
 * @param string $class Class name.
 *
 * @return void
 */
protected static function autoload( $class ) {
if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
return;
}

$relative = str_replace( __NAMESPACE__ . '\\', '', $class );
$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
$relative_lower = strtolower( $relative );

$path = ESTATE_OFFICE_PATH . 'includes/' . $relative_lower . '.php';

if ( file_exists( $path ) ) {
require_once $path;
return;
}

$parts = explode( DIRECTORY_SEPARATOR, $relative_lower );
$file  = 'class-' . str_replace( '_', '-', array_pop( $parts ) ) . '.php';
$path  = ESTATE_OFFICE_PATH . 'includes/' . ( $parts ? implode( DIRECTORY_SEPARATOR, $parts ) . DIRECTORY_SEPARATOR : '' ) . $file;

if ( file_exists( $path ) ) {
require_once $path;
}
}
}
